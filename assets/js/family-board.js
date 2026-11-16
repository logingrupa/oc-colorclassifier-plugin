/**
 * family-board.js - boots the Family board and wires events to the October AJAX API.
 *
 * Every change is saved by a server handler first; the board re-renders only
 * from the cards the server returns.
 *
 * @module ColorClassifierFamilyBoard
 */

'use strict'

;(function (namespace) {

    /** Milliseconds the search box waits after the last keystroke. */
    var SEARCH_DEBOUNCE_MS = 200

    /**
     * @param {HTMLElement} root Board root element.
     */
    function boot(root) {
        var payload = JSON.parse(root.querySelector('[data-fb-payload]').textContent)
        var store = namespace.createStore(payload)
        var view = namespace.createView(root, store, {
            onProductChange: function (productName) {
                store.setFilter('product', productName)
                refresh()
            }
        })
        var board = root.querySelector('[data-fb-board]')
        /** @type {Element|null} */
        var hoverElement = null

        /**
         * Post to a board handler. The framework already shows the error of a
         * failed request, so a failure resolves null and the board stays as it was.
         *
         * @param {string} handler
         * @param {Object} data
         * @returns {Promise<Object|null>}
         */
        function sendRequest(handler, data) {
            var startedAt = performance.now()
            return window.oc.request(root, handler, { data: data, flash: true }).then(function (response) {
                console.warn('DEBUGTIME', handler, Math.round(performance.now() - startedAt))
                return response
            }, function (failure) {
                console.warn('DEBUGFAIL', handler, failure && (failure.message || JSON.stringify(failure)))
                return null
            })
        }

        function refresh() {
            hoverElement = null
            view.hidePopover()
            view.renderAll()
        }

        /**
         * @param {{cards?: Object[]}|null} response
         * @param {boolean} clearSelection Drop the selection once the change is saved.
         * @returns {boolean} Whether the server saved the change.
         */
        function applyResponse(response, clearSelection) {
            console.warn('DEBUGAPPLY', response === null ? 'null' : JSON.stringify(Object.keys(response)), response && response.cards && response.cards.map(function (c) { return c.id + ':' + c.family }).join(','))
            if (response === null || !Array.isArray(response.cards)) {
                return false
            }
            store.mergeCards(response.cards)
            if (clearSelection) {
                store.clearSelection()
            }
            store.pruneSelection()
            refresh()
            return true
        }

        /**
         * Bulk change of the given cards; the selection is dropped once saved.
         *
         * @param {string} handler
         * @param {number[]} ids
         * @param {Object} [extra]
         * @returns {Promise<boolean>}
         */
        function changeEntries(handler, ids, extra) {
            return sendRequest(handler, Object.assign({ ids: ids }, extra || {})).then(function (response) {
                return applyResponse(response, true)
            })
        }

        function moveEntries(ids, family) {
            return changeEntries('onMoveEntries', ids, { family: family })
        }

        var drawer = namespace.createDrawer(root.querySelector('[data-fb-drawer]'), store, {
            sampleHex: function (id, relativeX, relativeY) {
                return sendRequest('onSampleHex', { id: id, x: relativeX, y: relativeY }).then(function (response) {
                    return response === null ? null : response.hex
                })
            },
            save: function (data) {
                return sendRequest('onSaveEntry', {
                    id: data.id,
                    family: data.family,
                    hex: data.hex,
                    locked: data.locked ? 1 : 0
                }).then(function (response) {
                    return applyResponse(response, false)
                })
            },
            reset: function (id) {
                return sendRequest('onResetEntries', { ids: [id] }).then(function (response) {
                    return applyResponse(response, false)
                })
            }
        })

        var drag = namespace.createDragController(root, store, {
            onStart: function () {
                hoverElement = null
                view.hidePopover()
            },
            onDrop: moveEntries
        })

        var bulkHandlers = {
            move: function (ids) {
                return moveEntries(ids, root.querySelector('[data-fb-bulk-family]').value)
            },
            lock: function (ids) {
                return changeEntries('onLockEntries', ids)
            },
            unlock: function (ids) {
                return changeEntries('onUnlockEntries', ids)
            },
            reset: function (ids) {
                return changeEntries('onResetEntries', ids)
            }
        }

        root.addEventListener('click', function (event) {
            if (drag.isClickSuppressed()) {
                return
            }
            var target = event.target
            var chip = target.closest('[data-fb-chip]')
            var action = target.closest('[data-fb-action]')
            var cardElement = target.closest('[data-fb-card]')

            if (chip) {
                var chipName = chip.getAttribute('data-fb-chip')
                store.setFilter(chipName, !store.isChipOn(chipName))
                refresh()
            } else if (action) {
                runBulkAction(action.getAttribute('data-fb-action'))
            } else if (target.closest('[data-fb-check]')) {
                store.toggleSelection(Number(cardElement.getAttribute('data-fb-card')))
                view.renderSelection()
            } else if (cardElement) {
                view.hidePopover()
                drawer.open(Number(cardElement.getAttribute('data-fb-card')))
            }
        })

        /**
         * @param {string} actionName move, lock, unlock, reset or clear.
         */
        function runBulkAction(actionName) {
            if (actionName === 'clear') {
                store.clearSelection()
                view.renderSelection()
                return
            }
            bulkHandlers[actionName](store.getSelectedIds())
        }

        var searchTimer = null
        root.querySelector('[data-fb-search]').addEventListener('input', function (event) {
            var text = event.target.value
            window.clearTimeout(searchTimer)
            searchTimer = window.setTimeout(function () {
                store.setFilter('search', text)
                refresh()
            }, SEARCH_DEBOUNCE_MS)
        })

        board.addEventListener('mouseover', function (event) {
            var cardElement = event.target.closest('[data-fb-card]')
            if (drag.isDragging() || cardElement === hoverElement) {
                return
            }
            hoverElement = cardElement
            if (cardElement) {
                view.showPopover(cardElement, store.getCard(Number(cardElement.getAttribute('data-fb-card'))))
            } else {
                view.hidePopover()
            }
        })

        board.addEventListener('mouseleave', function () {
            hoverElement = null
            view.hidePopover()
        })

        refresh()
    }

    function bootWhenReady() {
        var root = document.getElementById('familyBoard')
        if (root) {
            boot(root)
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootWhenReady)
    } else {
        bootWhenReady()
    }

})(window.ColorClassifierFamilyBoard = window.ColorClassifierFamilyBoard || {})
