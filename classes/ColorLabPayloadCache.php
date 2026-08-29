<?php

namespace Logingrupa\ColorClassifier\Classes;

/**
 * ColorLabPayloadCache - shared cache for the assembled Color Lab payloads.
 *
 * Sole responsibility: remember the payloads the Color Lab routes assemble
 * from the ColorEntry table so serving them costs a cache read instead of a
 * table load, and drop every copy the moment a ColorEntry changes. Without
 * a bootstrapped Cache facade (standalone plugin tests) remember() degrades
 * to building on every call and flush() is a no-op.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class ColorLabPayloadCache
{
    /** @var string Namespace prefix for every payload cache key. */
    private const CACHE_KEY_PREFIX = 'colorclassifier_payload_';

    /** @var int Payload TTL; invalidation on ColorEntry change is the primary freshness mechanism. */
    private const CACHE_TTL_SECONDS = 3600;

    /** @var string Pre-encoded entries JSON for the /tools/color-lab page. */
    public const KEY_PAGE_ENTRIES = 'page_entries';

    /** @var string Full /api/color-lab/data payload. */
    public const KEY_DATA = 'data';

    /** @var string Full /api/color-lab/offers.json payload including its version stamp. */
    public const KEY_OFFERS = 'offers';

    /** @var array<int, string> Every payload key, for flush(). */
    private const ALL_PAYLOAD_KEYS = [
        self::KEY_PAGE_ENTRIES,
        self::KEY_DATA,
        self::KEY_OFFERS,
    ];

    /**
     * Serve a payload from cache, building and storing it on a miss.
     *
     * @param string $sPayloadKey One of the KEY_* constants.
     * @param \Closure $fnBuildPayload Builds the payload on a cache miss.
     *
     * @return mixed
     */
    public static function remember(string $sPayloadKey, \Closure $fnBuildPayload): mixed
    {
        if (!self::isCacheFacadeAvailable()) {
            return $fnBuildPayload();
        }

        return \Cache::remember(
            self::CACHE_KEY_PREFIX . $sPayloadKey,
            self::CACHE_TTL_SECONDS,
            $fnBuildPayload
        );
    }

    /**
     * Drop every cached payload. Called from the ColorEntry model events so
     * any change to the table invalidates all derived payloads at once.
     *
     * @return void
     */
    public static function flush(): void
    {
        if (!self::isCacheFacadeAvailable()) {
            return;
        }

        foreach (self::ALL_PAYLOAD_KEYS as $sPayloadKey) {
            \Cache::forget(self::CACHE_KEY_PREFIX . $sPayloadKey);
        }
    }

    /**
     * @return bool Whether the Laravel Cache facade is bootstrapped.
     */
    private static function isCacheFacadeAvailable(): bool
    {
        try {
            return class_exists(\Cache::class) && \Cache::getFacadeRoot() !== null;
        } catch (\Throwable $obFacadeException) {
            return false;
        }
    }
}
