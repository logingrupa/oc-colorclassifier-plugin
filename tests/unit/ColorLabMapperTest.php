<?php

use Logingrupa\ColorClassifier\Classes\ColorLabMapper;
use PHPUnit\Framework\TestCase;

/**
 * Color Lab groups and filters by family and secondary family; an overridden
 * family must not keep the classifier's secondary family.
 */
class ColorLabMapperTest extends TestCase
{
    private const TAXONOMY = ['family' => 'Red', 'secondary_family' => 'Coral', 'depth' => 'Medium'];

    public function test_auto_family_keeps_the_secondary_family(): void
    {
        $arMapped = ColorLabMapper::mapTaxonomyValues(self::TAXONOMY, ['family' => 'Red', 'auto_family' => 'Red']);

        $this->assertSame('Red', $arMapped['family']);
        $this->assertSame('Coral', $arMapped['secondary_family']);
    }

    public function test_overridden_family_drops_the_secondary_family(): void
    {
        $arMapped = ColorLabMapper::mapTaxonomyValues(self::TAXONOMY, ['family' => 'Blue', 'auto_family' => 'Red']);

        $this->assertSame('Blue', $arMapped['family']);
        $this->assertNull($arMapped['secondary_family']);
        $this->assertSame('Medium', $arMapped['depth'], 'other taxonomy values are untouched');
    }
}
