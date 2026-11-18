<?php

namespace Logingrupa\ColorClassifier\Console;

use Illuminate\Console\Command;
use Logingrupa\ColorClassifier\Classes\OfferDataProvider;
use Logingrupa\ColorClassifier\Classes\OfferLinkRefreshPlanner;
use Logingrupa\ColorClassifier\Models\ColorEntry;

/**
 * RefreshOfferLinks rewrites the catalog-derived link fields of existing color entries.
 *
 * Matches by offer_id and touches only product_name, variation_name, detail_url and
 * image_url. Colors, overrides, locks and memberships stay as they are. An empty
 * catalog fails the command, so a feed outage never reads as "nothing to refresh".
 */
class RefreshOfferLinks extends Command
{
    /** @var string signature */
    protected $signature = 'colorclassifier:refresh-offer-links {--dry-run : Report the changes, write nothing}';

    /** @var string description */
    protected $description = 'Rewrite product name, variation name, detail URL and image URL of existing color entries from the current catalog.';

    /**
     * @return int
     */
    public function handle(): int
    {
        $arCatalogOffers = (new OfferDataProvider())->getOffersWithImages();

        if ($arCatalogOffers === []) {
            $this->error('The catalog returned no offers. Nothing was changed.');

            return self::FAILURE;
        }

        $obEntries = ColorEntry::query()
            ->select(array_merge(['id', 'offer_id'], OfferLinkRefreshPlanner::LINK_FIELDS))
            ->get()
            ->keyBy('offer_id');

        $arStoredLinkFields = $obEntries->map(fn (ColorEntry $obEntry): array => $obEntry->only(OfferLinkRefreshPlanner::LINK_FIELDS))->all();
        $arPlan = (new OfferLinkRefreshPlanner())->plan($arStoredLinkFields, $arCatalogOffers);

        $blDryRun = (bool) $this->option('dry-run');

        if (!$blDryRun) {
            foreach ($arPlan['changes'] as $sOfferId => $arChangedFields) {
                $obEntries[$sOfferId]->fill($arChangedFields)->save();
            }
        }

        $this->line(sprintf(
            '%s %d, unchanged %d, not in catalog %d.',
            $blDryRun ? 'Would update' : 'Updated',
            count($arPlan['changes']),
            $arPlan['unchanged'],
            $arPlan['absent']
        ));

        return self::SUCCESS;
    }
}
