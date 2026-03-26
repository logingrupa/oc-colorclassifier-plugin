/**
 * Color Lab — OKLCH Color Space Explorer
 *
 * Renders a dual-view interactive color visualization:
 *   View A: 2D taxonomy matrix (color family columns × depth rows) with product swatches.
 *   View B: 3D Plotly scatter plot in OKLCH cylindrical coordinates.
 *
 * Features:
 *   - URL routing: ?view=grid|scatter&color={id} — shareable deep links
 *   - Detail card: click a dot/swatch to open a rich info panel
 *   - Sidebar taxonomy filters control both views simultaneously
 *
 * All data comes from window.__COLOR_LAB_DATA__ and window.__COLOR_LAB_TAXONOMY__.
 *
 * @module color-lab
 */

'use strict';

// ─── JSDoc Type Definitions ───────────────────────────────────────────────────

/**
 * @typedef {Object} OklchValues
 * @property {number} lightness - L component (0–100)
 * @property {number} chroma    - C component (0–0.4+)
 * @property {number} hue       - H component (0–360 degrees)
 */

/**
 * @typedef {Object} ColorTaxonomy
 * @property {string|null} family
 * @property {string|null} undertone
 * @property {string|null} depth
 * @property {string|null} saturation
 * @property {string|null} finish
 * @property {string|null} opacity
 */

/**
 * @typedef {Object} ColorEntry
 * @property {number}         id
 * @property {string}         productName
 * @property {string}         variationName
 * @property {string}         hexColor
 * @property {string|null}    colorName
 * @property {OklchValues}    oklch
 * @property {string[]}       paletteColors
 * @property {ColorTaxonomy}  taxonomy
 * @property {number}         confidenceScore
 * @property {string}         imageUrl
 * @property {string|null}    croppedImageData
 */

/**
 * @typedef {Object} TaxonomyOptions
 * @property {string[]} families
 * @property {string[]} undertones
 * @property {string[]} depths
 * @property {string[]} saturations
 * @property {string[]} finishes
 * @property {string[]} opacities
 */

/**
 * @typedef {Object} FilterState
 * @property {Set<string>} families
 * @property {string|null} undertone
 * @property {Set<string>} depths
 * @property {Set<string>} saturations
 * @property {Set<string>} finishes
 * @property {Set<string>} opacities
 */

/** @typedef {'grid'|'scatter'} ViewName */

// ─── Module State ─────────────────────────────────────────────────────────────

/** @type {{allEntries: ColorEntry[], filteredEntries: ColorEntry[], filterState: FilterState, activeView: ViewName, plotlyInitialized: boolean, selectedEntryId: number|null}} */
const state = {
    allEntries: [],
    filteredEntries: [],
    filterState: {
        families: new Set(),
        undertone: null,
        depths: new Set(),
        saturations: new Set(),
        finishes: new Set(),
        opacities: new Set(),
    },
    activeView: 'grid',
    plotlyInitialized: false,
    selectedEntryId: null,
};

/** @type {Map<number, ColorEntry>} */
let entriesById = new Map();

// ─── URL Routing ──────────────────────────────────────────────────────────────

/**
 * Reads view and color parameters from the current URL search params.
 *
 * @returns {{view: ViewName|null, colorId: number|null}}
 */
function parseUrlParameters() {
    var urlParams = new URLSearchParams(window.location.search);
    return {
        view: (urlParams.get('view') === 'grid' || urlParams.get('view') === 'scatter') ? urlParams.get('view') : null,
        colorId: urlParams.get('color') ? parseInt(urlParams.get('color'), 10) : null,
        families: urlParams.get('families') ? urlParams.get('families').split(',') : [],
        undertone: urlParams.get('undertone') || null,
        depths: urlParams.get('depths') ? urlParams.get('depths').split(',') : [],
        saturations: urlParams.get('saturations') ? urlParams.get('saturations').split(',') : [],
        finishes: urlParams.get('finishes') ? urlParams.get('finishes').split(',') : [],
        opacities: urlParams.get('opacities') ? urlParams.get('opacities').split(',') : [],
    };
}

/**
 * Syncs current view, selected color, and all active filters to the URL.
 *
 * @param {ViewName} viewName
 * @param {number|null} colorEntryId
 * @returns {void}
 */
function updateUrlParameters(viewName, colorEntryId) {
    var urlParams = new URLSearchParams();
    urlParams.set('view', viewName);

    if (colorEntryId !== null) {
        urlParams.set('color', String(colorEntryId));
    }

    if (state.filterState.families.size > 0) {
        urlParams.set('families', Array.from(state.filterState.families).join(','));
    }
    if (state.filterState.undertone) {
        urlParams.set('undertone', state.filterState.undertone);
    }
    if (state.filterState.depths.size > 0) {
        urlParams.set('depths', Array.from(state.filterState.depths).join(','));
    }
    if (state.filterState.saturations.size > 0) {
        urlParams.set('saturations', Array.from(state.filterState.saturations).join(','));
    }
    if (state.filterState.finishes.size > 0) {
        urlParams.set('finishes', Array.from(state.filterState.finishes).join(','));
    }
    if (state.filterState.opacities.size > 0) {
        urlParams.set('opacities', Array.from(state.filterState.opacities).join(','));
    }

    var newUrl = window.location.pathname + '?' + urlParams.toString();
    window.history.replaceState(null, '', newUrl);
}

/**
 * Restores view, selected color, and filter state from URL parameters.
 *
 * @returns {void}
 */
function restoreStateFromUrl() {
    var urlState = parseUrlParameters();

    if (urlState.view) {
        state.activeView = urlState.view;
        switchToView(state.activeView);
    }

    // Restore filter selections
    urlState.families.forEach(function(family) { state.filterState.families.add(family); });
    state.filterState.undertone = urlState.undertone;
    urlState.depths.forEach(function(depth) { state.filterState.depths.add(depth); });
    urlState.saturations.forEach(function(saturation) { state.filterState.saturations.add(saturation); });
    urlState.finishes.forEach(function(finish) { state.filterState.finishes.add(finish); });
    urlState.opacities.forEach(function(opacity) { state.filterState.opacities.add(opacity); });

    // Check the corresponding checkboxes in the DOM
    if (countActiveFilters() > 0) {
        document.querySelectorAll('.color-lab__filter-option input[type="checkbox"]').forEach(function(inputElement) {
            var checkbox = /** @type {HTMLInputElement} */ (inputElement);
            var dimension = checkbox.dataset.dimension;
            var value = checkbox.dataset.value;

            if (dimension === 'families' && state.filterState.families.has(value)) { checkbox.checked = true; }
            if (dimension === 'undertones' && state.filterState.undertone === value) { checkbox.checked = true; }
            if (dimension === 'depths' && state.filterState.depths.has(value)) { checkbox.checked = true; }
            if (dimension === 'saturations' && state.filterState.saturations.has(value)) { checkbox.checked = true; }
            if (dimension === 'finishes' && state.filterState.finishes.has(value)) { checkbox.checked = true; }
            if (dimension === 'opacities' && state.filterState.opacities.has(value)) { checkbox.checked = true; }
        });

        applyFilters();
        updateFilterBadge();
    }

    if (urlState.colorId && entriesById.has(urlState.colorId)) {
        state.selectedEntryId = urlState.colorId;
        var selectedEntry = entriesById.get(urlState.colorId);
        showDetailCard(selectedEntry);

        var lightboxParam = new URLSearchParams(window.location.search).get('lightbox');
        if (lightboxParam === '1' && selectedEntry.imageUrl) {
            var overlayColor = isPngImage(selectedEntry.imageUrl) ? selectedEntry.hexColor : null;
            showLightbox(selectedEntry.imageUrl, overlayColor);
        }
    }
}

// ─── Initialization ───────────────────────────────────────────────────────────

/**
 * Main entry point. Reads window globals, wires up event listeners,
 * restores URL state, and performs the initial render.
 *
 * @returns {void}
 */
