<?php

use Logingrupa\ColorClassifier\Classes\GradientNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * GRAD-01: a gradient is null, an empty list or 2 or 3 strict hex stops.
 */
class GradientNormalizerTest extends TestCase
{
    public function test_null_and_empty_list_become_null(): void
    {
        $obNormalizer = new GradientNormalizer();

        $this->assertNull($obNormalizer->normalize(null));
        $this->assertNull($obNormalizer->normalize([]));
    }

    public function test_two_and_three_stops_are_accepted_and_lowercased(): void
    {
        $obNormalizer = new GradientNormalizer();

        $this->assertSame(['#ff00aa', '#00e5ff'], $obNormalizer->normalize(['#FF00AA', '#00e5ff']));
        $this->assertSame(['#ff00aa', '#00e5ff', '#ffd400'], $obNormalizer->normalize(['#ff00aa', '#00E5FF', '#FFD400']));
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function invalidGradientProvider(): array
    {
        return [
            'one stop'         => [['#ff00aa']],
            'four stops'       => [['#111111', '#222222', '#333333', '#444444']],
            'short hex'        => [['#fff', '#000000']],
            'no hash'          => [['ff00aa', '#000000']],
            'non string stop'  => [['#ff00aa', 12]],
            'keyed list'       => [['from' => '#ff00aa', 'to' => '#000000']],
            'not an array'     => ['#ff00aa'],
        ];
    }

    #[DataProvider('invalidGradientProvider')]
    public function test_invalid_gradients_are_rejected(mixed $mGradient): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new GradientNormalizer())->normalize($mGradient);
    }
}
