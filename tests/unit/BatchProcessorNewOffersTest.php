<?php

use Logingrupa\ColorClassifier\Classes\BatchProcessor;
use Logingrupa\ColorClassifier\Classes\OfferDataProvider;
use PHPUnit\Framework\TestCase;

/**
 * NEWOFFERS-01: a catalog that did not load fails the scheduled color job
 * instead of reporting that nothing is new.
 * NEWOFFERS-02: the scheduled job narrows the catalog to the color scope
 * before any cache or database work.
 * NEWOFFERS-03: a cached catalog without group paths stops the job before
 * the failure memory is read or written.
 */
class BatchProcessorNewOffersTest extends TestCase
{
    public function test_a_catalog_without_offers_throws_before_any_cache_or_database_work(): void
    {
        $obEmptyCatalogProvider = new class extends OfferDataProvider {
            public function getOffersWithImages(): array
            {
                return [];
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The catalog has no offers with images');

        (new BatchProcessor($obEmptyCatalogProvider))->processNewOffers(100);
    }

    public function test_the_scheduled_job_applies_the_color_scope_before_cache_or_database_work(): void
    {
        $obOutOfScopeProvider = new class extends OfferDataProvider {
            public function getOffersWithImages(): array
            {
                return [['offer_id' => 'p#1', 'image_url' => 'https://example.test/1.png', 'category_path_ids' => ['group-a']]];
            }

            public function getColorScopeGroupIds(): array
            {
                return ['group-b'];
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No catalog offer is in the color scope.');

        (new BatchProcessor($obOutOfScopeProvider))->processNewOffers(100);
    }

    public function test_a_catalog_without_group_paths_stops_the_job_before_the_failure_memory_is_touched(): void
    {
        $obUnwarmedCatalogProvider = new class extends OfferDataProvider {
            public function getOffersWithImages(): array
            {
                return [['offer_id' => 'p#1', 'image_url' => 'https://example.test/1.png']];
            }

            public function getColorScopeGroupIds(): array
            {
                return ['group-a'];
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Run php artisan nailolab:warm-catalog');

        (new BatchProcessor($obUnwarmedCatalogProvider))->processNewOffers(100);
    }
}
