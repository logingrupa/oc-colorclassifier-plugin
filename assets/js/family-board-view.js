/**
 * family-board-view.js - renders the Family board from store state.
 *
 * Payload strings reach the DOM only through escapeHtml, and a hex reaches a
 * style attribute only after isHexColor.
 *
 * @module ColorClassifierFamilyBoard.View
 */

'use strict'

;(function (namespace) {

    /** Confidence at or above this reads as high. */
    var HIGH_CONFIDENCE = 0.7

    /** Popover width in pixels; the CSS keeps the same value. */
    var POPOVER_WIDTH = 300

    /**
     * @param {*} value
     * @returns {string} HTML-safe text.
     */
    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
    }

    /**
     * @param {*} value
     * @returns {boolean} Whether the value is #rgb or #rrggbb.
     */
    function isHexColor(value) {
        return typeof value === 'string' && /^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(value)
    }

    /**
     * @param {*} hex
     * @returns {string} A background style attribute, empty for anything that is not a hex.
     */
    function backgroundStyle(hex) {
        return isHexColor(hex) ? ' style="background:' + hex + '"' : ''
    }

    /**
     * @param {number|null} confidence
     * @param {number} threshold
     * @returns {string}
     */
    function confidenceClass(confidence, threshold) {
        if (confidence === null) {
            return 'is-low'
        }
        if (confidence >= HIGH_CONFIDENCE) {
            return 'is-high'
        }
        return confidence >= threshold ? 'is-mid' : 'is-low'
    }

    /**
     * @param {number|null} confidence
     * @returns {string}
     */
    function formatConfidence(confidence) {
        return confidence === null ? '—' : confidence.toFixed(2)
    }

    /**
     * @param {import('./family-board-store.js').Card} card
     * @param {number} threshold
     * @returns {string}
     */
    function confidenceBadge(card, threshold) {
        return '<span class="fb-conf ' + confidenceClass(card.confidence, threshold) + '">' + formatConfidence(card.confidence) + '</span>'
    }

    /**
     * @param {import('./family-board-store.js').Card} card
     * @param {import('./family-board-store.js').Store} store
     * @returns {string}
     */
    function renderCard(card, store) {
        var selected = store.isSelected(card.id)
        var autoLine = card.familyOverridden && card.autoFamily
            ? '<div class="fb-sub"><span class="fb-auto">auto: ' + escapeHtml(card.autoFamily) + '</span></div>'
            : ''

        return '<div class="fb-card' + (selected ? ' is-selected' : '') + '" data-fb-card="' + card.id + '">'
            + '<span class="fb-thumb"><img src="' + escapeHtml(card.imageUrl) + '" alt="" loading="lazy" decoding="async" draggable="false"></span>'
            + '<div class="fb-meta">'
            + '<div class="fb-name" title="' + escapeHtml(card.variationName) + '">' + escapeHtml(card.variationName) + '</div>'
            + '<div class="fb-sub">'
            + confidenceBadge(card, store.lowConfidenceThreshold)
            + (card.manual ? '<span class="fb-tag">manual</span>' : '')
            + (card.locked ? '<i class="icon-lock fb-lock" title="Locked: Process All skips it"></i>' : '')
            + '<span class="fb-dot fb-dot--small"' + backgroundStyle(card.hex) + ' title="' + escapeHtml(card.hex) + '"></span>'
            + '</div>'
            + autoLine
            + '</div>'
            + '<input type="checkbox" class="fb-check" data-fb-check aria-label="Select ' + escapeHtml(card.variationName) + '"' + (selected ? ' checked' : '') + '>'
            + '</div>'
    }

    /**
     * @param {import('./family-board-store.js').Group} group
     * @param {import('./family-board-store.js').Store} store
     * @returns {string}
     */
    function renderGroup(group, store) {
        var family = group.family
        var dropAttribute = family ? ' data-fb-drop="' + escapeHtml(family.family) + '"' : ''
        var inactiveTag = family && !family.active ? '<span class="fb-tag fb-tag--muted">inactive</span>' : ''

        return '<section class="fb-group"' + dropAttribute + '>'
            + '<header class="fb-group-head"><span class="fb-dot"' + backgroundStyle(family ? family.hex : '#9e9e9e') + '></span>'
            + '<span>' + escapeHtml(group.label) + '</span>' + inactiveTag
            + '<span class="fb-count">' + group.cards.length + '</span></header>'
            + '<div class="fb-grid">' + group.cards.map(function (card) {
                return renderCard(card, store)
            }).join('') + '</div></section>'
    }

    /**
     * @param {import('./family-board-store.js').Family} family
     * @param {number} count
     * @returns {string}
     */
    function renderRailItem(family, count) {
        return '<div class="fb-rail-item' + (count === 0 ? ' is-empty' : '') + '" data-fb-drop="' + escapeHtml(family.family) + '">'
            + '<span class="fb-dot"' + backgroundStyle(family.hex) + '></span>'
            + '<span class="fb-rail-name">' + escapeHtml(family.family) + '</span>'
            + (family.custom ? '<span class="fb-tag">custom</span>' : '')
            + '<span class="fb-count">' + count + '</span></div>'
    }

    /**
     * @param {import('./family-board-store.js').Family[]} families
     * @param {string} selectedFamily
     * @returns {string} Option markup.
     */
    function renderFamilyOptions(families, selectedFamily) {
        return families.map(function (family) {
            return '<option value="' + escapeHtml(family.family) + '"' + (family.family === selectedFamily ? ' selected' : '') + '>'
                + escapeHtml(family.family) + '</option>'
        }).join('')
    }

    /**
     * @param {import('./family-board-store.js').Family[]} families Active families.
     * @returns {import('./family-board-dropdown.js').DropdownOption[]} Rows with a large color swatch.
     */
    function buildFamilyDropdownOptions(families) {
        return families.map(function (family) {
            return {
                value: family.family,
                text: family.family,
                iconHtml: '<span class="fb-dot fb-dot--large"' + backgroundStyle(family.hex) + '></span>',
                tagHtml: family.custom ? '<span class="fb-tag">custom</span>' : ''
            }
        })
    }

    /**
     * The counts are offers (shades), not products.
     *
     * @param {number} count
     * @returns {string} For example "· 227 shades".
     */
    function formatShadeCount(count) {
        return '· ' + count + (count === 1 ? ' shade' : ' shades')
    }

    /**
     * @param {{name: string, count: number, imageUrl: string}[]} products
     * @param {number} totalCount
     * @returns {import('./family-board-dropdown.js').DropdownOption[]} "All products" pinned first, then one row per product.
     */
    function buildProductDropdownOptions(products, totalCount) {
        var allOption = {
            value: '',
            text: 'All products',
            iconHtml: '<span class="fb-dd-thumb fb-dd-thumb--all"><i class="icon-th-large"></i></span>',
            countText: formatShadeCount(totalCount),
            pinned: true
        }

        return [allOption].concat(products.map(function (product) {
            return {
                value: product.name,
                text: product.name,
                iconHtml: '<span class="fb-dd-thumb">'
                    + (product.imageUrl !== '' ? '<img src="' + escapeHtml(product.imageUrl) + '" alt="" loading="lazy" decoding="async">' : '')
                    + '</span>',
                countText: formatShadeCount(product.count)
            }
        }))
    }

    /**
     * @param {HTMLElement} root Board root element.
     * @param {import('./family-board-store.js').Store} store
     * @param {{onProductChange: function(string): void}} handlers
     */
    function createView(root, store, handlers) {
        var productDropdown = namespace.createDropdown({
            container: root.querySelector('[data-fb-product]'),
            label: 'Product',
            options: [],
            value: '',
            searchable: true,
            searchPlaceholder: 'Search products',
            onChange: handlers.onProductChange
        })
        var skipLabel = root.querySelector('[data-fb-skip-label]')
        var rail = root.querySelector('[data-fb-rail]')
        var board = root.querySelector('[data-fb-board]')
        var bulk = root.querySelector('[data-fb-bulk]')
        var popover = root.querySelector('[data-fb-popover]')

        function renderToolbar() {
            productDropdown.setOptions(buildProductDropdownOptions(store.getProductOptions(), store.getTotalCount()))
            productDropdown.setValue(store.getFilterValue('product'))

            var lockedCount = store.getLockedCount()
            skipLabel.textContent = '· skips ' + lockedCount + ' locked'

            root.querySelectorAll('[data-fb-chip]').forEach(function (chip) {
                var isOn = store.isChipOn(chip.getAttribute('data-fb-chip'))
                chip.classList.toggle('is-on', isOn)
                chip.setAttribute('aria-pressed', isOn ? 'true' : 'false')
            })
        }

        function renderRail() {
            var counts = store.getRailCounts()
            rail.innerHTML = '<h4 class="fb-rail-title">Drop on a family</h4>' + store.getActiveFamilies().map(function (family) {
                return renderRailItem(family, counts.get(family.family) || 0)
            }).join('')
        }

        function renderBoard() {
            var groups = store.getGroups()
            board.innerHTML = groups.length > 0
                ? groups.map(function (group) {
                    return renderGroup(group, store)
                }).join('')
                : '<p class="fb-empty">No offers match these filters.</p>'
        }

        /** Selection changes touch the card marks and the bulk bar, not the whole board. */
        function renderSelection() {
            board.querySelectorAll('[data-fb-card]').forEach(function (cardElement) {
                var selected = store.isSelected(Number(cardElement.getAttribute('data-fb-card')))
                cardElement.classList.toggle('is-selected', selected)
                cardElement.querySelector('[data-fb-check]').checked = selected
            })
            renderBulk()
        }

        function renderBulk() {
            var count = store.getSelectedIds().length
            if (bulk.innerHTML === '') {
                bulk.innerHTML = '<strong data-fb-bulk-count></strong>'
                    + '<label class="fb-bulk-move">Move to <select class="form-control" data-fb-bulk-family>'
                    + renderFamilyOptions(store.getActiveFamilies(), '') + '</select></label>'
                    + '<button type="button" class="btn btn-primary" data-fb-action="move">Move + lock</button>'
                    + '<button type="button" class="btn btn-default" data-fb-action="lock"><i class="icon-lock"></i> Lock</button>'
                    + '<button type="button" class="btn btn-default" data-fb-action="unlock">Unlock</button>'
                    + '<button type="button" class="btn btn-default" data-fb-action="reset">Reset to auto</button>'
                    + '<button type="button" class="btn btn-default fb-bulk-clear" data-fb-action="clear" aria-label="Clear selection">&times;</button>'
            }
            bulk.hidden = count === 0
            bulk.querySelector('[data-fb-bulk-count]').textContent = count + ' selected'
        }

        /** Everything the filters and the data feed. */
        function renderAll() {
            renderToolbar()
            renderRail()
            renderBoard()
            renderBulk()
        }

        /**
         * @param {HTMLElement} cardElement Hovered card element.
         * @param {import('./family-board-store.js').Card} card
         */
        function showPopover(cardElement, card) {
            if (!window.matchMedia('(hover: hover)').matches) {
                return
            }
            var palette = card.palette.map(function (hex) {
                return '<span' + backgroundStyle(hex) + '></span>'
            }).join('')
            popover.innerHTML = '<img src="' + escapeHtml(card.imageUrl) + '" alt="">'
                + '<div class="fb-pal">' + palette + '</div>'
                + '<div class="fb-pop-cap"><span>' + escapeHtml((card.hex || '').toUpperCase()) + '</span>'
                + '<span>auto ' + escapeHtml(card.autoFamily || '—') + ' ' + formatConfidence(card.autoConfidence) + '</span></div>'
            popover.hidden = false

            var anchor = cardElement.getBoundingClientRect()
            var left = anchor.right + 8
            if (left + POPOVER_WIDTH > window.innerWidth) {
                left = Math.max(8, anchor.left - POPOVER_WIDTH - 8)
            }
            var top = Math.min(anchor.top - 20, window.innerHeight - popover.offsetHeight - 8)
            popover.style.left = left + 'px'
            popover.style.top = Math.max(8, top) + 'px'
        }

        function hidePopover() {
            popover.hidden = true
            popover.innerHTML = ''
        }

        return {
            renderAll: renderAll,
            renderSelection: renderSelection,
            showPopover: showPopover,
            hidePopover: hidePopover
        }
    }

    namespace.escapeHtml = escapeHtml
    namespace.isHexColor = isHexColor
    namespace.backgroundStyle = backgroundStyle
    namespace.formatConfidence = formatConfidence
    namespace.confidenceBadge = confidenceBadge
    namespace.buildFamilyDropdownOptions = buildFamilyDropdownOptions
    namespace.createView = createView

})(window.ColorClassifierFamilyBoard = window.ColorClassifierFamilyBoard || {})
