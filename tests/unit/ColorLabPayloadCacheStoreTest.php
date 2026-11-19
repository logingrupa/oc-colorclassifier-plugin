<?php

use Logingrupa\ColorClassifier\Classes\ColorLabPayloadCache;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * ColorLabPayloadCacheStoreTest - the cached path against an in-memory Cache
 * facade. Each test runs in its own process because it declares the global
 * Cache class, which the degraded-mode tests must not see.
 */
class ColorLabPayloadCacheStoreTest extends TestCase
{
    /**
     * Declares a minimal global Cache facade backed by a public array.
     */
    private function declareCacheFacade(): void
    {
        eval('class Cache {
            public static array $arStore = [];
            public static function getFacadeRoot(): object { return new stdClass(); }
            public static function get(string $sKey): mixed { return self::$arStore[$sKey] ?? null; }
            public static function put(string $sKey, mixed $mValue, int $iSeconds): void { self::$arStore[$sKey] = $mValue; }
            public static function forget(string $sKey): void { unset(self::$arStore[$sKey]); }
        }');
    }

    #[RunInSeparateProcess]
    public function test_payloads_live_under_a_v2_namespace_and_never_under_the_bare_keys(): void
    {
        $this->declareCacheFacade();

        ColorLabPayloadCache::remember(ColorLabPayloadCache::KEY_OFFERS, fn (): array => ['offers' => []], 'v1');

        $this->assertSame(['colorclassifier_payload_v2_offers'], array_keys(\Cache::$arStore));
        $this->assertSame(['version' => 'v1', 'payload' => ['offers' => []]], \Cache::$arStore['colorclassifier_payload_v2_offers']);
    }

    #[RunInSeparateProcess]
    public function test_flush_drops_the_v2_copies_and_the_bare_copies_of_earlier_releases(): void
    {
        $this->declareCacheFacade();
        \Cache::$arStore = [
            'colorclassifier_payload_v2_offers' => ['version' => '', 'payload' => []],
            'colorclassifier_payload_offers'    => ['offers' => []],
            'colorclassifier_payload_data'      => ['entries' => []],
            'unrelated'                         => 'kept',
        ];

        ColorLabPayloadCache::flush();

        $this->assertSame(['unrelated'], array_keys(\Cache::$arStore));
    }

    #[RunInSeparateProcess]
    public function test_a_copy_built_for_another_version_is_rebuilt(): void
    {
        $this->declareCacheFacade();
        $iBuildCount = 0;
        $fnBuild     = function () use (&$iBuildCount): array {
            $iBuildCount++;

            return ['build' => $iBuildCount];
        };

        ColorLabPayloadCache::remember(ColorLabPayloadCache::KEY_DATA, $fnBuild, 'directory-a');
        ColorLabPayloadCache::remember(ColorLabPayloadCache::KEY_DATA, $fnBuild, 'directory-a');
        $arRebuilt = ColorLabPayloadCache::remember(ColorLabPayloadCache::KEY_DATA, $fnBuild, 'directory-b');

        $this->assertSame(2, $iBuildCount);
        $this->assertSame(['build' => 2], $arRebuilt);
    }

    #[RunInSeparateProcess]
    public function test_an_unreadable_directory_serves_the_last_payload_built_for_a_real_one(): void
    {
        $this->declareCacheFacade();
        $iBuildCount = 0;
        $fnBuild     = function () use (&$iBuildCount): array {
            $iBuildCount++;

            return ['build' => $iBuildCount];
        };

        ColorLabPayloadCache::remember(ColorLabPayloadCache::KEY_DATA, $fnBuild, 'directory-a');
        $arDuringOutage = ColorLabPayloadCache::remember(ColorLabPayloadCache::KEY_DATA, $fnBuild, 'unreadable', true);

        $this->assertSame(['build' => 1], $arDuringOutage);
        $this->assertSame(1, $iBuildCount, 'The outage must not rebuild from the unfiltered table.');
    }

    #[RunInSeparateProcess]
    public function test_an_unreadable_directory_with_nothing_stored_builds_once(): void
    {
        $this->declareCacheFacade();
        $iBuildCount = 0;
        $fnBuild     = function () use (&$iBuildCount): array {
            $iBuildCount++;

            return ['build' => $iBuildCount];
        };

        ColorLabPayloadCache::remember(ColorLabPayloadCache::KEY_DATA, $fnBuild, 'unreadable', true);
        ColorLabPayloadCache::remember(ColorLabPayloadCache::KEY_DATA, $fnBuild, 'unreadable', true);

        $this->assertSame(1, $iBuildCount);
    }

    #[RunInSeparateProcess]
    public function test_a_payload_stored_by_an_earlier_release_never_counts_as_a_copy(): void
    {
        $this->declareCacheFacade();
        \Cache::$arStore['colorclassifier_payload_v2_data'] = ['entries' => [], 'taxonomy' => []];

        $arPayload = ColorLabPayloadCache::remember(ColorLabPayloadCache::KEY_DATA, fn (): array => ['built' => true], 'unreadable', true);

        $this->assertSame(['built' => true], $arPayload);
    }
}
