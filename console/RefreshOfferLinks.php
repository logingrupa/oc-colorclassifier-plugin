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
 * catalog or an empty shop offer directory fails the command, so a feed outage never
 * reads as "nothing to refresh" and never writes 1C names over shop names. Entries
 * whose image changed and are not locked are listed, not reprocessed.
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
        $obOfferDataProvider = new OfferDataProvider();

        if ($obOfferDataProvider->getOfferDirectory() === []) {
            $this->error('The shop offer directory is empty. Nothing was changed, because the rows would fall back to the 1C names.');

            return self::FAILURE;
        }

        $arCatalogOffers = $obOfferDataProvider->getOffersWithImages();

        if ($arCatalogOffers === []) {
            $this->error('The catalog returned no offers. Nothing was changed.');

            return self::FAILURE;
        }

        $arStoredFields = array_merge(OfferLinkRefreshPlanner::LINK_FIELDS, ['is_locked']);
        $obEntries = ColorEntry::query()
            ->select(array_merge(['id', 'offer_id'], $arStoredFields))
            ->get()
            ->keyBy('offer_id');

        $arStoredLinkFields = $obEntries->map(fn (ColorEntry $obEntry): array => $obEntry->only($arStoredFields))->all();
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
        $this->reportEntriesToReprocess($arPlan['reprocess'], $obEntries);

        return self::SUCCESS;
    }

    /**
     * @param array<int, string>                                  $arOfferIds Offers whose image changed on an unlocked entry.
     * @param \Illuminate\Support\Collection<string, ColorEntry> $obEntries  Stored entries keyed by offer_id.
     *
     * @return void
     */
    private function reportEntriesToReprocess(array $arOfferIds, $obEntries): void
    {
        if ($arOfferIds === []) {
            return;
        }

        $this->warn(sprintf('Image changed on %d unlocked entries; their colors still describe the old picture. Reprocess them:', count($arOfferIds)));

        foreach ($arOfferIds as $sOfferId) {
            $this->line(sprintf('  %s  %s / %s', $sOfferId, $obEntries[$sOfferId]->product_name, $obEntries[$sOfferId]->variation_name));
        }
    }
}
