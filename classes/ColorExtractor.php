<?php

namespace Logingrupa\ColorClassifier\Classes;

use Logingrupa\ColorClassifier\Models\Settings;

/**
 * ColorExtractor — PHP GD image processing pipeline for color extraction.
 *
 * Downloads product images, crops to center square, applies Gaussian blur
 * to reduce noise, then extracts the dominant color and a representative
 * color palette. All methods are static and depend only on PHP GD.
 *
 * Processing parameters are read from the Settings model at runtime.
 * Fallback constants are used when the database is unavailable (e.g. tests).
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class ColorExtractor
{
    /** @var int Fallback crop size when Settings model is unavailable. */
    private const FALLBACK_CROP_SIZE_PIXELS = 50;

    /** @var int Fallback blur passes when Settings model is unavailable. */
    private const FALLBACK_BLUR_PASSES = 10;

    /** @var int Fallback palette size when Settings model is unavailable. */
    private const FALLBACK_PALETTE_SIZE = 5;

    /** @var int Fallback palette thumbnail edge size when Settings model is unavailable. */
    private const FALLBACK_PALETTE_THUMBNAIL_SIZE_PIXELS = 15;

    /** @var int Fallback download timeout when Settings model is unavailable. */
    private const FALLBACK_DOWNLOAD_TIMEOUT_SECONDS = 10;

    /**
     * Retrieve a setting value from the Settings model with a safe fallback.
     *
     * Wraps Settings::get() in a try/catch so that classes running without a
     * database connection (e.g. standalone PHPUnit tests) receive the fallback
     * constant value instead of throwing an exception.
     *
     * @param string $sKey             Settings key to read.
     * @param mixed  $fallbackDefault  Value to return when Settings is unavailable.
     *
     * @return mixed The stored setting value, or $fallbackDefault on error.
     */
    private static function getSettingValue(string $sKey, mixed $fallbackDefault): mixed
    {
        try {
            return Settings::get($sKey, $fallbackDefault);
        } catch (\Throwable $exception) {
            return $fallbackDefault;
        }
    }

    /**
     * Download an image from a URL and return a GD image resource.
     *
     * Uses file_get_contents with a stream context to enforce timeout
     * and set a browser-like User-Agent for CDN compatibility.
     *
     * @param string $imageUrl Public URL of the image to download.
     *
     * @return \GdImage|false GD image resource, or false on failure.
     */
    public static function downloadImage(string $imageUrl): \GdImage|false
    {
        $iTimeout = (int) self::getSettingValue('download_timeout_seconds', self::FALLBACK_DOWNLOAD_TIMEOUT_SECONDS);

        $streamContext = stream_context_create([
            'http' => [
                'timeout'    => $iTimeout,
                'user_agent' => 'Mozilla/5.0 (compatible; ColorClassifier/1.0)',
            ],
        ]);

        $imageData = @file_get_contents($imageUrl, false, $streamContext);

        if ($imageData === false || strlen($imageData) === 0) {
            return false;
        }

        $gdImage = @imagecreatefromstring($imageData);

        return $gdImage ?: false;
    }

    /**
     * Crop a fixed-size square from the exact center of the source image.
     *
     * Takes exactly squareSize x squareSize pixels from the center of the
     * original image without resampling. If the source image is smaller than
     * the requested crop size, falls back to resampling the largest centered
     * square down to the target size.
     *
     * @param \GdImage $sourceImage The GD image to crop.
     * @param int      $squareSize  Exact pixel size to extract from center.
     *
     * @return \GdImage Cropped square image.
     */
    public static function cropCenterSquare(\GdImage $sourceImage, int $squareSize = self::FALLBACK_CROP_SIZE_PIXELS): \GdImage
    {
        $sourceWidth  = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);

        if ($sourceWidth >= $squareSize && $sourceHeight >= $squareSize) {
            $cropOffsetX = (int) (($sourceWidth  - $squareSize) / 2);
            $cropOffsetY = (int) (($sourceHeight - $squareSize) / 2);

            $croppedImage = imagecreatetruecolor($squareSize, $squareSize);
            imagecopy(
                $croppedImage,
                $sourceImage,
                0, 0,
                $cropOffsetX, $cropOffsetY,
                $squareSize, $squareSize
            );

            return $croppedImage;
        }

        $smallerSide = min($sourceWidth, $sourceHeight);
        $cropOffsetX = (int) (($sourceWidth  - $smallerSide) / 2);
        $cropOffsetY = (int) (($sourceHeight - $smallerSide) / 2);

        $croppedImage = imagecreatetruecolor($squareSize, $squareSize);
        imagecopyresampled(
            $croppedImage,
            $sourceImage,
            0, 0,
            $cropOffsetX, $cropOffsetY,
            $squareSize, $squareSize,
            $smallerSide, $smallerSide
        );

        return $croppedImage;
    }

    /**
     * Apply Gaussian blur to a GD image N times.
     *
     * Each pass applies PHP GD's built-in Gaussian blur filter.
     * More passes increase the effective blur radius, reducing color noise
     * and smoothing gradients for more accurate dominant color extraction.
     *
     * @param \GdImage $image      The GD image to blur (modified in place).
     * @param int      $blurPasses Number of filter passes to apply.
     *
     * @return \GdImage The blurred GD image (same resource, modified in place).
     */
    public static function applyGaussianBlur(\GdImage $image, int $blurPasses = self::FALLBACK_BLUR_PASSES): \GdImage
    {
        for ($passIndex = 0; $passIndex < $blurPasses; $passIndex++) {
            imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
        }

        return $image;
    }

    /**
     * Extract the dominant color from a GD image by resampling to 1×1.
     *
     * Resizing the image to a single pixel forces PHP GD to compute
     * the average color across the entire image, effectively returning
     * the dominant color.
     *
     * @param \GdImage $image Source GD image (can be any size).
     *
     * @return array{red: int, green: int, blue: int} Dominant color RGB values.
     */
    public static function extractDominantColor(\GdImage $image): array
    {
        $singlePixelImage = imagecreatetruecolor(1, 1);
        imagecopyresampled($singlePixelImage, $image, 0, 0, 0, 0, 1, 1, imagesx($image), imagesy($image));

        $pixelColorIndex = imagecolorat($singlePixelImage, 0, 0);
        $pixelColorArray = imagecolorsforindex($singlePixelImage, $pixelColorIndex);

        imagedestroy($singlePixelImage);

        return [
            'red'   => $pixelColorArray['red'],
            'green' => $pixelColorArray['green'],
            'blue'  => $pixelColorArray['blue'],
        ];
    }

    /**
     * Extract a color palette from a GD image using thumbnail frequency sampling.
     *
     * Resizes the image to a small thumbnail, collects all pixel colors,
     * then returns the most frequently occurring colors as hex strings.
     *
     * @param \GdImage $image         Source GD image.
     * @param int      $paletteSize   Number of colors to return.
     * @param int      $thumbnailSize Edge size of the sampling thumbnail in pixels.
     *
     * @return array<int, string> Array of hex color strings (e.g. ['#FF0000', ...]).
     */
    public static function extractColorPalette(
        \GdImage $image,
        int $paletteSize = self::FALLBACK_PALETTE_SIZE,
        int $thumbnailSize = self::FALLBACK_PALETTE_THUMBNAIL_SIZE_PIXELS
    ): array {
        $thumbnailImage = imagecreatetruecolor($thumbnailSize, $thumbnailSize);
        imagecopyresampled(
            $thumbnailImage,
            $image,
            0, 0, 0, 0,
            $thumbnailSize, $thumbnailSize,
            imagesx($image), imagesy($image)
        );

        $colorFrequencyMap = [];

        for ($row = 0; $row < $thumbnailSize; $row++) {
            for ($column = 0; $column < $thumbnailSize; $column++) {
                $pixelColorIndex = imagecolorat($thumbnailImage, $column, $row);
                $pixelColorArray = imagecolorsforindex($thumbnailImage, $pixelColorIndex);

                $quantizedRed   = (int) (round($pixelColorArray['red']   / 32) * 32);
                $quantizedGreen = (int) (round($pixelColorArray['green'] / 32) * 32);
                $quantizedBlue  = (int) (round($pixelColorArray['blue']  / 32) * 32);

                $quantizedRed   = min(255, $quantizedRed);
                $quantizedGreen = min(255, $quantizedGreen);
                $quantizedBlue  = min(255, $quantizedBlue);

                $hexKey = sprintf('%02X%02X%02X', $quantizedRed, $quantizedGreen, $quantizedBlue);

                if (!isset($colorFrequencyMap[$hexKey])) {
                    $colorFrequencyMap[$hexKey] = 0;
                }

                $colorFrequencyMap[$hexKey]++;
            }
        }

        imagedestroy($thumbnailImage);

        arsort($colorFrequencyMap);
        $topColorKeys = array_slice(array_keys($colorFrequencyMap), 0, $paletteSize);

        $paletteHexColors = [];
        foreach ($topColorKeys as $colorKey) {
            $paletteHexColors[] = '#' . $colorKey;
        }

        // Pad with repeats of the last color if not enough unique colors exist
        while (count($paletteHexColors) < $paletteSize) {
            $paletteHexColors[] = end($paletteHexColors) ?: '#000000';
        }

        return $paletteHexColors;
    }

    /**
     * Convert a GD image to a base64-encoded PNG data URI.
     *
     * @param \GdImage $image The GD image to encode.
     *
     * @return string Base64 data URI string (data:image/png;base64,...).
     */
    public static function gdImageToBase64Png(\GdImage $image): string
    {
        ob_start();
        imagepng($image);
        $pngData = ob_get_clean();

        return 'data:image/png;base64,' . base64_encode($pngData);
    }

    /**
     * Run the full image processing pipeline for a single image URL.
     *
     * Downloads the image, crops to center square, applies Gaussian blur,
     * then extracts both dominant color and palette. Processing parameters
     * are read from the Settings model at runtime. Returns null if any
     * step fails (network error, unsupported format, etc.).
     *
     * @param string $imageUrl Public URL of the product image.
     *
     * @return array{rgb: array{red: int, green: int, blue: int}, palette: array<int, string>}|null
     *   Extracted color data or null on failure.
     */
    public static function processImage(string $imageUrl): array|null
    {
        try {
            $iCropSize     = (int) self::getSettingValue('crop_size_pixels', self::FALLBACK_CROP_SIZE_PIXELS);
            $iBlurPasses   = (int) self::getSettingValue('blur_passes', self::FALLBACK_BLUR_PASSES);
            $iPaletteSize  = (int) self::getSettingValue('palette_size', self::FALLBACK_PALETTE_SIZE);
            $iThumbnailSize = (int) self::getSettingValue('palette_thumbnail_size_pixels', self::FALLBACK_PALETTE_THUMBNAIL_SIZE_PIXELS);

            $downloadedImage = self::downloadImage($imageUrl);

            if ($downloadedImage === false) {
                return null;
            }

            $croppedImage = self::cropCenterSquare($downloadedImage, $iCropSize);
            imagedestroy($downloadedImage);

            $croppedImageBase64 = self::gdImageToBase64Png($croppedImage);

            $blurredImage = self::applyGaussianBlur($croppedImage, $iBlurPasses);

            $dominantColorRgb = self::extractDominantColor($blurredImage);
            $paletteHexColors = self::extractColorPalette($blurredImage, $iPaletteSize, $iThumbnailSize);

            imagedestroy($blurredImage);

            return [
                'rgb'                => $dominantColorRgb,
                'palette'            => $paletteHexColors,
                'cropped_image_data' => $croppedImageBase64,
            ];
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
