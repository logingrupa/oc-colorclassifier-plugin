---
phase: quick-7
plan: "01"
subsystem: color-lab-frontend
tags: [mobile-ux, gesture, bottom-sheet, touch, detail-card]
dependency_graph:
  requires: []
  provides: [swipe-to-dismiss-detail-card, mobile-bottom-sheet-layout]
  affects: [assets/css/color-lab.css, assets/js/color-lab.js]
tech_stack:
  added: []
  patterns: [pointer-events-api, AbortController-cleanup, vaul-drawer-velocity-threshold]
key_files:
  created: []
  modified:
    - assets/css/color-lab.css
    - assets/js/color-lab.js
decisions:
  - "Use Pointer Events API (not Touch Events) for unified mouse+touch handling with pointer capture"
  - "AbortController used to clean up pointermove/pointerup listeners after each gesture ends"
  - "Hero zone has touch-action: none; card body retains default scrolling"
  - "transitionend listener with 250ms setTimeout fallback for dismiss animation cleanup"
  - "Snap-back by clearing inline style.transform so CSS --visible class reasserts translateY(0)"
metrics:
  duration: "~20 minutes"
  completed: "2026-03-26T19:06:58Z"
  tasks_completed: 2
  files_modified: 2
---

# Quick Task 7: Swipe-to-Close Gesture for Mobile Detail Sheet Summary

**One-liner:** Mobile detail card converted to a bottom sheet with pointer-based swipe-to-dismiss using velocity (>0.5 px/ms) and distance (>25% height) thresholds, without affecting desktop right-side slide-in behavior.

## Tasks Completed

| Task | Description | Commit |
|------|-------------|--------|
| 1 | Mobile bottom-sheet CSS and drag handle indicator | 397e0df |
| 2 | Pointer-based swipe-to-dismiss gesture handler | 7d7e1bc |

## What Was Built

### Task 1 — CSS (assets/css/color-lab.css)

Inside the existing `@media (max-width: 768px)` block, added overrides that convert the detail card from a right-side panel to a bottom sheet:

- Repositioned card: `left: 0; bottom: 0;` with `width: 100%; max-width: 100%; max-height: 85vh`
- Y-axis transform: `translateY(100%)` hidden / `translateY(0)` visible
- Rounded top corners `border-radius: 16px 16px 0 0` and upward shadow
- `will-change: transform` for GPU compositing during drag
- `.color-lab__detail-card--dragging` modifier disables CSS transition during active drag
- Drag handle indicator via `.color-lab__detail-hero::before` (36x4px, rgba pill)
- `touch-action: none` on `.color-lab__detail-hero` only — card body remains scrollable
- Close button repositioned to `top: 8px; right: 8px`

### Task 2 — JS (assets/js/color-lab.js)

Added `attachDetailCardSwipeHandler(cardElement)` function called from inside `showDetailCard`'s `requestAnimationFrame` callback:

- `pointerdown` guard: only activates on mobile viewport, only from hero area or card top when not inside a nested scrollable element with content
- `setPointerCapture` for reliable tracking across the entire gesture
- `pointermove` applies `translateY(deltaY)` clamped to non-negative (downward only)
- `pointerup`/`pointercancel` computes velocity (px/ms) and distance, then:
  - Dismiss: animate `translateY(100%)`, attach `transitionend` listener + 250ms timeout fallback, call `deselectColorEntry()`
  - Snap-back: clear `style.transform` so `--visible` class reasserts open position
- `AbortController` added per gesture, aborted on pointerup/cancel for clean teardown
- `isInsideScrollableContent()` helper prevents gesture hijack when user scrolls nested content

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check

### Files exist
- `assets/css/color-lab.css` — modified with bottom-sheet styles
- `assets/js/color-lab.js` — modified with swipe handler

### Commits exist
- 397e0df — CSS task
- 7d7e1bc — JS task

## Self-Check: PASSED
