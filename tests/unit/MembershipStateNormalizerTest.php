<?php

use Logingrupa\ColorClassifier\Classes\MembershipStateNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * MEMB-01: a hand edit equal to 1C is stored as null, a hand-only row is deleted
 * when it goes, and effective membership follows the hand state first.
 */
class MembershipStateNormalizerTest extends TestCase
{
    private MembershipStateNormalizer $obNormalizer;

    protected function setUp(): void
    {
        $this->obNormalizer = new MembershipStateNormalizer();
    }

    public function test_add_on_a_feed_row_stays_null(): void
    {
        $this->assertSame(['hand_state' => null, 'delete' => false], $this->obNormalizer->normalizeAdd(true));
    }

    public function test_add_on_a_non_feed_row_sets_added(): void
    {
        $this->assertSame(['hand_state' => 'added', 'delete' => false], $this->obNormalizer->normalizeAdd(false));
    }

    public function test_remove_on_a_feed_row_sets_removed(): void
    {
        $this->assertSame(['hand_state' => 'removed', 'delete' => false], $this->obNormalizer->normalizeRemove(true));
    }

    public function test_remove_on_a_hand_only_row_deletes_it(): void
    {
        $this->assertSame(['hand_state' => null, 'delete' => true], $this->obNormalizer->normalizeRemove(false));
    }

    public function test_reset_clears_the_hand_state_and_deletes_rows_without_feed(): void
    {
        $this->assertSame(['hand_state' => null, 'delete' => false], $this->obNormalizer->normalizeReset(true));
        $this->assertSame(['hand_state' => null, 'delete' => true], $this->obNormalizer->normalizeReset(false));
    }

    /**
     * @return array<string, array{0: string|null, 1: bool, 2: bool}>
     */
    public static function effectiveMembershipProvider(): array
    {
        return [
            'added without feed'   => ['added', false, true],
            'added with feed'      => ['added', true, true],
            'removed with feed'    => ['removed', true, false],
            'removed without feed' => ['removed', false, false],
            'null with feed'       => [null, true, true],
            'null without feed'    => [null, false, false],
        ];
    }

    #[DataProvider('effectiveMembershipProvider')]
    public function test_effective_membership(?string $sHandState, bool $blInFeed, bool $blExpected): void
    {
        $this->assertSame($blExpected, $this->obNormalizer->isEffective($sHandState, $blInFeed));
    }
}
