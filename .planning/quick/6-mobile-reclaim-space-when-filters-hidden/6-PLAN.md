---
phase: quick-6
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - plugins/logingrupa/colorclassifier/assets/css/color-lab.css
  - plugins/logingrupa/colorclassifier/assets/js/color-lab.js
autonomous: true
requirements: [MOBILE-FILTER-OVERLAY]
must_haves:
  truths:
    - "On mobile, filter sidebar is hidden by default and content occupies full vertical space"
    - "On mobile, a filter toggle button is visible in the header area"
    - "Tapping the toggle opens filters as a fixed overlay that drops down from the header"
    - "Tapping the toggle again or changing a filter auto-closes the overlay"
    - "Desktop sidebar behavior is completely unchanged"
  artifacts:
    - path: "plugins/logingrupa/colorclassifier/assets/css/color-lab.css"
      provides: "Mobile overlay styles for sidebar, repositioned toggle button"
      contains: "color-lab__sidebar--mobile-open"
    - path: "plugins/logingrupa/colorclassifier/assets/js/color-lab.js"
      provides: "Mobile-aware sidebar toggle with auto-close on filter change"
      contains: "isMobileViewport"
  key_links:
    - from: "color-lab.js toggle listener"
      to: "color-lab.css mobile overlay classes"
      via: "classList toggle of color-lab__sidebar--mobile-open"
      pattern: "sidebar--mobile-open"
    - from: "color-lab.js handleFilterChange"
      to: "mobile sidebar close"
      via: "closeMobileSidebar call after filter state update"
      pattern: "closeMobileSidebar"
---

<objective>
Convert the mobile filter sidebar from a static in-flow block to a fixed overlay that drops down from the header, hidden by default. This reclaims all vertical space for the grid/3D content on mobile devices.

Purpose: On mobile (<=768px), the filter sidebar sits above content as a static block, stealing vertical space even when collapsed (the sidebar element + filter-header remain in flow). Moving filters to a fixed overlay hidden by default gives content the full viewport.

Output: Updated CSS with mobile overlay positioning and updated JS with mobile-aware toggle logic and auto-close behavior.
</objective>

<execution_context>
@C:/Users/rolan/.claude/get-shit-done/workflows/execute-plan.md
@C:/Users/rolan/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@plugins/logingrupa/colorclassifier/assets/css/color-lab.css
@plugins/logingrupa/colorclassifier/assets/js/color-lab.js
@plugins/logingrupa/colorclassifier/views/color-lab.blade.php

<interfaces>
<!-- Current mobile CSS (lines 1026-1081): -->
<!-- .color-lab__body: grid-template-columns: 1fr (single column) -->
<!-- .color-lab__sidebar: position: static, height: auto, border-bottom -->
<!-- .color-lab__header: sticky, top:0, z-index:20, height:auto on mobile -->
<!-- .color-lab__sidebar-toggle: absolute positioned in .color-lab__body at top:0.5rem, left:sidebar-width -->

<!-- Current JS sidebar toggle (lines 1171-1184): -->
<!-- attachSidebarToggleListener() toggles class 'color-lab__body--sidebar-collapsed' on .color-lab__body -->
<!-- Toggle button innerHTML switches between hamburger and arrow -->

<!-- Filter change handler (line 424): handleFilterChange() is called on every checkbox change -->
<!-- It calls applyFilters(), renderActiveView(), updateFilterBadge(), updateUrlParameters() -->

<!-- Initialization (line 235): attachSidebarToggleListener() called during initializeColorLab() -->
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add mobile overlay CSS for sidebar and reposition toggle into header</name>
  <files>plugins/logingrupa/colorclassifier/assets/css/color-lab.css</files>
  <action>
Inside the existing `@media (max-width: 768px)` block (starts at line 1026), replace/update the mobile sidebar rules with the following approach:

