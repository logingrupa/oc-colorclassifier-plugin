<?php

namespace Logingrupa\ColorClassifier\Classes;

/**
 * EtagRevalidation - shared ETag / If-None-Match handling for the JSON
 * export routes (families.json, offers.json).
 *
 * Sole responsibility: decide 304 vs 200 for a versioned export payload and
 * build the cache headers. The matcher is a pure function tolerant of real
 * client / proxy header forms: weak validators (W/ prefix), comma-separated
 * ETag lists, and nginx's gzip suffix ("stamp-gzip"). No payload building,
 * no routing.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class EtagRevalidation
{
    /**
     * Does an If-None-Match header value match the given quoted ETag?
     *
     * Pure function. Splits the header on commas, trims each candidate,
     * strips a W/ weak prefix, strips a trailing "-gzip" inside the closing
     * quote (nginx gzip_vary rewrite), then compares against the quoted ETag.
     *
     * @param string $sHeaderValue Raw If-None-Match header value ('' when absent).
     * @param string $sEtag Quoted ETag, e.g. '"abc123"'.
     *
     * @return bool
     */
    public static function ifNoneMatchMatches(string $sHeaderValue, string $sEtag): bool
    {
        if ($sHeaderValue === '') {
            return false;
        }

        foreach (explode(',', $sHeaderValue) as $sCandidate) {
            $sCandidate = trim($sCandidate);

            if (str_starts_with($sCandidate, 'W/')) {
                $sCandidate = substr($sCandidate, 2);
            }

            if (str_ends_with($sCandidate, '-gzip"')) {
                $sCandidate = substr($sCandidate, 0, -strlen('-gzip"')) . '"';
            }

            if ($sCandidate === $sEtag) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build the 304 / 200 response for a versioned export payload.
     *
     * The ETag is the quoted payload version stamp; both responses carry
     * ETag + a one hour public Cache-Control.
     *
     * @param array{version: string} $arPayload Export payload with its version stamp.
     * @param string $sIfNoneMatchHeader Raw If-None-Match header value ('' when absent).
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public static function respond(array $arPayload, string $sIfNoneMatchHeader)
    {
        $sEtag = '"' . $arPayload['version'] . '"';
        $arHeaders = [
            'ETag'          => $sEtag,
            'Cache-Control' => 'public, max-age=3600',
        ];

        if (self::ifNoneMatchMatches($sIfNoneMatchHeader, $sEtag)) {
            return response('', 304, $arHeaders);
        }

        return response()->json($arPayload, 200, $arHeaders);
    }
}
