<?php

namespace OllamaChat\Fewo;

defined('C5_EXECUTE') or die('Access Denied.');

/** Fixed texts of the holiday apartment assistant, in German and English, and formatting of dates and amounts. */
final class FewoTexts
{
    private const TEXTS = [
        'de' => [
            'other' => 'Ich gebe Auskunft zu freien Terminen und Preisen der Ferienwohnung. Fragen Sie zum Beispiel: „Ist vom 3. bis 10. August für zwei Personen frei, und was kostet das?“ Bei allen anderen Fragen wenden Sie sich bitte direkt an uns.',
            'ask_dates' => 'Für welchen Zeitraum möchten Sie anfragen? Nennen Sie mir bitte Anreise, Abreise und die Anzahl der Gäste, zum Beispiel: „vom 3. bis 10. August zu zweit“.',
            'past' => 'Das Datum %s liegt in der Vergangenheit. Bitte nennen Sie einen Zeitraum in der Zukunft.',
            'unknown' => 'Ob die Ferienwohnung %s frei ist, kann ich gerade nicht sicher sagen, weil der Belegungsplan nicht aktuell ist. Bitte fragen Sie direkt bei uns an.',
            'occupied' => '%s ist die Ferienwohnung leider schon belegt.',
            'free' => 'Gute Nachricht: %s (%s) ist die Ferienwohnung laut Belegungsplan frei.',
            'on_request' => '%s (%s) ist die Ferienwohnung nur auf Anfrage verfügbar. Bitte fragen Sie direkt bei uns an.',
            'ask_guests' => 'Für den Preis nennen Sie mir bitte noch die Anzahl der Gäste.',
            'price' => 'Der Preis für %s und %s beträgt **%s** (%s).',
            'deposit' => 'Bei der Buchung ist eine Anzahlung von %d %% fällig.',
            'no_price' => 'Einen Preis kann ich dafür nicht nennen: %s',
            'disclaimer' => 'Alle Angaben sind unverbindlich. Verbindlich wird eine Buchung erst mit unserer Bestätigung.',
            'alternatives' => 'In der Nähe ist die Ferienwohnung zum Beispiel hier frei (Anreise bis Abreise):',
            'no_alternatives' => 'In den Wochen davor und danach ist leider kein ausreichend langer Zeitraum frei. Fragen Sie gern nach einem anderen Zeitraum.',
            'none_free' => '%s ist die Ferienwohnung leider komplett belegt.',
            'free_list' => '%s ist die Ferienwohnung in diesen Zeiträumen frei (Anreise bis Abreise):',
            'more_periods' => '- … und weitere',
            'min_stay' => 'Der Mindestaufenthalt beträgt %d Nächte.',
            'ask_for_price' => 'Für einen genauen Preis nennen Sie mir Anreise, Abreise und die Anzahl der Gäste.',
            'price_list' => 'Unsere Preise pro Nacht:',
            'season' => '%s (%s): %s',
            'price_for' => '%s für %s',
            'conditions' => 'Dazu kommt einmalig die Endreinigung von %s. Der Mindestaufenthalt beträgt %d Nächte, Platz ist für höchstens %d Gäste.',
            'free_from' => 'ab %s, frei bis mindestens %s',
            'free_period' => '%s (%s)',
            'cleaning' => 'Endreinigung',
            'nights_times' => '%s × %s (%s)',
            'period' => 'vom %s bis %s',
            'range' => '%s – %s',
            'reason_guests_missing' => 'Die Anzahl der Gäste fehlt.',
            'reason_past' => 'Das Anreisedatum liegt in der Vergangenheit.',
            'reason_order' => 'Die Abreise muss nach der Anreise liegen.',
            'reason_min_stay' => 'Der Mindestaufenthalt beträgt %d Nächte.',
            'reason_max_guests' => 'Die Ferienwohnung bietet Platz für höchstens %d Gäste.',
            'reason_no_prices' => 'Für diesen Zeitraum sind noch keine Preise hinterlegt.',
            'reason_partial_prices' => 'Für einen Teil dieses Zeitraums sind noch keine Preise hinterlegt.',
            'reason_no_price_for_guests' => 'Für %d Personen ist in diesem Zeitraum kein Preis hinterlegt.',
            'reason_no_list' => 'Es ist keine Preisliste hinterlegt.',
            'reason_external_failed' => 'Der Preis konnte gerade nicht berechnet werden.',
            'reason_external' => '%s',
        ],
        'en' => [
            'other' => 'I can tell you about free dates and prices of the holiday apartment. Ask for example: “Is it available from 3 to 10 August for two people, and how much is it?” For anything else, please contact us directly.',
            'ask_dates' => 'Which dates are you interested in? Please tell me your arrival, departure and the number of guests, for example: “from 3 to 10 August for two”.',
            'past' => 'The date %s is in the past. Please choose dates in the future.',
            'unknown' => 'I cannot say for sure whether the apartment is available %s, because the occupancy calendar is not up to date. Please contact us directly.',
            'occupied' => 'Unfortunately, the apartment is already booked %s.',
            'free' => 'Good news: the apartment is available %s (%s) according to the occupancy calendar.',
            'on_request' => 'The apartment is available %s (%s) on request only. Please contact us directly.',
            'ask_guests' => 'For the price, please also tell me the number of guests.',
            'price' => 'The price for %s and %s is **%s** (%s).',
            'deposit' => 'A deposit of %d %% is due when booking.',
            'no_price' => 'I cannot give a price for this: %s',
            'disclaimer' => 'All information is without guarantee. A booking is only binding once we have confirmed it.',
            'alternatives' => 'Nearby, the apartment is available for example (arrival to departure):',
            'no_alternatives' => 'Unfortunately, there is no period long enough in the weeks before and after. Feel free to ask about other dates.',
            'none_free' => 'Unfortunately, the apartment is fully booked %s.',
            'free_list' => 'The apartment is available in these periods %s (arrival to departure):',
            'more_periods' => '- … and more',
            'min_stay' => 'The minimum stay is %d nights.',
            'ask_for_price' => 'For an exact price, tell me your arrival, departure and the number of guests.',
            'price_list' => 'Our prices per night:',
            'season' => '%s (%s): %s',
            'price_for' => '%s for %s',
            'conditions' => 'A one-off final cleaning fee of %s is added. The minimum stay is %d nights, and the apartment sleeps up to %d guests.',
            'free_from' => 'from %s, free until at least %s',
            'free_period' => '%s (%s)',
            'cleaning' => 'Final cleaning',
            'nights_times' => '%s × %s (%s)',
            'period' => 'from %s to %s',
            'range' => '%s – %s',
            'reason_guests_missing' => 'The number of guests is missing.',
            'reason_past' => 'The arrival date is in the past.',
            'reason_order' => 'The departure must be after the arrival.',
            'reason_min_stay' => 'The minimum stay is %d nights.',
            'reason_max_guests' => 'The apartment sleeps up to %d guests.',
            'reason_no_prices' => 'There are no prices for these dates yet.',
            'reason_partial_prices' => 'There are no prices for part of these dates yet.',
            'reason_no_price_for_guests' => 'There is no price for %d people in this period.',
            'reason_no_list' => 'There is no price list.',
            'reason_external_failed' => 'The price could not be calculated right now.',
            'reason_external' => '%s',
        ],
    ];

