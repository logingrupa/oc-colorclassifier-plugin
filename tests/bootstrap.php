<?php

/**
 * Bootstrap for standalone ColorClassifier plugin tests.
 *
 * Requires all plugin class files in dependency order so that PHPUnit
 * can run the tests without OctoberCMS framework bootstrapping.
 *
 * Load order respects class dependencies:
 *   1. SettingModelStub — provides System\Models\SettingModel without OctoberCMS
 *   2. Settings model  — extends SettingModelStub; used by ColorExtractor and ColorClassifier
 *   3. Taxonomy        — no dependencies
 *   4. ColorConverter  — no dependencies
 *   5. ColorNamer      — depends on ColorConverter (hexToRgbArray)
 *   6. ColorExtractor  — reads from Settings with fallback constants
 *   7. ColorClassifier — reads from Settings with fallback constants
 *   8. EffectiveColorResolver - depends on ColorConverter
 *   9. OverrideNormalizer, SheetExportBuilder, FamilyExportBuilder - no dependencies
 *  10. ImageHexSampler (ColorConverter), FamilyBoardPayloadBuilder (resolver, SheetExportBuilder, Taxonomy)
 */

$pluginRootDirectory = dirname(__DIR__);

require_once $pluginRootDirectory . '/tests/stubs/SettingModelStub.php';
require_once $pluginRootDirectory . '/models/Settings.php';
require_once $pluginRootDirectory . '/classes/Taxonomy.php';
require_once $pluginRootDirectory . '/classes/ColorConverter.php';
require_once $pluginRootDirectory . '/classes/EffectiveColorResolver.php';
require_once $pluginRootDirectory . '/classes/OverrideNormalizer.php';
require_once $pluginRootDirectory . '/classes/ColorNamer.php';
require_once $pluginRootDirectory . '/classes/ColorExtractor.php';
require_once $pluginRootDirectory . '/classes/ColorClassifier.php';
require_once $pluginRootDirectory . '/classes/NailoLabTransforms.php';
require_once $pluginRootDirectory . '/classes/SheetExportBuilder.php';
require_once $pluginRootDirectory . '/classes/FamilyExportBuilder.php';
require_once $pluginRootDirectory . '/classes/ImageHexSampler.php';
require_once $pluginRootDirectory . '/classes/FamilyBoardPayloadBuilder.php';
require_once $pluginRootDirectory . '/classes/EtagRevalidation.php';
require_once $pluginRootDirectory . '/classes/ColorLabPayloadCache.php';
