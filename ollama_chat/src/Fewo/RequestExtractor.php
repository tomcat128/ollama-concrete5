<?php

namespace OllamaChat\Fewo;

use OllamaChat\OllamaClient;
use OllamaChat\OllamaException;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Lets the model understand the question: what the guest wants, for which dates, how many guests, in which
 * language. This is the model's only task; availability and prices are determined from the data afterwards and
 * the answer is put together from fixed texts. The model's JSON is checked strictly, and anything implausible is
 * treated as not given.
 */
final class RequestExtractor
{
    /** Earlier messages taken into account, so follow-up questions like "and for 3 people?" keep the dates. */
    private const HISTORY_MESSAGES = 6;

    private const WEEKDAYS = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];

    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'intent' => ['type' => 'string', 'enum' => ['stay', 'free_periods', 'prices', 'other']],
            'arrival' => ['type' => ['string', 'null']],
            'departure' => ['type' => ['string', 'null']],
            'nights' => ['type' => ['integer', 'null']],
            'guests' => ['type' => ['integer', 'null']],
            'search_from' => ['type' => ['string', 'null']],
            'search_to' => ['type' => ['string', 'null']],
            'language' => ['type' => 'string', 'enum' => ['de', 'en', 'other']],
        ],
        'required' => ['intent', 'arrival', 'departure', 'nights', 'guests', 'search_from', 'search_to', 'language'],
    ];

    /**
     * @param array<int, array{role: string, content: string}> $history
     *
     * @throws OllamaException
     */
    public static function extract(OllamaClient $client, array $history, string $question, \DateTimeImmutable $today): StayRequest
    {
        $transcript = '';
        foreach (array_slice($history, -self::HISTORY_MESSAGES) as $message) {
            $transcript .= ($message['role'] === 'user' ? 'Gast: ' : 'Assistent: ') . $message['content'] . "\n";
        }
        $transcript .= 'Gast: ' . $question;

        $system = sprintf(
            "Heute ist %s, der %s. Du liest Anfragen von Gästen einer Ferienwohnung. Bestimme für die letzte Nachricht des Gastes:\n"
            . "- intent: \"stay\", wenn er nach einem bestimmten Aufenthalt fragt (ob er frei ist oder was er kostet); "
            . "\"free_periods\", wenn er wissen will, wann (z. B. in einem Monat) frei ist; "
            . "\"prices\", wenn er allgemein nach Preisen fragt, ohne bestimmten Aufenthalt; sonst \"other\".\n"
            . "- arrival und departure: Anreise und Abreise im Format JJJJ-MM-TT. \"Vom 3. bis 10. August\" heißt Anreise 3. August, Abreise 10. August.\n"
            . "- nights: Anzahl der Nächte, falls er sie statt der Abreise nennt (\"eine Woche\" = 7).\n"
            . "- guests: Anzahl der Gäste; \"zu zweit\" = 2, Kinder zählen mit.\n"
            . "- search_from und search_to: bei \"free_periods\" der Zeitraum, in dem er sucht, als JJJJ-MM-TT (\"im Januar 2027\" = 2027-01-01 bis 2027-01-31).\n"
            . "- language: \"de\", wenn er Deutsch schreibt, \"en\" bei Englisch, sonst \"other\".\n"
            . "Nennt der Gast ein Datum ohne Jahr, ergänze das Jahr: Es ist das nächste Mal, an dem dieses Datum kommt. "
            . "Beispiel: \"vom 19. bis 26. August\" ergibt arrival \"%s\" und departure \"%s\". "
            . 'Angaben aus früheren Nachrichten gelten weiter, solange der Gast sie nicht ändert. '
            . 'Was der Gast gar nicht genannt hat, ist null; erfinde keine Daten oder Zahlen.',
            self::WEEKDAYS[(int) $today->format('w')],
            $today->format('d.m.Y'),
            self::withYear('08-19', $today)->format('Y-m-d'),
            self::withYear('08-26', $today)->format('Y-m-d')
        );
        $result = $client->chat(
            [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $transcript]],
            self::SCHEMA,
            ['temperature' => 0]
        );

        $data = json_decode($result['content'], true);
        $request = self::toRequest($data, $today);
        // "When is it free in July?" names a period to search in, not a stay, even if the model filled in
        // arrival and departure. "Wann"/"when" is the clearest signal for that.
        if ($request->hasDates() && ($data['intent'] ?? '') === StayRequest::INTENT_FREE_PERIODS && preg_match('/\b(wann|when)\b/iu', $question)) {
            $request->searchFrom = $request->arrival;
            $request->searchTo = $request->departure;
            $request->arrival = $request->departure = null;
            $request->intent = StayRequest::INTENT_FREE_PERIODS;
        }
        // The model often picks the wrong year for dates without one. If the guest names a date in this message
        // but no year, the year is set here: the next occurrence from today, the departure after the arrival.
        $namesYear = (bool) preg_match('/\b20\d{2}\b/', $question);
        if (!$namesYear && self::mentionsDate($question) && $request->arrival !== null) {
            $nights = $request->hasDates() ? $request->nights() : 0;
            $request->arrival = self::withYear($request->arrival->format('m-d'), $today);
            $request->departure = $nights > 0 ? $request->arrival->modify('+' . $nights . ' days') : null;
        }
        // A search period without a year ("when is it free in July?") is the next one that has not ended yet,
        // so "in November" during November means this November.
        if (!$namesYear && $request->searchFrom !== null && $request->searchTo !== null) {
            $days = (int) $request->searchFrom->diff($request->searchTo)->days;
            $request->searchTo = self::withYear($request->searchTo->format('m-d'), $today);
            $request->searchFrom = $request->searchTo->modify('-' . $days . ' days');
        }

        return $request;
    }

    /** @param mixed $data */
    private static function toRequest($data, \DateTimeImmutable $today): StayRequest
    {
        $request = new StayRequest();
        if (!is_array($data)) {
            return $request;
        }
        $request->language = ($data['language'] ?? '') === 'de' ? 'de' : (($data['language'] ?? '') === 'en' ? 'en' : 'de');
        $request->arrival = self::date($data['arrival'] ?? null);
        $request->departure = self::date($data['departure'] ?? null);
        $nights = isset($data['nights']) && is_int($data['nights']) ? $data['nights'] : 0;
        if ($request->arrival !== null && $request->departure === null && $nights > 0 && $nights <= 365) {
            $request->departure = $request->arrival->modify('+' . $nights . ' days');
        }
        // A departure before the arrival or a stay of more than a year is a misreading.
        if ($request->hasDates() && ($request->departure <= $request->arrival || $request->nights() > 365)) {
            $request->departure = null;
        }
        $guests = $data['guests'] ?? null;
        $request->guests = is_int($guests) && $guests >= 1 && $guests <= 50 ? $guests : null;

        $request->searchFrom = self::date($data['search_from'] ?? null);
        $request->searchTo = self::date($data['search_to'] ?? null);
        if ($request->searchFrom !== null && $request->searchTo !== null && $request->searchTo < $request->searchFrom) {
            $request->searchTo = null;
        }

        $intent = $data['intent'] ?? '';
        $request->intent = in_array($intent, [StayRequest::INTENT_STAY, StayRequest::INTENT_FREE_PERIODS, StayRequest::INTENT_PRICES], true)
            ? $intent : StayRequest::INTENT_OTHER;
        // Arrival and departure are the clearest signal, whatever the model classified.
        if ($request->hasDates()) {
            $request->intent = StayRequest::INTENT_STAY;
        }

        return $request;
    }

    /**
     * Completes a follow-up question like "and for 4 people?" with the dates of the previous request, which the
     * page sends along. The model does not reliably carry dates over from the history on its own.
     *
     * @param mixed $previous {arrival, departure, guests} as returned with the previous answer
     */
    public static function applyPrevious(StayRequest $request, $previous, string $question, \DateTimeImmutable $today): void
    {
        if (!is_array($previous) || $request->hasDates() || self::mentionsDate($question)
            || !in_array($request->intent, [StayRequest::INTENT_STAY, StayRequest::INTENT_PRICES, StayRequest::INTENT_OTHER], true)) {
            return;
        }
        $arrival = self::date($previous['arrival'] ?? null);
        $departure = self::date($previous['departure'] ?? null);
        if ($arrival === null || $departure === null || $departure <= $arrival || $arrival < $today) {
            return;
        }
        // Only a real follow-up: the guest changes the number of guests or asks about the same stay again.
        if ($request->guests === null && $request->intent !== StayRequest::INTENT_STAY) {
            return;
        }
        $request->arrival = $arrival;
        $request->departure = $departure;
        if ($request->guests === null && is_int($previous['guests'] ?? null) && $previous['guests'] >= 1 && $previous['guests'] <= 50) {
            $request->guests = $previous['guests'];
        }
        $request->intent = StayRequest::INTENT_STAY;
    }

    /** Whether the text names a day, like "19. August", "27.12." or "September 1". */
    private static function mentionsDate(string $text): bool
    {
        $months = 'jan|feb|mär|maer|mar|apr|mai|may|jun|jul|aug|sep|okt|oct|nov|dez|dec';

        return (bool) preg_match('/\\d{1,2}\\.\\s*\\d{1,2}\\.|\\d{1,2}\\.?\\s*(' . $months . ')|(' . $months . ')[a-zä]*\\s+\\d{1,2}\\b/iu', $text);
    }

    /** The first date with this month and day ("MM-DD") not before $notBefore; 29 February in the next leap year. */
    private static function withYear(string $monthDay, \DateTimeImmutable $notBefore): \DateTimeImmutable
    {
        [$month, $day] = array_map('intval', explode('-', $monthDay));
        $year = (int) $notBefore->format('Y');
        while (!checkdate($month, $day, $year) || sprintf('%04d-%02d-%02d', $year, $month, $day) < $notBefore->format('Y-m-d')) {
            ++$year;
        }

        return $notBefore->setDate($year, $month, $day);
    }

    /** @param mixed $value */
    private static function date($value): ?\DateTimeImmutable
    {
        if (!is_string($value)) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }
}
