<?php

namespace Logingrupa\ColorClassifier\Classes;

/**
 * OfferDataProvider - Bridge to the theme CommerceMlParser.
 *
 * Loads the CommerceMlParser from the theme directory. The color catalog is the
 * FULL 1C catalog (no NAILO_English gate, every offer with an image), because
 * the nailscosmetics shops read every color from this plugin. The offer
 * directory is the shop view: the offers nailolab lists, under the names it shows.
 * This bridges the plugin to the existing theme data layer without requiring the
 * theme to be loaded as a plugin dependency.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class OfferDataProvider
{
    /** @var string Relative path from project root to the CommerceMlParser class file. */
    private const PARSER_CLASS_PATH = 'themes/logingrupa-nailolab/classes/CommerceMlParser.php';

    /** @var string Relative path from project root to the product config file. */
    private const PRODUCT_CONFIG_PATH = 'themes/logingrupa-nailolab/data/product-config.php';

    /** @var string Fully qualified class name of the CommerceMlParser. */
    private const PARSER_FULLY_QUALIFIED_CLASS = 'Themes\\LogingrupaNailolab\\Classes\\CommerceMlParser';

    /**
     * Return a flat list of every offer of the full 1C catalog that has an image URL.
     *
     * Offers the shop lists carry the shop's names and a detail URL on the shop's
     * product slug; the others keep their 1C names and have no detail URL. While
     * the shop directory cannot be read, every row keeps its 1C names. The rows
     * are not limited to the color scope; the paths that create entries narrow
     * them with ColorScope.
     *
     * @return array<int, array{offer_id: string, product_name: string, variation_name: string, image_url: string, detail_url: string, category_path_ids: array<int, string>}> Empty when the full catalog cannot be read.
     */
    public function getOffersWithImages(): array
    {
        $arFullCatalog = $this->loadFullCatalog();

        if ($arFullCatalog === []) {
            error_log('[ColorClassifier] The full catalog is empty, so no offer rows were built.');
            return [];
        }

        return (new OfferRowBuilder())->build($arFullCatalog, $this->getOfferDirectory());
    }

    /**
     * Return the 1C collection tags of every offer of the full catalog, image-less ones included.
     *
     * Reads the same full catalog as getOffersWithImages, so a warm parser
     * cache means no second feed fetch. A failed load gives an empty catalog,
     * which the sync planner aborts on.
     *
     * @return array{has_collection_key: bool, offer_count: int, offers: array<string, array{shade_code: string|null, collections: array<int, array{value_id: string, value: string}>}>}
     */
    public function getCollectionTags(): array
    {
        return (new CollectionTagExtractor())->extract($this->loadFullCatalog());
    }

    /**
     * @return string The 1C characteristic id the collection tags come from.
     *
     * @throws \RuntimeException When the product config does not name it.
     */
    public function getCollectionCharacteristicId(): string
    {
        $sCharacteristicId = (string) ($this->loadProductConfig()['collection_characteristic_id'] ?? '');

        if ($sCharacteristicId === '') {
            throw new \RuntimeException('product-config.php has no collection_characteristic_id');
        }

        return $sCharacteristicId;
    }

    /**
     * @return array<int, string> The 1C group ids whose offers get new color entries.
     *
     * @throws \RuntimeException When the product config does not list them.
     */
    public function getColorScopeGroupIds(): array
    {
        $arGroupIds = $this->readColorScopeIdList('color_scope_group_ids');

        if ($arGroupIds === []) {
            throw new \RuntimeException('product-config.php has no color_scope_group_ids');
        }

        return $arGroupIds;
    }

    /**
     * @return array<int, string> 1C product ids whose offers get new color entries whatever their group; empty when the config lists none.
     *
     * @throws \RuntimeException When the list holds an invalid value.
     */
    public function getColorScopeProductIds(): array
    {
        return $this->readColorScopeIdList('color_scope_product_ids');
    }

    /**
     * @param string $sConfigKey Product config key of a list of 1C ids.
     *
     * @return array<int, string> The ids, empty when the key is missing.
     *
     * @throws \RuntimeException When the value is not a list of non-empty strings.
     */
    private function readColorScopeIdList(string $sConfigKey): array
    {
        $arIds = $this->loadProductConfig()[$sConfigKey] ?? [];

        if (!is_array($arIds)) {
            throw new \RuntimeException("product-config.php has an invalid {$sConfigKey}");
        }

        foreach ($arIds as $sId) {
            if (!is_string($sId) || $sId === '') {
                throw new \RuntimeException("product-config.php has an invalid value in {$sConfigKey}");
            }
        }

        return array_values($arIds);
    }

    /**
     * Return the offers the shop lists now, keyed by offer id, with the names it
     * shows for each and the slug of its product page. Read from the parser's
     * cached directory, so a public request never loads the full catalog.
     *
     * @return array<string, array{product_name: string, product_slug: string, offer_name: string, variant_title: string}> Empty when the catalog cannot be read.
     */
    public function getOfferDirectory(): array
    {
        return $this->readFromParser('getOfferDirectory', false);
    }

    /**
     * @return array<int, array<string, mixed>> Products of the full catalog, empty when the parser or config is missing or fails.
     */
    protected function loadFullCatalog(): array
    {
        return $this->readFromParser('getCatalog', true);
    }

    /**
     * Call a read method of the theme parser.
     *
     * @param string $sParserMethod  getCatalog or getOfferDirectory.
     * @param bool   $blFullCatalog  True reads the unfiltered catalog under its own cache key, false the shop view.
     *
     * @return array<int|string, mixed> Empty when the parser or config is missing or fails.
     */
    private function readFromParser(string $sParserMethod, bool $blFullCatalog): array
    {
        $parserClassFilePath = base_path(self::PARSER_CLASS_PATH);

        if (!file_exists($parserClassFilePath)) {
            error_log('[ColorClassifier] CommerceMlParser not found at: ' . $parserClassFilePath);
            return [];
        }

        $productConfig = $this->loadProductConfig();

        if ($productConfig === []) {
            return [];
        }

        if (!class_exists(self::PARSER_FULLY_QUALIFIED_CLASS)) {
            require_once $parserClassFilePath;
        }

        $parserClass = self::PARSER_FULLY_QUALIFIED_CLASS;

        if ($blFullCatalog) {
            $productConfig = $parserClass::buildUnfilteredConfig($productConfig, $productConfig['full_catalog_cache_key']);
        }

        try {
            return (new $parserClass($productConfig))->{$sParserMethod}();
        } catch (\Throwable $exception) {
            error_log('[ColorClassifier] CommerceMlParser error: ' . $exception->getMessage());
            return [];
        }
    }

    /**
     * @return array<string, mixed> The theme product config, empty when the file is missing.
     */
    private function loadProductConfig(): array
    {
        $productConfigPath = base_path(self::PRODUCT_CONFIG_PATH);

        if (!file_exists($productConfigPath)) {
            error_log('[ColorClassifier] Product config not found at: ' . $productConfigPath);
            return [];
        }

        return require $productConfigPath;
    }
}
