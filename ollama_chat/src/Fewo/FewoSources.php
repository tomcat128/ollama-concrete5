<?php

namespace OllamaChat\Fewo;

use Concrete\Core\Database\Connection\Connection;
use Concrete\Core\Support\Facade\Application;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Finds the data of the holiday apartment in the blocks of the Fewo Toolkit package: the iCal feed of the
 * occupancy plan ("Belegungsplan (Homeaway)") and the price list with the calculation mode of the price calculator
 * ("Preisrechner"). Each Concrete installation has exactly one apartment, so the first block found is used.
 */
final class FewoSources
{
    /** Class of the Fewo Toolkit that holds the price lists and the price calculation. */
    public const PRICE_LIST_CLASS = '\Concrete\Package\FewoToolkit\Block\Preisuebersicht\Controller';

    /** Class of the Fewo Toolkit's occupancy plan block. */
    public const OCCUPANCY_CLASS = '\Concrete\Package\FewoToolkit\Block\BelegungsplanHomeaway\Controller';

    /** @var string|null URL of the iCal feed */
    public $feedUrl;

    /** @var int minutes after which the cached feed is downloaded again (setting of the occupancy plan block) */
    public $refreshMinutes = 15;

    /** @var string "IN" = price list in this installation, "EX" = external price calculation by URL */
    public $priceType = 'IN';

    /** @var int ID of the price list (btFwbPreislisteConfig.listID) */
    public $priceListId = 0;

    /** @var string|null name of the price list */
    public $priceListName;

    /** @var string "T" = the arrival day's season is charged, "N" = the following day's season */
    public $priceMode = 'N';

    /** @var string|null URL of the external price calculation */
    public $externalPriceUrl;

    public static function detect(): self
    {
        $sources = new self();
        if (!class_exists(self::PRICE_LIST_CLASS)) {
            return $sources; // Fewo Toolkit not installed
        }
        $db = Application::getFacadeApplication()->make(Connection::class);

        try {
            $feed = $db->fetchAssociative(
                'SELECT a.ics_feed, a.time_refresh FROM btFwbBelegungsplanHomeaway a ' . self::onPublishedPage('a')
                . " AND a.ics_feed LIKE 'http%' ORDER BY a.bID DESC LIMIT 1"
            );
            if ($feed) {
                $sources->feedUrl = trim($feed['ics_feed']);
                $sources->refreshMinutes = max(1, (int) $feed['time_refresh'] ?: 15);
            }
        } catch (\Throwable $e) {
            // tables of the Fewo Toolkit missing
        }

        try {
            $calculator = $db->fetchAssociative(
                'SELECT r.type, r.preisliste, r.modus, r.url_extern FROM btFwbPreisrechner r ' . self::onPublishedPage('r')
                . " AND ((r.type = 'IN' AND r.preisliste > 0) OR (r.type = 'EX' AND r.url_extern LIKE 'http%'))"
                . ' ORDER BY r.bID DESC LIMIT 1'
            );
            if ($calculator) {
                $sources->priceType = $calculator['type'] === 'EX' ? 'EX' : 'IN';
                $sources->priceListId = (int) $calculator['preisliste'];
                $sources->priceMode = $calculator['modus'] === 'T' ? 'T' : 'N';
                $sources->externalPriceUrl = $calculator['type'] === 'EX' ? trim($calculator['url_extern']) : null;
            } else {
                // No price calculator on the site: use the price list of a price overview block, or the only one there is.
                $listId = $db->fetchOne('SELECT l.liste FROM btFwbPreisliste l ' . self::onPublishedPage('l') . ' AND l.liste > 0 ORDER BY l.bID DESC LIMIT 1')
                    ?: $db->fetchOne('SELECT MIN(listID) FROM btFwbPreislisteConfig');
                $sources->priceListId = (int) $listId;
            }
            if ($sources->priceListId > 0) {
                $name = $db->fetchOne('SELECT name FROM btFwbPreislisteConfig WHERE listID = ?', [$sources->priceListId]);
                $sources->priceListName = $name === false ? null : (string) $name;
            }
        } catch (\Throwable $e) {
            // tables of the Fewo Toolkit missing
        }

        return $sources;
    }

    public function hasCalendar(): bool
    {
        return $this->feedUrl !== null;
    }

    public function hasPrices(): bool
    {
        return $this->priceType === 'EX' ? $this->externalPriceUrl !== null : $this->priceListName !== null;
    }

    /**
     * Joins that keep only blocks shown on a published, active page (not old versions or the trash).
     * Blocks.bIsActive is not used: Concrete 9 leaves it at 0 for new blocks.
     */
    private static function onPublishedPage(string $alias): string
    {
        return "JOIN CollectionVersionBlocks cvb ON cvb.bID = {$alias}.bID
                JOIN CollectionVersions cv ON cv.cID = cvb.cID AND cv.cvID = cvb.cvID AND cv.cvIsApproved = 1
                JOIN Pages p ON p.cID = cvb.cID AND p.cIsActive = 1
                WHERE 1 = 1";
    }
}
