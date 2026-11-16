/**
 * family-board-chooser.js - the Move or Clone question after a drop on a group of the same kind.
 *
 * It opens next to the drop point, takes focus, and writes nothing until a
 * button is pressed. Escape, Cancel and a press outside close it unanswered.
 *
 * @module ColorClassifierFamilyBoard.Chooser
 */

'use strict'

;(function (namespace) {

    /** Gap in pixels between the chooser and the viewport edge. */
    var VIEWPORT_MARGIN_PX = 8

    /**
     * @typedef {Object} ChooserRequest
     * @property {number} x Drop point in viewport pixels.
     * @property {number} y
     * @property {string} prompt
     * @property {string} moveHint What Move does.
     * @property {string} cloneHint What Clone does.
     * @property {function('move'|'clone'): void} onChoose
     */

    /**
     * @param {string} choice move, clone or cancel.
     * @param {string} label
     * @param {string} buttonClass
     * @returns {HTMLButtonElement}
     */
    function createButton(choice, label, buttonClass) {
        var button = document.createElement('button')
        button.type = 'button'
        button.className = 'btn ' + buttonClass
        button.setAttribute('data-fb-choose', choice)
        button.textContent = label
        return button
    }

    /**
     * @param {HTMLElement} root Board root element.
     */
    function createChooser(root) {
        /** @type {HTMLElement|null} */
        var element = null
        /** @type {function('move'|'clone'): void|null} */
        var answer = null

        function onKeydown(event) {
            if (event.key === 'Escape') {
                event.preventDefault()
                event.stopPropagation()
                close()
            }
        }

        function onPointerdown(event) {
            if (element !== null && !element.contains(event.target)) {
                close()
            }
        }

        function close() {
            if (element === null) {
                return
            }
            element.remove()
            element = null
            answer = null
            document.removeEventListener('keydown', onKeydown, true)
            document.removeEventListener('pointerdown', onPointerdown, true)
        }

        /** @param {HTMLElement} chooser */
        function place(chooser, x, y) {
            var left = Math.min(x, window.innerWidth - chooser.offsetWidth - VIEWPORT_MARGIN_PX)
            var top = Math.min(y, window.innerHeight - chooser.offsetHeight - VIEWPORT_MARGIN_PX)
            chooser.style.left = Math.max(VIEWPORT_MARGIN_PX, left) + 'px'
            chooser.style.top = Math.max(VIEWPORT_MARGIN_PX, top) + 'px'
        }

        /**
         * @param {ChooserRequest} request
         */
        function ask(request) {
            close()
            answer = request.onChoose
            element = document.createElement('div')
            element.className = 'fb-chooser'
            element.setAttribute('role', 'dialog')
            element.setAttribute('aria-label', request.prompt)

            var prompt = document.createElement('p')
            prompt.className = 'fb-chooser-prompt'
            prompt.textContent = request.prompt
            element.appendChild(prompt)
            element.appendChild(createButton('move', 'Move', 'btn-primary'))
            element.appendChild(createButton('clone', 'Clone', 'btn-default'))
            element.appendChild(createButton('cancel', 'Cancel', 'btn-link'))

            var hints = document.createElement('p')
            hints.className = 'fb-hint'
            hints.textContent = 'Move: ' + request.moveHint + '. Clone: ' + request.cloneHint + '.'
            element.appendChild(hints)

            element.addEventListener('click', function (event) {
                var button = event.target.closest('[data-fb-choose]')
                if (button === null) {
                    return
                }
                var choice = button.getAttribute('data-fb-choose')
                var respond = answer
                close()
                if (choice !== 'cancel') {
                    respond(choice)
                }
            })

            root.appendChild(element)
            place(element, request.x, request.y)
            document.addEventListener('keydown', onKeydown, true)
            document.addEventListener('pointerdown', onPointerdown, true)
            element.querySelector('[data-fb-choose="move"]').focus()
        }

        return { ask: ask, close: close }
    }

    namespace.createChooser = createChooser

})(window.ColorClassifierFamilyBoard = window.ColorClassifierFamilyBoard || {})
