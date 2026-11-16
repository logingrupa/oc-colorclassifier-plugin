<?php

namespace Logingrupa\ColorClassifier\Controllers\Concerns;

use Logingrupa\ColorClassifier\Classes\BatchProcessor;
use Logingrupa\ColorClassifier\Models\ColorEntry;

/**
 * BatchProcessingHandlers - the Process All / Process New popup handlers.
 *
 * Shared by every backend controller that opens the batch popup, so the
 * popup, the chunk loop and the locked-row count behave the same everywhere.
 *
 * @package Logingrupa\ColorClassifier\Controllers\Concerns
 */
trait BatchProcessingHandlers
{
    /**
     * AJAX handler - prepare a chunked batch run and return the total offer count.
     *
     * Called once before the frontend begins looping onProcessBatch requests.
     * Caches the offer list so subsequent chunks read from cache, not the XML feed.
     *
     * @return array{total: int}
     */
    public function onStartBatch(): array
    {
        $sMode          = post('mode', 'all');
        $batchProcessor = new BatchProcessor();

        return $batchProcessor->prepareBatch($sMode);
    }

    /**
     * AJAX handler - process a single chunk of offers.
     *
     * The frontend calls this repeatedly with incrementing offsets until
     * the 'done' flag is true. Each request processes a small batch to
     * stay within serverless/hosting time limits.
     *
     * @return array{processed: int, skipped: int, failed: int, total: int, done: bool}
     */
    public function onProcessBatch(): array
    {
        $iOffset    = (int) post('offset', 0);
        $iBatchSize = (int) post('batch_size', 5);
        $sMode      = post('mode', 'all');

        $batchProcessor = new BatchProcessor();

        return $batchProcessor->processChunk($iOffset, $iBatchSize, $sMode);
    }

    /**
     * AJAX handler - open the batch processing popup.
     *
     * Prepares the offer list (clearing cache when mode is 'all') and
     * returns the popup partial with the total offer count and the number
     * of locked offers Process All will skip.
     *
     * @return string Rendered popup partial HTML.
     */
    public function onLoadBatchPopup(): string
    {
        $sMode          = post('mode', 'all');
        $batchProcessor = new BatchProcessor();
        $arResult       = $batchProcessor->prepareBatch($sMode);

        return $this->makePartial('$/logingrupa/colorclassifier/partials/_batch_popup.htm', [
            'mode'         => $sMode,
            'totalOffers'  => $arResult['total'],
            'lockedOffers' => ColorEntry::locked()->count(),
        ]);
    }
}
