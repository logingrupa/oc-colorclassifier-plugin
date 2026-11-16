<?php

use Logingrupa\ColorClassifier\Classes\ImageHexSampler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The drawer picks a swatch hex by sampling a 5x5 patch of the original
 * image on the server. The patch average keeps a single noisy pixel from
 * deciding the color, and the sampler must never read outside the image.
 */
class ImageHexSamplerTest extends TestCase
{
    private function makeSolidImage(int $iRed, int $iGreen, int $iBlue, int $iWidth = 40, int $iHeight = 30): \GdImage
    {
        $obImage = imagecreatetruecolor($iWidth, $iHeight);
        imagefilledrectangle($obImage, 0, 0, $iWidth - 1, $iHeight - 1, imagecolorallocate($obImage, $iRed, $iGreen, $iBlue));

        return $obImage;
    }

    /**
     * Left half black, right half white.
     */
    private function makeSplitImage(int $iWidth = 10, int $iHeight = 10): \GdImage
    {
        $obImage = $this->makeSolidImage(255, 255, 255, $iWidth, $iHeight);
        imagefilledrectangle($obImage, 0, 0, intdiv($iWidth, 2) - 1, $iHeight - 1, imagecolorallocate($obImage, 0, 0, 0));

        return $obImage;
    }

    public function test_solid_image_returns_its_color_at_any_point(): void
    {
        $obImage = $this->makeSolidImage(0x33, 0x66, 0xCC);

        foreach ([[0.0, 0.0], [0.5, 0.5], [1.0, 1.0], [0.2, 0.9]] as [$flX, $flY]) {
            $this->assertSame('#3366CC', ImageHexSampler::sampleHex($obImage, $flX, $flY));
        }
    }

    public function test_split_image_returns_the_color_under_the_point(): void
    {
        $obImage = $this->makeSplitImage(100, 20);

        $this->assertSame('#000000', ImageHexSampler::sampleHex($obImage, 0.1, 0.5));
        $this->assertSame('#FFFFFF', ImageHexSampler::sampleHex($obImage, 0.9, 0.5));
    }

    public function test_corners_clamp_the_patch_inside_the_image(): void
    {
        $obImage = $this->makeSplitImage(10, 10);

        $this->assertSame('#000000', ImageHexSampler::sampleHex($obImage, 0.0, 0.0));
        $this->assertSame('#FFFFFF', ImageHexSampler::sampleHex($obImage, 1.0, 1.0));
    }

    public function test_patch_on_the_boundary_returns_the_averaged_grey(): void
    {
        $obImage = $this->makeSplitImage(10, 10);

        // Pixel x=5 gives columns 3..7: two black, three white = 153.
        $this->assertSame('#999999', ImageHexSampler::sampleHex($obImage, 0.5, 0.5));
    }

    public function test_image_smaller_than_the_patch_averages_the_whole_image(): void
    {
        $obImage = $this->makeSplitImage(2, 2);

        $this->assertSame('#808080', ImageHexSampler::sampleHex($obImage, 0.5, 0.5));
    }

    public function test_palette_image_is_sampled_without_error(): void
    {
        $obImage = imagecreate(20, 20);
        imagecolorallocate($obImage, 0xAA, 0x11, 0x22);

        $this->assertSame('#AA1122', ImageHexSampler::sampleHex($obImage, 0.5, 0.5));
        $this->assertFalse(imageistruecolor($obImage));
    }

    #[DataProvider('provideOutOfRangePoints')]
    public function test_point_outside_the_image_throws(float $flX, float $flY): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ImageHexSampler::sampleHex($this->makeSolidImage(1, 2, 3), $flX, $flY);
    }

    /**
     * @return array<string, array{float, float}>
     */
    public static function provideOutOfRangePoints(): array
    {
        return [
            'x below zero' => [-0.01, 0.5],
            'x above one'  => [1.01, 0.5],
            'y below zero' => [0.5, -0.01],
            'y above one'  => [0.5, 1.01],
            'x not a number' => [NAN, 0.5],
        ];
    }

    #[DataProvider('provideBadPatchSizes')]
    public function test_even_or_non_positive_patch_size_throws(int $iPatchSize): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ImageHexSampler::sampleHex($this->makeSolidImage(1, 2, 3), 0.5, 0.5, $iPatchSize);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function provideBadPatchSizes(): array
    {
        return ['zero' => [0], 'negative' => [-3], 'even' => [4]];
    }
}
