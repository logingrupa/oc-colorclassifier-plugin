/**
 * family-board-drawer.js - the side drawer for one offer.
 *
 * The image is shown at its natural aspect ratio, so a click position as a
 * fraction of the rendered size maps to the same fraction of the original
 * pixels. The server samples those pixels; the browser never reads them
 * (the image host's CORS rules would block a canvas read).
 *
 * @module ColorClassifierFamilyBoard.Drawer
 */

'use strict'

;(function (namespace) {

    var escapeHtml = namespace.escapeHtml
    var backgroundStyle = namespace.backgroundStyle

    /**
     * @typedef {Object} DrawerHandlers
     * @property {function(number, number, number): Promise<string|null>} sampleHex Offer id, relative x, relative y; resolves the hex or null on failure.
     * @property {function({id: number, family: string, hex: string, locked: boolean}): Promise<boolean>} save Resolves true when saved.
     * @property {function(number): Promise<boolean>} reset Offer id; resolves true when reset.
     */

    /**
     * @param {string|null} left
     * @param {string|null} right
     * @returns {boolean} Case-insensitive hex equality.
     */
    function isSameHex(left, right) {
        return (left || '').toLowerCase() === (right || '').toLowerCase()
    }

    /**
     * @param {HTMLElement} drawer Drawer element.
     * @param {import('./family-board-store.js').Store} store
     * @param {DrawerHandlers} handlers
     */
    function createDrawer(drawer, store, handlers) {
        /** @type {import('./family-board-store.js').Card|null} */
        var card = null
        /** @type {string|null} Hex picked in this session and not saved yet. */
        var pendingHex = null
        /** @type {{getValue: function(): string}|null} */
        var familyDropdown = null

        function currentHex() {
            return pendingHex !== null ? pendingHex : card.hex
        }

        function selectedFamily() {
            return familyDropdown.getValue()
        }

        /** Saving an override locks the row, so the switch is forced on while one is pending. */
        function syncLockSwitch() {
            var lockInput = drawer.querySelector('[data-fb-drawer-lock]')
            var overridePending = (selectedFamily() || null) !== card.autoFamily || !isSameHex(currentHex(), card.autoHex)
            if (overridePending) {
                lockInput.checked = true
            }
            lockInput.disabled = overridePending
        }

        function syncPickedHex() {
            var hex = currentHex()
            var swatch = drawer.querySelector('[data-fb-pick-swatch]')
            swatch.style.background = namespace.isHexColor(hex) ? hex : ''
            drawer.querySelector('[data-fb-pick-hex]').textContent = (hex || '').toUpperCase()
            drawer.querySelector('[data-fb-picked]').hidden = pendingHex === null
            syncLockSwitch()
        }

        /**
         * @param {string} label
         * @param {string|null} family
         * @param {string} confidenceText
         * @param {string|null} hex
         * @returns {string}
         */
        function renderSummary(label, family, confidenceText, hex) {
            var familyRow = store.findFamily(family)
            return '<div><small>' + label + '</small>'
                + '<span class="fb-dot"' + backgroundStyle(familyRow ? familyRow.hex : '') + '></span> '
                + escapeHtml(family || '—') + ' · ' + confidenceText
                + '<div class="fb-hint"><span class="fb-dot fb-dot--small"' + backgroundStyle(hex) + '></span> ' + escapeHtml((hex || '').toUpperCase()) + '</div></div>'
        }

        /**
         * Active families in sort order. The current family stays listed, disabled,
         * when it was switched off.
         *
         * @returns {import('./family-board-dropdown.js').DropdownOption[]}
         */
        function buildFamilyChoices() {
            var active = store.getActiveFamilies()
            var choices = namespace.buildFamilyDropdownOptions(active)
            var isListed = active.some(function (family) {
                return family.family === card.family
            })
            if (card.family && !isListed) {
                var inactive = store.findFamily(card.family)
                choices.unshift({
                    value: card.family,
                    text: card.family + ' (inactive)',
                    iconHtml: '<span class="fb-dot fb-dot--large"' + backgroundStyle(inactive ? inactive.hex : '') + '></span>',
                    disabled: true
                })
            }
            return choices
        }

        function render() {
            var paletteButtons = card.palette.map(function (hex) {
                return '<button type="button" class="fb-pal-pick"' + backgroundStyle(hex) + ' data-hex="' + escapeHtml(hex) + '" title="' + escapeHtml(hex) + '" aria-label="Use ' + escapeHtml(hex) + '"></button>'
            }).join('')
            var exportedConfidence = namespace.formatConfidence(card.confidence) + (card.manual ? ' manual' : '')

            drawer.innerHTML = '<button type="button" class="fb-drawer-close" data-fb-drawer-close aria-label="Close">&times;</button>'
                + '<h3 class="fb-drawer-title">' + escapeHtml(card.variationName) + '</h3>'
                + '<div class="fb-hint">' + escapeHtml(card.productName) + ' · ' + escapeHtml(card.offerKey) + '</div>'
                + '<div class="fb-image-wrap"><img data-fb-pick-image src="' + escapeHtml(card.imageUrl) + '" alt="' + escapeHtml(card.variationName) + '"></div>'
                + '<p class="fb-hint">Click the image to pick the swatch color. The family does not change when you pick.</p>'
                + '<div class="fb-row"><span class="fb-row-label">Swatch hex</span><span class="fb-swatch" data-fb-pick-swatch></span>'
                + '<code data-fb-pick-hex></code><span class="fb-tag" data-fb-picked hidden>picked</span></div>'
                + '<div class="fb-row"><span class="fb-row-label">Palette</span><div class="fb-pal fb-pal--pick">' + paletteButtons + '</div></div>'
                + '<div class="fb-row"><span class="fb-row-label">Family</span><div data-fb-drawer-family></div></div>'
                + '<div class="fb-row"><span class="fb-row-label">Lock</span>'
                + '<label class="fb-switch"><input type="checkbox" data-fb-drawer-lock' + (card.locked ? ' checked' : '') + '> Process All skips this offer</label></div>'
                + '<div class="fb-compare">'
                + renderSummary('Classifier said', card.autoFamily, namespace.formatConfidence(card.autoConfidence), card.autoHex)
                + renderSummary('Exported to shops', card.family, exportedConfidence, card.hex)
                + '</div>'
                + '<div class="fb-row fb-row--actions"><button type="button" class="btn btn-primary" data-fb-drawer-save>Save</button>'
                + '<button type="button" class="btn btn-default" data-fb-drawer-reset>Reset to auto</button></div>'

            familyDropdown = namespace.createDropdown({
                container: drawer.querySelector('[data-fb-drawer-family]'),
                label: 'Family',
                options: buildFamilyChoices(),
                value: card.family || '',
                onChange: syncLockSwitch
            })
            syncPickedHex()
        }

        function close() {
            card = null
            pendingHex = null
            familyDropdown = null
            drawer.hidden = true
            drawer.innerHTML = ''
        }

        /**
         * @param {number} cardId
         */
        function open(cardId) {
            card = store.getCard(cardId)
            pendingHex = null
            render()
            drawer.hidden = false
            drawer.scrollTop = 0
        }

        /**
         * @param {HTMLImageElement} image Drawer image.
         * @param {MouseEvent} event Click on it.
         */
        function pickFromImage(image, event) {
            var bounds = image.getBoundingClientRect()
            var relativeX = Math.min(1, Math.max(0, (event.clientX - bounds.left) / bounds.width))
            var relativeY = Math.min(1, Math.max(0, (event.clientY - bounds.top) / bounds.height))
            var pickedCardId = card.id

            drawer.classList.add('is-busy')
            handlers.sampleHex(pickedCardId, relativeX, relativeY).then(function (hex) {
                drawer.classList.remove('is-busy')
                if (hex !== null && card !== null && card.id === pickedCardId) {
                    pendingHex = hex
                    syncPickedHex()
                }
            })
        }

        drawer.addEventListener('click', function (event) {
            if (card === null) {
                return
            }
            var target = event.target
            if (target.closest('[data-fb-drawer-close]')) {
                close()
            } else if (target.closest('[data-fb-pick-image]')) {
                pickFromImage(target.closest('[data-fb-pick-image]'), event)
            } else if (target.closest('[data-hex]')) {
                pendingHex = target.closest('[data-hex]').getAttribute('data-hex')
                syncPickedHex()
            } else if (target.closest('[data-fb-drawer-save]')) {
                handlers.save({
                    id: card.id,
                    family: selectedFamily(),
                    hex: pendingHex !== null ? pendingHex : (card.hexOverridden ? card.hex : ''),
                    locked: drawer.querySelector('[data-fb-drawer-lock]').checked
                }).then(function (saved) {
                    if (saved) {
                        close()
                    }
                })
            } else if (target.closest('[data-fb-drawer-reset]')) {
                handlers.reset(card.id).then(function (done) {
                    if (done) {
                        close()
                    }
                })
            }
        })

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && card !== null) {
                close()
            }
        })

        return { open: open, close: close }
    }

    namespace.createDrawer = createDrawer

})(window.ColorClassifierFamilyBoard = window.ColorClassifierFamilyBoard || {})
