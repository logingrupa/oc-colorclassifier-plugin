<?php

use Logingrupa\ColorClassifier\Classes\ScheduledJobMemoryLimit;
use PHPUnit\Framework\TestCase;

/**
 * The scheduled color jobs lower their own memory_limit below the container size.
 */
class ScheduledJobMemoryLimitTest extends TestCase
{
    private string $sPreviousMemoryLimit;

    protected function setUp(): void
    {
        $this->sPreviousMemoryLimit = (string) ini_get('memory_limit');
    }

    protected function tearDown(): void
    {
        ini_set('memory_limit', $this->sPreviousMemoryLimit);
    }

    public function test_apply_lowers_a_higher_limit_to_128m(): void
    {
        ini_set('memory_limit', '256M');

        ScheduledJobMemoryLimit::apply();

        $this->assertSame('128M', ini_get('memory_limit'));
    }

    public function test_apply_lowers_an_unlimited_limit_to_128m(): void
    {
        ini_set('memory_limit', '-1');

        ScheduledJobMemoryLimit::apply();

        $this->assertSame('128M', ini_get('memory_limit'));
    }

    public function test_apply_keeps_a_lower_limit(): void
    {
        ini_set('memory_limit', '96M');

        ScheduledJobMemoryLimit::apply();

        $this->assertSame('96M', ini_get('memory_limit'));
    }
}
