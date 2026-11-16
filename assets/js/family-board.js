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
            return window.oc.request(root, handler, { data: data, flash: true }).then(undefined, function () {
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

        function addMemberships(ids, family) {
            return changeEntries('onAddMemberships', ids, { family: family })
        }

        function removeMemberships(ids, family) {
            return changeEntries('onRemoveMemberships', ids, { family: family })
        }

        function resetMemberships(ids, family) {
            return changeEntries('onResetMemberships', ids, { family: family })
        }

        /**
         * A membership change from the drawer leaves the board selection alone.
         *
         * @param {string} handler
         * @param {number} id
         * @param {string} family
         * @returns {Promise<boolean>}
         */
        function changeMembershipFromDrawer(handler, id, family) {
            return sendRequest(handler, { ids: [id], family: family }).then(function (response) {
                return applyResponse(response, false)
            })
        }

        var chooser = namespace.createChooser(root)

        /**
         * @param {number[]} ids
         * @param {string} sourceKey
         * @returns {boolean} Whether any dragged offer has the source as its primary color.
         */
        function isPrimaryOfAny(ids, sourceKey) {
            return ids.some(function (cardId) {
                return store.getCard(cardId).family === sourceKey
            })
        }

        /**
         * A drop on a group of another kind adds without asking; a drop on the same kind asks Move or Clone;
         * a drop on a group the offer is already in does nothing.
         *
         * @param {number[]} ids
         * @param {string} targetKey
         * @param {string} sourceKey Empty when the card was dragged from Unclassified.
         * @param {{x: number, y: number}} point
         */
        function routeDrop(ids, targetKey, sourceKey, point) {
            var target = store.findFamily(targetKey)
            var droppedIds = ids.filter(function (cardId) {
                return store.getGroupKeys(cardId).indexOf(targetKey) === -1
            })
            if (!target || droppedIds.length === 0) {
                return
            }

            var source = sourceKey === '' ? null : store.findFamily(sourceKey)
            if (target.kind !== (source ? source.kind : 'color')) {
                addMemberships(droppedIds, targetKey)
            } else if (source === null) {
                moveEntries(droppedIds, targetKey)
            } else {
                chooser.ask({
                    x: point.x,
                    y: point.y,
                    prompt: 'Move or clone ' + droppedIds.length + (droppedIds.length === 1 ? ' offer' : ' offers') + ' to ' + targetKey + '?',
                    moveHint: isPrimaryOfAny(droppedIds, sourceKey) ? targetKey + ' becomes the primary color and the offer locks' : 'leaves ' + sourceKey,
                    cloneHint: 'stays in ' + sourceKey + ' too',
                    onChoose: function (choice) {
                        if (choice === 'move') {
                            changeEntries('onMoveMemberships', droppedIds, { from: sourceKey, family: targetKey })
                        } else {
                            addMemberships(droppedIds, targetKey)
                        }
                    }
                })
            }
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
            },
            addMembership: function (id, family) {
                return changeMembershipFromDrawer('onAddMemberships', id, family)
            },
            removeMembership: function (id, family) {
                return changeMembershipFromDrawer('onRemoveMemberships', id, family)
            },
            resetMembership: function (id, family) {
                return changeMembershipFromDrawer('onResetMemberships', id, family)
            }
        })

        var drag = namespace.createDragController(root, store, {
            onStart: function () {
                hoverElement = null
                view.hidePopover()
            },
            onDrop: routeDrop
        })

        var bulkHandlers = {
            move: function (ids) {
                var family = root.querySelector('[data-fb-bulk-family]').value
                return store.findFamily(family).kind === 'collection' ? addMemberships(ids, family) : moveEntries(ids, family)
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
            var cardAction = target.closest('[data-fb-card-action]')
            var groupReset = target.closest('[data-fb-group-reset]')
            var cardElement = target.closest('[data-fb-card]')

            if (chip) {
                var chipName = chip.getAttribute('data-fb-chip')
                store.setFilter(chipName, !store.isChipOn(chipName))
                refresh()
            } else if (action) {
                runBulkAction(action.getAttribute('data-fb-action'))
            } else if (cardAction) {
                var cardId = Number(cardElement.getAttribute('data-fb-card'))
                var familyKey = cardAction.getAttribute('data-fb-family')
                if (cardAction.getAttribute('data-fb-card-action') === 'remove') {
                    removeMemberships([cardId], familyKey)
                } else {
                    resetMemberships([cardId], familyKey)
                }
            } else if (groupReset) {
                var collectionKey = groupReset.getAttribute('data-fb-group-reset')
                resetMemberships(store.getHandEditedIds(collectionKey), collectionKey)
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