function initializeColorLab() {
    const allEntries = /** @type {ColorEntry[]} */ (window.__COLOR_LAB_DATA__ || []);
    const taxonomyOptions = /** @type {TaxonomyOptions} */ (window.__COLOR_LAB_TAXONOMY__ || {});

    state.allEntries = allEntries;

    entriesById = new Map(allEntries.map(function(colorEntry) {
        return [colorEntry.id, colorEntry];
    }));

    buildFilterSidebar(taxonomyOptions);
    applyFilters();

    attachViewToggleListeners();
    attachClearButtonListener();
    attachGridInteractionListeners();
    attachDetailCardCloseListeners();
    attachSidebarToggleListener();

    if (isMobileViewport()) {
        var bodyElement = document.querySelector('.color-lab__body');
        if (bodyElement) {
            bodyElement.classList.remove('color-lab__body--sidebar-collapsed');
        }
    }

    attachLightboxListeners();

    restoreStateFromUrl();
    renderActiveView();
    updateUrlParameters(state.activeView, state.selectedEntryId);
}

// ─── Filter Sidebar ───────────────────────────────────────────────────────────

/**
 * Builds all filter groups in the sidebar from taxonomy dimension options.
 *
 * @param {TaxonomyOptions} taxonomyOptions - Taxonomy dimension arrays.
 * @returns {void}
 */
function buildFilterSidebar(taxonomyOptions) {
    const filterGroupsContainer = document.querySelector('.color-lab__filter-groups');
    if (!filterGroupsContainer) {
        return;
    }

    const dimensionConfigs = [
        { key: 'families',    label: 'Color Family', inputType: 'checkbox', values: taxonomyOptions.families,    startExpanded: true,  description: 'Base hue group — tap a color to filter', displayMode: 'color-dots' },
        { key: 'undertones',  label: 'Undertone',    inputType: 'checkbox', values: taxonomyOptions.undertones,  startExpanded: true,  description: 'Warm (yellow-based), Cool (blue-based), or Neutral', displayMode: 'pills' },
        { key: 'depths',      label: 'Depth',        inputType: 'checkbox', values: taxonomyOptions.depths,      startExpanded: true,  description: 'How light or dark the polish appears on the nail', displayMode: 'pills' },
        { key: 'saturations', label: 'Saturation',   inputType: 'checkbox', values: taxonomyOptions.saturations, startExpanded: false, description: 'Color intensity — from muted pastels to neons', displayMode: 'pills' },
        { key: 'finishes',    label: 'Finish',       inputType: 'checkbox', values: taxonomyOptions.finishes,    startExpanded: false, description: 'Surface effect — must be set manually', displayMode: 'pills' },
        { key: 'opacities',   label: 'Opacity',      inputType: 'checkbox', values: taxonomyOptions.opacities,   startExpanded: false, description: 'How much natural nail shows through', displayMode: 'pills' },
    ];

    filterGroupsContainer.innerHTML = dimensionConfigs.map(function(dimensionConfig) {
        return buildFilterGroupHtml(dimensionConfig);
    }).join('');

    filterGroupsContainer.querySelectorAll('input[data-dimension]').forEach(function(inputElement) {
        inputElement.addEventListener('change', handleFilterChange);
    });

    filterGroupsContainer.querySelectorAll('.color-lab__filter-group-title').forEach(function(titleButton) {
        titleButton.addEventListener('click', function() {
            var filterGroup = titleButton.closest('.color-lab__filter-group');
            if (filterGroup) {
                filterGroup.classList.toggle('color-lab__filter-group--collapsed');
            }
        });
    });
}

/** @type {Object.<string, string>} Representative hex colors for each color family */
var FAMILY_DOT_COLORS = {
    'Red': '#D32F2F', 'Orange': '#F57C00', 'Coral': '#FF7043', 'Peach': '#FFAB91',
    'Yellow': '#FDD835', 'Gold': '#FFB300', 'Green': '#43A047', 'Teal': '#00897B',
    'Cyan': '#00ACC1', 'Blue': '#1E88E5', 'Navy': '#283593', 'Indigo': '#3949AB',
    'Violet': '#7E57C2', 'Purple': '#8E24AA', 'Magenta': '#D81B60', 'Pink': '#EC407A',
    'Rose': '#F48FB1', 'Brown': '#6D4C41', 'Beige': '#D7CCC8', 'Nude': '#EFEBE9',
    'Grey': '#9E9E9E', 'White': '#FAFAFA', 'Black': '#212121',
};

/**
 * @param {Object} dimensionConfig
 * @returns {string}
 */
function buildFilterGroupHtml(dimensionConfig) {
    var collapsedClass = dimensionConfig.startExpanded ? '' : ' color-lab__filter-group--collapsed';
    var groupModifier = dimensionConfig.displayMode === 'color-dots' ? ' color-lab__filter-group--families' : '';
    var optionName = 'filter-' + dimensionConfig.key;

    var optionsHtml = dimensionConfig.values.map(function(optionValue) {
        if (dimensionConfig.displayMode === 'color-dots') {
            return buildColorDotOptionHtml(optionName, dimensionConfig.key, optionValue);
        }
        return buildPillOptionHtml(optionName, dimensionConfig.key, optionValue);
    }).join('');

    var descriptionHtml = dimensionConfig.description
        ? '<span class="color-lab__filter-group-description">' + dimensionConfig.description + '</span>'
        : '';

    var dimensionModifier = ' color-lab__filter-group--' + dimensionConfig.key;

    return '<div class="color-lab__filter-group' + collapsedClass + groupModifier + dimensionModifier + '">'
        + '<button type="button" class="color-lab__filter-group-title">' + dimensionConfig.label + descriptionHtml + '</button>'
        + '<div class="color-lab__filter-group-options-wrap">'
        + '<div class="color-lab__filter-group-options">' + optionsHtml + '</div>'
        + '</div>'
        + '</div>';
}

/**
 * Builds a colored dot filter option for color families.
 *
 * @param {string} name
 * @param {string} dimension
 * @param {string} value
 * @returns {string}
 */
function buildColorDotOptionHtml(name, dimension, value) {
    var dotColor = FAMILY_DOT_COLORS[value] || '#999';
    return '<label class="color-lab__filter-option color-lab__filter-option--color-dot"'
        + ' style="background:' + dotColor + ';--color-lab-filter-dot-color:' + dotColor + ';"'
        + ' title="' + value + '">'
        + '<input type="checkbox" name="' + name + '"'
        + ' data-dimension="' + dimension + '" data-value="' + value + '">'
        + '</label>';
}

/** @type {Object.<string, Object.<string, string>>} Visual indicator colors per dimension */
var PILL_INDICATOR_COLORS = {
    undertones: {
        'Warm': 'linear-gradient(135deg, #F4A460, #DAA520)',
        'Cool': 'linear-gradient(135deg, #87CEEB, #6A5ACD)',
        'Neutral': 'linear-gradient(135deg, #C0B8A8, #A89F91)',
    },
    depths: {
        'Very Light': '#F5F0EB',
        'Light': '#DDD0C4',
        'Medium': '#A0876C',
        'Dark': '#5C4033',
        'Very Dark': '#2C1810',
    },
    saturations: {
        'Muted': '#B8A999',
        'Soft': '#D4A5A5',
        'Medium': '#E06666',
        'Vivid': '#FF0033',
        'Neon': '#FF2BF1',
    },
    finishes: {
        'Cream': '#FFF5E6',
        'Shimmer': 'linear-gradient(135deg, #E8D5B7, #F5E6CC, #C9B896)',
        'Glitter': 'linear-gradient(135deg, #FFD700, #FF69B4, #00CED1)',
        'Metallic': 'linear-gradient(135deg, #C0C0C0, #E8E8E8, #A0A0A0)',
        'Chrome': 'linear-gradient(135deg, #D4D4D4, #F8F8F8, #B0B0B0)',
        'Matte': '#8B7D6B',
        'Glossy': 'linear-gradient(135deg, #FFFFFF, #E0E0E0)',
        'Foil': 'linear-gradient(135deg, #FFD700, #DAA520)',
        'Holographic': 'linear-gradient(135deg, #FF6B6B, #FFD93D, #6BCB77, #4D96FF, #9B59B6)',
        'Cat Eye': 'linear-gradient(135deg, #2C3E50, #4CA1AF)',
    },
    opacities: {
        'Sheer': 'rgba(200, 180, 170, 0.3)',
        'Semi-Sheer': 'rgba(200, 180, 170, 0.5)',
        'Semi-Opaque': 'rgba(200, 180, 170, 0.75)',
        'Opaque': '#C8B4AA',
    },
};

/**
 * Builds a pill-style checkbox filter option with a color indicator dot.
 *
 * @param {string} name
 * @param {string} dimension
 * @param {string} value
 * @returns {string}
 */
