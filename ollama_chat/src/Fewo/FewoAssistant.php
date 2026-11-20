<?php

namespace OllamaChat\Fewo;

use OllamaChat\OllamaClient;
use OllamaChat\OllamaException;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Answers guests' questions about free dates and prices of the holiday apartment.
 *
 * The model only understands the question (RequestExtractor). Availability comes from the occupancy plan's iCal
 * feed, prices from the Fewo Toolkit's price calculation, and the answer is put together from fixed texts in
 * German or English. In tests, llama3 did not stick to given facts when phrasing answers itself: it calculated
 * prices on its own and got them wrong. Dates and prices for guests must be exact, so the model does not phrase them.
 *
 * Nothing is stored; the history comes from the browser with every question.
 */
final class FewoAssistant
{
    /** How far ahead free periods are listed when the guest names no period. */
    private const DEFAULT_SEARCH_MONTHS = 3;

    /** Free periods are listed at most this far ahead (the price calculator allows 18 months). */
    private const MAX_SEARCH_MONTHS = 18;

    /** Alternatives for an occupied stay are searched within this many days before and after it. */
    private const ALTERNATIVE_DAYS = 30;

    private const MAX_LISTED_PERIODS = 8;

    private const MAX_ALTERNATIVES = 3;

    /** @var FewoTexts */
    private $t;

    /**
     * @param array<int, array{role: string, content: string}> $history earlier questions and answers, oldest first
     * @param mixed $previous the request understood from the previous question, sent back by the page
     *
     * @throws OllamaException
     * @throws FewoUnavailableException
     *
     * @return array{answer: string, request: array} the answer, and the understood request for the next question
     */
    public function answer(FewoBlockSettings $settings, string $question, array $history, $previous = null): array
    {
        $sources = FewoSources::detect();
        if (!$sources->hasCalendar() && !$sources->hasPrices()) {
            throw new FewoUnavailableException('Kein Belegungsplan und keine Preisliste des Fewo Toolkits gefunden.');
        }
        $today = new \DateTimeImmutable('today');
        $request = RequestExtractor::extract(new OllamaClient($settings->ollama), $history, $question, $today);
        RequestExtractor::applyPrevious($request, $previous, $question, $today);
        $this->t = new FewoTexts($request->language);

        return ['answer' => $this->compose($request, $sources, $settings, $today), 'request' => $request->toPrevious()];
    }

    private function compose(StayRequest $request, FewoSources $sources, FewoBlockSettings $settings, \DateTimeImmutable $today): string
    {

        $calendar = null;
        if ($sources->hasCalendar()) {
            $loaded = OccupancyCalendar::load($sources->feedUrl, $sources->refreshMinutes);
            $dataTime = $loaded->dataTime();
            // An outdated occupancy plan must not lead to "free": then the availability is unknown.
            if ($dataTime !== null && time() - $dataTime <= $settings->maxDataAgeHours * 3600) {
                $calendar = $loaded;
            }
        }
        $prices = new PriceCalculator($sources);

        switch ($request->intent) {
            case StayRequest::INTENT_STAY:
                return $this->stay($request, $calendar, $prices, $sources->hasPrices(), $today);
            case StayRequest::INTENT_FREE_PERIODS:
                return $this->freePeriods($request, $calendar, $prices, $today);
            case StayRequest::INTENT_PRICES:
                return $this->prices($prices);
            default:
                return $this->t->text('other');
        }
    }

    private function stay(StayRequest $request, ?OccupancyCalendar $calendar, PriceCalculator $prices, bool $hasPrices, \DateTimeImmutable $today): string
    {
        $t = $this->t;
        if (!$request->hasDates()) {
            return $t->text('ask_dates');
        }
        $period = $t->period($request->arrival, $request->departure);
        if ($request->arrival < $today) {
            return $t->text('past', $t->day($request->arrival));
        }

        $parts = [];
        $status = 'unknown';
        if ($calendar === null) {
            $parts[] = $t->text('unknown', $period);
        } else {
            $status = $calendar->check($request->arrival, $request->departure)['status'];
            if ($status === 'occupied') {
                $parts[] = $t->text('occupied', $period);
                $parts[] = $this->alternatives($request, $calendar, $prices, $today);

                return implode("\n\n", $parts);
            }
            $parts[] = $t->text($status === 'free' ? 'free' : 'on_request', $period, $t->nights($request->nights()));
        }

        $namedPrice = false;
        if ($hasPrices) {
            if ($request->guests === null) {
                $parts[] = $t->text('ask_guests');
            } else {
                $price = $prices->calculate($request->arrival, $request->departure, $request->guests);
                if ($price['ok']) {
                    $namedPrice = true;
                    $parts[] = $t->text('price', $t->nights($request->nights()), $t->guests($request->guests), $t->money($price['amount']), $this->breakdown($price['items']))
                        . ($price['deposit'] > 0 ? ' ' . $t->text('deposit', $price['deposit']) : '');
                } else {
                    $parts[] = $t->text('no_price', $t->reason($price['reason']));
                }
            }
        }
        if ($status !== 'unknown' || $namedPrice) {
            $parts[] = $t->text('disclaimer');
        }

        return implode("\n\n", $parts);
    }

