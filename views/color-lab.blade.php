<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $colorLabPageTitle }} | Color Classifier</title>
    <link rel="stylesheet" href="/plugins/logingrupa/colorclassifier/assets/css/color-lab.css">
</head>
<body style="margin:0;background:#fff;font-family:'Didact Gothic',sans-serif;">

<section class="color-lab" data-product-url="{{ $colorLabProductUrl }}">
    <div class="color-lab__header">
        <div class="color-lab__header-left">
            <h1 class="color-lab__title">{{ $colorLabPageTitle }}</h1>
            <p class="color-lab__subtitle">OKLCH Color Space Explorer — {{ $colorLabEntryCount }} product colors</p>
        </div>
        <div class="color-lab__view-toggle">
            <button class="color-lab__view-button color-lab__view-button--active" data-view="grid">Color Grid</button>
            <button class="color-lab__view-button" data-view="scatter">3D Explorer</button>
        </div>
    </div>
    <div class="color-lab__body">
        <button class="color-lab__sidebar-toggle" aria-label="Toggle filters" title="Toggle filters">&#9776;</button>
        <aside class="color-lab__sidebar">
            <div class="color-lab__filter-header">
                <h2 class="color-lab__filter-title">
                    Filters <span class="color-lab__filter-badge" hidden>0</span>
                </h2>
                <button class="color-lab__clear-button" hidden>Clear All</button>
            </div>
            <div class="color-lab__filter-groups"></div>
        </aside>
        <main class="color-lab__content">
            <div class="color-lab__grid-view" id="colorLabGrid"></div>
            <div class="color-lab__scatter-view" id="colorLabScatter" hidden></div>
        </main>
    </div>
</section>

@if($colorLabPlotlyCdn)
    <script src="{{ $colorLabPlotlyCdn }}"></script>
@endif

<script>
    window.__COLOR_LAB_DATA__     = {!! $colorLabEntriesJson !!};
    window.__COLOR_LAB_TAXONOMY__ = {!! $colorLabTaxonomyJson !!};
</script>
<script src="/plugins/logingrupa/colorclassifier/assets/js/color-lab.js"></script>

</body>
</html>
