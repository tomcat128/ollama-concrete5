<?php

namespace OllamaChat\Fewo;

use GuzzleHttp\Client;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Rental prices from the Fewo Toolkit. The price itself is calculated by the toolkit's own method
 * Preisuebersicht::calculate(), the same one the price calculator on the website uses, so the chat always
 * names the same price.
 *
 * calculate() does not return errors: for invalid input it prints JSON and ends the request with die().
 * Every condition that leads there is therefore checked beforehand, with the same data and the same night
 * count as calculate() itself.
 */
final class PriceCalculator
{
    /** @var FewoSources */
    private $sources;

    /** @var array<string, mixed>|null price list as returned by Preisuebersicht::getPricelistById() */
    private $priceList;

    public function __construct(FewoSources $sources)
    {
        $this->sources = $sources;
    }

    /**
     * General conditions and season prices, for questions without specific dates.
     *
     * @return array{minNights: int, maxGuests: int, cleaning: float, deposit: int, seasons: array<int, array{name: string, from: string, to: string, prices: array<int, array{min: int, max: int, price: float}>}>}|null
     *                                                                                                                                                                                            null for an external price calculation
     */
    public function overview(): ?array
    {
        $list = $this->priceList();
        if ($list === null) {
            return null;
        }
        $seasons = [];
        foreach ($list['matrix'] ?? [] as $season) {
            $prices = [];
            foreach ($season['preise'] as $price => $persons) {
                $prices[] = ['min' => (int) $persons['min'], 'max' => (int) $persons['max'], 'price' => (float) $price];
            }
            usort($prices, function ($a, $b) {
                return $a['min'] <=> $b['min'];
            });
            $seasons[] = ['name' => (string) $season['bezeichnung'], 'from' => $season['datum_von'], 'to' => $season['datum_bis'], 'prices' => $prices];
        }

        return [
            'minNights' => (int) $list['config']['mindestaufenthaltsdauer'],
            'maxGuests' => (int) $list['config']['anzahl_gaeste'],
            'cleaning' => (float) $list['config']['gebuehr_endreinigung'],
            'deposit' => (int) $list['config']['anzahlung'],
            'seasons' => $seasons,
        ];
    }

    /**
     * @return array{ok: bool, reason?: array{key: string, args: array}, amount?: float, items?: array<int, array<string, mixed>>, deposit?: int}
     */
    public function calculate(\DateTimeImmutable $arrival, \DateTimeImmutable $departure, int $guests): array
    {
        if ($this->sources->priceType === 'EX') {
            return $this->calculateExternally($arrival, $departure, $guests);
        }
        $list = $this->priceList();
        if ($list === null) {
            return ['ok' => false, 'reason' => self::reason('no_list')];
        }
        $reason = $this->checkInput($list, $arrival, $departure, $guests);
        if ($reason !== null) {
            return ['ok' => false, 'reason' => $reason];
        }

        $class = FewoSources::PRICE_LIST_CLASS;
        $discount = ['per_rental' => 0, 'per_night' => 0, 'options' => ['per_night_type' => 'total', 'per_night_start' => 'total']];
        $finished = false;
        // Safety net in case calculate() still ends the request with die(): its output is turned into an
        // error message the chat understands.
        ob_start(function ($buffer) use (&$finished) {
            if ($finished) {
                return '';
            }
            $data = json_decode($buffer, true);
            $message = is_array($data) && isset($data['message']) ? ' ' . $data['message'] : '';

            return json_encode(['error' => 'Der Preis konnte nicht berechnet werden.' . $message]);
        });
        try {
            $result = $class::calculate(
                $this->sources->priceListId,
                $arrival->format('Y-m-d'),
                $departure->format('Y-m-d'),
                $guests,
                $this->sources->priceMode,
                true,
                true,
                $discount,
                0,
                []
            );
        } finally {
            $finished = true;
            ob_end_clean();
        }

        return [
            'ok' => true,
            'amount' => (float) $result['amount'],
            'items' => $result['pricelist'],
            'deposit' => (int) $result['commission'],
        ];
    }

