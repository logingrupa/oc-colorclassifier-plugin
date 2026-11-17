<?php

use Logingrupa\ColorClassifier\Classes\GradientNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * GRAD-03: every collection gradient the seed migration writes passes the
 * export rules, so the deploy can never make families.json refuse a row.
 */
class CollectionGradientsDataTest extends TestCase
{
    public function test_every_seeded_gradient_is_valid_and_keyed_by_a_slug(): void
    {
        $arGradients = require dirname(__DIR__, 2) . '/updates/data/collection_gradients.php';
        $obNormalizer = new GradientNormalizer();

        $this->assertNotEmpty($arGradients);
        foreach ($arGradients as $sSlug => $arGradient) {
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $sSlug);
            $this->assertSame(['type', 'angle', 'stops'], array_keys($arGradient), $sSlug);
            $this->assertSame($arGradient, $obNormalizer->normalize($arGradient['stops'], $arGradient['type'], $arGradient['angle']), $sSlug);
        }
    }
}
