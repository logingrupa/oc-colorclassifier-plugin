---
quick: 9
title: Add product name filter with checkbox list
subsystem: color-lab / filter sidebar
tags: [filter, sidebar, product-name, checkbox, ux]
dependency-graph:
  requires: []
  provides: [product-name-filter]
  affects: [color-lab-filter-sidebar, url-routing, mobile-ux]
tech-stack:
  added: []
  patterns: [Set-based filter state, BEM CSS, list displayMode, URL serialization with encodeURIComponent]
key-files:
  modified:
    - assets/js/color-lab.js
    - assets/css/color-lab.css
decisions:
  - Used displayMode:'list' in dimensionConfig to distinguish from pills/color-dots without adding a special-case key
  - Used <ul>/<li> HTML structure via listWrapperTag variable in buildFilterGroupHtml
  - Kept sidebar open on mobile after productNames checkbox changes to support multi-select UX
  - deriveProductNames() reads from state.allEntries (not taxonomy options) since product names are not part of the taxonomy API
  - URL serialization uses encodeURIComponent per name joined by comma to handle names with spaces/special chars
metrics:
  duration: 15m
  completed: 2026-03-27
  tasks: 1
  files: 2
---

# Quick Task 9: Add product name filter with checkbox list

Product name filter with `<ul>/<li>` checkbox items added to the color lab sidebar — lets users narrow the color grid/scatter to one or more specific product lines (e.g. Gelixir, MegaGel).

## What Was Built

A new "Product" filter group at the bottom of the sidebar. Unlike the existing pill-style and color-dot filters, this group renders checkboxes inside `<li>` items for readability when there are many product names. It is collapsed by default to avoid sidebar overflow.

### Changes

**`assets/js/color-lab.js`**

- `FilterState` typedef: added `productNames: Set<string>` property
- `state.filterState`: initialized `productNames: new Set()`
- `deriveProductNames()`: new function — iterates `state.allEntries`, deduplicates and sorts unique `productName` strings
- `buildFilterSidebar()`: added `productNames` entry to `dimensionConfigs` with `displayMode: 'list'`; calls `deriveProductNames()` for values
- `buildFilterGroupHtml()`: added `list` branch — wraps options in `<ul>` instead of `<div>`; delegates to `buildListItemOptionHtml()`
- `buildListItemOptionHtml()`: new function — renders `<li class="color-lab__filter-list-item">` containing a `<label>` with a visible checkbox and text span
- `applyFilters()`: added `productNames` Set check — excludes entries whose `productName` is not in the active set
- `countActiveFilters()`: added `+ state.filterState.productNames.size`
- `clearAllFilters()`: added `state.filterState.productNames.clear()`; broadened checkbox selector from `.color-lab__filter-option input[type="checkbox"]` to `input[data-dimension]` to cover list items
- `parseUrlParameters()`: added `productNames` field decoded from `?productNames=` URL param
- `updateUrlParameters()`: serializes `productNames` Set as comma-separated `encodeURIComponent` values
- `restoreStateFromUrl()`: restores `productNames` from URL state; broadened checkbox selector to `input[data-dimension]`; added `productNames` restore branch
- `handleFilterChange()`: added `dimension !== 'productNames'` guard — keeps mobile sidebar open for multi-select

**`assets/css/color-lab.css`**

- `.color-lab__filter-group--productNames .color-lab__filter-group-options`: block display, no list-style
- `.color-lab__filter-list-item`: strips default list-item bullet
- `.color-lab__filter-list-label`: flex row, gap, hover color transition
- `.color-lab__filter-list-checkbox`: sized, accent-color, no margin
- `.color-lab__filter-list-text`: line-height for readability
- `.color-lab__filter-list-label:has(:checked)`: accent color + bold weight when checked

## Deviations from Plan

None — plan file was absent (directory created but empty). Implemented based on task name ("add product name filter with checkbox li") and full codebase context.

## Self-Check: PASSED

- `assets/js/color-lab.js` — exists, syntax-checked with `node --check`
- `assets/css/color-lab.css` — exists, modified
- Commit `6962eb0` — confirmed in git log
