<?php

namespace OllamaChat;

use Concrete\Core\Database\Connection\Connection;
use Concrete\Core\Support\Facade\Application;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Builds a plain-text overview of this Concrete installation from its database. The overview is given to the
 * language model as the only source for its answers.
 *
 * Only read queries are used. Password hashes, e-mail addresses, IP addresses and configuration secrets are
 * never included.
 */
final class InstallationContext
{
    private const MAX_PAGES = 150;

    private const MAX_MATCHED_PAGES = 8;

    private const MAX_USERS = 50;

    private const MAX_GROUP_MEMBERS_LISTED = 20;

    private const MAX_FILES = 20;

    private const MAX_LOG_ENTRIES = 10;

    /** Monolog level from which log entries are included (300 = warning). */
    private const MIN_LOG_LEVEL = 300;

    /** Words that say nothing about which page is meant; they are not used to search for pages. */
    private const STOP_WORDS = [
        'aber', 'alle', 'allen', 'auch', 'bitte', 'dass', 'dein', 'deine', 'denn', 'diese', 'diesem', 'dieser',
        'dieses', 'eine', 'einem', 'einen', 'einer', 'erstellt', 'geändert', 'gibt', 'haben', 'hast', 'hier',
        'inhalt', 'inhalte', 'kann', 'letzte', 'letzten', 'mein', 'meine', 'nach', 'nicht', 'noch', 'oder', 'page',
        'seit', 'seite', 'seiten', 'sind', 'über', 'viele', 'warum', 'welche', 'welchem', 'welchen', 'welcher',
        'welches', 'wann', 'wurde', 'wurden', 'wird', 'zuletzt', 'what', 'which', 'when', 'pages',
    ];

    /** @var \Concrete\Core\Application\Application */
    private $app;

    /** @var Connection */
    private $db;

    public function __construct()
    {
        $this->app = Application::getFacadeApplication();
        $this->db = $this->app->make(Connection::class);
    }

    /**
     * @param string $question the user's question; pages whose name or path matches it are described in detail
     * @param int $maxChars maximum length of the overview
     */
    public function build(string $question, int $maxChars): string
    {
        // Most important first: if the budget runs out, the sections at the end are shortened or left out.
        $sections = [
            'Allgemein' => 'general',
            'Umfang' => 'statistics',
            'Seiten passend zur Frage' => 'matchingPages',
            'Installierte Pakete' => 'packages',
            'Themes' => 'themes',
            'Seitentypen' => 'pageTypes',
            'Gruppen' => 'groups',
            'Benutzer' => 'users',
            'Verwendete Blocktypen' => 'blockTypes',
            'Seiten (zuletzt geänderte zuerst)' => 'pages',
            'Neueste Dateien' => 'files',
            'Letzte Warnungen und Fehler im Protokoll' => 'logEntries',
        ];

        $overview = '';
        foreach ($sections as $title => $method) {
            try {
                $lines = $method === 'matchingPages' ? $this->matchingPages($question) : $this->{$method}();
            } catch (\Throwable $e) {
                $lines = ['(konnte nicht gelesen werden: ' . $e->getMessage() . ')'];
            }
            if ($lines === []) {
                continue;
            }
            $remaining = $maxChars - mb_strlen($overview);
            if ($remaining < 200) {
                break;
            }
            $overview .= $this->limit('## ' . $title . "\n" . implode("\n", $lines), $remaining - 2) . "\n\n";
        }

        return rtrim($overview);
    }

