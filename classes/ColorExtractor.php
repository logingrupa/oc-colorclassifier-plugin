<?php

namespace Logingrupa\ColorClassifier\Classes;

/**
 * ColorExtractor — PHP GD image processing pipeline for color extraction.
 *
 * Downloads product images, crops to center square, applies Gaussian blur
 * to reduce noise, then extracts the dominant color and a representative
 * color palette. All methods are static and depend only on PHP GD.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class ColorExtractor
{
    /** @var int Size in pixels for the center-crop sampling region. */
    private const DEFAULT_CROP_SIZE_PIXELS = 50;

    /** @var int Number of Gaussian blur passes to apply. */
    private const DEFAULT_BLUR_PASSES = 10;

    /** @var int Number of colors in the extracted palette. */
    private const DEFAULT_PALETTE_SIZE = 5;

    /** @var int Thumbnail edge size for palette extraction sampling. */
    private const PALETTE_THUMBNAIL_SIZE_PIXELS = 15;

    /** @var int HTTP request timeout in seconds for image downloads. */
    private const DOWNLOAD_TIMEOUT_SECONDS = 10;

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
        $streamContext = stream_context_create([
            'http' => [
                'timeout'    => self::DOWNLOAD_TIMEOUT_SECONDS,
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
    public static function cropCenterSquare(\GdImage $sourceImage, int $squareSize = self::DEFAULT_CROP_SIZE_PIXELS): \GdImage
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
    public static function applyGaussianBlur(\GdImage $image, int $blurPasses = self::DEFAULT_BLUR_PASSES): \GdImage
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
     * @param \GdImage $image       Source GD image.
     * @param int      $paletteSize Number of colors to return.
     *
     * @return array<int, string> Array of hex color strings (e.g. ['#FF0000', ...]).
     */
    public static function extractColorPalette(\GdImage $image, int $paletteSize = self::DEFAULT_PALETTE_SIZE): array
    {
        $thumbnailSize  = self::PALETTE_THUMBNAIL_SIZE_PIXELS;
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
     * Run the full image processing pipeline for a single image URL.
     *
     * Downloads the image, crops to center square, applies Gaussian blur,
     * then extracts both dominant color and palette. Returns null if any
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
            $downloadedImage = self::downloadImage($imageUrl);

            if ($downloadedImage === false) {
                return null;
            }

            $croppedImage = self::cropCenterSquare($downloadedImage);
            imagedestroy($downloadedImage);

            $blurredImage = self::applyGaussianBlur($croppedImage);

            $dominantColorRgb = self::extractDominantColor($blurredImage);
            $paletteHexColors = self::extractColorPalette($blurredImage);

            imagedestroy($blurredImage);

            return [
                'rgb'     => $dominantColorRgb,
                'palette' => $paletteHexColors,
            ];
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
