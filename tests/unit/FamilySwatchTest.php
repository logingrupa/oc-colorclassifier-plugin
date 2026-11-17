<?php

use Logingrupa\ColorClassifier\Classes\FamilySwatch;
use PHPUnit\Framework\TestCase;

/**
 * GRAD-02: the swatch background the Families form preview and the Family
 * board draw: the gradient as CSS when it is valid, the hex otherwise.
 */
class FamilySwatchTest extends TestCase
{
    public function test_each_type_becomes_its_css_gradient(): void
    {
        $obSwatch = new FamilySwatch();
        $arStops = ['#FF00AA', '#00e5ff', '#ffd400'];

        $this->assertSame('linear-gradient(135deg, #ff00aa, #00e5ff, #ffd400)', $obSwatch->background('#9e9e9e', $arStops, 'linear', 135));
        $this->assertSame('radial-gradient(circle, #ff00aa, #00e5ff, #ffd400)', $obSwatch->background('#9e9e9e', $arStops, 'radial', 135));
        $this->assertSame('conic-gradient(from 20deg, #ff00aa, #00e5ff, #ffd400)', $obSwatch->background('#9e9e9e', $arStops, 'conic', 20));
    }

    public function test_no_gradient_draws_the_hex(): void
    {
        $this->assertSame('#9e9e9e', (new FamilySwatch())->background('#9e9e9e', null, 'linear', 90));
        $this->assertSame('#9e9e9e', (new FamilySwatch())->background('#9e9e9e', [], 'linear', 90));
    }

    public function test_a_bad_gradient_draws_the_hex(): void
    {
        $obSwatch = new FamilySwatch();

        $this->assertSame('#9e9e9e', $obSwatch->background('#9e9e9e', ['#ff00aa'], 'linear', 90));
        $this->assertSame('#9e9e9e', $obSwatch->background('#9e9e9e', ['#ff00aa', '#00e5ff'], 'spiral', 90));
        $this->assertSame('#9e9e9e', $obSwatch->background('#9e9e9e', ['#ff00aa', '#00e5ff'], 'conic', 400));
    }
}
