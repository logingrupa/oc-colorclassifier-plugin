<?php

namespace Logingrupa\ColorClassifier\Console;

use Illuminate\Console\Command;
use Logingrupa\ColorClassifier\Classes\CollectionFeedSync;

/**
 * SyncCollections mirrors the 1C Kolekcija tags into collection families and memberships.
 *
 * Scheduled hourly. Prints one summary line, or the abort reason with exit code 1.
 */
class SyncCollections extends Command
{
    /** @var string signature */
    protected $signature = 'colorclassifier:sync-collections {--dry-run : Plan only, write nothing}';

    /** @var string description */
    protected $description = 'Mirror the 1C collection tags into collection families and memberships.';

    /**
     * @return int
     */
    public function handle(): int
    {
        $blDryRun = (bool) $this->option('dry-run');
        $obPlan = (new CollectionFeedSync())->run($blDryRun);

        if ($obPlan->isAborted()) {
            $this->line('Aborted: ' . $obPlan->sAbortReason);

            return self::FAILURE;
        }

        $this->line($obPlan->describe($blDryRun ? 'Planned' : 'Applied'));

        return self::SUCCESS;
    }
}
