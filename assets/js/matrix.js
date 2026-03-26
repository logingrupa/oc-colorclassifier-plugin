/**
 * matrix.js — Backend JavaScript for the Color Classifier plugin.
 *
 * Orchestrates chunked batch processing for Process All and Process New
 * buttons. Each chunk processes a small number of offers per AJAX request
 * to avoid HTTP timeouts on serverless or time-limited hosting.
 *
 * @module ColorClassifierMatrix
 */

'use strict'

/**
 * Initialize the Color Classifier backend UI.
 *
 * Binds click handlers to Process All and Process New buttons that
 * orchestrate sequential chunked AJAX calls with real-time progress.
 * The batch size is read from the #batchSizeInput field before each run.
 *
 * @returns {void}
 */
function initializeColorClassifierMatrix() {
    /** @type {HTMLButtonElement|null} */
    var processAllButton = document.getElementById('btnProcessAll')
    /** @type {HTMLButtonElement|null} */
    var processNewButton = document.getElementById('btnProcessNew')
    /** @type {HTMLInputElement|null} */
    var batchSizeInput = document.getElementById('batchSizeInput')
    /** @type {HTMLElement|null} */
    var progressWrapper = document.getElementById('processingProgress')
    /** @type {HTMLElement|null} */
    var progressBar = progressWrapper
        ? progressWrapper.querySelector('.processing-progress-bar')
        : null
    /** @type {HTMLElement|null} */
    var progressText = document.getElementById('processingProgressText')

    if (!processAllButton && !processNewButton) {
        return
    }

    /** @type {boolean} */
    var isProcessing = false

    /**
     * Update the progress bar width and status text.
     *
     * @param {number} percent - Progress percentage (0-100).
     * @param {string} [text] - Status text to display.
     * @returns {void}
     */
    function showProgress(percent, text) {
        if (progressWrapper) {
            progressWrapper.style.display = 'inline-flex'
        }
        if (progressBar) {
            progressBar.style.width = percent + '%'
        }
        if (progressText) {
            progressText.textContent = text || ''
        }
    }

    /**
     * Animate the progress bar to 100% and hide it after a short delay.
     *
     * @returns {void}
     */
    function hideProgress() {
        if (progressBar) {
            progressBar.style.width = '100%'
        }

        setTimeout(function () {
            if (progressWrapper) {
                progressWrapper.style.display = 'none'
            }
            if (progressBar) {
                progressBar.style.width = '0%'
            }
            if (progressText) {
                progressText.textContent = ''
            }
        }, 800)
    }

    /**
     * Enable or disable the batch processing buttons and the batch size input.
     *
     * Locks the input during processing so the batch size cannot change mid-run.
     *
     * @param {boolean} disabled - True to disable, false to enable.
     * @returns {void}
     */
    function setButtonsDisabled(disabled) {
        ;[processAllButton, processNewButton].forEach(function (btn) {
            if (btn) {
                btn.disabled = disabled
            }
        })
        if (batchSizeInput) {
            batchSizeInput.disabled = disabled
        }
    }

    /**
     * Read the current batch size from the input field.
     *
     * Falls back to 5 if the input is missing or contains an invalid value.
     *
     * @returns {number} Batch size clamped between 1 and 50.
     */
    function getBatchSize() {
        if (!batchSizeInput) {
            return 5
        }
        var parsed = parseInt(batchSizeInput.value, 10)
        if (isNaN(parsed) || parsed < 1) {
            return 1
        }
        if (parsed > 50) {
            return 50
        }
        return parsed
    }

    /**
     * Run chunked batch processing for the given mode.
     *
     * 1. Calls onStartBatch to prepare the offer list and get the total count.
     * 2. Loops onProcessBatch with incrementing offsets until done.
     * 3. Shows real-time progress and a summary flash message on completion.
     *
     * @param {string} mode - 'all' to re-process everything, 'new' for unprocessed only.
     * @param {HTMLElement} triggerElement - The button element (used as $.request context).
     * @returns {void}
     */
    function runBatchProcess(mode, triggerElement) {
        if (isProcessing) {
            return
        }

        var batchSize = getBatchSize()

        isProcessing = true
        setButtonsDisabled(true)
        showProgress(0, 'Preparing\u2026')

        $(triggerElement).request('onStartBatch', {
            data: { mode: mode },
            success: function (data) {
                var total = data.total

                if (total === 0) {
                    $.oc.flashMsg({ text: 'No offers to process.', class: 'warning' })
                    hideProgress()
                    setButtonsDisabled(false)
                    isProcessing = false
                    return
                }

                var offset = 0
                var totalProcessed = 0
                var totalFailed = 0
                var totalSkipped = 0

                /**
                 * Process the next chunk and recurse until done.
                 *
                 * @returns {void}
                 */
                function processNextChunk() {
                    var percent = Math.min(Math.round((offset / total) * 100), 99)
                    showProgress(percent, offset + ' / ' + total + ' offers')

                    $(triggerElement).request('onProcessBatch', {
                        data: {
                            mode: mode,
                            offset: offset,
                            batch_size: batchSize
                        },
                        success: function (result) {
                            totalProcessed += result.processed
                            totalFailed += result.failed
                            totalSkipped += result.skipped
                            offset += batchSize

                            if (result.done || offset >= total) {
                                showProgress(100, total + ' / ' + total + ' offers')
                                $.oc.flashMsg({
                                    text: 'Processed ' + totalProcessed
                                        + ', skipped ' + totalSkipped
                                        + ', failed ' + totalFailed
                                        + ' of ' + total + ' offers.',
                                    class: 'success'
                                })
                                hideProgress()
                                setButtonsDisabled(false)
                                isProcessing = false
                                window.location.reload()
                            } else {
                                processNextChunk()
                            }
                        },
                        error: function () {
                            $.oc.flashMsg({
                                text: 'Processing failed at offset ' + offset
                                    + '. ' + totalProcessed + ' offers completed so far.',
                                class: 'error'
                            })
                            hideProgress()
                            setButtonsDisabled(false)
                            isProcessing = false
                        }
                    })
                }

                processNextChunk()
            },
            error: function () {
                $.oc.flashMsg({
                    text: 'Failed to start batch processing.',
                    class: 'error'
                })
                hideProgress()
                setButtonsDisabled(false)
                isProcessing = false
            }
        })
    }

    if (processAllButton) {
        processAllButton.addEventListener('click', function (event) {
            event.preventDefault()
            event.stopPropagation()
            $.oc.confirm('This will re-process ALL offers. Continue?', function () {
                runBatchProcess('all', processAllButton)
            })
        })
    }

    if (processNewButton) {
        processNewButton.addEventListener('click', function (event) {
            event.preventDefault()
            event.stopPropagation()
            runBatchProcess('new', processNewButton)
        })
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeColorClassifierMatrix)
} else {
    initializeColorClassifierMatrix()
}
