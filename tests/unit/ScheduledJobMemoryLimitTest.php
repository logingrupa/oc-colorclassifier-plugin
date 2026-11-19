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

    public function test_apply_sets_the_memory_limit_to_128m(): void
    {
        ScheduledJobMemoryLimit::apply();

        $this->assertSame('128M', ini_get('memory_limit'));
    }
}
