/**
 * family-board-store.js - state and derived lists for the Family board.
 *
 * No DOM access here: the view renders what the store derives.
 *
 * @module ColorClassifierFamilyBoard.Store
 */

'use strict'

;(function (namespace) {

    /**
     * @typedef {Object} Card
     * @property {number} id
     * @property {string} offerKey
     * @property {string} productName
     * @property {string} variationName
     * @property {string} colorName
     * @property {string} imageUrl
     * @property {string[]} palette
     * @property {string|null} family Effective family.
     * @property {string|null} autoFamily Classifier family.
     * @property {boolean} familyOverridden
     * @property {string|null} hex Effective hex.
     * @property {string|null} autoHex Classifier hex.
     * @property {boolean} hexOverridden
     * @property {number|null} confidence Effective confidence.
     * @property {number|null} autoConfidence Classifier confidence.
     * @property {boolean} manual
     * @property {boolean} locked
     */

    /**
     * @typedef {Object} Family
     * @property {string} family
     * @property {string} slug
     * @property {string} hex
     * @property {boolean} active
     * @property {boolean} custom
     */

    /**
     * @typedef {Object} Group
     * @property {Family|null} family Null for offers whose family is unknown.
     * @property {string} label
     * @property {Card[]} cards
     */

    /** @typedef {ReturnType<createStore>} Store */

    /** Label of the group holding offers without a known family. */
    var UNCLASSIFIED_LABEL = 'Unclassified'

    /**
     * @param {{families: Family[], cards: Card[], lowConfidenceThreshold: number}} payload
     */
    function createStore(payload) {
        /** @type {Map<number, Card>} */
        var cards = new Map()
        /** @type {Set<number>} */
        var selection = new Set()
        var filters = { product: '', search: '', low: false, manual: false, locked: false }

        payload.cards.forEach(function (card) {
            cards.set(card.id, card)
        })

        /**
         * @param {Card} card
         * @returns {boolean}
         */
        function matchesFilters(card) {
            if (filters.product !== '' && card.productName !== filters.product) {
                return false
            }
            if (filters.low && !(card.confidence !== null && card.confidence < payload.lowConfidenceThreshold)) {
                return false
            }
            if (filters.manual && !card.manual) {
                return false
            }
            if (filters.locked && !card.locked) {
                return false
            }
            return matchesSearch(card)
        }

        /**
         * Search covers variation, color name and both hex values, with or without the hash.
         *
         * @param {Card} card
         * @returns {boolean}
         */
        function matchesSearch(card) {
            var needle = filters.search.trim().toLowerCase().replace(/^#/, '')
            if (needle === '') {
                return true
            }
            return [card.variationName, card.colorName, card.hex, card.autoHex].some(function (text) {
                return (text || '').toLowerCase().replace(/^#/, '').indexOf(needle) !== -1
            })
        }

        /**
         * @param {Card} left
         * @param {Card} right
         * @returns {number} Confidence descending, then variation name.
         */
        function compareCards(left, right) {
            var byConfidence = (right.confidence === null ? -1 : right.confidence) - (left.confidence === null ? -1 : left.confidence)
            return byConfidence !== 0 ? byConfidence : left.variationName.localeCompare(right.variationName, undefined, { numeric: true })
        }

        return {
            families: payload.families,
            lowConfidenceThreshold: payload.lowConfidenceThreshold,
            unclassifiedLabel: UNCLASSIFIED_LABEL,

            /** @returns {Family[]} Active families in sort order. */
            getActiveFamilies: function () {
                return payload.families.filter(function (family) {
                    return family.active
                })
            },

            /**
             * @param {string|null} familyName
             * @returns {Family|undefined}
             */
            findFamily: function (familyName) {
                return payload.families.find(function (family) {
                    return family.family === familyName
                })
            },

            /**
             * @param {number} cardId
             * @returns {Card|undefined}
             */
            getCard: function (cardId) {
                return cards.get(cardId)
            },

            /** @returns {Card[]} Cards that pass every filter. */
            getVisibleCards: function () {
                return Array.from(cards.values()).filter(matchesFilters)
            },

            /**
             * Groups in family sort order, only those with visible cards. A family
             * that was switched off still shows while it holds offers.
             *
             * @returns {Group[]}
             */
            getGroups: function () {
                var visible = this.getVisibleCards()
                var groups = payload.families.map(function (family) {
                    return {
                        family: family,
                        label: family.family,
                        cards: visible.filter(function (card) {
                            return card.family === family.family
                        }).sort(compareCards)
                    }
                }).filter(function (group) {
                    return group.cards.length > 0
                })

                var orphans = visible.filter(function (card) {
                    return !payload.families.some(function (family) {
                        return family.family === card.family
                    })
                }).sort(compareCards)

                if (orphans.length > 0) {
                    groups.push({ family: null, label: UNCLASSIFIED_LABEL, cards: orphans })
                }
                return groups
            },

            /**
             * @returns {Map<string, number>} Visible card count per effective family.
             */
            getRailCounts: function () {
                var counts = new Map()
                this.getVisibleCards().forEach(function (card) {
                    counts.set(card.family, (counts.get(card.family) || 0) + 1)
                })
                return counts
            },

            /**
             * The thumbnail is the image of the product's first offer, so the picker needs no extra request.
             *
             * @returns {{name: string, count: number, imageUrl: string}[]} Distinct products in name order.
             */
            getProductOptions: function () {
                var products = new Map()
                cards.forEach(function (card) {
                    var product = products.get(card.productName)
                    if (!product) {
                        product = { name: card.productName, count: 0, imageUrl: '' }
                        products.set(card.productName, product)
                    }
                    product.count++
                    if (product.imageUrl === '') {
                        product.imageUrl = card.imageUrl
                    }
                })
                return Array.from(products.values()).sort(function (left, right) {
                    return left.name.localeCompare(right.name)
                })
            },

            /**
             * @param {string} name product or search
             * @returns {string|boolean}
             */
            getFilterValue: function (name) {
                return filters[name]
            },

            /** @returns {number} Every card, ignoring filters. */
            getTotalCount: function () {
                return cards.size
            },

            /** @returns {number} Locked cards, ignoring filters: what Process All skips. */
            getLockedCount: function () {
                return Array.from(cards.values()).filter(function (card) {
                    return card.locked
                }).length
            },

            /** @param {Card[]} returnedCards Rows the server saved. */
            mergeCards: function (returnedCards) {
                returnedCards.forEach(function (card) {
                    cards.set(card.id, card)
                })
            },

            /**
             * @param {string} name product, search, or a chip name (low, manual, locked)
             * @param {string|boolean} value
             */
            setFilter: function (name, value) {
                filters[name] = value
                this.pruneSelection()
            },

            /** @returns {boolean} Whether a chip filter is on. */
            isChipOn: function (name) {
                return filters[name] === true
            },

            /** Selection only holds cards that are still visible. */
            pruneSelection: function () {
                var visibleIds = new Set(this.getVisibleCards().map(function (card) {
                    return card.id
                }))
                selection.forEach(function (cardId) {
                    if (!visibleIds.has(cardId)) {
                        selection.delete(cardId)
                    }
                })
            },

            /** @param {number} cardId */
            toggleSelection: function (cardId) {
                if (selection.has(cardId)) {
                    selection.delete(cardId)
                } else {
                    selection.add(cardId)
                }
            },

            clearSelection: function () {
                selection.clear()
            },

            /** @returns {number[]} */
            getSelectedIds: function () {
                return Array.from(selection)
            },

            /**
             * @param {number} cardId
             * @returns {boolean}
             */
            isSelected: function (cardId) {
                return selection.has(cardId)
            },

            /**
             * Ids a drag carries: the whole selection when the dragged card is in it.
             *
             * @param {number} cardId
             * @returns {number[]}
             */
            getDragIds: function (cardId) {
                return selection.has(cardId) ? Array.from(selection) : [cardId]
            }
        }
    }

    namespace.createStore = createStore

})(window.ColorClassifierFamilyBoard = window.ColorClassifierFamilyBoard || {})
