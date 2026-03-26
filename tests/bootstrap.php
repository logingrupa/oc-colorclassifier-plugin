<?php

/**
 * Bootstrap for standalone ColorClassifier plugin tests.
 *
 * Requires all plugin class files in dependency order so that PHPUnit
 * can run the tests without OctoberCMS framework bootstrapping.
 *
 * Load order respects class dependencies:
 *   1. Taxonomy     — no dependencies
 *   2. ColorConverter — no dependencies
 *   3. ColorNamer   — depends on ColorConverter (hexToRgbArray)
 *   4. ColorExtractor — no class dependencies (uses GD only)
 *   5. ColorClassifier — depends on ColorConverter
 */

$pluginRootDirectory = dirname(__DIR__);

require_once $pluginRootDirectory . '/classes/Taxonomy.php';
require_once $pluginRootDirectory . '/classes/ColorConverter.php';
require_once $pluginRootDirectory . '/classes/ColorNamer.php';
require_once $pluginRootDirectory . '/classes/ColorExtractor.php';
require_once $pluginRootDirectory . '/classes/ColorClassifier.php';
