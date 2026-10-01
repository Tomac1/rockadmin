# Interlude — adopting the Rock Admin design system

Not a milestone: no new capability ships. Every page the admin already renders
comes out looking like `docs/design-system/`, and the templates stop being
Bootstrap with a theme over it.

## What is actually there

- **`components/components.css`** — 55 kB, 426 rules, 33 `ra-` blocks in BEM.
  Plain CSS, no build step. This is the substance.
- **`tokens/*.css`** — colours, type, spacing, shadows, borders.
- **`components/*.jsx`, `_ds_bundle.js`, `rock-admin-standalone.html`** — a
  React preview. **Not used.** Rule 1 has not moved; this project takes the CSS
  and the markup shapes, never the components.
- **`ui_kits/rock_admin/screens/*.jsx`** — six screens, including
  `ProductList` and `FormAdd`, which are the shapes this project needs most.
  Read for markup, not imported.

## Decisions this plan makes

**Bootstrap goes.** The design system covers all 33 blocks this admin uses —
table, button, field, card, modal, offcanvas, toast, alert, dropdown, nav.
Keeping both means two stylesheets competing for the same elements, and
`assets/css/rockadmin.css` is 1278 lines of Bootstrap theming that becomes
dead the moment the templates stop emitting Bootstrap's classes. 29 of 55
templates reference them today.

This also removes 309 kB of vendored CSS and JS, and the dependency on
Bootstrap's JavaScript for toasts, modals and dropdowns — which `core.js`
will have to carry instead, and that is the real cost, named here rather than
discovered in task 6.

**Both `ra-` vocabularies stay, on the same element.** The design system names
what a thing *looks like* (`ra-table`, `ra-td`, `ra-badge--green`). Rule 6
requires every element to carry what a thing *is* (`ra-grid-cell-price`),
because that is the override path, the test hook and the project's own CSS
selector. So a cell is `class="ra-td ra-grid-cell ra-grid-cell-price"`. Neither
vocabulary is renamed: renaming the project's would break every template
override a project has written, and renaming the design system's would make it
impossible to take an update.

**The shell gains a second navigation bar.** The design is two-level — a dark
`ra-topnav` carrying brand, workspace switcher and account, and a darker
`ra-secnav` carrying the page menu with count badges. RockAdmin's shell has
one. This is a template and `ShellView` change, not a stylesheet one, and it
is the largest structural piece of this interlude.

**The workspace switcher is drawn and not wired.** It is in the design's top
bar, and workspaces are a deferred milestone. Drawing a dead control would be
the "visible button that does nothing" this project refuses. So the shell
renders it only when a workspace list is supplied, and nothing supplies one
yet — the markup exists, the control does not appear.

**Dark mode is not in the design system.** The current theme supports it and
measures its contrast in both. The design system ships one palette. **This is
an open question, not a decision**: either dark mode is rebuilt on the design
system's tokens, or it is dropped and `theme.dark` goes with it. Recorded in
the open questions below rather than settled quietly.

## The open question that needs an answer before task 2

**The fonts.** `tokens/typography.css` opens with

```css
@import url('https://fonts.googleapis.com/css2?family=Inter...&family=JetBrains+Mono...');
```

and `CLAUDE.md` says `assets/` carries **no CDN**. The design system ships no
font files — `assets/` in it holds three SVGs. So there are three ways and
each costs something:

1. **Vendor Inter and JetBrains Mono** as woff2 in `assets/fonts/`. Both are
   SIL Open Font License, so redistribution is allowed with the licence file.
   Keeps the design exactly. Costs roughly 300–800 kB in the repository, and
   somebody has to put the files there — this cannot be fetched from a CDN by
   the thing that is not allowed to use one.
2. **Keep the `@import`.** The design is exact and the rule is broken, on
   every page load, for every installation — including ones behind a firewall
   where the font simply fails and the layout shifts.
3. **System stack.** Costs nothing and loses the typography the design is
   partly made of. Inter's metrics are not Segoe UI's.

Recommendation: 1. It is the only one that satisfies both the design and the
rule, and the cost is a one-off.

---

### Task 1: Vendor the system

Copy `tokens/*.css` and `components/components.css` into `assets/css/`, with
`styles.css` as the entry. Nothing is edited on the way in — an edited copy
cannot take an update, and the first thing anyone will want is an update.
Project-specific overrides live in a separate file that loads after.

Remove `assets/vendor/bootstrap/`. Delete the parts of
`assets/css/rockadmin.css` that theme Bootstrap; keep only what the design
system does not cover, and say in a comment why each survivor survived.

`Assets` already emits the stylesheet list; it gains the new files and loses
Bootstrap.