1. **Hide sidebar from document flow by default on mobile:**
   Replace the existing `.color-lab__sidebar` mobile rule (lines 1039-1045) with:
   ```css
   .color-lab__sidebar {
       position: fixed;
       top: 0;
       left: 0;
       right: 0;
       z-index: 19;
       height: auto;
       max-height: 75vh;
       overflow-y: auto;
       border-right: none;
       border-bottom: 1px solid var(--color-lab-color-border);
       background: #ffffff;
       box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
       transform: translateY(-100%);
       transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
       pointer-events: none;
   }
   ```
   The `top: 0` + `transform: translateY(-100%)` slides it fully off-screen above. z-index 19 puts it below the header (z-index 20) but above content.

2. **Show sidebar when open class is present:**
   ```css
   .color-lab__sidebar--mobile-open {
       transform: translateY(0);
       pointer-events: auto;
   }
   ```

3. **Reposition the sidebar toggle button into the header area on mobile:**
   Replace existing `.color-lab__sidebar-toggle` positioning. The button is inside `.color-lab__body` (absolute positioned) but on mobile we need it visible in the header zone. Use fixed positioning:
   ```css
   .color-lab__sidebar-toggle {
       position: fixed;
       top: 0.6rem;
       right: 0.75rem;
       left: auto;
       z-index: 21;
       border-radius: 6px;
       width: 36px;
       height: 36px;
   }
   ```
   z-index 21 puts it above the sticky header (20). It sits at top-right of viewport, overlapping the header visually.

4. **Override the collapsed state rules for mobile** — the desktop `--sidebar-collapsed` class should be inert on mobile since we use `--mobile-open` instead:
   ```css
   .color-lab__body--sidebar-collapsed .color-lab__sidebar-toggle {
       left: auto;
   }
   .color-lab__body--sidebar-collapsed .color-lab__sidebar {
       opacity: 1;
       padding: 1rem;
       pointer-events: none;
   }
   ```

5. **Remove the old mobile collapsed rule** (`.color-lab__sidebar--collapsed .color-lab__filter-groups { display: none }` at line 1047-1049) as it is no longer needed — the sidebar is hidden entirely via transform.

6. **Add a backdrop overlay when sidebar is open:**
   ```css
   .color-lab__sidebar-backdrop {
       display: none;
   }
   .color-lab__sidebar-backdrop--visible {
       display: block;
       position: fixed;
       inset: 0;
       z-index: 18;
       background: rgba(0, 0, 0, 0.3);
   }
   ```
   The backdrop sits below sidebar (19) and header (20) to dim content and act as a close target.

7. **Adjust header to accommodate the toggle button on right:**
   The header on mobile already has `flex-direction: column; align-items: flex-start` — the view toggle buttons will still flow normally. The sidebar toggle sits fixed at top-right overlapping the header, so no header layout changes needed. But add right padding to prevent the toggle from overlapping the title:
   ```css
   .color-lab__header {
       padding-right: 3.5rem;
   }
   ```
   (Add to existing mobile header rule, merging with existing padding.)

Keep ALL desktop styles completely untouched. Only modify within the `@media (max-width: 768px)` block. The `.color-lab__sidebar-backdrop` rule outside the media query uses `display: none` as base, and the visible state is only inside the media query.
  </action>
  <verify>
    <automated>cd C:/laragon/www/nailolab/plugins/logingrupa/colorclassifier && grep -c "sidebar--mobile-open" assets/css/color-lab.css && grep -c "sidebar-backdrop" assets/css/color-lab.css && grep -c "translateY(-100%)" assets/css/color-lab.css</automated>
  </verify>
  <done>Mobile sidebar is styled as a fixed overlay hidden off-screen by default, with a new `--mobile-open` class to reveal it. Toggle button repositioned to top-right fixed position visible in header area. Backdrop overlay added. Desktop sidebar is untouched.</done>
</task>

<task type="auto">
  <name>Task 2: Make sidebar toggle mobile-aware with auto-close on filter change</name>
  <files>plugins/logingrupa/colorclassifier/assets/js/color-lab.js</files>
  <action>
Make three targeted changes to the JS:

1. **Add a mobile viewport helper** (above `attachSidebarToggleListener`, around line 1170):
   ```javascript
   /** @returns {boolean} */
   function isMobileViewport() {
       return window.matchMedia('(max-width: 768px)').matches;
   }
   ```

