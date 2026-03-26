# Quick Task 4: Batch processing popup modal

**Description:** Move batch processing UI into OctoberCMS popup modal with config, live progress, ETA, and close warning.

## Tasks

### Task 1: Create popup partial and controller handler
- **Files:** `_batch_popup.htm`, `ColorEntries.php`
- **Action:** New partial with 3-state UI (config → processing → complete). Controller handler `onLoadBatchPopup()` prepares offer list and returns partial with total count.

### Task 2: Clean toolbar and rewrite JS
- **Files:** `_list_toolbar.htm`, `matrix.js`, `matrix.css`
- **Action:** Remove inline batch-size input and progress bar from toolbar. Buttons use `data-control="popup"` to open modal. JS uses event delegation for popup-driven batch loop with ETA calculation and close prevention.
