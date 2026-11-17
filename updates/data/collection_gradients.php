<?php

/**
 * Swatch gradients for the 1C collections, keyed by collection slug, picked
 * from each collection's offer photos (2026-11-17). Applied once by
 * seed_collection_gradients.php; the Families page edits them after that.
 */
return [
    'disco' => [
        'type'  => 'conic',
        'angle' => 0,
        'stops' => ['#f3eefb', '#6a3fa0', '#f9d9ea', '#2340c0', '#e3f4f7', '#8b1a3a', '#f3eefb'],
    ],
    'magnetic-disco' => [
        'type'  => 'linear',
        'angle' => 135,
        'stops' => ['#14141c', '#3b2470', '#c48af2', '#f3cf73', '#2e74b5', '#14141c'],
    ],
    'galaxy' => [
        'type'  => 'radial',
        'angle' => 0,
        'stops' => ['#ffffff', '#f08ad8', '#9a7ff0', '#3f74c8', '#2a2370'],
    ],
    'holo' => [
        'type'  => 'conic',
        'angle' => 45,
        'stops' => ['#b9a6cf', '#83d3dc', '#f2e2b9', '#f2b8d9', '#b9a6cf'],
    ],
    'lolipop' => [
        'type'  => 'conic',
        'angle' => 0,
        'stops' => ['#ec8fd6', '#fff6fb', '#f3df6a', '#fff6fb', '#8fd6b9', '#fff6fb', '#ec8fd6'],
    ],
    'rainbow' => [
        'type'  => 'linear',
        'angle' => 135,
        'stops' => ['#e8344e', '#f28c28', '#f6d13a', '#3cbf6e', '#2f7fe0', '#8a4fd0'],
    ],
    'sandy' => [
        'type'  => 'linear',
        'angle' => 45,
        'stops' => ['#b3a2d6', '#e2dca6', '#e3b0dc', '#f1b8c6'],
    ],
    'satin' => [
        'type'  => 'linear',
        'angle' => 115,
        'stops' => ['#8c6b6b', '#f1e4df', '#b08f8a', '#f6ece8', '#7a5658'],
    ],
    'shimmer' => [
        'type'  => 'radial',
        'angle' => 0,
        'stops' => ['#f8dca2', '#da7a3b', '#8e1c12', '#4a2526'],
    ],
    'vitrage' => [
        'type'  => 'conic',
        'angle' => 20,
        'stops' => ['#a00d14', '#c7801f', '#0a6b52', '#c97ad0', '#e5585f', '#a00d14'],
    ],
    'aurora' => [
        'type'  => 'linear',
        'angle' => 160,
        'stops' => ['#123a2c', '#2bc08a', '#3a8fc0', '#6a3fb0', '#c0405e'],
    ],
    'neon' => [
        'type'  => 'linear',
        'angle' => 90,
        'stops' => ['#ff2e88', '#ffe600', '#39ff14', '#00e5ff'],
    ],
];
