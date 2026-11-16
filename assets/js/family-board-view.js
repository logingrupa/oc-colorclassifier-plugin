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
     * A collection with a gradient draws it, every other family draws its hex.
     *
     * @param {import('./family-board-store.js').Family|null|undefined} family
     * @returns {string} A background style attribute, empty when nothing valid can be drawn.
     */
    function swatchStyle(family) {
        var gradient = family ? family.gradient : null
        if (Array.isArray(gradient) && gradient.length >= 2 && gradient.length <= 3 && gradient.every(isHexColor)) {
            return ' style="background:linear-gradient(90deg,' + gradient.join(',') + ')"'
        }
        return backgroundStyle(family ? family.hex : '')
    }

    /**
     * @param {import('./family-board-store.js').Family} family
     * @returns {string} A tag for a family that shops do not receive, empty otherwise.
     */
    function renderInactiveTag(family) {
        if (family.active) {
            return ''
        }
        return '<span class="fb-tag fb-tag--muted">' + (family.kind === 'collection' ? 'draft' : 'inactive') + '</span>'
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
     * @param {string} groupKey Group the instance renders in; empty for Unclassified.
     * @returns {string} The note under the name: where else the card sits, or which color it belongs to here.
     */
    function renderGroupNote(card, groupKey) {
        var otherKeys = card.extraColors.concat(card.collections)
        if (groupKey !== '' && groupKey !== card.family) {
            return card.family ? '<div class="fb-sub"><span class="fb-also">primary: ' + escapeHtml(card.family) + '</span></div>' : ''
        }
        return otherKeys.length > 0 ? '<div class="fb-sub"><span class="fb-also">also: ' + escapeHtml(otherKeys.join(', ')) + '</span></div>' : ''
    }

    /**
     * @param {import('./family-board-store.js').Card} card
     * @param {import('./family-board-store.js').Store} store
     * @param {string} groupKey Group the instance renders in; empty for Unclassified.
     * @returns {string}
     */
    function renderCard(card, store, groupKey) {
        var selected = store.isSelected(card.id)
        var isExtraGroup = groupKey !== '' && groupKey !== card.family
        var autoLine = card.familyOverridden && card.autoFamily
            ? '<div class="fb-sub"><span class="fb-auto">auto: ' + escapeHtml(card.autoFamily) + '</span></div>'
            : ''
        var familyAttribute = ' data-fb-family="' + escapeHtml(groupKey) + '"'
        var resetButton = card.collectionStates[groupKey] === 'added'
            ? '<button type="button" class="fb-tag fb-tag--action" data-fb-card-action="reset"' + familyAttribute + '>Reset to 1C</button>'
            : ''
        var removeButton = isExtraGroup
            ? '<button type="button" class="fb-card-remove" data-fb-card-action="remove"' + familyAttribute
                + ' aria-label="Remove from ' + escapeHtml(groupKey) + '" title="Remove from ' + escapeHtml(groupKey) + '">&times;</button>'
            : ''

        return '<div class="fb-card' + (selected ? ' is-selected' : '') + '" data-fb-card="' + card.id + '" data-fb-source="' + escapeHtml(groupKey) + '">'
            + '<span class="fb-thumb"><img src="' + escapeHtml(card.imageUrl) + '" alt="" loading="lazy" decoding="async" draggable="false"></span>'
            + '<div class="fb-meta">'
            + '<div class="fb-name" title="' + escapeHtml(card.variationName) + '">' + escapeHtml(card.variationName) + '</div>'
            + '<div class="fb-sub">'
            + confidenceBadge(card, store.lowConfidenceThreshold)
            + (card.manual ? '<span class="fb-tag">manual</span>' : '')
            + (card.locked ? '<i class="icon-lock fb-lock" title="Locked: Process All skips it"></i>' : '')
            + '<span class="fb-dot fb-dot--small"' + backgroundStyle(card.hex) + ' title="' + escapeHtml(card.hex) + '"></span>'
            + resetButton
            + '</div>'
            + autoLine
            + renderGroupNote(card, groupKey)
            + '</div>'
            + '<input type="checkbox" class="fb-check" data-fb-check aria-label="Select ' + escapeHtml(card.variationName) + '"' + (selected ? ' checked' : '') + '>'
            + removeButton
            + '</div>'
    }

    /**
     * @param {import('./family-board-store.js').Group} group
     * @param {import('./family-board-store.js').Store} store
     * @returns {string}
     */
    function renderGroup(group, store) {
        var family = group.family
        var groupKey = family ? family.family : ''
        var dropAttribute = family ? ' data-fb-drop="' + escapeHtml(groupKey) + '"' : ''
        var isCollection = family !== null && family.kind === 'collection'
        var note = isCollection && family.feedOffersWithoutCard > 0
            ? '<div class="fb-hint fb-group-note">+' + family.feedOffersWithoutCard + ' tagged in 1C, no image</div>'
            : ''
        var resetButton = isCollection && store.getHandEditedIds(groupKey).length > 0
            ? '<button type="button" class="fb-tag fb-tag--action" data-fb-group-reset="' + escapeHtml(groupKey) + '">Reset to 1C</button>'
            : ''

        return '<section class="fb-group"' + dropAttribute + '>'
            + '<header class="fb-group-head"><span class="fb-dot"' + (family ? swatchStyle(family) : backgroundStyle('#9e9e9e')) + '></span>'
            + '<span>' + escapeHtml(group.label) + '</span>' + (family ? renderInactiveTag(family) : '') + resetButton
            + '<span class="fb-count">' + group.cards.length + '</span></header>' + note
            + '<div class="fb-grid">' + group.cards.map(function (card) {
                return renderCard(card, store, groupKey)
            }).join('') + '</div></section>'
    }

    /**
     * @param {import('./family-board-store.js').Family} family
     * @param {number} count
     * @returns {string}
     */
    function renderRailItem(family, count) {
        return '<div class="fb-rail-item' + (count === 0 ? ' is-empty' : '') + '" data-fb-drop="' + escapeHtml(family.family) + '">'
            + '<span class="fb-dot"' + swatchStyle(family) + '></span>'
            + '<span class="fb-rail-name">' + escapeHtml(family.family) + '</span>'
            + (family.custom ? '<span class="fb-tag">custom</span>' : '')
            + renderInactiveTag(family)
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
                + escapeHtml(family.family) + (family.active ? '' : ' (draft)') + '</option>'
        }).join('')
    }

    /**
     * @param {import('./family-board-store.js').Store} store
     * @returns {string} Bulk target options: colors move and lock, collections only add.
     */
    function renderBulkTargetOptions(store) {
        var collections = store.getCollections()
        return renderFamilyOptions(store.getActiveColors(), '')
            + (collections.length > 0 ? '<optgroup label="Collections (add, no lock)">' + renderFamilyOptions(collections, '') + '</optgroup>' : '')
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
            var collections = store.getCollections()
            var renderItems = function (families) {
                return families.map(function (family) {
                    return renderRailItem(family, counts.get(family.family) || 0)
                }).join('')
            }
            rail.innerHTML = '<h4 class="fb-rail-title">Drop on a family</h4>' + renderItems(store.getActiveColors())
                + (collections.length > 0 ? '<h4 class="fb-rail-title">Collections</h4>' + renderItems(collections) : '')
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
                    + renderBulkTargetOptions(store) + '</select></label>'
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
    namespace.swatchStyle = swatchStyle
    namespace.formatConfidence = formatConfidence
    namespace.confidenceBadge = confidenceBadge
    namespace.buildFamilyDropdownOptions = buildFamilyDropdownOptions
    namespace.createView = createView

})(window.ColorClassifierFamilyBoard = window.ColorClassifierFamilyBoard || {})
