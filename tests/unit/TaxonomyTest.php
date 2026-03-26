<?php

use PHPUnit\Framework\TestCase;
use Logingrupa\ColorClassifier\Classes\Taxonomy;

/**
 * Unit tests for Taxonomy.
 *
 * Covers taxonomy constant array requirements:
 *   CC-15 — colorFamilies has exactly 22 entries
 *   CC-16 — undertones has exactly 3 entries
 *   CC-17 — depths has exactly 5 entries
 *   CC-18 — saturations has exactly 5 entries
 *   CC-19 — finishes has exactly 10 entries
 *   CC-20 — opacities has exactly 4 entries
 *   CC-21 — All taxonomy arrays contain only non-empty strings
 *   CC-22 — No duplicates exist in any taxonomy array
 */
class TaxonomyTest extends TestCase
{
    /**
     * colorFamilies should have exactly 22 entries.
     */
    public function test_colorFamilies_has_exactly_22_entries(): void
    {
        $this->assertCount(22, Taxonomy::$colorFamilies);
    }

    /**
     * undertones should have exactly 3 entries.
     */
    public function test_undertones_has_exactly_3_entries(): void
    {
        $this->assertCount(3, Taxonomy::$undertones);
    }

    /**
     * depths should have exactly 5 entries.
     */
    public function test_depths_has_exactly_5_entries(): void
    {
        $this->assertCount(5, Taxonomy::$depths);
    }

    /**
     * saturations should have exactly 5 entries.
     */
    public function test_saturations_has_exactly_5_entries(): void
    {
        $this->assertCount(5, Taxonomy::$saturations);
    }

    /**
     * finishes should have exactly 10 entries.
     */
    public function test_finishes_has_exactly_10_entries(): void
    {
        $this->assertCount(10, Taxonomy::$finishes);
    }

    /**
     * opacities should have exactly 4 entries.
     */
    public function test_opacities_has_exactly_4_entries(): void
    {
        $this->assertCount(4, Taxonomy::$opacities);
    }

    /**
     * All taxonomy arrays should contain only non-empty strings.
     */
    public function test_all_taxonomy_arrays_contain_only_non_empty_strings(): void
    {
        $allTaxonomyArrays = [
            'colorFamilies' => Taxonomy::$colorFamilies,
            'undertones'    => Taxonomy::$undertones,
            'depths'        => Taxonomy::$depths,
            'saturations'   => Taxonomy::$saturations,
            'finishes'      => Taxonomy::$finishes,
            'opacities'     => Taxonomy::$opacities,
        ];

        foreach ($allTaxonomyArrays as $arrayName => $taxonomyArray) {
            foreach ($taxonomyArray as $taxonomyValue) {
                $this->assertIsString($taxonomyValue, "Entry in {$arrayName} is not a string");
                $this->assertNotEmpty($taxonomyValue, "Entry in {$arrayName} is empty");
            }
        }
    }

    /**
     * No duplicates should exist in any taxonomy array.
     */
    public function test_no_duplicates_in_any_taxonomy_array(): void
    {
        $allTaxonomyArrays = [
            'colorFamilies' => Taxonomy::$colorFamilies,
            'undertones'    => Taxonomy::$undertones,
            'depths'        => Taxonomy::$depths,
            'saturations'   => Taxonomy::$saturations,
            'finishes'      => Taxonomy::$finishes,
            'opacities'     => Taxonomy::$opacities,
        ];

        foreach ($allTaxonomyArrays as $arrayName => $taxonomyArray) {
            $uniqueValues = array_unique($taxonomyArray);

            $this->assertCount(
                count($taxonomyArray),
                $uniqueValues,
                "Duplicate entries found in {$arrayName}"
            );
        }
    }
}