/** @type {Object.<string, string>} CSS modifier classes for opacity pills */
var OPACITY_PILL_CLASSES = {
    'Sheer': 'color-lab__filter-option--opacity-sheer',
    'Semi-Sheer': 'color-lab__filter-option--opacity-semi-sheer',
    'Semi-Opaque': 'color-lab__filter-option--opacity-semi-opaque',
    'Opaque': 'color-lab__filter-option--opacity-opaque',
};

function buildPillOptionHtml(name, dimension, value) {
    var indicatorColors = PILL_INDICATOR_COLORS[dimension] || {};
    var indicatorBg = indicatorColors[value] || '';
    var dotHtml = '';

    if (indicatorBg) {
        dotHtml = '<span class="color-lab__filter-dot" style="background:' + indicatorBg + ';"></span>';
    }

    var extraClass = (dimension === 'opacities' && OPACITY_PILL_CLASSES[value]) ? ' ' + OPACITY_PILL_CLASSES[value] : '';

    return '<label class="color-lab__filter-option' + extraClass + '">'
        + '<input type="checkbox" name="' + name + '"'
        + ' data-dimension="' + dimension + '" data-value="' + value + '">'
        + dotHtml + value
        + '</label>';
}

// ─── Filter Logic ─────────────────────────────────────────────────────────────

/**
 * @param {Event} changeEvent
 * @returns {void}
 */
function handleFilterChange(changeEvent) {
    var inputElement = /** @type {HTMLInputElement} */ (changeEvent.target);
    var dimension = inputElement.dataset.dimension;
    var value = inputElement.dataset.value;

    var filterStateKey = dimension === 'undertones' ? 'undertones_set' : dimension;

    if (dimension === 'undertones') {
        if (inputElement.checked) {
            state.filterState.undertone = value;
        } else {
            state.filterState.undertone = null;
        }
    } else {
        var targetSet = state.filterState[dimension];
        if (targetSet instanceof Set) {
            inputElement.checked ? targetSet.add(value) : targetSet.delete(value);
        }
    }

    applyFilters();
    renderActiveView();
    updateFilterBadge();
    updateUrlParameters(state.activeView, state.selectedEntryId);

    if (isMobileViewport()) {
        closeMobileSidebar();
    }
}

/**
 * Filters allEntries into filteredEntries based on filterState.
 * Entries with null taxonomy values pass through (unclassified entries are always included).
 *
 * @returns {void}
 */
function applyFilters() {
    state.filteredEntries = state.allEntries.filter(function(colorEntry) {
        var taxonomy = colorEntry.taxonomy || {};

        if (state.filterState.families.size > 0) {
            var matchesPrimary = taxonomy.family && state.filterState.families.has(taxonomy.family);
            var matchesSecondary = taxonomy.secondary_family && state.filterState.families.has(taxonomy.secondary_family);
            if (taxonomy.family && !matchesPrimary && !matchesSecondary) {
                return false;
            }
        }
        if (state.filterState.undertone !== null && taxonomy.undertone && taxonomy.undertone !== state.filterState.undertone) {
            return false;
        }
        if (state.filterState.depths.size > 0 && taxonomy.depth && !state.filterState.depths.has(taxonomy.depth)) {
            return false;
        }
        if (state.filterState.saturations.size > 0 && taxonomy.saturation && !state.filterState.saturations.has(taxonomy.saturation)) {
            return false;
        }
        if (state.filterState.finishes.size > 0 && taxonomy.finish && !state.filterState.finishes.has(taxonomy.finish)) {
            return false;
        }
        if (state.filterState.opacities.size > 0 && taxonomy.opacity && !state.filterState.opacities.has(taxonomy.opacity)) {
            return false;
        }
        return true;
    });
}

/** @returns {number} */
function countActiveFilters() {
    return state.filterState.families.size
        + (state.filterState.undertone !== null ? 1 : 0)
        + state.filterState.depths.size
        + state.filterState.saturations.size
        + state.filterState.finishes.size
        + state.filterState.opacities.size;
}

/** @returns {void} */
function updateFilterBadge() {
    var badgeElement = document.querySelector('.color-lab__filter-badge');
    var clearButton = document.querySelector('.color-lab__clear-button');
    var activeFilterCount = countActiveFilters();

    if (badgeElement) {
        badgeElement.textContent = String(activeFilterCount);
        activeFilterCount > 0 ? badgeElement.removeAttribute('hidden') : badgeElement.setAttribute('hidden', '');
    }
    if (clearButton) {
        activeFilterCount > 0 ? clearButton.removeAttribute('hidden') : clearButton.setAttribute('hidden', '');
    }
}

/** @returns {void} */
function clearAllFilters() {
    state.filterState.families.clear();
    state.filterState.undertone = null;
    state.filterState.depths.clear();
    state.filterState.saturations.clear();
    state.filterState.finishes.clear();
    state.filterState.opacities.clear();

    document.querySelectorAll('.color-lab__filter-option input[type="checkbox"]').forEach(function(inputElement) {
        /** @type {HTMLInputElement} */ (inputElement).checked = false;
    });

    applyFilters();
    renderActiveView();
    updateFilterBadge();
    updateUrlParameters(state.activeView, state.selectedEntryId);
}

// ─── View Switching ───────────────────────────────────────────────────────────

/** @returns {void} */
function renderActiveView() {
    if (state.activeView === 'grid') {
        renderGridView();
    } else {
        renderScatterView();
    }
}

/**
 * Switches to a specific view, updating containers and button states.
 *
 * @param {ViewName} targetView
 * @returns {void}
 */
function switchToView(targetView) {
    state.activeView = targetView;

    document.querySelectorAll('.color-lab__view-button').forEach(function(viewButton) {
        var buttonView = /** @type {HTMLButtonElement} */ (viewButton).dataset.view;
        viewButton.classList.toggle('color-lab__view-button--active', buttonView === targetView);
    });

    var gridContainer = document.getElementById('colorLabGrid');
    var scatterContainer = document.getElementById('colorLabScatter');

    if (gridContainer && scatterContainer) {
        if (targetView === 'grid') {
            gridContainer.removeAttribute('hidden');
            scatterContainer.setAttribute('hidden', '');
        } else {
            gridContainer.setAttribute('hidden', '');
            scatterContainer.removeAttribute('hidden');
        }
    }
}

/**
 * @param {Event} clickEvent
 * @returns {void}
 */
function handleViewToggle(clickEvent) {
    var buttonElement = /** @type {HTMLButtonElement} */ (clickEvent.currentTarget);
    var targetView = /** @type {ViewName} */ (buttonElement.dataset.view);

    if (targetView === state.activeView) {
        return;
    }

    switchToView(targetView);
    renderActiveView();
    updateUrlParameters(targetView, state.selectedEntryId);
}

// ─── Grid View Renderer ───────────────────────────────────────────────────────