### Task 2: The shell

`templates/layout/base.php` becomes `ra-topnav` + `ra-secnav`. `ShellView`
grows what the second bar needs — menu items with optional counts — and the
workspace switcher is rendered only when given a list.

The account menu, the sign-out form and its CSRF token stay exactly as they
are; only their markup changes.

### Task 3: The grid

`templates/region/list/` onto `ra-table`, `ra-th`, `ra-td`, `ra-table-toolbar`,
`ra-table-search`, `ra-badge`. This is the page the whole product is for, and
`ui_kits/rock_admin/screens/ProductList.jsx` is the reference.

**The header and body must still agree about cell count** — this project has
shipped that mismatch twice and caught it in RED once. Assert it through the
DOM for a viewer and for an editor, as milestone 5 does.

### Task 4: Forms, the login page, and the detail panel

`templates/region/form/`, `templates/page/login.php`, `region/preview/` onto
`ra-field`, `ra-input`, `ra-select`, `ra-checkbox`, `ra-card`.
`ui_kits/rock_admin/screens/FormAdd.jsx` and `Login.jsx` are the references.

### Task 5: Feedback

Toasts, alerts and the error page onto `ra-toast` and `ra-alert`. **The toast
must still be visible and must still not cover the navigation** — milestone 4
shipped an invisible one and milestone 7 shipped one that covered every
navigation link, and both passed their tests.

### Task 6: The JavaScript Bootstrap was doing

Toast dismissal, dropdown open/close, modal and offcanvas. `core.js` carries
it, bound by delegation through `data-ra-*`, because rule 6 forbids a script
tag in a template and the markup arrives by AJAX.

This is the task where removing Bootstrap stops being free.

### Task 7: Looking at all of it

Every page, in a browser, measured. Contrast from computed styles on the live
page. The demo and the real project both — the real project is where a
twenty-two column grid actually exists.

## Acceptance

1. `composer run check` green on both drivers.
2. No Bootstrap in `assets/`, and no Bootstrap class in any template.
3. Every element carries both vocabularies; `TemplateStandardsTest` enforces
   the project's half.
4. No CDN request on any page load.
5. Header and body cell counts agree, for a viewer and for an editor.
6. Toasts visible, and no toast covers a navigation link.
7. Contrast measured on the live page, both the demo and the real project.
8. Everything works with JavaScript disabled.

---

## Where it ended up (2026-10-01)

All seven tasks are done and committed. What the plan did not predict is
worth recording, because all of it was the same mistake in different places.

**One mistake, five times.** Bootstrap hid a component until a class said to
show it; the design system shows a component because it is in the document.
Every component whose visibility used to be Bootstrap's business came out
visible when Bootstrap went:

- the offcanvas, as a 420px panel down the right of every page;
- the modal, as a band across the top;
- the submenu, hanging open under every parent item that had children;
- and, going the other way a milestone earlier, the toast that nobody could
  see for a milestone because Bootstrap was hiding it.

The markup is identical in both systems and says nothing about which. There
is no test for this; the only thing that found any of them was looking at the
page.

**Two class lists were composed in PHP, so no test saw them.** The page
header's buttons rendered `btn btn-primary`, 16px tall, and a menu item
carried `nav-link`. `ButtonModifierTest` reads templates. It now has a second
test that calls `ButtonView::classes()` directly.

**`ra-ra-input`.** A doubled prefix from a bad mechanical edit, in five
control templates. It names nothing, so those five inputs were drawn by the
browser alone, and nothing failed.

**Sizes are modifiers, not defaults.** `.ra-btn` has no height, `.ra-modal`
no max-width, and `.ra-input` needs its wrapper for `appearance: none` to not
leave a hole. Three separate symptoms -- a flat button, a full-width dialog,
a select with no arrow -- all of them "the component class alone is not the
component".

**What the design system has no opinion about** turned out to be a real list,
and all of it is layout rather than components: how a column of form fields
is spaced and how wide it is, how a block of filters arranges itself, what a
preview's definition list looks like, what an error page looks like with no
stylesheet at all. Those live in `rockadmin.css` and read the design system's
own tokens rather than choosing second numbers.

### Still open

**Dark mode.** `theme.dark` writes `data-ra-theme` (it used to write
Bootstrap's `data-bs-theme`). The design system ships one palette and not a
single dark rule, so nothing bundled answers the attribute. The key is kept,
and the schema says so. Writing a dark palette is a decision about the design
system, not about this code.

**Narrow viewports are unverified.** Window resizing did not reach the page
viewport through the tooling available, so nothing below 1568px has actually
been looked at. The design system's own media queries are in place and
untested here.