    /**
     * The reason why calculate() would refuse the input, or null if it will calculate a price.
     *
     * @return array{key: string, args: array}|null
     */
    private function checkInput(array $list, \DateTimeImmutable $arrival, \DateTimeImmutable $departure, int $guests): ?array
    {
        $config = $list['config'];
        if ($guests < 1) {
            return self::reason('guests_missing');
        }
        if ($arrival->format('Y-m-d') < date('Y-m-d')) {
            return self::reason('past');
        }
        if ($arrival >= $departure) {
            return self::reason('order');
        }
        // Same night count as calculate(), including its rounding across daylight saving changes.
        $nights = (int) ((strtotime($departure->format('Y-m-d')) - strtotime($arrival->format('Y-m-d'))) / 86400);
        if ($nights < (int) $config['mindestaufenthaltsdauer']) {
            return self::reason('min_stay', (int) $config['mindestaufenthaltsdauer']);
        }
        if ($guests > (int) $config['anzahl_gaeste']) {
            return self::reason('max_guests', (int) $config['anzahl_gaeste']);
        }

        // Season of every day calculate() looks at; the last wins, as in calculate().
        $seasonOfDay = [];
        foreach ($list['matrix'] ?? [] as $index => $season) {
            for ($day = $season['datum_von']; $day <= $season['datum_bis']; $day = date('Y-m-d', strtotime($day . ' +1 day'))) {
                $seasonOfDay[$day] = $index;
            }
        }
        if (!isset($seasonOfDay[$arrival->format('Y-m-d')], $seasonOfDay[$departure->format('Y-m-d')])) {
            return self::reason('no_prices');
        }
        // calculate() only checks the arrival and departure day; a gap between seasons or a missing price for the
        // number of guests would silently leave nights out of the total. Refuse instead of naming a wrong price.
        for ($night = $arrival; $night < $departure; $night = $night->modify('+1 day')) {
            $charged = $this->sources->priceMode === 'N' ? $night->modify('+1 day') : $night;
            $index = $seasonOfDay[$charged->format('Y-m-d')] ?? null;
            if ($index === null) {
                return self::reason('partial_prices');
            }
            $hasPrice = false;
            foreach ($list['matrix'][$index]['preise'] as $persons) {
                $hasPrice = $hasPrice || ($guests >= $persons['min'] && $guests <= $persons['max']);
            }
            if (!$hasPrice) {
                return self::reason('no_price_for_guests', $guests);
            }
        }

        return null;
    }

    /** The price list of the toolkit, or null if there is none. */
    private function priceList(): ?array
    {
        if ($this->priceList === null && $this->sources->priceType === 'IN' && $this->sources->priceListId > 0) {
            $class = FewoSources::PRICE_LIST_CLASS;
            $list = $class::getPricelistById($this->sources->priceListId);
            $this->priceList = isset($list['config']) ? $list : null;
        }

        return $this->priceList;
    }

    /** External price calculation, called with the same parameters as by the toolkit's price calculator. */
    private function calculateExternally(\DateTimeImmutable $arrival, \DateTimeImmutable $departure, int $guests): array
    {
        try {
            $response = (new Client(['timeout' => 15]))->get($this->sources->externalPriceUrl, ['query' => [
                'start' => $arrival->format('d.m.Y'),
                'ende' => $departure->format('d.m.Y'),
                'personen' => $guests,
                'deposit' => 0,
            ]]);
            $data = json_decode((string) $response->getBody(), true);
        } catch (\Throwable $e) {
            return ['ok' => false, 'reason' => self::reason('external_failed')];
        }
        if (!is_array($data) || empty($data['status']) || !isset($data['amount'])) {
            return ['ok' => false, 'reason' => is_array($data) && !empty($data['message']) ? self::reason('external', (string) $data['message']) : self::reason('external_failed')];
        }

        return ['ok' => true, 'amount' => (float) $data['amount'], 'items' => $data['pricelist'] ?? [], 'deposit' => (int) ($data['commission'] ?? 0)];
    }

    /**
     * Reason for not naming a price, as a text key of FewoTexts (without the prefix "reason_") and its values.
     *
     * @param mixed ...$args
     *
     * @return array{key: string, args: array}
     */
    private static function reason(string $key, ...$args): array
    {
        return ['key' => $key, 'args' => $args];
    }
}
