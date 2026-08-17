<?php

use PHPUnit\Framework\TestCase;
use Logingrupa\ColorClassifier\Classes\EtagRevalidation;

/**
 * Unit tests for the pure If-None-Match matcher.
 *
 * The matcher must accept the header forms real clients and proxies send:
 *   - Exact quoted ETag match
 *   - Weak validator prefix (W/"...")
 *   - Comma-separated multi-ETag lists (with surrounding whitespace)
 *   - nginx gzip suffix inside the closing quote ("stamp-gzip")
 *   - Combinations of the above
 * and reject non-matching or empty headers. The respond() wrapper is thin
 * Laravel response wiring exercised by the routes, not unit tested here.
 */
class EtagRevalidationTest extends TestCase
{
    private const ETAG = '"abc123def456"';

    public function test_exact_match(): void
    {
        $this->assertTrue(EtagRevalidation::ifNoneMatchMatches('"abc123def456"', self::ETAG));
    }

    public function test_weak_prefix_matches(): void
    {
        $this->assertTrue(EtagRevalidation::ifNoneMatchMatches('W/"abc123def456"', self::ETAG));
    }

    public function test_gzip_suffix_matches(): void
    {
        $this->assertTrue(EtagRevalidation::ifNoneMatchMatches('"abc123def456-gzip"', self::ETAG));
    }

    public function test_weak_prefix_and_gzip_suffix_match(): void
    {
        $this->assertTrue(EtagRevalidation::ifNoneMatchMatches('W/"abc123def456-gzip"', self::ETAG));
    }

    public function test_multi_etag_list_matches_any_position(): void
    {
        $this->assertTrue(
            EtagRevalidation::ifNoneMatchMatches('"stale1", "abc123def456", "stale2"', self::ETAG)
        );
        $this->assertTrue(
            EtagRevalidation::ifNoneMatchMatches('"stale1",W/"abc123def456-gzip"', self::ETAG)
        );
    }

    public function test_no_match_returns_false(): void
    {
        $this->assertFalse(EtagRevalidation::ifNoneMatchMatches('"other"', self::ETAG));
        $this->assertFalse(EtagRevalidation::ifNoneMatchMatches('"stale1", "stale2"', self::ETAG));
    }

    public function test_unquoted_stamp_does_not_match(): void
    {
        $this->assertFalse(EtagRevalidation::ifNoneMatchMatches('abc123def456', self::ETAG));
    }

    public function test_gzip_suffix_only_stripped_inside_closing_quote(): void
    {
        // literal different stamp ending in -gzip must not be rewritten into a match
        $this->assertFalse(EtagRevalidation::ifNoneMatchMatches('"abc123def456-gzip', self::ETAG));
    }

    public function test_empty_header_returns_false(): void
    {
        $this->assertFalse(EtagRevalidation::ifNoneMatchMatches('', self::ETAG));
    }
}
