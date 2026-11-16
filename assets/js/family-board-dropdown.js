/**
 * family-board-dropdown.js - one keyboard accessible dropdown for the board.
 *
 * Used by the toolbar product picker (thumbnails, search) and the drawer
 * family picker (color swatches). A button opens a listbox; the optional search
 * input matches anywhere in the text, ignoring case and diacritics.
 *
 * @module ColorClassifierFamilyBoard.Dropdown
 */

'use strict'

;(function (namespace) {

    var escapeHtml = namespace.escapeHtml

    /**
     * @typedef {Object} DropdownOption
     * @property {string} value
     * @property {string} text
     * @property {string} [iconHtml] Trusted markup; the caller escapes its values.
     * @property {string} [tagHtml] Trusted markup shown after the text.
     * @property {string} [countText]
     * @property {boolean} [disabled] Shown but not pickable.
     * @property {boolean} [pinned] Stays first and visible while searching.
     */

    /**
     * @typedef {Object} DropdownConfig
     * @property {HTMLElement} container Element the dropdown renders into.
     * @property {string} label Accessible name of the listbox.
     * @property {DropdownOption[]} options
     * @property {string} [value] Selected option value.
     * @property {boolean} [searchable]
     * @property {string} [searchPlaceholder]
     * @property {function(string): void} onChange Called with the picked value.
     */

    var instanceCounter = 0

    /** @type {{close: function(boolean): void, contains: function(Node): boolean}|null} The one open dropdown. */
    var openInstance = null

    /**
     * @param {string} text
     * @returns {string} Lowercase text without diacritics.
     */
    function normalizeText(text) {
        return String(text).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
    }

    /**
     * @param {DropdownOption} option
     * @returns {string}
     */
    function renderRow(option) {
        return (option.iconHtml || '')
            + '<span class="fb-dd-text">' + escapeHtml(option.text) + '</span>'
            + (option.tagHtml || '')
            + (option.countText ? '<span class="fb-dd-count">' + escapeHtml(option.countText) + '</span>' : '')
    }

    /**
     * @param {DropdownConfig} config
     */
    function createDropdown(config) {
        var idPrefix = 'fbDd' + (++instanceCounter)
        var container = config.container
        var options = config.options
        var value = config.value === undefined ? '' : config.value
        var isSearchable = config.searchable === true
        /** @type {DropdownOption[]} */
        var visibleOptions = []
        var activeIndex = -1
        var query = ''
        var isOpen = false

        container.classList.add('fb-dd')
        container.innerHTML = '<button type="button" class="fb-dd-button" aria-haspopup="listbox" aria-expanded="false" aria-controls="' + idPrefix + '-panel">'
            + '<span class="fb-dd-current"></span><span class="fb-dd-caret" aria-hidden="true"></span></button>'
            + '<div class="fb-dd-panel" id="' + idPrefix + '-panel" hidden>'
            + (isSearchable
                ? '<input type="search" class="form-control fb-dd-search" autocomplete="off" aria-controls="' + idPrefix + '-list" aria-label="Search" placeholder="' + escapeHtml(config.searchPlaceholder || 'Search') + '">'
                : '')
            + '<ul class="fb-dd-list" id="' + idPrefix + '-list" role="listbox" tabindex="-1" aria-label="' + escapeHtml(config.label) + '"></ul></div>'

        var button = container.querySelector('.fb-dd-button')
        var currentLabel = container.querySelector('.fb-dd-current')
        var panel = container.querySelector('.fb-dd-panel')
        var list = container.querySelector('.fb-dd-list')
        var searchInput = container.querySelector('.fb-dd-search')
        var focusTarget = searchInput || list

        function renderButton() {
            var selected = options.find(function (option) {
                return option.value === value
            })
            currentLabel.innerHTML = selected ? renderRow(selected) : ''
        }

        function computeVisible() {
            var needle = normalizeText(query.trim())
            var matching = options.filter(function (option) {
                return !option.pinned && (needle === '' || normalizeText(option.text).indexOf(needle) !== -1)
            })
            visibleOptions = options.filter(function (option) {
                return option.pinned
            }).concat(matching)
        }

        /** @returns {number} The selected row when not searching, else the first real match. */
        function defaultActiveIndex() {
            var firstEnabled = visibleOptions.findIndex(function (option) {
                return !option.disabled
            })
            if (query.trim() === '') {
                var selectedIndex = visibleOptions.findIndex(function (option) {
                    return option.value === value && !option.disabled
                })
                return selectedIndex >= 0 ? selectedIndex : firstEnabled
            }
            var matchIndex = visibleOptions.findIndex(function (option) {
                return !option.pinned && !option.disabled
            })
            return matchIndex >= 0 ? matchIndex : firstEnabled
        }

        function applyActive() {
            list.querySelectorAll('.is-active').forEach(function (row) {
                row.classList.remove('is-active')
            })
            var row = list.children[activeIndex]
            if (!row || !row.hasAttribute('role') || row.getAttribute('role') !== 'option') {
                focusTarget.removeAttribute('aria-activedescendant')
                return
            }
            row.classList.add('is-active')
            focusTarget.setAttribute('aria-activedescendant', row.id)

            var pinnedRow = list.querySelector('.is-pinned')
            var stickyHeight = pinnedRow && pinnedRow !== row ? pinnedRow.offsetHeight : 0
            if (row.offsetTop - stickyHeight < list.scrollTop) {
                list.scrollTop = row.offsetTop - stickyHeight
            } else if (row.offsetTop + row.offsetHeight > list.scrollTop + list.clientHeight) {
                list.scrollTop = row.offsetTop + row.offsetHeight - list.clientHeight
            }
        }

        function renderList() {
            list.innerHTML = visibleOptions.length > 0
                ? visibleOptions.map(function (option, index) {
                    return '<li role="option" id="' + idPrefix + '-o' + index + '" data-fb-dd-index="' + index + '"'
                        + ' class="fb-dd-option' + (option.pinned ? ' is-pinned' : '') + (option.disabled ? ' is-disabled' : '') + '"'
                        + ' aria-selected="' + (option.value === value ? 'true' : 'false') + '"'
                        + (option.disabled ? ' aria-disabled="true"' : '') + '>' + renderRow(option) + '</li>'
                }).join('')
                : '<li class="fb-dd-empty" role="presentation">No match</li>'
            applyActive()
        }

        /** @param {number} step 1 or -1 */
        function moveActive(step) {
            var index = activeIndex + step
            while (index >= 0 && index < visibleOptions.length && visibleOptions[index].disabled) {
                index += step
            }
            if (index >= 0 && index < visibleOptions.length) {
                activeIndex = index
                applyActive()
            }
        }

        function open() {
            if (openInstance !== null && openInstance !== api) {
                openInstance.close(false)
            }
            isOpen = true
            openInstance = api
            query = ''
            if (searchInput) {
                searchInput.value = ''
            }
            computeVisible()
            activeIndex = defaultActiveIndex()
            panel.hidden = false
            button.setAttribute('aria-expanded', 'true')
            renderList()
            focusTarget.focus({ preventScroll: true })
        }

        /** @param {boolean} returnFocus Move focus back to the button. */
        function close(returnFocus) {
            if (!isOpen) {
                return
            }
            isOpen = false
            openInstance = null
            panel.hidden = true
            button.setAttribute('aria-expanded', 'false')
            if (returnFocus) {
                button.focus({ preventScroll: true })
            }
        }

        /** @param {number} index Index in visibleOptions. */
        function pick(index) {
            var option = visibleOptions[index]
            if (!option || option.disabled) {
                return
            }
            var changed = option.value !== value
            value = option.value
            renderButton()
            close(true)
            if (changed) {
                config.onChange(value)
            }
        }

        button.addEventListener('click', function () {
            if (isOpen) {
                close(true)
            } else {
                open()
            }
        })

        button.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault()
                open()
            }
        })

        panel.addEventListener('keydown', function (event) {
            switch (event.key) {
                case 'ArrowDown':
                    moveActive(1)
                    break
                case 'ArrowUp':
                    moveActive(-1)
                    break
                case 'Home':
                case 'End':
                    if (isSearchable) {
                        return
                    }
                    activeIndex = event.key === 'Home' ? -1 : visibleOptions.length
                    moveActive(event.key === 'Home' ? 1 : -1)
                    break
                case 'Enter':
                    pick(activeIndex)
                    break
                case ' ':
                    if (isSearchable) {
                        return
                    }
                    pick(activeIndex)
                    break
                case 'Escape':
                    event.stopPropagation()
                    close(true)
                    break
                case 'Tab':
                    close(false)
                    return
                default:
                    return
            }
            event.preventDefault()
        })

        list.addEventListener('click', function (event) {
            var row = event.target.closest('[data-fb-dd-index]')
            if (row) {
                pick(Number(row.getAttribute('data-fb-dd-index')))
            }
        })

        list.addEventListener('mousemove', function (event) {
            var row = event.target.closest('[data-fb-dd-index]')
            var index = row ? Number(row.getAttribute('data-fb-dd-index')) : -1
            if (index >= 0 && index !== activeIndex && !visibleOptions[index].disabled) {
                activeIndex = index
                applyActive()
            }
        })

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                query = searchInput.value
                computeVisible()
                activeIndex = defaultActiveIndex()
                list.scrollTop = 0
                renderList()
            })
        }

        var api = {
            /** @returns {string} */
            getValue: function () {
                return value
            },

            /** @param {string} newValue */
            setValue: function (newValue) {
                value = newValue
                renderButton()
            },

            /** @param {DropdownOption[]} newOptions */
            setOptions: function (newOptions) {
                options = newOptions
                renderButton()
            },

            close: close,

            /**
             * @param {Node} node
             * @returns {boolean}
             */
            contains: function (node) {
                return container.contains(node)
            }
        }

        renderButton()

        return api
    }

    document.addEventListener('pointerdown', function (event) {
        if (openInstance !== null && !openInstance.contains(event.target)) {
            openInstance.close(false)
        }
    })

    namespace.createDropdown = createDropdown

})(window.ColorClassifierFamilyBoard = window.ColorClassifierFamilyBoard || {})