    private const WEEKDAYS = ['de' => ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'], 'en' => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']];

    /** @var string */
    private $language;

    public function __construct(string $language)
    {
        $this->language = $language === 'en' ? 'en' : 'de';
    }

    /** @param mixed ...$args values for the placeholders */
    public function text(string $key, ...$args): string
    {
        $text = $args === [] ? self::TEXTS[$this->language][$key] : vsprintf(self::TEXTS[$this->language][$key], $args);

        return ucfirst($text);
    }

    /** @param array{key: string, args: array} $reason */
    public function reason(array $reason): string
    {
        return $this->text('reason_' . $reason['key'], ...$reason['args']);
    }

    public function day(\DateTimeImmutable $date): string
    {
        $weekday = self::WEEKDAYS[$this->language][(int) $date->format('w')];

        return $this->language === 'en' ? $weekday . ', ' . $date->format('j M Y') : $weekday . ', ' . $date->format('d.m.Y');
    }

    /** "vom Mo, 03.08.2027 bis Mo, 10.08.2027" */
    public function period(\DateTimeImmutable $from, \DateTimeImmutable $to): string
    {
        return lcfirst(vsprintf(self::TEXTS[$this->language]['period'], [$this->day($from), $this->day($to)]));
    }

    /** "Mo, 03.08.2027 – Mo, 10.08.2027" */
    public function range(\DateTimeImmutable $from, \DateTimeImmutable $to): string
    {
        return vsprintf(self::TEXTS[$this->language]['range'], [$this->day($from), $this->day($to)]);
    }

    public function money(float $amount): string
    {
        return $this->language === 'en' ? '€' . number_format($amount, 2, '.', ',') : number_format($amount, 2, ',', '.') . ' €';
    }

    public function nights(int $count): string
    {
        if ($this->language === 'en') {
            return $count . ($count === 1 ? ' night' : ' nights');
        }

        return $count . ($count === 1 ? ' Nacht' : ' Nächte');
    }

    public function guests(int $count): string
    {
        if ($this->language === 'en') {
            return $count . ($count === 1 ? ' guest' : ' guests');
        }

        return $count . ($count === 1 ? ' Gast' : ' Gäste');
    }

    public function persons(int $min, int $max): string
    {
        $count = $min === $max ? (string) $min : $min . '–' . $max;
        $plural = $max > 1;
        if ($this->language === 'en') {
            return $count . ($plural ? ' people' : ' person');
        }

        return $count . ($plural ? ' Personen' : ' Person');
    }
}
