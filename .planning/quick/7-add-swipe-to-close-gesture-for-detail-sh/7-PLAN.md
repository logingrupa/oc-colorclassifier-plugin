---
phase: quick-7
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - assets/css/color-lab.css
  - assets/js/color-lab.js
autonomous: true
requirements: [SWIPE-01]

must_haves:
  truths:
    - "On mobile, the detail card slides up from the bottom of the screen"
    - "User can swipe the detail card downward to dismiss it"
    - "A quick flick (high velocity) dismisses even with small distance"
    - "A slow drag past 25% of card height dismisses"
    - "A slow drag less than 25% snaps the card back to open position"
    - "On desktop the card still slides in from the right with no swipe behavior"
    - "Existing close button and Escape key still work on all viewports"
  artifacts:
    - path: "assets/css/color-lab.css"
      provides: "Mobile bottom-sheet positioning and drag handle indicator"
      contains: "detail-card.*bottom"
    - path: "assets/js/color-lab.js"
      provides: "Touch/pointer swipe gesture handler for detail card dismiss"
      contains: "pointerdown|touchstart"
  key_links:
    - from: "swipe gesture handler"
      to: "deselectColorEntry()"
      via: "velocity/distance threshold check on pointerup"
      pattern: "deselectColorEntry"
    - from: "pointerdown handler"
      to: "CSS transform on detail card"
      via: "pointermove translates card during drag"
      pattern: "translate3d|translateY"
---

<objective>
Add a swipe-to-close gesture for the detail card on mobile devices, following the pattern
used by shadcn/ui's Sheet component (vaul drawer library).

On mobile (<=768px), the detail card should behave as a bottom sheet that slides up from
the bottom edge. The user can drag it downward to dismiss. On desktop, the existing
right-side slide-in behavior is unchanged.

Purpose: Mobile UX improvement — tapping a small close button on a full-height panel is
awkward; swiping down is the natural mobile gesture for dismissing bottom sheets.

Output: Updated CSS and JS in the existing color-lab files.
</objective>

<execution_context>
@C:/Users/rolan/.claude/get-shit-done/workflows/execute-plan.md
@C:/Users/rolan/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@assets/js/color-lab.js
@assets/css/color-lab.css

<interfaces>
<!-- Key functions and patterns the executor needs -->

From assets/js/color-lab.js:
```javascript
// State object tracks selected entry
state.selectedEntryId  // null | number

// Show/hide lifecycle
function showDetailCard(colorEntry) { ... }  // Creates DOM, appends to body, adds --visible
function hideDetailCard() { ... }            // Removes --visible, setTimeout remove 200ms
function deselectColorEntry() { ... }        // Nulls state, updates URL, calls hideDetailCard

// Close listeners (click on .detail-close, Escape key)
function attachDetailCardCloseListeners() { ... }

// Mobile check utility (already exists)
function isMobileViewport() { return window.matchMedia('(max-width: 768px)').matches; }
```

From assets/css/color-lab.css:
```css
/* Desktop: slides from right */
.color-lab__detail-card {
    position: fixed; top: 0; right: 0;
    width: 460px; max-width: 92vw; height: 100vh;
    transform: translateX(100%);
    transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 1100;
    overflow-y: auto;
}
.color-lab__detail-card--visible { transform: translateX(0); }

/* Mobile breakpoint already defined at max-width: 768px */
```
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add mobile bottom-sheet CSS and drag handle indicator</name>
  <files>assets/css/color-lab.css</files>
  <action>
Inside the existing `@media (max-width: 768px)` block, add styles that convert the
detail card from a right-side panel to a bottom sheet:

1. Override `.color-lab__detail-card` positioning for mobile:
   - Change from right-side slide to bottom-up slide:
     `top: auto; right: auto; left: 0; bottom: 0;`
   - Full width: `width: 100%;` and `max-width: 100%;`
   - Height capped: `max-height: 85vh;` (leave some screen visible behind)
   - Transform changes to Y axis: `transform: translateY(100%);`
   - Transition stays on transform: `transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);`
   - Round top corners: `border-radius: 16px 16px 0 0;`
   - Box shadow upward: `box-shadow: 0 -4px 24px rgba(0, 0, 0, 0.15);`
   - `will-change: transform;` for GPU compositing during drag
   - Disable transition during drag via a modifier class:
     `.color-lab__detail-card--dragging { transition: none !important; }`

2. Override `.color-lab__detail-card--visible` for mobile:
   - `transform: translateY(0);`

3. Add a drag handle indicator on `.color-lab__detail-hero::before`:
   - `content: ''; display: block; width: 36px; height: 4px;`
   - `background: rgba(0,0,0,0.25); border-radius: 2px;`
   - `margin: 0 auto 0.75rem;`
   - This gives the visual cue that the card is draggable.

4. Adjust `.color-lab__detail-close` on mobile — move it to `top: 8px; right: 8px;`
   so it does not overlap the drag handle area.

Do NOT add `touch-action: none` globally on the card — only the hero (drag handle)
area should suppress default touch scrolling. The card body must remain scrollable.
Add `touch-action: none;` specifically to `.color-lab__detail-hero` on mobile.
  </action>
  <verify>
    <automated>grep -c "translateY" assets/css/color-lab.css | grep -q "[1-9]" && grep -c "detail-card--dragging" assets/css/color-lab.css | grep -q "[1-9]" && echo "PASS" || echo "FAIL"</automated>
  </verify>
  <done>