/** @returns {void} */
function renderGridView() {
    var gridContainer = document.getElementById('colorLabGrid');
    if (!gridContainer) {
        return;
    }

    if (state.filteredEntries.length === 0) {
        gridContainer.innerHTML = buildEmptyStateHtml();
        return;
    }

    var taxonomyOptions = /** @type {TaxonomyOptions} */ (window.__COLOR_LAB_TAXONOMY__ || {});
    var allFamilies = taxonomyOptions.families || [];
    var allDepths = taxonomyOptions.depths || [];

    var representedFamilies = new Set();
    state.filteredEntries.forEach(function(entry) {
        if (entry.taxonomy && entry.taxonomy.family) {
            representedFamilies.add(entry.taxonomy.family);
        }
        if (entry.taxonomy && entry.taxonomy.secondary_family) {
            representedFamilies.add(entry.taxonomy.secondary_family);
        }
    });

    var visibleFamilies = allFamilies.filter(function(family) { return representedFamilies.has(family); });

    /**
     * Place each entry in its primary family column AND its secondary family column.
     * @type {Object.<string, Object.<string, ColorEntry[]>>}
     */
    var columnData = {};
    state.filteredEntries.forEach(function(colorEntry) {
        var taxonomy = colorEntry.taxonomy || {};
        var primaryFamily = taxonomy.family || null;
        var secondaryFamily = taxonomy.secondary_family || null;
        var depthKey = taxonomy.depth || 'Uncategorized';

        if (primaryFamily) {
            if (!columnData[primaryFamily]) { columnData[primaryFamily] = {}; }
            if (!columnData[primaryFamily][depthKey]) { columnData[primaryFamily][depthKey] = []; }
            columnData[primaryFamily][depthKey].push(colorEntry);
        }

        if (secondaryFamily && secondaryFamily !== primaryFamily) {
            if (!columnData[secondaryFamily]) { columnData[secondaryFamily] = {}; }
            if (!columnData[secondaryFamily][depthKey]) { columnData[secondaryFamily][depthKey] = []; }
            columnData[secondaryFamily][depthKey].push(colorEntry);
        }
    });

    var htmlParts = ['<div class="color-lab__columns">'];

    visibleFamilies.forEach(function(family) {
        var familyDotColor = FAMILY_DOT_COLORS[family] || '#999';
        var familyDepths = columnData[family] || {};

        var familyColorCount = 0;
        Object.keys(familyDepths).forEach(function(depthKey) {
            familyColorCount += familyDepths[depthKey].length;
        });

        htmlParts.push('<div class="color-lab__column">');
        htmlParts.push(
            '<div class="color-lab__column-header">'
            + '<span class="color-lab__column-dot" style="background:' + familyDotColor + ';"></span>'
            + '<span class="color-lab__column-title">' + family + '</span>'
            + '<span class="color-lab__column-count">' + familyColorCount + '</span>'
            + '</div>'
        );

        allDepths.forEach(function(depth) {
            var depthEntries = familyDepths[depth];
            if (!depthEntries || depthEntries.length === 0) { return; }

            htmlParts.push(
                '<div class="color-lab__depth-group">'
                + '<div class="color-lab__depth-label">'
                + '<span class="color-lab__depth-arrow">&#9662;</span> '
                + depth
                + '</div>'
                + '<div class="color-lab__depth-swatches">'
            );

            depthEntries.forEach(function(colorEntry) {
                htmlParts.push(buildSwatchHtml(colorEntry));
            });

            htmlParts.push('</div></div>');
        });

        htmlParts.push('</div>');
    });

    htmlParts.push('</div>');
    gridContainer.innerHTML = htmlParts.join('');
}

/**
 * @param {ColorEntry} colorEntry
 * @returns {string}
 */
function buildSwatchHtml(colorEntry) {
    var safeHex = colorEntry.hexColor || '#cccccc';
    var isSelected = colorEntry.id === state.selectedEntryId;
    var selectedClass = isSelected ? ' color-lab__swatch--selected' : '';
    return '<div class="color-lab__swatch' + selectedClass + '"'
        + ' style="background-color:' + safeHex + ';"'
        + ' data-entry-id="' + colorEntry.id + '"'
        + ' role="button" tabindex="0"'
        + ' aria-label="' + escapeHtmlAttribute(colorEntry.productName) + ' — ' + escapeHtmlAttribute(colorEntry.variationName) + '"'
        + '></div>';
}

/** @returns {string} */
function buildEmptyStateHtml() {
    return '<div class="color-lab__empty-state">'
        + '<p class="color-lab__empty-state-title">No colors match the current filters</p>'
        + '<p class="color-lab__empty-state-message">Try removing some filters to see more results.</p>'
        + '</div>';
}

// ─── 3D Scatter View Renderer ──────────────────────────────────────────────────

/** @returns {void} */
function renderScatterView() {
    var scatterContainer = document.getElementById('colorLabScatter');
    if (!scatterContainer) { return; }

    if (typeof Plotly === 'undefined') {
        scatterContainer.innerHTML = '<div class="color-lab__scatter-error">Plotly.js failed to load. Check your network connection.</div>';
        return;
    }

    if (state.filteredEntries.length === 0) {
        if (state.plotlyInitialized) { Plotly.purge(scatterContainer); }
        scatterContainer.innerHTML = '<div class="color-lab__scatter-error">No colors match the current filters.</div>';
        state.plotlyInitialized = false;
        return;
    }

    if (!state.plotlyInitialized) {
        scatterContainer.innerHTML = '';
    }

    var xCoordinates = [];
    var yCoordinates = [];
    var zCoordinates = [];
    var markerColors = [];
    var hoverTexts = [];
    var entryIds = [];

    state.filteredEntries.forEach(function(colorEntry) {
        var oklch = colorEntry.oklch || { lightness: 50, chroma: 0, hue: 0 };
        var hueRadians = (oklch.hue || 0) * Math.PI / 180;
        var chroma = oklch.chroma || 0;

        xCoordinates.push(chroma * Math.cos(hueRadians));
        yCoordinates.push(oklch.lightness || 0);
        zCoordinates.push(chroma * Math.sin(hueRadians));
        markerColors.push(colorEntry.hexColor || '#cccccc');
        entryIds.push(colorEntry.id);

        var lightnessDisplay = typeof oklch.lightness === 'number' ? oklch.lightness.toFixed(1) : '—';
        var chromaDisplay = typeof oklch.chroma === 'number' ? oklch.chroma.toFixed(3) : '—';
        var hueDisplay = typeof oklch.hue === 'number' ? oklch.hue.toFixed(1) : '—';

        hoverTexts.push(
            escapeHtml(colorEntry.productName)
            + ' — ' + escapeHtml(colorEntry.variationName)
            + '<br>' + escapeHtml(colorEntry.hexColor || '')
            + '  L:' + lightnessDisplay + ' C:' + chromaDisplay + ' H:' + hueDisplay
        );
    });

    /** @type {Plotly.Data[]} */
    var plotlyTrace = [{
        type: 'scatter3d',
        mode: 'markers',
        x: xCoordinates,
        y: yCoordinates,
        z: zCoordinates,
        text: hoverTexts,
        hoverinfo: 'text',
        hoverlabel: { bgcolor: 'rgba(0,0,0,0)', bordercolor: 'rgba(0,0,0,0)', font: { size: 1, color: 'rgba(0,0,0,0)' } },
        customdata: entryIds,
        marker: {
            color: markerColors,
            size: 10,
            opacity: 0.85,
            line: { color: 'rgba(0,0,0,0.15)', width: 0.5 },
        },
    }];

    var isMobile = isMobileViewport();

    /** @type {Plotly.Layout} */
    var plotlyLayout = {
        scene: {
            xaxis: { title: 'Chroma \u00d7 cos(Hue)' },
            yaxis: { title: 'Lightness' },
            zaxis: { title: 'Chroma \u00d7 sin(Hue)' },
            aspectmode: 'cube',
            domain: isMobile ? { x: [0, 1], y: [0, 1] } : undefined,
        },
        margin: isMobile
            ? { left: 0, right: 0, top: 0, bottom: 0 }
            : { left: 0, right: 0, top: 30, bottom: 0 },
        paper_bgcolor: 'transparent',
        font: { family: "'Didact Gothic', sans-serif", size: 11 },
    };

    /** @type {Plotly.Config} */
    var plotlyConfig = {
        responsive: true,
        displayModeBar: true,
        displaylogo: false,
        modeBarButtonsToRemove: ['resetCamera'],
    };

    if (state.plotlyInitialized) {
        Plotly.react(scatterContainer, plotlyTrace, plotlyLayout, plotlyConfig);
        if (isMobile) { adjustMobileSceneBounds(scatterContainer); }
    } else {
        Plotly.newPlot(scatterContainer, plotlyTrace, plotlyLayout, plotlyConfig).then(function() {
            scatterContainer.on('plotly_click', handlePlotlyDotClick);
            scatterContainer.on('plotly_hover', handlePlotlyDotHover);
            scatterContainer.on('plotly_unhover', handlePlotlyDotUnhover);
            scatterContainer.addEventListener('mousemove', function(mouseEvent) {
                lastMousePosition.x = mouseEvent.clientX;
                lastMousePosition.y = mouseEvent.clientY;
            });
            if (isMobile) { adjustMobileSceneBounds(scatterContainer); }
        });
        state.plotlyInitialized = true;
    }
}

/**
 * Overrides Plotly's internal scene positioning on mobile to remove top gap.
 * Uses MutationObserver to persistently enforce the override after every Plotly redraw.
 *
 * @param {HTMLElement} container - The Plotly container element.
 * @returns {void}
 */
function adjustMobileSceneBounds(container) {
    var sceneElement = container.querySelector('#scene');
    if (!sceneElement) { return; }

    function applyOverride() {
        var currentTop = parseInt(sceneElement.style.top, 10) || 0;
        if (currentTop === 0) { return; }
        var currentHeight = parseInt(sceneElement.style.height, 10) || 0;
        sceneElement.style.top = '0px';
        sceneElement.style.height = (currentHeight + currentTop - 100) + 'px';
    }

    applyOverride();

    new MutationObserver(function() {
        applyOverride();
    }).observe(sceneElement, { attributes: true, attributeFilter: ['style'] });
}

