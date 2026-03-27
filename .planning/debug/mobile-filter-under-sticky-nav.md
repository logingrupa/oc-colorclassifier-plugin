---
status: awaiting_human_verify
trigger: "mobile-filter-under-sticky-nav"
created: 2026-03-26T00:00:00Z
updated: 2026-03-26T00:00:00Z
---

## Current Focus

hypothesis: openMobileSidebar() computes sidebar top from header.offsetHeight (element height = 80px) instead of header.getBoundingClientRect().bottom (actual viewport position). When scrolled to top, the site navbar sits above the color-lab header, so the header's actual bottom is at ~navHeight + 80px, but the sidebar is placed at just 80px, landing under the site nav.
test: Replace header.offsetHeight with header.getBoundingClientRect().bottom in openMobileSidebar()
expecting: Sidebar top will always equal the actual bottom edge of the color-lab header in the viewport, regardless of scroll position or site nav visibility
next_action: Apply fix to color-lab.js

## Symptoms

expected: Filter sidebar should appear starting from the bottom edge of the sticky nav, never overlapping with it
actual: Filter sidebar renders behind/under the sticky nav when scrolled to top — the nav covers the top portion of the filter
errors: No JS errors — purely a CSS stacking/positioning issue
reproduction: Open color lab on mobile, tap Filters button, scroll to top — nav overlaps the filter sidebar
started: After recent mobile filter overlay changes (quick-6)

## Eliminated

- hypothesis: The sidebar has a lower z-index (19) than the site nav (99) causing stacking overlap
  evidence: The site navbar is position:relative (not fixed/sticky), so z-index stacking order is not the cause — the navbar scrolls away with the page
  timestamp: 2026-03-26T00:00:00Z

- hypothesis: CSS top value in the mobile media query is wrong
  evidence: The mobile CSS rule has no explicit top — it inherits from desktop rule (top: var(--color-lab-header-height) = 80px). The JS in openMobileSidebar() overrides this with sidebar.style.top = header.offsetHeight. So CSS top is irrelevant once the sidebar opens.
  timestamp: 2026-03-26T00:00:00Z

## Evidence

- timestamp: 2026-03-26T00:00:00Z
  checked: assets/css/color-lab.css mobile media query (line 1127)
  found: .color-lab__sidebar on mobile has position:fixed, z-index:19, no explicit top value — inherits desktop top:var(--color-lab-header-height) = 80px
  implication: CSS top is overridden by inline style set in JS anyway

- timestamp: 2026-03-26T00:00:00Z
  checked: assets/js/color-lab.js openMobileSidebar() (line 1386-1389)
  found: sidebar.style.top = header.offsetHeight + 'px' where header = .color-lab__header
  implication: offsetHeight returns the element's intrinsic height (80px per --color-lab-header-height), NOT its position from viewport top. When at page top, the site navbar pushes the color-lab header down, so header bottom is at navHeight+80px, but sidebar is positioned at 80px — landing under the site nav.

- timestamp: 2026-03-26T00:00:00Z
  checked: themes/logingrupa-nailolab/assets/src/css/style.css .navbar rule (line 698)
  found: .navbar { position: relative } — the site navbar is NOT fixed/sticky. The nav-scroll (fixed) variant is commented out in custom.js.
  implication: Overlap happens because header.offsetHeight misses the navbar's contribution to the header's viewport position, not because of z-index stacking.

- timestamp: 2026-03-26T00:00:00Z
  checked: .color-lab__header CSS (line 67-80)
  found: position:sticky; top:0; z-index:20; height:var(--color-lab-header-height) = 80px
  implication: The header sticks at viewport top:0 AFTER the user scrolls past it. Before scrolling, it sits below the static site navbar. getBoundingClientRect().bottom gives the correct value in both states.

## Resolution

root_cause: In openMobileSidebar(), sidebar.style.top is set to header.offsetHeight (the element's own height = 80px) instead of header.getBoundingClientRect().bottom (the element's actual bottom position in the viewport). When the page is at the top and the site navbar is visible above the color-lab section, the color-lab header's bottom edge is at viewport position (siteNavHeight + 80px), but the sidebar is incorrectly positioned at just 80px — causing it to appear under the site navbar.
fix: Changed header.offsetHeight to header.getBoundingClientRect().bottom in openMobileSidebar() — now uses actual viewport position of the header's bottom edge instead of the element's intrinsic height
verification: awaiting human confirmation
files_changed:
  - assets/js/color-lab.js
