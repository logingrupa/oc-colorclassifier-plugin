---
phase: quick-1
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - plugins/logingrupa/colorclassifier/models/Settings.php
  - plugins/logingrupa/colorclassifier/models/settings/fields.yaml
  - plugins/logingrupa/colorclassifier/Plugin.php
  - plugins/logingrupa/colorclassifier/controllers/ColorEntries.php
  - plugins/logingrupa/colorclassifier/controllers/colorentries/_list_toolbar.htm
  - plugins/logingrupa/colorclassifier/controllers/colorentries/_settings_popup.htm
  - plugins/logingrupa/colorclassifier/classes/ColorExtractor.php
  - plugins/logingrupa/colorclassifier/classes/ColorClassifier.php
autonomous: true
requirements: [SETTINGS-01]

must_haves:
  truths:
    - "User can click a Settings button on the ColorEntries toolbar to open a modal popup"
    - "Modal displays all configurable parameters with current values (crop size, blur passes, palette size, classifier thresholds)"
    - "User can modify settings and save them — values persist in system_settings table"
    - "ColorExtractor and ColorClassifier read settings from the Settings model instead of hardcoded constants"
    - "Default values match the current hardcoded constants (backward-compatible)"
  artifacts:
    - path: "plugins/logingrupa/colorclassifier/models/Settings.php"
      provides: "Settings model extending System\\Models\\SettingModel"
      contains: "settingsCode"
    - path: "plugins/logingrupa/colorclassifier/models/settings/fields.yaml"
      provides: "Form field definitions for settings popup"
      contains: "crop_size_pixels"
    - path: "plugins/logingrupa/colorclassifier/controllers/colorentries/_settings_popup.htm"
      provides: "Popup partial with modal-header, modal-body (form), modal-footer (save/close)"
  key_links:
    - from: "controllers/colorentries/_list_toolbar.htm"
      to: "controllers/ColorEntries.php::onLoadSettingsPopup"
      via: "data-control=popup data-handler=onLoadSettingsPopup"
      pattern: "data-handler.*onLoadSettingsPopup"
    - from: "controllers/ColorEntries.php::onSaveSettings"
      to: "models/Settings.php"
      via: "Settings::set() call"
      pattern: "Settings::set"
    - from: "classes/ColorExtractor.php"
      to: "models/Settings.php"
      via: "Settings::get() for crop_size, blur_passes, palette_size"
      pattern: "Settings::get"
    - from: "classes/ColorClassifier.php"
      to: "models/Settings.php"
      via: "Settings::get() for classification thresholds"
      pattern: "Settings::get"
---

<objective>
Add a configurable settings modal popup to the ColorClassifier backend, replacing all hardcoded
processing constants with values stored in October CMS's `system_settings` table.

Purpose: Allow users to tune color extraction and classification parameters without editing PHP source code.
Output: Settings model, fields YAML, popup partial, toolbar button, and updated extractor/classifier classes.
</objective>

<execution_context>
@C:/Users/rolan/.claude/get-shit-done/workflows/execute-plan.md
@C:/Users/rolan/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@plugins/logingrupa/colorclassifier/Plugin.php
@plugins/logingrupa/colorclassifier/models/Settings.php (to create)
@plugins/logingrupa/colorclassifier/controllers/ColorEntries.php
@plugins/logingrupa/colorclassifier/controllers/colorentries/_list_toolbar.htm
@plugins/logingrupa/colorclassifier/classes/ColorExtractor.php
@plugins/logingrupa/colorclassifier/classes/ColorClassifier.php

<interfaces>
<!-- October CMS SettingModel pattern (System\Models\SettingModel) -->
<!-- The Settings model MUST extend this base class, NOT use the deprecated behavior -->

Base class: System\Models\SettingModel
Required properties:
  - public $settingsCode = 'logingrupa_colorclassifier_settings';
  - public $settingsFields = 'fields.yaml';

Static API:
  - Settings::instance()        -> returns the populated model
  - Settings::get('key', $default) -> read a single value
  - Settings::set('key', $value)   -> write a single value
  - Settings::set(['key1' => $val1, 'key2' => $val2]) -> write multiple

Method override for defaults:
  - public function initSettingsData() { $this->field = default; ... }

<!-- October CMS Popup pattern (remote AJAX popup) -->
Toolbar button: data-control="popup" data-handler="onLoadSettingsPopup"
Controller handler returns: $this->makePartial('settings_popup')
Popup partial structure: modal-header + modal-body (form widget render) + modal-footer (save/close buttons)
Save button: data-request="onSaveSettings" data-request-close-popup
Form widget: Backend\Widgets\Form initialized with Settings::instance() model and fields.yaml config
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Create Settings model and fields YAML</name>
  <files>
    plugins/logingrupa/colorclassifier/models/Settings.php,
    plugins/logingrupa/colorclassifier/models/settings/fields.yaml
  </files>
  <action>
