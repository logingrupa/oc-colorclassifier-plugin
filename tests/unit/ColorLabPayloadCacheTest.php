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

    public function testFlushIsANoOpWithoutCacheFacade(): void
    {
        ColorLabPayloadCache::flush();

        $this->addToAssertionCount(1);
    }
}
