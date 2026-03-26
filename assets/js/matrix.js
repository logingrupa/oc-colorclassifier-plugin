/**
 * matrix.js — Backend JavaScript for the Color Classifier plugin.
 *
 * Drives batch processing inside an OctoberCMS popup modal.
 * Three states: config (set batch size) → processing (live stats + ETA) → complete.
 * Prevents accidental close during processing.
 *
 * @module ColorClassifierMatrix
 */

'use strict'

;(function ($) {

    /** @type {boolean} Whether a batch is currently running. */
    var isProcessing = false

    /** @type {boolean} Whether the user requested a stop. */
    var cancelRequested = false

    /**
     * Read and clamp the batch size input value.
     *
     * @returns {number} Batch size between 1 and 50.
     */
    function getBatchSize() {
        var input = document.getElementById('batchSizeInput')
        if (!input) {
            return 5
        }
        var value = parseInt(input.value, 10)
        if (isNaN(value) || value < 1) {
            return 1
        }
        return value > 50 ? 50 : value
    }

    /**
     * Format milliseconds into a human-readable ETA string.
     *
     * @param {number} ms - Estimated milliseconds remaining.
     * @returns {string} Formatted time string.
     */
    function formatEta(ms) {
        if (!isFinite(ms) || ms < 0) {
            return '\u2014'
        }
        var totalSeconds = Math.ceil(ms / 1000)
        if (totalSeconds < 60) {
            return totalSeconds + 's'
        }
        var minutes = Math.floor(totalSeconds / 60)
        var seconds = totalSeconds % 60
        return minutes + 'm ' + seconds + 's'
    }

    /**
     * Transition the popup from config state to processing state.
     *
     * @returns {void}
     */
    function showProcessingState() {
        var configSection = document.getElementById('batchConfigSection')
        var progressSection = document.getElementById('batchProgressSection')
        var startBtn = document.getElementById('batchStartBtn')
        var stopBtn = document.getElementById('batchStopBtn')
        var cancelBtn = document.getElementById('batchCancelBtn')

        if (configSection) {
            configSection.style.display = 'none'
        }
        if (progressSection) {
            progressSection.style.display = 'block'
        }
        if (startBtn) {
            startBtn.style.display = 'none'
        }
        if (stopBtn) {
            stopBtn.style.display = 'inline-block'
        }
        if (cancelBtn) {
            cancelBtn.style.display = 'none'
        }
    }

    /**
     * Transition the popup from processing state to complete state.
     *
     * @param {string} summaryText - Completion summary message.
     * @returns {void}
     */
    function showCompleteState(summaryText) {
        var progressSection = document.getElementById('batchProgressSection')
        var completeSection = document.getElementById('batchCompleteSection')
        var completeText = document.getElementById('batchCompleteText')
        var stopBtn = document.getElementById('batchStopBtn')
        var closeRefreshBtn = document.getElementById('batchCloseRefreshBtn')

        if (progressSection) {
            progressSection.style.display = 'none'
        }
        if (completeSection) {
            completeSection.style.display = 'block'
        }
        if (completeText) {
            completeText.textContent = summaryText
        }
        if (stopBtn) {
            stopBtn.style.display = 'none'
        }
        if (closeRefreshBtn) {
            closeRefreshBtn.style.display = 'inline-block'
        }
    }

    /**
     * Update all progress stats in the popup.
     *
     * @param {object} stats
     * @param {number} stats.offset - Current offset into the offer list.
     * @param {number} stats.total - Total offer count.
     * @param {number} stats.processed - Total processed so far.
     * @param {number} stats.skipped - Total skipped so far.
     * @param {number} stats.failed - Total failed so far.
     * @param {number} stats.etaMs - Estimated milliseconds remaining.
     * @param {string} stats.statusText - Status line text.
     * @returns {void}
     */
    function updateProgress(stats) {
        var percent = stats.total > 0
            ? Math.min(Math.round((stats.offset / stats.total) * 100), 99)
            : 0

        var progressBar = document.getElementById('batchProgressBar')
        var statProgress = document.getElementById('batchStatProgress')
        var statProcessed = document.getElementById('batchStatProcessed')
        var statSkipped = document.getElementById('batchStatSkipped')
        var statFailed = document.getElementById('batchStatFailed')
        var statEta = document.getElementById('batchStatEta')
        var statusText = document.getElementById('batchStatusText')

        if (progressBar) {
            progressBar.style.width = percent + '%'
        }
        if (statProgress) {
            statProgress.textContent = stats.offset + ' / ' + stats.total
        }
        if (statProcessed) {
            statProcessed.textContent = stats.processed
        }
        if (statSkipped) {
            statSkipped.textContent = stats.skipped
        }
        if (statFailed) {
            statFailed.textContent = stats.failed
        }
        if (statEta) {
            statEta.textContent = formatEta(stats.etaMs)
        }
        if (statusText) {
            statusText.textContent = stats.statusText || ''
        }
    }

    /**
     * Run the chunked batch processing loop inside the popup.
     *
     * @param {string} mode - 'all' or 'new'.
     * @param {number} total - Total offer count.
     * @param {number} batchSize - Offers per chunk.
     * @param {jQuery} $trigger - Element to use as AJAX request context.
     * @returns {void}
     */
    function runBatchLoop(mode, total, batchSize, $trigger) {
        var offset = 0
        var totalProcessed = 0
        var totalSkipped = 0
        var totalFailed = 0
        var startTime = Date.now()
        var chunksCompleted = 0

        /** @type {number} Estimated remaining ms — starts at 5 min, refined by real data. */
        var etaRemainingMs = 5 * 60 * 1000
        /** @type {number|null} Interval ID for the 1-second countdown tick. */
        var countdownInterval = null

        isProcessing = true
        cancelRequested = false
        showProcessingState()

        /** Tick the ETA countdown by 1 second and update the display. */
        function tickCountdown() {
            etaRemainingMs = Math.max(etaRemainingMs - 1000, 0)
            var statEta = document.getElementById('batchStatEta')
            if (statEta) {
                statEta.textContent = formatEta(etaRemainingMs)
            }
        }

        countdownInterval = setInterval(tickCountdown, 1000)

        function processNextChunk() {
            if (cancelRequested) {
                clearInterval(countdownInterval)
                var remaining = total - offset
                var statEtaEl = document.getElementById('batchStatEta')
                if (statEtaEl) {
                    statEtaEl.textContent = remaining + ' left'
                }
                var statEtaLabel = statEtaEl
                    ? statEtaEl.parentElement.querySelector('.batch-popup-stat-label')
                    : null
                if (statEtaLabel) {
                    statEtaLabel.textContent = 'Unprocessed'
                }
                finishProcessing('Stopped. Processed ' + totalProcessed
                    + ', skipped ' + totalSkipped
                    + ', failed ' + totalFailed
                    + '. ' + remaining + ' offers left unprocessed.')
                return
            }

            var elapsedMs = Date.now() - startTime
            if (chunksCompleted > 0) {
                var msPerChunk = elapsedMs / chunksCompleted
                var chunksRemaining = Math.ceil((total - offset) / batchSize)
                etaRemainingMs = msPerChunk * chunksRemaining
            }

            updateProgress({
                offset: offset,
                total: total,
                processed: totalProcessed,
                skipped: totalSkipped,
                failed: totalFailed,
                etaMs: etaRemainingMs,
                statusText: 'Batch ' + (chunksCompleted + 1) + ' of '
                    + Math.ceil(total / batchSize) + '\u2026'
            })

            $trigger.request('onProcessBatch', {
                data: {
                    mode: mode,
                    offset: offset,
                    batch_size: batchSize
                },
                success: function (result) {
                    totalProcessed += result.processed
                    totalSkipped += result.skipped
                    totalFailed += result.failed
                    offset += batchSize
                    chunksCompleted++

                    if (result.done || offset >= total) {
                        clearInterval(countdownInterval)
                        updateProgress({
                            offset: total,
                            total: total,
                            processed: totalProcessed,
                            skipped: totalSkipped,
                            failed: totalFailed,
                            etaMs: 0,
                            statusText: 'Complete!'
                        })
                        var progressBar = document.getElementById('batchProgressBar')
                        if (progressBar) {
                            progressBar.style.width = '100%'
                        }
                        finishProcessing('Processed ' + totalProcessed
                            + ', skipped ' + totalSkipped
                            + ', failed ' + totalFailed
                            + ' of ' + total + ' offers.')
                    } else {
                        processNextChunk()
                    }
                },
                error: function () {
                    clearInterval(countdownInterval)
                    finishProcessing('Error at offset ' + offset
                        + '. ' + totalProcessed + ' offers processed before failure.')
                }
            })
        }

        processNextChunk()
    }

    /**
     * End the processing loop and show the complete state.
     *
     * @param {string} summary - Summary text to display.
     * @returns {void}
     */
    function finishProcessing(summary) {
        isProcessing = false
        cancelRequested = false
        showCompleteState(summary)
    }

    // ── Event Delegation (handles dynamically loaded popup content) ──

    /** Start Processing button inside popup. */
    $(document).on('click', '#batchStartBtn', function () {
        var modeInput = document.getElementById('batchMode')
        var totalInput = document.getElementById('batchTotal')
        var mode = modeInput ? modeInput.value : 'all'
        var total = totalInput ? parseInt(totalInput.value, 10) : 0
        var batchSize = getBatchSize()

        if (total === 0) {
            return
        }

        runBatchLoop(mode, total, batchSize, $(this))
    })

    /** Stop Processing button — sets cancel flag, waits for current batch. */
    $(document).on('click', '#batchStopBtn', function () {
        cancelRequested = true
        $(this).prop('disabled', true).text('Stopping\u2026')
    })

    /** Close & Refresh button — reloads the page. */
    $(document).on('click', '#batchCloseRefreshBtn', function () {
        window.location.reload()
    })

    /** Prevent modal close while processing. */
    $(document).on('hide.bs.modal', '.control-popup', function (event) {
        if (isProcessing) {
            event.preventDefault()
            $.oc.flashMsg({
                text: 'Processing in progress. Use "Stop Processing" first.',
                class: 'warning'
            })
        }
    })

})(jQuery)
