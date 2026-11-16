<?php

use Logingrupa\ColorClassifier\Classes\ColorConverter;
use Logingrupa\ColorClassifier\Classes\EffectiveColorResolver;
use PHPUnit\Framework\TestCase;

class EffectiveColorResolverTest extends TestCase
{
    private function makeRow(array $arOverrides = []): object
    {
        return (object) array_merge([
            'hex_color'        => '#ec407a',
            'taxonomy'         => ['family' => 'Pink'],
            'oklch_values'     => ['lightness' => 0.64, 'chroma' => 0.15, 'hue' => 354.2],
            'confidence_score' => '0.83',
            'family_override'  => null,
            'hex_override'     => null,
        ], $arOverrides);
    }

    public function test_without_overrides_the_classifier_values_win(): void
    {
        $arEffective = EffectiveColorResolver::resolve($this->makeRow());

        $this->assertSame('Pink', $arEffective['family']);
        $this->assertSame('Pink', $arEffective['auto_family']);
        $this->assertSame('#ec407a', $arEffective['hex']);
        $this->assertSame(0.64, $arEffective['lightness']);
        $this->assertSame(0.15, $arEffective['chroma']);
        $this->assertSame(354.2, $arEffective['hue']);
        $this->assertSame(0.83, $arEffective['confidence']);
        $this->assertFalse($arEffective['family_overridden']);
        $this->assertFalse($arEffective['hex_overridden']);
        $this->assertFalse($arEffective['manual']);
    }

    public function test_family_override_wins_with_full_confidence(): void
    {
        $arEffective = EffectiveColorResolver::resolve($this->makeRow(['family_override' => 'Rose']));

        $this->assertSame('Rose', $arEffective['family']);
        $this->assertSame('Pink', $arEffective['auto_family']);
        $this->assertSame(1.0, $arEffective['confidence']);
        $this->assertSame(0.83, $arEffective['auto_confidence']);
        $this->assertTrue($arEffective['family_overridden']);
        $this->assertTrue($arEffective['manual']);
        $this->assertSame('#ec407a', $arEffective['hex']);
    }

    public function test_hex_override_recomputes_oklch_and_keeps_confidence(): void
    {
        $arEffective = EffectiveColorResolver::resolve($this->makeRow(['hex_override' => '#123456']));
        $arExpected = ColorConverter::rgbToOklch(0x12, 0x34, 0x56);

        $this->assertSame('#123456', $arEffective['hex']);
        $this->assertSame('#ec407a', $arEffective['auto_hex']);
        $this->assertSame($arExpected['lightness'], $arEffective['lightness']);
        $this->assertSame($arExpected['chroma'], $arEffective['chroma']);
        $this->assertSame($arExpected['hue'], $arEffective['hue']);
        $this->assertSame(0.83, $arEffective['confidence']);
        $this->assertSame('Pink', $arEffective['family']);
        $this->assertTrue($arEffective['hex_overridden']);
        $this->assertFalse($arEffective['family_overridden']);
        $this->assertTrue($arEffective['manual']);
    }

    public function test_both_overrides_together(): void
    {
        $arEffective = EffectiveColorResolver::resolve($this->makeRow([
            'family_override' => 'Red',
            'hex_override'    => '#ff0000',
        ]));

        $this->assertSame('Red', $arEffective['family']);
        $this->assertSame('#ff0000', $arEffective['hex']);
        $this->assertSame(1.0, $arEffective['confidence']);
        $this->assertTrue($arEffective['manual']);
    }

    public function test_empty_string_overrides_count_as_absent(): void
    {
        $arEffective = EffectiveColorResolver::resolve($this->makeRow([
            'family_override' => '',
            'hex_override'    => '',
        ]));

        $this->assertSame('Pink', $arEffective['family']);
        $this->assertSame('#ec407a', $arEffective['hex']);
        $this->assertFalse($arEffective['manual']);
    }

    public function test_malformed_hex_override_counts_as_absent(): void
    {
        $arEffective = EffectiveColorResolver::resolve($this->makeRow(['hex_override' => 'red']));

        $this->assertSame('#ec407a', $arEffective['hex']);
        $this->assertSame(0.64, $arEffective['lightness']);
        $this->assertFalse($arEffective['hex_overridden']);
        $this->assertFalse($arEffective['manual']);
    }

    public function test_confidence_string_becomes_float_and_junk_becomes_null(): void
    {
        $this->assertSame(0.83, EffectiveColorResolver::resolve($this->makeRow())['confidence']);
        $this->assertNull(EffectiveColorResolver::resolve($this->makeRow(['confidence_score' => 'high']))['confidence']);
        $this->assertNull(EffectiveColorResolver::resolve($this->makeRow(['confidence_score' => null]))['confidence']);
    }

    public function test_malformed_taxonomy_without_override_has_no_family(): void
    {
        $arEffective = EffectiveColorResolver::resolve($this->makeRow(['taxonomy' => null]));

        $this->assertNull($arEffective['family']);
        $this->assertNull($arEffective['auto_family']);
    }

    public function test_family_override_rescues_malformed_taxonomy(): void
    {
        $arEffective = EffectiveColorResolver::resolve($this->makeRow([
            'taxonomy'        => null,
            'family_override' => 'Rose',
        ]));

        $this->assertSame('Rose', $arEffective['family']);
    }

    public function test_row_without_override_properties_resolves_like_classifier_only(): void
    {
        $obRow = (object) [
            'hex_color'        => '#ec407a',
            'taxonomy'         => ['family' => 'Pink'],
            'oklch_values'     => ['lightness' => 0.64, 'chroma' => 0.15, 'hue' => 354.2],
            'confidence_score' => '0.83',
        ];

        $this->assertFalse(EffectiveColorResolver::resolve($obRow)['manual']);
    }

    public function test_low_confidence_threshold_constant(): void
    {
        $this->assertSame(0.55, EffectiveColorResolver::LOW_CONFIDENCE_THRESHOLD);
    }
}
