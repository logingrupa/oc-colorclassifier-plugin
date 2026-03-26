---
phase: quick-8
plan: 01
subsystem: color-lab-ui
tags: [css, js, ux, spinner, loading-state]
dependency_graph:
  requires: []
  provides: [right-aligned-product-link, loading-spinner-on-click]
  affects: [assets/css/color-lab.css, assets/js/color-lab.js]
tech_stack:
  added: []
  patterns: [css-flex-column-layout, css-keyframe-animation, js-click-handler-dom-swap]
key_files:
  modified:
    - assets/css/color-lab.css
    - assets/js/color-lab.js
decisions:
  - Spinner element injected on click via innerHTML swap rather than hidden in template — simpler, no hidden DOM state
  - .color-lab__spinner is a standalone class (not nested under --loading) so any future use of the spinner is reusable
  - pointer-events: none on --loading class prevents double-click without JS guard logic
metrics:
  duration: ~10min
  completed: 2026-03-26
  tasks_completed: 2
  files_modified: 2
---

# Phase quick-8 Plan 01: View Product Link — Right-align and Loading Spinner Summary

**One-liner:** Right-aligned "View Product" link with CSS flex column + inline spinner swap on click using @keyframes color-lab-spin.

## What Was Built

The "View Product" link in the detail card now sits flush-right in the card body. Clicking it immediately replaces the link content with "Loading... [spinner]" and blocks further clicks while the browser navigates to the product page (~4s load time).

## Tasks Completed

| # | Task | Commit | Files |
|---|------|--------|-------|
| 1 | Right-align link and add spinner CSS | 4491d51 | assets/css/color-lab.css |
| 2 | Add spinner click handler and update link HTML | a2e7722 | assets/js/color-lab.js |

## Implementation Details

### CSS (Task 1)

- `.color-lab__detail-body` now has `display: flex; flex-direction: column` — children remain block-level, layout unchanged except the link can be pushed right
- `.color-lab__detail-product-link` gains `align-self: flex-end; margin-top: auto` — sits flush-right and sticks to the bottom when the body has extra height
- `@keyframes color-lab-spin` — simple 360deg rotate, 0.7s linear infinite
- `.color-lab__detail-product-link--loading` — `pointer-events: none; opacity: 0.85`
- `.color-lab__spinner` — standalone 14x14px circle border spinner, visible whenever rendered

### JS (Task 2)

- `attachProductLinkLoadingHandler(cardElement)` — queries `.color-lab__detail-product-link` within the card, attaches a click listener
- On click: adds `--loading` class and sets `link.innerHTML = 'Loading&hellip; <span class="color-lab__spinner"></span>'`
- No `preventDefault()` — browser navigation proceeds normally
- Called in the `requestAnimationFrame` callback alongside `attachDetailCardSwipeHandler`

## Deviations from Plan

### Auto-fixed Issues

None — plan executed exactly as written. The plan's own "simplify" note (remove hidden initial spinner, use innerHTML swap on click) was already incorporated as the canonical approach.

## Self-Check

### Files exist:
- assets/css/color-lab.css: modified
- assets/js/color-lab.js: modified

### Commits exist:
- 4491d51: feat(quick-8): right-align product link and add spinner CSS
- a2e7722: feat(quick-8): add product link loading spinner click handler

## Self-Check: PASSED