/**
 * Handles a click on a Plotly 3D scatter dot.
 * Opens the detail card for the clicked color entry.
 *
 * @param {Object} plotlyClickData - Plotly click event data.
 * @returns {void}
 */
function handlePlotlyDotClick(plotlyClickData) {
    if (!plotlyClickData || !plotlyClickData.points || plotlyClickData.points.length === 0) {
        return;
    }

    var clickedPoint = plotlyClickData.points[0];
    var entryId = clickedPoint.customdata;

    if (typeof entryId === 'number' && entriesById.has(entryId)) {
        var colorEntry = entriesById.get(entryId);
        selectColorEntry(colorEntry);
    }
}

/** @type {{x: number, y: number}} Last known mouse position for 3D tooltip placement */
var lastMousePosition = { x: 0, y: 0 };

/**
 * Handles hover on a Plotly 3D scatter dot.
 * Shows the rich custom tooltip positioned near the mouse cursor.
 *
 * @param {Object} plotlyHoverData - Plotly hover event data.
 * @returns {void}
 */
function handlePlotlyDotHover(plotlyHoverData) {
    if (!plotlyHoverData || !plotlyHoverData.points || plotlyHoverData.points.length === 0) {
        return;
    }

    var hoveredPoint = plotlyHoverData.points[0];
    var entryId = hoveredPoint.customdata;

    if (typeof entryId !== 'number' || !entriesById.has(entryId)) {
        return;
    }

    var colorEntry = entriesById.get(entryId);
    hideTooltip();

    var tooltipElement = buildRichTooltipElement(colorEntry);
    document.body.appendChild(tooltipElement);

    var tooltipRect = tooltipElement.getBoundingClientRect();
    var preferredTop = lastMousePosition.y - tooltipRect.height - 12;
    var preferredLeft = lastMousePosition.x + 16;

    var clampedTop = Math.max(4, Math.min(preferredTop, window.innerHeight - tooltipRect.height - 4));
    var clampedLeft = Math.max(4, Math.min(preferredLeft, window.innerWidth - tooltipRect.width - 4));

    tooltipElement.style.top = clampedTop + 'px';
    tooltipElement.style.left = clampedLeft + 'px';
}

/**
 * Handles unhover on a Plotly 3D scatter dot.
 *
 * @returns {void}
 */
function handlePlotlyDotUnhover() {
    hideTooltip();
}

// ─── Color Entry Selection ────────────────────────────────────────────────────

/**
 * Selects a color entry: updates URL, state, and opens the detail card.
 *
 * @param {ColorEntry} colorEntry
 * @returns {void}
 */
function selectColorEntry(colorEntry) {
    state.selectedEntryId = colorEntry.id;
    updateUrlParameters(state.activeView, colorEntry.id);
    showDetailCard(colorEntry);
}

/**
 * Deselects the current color entry: closes detail card, updates URL.
 *
 * @returns {void}
 */
function deselectColorEntry() {
    state.selectedEntryId = null;
    updateUrlParameters(state.activeView, null);
    hideDetailCard();
}

// ─── Detail Card ──────────────────────────────────────────────────────────────

/**
 * Builds and displays a rich detail card panel for the given color entry.
 * Shows original image, cropped swatch, color data, taxonomy, palette, and product link.
 *
 * @param {ColorEntry} colorEntry
 * @returns {void}
 */
function showDetailCard(colorEntry) {
    hideDetailCard();

    var oklch = colorEntry.oklch || { lightness: 0, chroma: 0, hue: 0 };
    var lightnessDisplay = typeof oklch.lightness === 'number' ? oklch.lightness.toFixed(3) : '—';
    var chromaDisplay = typeof oklch.chroma === 'number' ? oklch.chroma.toFixed(3) : '—';
    var hueDisplay = typeof oklch.hue === 'number' ? oklch.hue.toFixed(1) + '\u00b0' : '—';
    var confidencePercent = typeof colorEntry.confidenceScore === 'number'
        ? (colorEntry.confidenceScore * 100).toFixed(0) + '%' : '—';

    var confidenceColor = colorEntry.confidenceScore >= 0.8 ? '#28a745'
        : colorEntry.confidenceScore >= 0.5 ? '#ffc107' : '#dc3545';

    var productDetailUrl = colorEntry.detailUrl || '#';

    var textColorOnSwatch = (oklch.lightness || 0) > 60 ? '#000' : '#fff';

    var paletteHtml = '';
    if (colorEntry.paletteColors && colorEntry.paletteColors.length > 0) {
        paletteHtml = '<div class="color-lab__detail-palette">';
        colorEntry.paletteColors.forEach(function(paletteHex) {
            paletteHtml += '<span class="color-lab__detail-palette-swatch" style="background:' + escapeHtml(paletteHex) + ';" title="' + escapeHtml(paletteHex) + '"></span>';
        });
        paletteHtml += '</div>';
    }

    var taxonomy = colorEntry.taxonomy || {};
    var taxonomyRows = [
        { label: 'Family', value: taxonomy.family },
        { label: 'Undertone', value: taxonomy.undertone },
        { label: 'Depth', value: taxonomy.depth },
        { label: 'Saturation', value: taxonomy.saturation },
        { label: 'Finish', value: taxonomy.finish },
        { label: 'Opacity', value: taxonomy.opacity },
    ];

    var taxonomyHtml = '<div class="color-lab__detail-taxonomy">';
    taxonomyRows.forEach(function(row) {
        var displayValue = row.value ? escapeHtml(row.value) : '<span class="color-lab__detail-null">—</span>';
        taxonomyHtml += '<div class="color-lab__detail-taxonomy-row">'
            + '<span class="color-lab__detail-taxonomy-label">' + row.label + '</span>'
            + '<span class="color-lab__detail-taxonomy-value">' + displayValue + '</span>'
            + '</div>';
    });
    taxonomyHtml += '</div>';

    var cardElement = document.createElement('div');
    cardElement.className = 'color-lab__detail-card';
    cardElement.setAttribute('role', 'dialog');
    cardElement.setAttribute('aria-label', colorEntry.productName + ' — ' + colorEntry.variationName);

    cardElement.innerHTML = ''
        + '<button class="color-lab__detail-close" aria-label="Close" title="Close">&times;</button>'

        + '<div class="color-lab__detail-hero" style="background:' + escapeHtml(colorEntry.hexColor) + ';color:' + textColorOnSwatch + ';">'
        + '    <div class="color-lab__detail-hero-hex">' + escapeHtml(colorEntry.hexColor) + '</div>'
        + '    <div class="color-lab__detail-hero-oklch">oklch(' + lightnessDisplay + ' ' + chromaDisplay + ' ' + hueDisplay + ')</div>'
        + '</div>'

        + '<div class="color-lab__detail-images">'
        + (colorEntry.imageUrl
            ? '<div class="color-lab__detail-image-group" data-lightbox-entry="' + colorEntry.id + '">'
            + '    <img src="' + escapeHtml(colorEntry.imageUrl) + '" alt="Original" class="color-lab__detail-image" loading="lazy">'
            + '    <span class="color-lab__detail-image-label">Original</span>'
            + '</div>'
            : '')
        + (colorEntry.croppedImageData
            ? buildDetailImageGroupHtml(colorEntry.croppedImageData, 'Center Crop', null, 'color-lab__detail-image--crop')
            : '')
        + buildDetailImageGroupHtml(null, escapeHtml(colorEntry.hexColor), colorEntry.hexColor, 'color-lab__detail-image--hex')
        + (isPngImage(colorEntry.imageUrl)
            ? buildDetailOverlayGroupHtml(colorEntry.imageUrl, colorEntry.hexColor, colorEntry.id)
            : '')
        + '</div>'

        + '<div class="color-lab__detail-body">'
        + '    <h3 class="color-lab__detail-product-name">' + escapeHtml(colorEntry.productName) + '</h3>'
        + '    <p class="color-lab__detail-variation-name">' + escapeHtml(colorEntry.variationName) + '</p>'
        + (colorEntry.colorName ? '<p class="color-lab__detail-color-name">' + escapeHtml(colorEntry.colorName) + '</p>' : '')
        + paletteHtml
        + taxonomyHtml
        + (productDetailUrl !== '#' ? '    <a href="' + productDetailUrl + '" class="color-lab__detail-product-link">View Product &rarr;</a>' : '')
        + '</div>';

    document.body.appendChild(cardElement);

    requestAnimationFrame(function() {
        cardElement.classList.add('color-lab__detail-card--visible');
        attachDetailCardSwipeHandler(cardElement);
        attachProductLinkLoadingHandler(cardElement);
    });
}

