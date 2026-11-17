<?php

use Logingrupa\ColorClassifier\Classes\GradientNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * GRAD-01: a gradient is no stops at all, or 2 to 8 strict hex stops with a
 * type (linear, radial, conic) and a whole angle from 0 to 359.
 */
class GradientNormalizerTest extends TestCase
{
    public function test_null_and_empty_stops_become_null_whatever_the_type_and_angle(): void
    {
        $obNormalizer = new GradientNormalizer();

        $this->assertNull($obNormalizer->normalize(null, 'linear', 90));
        $this->assertNull($obNormalizer->normalize([], 'spiral', 999));
    }

    public function test_stops_are_lowercased_and_returned_with_type_and_angle(): void
    {
        $obNormalizer = new GradientNormalizer();

        $this->assertSame(
            ['type' => 'linear', 'angle' => 90, 'stops' => ['#ff00aa', '#00e5ff']],
            $obNormalizer->normalize(['#FF00AA', '#00e5ff'], 'linear', 90)
        );
        $this->assertSame(
            ['type' => 'conic', 'angle' => 0, 'stops' => ['#111111', '#222222', '#333333', '#444444', '#555555', '#666666', '#777777', '#888888']],
            $obNormalizer->normalize(['#111111', '#222222', '#333333', '#444444', '#555555', '#666666', '#777777', '#888888'], 'conic', 0)
        );
        $this->assertSame(
            ['type' => 'radial', 'angle' => 359, 'stops' => ['#ffffff', '#000000', '#ff0000']],
            $obNormalizer->normalize(['#FFFFFF', '#000000', '#ff0000'], 'radial', 359)
        );
    }

    public function test_a_digit_string_angle_from_a_form_post_becomes_an_int(): void
    {
        $this->assertSame(135, (new GradientNormalizer())->normalize(['#ff00aa', '#00e5ff'], 'linear', '135')['angle']);
    }

    /**
     * @return array<string, array{0: mixed, 1: mixed, 2: mixed}>
     */
    public static function invalidGradientProvider(): array
    {
        $arTwoStops = ['#ff00aa', '#00e5ff'];

        return [
            'one stop'         => [['#ff00aa'], 'linear', 90],
            'nine stops'       => [array_fill(0, 9, '#111111'), 'linear', 90],
            'short hex'        => [['#fff', '#000000'], 'linear', 90],
            'no hash'          => [['ff00aa', '#000000'], 'linear', 90],
            'non string stop'  => [['#ff00aa', 12], 'linear', 90],
            'keyed list'       => [['from' => '#ff00aa', 'to' => '#000000'], 'linear', 90],
            'not an array'     => ['#ff00aa', 'linear', 90],
            'unknown type'     => [$arTwoStops, 'spiral', 90],
            'missing type'     => [$arTwoStops, null, 90],
            'uppercase type'   => [$arTwoStops, 'Linear', 90],
            'angle 360'        => [$arTwoStops, 'linear', 360],
            'negative angle'   => [$arTwoStops, 'linear', -1],
            'missing angle'    => [$arTwoStops, 'conic', null],
            'fraction angle'   => [$arTwoStops, 'linear', 45.5],
            'angle with unit'  => [$arTwoStops, 'linear', '45deg'],
        ];
    }

    #[DataProvider('invalidGradientProvider')]
    public function test_invalid_gradients_are_rejected(mixed $mStops, mixed $mType, mixed $mAngle): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new GradientNormalizer())->normalize($mStops, $mType, $mAngle);
    }
}
