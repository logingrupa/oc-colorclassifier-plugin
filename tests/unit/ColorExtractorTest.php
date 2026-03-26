<?php

use PHPUnit\Framework\TestCase;
use Logingrupa\ColorClassifier\Classes\ColorExtractor;

/**
 * Unit tests for ColorExtractor.
 *
 * Covers GD image processing pipeline requirements:
 *   CC-23 — cropCenterSquare produces image of target dimensions
 *   CC-24 — applyGaussianBlur returns a valid GdImage resource
 *   CC-25 — extractDominantColor from solid red returns near [255, 0, 0]
 *   CC-26 — extractColorPalette returns exactly 5 hex strings
 *   CC-27 — processImage returns null for invalid/unreachable URL
 *
 * Test fixtures are created using imagecreatetruecolor and imagefilledrectangle
 * to produce solid-color synthetic images without file I/O.
 */
class ColorExtractorTest extends TestCase
{
    /**
     * Create a solid-color GD image of the specified dimensions.
     *
     * @param int $width       Image width in pixels.
     * @param int $height      Image height in pixels.
     * @param int $red         Fill color red channel (0–255).
     * @param int $greenChannel Fill color green channel (0–255).
     * @param int $blue        Fill color blue channel (0–255).
     *
     * @return \GdImage
     */
    private function createSolidColorImage(
        int $width,
        int $height,
        int $red,
        int $greenChannel,
        int $blue
    ): \GdImage {
        $gdImage    = imagecreatetruecolor($width, $height);
        $fillColor  = imagecolorallocate($gdImage, $red, $greenChannel, $blue);
        imagefilledrectangle($gdImage, 0, 0, $width - 1, $height - 1, $fillColor);

        return $gdImage;
    }

    /**
     * cropCenterSquare should produce an image of exactly the requested square size.
     */
    public function test_cropCenterSquare_produces_image_of_target_dimensions(): void
    {
        $sourceImage  = $this->createSolidColorImage(200, 150, 100, 150, 200);
        $targetSize   = 64;
        $croppedImage = ColorExtractor::cropCenterSquare($sourceImage, $targetSize);

        $this->assertSame($targetSize, imagesx($croppedImage));
        $this->assertSame($targetSize, imagesy($croppedImage));

        imagedestroy($sourceImage);
        imagedestroy($croppedImage);
    }

    /**
     * cropCenterSquare should produce a square even from non-square source images.
     */
    public function test_cropCenterSquare_produces_square_from_non_square_source(): void
    {
        $wideSourceImage = $this->createSolidColorImage(300, 100, 50, 100, 150);
        $croppedImage    = ColorExtractor::cropCenterSquare($wideSourceImage, 50);

        $this->assertSame(imagesx($croppedImage), imagesy($croppedImage));

        imagedestroy($wideSourceImage);
        imagedestroy($croppedImage);
    }

    /**
     * applyGaussianBlur should return a valid GdImage instance.
     */
    public function test_applyGaussianBlur_returns_valid_gd_image(): void
    {
        $sourceImage  = $this->createSolidColorImage(50, 50, 200, 100, 50);
        $blurredImage = ColorExtractor::applyGaussianBlur($sourceImage, 3);

        $this->assertInstanceOf(\GdImage::class, $blurredImage);

        imagedestroy($blurredImage);
    }

    /**
     * extractDominantColor from a solid red image should return RGB near [255, 0, 0].
     */
    public function test_extractDominantColor_from_solid_red_returns_near_red(): void
    {
        $solidRedImage    = $this->createSolidColorImage(20, 20, 255, 0, 0);
        $dominantColorRgb = ColorExtractor::extractDominantColor($solidRedImage);

        $this->assertEqualsWithDelta(255, $dominantColorRgb['red'],   10);
        $this->assertEqualsWithDelta(0,   $dominantColorRgb['green'], 10);
        $this->assertEqualsWithDelta(0,   $dominantColorRgb['blue'],  10);

        imagedestroy($solidRedImage);
    }

    /**
     * extractColorPalette should return exactly 5 hex color strings.
     */
    public function test_extractColorPalette_returns_exactly_5_hex_strings(): void
    {
        $sourceImage       = $this->createSolidColorImage(30, 30, 100, 150, 200);
        $paletteHexColors  = ColorExtractor::extractColorPalette($sourceImage, 5);

        $this->assertCount(5, $paletteHexColors);

        foreach ($paletteHexColors as $hexColor) {
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $hexColor);
        }

        imagedestroy($sourceImage);
    }

    /**
     * processImage should return null for an invalid/unreachable URL.
     */
    public function test_processImage_returns_null_for_invalid_url(): void
    {
        $imageResult = ColorExtractor::processImage('https://invalid.example.invalid/no-such-image.jpg');

        $this->assertNull($imageResult);
    }

    /**
     * processImage should return null for a non-image response.
     */
    public function test_processImage_returns_null_for_non_image_url(): void
    {
        $imageResult = ColorExtractor::processImage('not-a-valid-url-at-all');

        $this->assertNull($imageResult);
    }
}