    private function freePeriods(StayRequest $request, ?OccupancyCalendar $calendar, PriceCalculator $prices, \DateTimeImmutable $today): string
    {
        $t = $this->t;
        $from = $request->searchFrom !== null && $request->searchFrom > $today ? $request->searchFrom : $today;
        $to = $request->searchTo ?? $from->modify('+' . self::DEFAULT_SEARCH_MONTHS . ' months -1 day');
        $to = min($to, $today->modify('+' . self::MAX_SEARCH_MONTHS . ' months'));
        if ($to < $from) {
            return $t->text('past', $t->day($request->searchTo ?? $from));
        }
        if ($calendar === null) {
            return $t->text('unknown', $t->period($from, $to));
        }

        $until = $to->modify('+1 day'); // the last searched night is the night before
        $free = $calendar->freePeriods($from, $until);
        if ($free === []) {
            return $t->text('none_free', $t->period($from, $to));
        }
        $lines = [$t->text('free_list', $t->period($from, $to))];
        foreach (array_slice($free, 0, self::MAX_LISTED_PERIODS) as $p) {
            $lines[] = '- ' . $this->describeFree($p, $until);
        }
        if (count($free) > self::MAX_LISTED_PERIODS) {
            $lines[] = $t->text('more_periods');
        }
        $overview = $prices->overview();
        $text = implode("\n", $lines);
        if ($overview !== null && $overview['minNights'] > 1) {
            $text .= "\n\n" . $t->text('min_stay', $overview['minNights']);
        }

        return $text . "\n\n" . $t->text('ask_for_price');
    }

    private function prices(PriceCalculator $prices): string
    {
        $t = $this->t;
        $overview = $prices->overview();
        if ($overview === null || $overview['seasons'] === []) {
            return $t->text('ask_dates');
        }
        $lines = [$t->text('price_list')];
        foreach ($overview['seasons'] as $season) {
            $lines[] = '- ' . $t->text('season', $season['name'], $t->range(new \DateTimeImmutable($season['from']), new \DateTimeImmutable($season['to'])),
                implode(', ', array_map(function ($p) use ($t) {
                    return $t->text('price_for', $t->money($p['price']), $t->persons($p['min'], $p['max']));
                }, $season['prices'])));
        }

        return implode("\n", $lines) . "\n\n"
            . $t->text('conditions', $t->money($overview['cleaning']), $overview['minNights'], $overview['maxGuests'])
            . "\n\n" . $t->text('ask_for_price');
    }

    /** Free periods near an occupied stay that are long enough for the minimum stay. */
    private function alternatives(StayRequest $request, OccupancyCalendar $calendar, PriceCalculator $prices, \DateTimeImmutable $today): string
    {
        $overview = $prices->overview();
        $minNights = max(1, $overview !== null ? $overview['minNights'] : 1);
        $from = max($today, $request->arrival->modify('-' . self::ALTERNATIVE_DAYS . ' days'));
        $until = $request->departure->modify('+' . self::ALTERNATIVE_DAYS . ' days');
        $found = [];
        foreach ($calendar->freePeriods($from, $until) as $p) {
            $end = $p['departure'] ?? $until->format('Y-m-d');
            if ((new \DateTimeImmutable($p['arrival']))->diff(new \DateTimeImmutable($end))->days >= $minNights) {
                $found[] = '- ' . $this->describeFree($p, $until);
            }
        }
        if ($found === []) {
            return $this->t->text('no_alternatives');
        }

        return $this->t->text('alternatives') . "\n" . implode("\n", array_slice($found, 0, self::MAX_ALTERNATIVES));
    }

    /** @param array{arrival: string, departure: string|null} $period */
    private function describeFree(array $period, \DateTimeImmutable $until): string
    {
        $t = $this->t;
        $arrival = new \DateTimeImmutable($period['arrival']);
        if ($period['departure'] === null) {
            return $t->text('free_from', $t->day($arrival), $t->day($until));
        }
        $departure = new \DateTimeImmutable($period['departure']);

        return $t->text('free_period', $t->range($arrival, $departure), $t->nights((int) $arrival->diff($departure)->days));
    }

    /** "5 Nächte × 80,00 € (Hauptsaison), Endreinigung 60,00 €" from the items of the price calculation. */
    private function breakdown(array $items): string
    {
        $t = $this->t;
        $groups = [];
        $extras = [];
        foreach ($items as $item) {
            if (($item['type'] ?? '') === 'season') {
                $key = $item['name'] . '|' . $item['price'];
                $groups[$key] = ($groups[$key] ?? 0) + 1;
            } elseif (isset($item['price'])) {
                $extras[] = ($item['type'] === 'cleaning' ? $t->text('cleaning') : (string) $item['name']) . ' ' . $t->money((float) $item['price']);
            }
        }
        $parts = [];
        foreach ($groups as $key => $count) {
            [$name, $price] = explode('|', $key, 2);
            $parts[] = $t->text('nights_times', $t->nights($count), $t->money((float) $price), $name);
        }

        return implode(', ', array_merge($parts, $extras));
    }
}
