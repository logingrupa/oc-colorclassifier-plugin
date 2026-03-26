<?php

namespace Logingrupa\ColorClassifier\Models;

use System\Models\SettingModel;

/**
 * Settings — Plugin-wide configuration stored in system_settings table.
 *
 * Provides configurable defaults for all color extraction and classification
 * parameters, replacing previously hardcoded constants in ColorExtractor and
 * ColorClassifier. Values persist under the code `logingrupa_colorclassifier_settings`.
 *
 * @package Logingrupa\ColorClassifier\Models
 */
class Settings extends SettingModel
{
    /**
     * Unique settings code used as the key in system_settings table.
     *
     * @var string
     */
    public $settingsCode = 'logingrupa_colorclassifier_settings';

    /**
     * Path to the form fields YAML definition (relative to the model directory).
     *
     * @var string
     */
    public $settingsFields = 'fields.yaml';

    /**
     * Populate default values for all configurable parameters.
     *
     * Defaults exactly match the previously hardcoded constants in
     * ColorExtractor and ColorClassifier, ensuring backward compatibility.
     *
     * @return void
     */
    public function initSettingsData(): void
    {
        // ColorExtractor defaults
        $this->crop_size_pixels              = 50;
        $this->blur_passes                   = 10;
        $this->palette_size                  = 5;
        $this->palette_thumbnail_size_pixels = 15;
        $this->download_timeout_seconds      = 10;

        // ColorClassifier achromatic detection thresholds
        $this->achromatic_saturation_threshold = 5.0;
        $this->white_lightness_threshold       = 90.0;
        $this->black_lightness_threshold       = 10.0;

        // ColorClassifier depth thresholds
        $this->very_light_depth_threshold = 80.0;
        $this->light_depth_threshold      = 65.0;
        $this->medium_depth_threshold     = 40.0;
        $this->dark_depth_threshold       = 20.0;

        // ColorClassifier saturation thresholds
        $this->neon_saturation_threshold   = 85.0;
        $this->vivid_saturation_threshold  = 65.0;
        $this->medium_saturation_threshold = 40.0;
        $this->soft_saturation_threshold   = 20.0;
    }
}
