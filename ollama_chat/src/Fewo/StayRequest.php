<?php

namespace OllamaChat\Fewo;

defined('C5_EXECUTE') or die('Access Denied.');

/** What the guest is asking, as understood by the model. Every field may be unknown. */
final class StayRequest
{
    /** A specific stay: is it free, what does it cost? */
    public const INTENT_STAY = 'stay';

    /** When is the apartment free (in a month or period)? */
    public const INTENT_FREE_PERIODS = 'free_periods';

    /** Prices in general, without a specific stay. */
    public const INTENT_PRICES = 'prices';

    /** Anything else: greetings, equipment, directions … */
    public const INTENT_OTHER = 'other';

    /** @var string one of the INTENT_ constants */
    public $intent = self::INTENT_OTHER;

    /** @var \DateTimeImmutable|null */
    public $arrival;

    /** @var \DateTimeImmutable|null */
    public $departure;

    /** @var int|null */
    public $guests;

    /** @var \DateTimeImmutable|null first day of the period in which the guest looks for free dates */
    public $searchFrom;

    /** @var \DateTimeImmutable|null last day of that period */
    public $searchTo;

    /** @var string "de" or "en"; the language of the answer */
    public $language = 'de';

    public function hasDates(): bool
    {
        return $this->arrival !== null && $this->departure !== null;
    }

    /**
     * What the page keeps and sends with the next question, so follow-up questions can refer to it.
     *
     * @return array{arrival: string|null, departure: string|null, guests: int|null}
     */
    public function toPrevious(): array
    {
        return [
            'arrival' => $this->arrival ? $this->arrival->format('Y-m-d') : null,
            'departure' => $this->departure ? $this->departure->format('Y-m-d') : null,
            'guests' => $this->guests,
        ];
    }

    public function nights(): int
    {
        return $this->hasDates() ? (int) $this->arrival->diff($this->departure)->days : 0;
    }
}