/** @returns {void} */
function hideDetailCard() {
    var existingCard = document.querySelector('.color-lab__detail-card');
    if (existingCard) {
        existingCard.classList.remove('color-lab__detail-card--visible');
        setTimeout(function() { existingCard.remove(); }, 200);
    }
}

/**
 * Attaches a click handler to the product link inside the detail card.
 * On click, swaps the link content to a "Loading..." spinner state and
 * prevents double-clicking via pointer-events: none. Navigation proceeds normally.
 *
 * @param {HTMLElement} cardElement - The detail card DOM element containing the link.
 * @returns {void}
 */
function attachProductLinkLoadingHandler(cardElement) {
    var link = cardElement.querySelector('.color-lab__detail-product-link');
    if (!link) { return; }

    link.addEventListener('click', function() {
        link.classList.add('color-lab__detail-product-link--loading');
        link.innerHTML = 'Loading&hellip; <span class="color-lab__spinner"></span>';
    });
}

/**
 * Attaches a pointer-based swipe-to-dismiss gesture handler to the detail card
 * on mobile viewports. Follows the vaul/shadcn drawer pattern: a fast flick or
 * a drag past 25% of card height dismisses; anything less snaps the card back.
 *
 * Only active when `isMobileViewport()` is true at the moment of `pointerdown`.
 * All gesture state is scoped via closure — nothing written to the module `state` object.
 *
 * @param {HTMLElement} cardElement - The detail card DOM element to attach the handler to.
 * @returns {void}
 */
function attachDetailCardSwipeHandler(cardElement) {
    /** @type {number} Y coordinate at the start of the drag gesture */
    var startY = 0;

    /** @type {number} Unix timestamp (ms) at the start of the drag gesture */
    var startTime = 0;

    /** @type {boolean} Whether a drag gesture is currently active */
    var isDragging = false;

    /** @type {AbortController} Used to clean up move/up listeners after each gesture */
    var gestureController = null;

    /**
     * Returns true if the pointer target is inside a scrollable element that
     * currently has scrollable content below the top position.
     *
     * @param {EventTarget} target
     * @returns {boolean}
     */
    function isInsideScrollableContent(target) {
        var element = /** @type {HTMLElement} */ (target);
        while (element && element !== cardElement) {
            if (element.scrollHeight > element.clientHeight && element.scrollTop > 0) {
                return true;
            }
            element = element.parentElement;
        }
        return false;
    }

    /**
     * Handles `pointerdown` — starts a drag gesture when conditions are met.
     *
     * @param {PointerEvent} pointerEvent
     * @returns {void}
     */
    function handlePointerDown(pointerEvent) {
        if (!isMobileViewport()) { return; }

        var target = /** @type {HTMLElement} */ (pointerEvent.target);
        var isOnHeroArea = !!target.closest('.color-lab__detail-hero');
        var cardScrolledToTop = cardElement.scrollTop === 0;
        var targetIsInScrollableContent = isInsideScrollableContent(target);

        // Only activate from the hero/handle zone, OR from the card body when
        // scrolled to top and not inside a nested scrollable element.
        if (!isOnHeroArea && (!cardScrolledToTop || targetIsInScrollableContent)) {
            return;
        }

        isDragging = true;
        startY = pointerEvent.clientY;
        startTime = Date.now();

        pointerEvent.target.setPointerCapture(pointerEvent.pointerId);
        cardElement.classList.add('color-lab__detail-card--dragging');

        gestureController = new AbortController();
        var gestureOptions = { signal: gestureController.signal };

        cardElement.addEventListener('pointermove', handlePointerMove, gestureOptions);
        cardElement.addEventListener('pointerup', handlePointerUp, gestureOptions);
        cardElement.addEventListener('pointercancel', handlePointerUp, gestureOptions);
    }

    /**
     * Handles `pointermove` — translates the card vertically during the drag.
     *
     * @param {PointerEvent} pointerEvent
     * @returns {void}
     */
    function handlePointerMove(pointerEvent) {
        if (!isDragging) { return; }

        var deltaY = pointerEvent.clientY - startY;
        // Clamp: do not allow dragging upward past the open position.
        if (deltaY < 0) { deltaY = 0; }

        cardElement.style.transform = 'translateY(' + deltaY + 'px)';
    }

    /**
     * Handles `pointerup` and `pointercancel` — decides whether to dismiss
     * (via velocity or distance threshold) or snap the card back to open position.
     *
     * @param {PointerEvent} pointerEvent
     * @returns {void}
     */
    function handlePointerUp(pointerEvent) {
        if (!isDragging) { return; }

        isDragging = false;
        cardElement.classList.remove('color-lab__detail-card--dragging');

        var deltaY = Math.max(0, pointerEvent.clientY - startY);
        var elapsedTime = Date.now() - startTime;
        var velocity = elapsedTime > 0 ? deltaY / elapsedTime : 0;
        var cardHeight = cardElement.offsetHeight;
        var distanceThresholdMet = deltaY > cardHeight * 0.25;
        var velocityThresholdMet = velocity > 0.5;

        if (gestureController) {
            gestureController.abort();
            gestureController = null;
        }

        if (velocityThresholdMet || distanceThresholdMet) {
            // Animate out, then deselect. Re-enable transition first.
            cardElement.style.transform = 'translateY(100%)';
            cardElement.style.transition = 'transform 0.25s cubic-bezier(0.4, 0, 0.2, 1)';

            var dismissTimeout = setTimeout(function() {
                deselectColorEntry();
            }, 250);

            cardElement.addEventListener('transitionend', function onTransitionEnd() {
                clearTimeout(dismissTimeout);
                cardElement.removeEventListener('transitionend', onTransitionEnd);
                deselectColorEntry();
            }, { once: true });
        } else {
            // Snap back: clear inline transform so CSS --visible class reasserts translateY(0).
            cardElement.style.transform = '';
        }
    }

    cardElement.addEventListener('pointerdown', handlePointerDown);
}

// ─── Tooltip (Grid hover) ─────────────────────────────────────────────────────

/**
 * @param {ColorEntry} colorEntry
 * @param {HTMLElement} swatchElement
 * @returns {void}
 */
/**
 * Builds a rich tooltip DOM element for a color entry.
 * Shared by both grid hover and 3D scatter hover.
 *
 * @param {ColorEntry} colorEntry
 * @returns {HTMLElement}
 */
function buildRichTooltipElement(colorEntry) {
    var oklch = colorEntry.oklch || { lightness: 0, chroma: 0, hue: 0 };
    var lightnessDisplay = typeof oklch.lightness === 'number' ? oklch.lightness.toFixed(1) : '—';
    var chromaDisplay = typeof oklch.chroma === 'number' ? oklch.chroma.toFixed(3) : '—';
    var hueDisplay = typeof oklch.hue === 'number' ? oklch.hue.toFixed(1) + '\u00b0' : '—';
    var taxonomy = colorEntry.taxonomy || {};

    var tooltipElement = document.createElement('div');
    tooltipElement.className = 'color-lab__tooltip';
    tooltipElement.setAttribute('role', 'tooltip');

    var imageHtml = colorEntry.imageUrl
        ? '<img src="' + escapeHtml(colorEntry.imageUrl) + '" class="color-lab__tooltip-image" alt="" loading="lazy">'
        : '';

    var taxonomyBadges = '';
    if (taxonomy.family) {
        var familyLabel = escapeHtml(taxonomy.family);
        if (taxonomy.secondary_family) {
            familyLabel += ' / ' + escapeHtml(taxonomy.secondary_family);
        }
        taxonomyBadges += '<span class="color-lab__tooltip-badge">' + familyLabel + '</span>';
    }
    if (taxonomy.depth) { taxonomyBadges += '<span class="color-lab__tooltip-badge">' + escapeHtml(taxonomy.depth) + '</span>'; }
    if (taxonomy.undertone) { taxonomyBadges += '<span class="color-lab__tooltip-badge">' + escapeHtml(taxonomy.undertone) + '</span>'; }

    tooltipElement.innerHTML = ''
        + '<div class="color-lab__tooltip-top">'
        + imageHtml
        + '<div class="color-lab__tooltip-info">'
        + '    <span class="color-lab__tooltip-product-name">' + escapeHtml(colorEntry.productName) + '</span>'
        + '    <span class="color-lab__tooltip-variation-name">' + escapeHtml(colorEntry.variationName) + '</span>'
        + '    <div class="color-lab__tooltip-hex-row">'
        + '        <span class="color-lab__tooltip-hex-swatch" style="background:' + escapeHtml(colorEntry.hexColor || '#ccc') + ';"></span>'
        + '        <span class="color-lab__tooltip-hex-value">' + escapeHtml(colorEntry.hexColor || '—') + '</span>'
        + '    </div>'
        + '</div>'
        + '</div>'
        + '<span class="color-lab__tooltip-oklch">oklch(' + lightnessDisplay + ' ' + chromaDisplay + ' ' + hueDisplay + ')</span>'
        + (colorEntry.colorName ? '<span class="color-lab__tooltip-color-name">' + escapeHtml(colorEntry.colorName) + '</span>' : '')
        + (taxonomyBadges ? '<div class="color-lab__tooltip-badges">' + taxonomyBadges + '</div>' : '')
        + '<span class="color-lab__tooltip-hint">Click for details</span>';

    return tooltipElement;
}

