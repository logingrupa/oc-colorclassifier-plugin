---
quick: 3
title: Add batch size input to Process All configuration
type: quick
autonomous: true
---

# Quick Task 3: Add Batch Size Input to Process All Configuration

## Objective

Replace the hardcoded `BATCH_SIZE = 5` constant in `matrix.js` with a configurable
numeric input field in the toolbar, allowing backend users to tune chunk size before
running Process All or Process New.

## Context

- `assets/js/matrix.js` — hardcoded `var BATCH_SIZE = 5` consumed by `runBatchProcess()`
- `controllers/colorentries/_list_toolbar.htm` — toolbar with Process All / Process New buttons
- `assets/css/matrix.css` — backend plugin styles

## Tasks

### Task 1: Add batch size input to toolbar HTML

Add a compact `<input type="number" id="batchSizeInput">` with label before the
Process All button. Default 5, min 1, max 50.

**Files:** `controllers/colorentries/_list_toolbar.htm`

### Task 2: Add CSS for batch size group

Style the label + input inline in the toolbar to match existing toolbar aesthetics.

**Files:** `assets/css/matrix.css`

### Task 3: Update matrix.js to read from input

- Remove hardcoded `BATCH_SIZE` constant
- Add `getBatchSize()` function that reads `#batchSizeInput`, with fallback and clamping
- Capture batch size at run-start in `runBatchProcess()`
- Lock the input during processing in `setButtonsDisabled()`

**Files:** `assets/js/matrix.js`

## Success Criteria

- Batch size input is visible in the toolbar with a "Batch size" label
- Input accepts values 1–50 (default 5)
- Input is disabled during an active batch run and re-enabled when complete
- `onProcessBatch` AJAX calls send the user-specified `batch_size` value
