---
quick: 3
title: Add batch size input to Process All configuration
completed: 2026-03-26
commit: c722603
duration: ~15 minutes
tasks_completed: 3
tasks_total: 3
files_modified: 3
files_created: 0
key_files:
  modified:
    - controllers/colorentries/_list_toolbar.htm
    - assets/css/matrix.css
    - assets/js/matrix.js
decisions:
  - Read batch size from input at run-start (not per-chunk) so size stays consistent throughout a run
  - Clamp value to 1–50 range in getBatchSize() fallback rather than trusting HTML min/max alone
  - Lock input alongside buttons in setButtonsDisabled() to prevent mid-run changes
---

# Quick Task 3: Add Batch Size Input to Process All Configuration

## One-liner

Replaced hardcoded `BATCH_SIZE = 5` in matrix.js with a toolbar numeric input (#batchSizeInput, default 5, range 1–50) that controls chunk size for both Process All and Process New runs.

## What Was Done

### Task 1 — Toolbar HTML (`_list_toolbar.htm`)

Added a `.batch-size-group` div containing a `<label>` and `<input type="number" id="batchSizeInput">` before the Process All button. Default value 5, min 1, max 50, step 1.

### Task 2 — CSS (`matrix.css`)

Added three new rules:
- `.batch-size-group` — inline-flex container with gap, vertical-align middle
- `.batch-size-label` — 12px muted label, no margin, normal weight
- `.batch-size-input` — 60px wide, 32px tall, centered text

### Task 3 — JavaScript (`matrix.js`)

- Removed the `var BATCH_SIZE = 5` module-level constant
- Added `getBatchSize()` function: reads `#batchSizeInput.value`, parses as integer, returns clamped value (1–50), falls back to 5 if element missing or value invalid
- `runBatchProcess()` now calls `getBatchSize()` once at the top and stores result in `batchSize`; uses that local variable for all `batch_size` POST data and `offset += batchSize` increments
- `setButtonsDisabled()` extended to also set `batchSizeInput.disabled`, preventing mid-run changes

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check

- [x] `controllers/colorentries/_list_toolbar.htm` — `#batchSizeInput` present
- [x] `assets/css/matrix.css` — `.batch-size-group`, `.batch-size-label`, `.batch-size-input` present
- [x] `assets/js/matrix.js` — `getBatchSize()` present, `BATCH_SIZE` constant removed
- [x] Commit c722603 exists