/**
 * Shows the rich tooltip near a grid swatch element.
 *
 * @param {ColorEntry} colorEntry
 * @param {HTMLElement} swatchElement
 * @returns {void}
 */
function showTooltip(colorEntry, swatchElement) {
    hideTooltip();

    var tooltipElement = buildRichTooltipElement(colorEntry);
    document.body.appendChild(tooltipElement);

    var swatchRect = swatchElement.getBoundingClientRect();
    var tooltipRect = tooltipElement.getBoundingClientRect();
    var preferredTop = swatchRect.top - tooltipRect.height - 8;
    var preferredLeft = swatchRect.left + (swatchRect.width / 2) - (tooltipRect.width / 2);
    var clampedTop = Math.max(4, Math.min(preferredTop, window.innerHeight - tooltipRect.height - 4));
    var clampedLeft = Math.max(4, Math.min(preferredLeft, window.innerWidth - tooltipRect.width - 4));

    tooltipElement.style.top = clampedTop + 'px';
    tooltipElement.style.left = clampedLeft + 'px';
}

/** @returns {void} */
function hideTooltip() {
    var existingTooltip = document.querySelector('.color-lab__tooltip');
    if (existingTooltip) { existingTooltip.remove(); }
}

// ─── Event Listeners ──────────────────────────────────────────────────────────

/** @returns {void} */
function attachGridInteractionListeners() {
    var gridContainer = document.getElementById('colorLabGrid');
    if (!gridContainer) { return; }

    gridContainer.addEventListener('mouseover', function(mouseEvent) {
        var swatchElement = /** @type {HTMLElement} */ (mouseEvent.target).closest('.color-lab__swatch');
        if (!swatchElement) { return; }
        var entryId = parseInt(swatchElement.dataset.entryId, 10);
        var colorEntry = entriesById.get(entryId);
        if (colorEntry) { showTooltip(colorEntry, /** @type {HTMLElement} */ (swatchElement)); }
    });

    gridContainer.addEventListener('mouseout', function(mouseEvent) {
        if (/** @type {HTMLElement} */ (mouseEvent.target).closest('.color-lab__swatch')) { hideTooltip(); }
    });

    gridContainer.addEventListener('click', function(clickEvent) {
        var swatchElement = /** @type {HTMLElement} */ (clickEvent.target).closest('.color-lab__swatch');
        if (!swatchElement) { return; }
        var entryId = parseInt(swatchElement.dataset.entryId, 10);
        var colorEntry = entriesById.get(entryId);
        if (colorEntry) { selectColorEntry(colorEntry); }
    });
}

/** @returns {void} */
function attachViewToggleListeners() {
    document.querySelectorAll('.color-lab__view-button').forEach(function(viewButton) {
        viewButton.addEventListener('click', handleViewToggle);
    });
}

/** @returns {void} */
function attachClearButtonListener() {
    var clearButton = document.querySelector('.color-lab__clear-button');
    if (clearButton) { clearButton.addEventListener('click', clearAllFilters); }
}

/** @returns {void} */
function attachDetailCardCloseListeners() {
    document.addEventListener('click', function(clickEvent) {
        var target = /** @type {HTMLElement} */ (clickEvent.target);
        if (target.closest('.color-lab__detail-close')) {
            deselectColorEntry();
        }
    });

    document.addEventListener('keydown', function(keyEvent) {
        if (keyEvent.key === 'Escape' && state.selectedEntryId !== null) {
            deselectColorEntry();
        }
    });
}

/** @returns {boolean} */
function isMobileViewport() {
    return window.matchMedia('(max-width: 768px)').matches;
}

/** @returns {void} */
function openMobileSidebar() {
    var sidebar = document.querySelector('.color-lab__sidebar');
    var filterButton = document.querySelector('.color-lab__filter-toggle');
    if (!sidebar) { return; }

    var header = document.querySelector('.color-lab__header');
    if (header) {
        sidebar.style.top = header.offsetHeight + 'px';
    }

    sidebar.classList.add('color-lab__sidebar--mobile-open');
    if (filterButton) {
        filterButton.classList.add('color-lab__view-button--active');
    }

    var backdrop = document.querySelector('.color-lab__sidebar-backdrop');
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.className = 'color-lab__sidebar-backdrop';
        document.querySelector('.color-lab').appendChild(backdrop);
    }
    backdrop.classList.add('color-lab__sidebar-backdrop--visible');
    backdrop.addEventListener('click', closeMobileSidebar);
}

/** @returns {void} */
function closeMobileSidebar() {
    var sidebar = document.querySelector('.color-lab__sidebar');
    var filterButton = document.querySelector('.color-lab__filter-toggle');
    if (!sidebar) { return; }

    sidebar.classList.remove('color-lab__sidebar--mobile-open');
    if (filterButton) {
        filterButton.classList.remove('color-lab__view-button--active');
    }

    var backdrop = document.querySelector('.color-lab__sidebar-backdrop');
    if (backdrop) {
        backdrop.classList.remove('color-lab__sidebar-backdrop--visible');
    }
}

/** @returns {void} */
function attachSidebarToggleListener() {
    var toggleButton = document.querySelector('.color-lab__sidebar-toggle');
    if (!toggleButton) { return; }

    if (isMobileViewport()) {
        var viewToggle = document.querySelector('.color-lab__view-toggle');
        if (viewToggle && !document.querySelector('.color-lab__filter-toggle')) {
            var filterButton = document.createElement('button');
            filterButton.className = 'color-lab__view-button color-lab__filter-toggle';
            filterButton.textContent = 'Filters';
            filterButton.type = 'button';
            viewToggle.appendChild(filterButton);

            filterButton.addEventListener('click', function() {
                var sidebar = document.querySelector('.color-lab__sidebar');
                var isOpen = sidebar && sidebar.classList.contains('color-lab__sidebar--mobile-open');
                isOpen ? closeMobileSidebar() : openMobileSidebar();
            });
        }
    }

    toggleButton.addEventListener('click', function() {
        if (isMobileViewport()) {
            var sidebar = document.querySelector('.color-lab__sidebar');
            var isOpen = sidebar && sidebar.classList.contains('color-lab__sidebar--mobile-open');
            isOpen ? closeMobileSidebar() : openMobileSidebar();
        } else {
            var bodyElement = document.querySelector('.color-lab__body');
            if (!bodyElement) { return; }
            bodyElement.classList.toggle('color-lab__body--sidebar-collapsed');
            var isCollapsed = bodyElement.classList.contains('color-lab__body--sidebar-collapsed');
            toggleButton.innerHTML = isCollapsed ? '&#9654;' : '&#9776;';
            toggleButton.title = isCollapsed ? 'Show filters' : 'Hide filters';
        }
    });
}

// ─── Detail Image Builders (DRY) ──────────────────────────────────────────────

