<?php

namespace Logingrupa\ColorClassifier\Console;

use Illuminate\Console\Command;
use Logingrupa\ColorClassifier\Classes\BatchProcessor;
use Logingrupa\ColorClassifier\Classes\NewOfferQueue;
use Logingrupa\ColorClassifier\Classes\ScheduledJobMemoryLimit;
use Logingrupa\ColorClassifier\Models\Settings;

/**
 * ProcessNewOffers gives a color entry to offers in the color scope that have an image and no entry yet.
 *
 * Scheduled hourly, with its own memory limit (ScheduledJobMemoryLimit). Does
 * nothing while the Automation setting is off. Prints one summary line plus one
 * line per offer whose image failed and one per offer it no longer retries; failed
 * images exit 0. A catalog that does not load throws, so the run exits non-zero.
 */
class ProcessNewOffers extends Command
{
    /** @var string signature */
    protected $signature = 'colorclassifier:process-new-offers {--limit=100 : Most offers to process in one run}';

    /** @var string description */
    protected $description = 'Assign colors to offers in the color scope groups that have an image and no color entry yet.';

    /**
     * @return int
     */
    public function handle(): int
    {
        ScheduledJobMemoryLimit::apply();

        if (!Settings::get('auto_process_new_offers', true)) {
            $this->line('Off: "Assign colors to new offers automatically" is switched off in the Color Classifier settings.');

            return self::SUCCESS;
        }

        $iLimit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($iLimit === false) {
            $this->error('--limit must be a whole number of 1 or more.');

            return self::FAILURE;
        }

        $arResult = (new BatchProcessor())->processNewOffers($iLimit);

        $this->line(sprintf(
            'Processed %d, failed %d, left %d of %d new offers.',
            $arResult['processed'],
            $arResult['failed'],
            $arResult['left'],
            $arResult['total']
        ));

        foreach ($arResult['failed_offers'] as $arFailedOffer) {
            $this->line('Failed: ' . $arFailedOffer['offer_id'] . ' ' . $arFailedOffer['name']);
        }

        foreach ($arResult['retired_offers'] as $arRetiredOffer) {
            $this->line(sprintf(
                'Not retried, failed %d times: %s %s',
                NewOfferQueue::MAX_FAILED_ATTEMPTS,
                $arRetiredOffer['offer_id'],
                $arRetiredOffer['name']
            ));
        }

        return self::SUCCESS;
    }
}
