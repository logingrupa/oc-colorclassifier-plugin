---
phase: quick-8
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - assets/css/color-lab.css
  - assets/js/color-lab.js
autonomous: true
requirements: [QUICK-8]
must_haves:
  truths:
    - "View Product link is visually right-aligned within the detail card body"
    - "Clicking the link shows an inline spinner animation while navigating"
    - "Spinner replaces the arrow text, link remains clickable area"
  artifacts:
    - path: "assets/css/color-lab.css"
      provides: "Right-alignment for link, spinner keyframes, loading state styles"
      contains: "@keyframes color-lab-spin"
    - path: "assets/js/color-lab.js"
      provides: "Click handler that swaps link content to spinner on click"
  key_links:
    - from: "assets/js/color-lab.js"
      to: "assets/css/color-lab.css"
      via: "Adding --loading class on click triggers CSS spinner"
      pattern: "--loading"
---

<objective>
Align the "View Product" link to the right side of the detail card and add a loading spinner on click.

Purpose: The link currently sits left-aligned and gives no feedback during the ~4s page navigation. Right-alignment improves visual hierarchy (action button at the end), and the spinner communicates that the click registered and the page is loading.

Output: Updated CSS with right-alignment + spinner animation, updated JS with click handler.
</objective>

<execution_context>
@C:/Users/rolan/.claude/get-shit-done/workflows/execute-plan.md
@C:/Users/rolan/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@assets/css/color-lab.css
@assets/js/color-lab.js
</context>

<tasks>

<task type="auto">
  <name>Task 1: Right-align link and add spinner CSS</name>
  <files>assets/css/color-lab.css</files>
  <action>
1. Make `.color-lab__detail-body` a flex column so the link can be pushed right:
   - Add `display: flex; flex-direction: column;` to `.color-lab__detail-body` (line 950).
   - Children are block-level already so this won't break layout.

2. Right-align the link by adding `align-self: flex-end;` to `.color-lab__detail-product-link` (line 1034). Also add `margin-top: auto;` so it sticks to the bottom of the body when there's extra space. Keep `display: inline-block` so it only takes up content width.

3. Add a `@keyframes color-lab-spin` animation (simple 360deg rotate, linear, infinite, 0.7s duration) immediately after the `.color-lab__detail-product-link:hover` rule block (after line 1051).

4. Add a `.color-lab__detail-product-link--loading` modifier class:
   - `pointer-events: none;` (prevent double-click)
   - `opacity: 0.85;`

5. Add `.color-lab__detail-product-link--loading .color-lab__spinner` styles:
   - `display: inline-block; width: 14px; height: 14px;`
   - `border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff;`
   - `border-radius: 50%;`
   - `animation: color-lab-spin 0.7s linear infinite;`
   - `vertical-align: middle; margin-left: 0.4em;`
  </action>
  <verify>
    <automated>grep -n "align-self.*flex-end" assets/css/color-lab.css && grep -n "color-lab-spin" assets/css/color-lab.css && grep -n "color-lab__spinner" assets/css/color-lab.css</automated>
  </verify>
  <done>Link is right-aligned via align-self, spinner keyframe and loading modifier class exist in CSS.</done>
</task>

<task type="auto">
  <name>Task 2: Add spinner click handler and update link HTML</name>
  <files>assets/js/color-lab.js</files>
  <action>
1. In the `showDetailCard` function, update the link HTML template (line 1048) to include a spinner element that is hidden by default. Change the link markup from:
   `View Product &amp;rarr;`
   to:
   `View Product &amp;rarr;<span class="color-lab__spinner" style="display:none"></span>`
   The spinner span is hidden initially via inline `display:none` and only shown when the loading class is added.

2. After the card is appended to the DOM (after line 1051), attach a click handler to the product link inside the card. Create a small helper function `attachProductLinkLoadingHandler(cardElement)` that:
   - Queries `.color-lab__detail-product-link` within `cardElement`
   - If found, adds a `click` event listener that:
     a. Adds class `color-lab__detail-product-link--loading` to the link
     b. Changes the link's text content: hide the arrow text, show the spinner. Set `link.innerHTML = 'Loading&hellip; <span class="color-lab__spinner"></span>'` (spinner is visible because the --loading modifier makes `.color-lab__spinner` display inline-block)
     c. Does NOT call `preventDefault()` — the browser navigates normally, the spinner is just visual feedback during the ~4s load

3. Call `attachProductLinkLoadingHandler(cardElement)` right after `attachDetailCardSwipeHandler(cardElement)` on line 1055.

Note: The spinner visibility is controlled by the CSS `.color-lab__detail-product-link--loading .color-lab__spinner` rule which sets `display: inline-block`, overriding the initial inline `display:none`. This way the initial render shows no spinner, and the loading state shows it.

Actually, simplify: since the click handler replaces innerHTML entirely (step 2b), the initial hidden spinner span in the template (step 1) is unnecessary. Instead:
- Step 1: Keep the link HTML as-is: `View Product &amp;rarr;` (no change to template)
- Step 2b: On click, set `link.innerHTML = 'Loading&hellip; <span class="color-lab__spinner"></span>'` and add the `--loading` class

This is cleaner — the spinner element only exists after click.

Update the CSS from Task 1 accordingly: the `.color-lab__spinner` styles should NOT be nested under `--loading`. Make `.color-lab__spinner` a standalone class:
   - `display: inline-block; width: 14px; height: 14px;`
   - `border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff;`
   - `border-radius: 50%;`
   - `animation: color-lab-spin 0.7s linear infinite;`
   - `vertical-align: middle; margin-left: 0.4em;`
  </action>
  <verify>
    <automated>grep -n "attachProductLinkLoadingHandler" assets/js/color-lab.js && grep -n "loading" assets/js/color-lab.js | head -5</automated>
  </verify>
  <done>Clicking "View Product" immediately shows "Loading..." with a spinning animation. Browser navigation proceeds normally. No double-click possible due to pointer-events:none on the loading state.</done>
</task>

</tasks>

<verification>
1. Open the color lab page, click any swatch with a product link to show the detail card.
2. Confirm the "View Product" link is right-aligned within the detail card body.
3. Click "View Product" — link text should change to "Loading..." with a spinning circle animation.
4. The page should navigate normally to the product detail page.
5. Verify mobile bottom sheet still shows the link correctly (right-aligned, spinner works).
</verification>

<success_criteria>
- "View Product" link is flush-right in the detail card body
- Clicking the link shows "Loading..." with an animated spinner inline
- Link is non-clickable (pointer-events: none) once loading state is active
- Normal page navigation is not interrupted
- Works on both desktop card and mobile bottom sheet
</success_criteria>

<output>
After completion, create `.planning/quick/8-align-view-product-link-right-and-add-lo/8-SUMMARY.md`
</output>
