<?php

namespace OllamaChat\Fewo;

use GuzzleHttp\Client;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Occupancy of the apartment from the iCal feed of the Fewo Toolkit's occupancy plan.
 *
 * Interpreted like the occupancy plan block: every event is an occupied period from its arrival day (DTSTART)
 * to its departure day (DTEND). The departure day is free from the afternoon, so a new stay may start on it.
 * Events titled "Auf Anfrage" mark periods that are available on request only.
 *
 * The feed is cached in the same file the occupancy plan block uses and downloaded again when it is older
 * than the block's refresh interval.
 */
final class OccupancyCalendar
{
    private const ON_REQUEST_SUMMARY = 'Auf Anfrage';

    private const DOWNLOAD_TIMEOUT_SECONDS = 10;

    /** @var array<int, array{arrival: string, departure: string, onRequest: bool}> dates as Y-m-d, sorted by arrival */
    private $periods = [];

    /** @var int|null time of the feed data (modification time of the cache file) */
    private $dataTime;

    public static function load(string $feedUrl, int $refreshMinutes): self
    {
        $calendar = new self();
        $file = self::cacheFile($feedUrl);
        if (!is_file($file) || time() - filemtime($file) > $refreshMinutes * 60) {
            self::download($feedUrl, $file);
        }
        if (is_file($file)) {
            $calendar->dataTime = filemtime($file);
            $calendar->parse((string) file_get_contents($file));
        }

        return $calendar;
    }

    /** Time of the data, or null if there is none. */
    public function dataTime(): ?int
    {
        return $this->dataTime;
    }

    /**
     * @return array{status: string, conflicts: array<int, array{arrival: string, departure: string, onRequest: bool}>}
     *               status is "free", "occupied" or "on_request"
     */
    public function check(\DateTimeImmutable $arrival, \DateTimeImmutable $departure): array
    {
        $from = $arrival->format('Y-m-d');
        $to = $departure->format('Y-m-d');
        $conflicts = array_values(array_filter($this->periods, function ($p) use ($from, $to) {
            // Overlap of the nights: a stay may start on the departure day of the previous one.
            return $p['arrival'] < $to && $p['departure'] > $from;
        }));

        $status = 'free';
        foreach ($conflicts as $conflict) {
            $status = $conflict['onRequest'] && $status !== 'occupied' ? 'on_request' : 'occupied';
        }

        return ['status' => $status, 'conflicts' => $conflicts];
    }

    /**
     * Free periods between two dates. A period without departure is free until at least $until.
     *
     * @return array<int, array{arrival: string, departure: string|null}> dates as Y-m-d
     */
    public function freePeriods(\DateTimeImmutable $from, \DateTimeImmutable $until): array
    {
        $cursor = $from->format('Y-m-d');
        $end = $until->format('Y-m-d');
        $free = [];
        foreach ($this->periods as $period) {
            if ($period['departure'] <= $cursor) {
                continue;
            }
            if ($period['arrival'] >= $end) {
                break;
            }
            if ($period['arrival'] > $cursor) {
                $free[] = ['arrival' => $cursor, 'departure' => $period['arrival']];
            }
            $cursor = max($cursor, $period['departure']);
        }
        if ($cursor < $end) {
            $free[] = ['arrival' => $cursor, 'departure' => null];
        }

        return $free;
    }

    private function parse(string $ics): void
    {
        // Undo the line folding of iCal (RFC 5545): a line break followed by a space or tab continues the line.
        $ics = preg_replace("/\r?\n[ \t]/", '', $ics);
        if (!preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $ics, $events)) {
            return;
        }
        $today = date('Y-m-d');
        foreach ($events[1] as $event) {
            $arrival = self::date($event, 'DTSTART');
            if ($arrival === null) {
                continue;
            }
            $departure = self::date($event, 'DTEND') ?: date('Y-m-d', strtotime($arrival . ' +1 day'));
            if ($departure < $arrival) {
                [$arrival, $departure] = [$departure, $arrival];
            }
            if ($departure < $today) {
                continue; // in the past
            }
            $summary = preg_match('/^SUMMARY(?:;[^:\r\n]*)?:(.*)$/m', $event, $m) ? trim($m[1]) : '';
            $this->periods[] = [
                'arrival' => $arrival,
                'departure' => $departure,
                'onRequest' => $summary === self::ON_REQUEST_SUMMARY,
            ];
        }
        usort($this->periods, function ($a, $b) {
            return strcmp($a['arrival'], $b['arrival']);
        });
    }

    /** Date of a property like "DTSTART:20260801" or "DTSTART;VALUE=DATE:20260801", as Y-m-d. */
    private static function date(string $event, string $property): ?string
    {
        if (!preg_match('/^' . $property . '(?:;[^:\r\n]*)?:(\d{4})(\d{2})(\d{2})/m', $event, $m)) {
            return null;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? "{$m[1]}-{$m[2]}-{$m[3]}" : null;
    }

    /** The cache file of the occupancy plan block, so both read and refresh the same data. */
    private static function cacheFile(string $feedUrl): string
    {
        $directory = DIR_APPLICATION . '/files/blocks/belegungsplan/homeaway';
        // The block derives the directory from the document root; use its method where that is available.
        $block = FewoSources::OCCUPANCY_CLASS;
        if (!empty($_SERVER['DOCUMENT_ROOT']) && class_exists($block) && method_exists($block, 'getTempPath')) {
            $directory = $block::getTempPath();
        }

        return $directory . '/' . basename($feedUrl);
    }

    /** Downloads the feed into the cache file; keeps the old file if the download fails. */
    private static function download(string $feedUrl, string $file): void
    {
        try {
            $response = (new Client(['timeout' => self::DOWNLOAD_TIMEOUT_SECONDS]))->get($feedUrl);
            $data = (string) $response->getBody();
        } catch (\Throwable $e) {
            return;
        }
        if (strpos($data, 'BEGIN:VCALENDAR') === false) {
            return; // not a calendar, e.g. an error page
        }
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0775, true);
        }
        @file_put_contents($file, $data);
    }
}
