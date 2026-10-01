# Milestone 9 — `open` actions, and results that land without a reload

The other half of specification 8.5. Milestone 8 built `link` and `post` and
deferred `open` with a message naming this milestone; 8.6 describes the
machinery it needs and most of that machinery now exists, because the design
system interlude had to build the modal and the offcanvas anyway.

## Decisions this plan makes

**An `open` action is a link that script upgrades, not a button that script
replaces.** 8.6's own example is `<button data-ra-action="preview">`, and a
button is wrong here: with JavaScript off it does nothing at all, and every
milestone in this project has been held to "everything works with JavaScript
disabled". So the control is an `<a href="…">` pointing at the standalone
equivalent of what it opens — `/p/users/42` for a preview of a row, `/p/users`
for a list region — and carries the fragment URL in `data-ra-open`. With
script, the click is intercepted and the fragment goes into the overlay.
Without it, the link navigates to a real page.

The alternative, linking straight at `/r/{page}/{region}`, is what 8.6's
"a fragment is always valid HTML, so it can be opened directly in a browser"
suggests. That sentence is about debugging. A person who has JavaScript off
and clicks Preview should not land on an unstyled fragment with no navigation.

**`refresh` is implemented here, or it is deleted.** It is loaded, validated,
refused when it names a region the page has not got — and then carried to
nothing. A configured key that does nothing is the third one this project has
found in two milestones (`theme.dark`, `data-ra-confirm` before milestone 8,
and this), and the pattern is always the same: the loader is the part somebody
writes a test for, and the part that consumes it is the part nobody notices is
missing. It belongs in this milestone because it is the same mechanism: a
fragment fetched from `/r/{page}/{region}` and swapped into the live page.

**The overlay keeps nothing between opens.** It is emptied on close, not
left holding the last fragment. A modal that flashes the previous row's data
for 200ms before the new fetch lands is the kind of thing that reads as a data
leak to whoever reports it, and it is indistinguishable from one in a
screenshot.

**Focus is the accessibility requirement and it is not optional.** Opening
moves focus into the dialog, closing returns it to the control that opened it,
and Tab does not escape while it is open. This is the part 8.5 meant by
"it needs focus management, escape handling", and it is why `open` was given
its own milestone rather than being squeezed into 8.

**`params` resolve from the row, like `to` does.** `'params' => ['id' =>
'{user_id}']` on an action in the ads grid means the *ad's* `user_id`, and it
is URL-encoded on substitution by the same `RowPlaceholders` that already
guards `to`. The trap 8.5 names — writing `{{user.id}}` and getting the
logged-in administrator on every row — is caught by the two being parsed by
different code at different times, which is already true.

## Pre-flight scan — what is already here and what is not

Run against the code before dispatching anything.

**The overlays exist and work.** The interlude built `ui/modal.php`, the
offcanvas in `layout/base.php`, and `openOverlay`/`closeOverlay`/Escape/
backdrop-click in `core.js`. What they have not got is anything that puts a
*fragment* in them, a size modifier chosen per action, or focus management.

**`Resolver` already parses `@region:` and refuses it**, with a message
naming this milestone. Task 2 turns the refusal into a `RegionReference`. The
dotted form `@region:users.detail` is already described in the error path's
sibling, so the parsing shape is decided.

**`RegionHandler` already serves `/r/{page}/{region}`** for both a list and a
preview region, and the gate already requires `{page}.view` on it. A region of
*another* page is therefore already permission-checked under that page's own
permissions, which is 8.5's whole argument for `@region:users.detail` being
sound. Nothing in task 4 needs to add a permission check; it needs to not
remove one.

**`core.js` already has `load()`, `attach()` and `detach()`** and a
`navigate()` that swaps a region after a filter or a page change. An `open`
action and a `refresh` are the same operation with a different destination,
and the first of them must not grow a second fetch-and-swap.

**`ActionView` has no room for a target.** It carries a URL and a boolean. An
`open` action needs the fragment URL, where to put it, how big, and a title —
four things, and three of them are configuration rather than a row's.

## Global constraints

- **No runtime dependencies**, and no new JavaScript library.
- **Everything works with JavaScript disabled.** An `open` action navigates.
  A `refresh` is a redirect, exactly as it is today.
- **A fragment is always valid HTML**, never JSON, never carrying a script
  tag — 8.6.3, and `TemplateStandardsTest` already enforces the last of those.
- **The gate decides what is drawn**, and a region fetched from another page
  is gated by that page's own `view` permission.
- **Every configuration key is in the schema**, or `ReferenceIsCurrentTest`
  fails.

## Test environment

Both drivers exported as `CLAUDE.md` records them. The demo and the test
fixtures share `rockadmin_test`, so a suite run wipes the demo's data.

---

### Task 1: `@region:` becomes a reference

**Files:** create `src/Config/RegionReference.php`; modify
`src/Config/Resolver.php`, `src/Http/UrlGenerator.php`.
Test: `tests/Unit/Config/ReferenceTest.php`.

`@region:detail` is a region of the current page; `@region:users.detail` is
the region `detail` of the page `users`. One dot, and the part before it is a
page file's name. Two dots, or a leading or trailing one, is refused naming
what the two forms are.

`UrlGenerator::reference()` learns the third type, producing
`/r/{page}/{region}`. The current-page form takes the page from the caller,
the same way `@action:` already does.

**Validated in `PageRepository`, not in `Resolver`** — the same split
milestone 8 recorded: the resolver lives in `Config` and the page catalogue
lives in `Page`, and a reference to a region that does not exist is refused
where the catalogue is.

---

### Task 2: What an `open` action is

**Files:** modify `src/Page/ActionType.php`, `ActionSchema.php`,
`ActionDefinition.php`, `PageRepository.php`.
Test: `tests/Unit/Page/OpenActionTest.php`.

