<?php

namespace Logingrupa\ColorClassifier\Classes;

/**
 * ImageHexSampler - averages a small square patch of an image at a relative point.
 *
 * The Family board sends the click position as fractions of the rendered image,
 * so the server samples the original pixels without a canvas read (which the
 * shop image host's CORS rules would block). Averaging a patch keeps one noisy
 * pixel from deciding the swatch.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class ImageHexSampler
{
    /** @var int Default patch edge in pixels. */
    public const DEFAULT_PATCH_SIZE = 5;

    /**
     * Average the patch around a relative point and return it as #RRGGBB.
     *
     * The patch window is shifted to stay inside the image, and shrinks to the
     * whole image when the image is smaller than the patch.
     *
     * @param \GdImage $obImage Image to read; it is not modified.
     * @param float $flRelativeX Horizontal position, 0 (left) to 1 (right).
     * @param float $flRelativeY Vertical position, 0 (top) to 1 (bottom).
     * @param int $iPatchSize Odd, positive patch edge in pixels.
     *
     * @return string Uppercase hex color.
     *
     * @throws \InvalidArgumentException When the point is outside 0..1 or the patch size is not odd and positive.
     */
    public static function sampleHex(\GdImage $obImage, float $flRelativeX, float $flRelativeY, int $iPatchSize = self::DEFAULT_PATCH_SIZE): string
    {
        self::assertRelativePoint($flRelativeX, 'x');
        self::assertRelativePoint($flRelativeY, 'y');

        if ($iPatchSize < 1 || $iPatchSize % 2 === 0) {
            throw new \InvalidArgumentException('Patch size must be an odd positive number, got ' . $iPatchSize);
        }

        $iWidth = imagesx($obImage);
        $iHeight = imagesy($obImage);
        $iLeft = self::resolveWindowStart($flRelativeX, $iWidth, $iPatchSize);
        $iTop = self::resolveWindowStart($flRelativeY, $iHeight, $iPatchSize);
        $iPatchWidth = min($iPatchSize, $iWidth);
        $iPatchHeight = min($iPatchSize, $iHeight);

        $iRedSum = 0;
        $iGreenSum = 0;
        $iBlueSum = 0;

        for ($iOffsetY = 0; $iOffsetY < $iPatchHeight; $iOffsetY++) {
            for ($iOffsetX = 0; $iOffsetX < $iPatchWidth; $iOffsetX++) {
                $arPixel = self::readPixel($obImage, $iLeft + $iOffsetX, $iTop + $iOffsetY);
                $iRedSum += $arPixel['red'];
                $iGreenSum += $arPixel['green'];
                $iBlueSum += $arPixel['blue'];
            }
        }

        $iPixelCount = $iPatchWidth * $iPatchHeight;

        return ColorConverter::rgbToHex(
            (int) round($iRedSum / $iPixelCount),
            (int) round($iGreenSum / $iPixelCount),
            (int) round($iBlueSum / $iPixelCount)
        );
    }

    /**
     * @param float $flValue Relative coordinate.
     * @param string $sAxis Axis name for the error message.
     *
     * @return void
     *
     * @throws \InvalidArgumentException When the value is not a number between 0 and 1.
     */
    private static function assertRelativePoint(float $flValue, string $sAxis): void
    {
        if (is_nan($flValue) || $flValue < 0.0 || $flValue > 1.0) {
            throw new \InvalidArgumentException('Relative ' . $sAxis . ' must be between 0 and 1');
        }
    }

    /**
     * First pixel of the patch window along one axis, kept inside the image.
     *
     * @param float $flRelative Relative coordinate.
     * @param int $iLength Image length on that axis in pixels.
     * @param int $iPatchSize Patch edge in pixels.
     *
     * @return int
     */
    private static function resolveWindowStart(float $flRelative, int $iLength, int $iPatchSize): int
    {
        $iCenter = min($iLength - 1, (int) floor($flRelative * $iLength));
        $iStart = $iCenter - intdiv($iPatchSize - 1, 2);

        return max(0, min($iStart, $iLength - min($iPatchSize, $iLength)));
    }

    /**
     * Palette and truecolor images share one reader, so the caller's image is never converted.
     *
     * @return array{red: int, green: int, blue: int}
     */
    private static function readPixel(\GdImage $obImage, int $iX, int $iY): array
    {
        $iColor = imagecolorat($obImage, $iX, $iY);

        if (!imageistruecolor($obImage)) {
            $arColor = imagecolorsforindex($obImage, $iColor);

            return ['red' => $arColor['red'], 'green' => $arColor['green'], 'blue' => $arColor['blue']];
        }

        return [
            'red'   => ($iColor >> 16) & 0xFF,
            'green' => ($iColor >> 8) & 0xFF,
            'blue'  => $iColor & 0xFF,
        ];
    }
}