2. **Add backdrop element creation and mobile sidebar open/close helpers** (below the helper):
   ```javascript
   /** @returns {void} */
   function openMobileSidebar() {
       var sidebar = document.querySelector('.color-lab__sidebar');
       var toggle = document.querySelector('.color-lab__sidebar-toggle');
       if (!sidebar) { return; }

       sidebar.classList.add('color-lab__sidebar--mobile-open');
       toggle.innerHTML = '&#10005;';
       toggle.title = 'Close filters';

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
       var toggle = document.querySelector('.color-lab__sidebar-toggle');
       if (!sidebar) { return; }

       sidebar.classList.remove('color-lab__sidebar--mobile-open');
       if (toggle) {
           toggle.innerHTML = '&#9776;';
           toggle.title = 'Show filters';
       }

       var backdrop = document.querySelector('.color-lab__sidebar-backdrop');
       if (backdrop) {
           backdrop.classList.remove('color-lab__sidebar-backdrop--visible');
       }
   }
   ```

3. **Rewrite `attachSidebarToggleListener`** (lines 1171-1184) to branch on viewport:
   ```javascript
   /** @returns {void} */
   function attachSidebarToggleListener() {
       var toggleButton = document.querySelector('.color-lab__sidebar-toggle');
       if (!toggleButton) { return; }

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
   ```

4. **Add auto-close on filter change for mobile.** In the `handleFilterChange` function (line 424), after the existing calls to `applyFilters()`, `renderActiveView()`, `updateFilterBadge()`, and `updateUrlParameters()` (lines 444-447), add:
   ```javascript
   if (isMobileViewport()) {
       closeMobileSidebar();
   }
   ```
   This auto-closes the filter overlay when the user taps a filter option on mobile, giving immediate visual feedback of the filter effect.

5. **Ensure mobile starts with sidebar hidden.** In `initializeColorLab()` (around line 235 where `attachSidebarToggleListener()` is called), immediately after that call add:
   ```javascript
   if (isMobileViewport()) {
       var bodyElement = document.querySelector('.color-lab__body');
       if (bodyElement) {
           bodyElement.classList.remove('color-lab__body--sidebar-collapsed');
       }
   }
   ```
   This ensures the desktop collapsed class is removed on mobile so it does not interfere with the mobile overlay approach. The sidebar is hidden by CSS transform by default on mobile regardless.

Desktop behavior must remain completely unchanged. The `isMobileViewport()` check ensures all new logic only activates at <=768px.
  </action>
  <verify>
    <automated>cd C:/laragon/www/nailolab/plugins/logingrupa/colorclassifier && grep -c "isMobileViewport" assets/js/color-lab.js && grep -c "closeMobileSidebar" assets/js/color-lab.js && grep -c "openMobileSidebar" assets/js/color-lab.js</automated>
  </verify>
  <done>Sidebar toggle is mobile-aware: on mobile it opens/closes a fixed overlay via CSS class toggle; on desktop it uses the existing grid collapse. Filter changes auto-close the mobile overlay. Mobile initializes with sidebar hidden.</done>
</task>

</tasks>

<verification>
1. On desktop (>768px): sidebar toggle collapses/expands sidebar exactly as before. No visual or behavioral changes.
2. On mobile (<=768px):
   - Page loads with sidebar hidden, content fills full vertical space
   - Filter toggle button visible at top-right of header area
   - Tapping toggle slides sidebar down from top as overlay
   - Backdrop dims content behind sidebar
   - Tapping backdrop or toggle again closes sidebar
   - Tapping any filter option closes sidebar automatically
   - Grid and 3D views have full vertical space when sidebar is closed
</verification>

<success_criteria>
- Mobile sidebar hidden by default with zero vertical space consumed
- Toggle button accessible in header area on mobile
- Sidebar opens as fixed overlay, does not push content down
- Auto-close on filter change provides immediate feedback
- Desktop behavior completely unaffected
</success_criteria>

<output>
After completion, create `.planning/quick/6-mobile-reclaim-space-when-filters-hidden/6-SUMMARY.md`
</output>
