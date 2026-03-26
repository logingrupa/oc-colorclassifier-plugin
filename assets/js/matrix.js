/**
 * matrix.js — Backend JavaScript for the Color Classifier plugin.
 *
 * Enhances the color entries list view with loading state management
 * and progress bar feedback during batch processing operations.
 *
 * OctoberCMS handles Flash messages automatically via Flash::success().
 * This file adds visual polish on top of the built-in AJAX behavior.
 *
 * @module ColorClassifierMatrix
 */

'use strict'

/**
 * Initialize the Color Classifier backend UI enhancements.
 *
 * Binds AJAX lifecycle events to the Process All and Process New buttons
 * to show and hide the progress bar during batch operations.
 *
 * @returns {void}
 */
function initializeColorClassifierMatrix() {
    const processAllButton = document.querySelector('[data-request="onProcessAll"]')
    const processNewButton = document.querySelector('[data-request="onProcessNew"]')
    const progressWrapper  = document.getElementById('processingProgress')
    const progressBar      = progressWrapper
        ? progressWrapper.querySelector('.processing-progress-bar')
        : null

    if (!processAllButton && !processNewButton) {
        return
    }

    /**
     * Show the progress bar and animate it to indicate activity.
     *
     * @returns {void}
     */
    function showProgressIndicator() {
        if (!progressWrapper || !progressBar) {
            return
        }

        progressWrapper.style.display = 'inline-block'
        progressBar.style.width = '0%'

        // Animate to 85% to indicate progress without false completion
        requestAnimationFrame(function () {
            progressBar.style.width = '85%'
        })
    }

    /**
     * Complete and hide the progress bar after processing finishes.
     *
     * @returns {void}
     */
    function hideProgressIndicator() {
        if (!progressWrapper || !progressBar) {
            return
        }

        progressBar.style.width = '100%'

        setTimeout(function () {
            progressWrapper.style.display = 'none'
            progressBar.style.width = '0%'
        }, 600)
    }

    /**
     * Attach progress bar lifecycle events to a batch processing button.
     *
     * @param {HTMLElement} buttonElement - The button to attach listeners to.
     * @returns {void}
     */
    function attachProgressListenersToButton(buttonElement) {
        if (!buttonElement) {
            return
        }

        buttonElement.addEventListener('ajaxSetup', function () {
            showProgressIndicator()
        })

        buttonElement.addEventListener('ajaxDone', function () {
            hideProgressIndicator()
        })

        buttonElement.addEventListener('ajaxFail', function () {
            hideProgressIndicator()
        })
    }

    attachProgressListenersToButton(processAllButton)
    attachProgressListenersToButton(processNewButton)
}

// Initialize when the DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeColorClassifierMatrix)
} else {
    initializeColorClassifierMatrix()
}
