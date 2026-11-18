<?php

use Logingrupa\ColorClassifier\Classes\ColorLabPayloadCache;
use PHPUnit\Framework\TestCase;

/**
 * ColorLabPayloadCacheTest - degraded-mode behaviour without a Cache facade.
 *
 * Standalone tests have no bootstrapped Laravel Cache, so remember() must
 * build on every call and flush() must be a harmless no-op. The cached path
 * itself runs against the real facade inside the CMS.
 */
class ColorLabPayloadCacheTest extends TestCase
{
    public function testRememberBuildsOnEveryCallWithoutCacheFacade(): void
    {
        $iBuildCount = 0;

        $fnBuildPayload = function () use (&$iBuildCount) {
            $iBuildCount++;

            return ['build' => $iBuildCount];
        };

        $arFirstPayload  = ColorLabPayloadCache::remember(ColorLabPayloadCache::KEY_OFFERS, $fnBuildPayload);
        $arSecondPayload = ColorLabPayloadCache::remember(ColorLabPayloadCache::KEY_OFFERS, $fnBuildPayload);

        $this->assertSame(['build' => 1], $arFirstPayload);
        $this->assertSame(['build' => 2], $arSecondPayload);
    }

    public function testStoredCopyIsCurrentOnlyForTheVersionItWasBuiltFor(): void
    {
        $arStoredCopy = ['version' => 'directory-a', 'payload' => ['entryCount' => 3]];

        $this->assertTrue(ColorLabPayloadCache::isStoredForVersion($arStoredCopy, 'directory-a'));
        $this->assertFalse(
            ColorLabPayloadCache::isStoredForVersion($arStoredCopy, 'directory-b'),
            'A payload built from another catalog directory must be rebuilt.'
        );
    }

    public function testStoredCopyWithoutAVersionMatchesTheEmptyVersion(): void
    {
        $this->assertTrue(ColorLabPayloadCache::isStoredForVersion(['version' => '', 'payload' => []], ''));
    }

    public function testMissingOrForeignCopyNeverCounts(): void
    {
        $this->assertFalse(ColorLabPayloadCache::isStoredForVersion(null, ''));
        $this->assertFalse(ColorLabPayloadCache::isStoredForVersion(['entriesJson' => '[]', 'entryCount' => 0], ''), 'A copy stored before versioning must be rebuilt.');
    }

    public function testFlushIsANoOpWithoutCacheFacade(): void
    {
        ColorLabPayloadCache::flush();

        $this->addToAssertionCount(1);
    }
}
