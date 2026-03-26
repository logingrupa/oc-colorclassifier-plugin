<?php

use PHPUnit\Framework\TestCase;
use Logingrupa\ColorClassifier\Classes\ColorExtractor;
use Logingrupa\ColorClassifier\Classes\ColorConverter;
use Logingrupa\ColorClassifier\Classes\ColorClassifier;
use Logingrupa\ColorClassifier\Classes\ColorNamer;

/**
 * Functional tests for the BatchProcessor pipeline.
 *
 * These tests exercise the full color processing pipeline on synthetic
 * GD images without requiring database access or OctoberCMS bootstrap.
 * They verify the integration between ColorExtractor, ColorConverter,
 * ColorClassifier, and ColorNamer.
 *
 * Tests that require database interaction (ColorEntry::updateOrCreate,
 * BatchProcessor::processNew, BatchProcessor::processAll) are excluded
 * here as they require OctoberCMS and a database connection.
 *
 * Covers:
 *   CC-28 — Full pipeline returns correct structure for synthetic image
 *   CC-29 — Pipeline correctly classifies a solid blue synthetic image
 *   CC-30 — Pipeline output is complete with all required fields
 */
class BatchProcessorTest extends TestCase
{
    /**
     * Create a solid-color GD image.
     *
     * @param int $red   Red channel value.
     * @param int $green Green channel value.
     * @param int $blue  Blue channel value.
     * @param int $size  Image size in pixels (square).
     *
     * @return \GdImage
     */
    private function createSolidColorGdImage(int $red, int $green, int $blue, int $size = 50): \GdImage
    {
        $gdImage   = imagecreatetruecolor($size, $size);
        $fillColor = imagecolorallocate($gdImage, $red, $green, $blue);
        imagefilledrectangle($gdImage, 0, 0, $size - 1, $size - 1, $fillColor);

        return $gdImage;
    }

    /**
     * Run the color processing pipeline on a synthetic GD image.
     *
     * This mirrors what BatchProcessor::storeColorEntry does, but
     * operates on a pre-created GD image instead of downloading from URL.
     *
     * @param \GdImage $gdImage The synthetic test image.
     *
     * @return array{hex_color: string, oklch_values: array, palette_colors: array, color_name: string, taxonomy: array}
     */
    private function runPipelineOnGdImage(\GdImage $gdImage): array
    {
        $croppedImage     = ColorExtractor::cropCenterSquare($gdImage);
        $blurredImage     = ColorExtractor::applyGaussianBlur($croppedImage, 3);
        $dominantColorRgb = ColorExtractor::extractDominantColor($blurredImage);
        $paletteHexColors = ColorExtractor::extractColorPalette($blurredImage);

        imagedestroy($blurredImage);

        $red   = $dominantColorRgb['red'];
        $green = $dominantColorRgb['green'];
        $blue  = $dominantColorRgb['blue'];

        $hexColor    = ColorConverter::rgbToHex($red, $green, $blue);
        $oklchValues = ColorConverter::rgbToOklch($red, $green, $blue);
        $colorName   = ColorNamer::findNearestColorName($hexColor);
        $taxonomy    = ColorClassifier::classify($red, $green, $blue);

        return [
            'hex_color'    => $hexColor,
            'oklch_values' => $oklchValues,
            'palette_colors' => $paletteHexColors,
            'color_name'   => $colorName,
            'taxonomy'     => $taxonomy,
        ];
    }

    /**
     * Full pipeline on a solid red image should produce valid output structure.
     */
    public function test_pipeline_on_solid_red_image_returns_valid_output_structure(): void
    {
        $solidRedImage = $this->createSolidColorGdImage(255, 0, 0);
        $pipelineResult = $this->runPipelineOnGdImage($solidRedImage);
        imagedestroy($solidRedImage);

        $this->assertArrayHasKey('hex_color', $pipelineResult);
        $this->assertArrayHasKey('oklch_values', $pipelineResult);
        $this->assertArrayHasKey('palette_colors', $pipelineResult);
        $this->assertArrayHasKey('color_name', $pipelineResult);
        $this->assertArrayHasKey('taxonomy', $pipelineResult);

        $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $pipelineResult['hex_color']);
        $this->assertNotEmpty($pipelineResult['color_name']);
        $this->assertCount(5, $pipelineResult['palette_colors']);
    }

    /**
     * Full pipeline on a solid blue image should classify as Blue family.
     */
    public function test_pipeline_on_solid_blue_image_classifies_as_blue_family(): void
    {
        $solidBlueImage  = $this->createSolidColorGdImage(0, 0, 255);
        $pipelineResult  = $this->runPipelineOnGdImage($solidBlueImage);
        imagedestroy($solidBlueImage);

        $taxonomy = $pipelineResult['taxonomy'];

        $this->assertSame('Blue', $taxonomy['family']);
        $this->assertSame('Cool', $taxonomy['undertone']);
    }

    /**
     * Taxonomy in pipeline output should always include all required dimensions.
     */
    public function test_pipeline_taxonomy_always_has_all_required_dimensions(): void
    {
        $solidGreenImage = $this->createSolidColorGdImage(0, 180, 60);
        $pipelineResult  = $this->runPipelineOnGdImage($solidGreenImage);
        imagedestroy($solidGreenImage);

        $taxonomy = $pipelineResult['taxonomy'];

        $this->assertArrayHasKey('family', $taxonomy);
        $this->assertArrayHasKey('undertone', $taxonomy);
        $this->assertArrayHasKey('depth', $taxonomy);
        $this->assertArrayHasKey('saturation', $taxonomy);
        $this->assertArrayHasKey('finish', $taxonomy);
        $this->assertArrayHasKey('opacity', $taxonomy);
        $this->assertArrayHasKey('confidence_score', $taxonomy);

        $this->assertNull($taxonomy['finish']);
        $this->assertSame('Opaque', $taxonomy['opacity']);
    }

    /**
     * OKLCH values in pipeline output should always be within valid ranges.
     */
    public function test_pipeline_oklch_values_are_within_valid_ranges(): void
    {
        $solidOrangeImage = $this->createSolidColorGdImage(255, 128, 0);
        $pipelineResult   = $this->runPipelineOnGdImage($solidOrangeImage);
        imagedestroy($solidOrangeImage);

        $oklchValues = $pipelineResult['oklch_values'];

        $this->assertGreaterThanOrEqual(0.0, $oklchValues['lightness']);
        $this->assertLessThanOrEqual(1.0, $oklchValues['lightness']);
        $this->assertGreaterThanOrEqual(0.0, $oklchValues['chroma']);
        $this->assertLessThanOrEqual(0.4, $oklchValues['chroma']);
        $this->assertGreaterThanOrEqual(0.0, $oklchValues['hue']);
        $this->assertLessThanOrEqual(360.0, $oklchValues['hue']);
    }
}
