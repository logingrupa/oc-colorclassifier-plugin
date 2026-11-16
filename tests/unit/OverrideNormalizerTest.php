<?php

use Logingrupa\ColorClassifier\Classes\OverrideNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OverrideNormalizerTest extends TestCase
{
    private const ACTIVE_FAMILIES = ['Red', 'Pink', 'Rose'];

    private OverrideNormalizer $obNormalizer;

    protected function setUp(): void
    {
        $this->obNormalizer = new OverrideNormalizer();
    }

    public function test_family_equal_to_auto_is_a_no_op(): void
    {
        $this->assertNull($this->obNormalizer->normalizeFamilyOverride('Pink', 'Pink', self::ACTIVE_FAMILIES));
    }

    public function test_empty_family_is_null(): void
    {
        $this->assertNull($this->obNormalizer->normalizeFamilyOverride('', 'Pink', self::ACTIVE_FAMILIES));
        $this->assertNull($this->obNormalizer->normalizeFamilyOverride('   ', 'Pink', self::ACTIVE_FAMILIES));
        $this->assertNull($this->obNormalizer->normalizeFamilyOverride(null, 'Pink', self::ACTIVE_FAMILIES));
    }

    public function test_family_outside_the_active_list_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->obNormalizer->normalizeFamilyOverride('Teal', 'Pink', self::ACTIVE_FAMILIES);
    }

    public function test_active_family_is_kept(): void
    {
        $this->assertSame('Rose', $this->obNormalizer->normalizeFamilyOverride(' Rose ', 'Pink', self::ACTIVE_FAMILIES));
    }

    public function test_hex_is_normalized_to_lowercase_with_hash(): void
    {
        $this->assertSame('#abcdef', $this->obNormalizer->normalizeHex('#ABCDEF'));
        $this->assertSame('#abcdef', $this->obNormalizer->normalizeHex('abcdef'));
        $this->assertNull($this->obNormalizer->normalizeHex('  '));
        $this->assertNull($this->obNormalizer->normalizeHex(null));
    }

    #[DataProvider('invalidHexProvider')]
    public function test_invalid_hex_throws(string $sHex): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->obNormalizer->normalizeHex($sHex);
    }

    /** @return array<string, array<int, string>> */
    public static function invalidHexProvider(): array
    {
        return [
            'short'   => ['#abc'],
            'word'    => ['red'],
            'nonhex'  => ['#12345g'],
        ];
    }

    public function test_hex_equal_to_auto_hex_is_a_no_op(): void
    {
        $this->assertNull($this->obNormalizer->normalizeHexOverride('#EC407A', '#ec407a'));
        $this->assertNull($this->obNormalizer->normalizeHexOverride('ec407a', '#EC407A'));
    }

    public function test_hex_different_from_auto_is_kept(): void
    {
        $this->assertSame('#123456', $this->obNormalizer->normalizeHexOverride('#123456', '#ec407a'));
        $this->assertSame('#123456', $this->obNormalizer->normalizeHexOverride('#123456', null));
    }

    public function test_lock_is_true_when_requested_or_overridden(): void
    {
        $this->assertTrue($this->obNormalizer->resolveLock(true, null, null));
        $this->assertTrue($this->obNormalizer->resolveLock(false, 'Rose', null));
        $this->assertTrue($this->obNormalizer->resolveLock(false, null, '#123456'));
        $this->assertFalse($this->obNormalizer->resolveLock(false, null, null));
    }
}
