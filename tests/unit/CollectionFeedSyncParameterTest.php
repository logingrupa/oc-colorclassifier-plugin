<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/classes/CollectionFeedSync.php';

use Logingrupa\ColorClassifier\Classes\CollectionFeedSync;

/**
 * The collection sync compares the new offer count with the count of its own
 * source, so a release that counts a different catalog never trips its abort guard.
 */
class CollectionFeedSyncParameterTest extends TestCase
{
    public function test_full_catalog_sync_stores_its_count_under_a_key_of_its_own(): void
    {
        $this->assertSame(
            'logingrupa.colorclassifier::collection_sync.full_offer_count',
            CollectionFeedSync::LAST_OFFER_COUNT_PARAMETER
        );
        $this->assertNotSame('logingrupa.colorclassifier::collection_sync.offer_count', CollectionFeedSync::LAST_OFFER_COUNT_PARAMETER);
    }
}