/**
 * Builds a single detail image group (crop, hex block, etc).
 *
 * @param {string|null} imageSrc - Image source URL or base64, or null for color block.
 * @param {string} label - Label text below the image.
 * @param {string|null} backgroundColor - Background color for color blocks.
 * @param {string} extraClass - Additional CSS class.
 * @returns {string} HTML string.
 */
function buildDetailImageGroupHtml(imageSrc, label, backgroundColor, extraClass) {
    var inner = imageSrc
        ? '<img src="' + imageSrc + '" alt="' + escapeHtmlAttribute(label) + '" class="color-lab__detail-image ' + extraClass + '" loading="lazy">'
        : '<div class="color-lab__detail-image ' + extraClass + '" style="background:' + escapeHtml(backgroundColor) + ';"></div>';

    return '<div class="color-lab__detail-image-group">'
        + inner
        + '<span class="color-lab__detail-image-label">' + label + '</span>'
        + '</div>';
}

/**
 * Builds the overlay image group (PNG on hex color background).
 *
 * @param {string} imageUrl - PNG image URL.
 * @param {string} hexColor - Background hex color.
 * @returns {string} HTML string.
 */
function buildDetailOverlayGroupHtml(imageUrl, hexColor, entryId) {
    return '<div class="color-lab__detail-image-group" data-lightbox-entry="' + entryId + '" data-lightbox-mode="overlay">'
        + '<div class="color-lab__detail-image color-lab__detail-image--blend" style="background:' + escapeHtml(hexColor) + ';">'
        + '    <img src="' + escapeHtml(imageUrl) + '" alt="Overlay" loading="lazy">'
        + '</div>'
        + '<span class="color-lab__detail-image-label">Overlay</span>'
        + '</div>';
}

// ─── Lightbox ─────────────────────────────────────────────────────────────────

/**
 * Shows a fullscreen lightbox with the original image and optional blend overlay.
 *
 * @param {string} imageSrc - URL of the full-size image.
 * @param {string|null} blendColor - Hex color for blend overlay, or null.
 * @returns {void}
 */
/**
 * Shows a fullscreen lightbox with the product image on 3 backgrounds + overlay.
 *
 * @param {string} imageSrc - URL of the product image.
 * @param {string|null} overlayColor - Hex color for overlay panel, or null.
 * @returns {void}
 */
function showLightbox(imageSrc, overlayColor) {
    hideLightbox();

    var overlay = document.createElement('div');
    overlay.className = 'color-lab__lightbox';

    var backgrounds = [
        { color: '#ffffff', label: 'White' },
        { color: '#808080', label: 'Grey' },
        { color: '#1a1a1a', label: 'Black' },
    ];

    var contentHtml = '<div class="color-lab__lightbox-content">';

    backgrounds.forEach(function(background) {
        contentHtml += '<div class="color-lab__lightbox-panel">'
            + '<div class="color-lab__lightbox-bg" style="background:' + background.color + ';">'
            + '    <img src="' + escapeHtml(imageSrc) + '" alt="On ' + background.label + '" class="color-lab__lightbox-image">'
            + '</div>'
            + '<span class="color-lab__lightbox-label">' + background.label + '</span>'
            + '</div>';
    });

    if (overlayColor) {
        contentHtml += '<div class="color-lab__lightbox-panel">'
            + '<div class="color-lab__lightbox-bg" style="background:' + escapeHtml(overlayColor) + ';">'
            + '    <img src="' + escapeHtml(imageSrc) + '" alt="Overlay" class="color-lab__lightbox-image">'
            + '</div>'
            + '<span class="color-lab__lightbox-label">Overlay — ' + escapeHtml(overlayColor) + '</span>'
            + '</div>';
    }

    contentHtml += '</div>';

    overlay.innerHTML = '<button class="color-lab__lightbox-close" aria-label="Close">&times;</button>'
        + contentHtml;

    overlay.addEventListener('click', function(clickEvent) {
        if (/** @type {HTMLElement} */ (clickEvent.target).closest('.color-lab__lightbox-content')) {
            return;
        }
        hideLightbox();
    });

    document.body.appendChild(overlay);
    requestAnimationFrame(function() { overlay.classList.add('color-lab__lightbox--visible'); });

    var urlParams = new URLSearchParams(window.location.search);
    urlParams.set('lightbox', '1');
    window.history.replaceState(null, '', window.location.pathname + '?' + urlParams.toString());
}

/**
 * Shows a lightbox with just 2 panels: Original image + Overlay on hex color.
 *
 * @param {string} imageSrc - URL of the product image.
 * @param {string} hexColor - Hex color for overlay background.
 * @returns {void}
 */
function showLightboxOverlay(imageSrc, hexColor) {
    hideLightbox();

    var overlay = document.createElement('div');
    overlay.className = 'color-lab__lightbox';

    overlay.innerHTML = '<button class="color-lab__lightbox-close" aria-label="Close">&times;</button>'
        + '<div class="color-lab__lightbox-content color-lab__lightbox-content--two">'
        + '<div class="color-lab__lightbox-panel">'
        + '    <div class="color-lab__lightbox-bg" style="background:#ffffff;">'
        + '        <img src="' + escapeHtml(imageSrc) + '" alt="Original" class="color-lab__lightbox-image">'
        + '    </div>'
        + '    <span class="color-lab__lightbox-label">Original</span>'
        + '</div>'
        + '<div class="color-lab__lightbox-panel">'
        + '    <div class="color-lab__lightbox-bg" style="background:' + escapeHtml(hexColor) + ';">'
        + '        <img src="' + escapeHtml(imageSrc) + '" alt="Overlay" class="color-lab__lightbox-image">'
        + '    </div>'
        + '    <span class="color-lab__lightbox-label">Overlay — ' + escapeHtml(hexColor) + '</span>'
        + '</div>'
        + '</div>';

    overlay.addEventListener('click', function(clickEvent) {
        if (!/** @type {HTMLElement} */ (clickEvent.target).closest('.color-lab__lightbox-content')) {
            hideLightbox();
        }
    });

    document.body.appendChild(overlay);
    requestAnimationFrame(function() { overlay.classList.add('color-lab__lightbox--visible'); });

    var urlParams = new URLSearchParams(window.location.search);
    urlParams.set('lightbox', '1');
    window.history.replaceState(null, '', window.location.pathname + '?' + urlParams.toString());
}

/** @returns {void} */
function hideLightbox() {
    var existing = document.querySelector('.color-lab__lightbox');
    if (existing) { existing.remove(); }

    var urlParams = new URLSearchParams(window.location.search);
    urlParams.delete('lightbox');
    window.history.replaceState(null, '', window.location.pathname + '?' + urlParams.toString());
}

/**
 * Attaches click/tap listeners to detail card image groups for lightbox.
 * Uses event delegation on body since detail cards are dynamically created.
 *
 * @returns {void}
 */
function attachLightboxListeners() {
    document.body.addEventListener('click', function(clickEvent) {
        var imageGroup = /** @type {HTMLElement} */ (clickEvent.target).closest('.color-lab__detail-image-group[data-lightbox-entry]');
        if (!imageGroup) { return; }

        var entryId = parseInt(imageGroup.dataset.lightboxEntry, 10);
        var lightboxMode = imageGroup.dataset.lightboxMode || 'backgrounds';
        var colorEntry = entriesById.get(entryId);
        if (!colorEntry || !colorEntry.imageUrl) { return; }

        if (lightboxMode === 'overlay') {
            showLightboxOverlay(colorEntry.imageUrl, colorEntry.hexColor);
        } else {
            var overlayColor = isPngImage(colorEntry.imageUrl) ? colorEntry.hexColor : null;
            showLightbox(colorEntry.imageUrl, overlayColor);
        }
    });

    document.addEventListener('keydown', function(keyEvent) {
        if (keyEvent.key === 'Escape') { hideLightbox(); }
    });
}

// ─── Utility Helpers ──────────────────────────────────────────────────────────

/**
 * Checks if an image URL ends with .png (case-insensitive).
 *
 * @param {string|null} imageUrl
 * @returns {boolean}
 */
function isPngImage(imageUrl) {
    if (!imageUrl) { return false; }
    return imageUrl.toLowerCase().endsWith('.png');
}

/**
 * @param {string} rawText
 * @returns {string}
 */
function escapeHtml(rawText) {
    return String(rawText).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

/**
 * @param {string} rawText
 * @returns {string}
 */
function escapeHtmlAttribute(rawText) {
    return String(rawText).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

// ─── Bootstrap ───────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', initializeColorLab);
