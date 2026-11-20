<?php

declare(strict_types=1);

namespace Logingrupa\ColorClassifier\Classes;

/**
 * ScheduledJobMemoryLimit - the PHP memory limit of the scheduled color jobs.
 *
 * The scheduled color jobs run in the same 256 MB container as the site, so their
 * own limit sits below it and a runaway job fails alone with a PHP fatal. A limit
 * that is already lower is kept.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
final class ScheduledJobMemoryLimit
{
    /** @var string memory_limit of a scheduled color job. */
    public const MEMORY_LIMIT = '128M';

    /**
     * @return void
     *
     * @throws \RuntimeException When PHP refuses the limit.
     */
    public static function apply(): void
    {
        $iCurrentLimitBytes = ini_parse_quantity((string) ini_get('memory_limit'));

        if ($iCurrentLimitBytes > 0 && $iCurrentLimitBytes <= ini_parse_quantity(self::MEMORY_LIMIT)) {
            return;
        }

        if (ini_set('memory_limit', self::MEMORY_LIMIT) === false) {
            throw new \RuntimeException('Could not set memory_limit to ' . self::MEMORY_LIMIT);
        }
    }
}