    /** @return string[] */
    private function general(): array
    {
        $site = $this->app->make('site')->getSite();
        $config = $this->app->make('config');
        $theme = $this->db->fetchOne(
            'SELECT t.pThemeName FROM Sites s JOIN PageThemes t ON t.pThemeID = s.pThemeID WHERE s.siteID = ?',
            [$site->getSiteID()]
        );

        return [
            'Name der Website: ' . $site->getSiteName(),
            'Kanonische URL: ' . ($site->getSiteCanonicalURL() ?: 'nicht festgelegt'),
            'Concrete-Version: ' . $config->get('concrete.version'),
            'PHP-Version: ' . PHP_VERSION,
            'Datenbank-Server: ' . $this->db->fetchOne('SELECT VERSION()'),
            'Aktives Theme: ' . ($theme ?: 'unbekannt'),
            'Wartungsmodus: ' . $this->onOff($config->get('concrete.maintenance_mode')),
            'Cache allgemein: ' . $this->onOff($config->get('concrete.cache.enabled'))
                . ', Block-Cache: ' . $this->onOff($config->get('concrete.cache.blocks'))
                . ', Seiten-Cache (Full Page Cache): ' . $this->describePageCache($config->get('concrete.cache.pages'))
                . ', Cache für Overrides: ' . $this->onOff($config->get('concrete.cache.overrides')),
            'Fehler auf der Seite anzeigen: ' . $this->onOff($config->get('concrete.debug.display_errors'))
                . ', Detailgrad: ' . ($config->get('concrete.debug.detail') ?: 'message'),
            'Zeitzone des Servers: ' . date_default_timezone_get(),
            'Stand dieser Daten: ' . date('Y-m-d H:i'),
        ];
    }

    /** @return string[] */
    private function statistics(): array
    {
        return [
            'Seiten: ' . $this->db->fetchOne('SELECT COUNT(*) FROM Pages p WHERE ' . $this->contentPageCondition('p')),
            'Seiten im Papierkorb oder deaktiviert: ' . $this->db->fetchOne(
                'SELECT COUNT(*) FROM Pages p WHERE p.cIsActive = 0 AND p.cIsTemplate = 0 AND p.cIsSystemPage = 0 AND p.cIsDraft = 0'
            ),
            'Entwürfe: ' . $this->db->fetchOne('SELECT COUNT(*) FROM Pages WHERE cIsDraft = 1'),
            'Benutzer: ' . $this->db->fetchOne('SELECT COUNT(*) FROM Users')
                . ' (davon aktiv: ' . $this->db->fetchOne('SELECT COUNT(*) FROM Users WHERE uIsActive = 1') . ')',
            'Dateien: ' . $this->db->fetchOne('SELECT COUNT(*) FROM Files')
                . ', zusammen ' . $this->formatBytes((int) $this->db->fetchOne('SELECT COALESCE(SUM(fvSize), 0) FROM FileVersions WHERE fvIsApproved = 1')),
            'Installierte Pakete: ' . $this->db->fetchOne('SELECT COUNT(*) FROM Packages WHERE pkgIsInstalled = 1'),
            'Installierte Blocktypen: ' . $this->db->fetchOne('SELECT COUNT(*) FROM BlockTypes WHERE btIsInternal = 0'),
        ];
    }

    /**
     * Pages whose name or path contains a word of the question, described in detail. This lets the model answer
     * questions about a specific page even on large sites where the general page list is shortened.
     *
     * @return string[]
     */
    private function matchingPages(string $question): array
    {
        $words = $this->searchWords($question);
        if ($words === []) {
            return [];
        }
        $conditions = [];
        $params = [];
        foreach ($words as $word) {
            $conditions[] = '(cv.cvName LIKE ? OR pp.cPath LIKE ?)';
            $like = '%' . addcslashes($word, '%_\\') . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $rows = $this->db->fetchAllAssociative(
            $this->pageSelect() . ' AND (' . implode(' OR ', $conditions) . ') ORDER BY c.cDateModified DESC LIMIT ' . self::MAX_MATCHED_PAGES,
            $params
        );

        $lines = [];
        foreach ($rows as $row) {
            $lines[] = $this->describePage($row);
            if ($row['cvDescription'] !== null && trim($row['cvDescription']) !== '') {
                $lines[] = '  Beschreibung: ' . $this->oneLine($row['cvDescription']);
            }
            $lines[] = '  Versionen: ' . $this->db->fetchOne('SELECT COUNT(*) FROM CollectionVersions WHERE cID = ?', [$row['cID']]);
            $blocks = $this->db->fetchAllAssociative(
                'SELECT bt.btName, COUNT(*) AS n
                 FROM CollectionVersionBlocks cvb
                 JOIN Blocks b ON b.bID = cvb.bID
                 JOIN BlockTypes bt ON bt.btID = b.btID
                 WHERE cvb.cID = ? AND cvb.cvID = ?
                 GROUP BY bt.btName ORDER BY n DESC, bt.btName',
                [$row['cID'], $row['cvID']]
            );
            $lines[] = '  Blöcke: ' . ($blocks === [] ? 'keine' : implode(', ', array_map(function ($b) {
                return $b['btName'] . ' (' . $b['n'] . ')';
            }, $blocks)));
        }

        return $lines;
    }

    /** @return string[] */
    private function packages(): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT pkgName, pkgHandle, pkgVersion, pkgDateInstalled FROM Packages WHERE pkgIsInstalled = 1 ORDER BY pkgName'
        );
        if ($rows === []) {
            return ['keine zusätzlichen Pakete installiert'];
        }

