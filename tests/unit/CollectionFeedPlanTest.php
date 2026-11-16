<?php

use Logingrupa\ColorClassifier\Classes\CollectionFeedPlan;
use PHPUnit\Framework\TestCase;

/**
 * SYNC-01: the summary line carries the six plan counts and their sum.
 */
class CollectionFeedPlanTest extends TestCase
{
    public function test_describe_prints_the_six_counts_and_the_write_total(): void
    {
        $obPlan = new CollectionFeedPlan(
            null,
            [['family' => 'DISCO'], ['family' => 'NEON']],
            [['value_id' => 'a', 'feed_value_name' => 'DISCO']],
            [['value_id' => 'a', 'offer_id' => 'o1', 'shade_code' => null, 'row_id' => null]],
            [['row_id' => 4]],
            [['row_id' => 5], ['row_id' => 6]],
            [['row_id' => 7, 'shade_code' => 'DISCO 1']]
        );

        $this->assertSame('Planned: create 2, rename 1, set 1, unset 1, delete 2, shade 1, writes 8', $obPlan->describe('Planned'));
        $this->assertSame('Applied: create 2, rename 1, set 1, unset 1, delete 2, shade 1, writes 8', $obPlan->describe('Applied'));
    }

    public function test_describe_adds_a_second_line_listing_the_skipped_values(): void
    {
        $obPlan = new CollectionFeedPlan(null, [], [], [], [], [], [], [
            ['value_id' => 'a', 'name' => 'Rudens 2.0', 'reason' => 'cannot be a family key'],
            ['value_id' => 'b', 'name' => 'red', 'reason' => 'collides with an existing family'],
        ]);

        $arLines = explode("\n", $obPlan->describe('Planned'));

        $this->assertSame('Planned: create 0, rename 0, set 0, unset 0, delete 0, shade 0, writes 0', $arLines[0]);
        $this->assertSame('Skipped 2: Rudens 2.0 (cannot be a family key), red (collides with an existing family)', $arLines[1]);
        $this->assertCount(2, $arLines);
    }

    public function test_describe_of_an_empty_plan_writes_zero(): void
    {
        $this->assertSame('Applied: create 0, rename 0, set 0, unset 0, delete 0, shade 0, writes 0', (new CollectionFeedPlan())->describe('Applied'));
    }
}