Create `models/Settings.php` extending `System\Models\SettingModel`.

Settings model requirements:
- `$settingsCode = 'logingrupa_colorclassifier_settings'`
- `$settingsFields = 'fields.yaml'`
- Override `initSettingsData()` to set ALL defaults matching current hardcoded values

Default values (from current constants):
```
// ColorExtractor defaults
crop_size_pixels = 50
blur_passes = 10
palette_size = 5
palette_thumbnail_size_pixels = 15
download_timeout_seconds = 10

// ColorClassifier thresholds
achromatic_saturation_threshold = 5.0
white_lightness_threshold = 90.0
black_lightness_threshold = 10.0
very_light_depth_threshold = 80.0
light_depth_threshold = 65.0
medium_depth_threshold = 40.0
dark_depth_threshold = 20.0
neon_saturation_threshold = 85.0
vivid_saturation_threshold = 65.0
medium_saturation_threshold = 40.0
soft_saturation_threshold = 20.0
```

Create `models/settings/fields.yaml` with two tab groups:

**Tab: Color Extraction**
- `crop_size_pixels`: type `dropdown`, label "Center Crop Size (px)", options as key-value pairs: 50, 75, 100, 125, 150, 175, 200. Comment: "Size of the center square crop in pixels"
- `blur_passes`: type `number`, label "Gaussian Blur Passes", comment "Number of blur filter passes (higher = smoother, 1-50)", span left
- `palette_size`: type `number`, label "Palette Colors", comment "Number of colors to extract for palette (1-10)", span right
- `palette_thumbnail_size_pixels`: type `number`, label "Palette Thumbnail Size (px)", comment "Thumbnail edge size for palette sampling (5-50)", span left
- `download_timeout_seconds`: type `number`, label "Download Timeout (seconds)", comment "HTTP request timeout for image downloads (1-60)", span right

**Tab: Classification Thresholds**
- Section "Achromatic Detection" with `achromatic_saturation_threshold` (span left), `white_lightness_threshold` (span right), `black_lightness_threshold` (span left)
- Section "Depth Thresholds" with `very_light_depth_threshold`, `light_depth_threshold`, `medium_depth_threshold`, `dark_depth_threshold` — all as number type, two per row
- Section "Saturation Thresholds" with `neon_saturation_threshold`, `vivid_saturation_threshold`, `medium_saturation_threshold`, `soft_saturation_threshold` — all as number type, two per row

All threshold fields should have descriptive comments explaining what values above/below the threshold mean.

Follow Hungarian notation in model code: `$arDefaultValues`, `$sSettingsCode`, etc.
Follow PSR-2, 4-space indentation.
  </action>
  <verify>
    <automated>php -l plugins/logingrupa/colorclassifier/models/Settings.php && php -r "require 'vendor/autoload.php'; \Illuminate\Support\Facades\Facade::clearResolvedInstances(); echo 'Syntax OK';"</automated>
  </verify>
  <done>
    Settings.php extends SettingModel with correct settingsCode, all 16 fields defined in fields.yaml with tabs,
    initSettingsData() sets all defaults matching current hardcoded values
  </done>
</task>

<task type="auto">
  <name>Task 2: Add popup partial, toolbar button, and controller AJAX handlers</name>
  <files>
    plugins/logingrupa/colorclassifier/controllers/colorentries/_settings_popup.htm,
    plugins/logingrupa/colorclassifier/controllers/colorentries/_list_toolbar.htm,
    plugins/logingrupa/colorclassifier/controllers/ColorEntries.php
  </files>
  <action>
**A) Create `_settings_popup.htm` partial:**

Structure follows October CMS popup pattern:
```
<div class="modal-header">
    <h4 class="modal-title">Color Classifier Settings</h4>
    <button type="button" class="btn-close" data-dismiss="modal"></button>
</div>
<div class="modal-body">
    <?= $settingsFormWidget->render() ?>
</div>
<div class="modal-footer">
    <button
        type="submit"
        class="btn btn-primary"
        data-request="onSaveSettings"
        data-request-close-popup
        data-stripe-load-indicator>
        Save Settings
    </button>
    <button
        type="button"
        class="btn btn-default"
        data-dismiss="modal">
        Cancel
    </button>
</div>
```

The form widget variable `$settingsFormWidget` is passed from the controller handler.

**B) Update `_list_toolbar.htm`:**