`ActionType::Open` stops being refused. Four keys join the schema, each
needing a description written for somebody deciding whether to set it:

- `in` — `modal` or `offcanvas`. Required on an `open`; there is no sensible
  default, and guessing one means a project discovers which it got by
  clicking.
- `size` — the overlay's width, from the set the design system defines
  (`sm`, `md`, `lg`, `xl` for a modal; `sm`, `lg` for an offcanvas). A size
  the design system has no rule for is refused at load rather than silently
  drawing the default, which is the `ra-btn--xs` mistake with a different
  prefix.
- `target` — a `@region:` reference, never a bare URL. 8.5 gives the reason
  and it is a security one.
- `title` — the overlay's heading, with row placeholders: `'Ad #{id}'`.

**What is refused at load:** an `open` with no `target`; a `target` that is
not a `@region:`; a `link` or a `post` that carries `in`, `size` or `target`;
an `open` that carries `set` or `confirm` — it opens something, it changes
nothing, and a confirmation in front of it is the same mistake 8 refused on a
`link`.

---

### Task 3: Drawing one

**Files:** modify `src/View/ActionView.php`, `src/Grid/ActionBuilder.php`,
`templates/ui/action.php`.
Test: `tests/Unit/Grid/OpenActionViewTest.php`.

An anchor whose `href` is the standalone page and whose `data-ra-open` is the
fragment URL, plus `data-ra-in`, `data-ra-size` and `data-ra-title`.

**The `href` is the point of this task.** It is what the control does with no
script, and it must address the same thing the overlay would show: the row's
own page for a preview, the page itself for a list.

`params` are substituted per row and URL-encoded, through the same
`RowPlaceholders` that guards `to`. The test that matters is the one milestone
8 wrote for `to`: a row whose value is `../../etc/passwd`, and one containing
`?`, `#` and a space, produce a URL that still addresses the row the action
was drawn for.

---

### Task 4: Opening it

**Files:** modify `assets/js/core.js`, `templates/ui/modal.php`,
`templates/layout/base.php`, `assets/css/rockadmin.css`.
Test: a browser pass, reported.

Click, fetch, fill, `attach()`. Close empties it and calls `detach()`, which
is what keeps a tab left open all day from accumulating editor instances —
8.6.2's whole reason for the registry.

**Focus.** On open, focus moves to the dialog; Tab is trapped inside it; on
close, focus returns to the control that opened it. A dialog that leaves focus
behind it on the page is one a keyboard user cannot reach and cannot escape.

**The title and the size come from the fragment root as well as from the
action** — 8.6.3 says the fragment carries `data-ra-title`, `data-ra-size` and
`data-ra-class`, and the action carries its own. The fragment wins, because it
knows which row it ended up being; the action's value is what the overlay shows
while the fetch is in flight.

**A failed fetch says so inside the overlay**, rather than leaving it empty or
closing it. An overlay that opens and closes again is indistinguishable from a
control that did nothing.

---

### Task 5: `refresh`

**Files:** modify `src/View/ActionView.php`, `templates/ui/action.php`,
`assets/js/core.js`, `src/Http/ActionHandler.php`.
Test: `tests/Unit/Grid/RefreshTest.php`, and the browser.

An action declaring `refresh: ['grid']` posts in the background and re-renders
those regions instead of reloading the page. Without script it is the redirect
it already is.

**The server does not change what it does.** It still writes, still flashes,
still redirects — a background post reads the redirect and asks for the named
regions rather than following it. That keeps one write path and means the
no-script case is not a second implementation.

**Flashes have to arrive.** A background post that swallows the toast is worse
than a reload, because the write succeeded silently. The flash bag is read on
the next render; a refreshed region is not that render. This task owns the
decision and `FlashBag` may need a fragment-sized answer.

---

### Task 6: The demo, and looking at it

**Files:** `demo/config/pages/ads.php`, `demo/README.md`.
Test: `tests/Integration/Form/DemoOpenTest.php`.

The demo already has a `preview` region that only `/p/ads/{id}` reaches. Give
it the `open` action 8.5 says it is shorthand for, in an offcanvas, and a
second one opening the same region in a modal — the two spellings of one
region, which is 8.5's own argument for keeping regions and actions apart.

**Then drive it in a browser and report what was measured:** the offcanvas
opens with the row's data and the right title, Escape closes it, focus returns
to the control, Tab does not leave the dialog, the control works with the
script removed, and a second open shows the second row rather than flashing
the first.

Six defects in this project have shipped green and been found by opening the
page. The most recent was four days old, three tests deep, and a 404 on every
row action in the admin.

## Milestone acceptance

1. `composer run check` green with both databases.
2. A preview opens in an offcanvas from a grid row, from configuration alone.
3. The same region opens in a modal from a different control.
4. A region of another page opens under *that* page's permissions, and a
   reader without them gets 403 rather than an empty overlay.
5. Every `open` control navigates somewhere real with JavaScript disabled.
6. Focus enters the dialog, is trapped, and returns to the opener.
7. An action with `refresh` updates its regions without a reload, and still
   shows its flash.
8. A row placeholder in `params` cannot produce a URL addressing another row.
9. Every new key is in the generated reference.

## What this milestone deliberately leaves out

- **Workspaces.** Deferred twice already, with the same reason both times: the
  first real project has one site. Recorded here so the deferral stays a
  decision rather than becoming a habit.
- **`data-ra-requires`** (8.6.4), per-view libraries fetched on demand.
  Nothing in v1 ships a view that needs one; the day `chart` arrives, so does
  this.
- **Inline cell editing** (8.12), reserved for v1.1 by the specification
  itself.
