<?php

use Logingrupa\ColorClassifier\Classes\BatchProcessor;
use Logingrupa\ColorClassifier\Classes\OfferDataProvider;
use PHPUnit\Framework\TestCase;

/**
 * NEWOFFERS-01: a catalog that did not load fails the scheduled color job
 * instead of reporting that nothing is new.
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
}