        return array_map(function ($r) {
            return '- ' . $r['pkgName'] . ' (' . $r['pkgHandle'] . ') Version ' . $r['pkgVersion']
                . ', installiert am ' . $this->formatDate($r['pkgDateInstalled']);
        }, $rows);
    }

    /** @return string[] */
    private function themes(): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT t.pThemeName, t.pThemeHandle, p.pkgName
             FROM PageThemes t LEFT JOIN Packages p ON p.pkgID = t.pkgID
             ORDER BY t.pThemeName'
        );

        return array_map(function ($r) {
            return '- ' . $r['pThemeName'] . ' (' . $r['pThemeHandle'] . ')' . ($r['pkgName'] ? ', aus Paket ' . $r['pkgName'] : '');
        }, $rows);
    }

    /** @return string[] */
    private function pageTypes(): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT pt.ptName, pt.ptHandle, COUNT(p.cID) AS pages
             FROM PageTypes pt
             LEFT JOIN Pages p ON p.ptID = pt.ptID AND ' . $this->contentPageCondition('p') . '
             WHERE pt.ptIsInternal = 0
             GROUP BY pt.ptID, pt.ptName, pt.ptHandle
             ORDER BY pt.ptName'
        );

        return array_map(function ($r) {
            return '- ' . $r['ptName'] . ' (' . $r['ptHandle'] . '): ' . $r['pages'] . ' Seiten';
        }, $rows);
    }

    /** @return string[] */
    private function groups(): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT g.gID, g.gName, COUNT(u.uID) AS members,
                    GROUP_CONCAT(u.uName ORDER BY u.uName SEPARATOR \', \') AS memberNames
             FROM `Groups` g
             LEFT JOIN UserGroups ug ON ug.gID = g.gID
             LEFT JOIN Users u ON u.uID = ug.uID
             GROUP BY g.gID, g.gName
             ORDER BY g.gName'
        );

        return array_map(function ($r) {
            // Guests and registered users are implicit groups without stored members.
            if ((int) $r['gID'] === GUEST_GROUP_ID) {
                $members = 'alle Besucher';
            } elseif ((int) $r['gID'] === REGISTERED_GROUP_ID) {
                $members = 'alle angemeldeten Benutzer';
            } elseif ((int) $r['members'] === 0) {
                $members = 'keine Mitglieder';
            } elseif ((int) $r['members'] <= self::MAX_GROUP_MEMBERS_LISTED) {
                // Naming the members lets the model answer "who is in group X" without combining two lists.
                $members = $r['members'] . ' Mitglieder: ' . $r['memberNames'];
            } else {
                $members = $r['members'] . ' Mitglieder';
            }

            return '- ' . $r['gName'] . ': ' . $members;
        }, $rows);
    }

    /** @return string[] */
    private function users(): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT u.uID, u.uName, u.uIsActive, u.uDateAdded, u.uLastLogin, u.uNumLogins,
                    GROUP_CONCAT(g.gName ORDER BY g.gName SEPARATOR \', \') AS groupNames
             FROM Users u
             LEFT JOIN UserGroups ug ON ug.uID = u.uID
             LEFT JOIN `Groups` g ON g.gID = ug.gID
             GROUP BY u.uID, u.uName, u.uIsActive, u.uDateAdded, u.uLastLogin, u.uNumLogins
             ORDER BY u.uLastLogin DESC, u.uName
             LIMIT ' . self::MAX_USERS
        );

        return $this->withTruncationNote(array_map(function ($r) {
            $line = '- ' . $r['uName'] . ((int) $r['uID'] === USER_SUPER_ID ? ' (Super-Administrator)' : '')
                . ', ' . ($r['uIsActive'] ? 'aktiv' : 'deaktiviert')
                . ', Gruppen: ' . ($r['groupNames'] ?: 'keine')
                . ', registriert ' . $this->formatDate($r['uDateAdded'])
                . ', letzte Anmeldung ' . ((int) $r['uLastLogin'] > 0 ? date('Y-m-d H:i', (int) $r['uLastLogin']) : 'nie')
                . ', ' . (int) $r['uNumLogins'] . ' Anmeldungen';

            return $line;
        }, $rows), (int) $this->db->fetchOne('SELECT COUNT(*) FROM Users'), 'Benutzer');
    }

    /** @return string[] */
    private function blockTypes(): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT bt.btName, bt.btHandle, COUNT(*) AS uses, COUNT(DISTINCT cvb.cID) AS pages
             FROM CollectionVersionBlocks cvb
             JOIN CollectionVersions cv ON cv.cID = cvb.cID AND cv.cvID = cvb.cvID AND cv.cvIsApproved = 1
             JOIN Pages p ON p.cID = cvb.cID AND ' . $this->contentPageCondition('p') . '
             JOIN Blocks b ON b.bID = cvb.bID
             JOIN BlockTypes bt ON bt.btID = b.btID
             GROUP BY bt.btID, bt.btName, bt.btHandle
             ORDER BY uses DESC, bt.btName'
        );

        return array_map(function ($r) {
            return '- ' . $r['btName'] . ' (' . $r['btHandle'] . '): ' . $r['uses'] . '-mal auf ' . $r['pages'] . ' Seiten';
        }, $rows);
    }

    /** @return string[] */
    private function pages(): array
    {
        $rows = $this->db->fetchAllAssociative(
            $this->pageSelect() . ' ORDER BY c.cDateModified DESC LIMIT ' . self::MAX_PAGES
        );
        $total = (int) $this->db->fetchOne('SELECT COUNT(*) FROM Pages p WHERE ' . $this->contentPageCondition('p'));

        return $this->withTruncationNote(array_map([$this, 'describePage'], $rows), $total, 'Seiten');
    }

    /** @return string[] */
    private function files(): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT fv.fvFilename, fv.fvTitle, fv.fvSize, f.fDateAdded, u.uName
             FROM Files f
             JOIN FileVersions fv ON fv.fID = f.fID AND fv.fvIsApproved = 1
             LEFT JOIN Users u ON u.uID = f.uID
             ORDER BY f.fDateAdded DESC
             LIMIT ' . self::MAX_FILES
        );

        return $this->withTruncationNote(array_map(function ($r) {
            $title = ($r['fvTitle'] && $r['fvTitle'] !== $r['fvFilename']) ? ' „' . $this->oneLine($r['fvTitle']) . '“' : '';

            return '- ' . $r['fvFilename'] . $title . ', ' . $this->formatBytes((int) $r['fvSize'])
                . ', hochgeladen ' . $this->formatDate($r['fDateAdded']) . ($r['uName'] ? ' von ' . $r['uName'] : '');
        }, $rows), (int) $this->db->fetchOne('SELECT COUNT(*) FROM Files'), 'Dateien');
    }

    /** @return string[] */
    private function logEntries(): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT channel, time, message, level FROM Logs WHERE level >= ? ORDER BY logID DESC LIMIT ' . self::MAX_LOG_ENTRIES,
            [self::MIN_LOG_LEVEL]
        );
        if ($rows === []) {
            return ['keine Warnungen oder Fehler'];
        }

        return array_map(function ($r) {
            return '- ' . date('Y-m-d H:i', (int) $r['time']) . ' ' . $this->describeLogLevel((int) $r['level'])
                . ' (' . $r['channel'] . '): ' . mb_substr($this->oneLine((string) $r['message']), 0, 200);
        }, $rows);
    }

    /** Query for content pages with their approved version, path, type and author. */
    private function pageSelect(): string
    {
        return 'SELECT p.cID, cv.cvID, cv.cvName, cv.cvDescription, pp.cPath, pt.ptName,
                       c.cDateAdded, c.cDateModified, author.uName AS authorName
                FROM Pages p
                JOIN Collections c ON c.cID = p.cID
                JOIN CollectionVersions cv ON cv.cID = p.cID AND cv.cvIsApproved = 1
                LEFT JOIN PagePaths pp ON pp.cID = p.cID AND pp.ppIsCanonical = 1
                LEFT JOIN PageTypes pt ON pt.ptID = p.ptID
                LEFT JOIN Users author ON author.uID = cv.cvAuthorUID
                WHERE ' . $this->contentPageCondition('p');
    }

    /** Pages that visitors can reach: no system pages, templates, aliases, drafts or pages in the trash. */
    private function contentPageCondition(string $alias): string
    {
        return "{$alias}.cIsTemplate = 0 AND {$alias}.cIsSystemPage = 0 AND {$alias}.cPointerID = 0"
            . " AND {$alias}.cIsActive = 1 AND {$alias}.cIsDraft = 0";
    }

    /** @param array<string, mixed> $row */
    private function describePage(array $row): string
    {
        return '- „' . $this->oneLine((string) $row['cvName']) . '“ ' . ($row['cPath'] ?: '/')
            . ($row['ptName'] ? ' (Typ: ' . $row['ptName'] . ')' : '')
            . ', erstellt ' . $this->formatDate($row['cDateAdded'])
            . ', zuletzt geändert ' . $this->formatDate($row['cDateModified'])
            . ($row['authorName'] ? ' von ' . $row['authorName'] : '');
    }

    /**
     * @param string[] $lines
     *
     * @return string[]
     */
    private function withTruncationNote(array $lines, int $total, string $what): array
    {
        if ($total > count($lines)) {
            $lines[] = sprintf('(gekürzt: %d von %d %s aufgeführt)', count($lines), $total, $what);
        }

        return $lines;
    }

    /** @return string[] up to six distinctive words of the question */
    private function searchWords(string $question): array
    {
        // Compound words like "Blog-Einträge" are split, so "blog" can match the page name.
        preg_match_all('/[\p{L}\p{N}]{4,}/u', mb_strtolower($question), $matches);
        $words = array_diff(array_unique($matches[0]), self::STOP_WORDS);

        return array_slice(array_values($words), 0, 6);
    }

    /** Cuts the text at a line end so it is at most $maxChars long. */
    private function limit(string $text, int $maxChars): string
    {
        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }
        $note = "\n(gekürzt)";
        $cut = mb_substr($text, 0, max(0, $maxChars - mb_strlen($note)));
        $lastNewline = mb_strrpos($cut, "\n");
        if ($lastNewline !== false) {
            $cut = mb_substr($cut, 0, $lastNewline);
        }

        return $cut . $note;
    }

    private function oneLine(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /** @param mixed $value */
    private function onOff($value): string
    {
        return $value ? 'an' : 'aus';
    }

    /** @param mixed $value */
    private function describePageCache($value): string
    {
        if ($value === 'all') {
            return 'an für alle Seiten';
        }
        if ($value === 'blocks') {
            return 'an, wenn alle Blöcke einer Seite es erlauben';
        }

        return 'aus';
    }

    private function describeLogLevel(int $level): string
    {
        if ($level >= 500) {
            return 'KRITISCH';
        }
        if ($level >= 400) {
            return 'FEHLER';
        }

        return 'WARNUNG';
    }

    /** @param mixed $value */
    private function formatDate($value): string
    {
        return $value ? mb_substr((string) $value, 0, 16) : 'unbekannt';
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 0, ',', '.') . ' KB';
        }

        return $bytes . ' Bytes';
    }
}