Add a Settings button AFTER the Export CSV link, visually separated. Use `oc-icon-cog` icon class.
The button must use `data-control="popup"` and `data-handler="onLoadSettingsPopup"` attributes.
Use `data-size="large"` for the popup since there are many fields with tabs.

```html
<button
    class="btn btn-default oc-icon-cog"
    data-control="popup"
    data-handler="onLoadSettingsPopup"
    data-size="large">
    Settings
</button>
```

**C) Update `ColorEntries.php` controller:**

Add two AJAX handlers:

1. `onLoadSettingsPopup()`:
   - Create a `Backend\Widgets\Form` instance with:
     - `model` = `Settings::instance()`
     - `fields` config from `Settings::instance()->getFieldConfig()`
     - `arrayName` = `Settings` (form field name prefix)
   - Call `$obFormWidget->init()` on the form widget
   - Return `$this->makePartial('settings_popup', ['settingsFormWidget' => $obFormWidget])`

2. `onSaveSettings()`:
   - Get the settings instance: `$obSettings = Settings::instance()`
   - Fill from post data: `$obSettings->fill(post('Settings', []))`
   - Save: `$obSettings->save()`
   - Flash success message: "Settings saved successfully."
   - Return (no list refresh needed — settings don't affect list display)

Add `use Logingrupa\ColorClassifier\Models\Settings;` import at the top.

Follow Hungarian notation: `$obFormWidget`, `$obSettings`, `$arSettingsData`.
  </action>
  <verify>
    <automated>php -l plugins/logingrupa/colorclassifier/controllers/ColorEntries.php && php -l plugins/logingrupa/colorclassifier/models/Settings.php</automated>
  </verify>
  <done>
    Settings button appears on toolbar, clicking it opens a large popup with tabbed form fields,
    Save persists values to system_settings table, Cancel closes without saving
  </done>
</task>

<task type="auto">
  <name>Task 3: Wire ColorExtractor and ColorClassifier to read from Settings model</name>
  <files>
    plugins/logingrupa/colorclassifier/classes/ColorExtractor.php,
    plugins/logingrupa/colorclassifier/classes/ColorClassifier.php
  </files>
  <action>
**A) Update `ColorExtractor.php`:**

Keep the existing `private const` values as fallback defaults (rename them to `FALLBACK_*` prefix for clarity). Add a private static helper method `getSettingValue(string $sKey, mixed $fallbackDefault): mixed` that:
1. Tries `Settings::get($sKey, $fallbackDefault)` wrapped in a try/catch
2. On any exception (e.g., no database connection during tests), returns `$fallbackDefault`

This ensures backward compatibility: when the database isn't available (standalone tests), it falls back to the constants.

Update methods to use settings:
- `downloadImage()`: timeout from `Settings::get('download_timeout_seconds', self::FALLBACK_DOWNLOAD_TIMEOUT_SECONDS)`
- `cropCenterSquare()`: change default parameter value approach — since method has a `$squareSize` parameter, the caller (`processImage`) should pass the setting value
- `applyGaussianBlur()`: same approach — caller passes setting value
- `extractColorPalette()`: same approach — caller passes setting value
- `processImage()`: This is the orchestrator. Read settings here and pass to individual methods:
  ```php
  $iCropSize = (int) self::getSettingValue('crop_size_pixels', self::FALLBACK_CROP_SIZE_PIXELS);
  $iBlurPasses = (int) self::getSettingValue('blur_passes', self::FALLBACK_BLUR_PASSES);
  $iPaletteSize = (int) self::getSettingValue('palette_size', self::FALLBACK_PALETTE_SIZE);
  ```
  Then pass these to `cropCenterSquare($downloadedImage, $iCropSize)`, `applyGaussianBlur($croppedImage, $iBlurPasses)`, `extractColorPalette($blurredImage, $iPaletteSize)`.

Add `use Logingrupa\ColorClassifier\Models\Settings;` import.

**B) Update `ColorClassifier.php`:**

Same approach: keep constants as `FALLBACK_*` and add private static `getSettingValue()` helper (or extract to a shared trait if DRY is preferred — but since there are only 2 classes, inline is acceptable to avoid over-abstraction).

Update each threshold usage in `classify()`, `classifyAchromaticFamily()`, `determineDepth()`, and `determineSaturation()`:

In `classify()` — read all thresholds once and pass to private methods. Since multiple methods use these values, refactor to read them at the `classify()` entry point and pass as parameters:

