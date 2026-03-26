---
phase: quick-5
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - plugins/logingrupa/colorclassifier/assets/css/color-lab.css
autonomous: true
requirements: [QUICK-5]

must_haves:
  truths:
    - "On mobile (<=768px), the 3D scatter plot fills most of the viewport below header/filters"
    - "On desktop, the scatter view layout is unchanged"
    - "The plot remains usable — no clipping or overflow issues"
  artifacts:
    - path: "plugins/logingrupa/colorclassifier/assets/css/color-lab.css"
      provides: "Updated mobile scatter view height"
      contains: "calc(100vh - 160px)"
  key_links:
    - from: "color-lab.css @media(max-width:768px)"
      to: ".color-lab__scatter-view"
      via: "height override"
      pattern: "color-lab__scatter-view.*height"
---

<objective>
Make the 3D scatter plot take up most of the viewport on mobile devices instead of the current cramped 60vh.

Purpose: On mobile, the Color Lab 3D view is too small — the plot only gets 60vh while the header and collapsed filters use minimal space above, leaving wasted whitespace below the plot.
Output: Updated mobile CSS so the scatter view fills the available viewport.
</objective>

<execution_context>
@C:/Users/rolan/.claude/get-shit-done/workflows/execute-plan.md
@C:/Users/rolan/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@plugins/logingrupa/colorclassifier/assets/css/color-lab.css
</context>

<tasks>

<task type="auto">
  <name>Task 1: Increase mobile 3D scatter view height</name>
  <files>plugins/logingrupa/colorclassifier/assets/css/color-lab.css</files>
  <action>
In the `@media (max-width: 768px)` block (line 1020), update the `.color-lab__scatter-view` rule (lines 1058-1061) from:

```css
.color-lab__scatter-view {
    height: 60vh;
    min-height: 350px;
}
```

to:

```css
.color-lab__scatter-view {
    height: calc(100vh - 160px);
    min-height: 400px;
}
```

Rationale for `calc(100vh - 160px)`:
- The mobile header (`.color-lab__header` with `padding: 1rem` and stacked layout) takes roughly 80-100px
- The sidebar/filter bar when collapsed takes roughly 40-50px
- 160px provides breathing room so the plot does not overflow the viewport
- This mirrors the desktop approach (`calc(100vh - 200px)`) but with a smaller offset since mobile has less chrome

Do NOT change the desktop rule at line 645-649. Only modify the mobile media query override.
  </action>
  <verify>
    <automated>grep -A3 "color-lab__scatter-view" plugins/logingrupa/colorclassifier/assets/css/color-lab.css | grep -q "calc(100vh - 160px)" && echo "PASS" || echo "FAIL"</automated>
  </verify>
  <done>Mobile scatter view uses `calc(100vh - 160px)` with `min-height: 400px`. Desktop rule unchanged at `calc(100vh - 200px)` with `min-height: 500px`.</done>
</task>

</tasks>

<verification>
1. Desktop check: Open Color Lab at `/tools/color-lab` on desktop browser — scatter view should be unchanged (`calc(100vh - 200px)`)
2. Mobile check: Open same page with mobile viewport (375px wide) — scatter view should fill most of the screen below header/filters
3. CSS validation: Only the mobile media query block was modified; no other rules affected
</verification>

<success_criteria>
- The 3D scatter plot on mobile viewports (<=768px) uses `calc(100vh - 160px)` instead of `60vh`
- The min-height is increased from 350px to 400px
- Desktop layout is unaffected
- No visual overflow or clipping on common mobile sizes (375px, 390px, 414px width)
</success_criteria>

<output>
After completion, create `.planning/quick/5-on-mobile-devices-3d-view-is-too-small-m/5-SUMMARY.md`
</output>
