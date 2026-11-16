/**
 * family-board-drag.js - pointer based card dragging for the Family board.
 *
 * Native HTML5 drag and drop blocks the mouse wheel in Chromium, so cards are
 * dragged with pointer events: a ghost follows the pointer, the page scrolls
 * near the viewport edges, and the wheel keeps working. Touch devices use the
 * checkbox and "Move to" bulk bar instead.
 *
 * @module ColorClassifierFamilyBoard.Drag
 */

'use strict'

;(function (namespace) {

    /** Pointer travel in pixels before a press becomes a drag. */
    var DRAG_THRESHOLD_PX = 5

    /** Distance from the viewport top or bottom where auto-scroll starts. */
    var SCROLL_EDGE_PX = 200

    /** Scroll step per frame at the very edge. */
    var MAX_SCROLL_STEP_PX = 25

    /** Ghost offset from the pointer so it does not cover the drop target. */
    var GHOST_OFFSET_PX = 12

    /**
     * @param {number} pointerY Pointer position in the viewport.
     * @param {number} viewportHeight
     * @returns {number} Pixels to scroll this frame; negative scrolls up.
     */
    function resolveScrollStep(pointerY, viewportHeight) {
        var edgeDistance = Math.min(SCROLL_EDGE_PX, viewportHeight / 2)
        var intoTopZone = edgeDistance - pointerY
        var intoBottomZone = pointerY - (viewportHeight - edgeDistance)

        if (intoTopZone > 0) {
            return -Math.ceil(MAX_SCROLL_STEP_PX * Math.min(1, intoTopZone / edgeDistance))
        }
        if (intoBottomZone > 0) {
            return Math.ceil(MAX_SCROLL_STEP_PX * Math.min(1, intoBottomZone / edgeDistance))
        }
        return 0
    }

    /**
     * @param {HTMLElement} root Board root element.
     * @param {import('./family-board-store.js').Store} store
     * @param {{onStart: function(): void, onDrop: function(number[], string, string, {x: number, y: number}): void}} handlers onDrop gets the ids, the target key, the source key (empty for Unclassified) and the drop point.
     */
    function createDragController(root, store, handlers) {
        /** @type {{cardId: number, sourceKey: string, element: HTMLElement, startX: number, startY: number, pointerId: number}|null} */
        var pending = null
        /** @type {{ids: number[], sourceKey: string, ghost: HTMLElement, x: number, y: number, target: Element|null, frame: number}|null} */
        var session = null
        var clickSuppressed = false

        /** @param {Element|null} target */
        function setTarget(target) {
            if (session.target === target) {
                return
            }
            if (session.target) {
                session.target.classList.remove('is-drop-target')
            }
            if (target) {
                target.classList.add('is-drop-target')
            }
            session.target = target
        }

        /** Drop targets under the pointer; the ghost ignores pointer events. */
        function updateTarget() {
            var hit = document.elementFromPoint(session.x, session.y)
            var dropTarget = hit ? hit.closest('[data-fb-drop]') : null
            setTarget(dropTarget && root.contains(dropTarget) ? dropTarget : null)
        }

        function placeGhost() {
            session.ghost.style.transform = 'translate(' + (session.x + GHOST_OFFSET_PX) + 'px,' + (session.y + GHOST_OFFSET_PX) + 'px)'
        }

        /**
         * Scrolls near the edges, then re-checks the target because the page moved under a still pointer.
         * The backend sets scroll-behavior: smooth, which would restart an animation every frame.
         */
        function tick() {
            var step = resolveScrollStep(session.y, window.innerHeight)
            if (step !== 0) {
                window.scrollBy({ top: step, behavior: 'instant' })
            }
            updateTarget()
            session.frame = window.requestAnimationFrame(tick)
        }

        /**
         * @param {HTMLElement} cardElement Pressed card.
         * @param {number} count Cards being dragged.
         * @returns {HTMLElement}
         */
        function createGhost(cardElement, count) {
            var ghost = cardElement.cloneNode(true)
            ghost.removeAttribute('data-fb-card')
            ghost.removeAttribute('data-fb-source')
            ghost.classList.remove('is-selected', 'is-dragging')
            ghost.classList.add('fb-ghost')
            ghost.style.width = cardElement.getBoundingClientRect().width + 'px'
            ghost.querySelectorAll('[data-fb-check], [data-fb-card-action]').forEach(function (control) {
                control.remove()
            })
            if (count > 1) {
                ghost.insertAdjacentHTML('beforeend', '<span class="fb-ghost-count">' + count + '</span>')
            }
            return ghost
        }

        function startSession(event) {
            var ids = store.getDragIds(pending.cardId)

            session = { ids: ids, sourceKey: pending.sourceKey, ghost: createGhost(pending.element, ids.length), x: event.clientX, y: event.clientY, target: null, frame: 0 }
            root.appendChild(session.ghost)
            ids.forEach(function (cardId) {
                root.querySelectorAll('[data-fb-card="' + cardId + '"]').forEach(function (source) {
                    source.classList.add('is-dragging')
                })
            })
            document.body.classList.add('fb-dragging')
            clickSuppressed = true
            handlers.onStart()
            placeGhost()
            session.frame = window.requestAnimationFrame(tick)
        }

        /** @param {boolean} shouldDrop Drop on the highlighted target. */
        function finishSession(shouldDrop) {
            var finished = session
            var dropTarget = session.target
            window.cancelAnimationFrame(finished.frame)
            setTarget(null)
            finished.ghost.remove()
            root.querySelectorAll('.is-dragging').forEach(function (source) {
                source.classList.remove('is-dragging')
            })
            document.body.classList.remove('fb-dragging')
            session = null
            pending = null

            if (shouldDrop && dropTarget) {
                handlers.onDrop(finished.ids, dropTarget.getAttribute('data-fb-drop'), finished.sourceKey, { x: finished.x, y: finished.y })
            }
        }

        root.addEventListener('pointerdown', function (event) {
            clickSuppressed = false
            if (event.pointerType === 'touch' || event.button !== 0 || session !== null) {
                return
            }
            var cardElement = event.target.closest('[data-fb-card]')
            if (!cardElement || event.target.closest('[data-fb-check], [data-fb-card-action]')) {
                return
            }
            pending = {
                cardId: Number(cardElement.getAttribute('data-fb-card')),
                sourceKey: cardElement.getAttribute('data-fb-source') || '',
                element: cardElement,
                startX: event.clientX,
                startY: event.clientY,
                pointerId: event.pointerId
            }
        })

        document.addEventListener('pointermove', function (event) {
            if (session !== null) {
                if (event.buttons === 0) {
                    finishSession(false)
                    return
                }
                session.x = event.clientX
                session.y = event.clientY
                placeGhost()
                updateTarget()
            } else if (pending !== null && event.pointerId === pending.pointerId
                && Math.hypot(event.clientX - pending.startX, event.clientY - pending.startY) >= DRAG_THRESHOLD_PX) {
                startSession(event)
            }
        })

        document.addEventListener('pointerup', function (event) {
            if (session !== null) {
                session.x = event.clientX
                session.y = event.clientY
                updateTarget()
                finishSession(true)
            }
            pending = null
        })

        document.addEventListener('pointercancel', function () {
            if (session !== null) {
                finishSession(false)
            }
            pending = null
        })

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && session !== null) {
                event.preventDefault()
                event.stopPropagation()
                finishSession(false)
            }
        }, true)

        return {
            /** @returns {boolean} Whether a card is being dragged. */
            isDragging: function () {
                return session !== null
            },

            /** @returns {boolean} True for the click that ends a drag, so it does not open the drawer. */
            isClickSuppressed: function () {
                return clickSuppressed
            }
        }
    }

    namespace.createDragController = createDragController

})(window.ColorClassifierFamilyBoard = window.ColorClassifierFamilyBoard || {})