```php
public static function classify(int $red, int $green, int $blue): array
{
    // ... existing HSL/OKLCH conversion ...

    $arThresholds = self::loadThresholds();

    return [
        'family'     => self::determineColorFamily($hue, $saturation, $lightness, $arThresholds),
        'undertone'  => self::determineUndertone($hue, $saturation),
        'depth'      => self::determineDepth($perceptualLightness, $arThresholds),
        'saturation' => self::determineSaturation($saturation, $arThresholds),
        // ... rest unchanged ...
    ];
}

private static function loadThresholds(): array
{
    return [
        'achromatic_saturation' => (float) self::getSettingValue('achromatic_saturation_threshold', self::FALLBACK_ACHROMATIC_SATURATION_THRESHOLD),
        'white_lightness'       => (float) self::getSettingValue('white_lightness_threshold', self::FALLBACK_WHITE_LIGHTNESS_THRESHOLD),
        'black_lightness'       => (float) self::getSettingValue('black_lightness_threshold', self::FALLBACK_BLACK_LIGHTNESS_THRESHOLD),
        'very_light_depth'      => (float) self::getSettingValue('very_light_depth_threshold', self::FALLBACK_VERY_LIGHT_DEPTH_THRESHOLD),
        'light_depth'           => (float) self::getSettingValue('light_depth_threshold', self::FALLBACK_LIGHT_DEPTH_THRESHOLD),
        'medium_depth'          => (float) self::getSettingValue('medium_depth_threshold', self::FALLBACK_MEDIUM_DEPTH_THRESHOLD),
        'dark_depth'            => (float) self::getSettingValue('dark_depth_threshold', self::FALLBACK_DARK_DEPTH_THRESHOLD),
        'neon_saturation'       => (float) self::getSettingValue('neon_saturation_threshold', self::FALLBACK_NEON_SATURATION_THRESHOLD),
        'vivid_saturation'      => (float) self::getSettingValue('vivid_saturation_threshold', self::FALLBACK_VIVID_SATURATION_THRESHOLD),
        'medium_saturation'     => (float) self::getSettingValue('medium_saturation_threshold', self::FALLBACK_MEDIUM_SATURATION_THRESHOLD),
        'soft_saturation'       => (float) self::getSettingValue('soft_saturation_threshold', self::FALLBACK_SOFT_SATURATION_THRESHOLD),
    ];
}
```

Update `determineColorFamily()`, `classifyAchromaticFamily()`, `determineDepth()`, and `determineSaturation()` to accept `$arThresholds` parameter and use it instead of `self::CONSTANT`.

The public static methods `determineColorFamily`, `determineUndertone`, `determineDepth`, `determineSaturation` should accept the thresholds array as a parameter since they are called from `classify()`. Keep `calculateConfidence` unchanged (it does not use configurable thresholds).

Add `use Logingrupa\ColorClassifier\Models\Settings;` import.

**Important:** Existing tests must still pass. The `getSettingValue()` helper's try/catch ensures tests running without a database get the fallback constant values.
  </action>
  <verify>
    <automated>php -l plugins/logingrupa/colorclassifier/classes/ColorExtractor.php && php -l plugins/logingrupa/colorclassifier/classes/ColorClassifier.php && cd C:/laragon/www/nailolab && php vendor/bin/phpunit --configuration plugins/logingrupa/colorclassifier/phpunit.xml</automated>
  </verify>
  <done>
    Both classes read from Settings model with fallback to constant defaults.
    Existing tests pass unchanged (fallback path works without database).
    processImage() reads crop_size, blur_passes, palette_size from settings.
    classify() reads all 11 thresholds from settings via loadThresholds().
  </done>
</task>

</tasks>

<verification>
1. `php -l` passes for all modified PHP files (no syntax errors)
2. Existing plugin tests pass: `php vendor/bin/phpunit --configuration plugins/logingrupa/colorclassifier/phpunit.xml`
3. Visit `/back/logingrupa/colorclassifier/colorentries` — Settings button visible on toolbar
4. Click Settings — popup opens with two tabs (Color Extraction, Classification Thresholds)
5. All fields show default values matching the previously hardcoded constants
6. Change a value, click Save — flash message appears
7. Reopen Settings — changed value persists
8. Process an entry — uses new settings values
</verification>

<success_criteria>
- Settings button on toolbar opens a large modal popup with tabbed form
- All 16 parameters are configurable via the popup form
- Settings persist in `system_settings` table under code `logingrupa_colorclassifier_settings`
- Default values exactly match the original hardcoded constants (backward-compatible)
- ColorExtractor and ColorClassifier dynamically read from Settings model
- Existing tests still pass (graceful fallback when database unavailable)
</success_criteria>

<output>
After completion, create `.planning/quick/1-implement-backend-settings-modal-for-col/1-SUMMARY.md`
</output>
