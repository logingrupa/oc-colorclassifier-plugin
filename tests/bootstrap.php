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
 *  11. Collections: FamilyKind, GradientNormalizer, FamilySwatch, MembershipStateNormalizer, FamilyMembershipIndex,
 *      CollectionTagExtractor, CollectionFeedPlan, CollectionFeedPlanner, CollectionHandSwitch - no CMS dependencies
 *  12. FamilySwitchWriter - takes an Illuminate database connection
 *  13. NewOfferQueue, ScheduledJobMemoryLimit, OfferLinkRefreshPlanner - no dependencies
 *  14. ColorScope - no dependencies; OfferRowBuilder (NailoLabTransforms), OfferDataProvider (OfferRowBuilder, CollectionTagExtractor), BatchProcessor (OfferDataProvider, NewOfferQueue)
 *  15. ColorLabOfferDirectory (OfferDataProvider), ColorLabMapper (ColorLabOfferDirectory)
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
require_once $pluginRootDirectory . '/classes/FamilyKind.php';
require_once $pluginRootDirectory . '/classes/GradientNormalizer.php';
require_once $pluginRootDirectory . '/classes/FamilySwatch.php';
require_once $pluginRootDirectory . '/classes/MembershipStateNormalizer.php';
require_once $pluginRootDirectory . '/classes/FamilyMembershipIndex.php';
require_once $pluginRootDirectory . '/classes/CollectionTagExtractor.php';
require_once $pluginRootDirectory . '/classes/CollectionFeedPlan.php';
require_once $pluginRootDirectory . '/classes/CollectionFeedPlanner.php';
require_once $pluginRootDirectory . '/classes/CollectionHandSwitch.php';
require_once $pluginRootDirectory . '/classes/FamilySwitchWriter.php';
require_once $pluginRootDirectory . '/classes/NewOfferQueue.php';
require_once $pluginRootDirectory . '/classes/ScheduledJobMemoryLimit.php';
require_once $pluginRootDirectory . '/classes/OfferLinkRefreshPlanner.php';
require_once $pluginRootDirectory . '/classes/ColorScope.php';
require_once $pluginRootDirectory . '/classes/OfferRowBuilder.php';
require_once $pluginRootDirectory . '/classes/OfferDataProvider.php';
require_once $pluginRootDirectory . '/classes/BatchProcessor.php';
require_once $pluginRootDirectory . '/classes/SheetExportBuilder.php';
require_once $pluginRootDirectory . '/classes/FamilyExportBuilder.php';
require_once $pluginRootDirectory . '/classes/ImageHexSampler.php';
require_once $pluginRootDirectory . '/classes/FamilyBoardMembershipMap.php';
require_once $pluginRootDirectory . '/classes/FamilyBoardPayloadBuilder.php';
require_once $pluginRootDirectory . '/classes/EtagRevalidation.php';
require_once $pluginRootDirectory . '/classes/ColorLabPayloadCache.php';
require_once $pluginRootDirectory . '/classes/ColorLabOfferDirectory.php';
require_once $pluginRootDirectory . '/classes/ColorLabMapper.php';