On mobile, the detail card visually appears as a bottom sheet with rounded top corners
and a drag handle indicator. On desktop, appearance is unchanged (right-side panel).
  </done>
</task>

<task type="auto">
  <name>Task 2: Implement pointer-based swipe-to-dismiss gesture</name>
  <files>assets/js/color-lab.js</files>
  <action>
Create a new function `attachDetailCardSwipeHandler()` and call it from within
`attachDetailCardCloseListeners()`. This function adds pointer event listeners
to the detail card for mobile swipe-to-dismiss, following the vaul/shadcn pattern.

**Algorithm (based on vaul drawer library):**

The swipe handler should be a self-contained function that:

1. Listens for `pointerdown` on the detail card element.
   - Only activate if `isMobileViewport()` returns true.
   - Only activate if the `pointerdown` target is within `.color-lab__detail-hero`
     (the drag handle zone) OR if the card's scrollTop is 0 and the pointer target
     is NOT inside a scrollable area that has scrollable content. This prevents
     hijacking scroll inside the card body.
   - Record `startY = event.clientY` and `startTime = Date.now()`.
   - Call `event.target.setPointerCapture(event.pointerId)`.
   - Add `color-lab__detail-card--dragging` class to disable CSS transition.

2. On `pointermove`:
   - Calculate `deltaY = event.clientY - startY`.
   - If `deltaY < 0`, clamp to 0 (don't allow dragging upward past open position).
   - Apply `card.style.transform = 'translateY(' + deltaY + 'px)'`.
   - This gives real-time visual feedback during the drag.

3. On `pointerup` / `pointercancel`:
   - Remove `color-lab__detail-card--dragging` class.
   - Calculate final `deltaY` and elapsed time.
   - Calculate `velocity = deltaY / (Date.now() - startTime)` (px/ms).
   - **Dismiss if:** velocity > 0.5 (fast flick) OR deltaY > (cardHeight * 0.25).
   - **Snap back if:** neither threshold met.
   - On dismiss: call `deselectColorEntry()`. The existing `hideDetailCard()` handles
     removing `--visible` class and cleanup. But first, set the card's inline
     `transform` to `translateY(100%)` and restore the transition so it animates
     out smoothly, then call `deselectColorEntry()` after the transition ends
     (use a `transitionend` listener or a 250ms setTimeout fallback).
   - On snap-back: remove inline `style.transform` (CSS `--visible` class will
     reassert `translateY(0)`).
   - Release pointer capture.
   - Clean up: remove the move/up listeners (use AbortController or named functions
     added/removed explicitly).

**Integration point:** The swipe handler must be re-attached each time a new detail
card is created, since `showDetailCard()` destroys and recreates the DOM element.
The cleanest approach: call `attachDetailCardSwipeHandler()` at the end of
`showDetailCard()`, right after the `requestAnimationFrame` that adds `--visible`.
Inside the rAF callback, after adding `--visible`, call the swipe setup.

**Important constraints:**
- Use pointer events (not touch events) for unified mouse+touch handling.
- Use JSDoc annotations for all new functions and variables per project conventions.
- Keep the handler self-contained: all state (startY, startTime, isDragging) scoped
  inside the function via closure, not on the module-level `state` object.
- Do NOT add swipe handling on desktop — the `isMobileViewport()` guard at pointerdown
  is sufficient.
- Variable naming must follow project conventions: descriptive names, no abbreviations.
  </action>
  <verify>
    <automated>grep -c "pointerdown" assets/js/color-lab.js | grep -q "[1-9]" && grep -c "velocity" assets/js/color-lab.js | grep -q "[1-9]" && grep -c "attachDetailCardSwipeHandler\|swipe" assets/js/color-lab.js | grep -q "[1-9]" && echo "PASS" || echo "FAIL"</automated>
  </verify>
  <done>
On mobile, swiping down on the detail card hero area dismisses the card. A fast flick
(velocity > 0.5 px/ms) dismisses regardless of distance. A slow drag past 25% of card
height dismisses. Anything less snaps back. Desktop behavior is completely unchanged.
The existing close button and Escape key continue to work on all viewports.
  </done>
</task>

</tasks>

<verification>
1. Open color lab on a mobile viewport (or Chrome DevTools mobile emulation, e.g. iPhone 14).
2. Tap a color swatch to open the detail card.
3. Verify the card slides up from the bottom with rounded top corners and a drag handle bar.
4. Place finger on the hero/handle area and drag downward slowly past ~25% — card should dismiss.
5. Open again, drag down slightly and release — card should snap back.
6. Open again, do a quick flick downward — card should dismiss even with small distance.
7. Open again, scroll the card body content — body should scroll normally without triggering dismiss.
8. Tap the X close button — should still close.
9. Press Escape — should still close.
10. Resize to desktop width — card should slide in from the right as before, no drag handle visible.
</verification>

<success_criteria>
- Mobile detail card appears as a bottom sheet (slides up from bottom edge)
- Drag handle indicator visible at top of card on mobile
- Swipe down gesture dismisses the card (velocity > 0.5 OR distance > 25%)
- Small drags snap back to open position
- Card body remains scrollable (swipe only from hero/handle area or when scrolled to top)
- Desktop behavior completely unchanged (right-side slide-in, no swipe)
- Existing close button and Escape key work on all viewports
</success_criteria>

<output>
After completion, create `.planning/quick/7-add-swipe-to-close-gesture-for-detail-sh/7-SUMMARY.md`
</output>
