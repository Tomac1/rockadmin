# Page reference

<!-- Generated from the schema by `composer run docs:reference`. Do not edit by hand. -->

Every key a page file accepts. One file per page, under the directory
`pages_path` names, and the file's own name is the page's: `pages/ads.php` is
the page `ads`, reachable at `/p/ads`.

A page file is loaded the same way `rockadmin.php` is — shared definitions
expanded, placeholders resolved, validated against this schema, defaults
applied — so `['use' => '@column:id']`, `{{env.*}}` and `@enum:` references
all work here too, and an unknown key is refused rather than ignored.

Three things are worth reading before writing one. A **source** is a path,
not a column name: `title` is a column of the entity's own table, `user.name`
crosses a declared relation, and `stats->daily->views` traverses JSON inside
the row. A column's **key is its identity** — it names the URL parameter a
filter uses, the CSS class the cell carries, and the template that can
override it. The label is decoration and can change freely; the key cannot.
And `entity.key` must be among a region's own `columns` — a grid that never
selects it cannot attach a `collection`'s values back onto their row, cannot
keep the tiebreaker that stops two equally-sorted rows from swapping places
between pages, and cannot build a row's own detail URL.

A list region reads its state — search, filters, sort and page — out of its
own slice of the query string, namespaced under the region's key. For a
region keyed `grid`:

- `grid[q]` — the search term, matched against every column the region marks
  `searchable`.
- `grid[f][title]=bike` — a filter on the column `title`. A `select` or
  `multiselect` filter reads a list the same way: `grid[f][state][]=active`.
- `grid[f][price][from]=10&grid[f][price][to]=20` — a range filter, for a
  `range` or `date` column. Either end may be omitted.
- `grid[sort]=-created_at,id` — a comma-separated list of column keys, most
  significant first; a leading `-` sorts that column descending.
- `grid[page]=2` — the page number, one-based.

A parameter naming a column the region does not declare, or a shape its
filter does not expect, is dropped rather than raised — a stale or hand-
written link must still show a grid, not an error page.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `title` | string | — | `Orders` | The page title, shown in the browser tab and header. **Required.** |
| `layout` | string | `single` | `single` | The page layout: single, two-column or multi-column. Single is the default. |
| `bulk_actions` | array | — | `['delete', 'archive']` | The actions offered over a selection of rows, as a list of keys from this page's `actions` block, or defined inline the same three ways a column's `items` accepts. Declaring any of them puts a selection column on the grid. Every one must be a `post`: a selection of forty rows has no single URL to navigate to. A `when` is allowed and is tested per row when the action runs, so archiving a selection archives the ones that qualify and says how many did not. |

## `entity`

The data source and entity being shown.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `table` | string | — | `orders` | The database table holding the primary entity. **Required.** |
| `key` | string | `id` | `id` | The primary key column. Defaults to `id`. |
| `scope` | array | — | `['site_id' => '{{workspace.site_id}}']` | A map of column to value, most often a placeholder. Every entry becomes a filter that is always applied and can never be removed by a URL, which is what makes it a workspace boundary rather than a default. |

## `entity.relations.*`

Relationships to other tables, for joining.

Every entry of `entity.relations`.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `table` | string | — | `users` | The table this relation joins. **Required.** |
| `on` | string | — | `users.id = ads.user_id` | The join condition, written with table names, e.g. `users.id = ads.user_id`. **Required.** |
| `type` | string | `left` | `left` | The join type: left or inner. |

## `header`

Optional content shown at the top of the page.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `description` | string | — | `All orders, past and present.` | A short description of what this page shows. |
| `buttons` | array | — | `['create', 'export']` | The actions drawn in the page header, in the same three forms a grid column's `items` accepts: the name of an action from this page's `actions` block, that name adjusted with `'use' => '@action:export'`, or an action defined inline. A header button is drawn once, above the regions, with no row behind it — so a `when`, which tests a row, and a `{column}` placeholder, which is substituted from one, are both refused on it. |

## `actions.*`

The actions this page offers, each defined once and referenced wherever it should appear — a grid column, the page header. A region renders content; an action decides where that content appears, which is why the two are configured separately.

Every entry of `actions`.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `type` | string | `link` | `post` | What activating the action does: `link` navigates to it, `post` submits to it and changes data. `open` loads a region into a modal or an offcanvas; it is in the specification and refused at load until the milestone that builds it. |
| `label` | string | — | `Archive` | The text on the control. Defaults at load to the key, first letter upper-cased, underscores turned to spaces — so `archive` needs no label and `send_to_review` probably wants one. |
| `icon` | string | — | `archive` | An icon name shown before the label, or instead of it in a narrow action column. Set it when the action appears in a grid row, where there is rarely room for words. |
| `to` | mixed | — | `@page:ads/{id}/edit` | Where the action goes, always as a reference and never as a bare URL: `@page:ads/{id}/edit` for a `link`, `@action:archive` for a `post`. A `{column}` in it is substituted per row while rendering, URL-encoded, from that row's own data — not to be confused with `{{user.id}}`, which resolves once at load from the signed-in administrator. A raw URL is refused because it cannot be permission-checked against a page. |
| `target` | mixed | — | `@region:detail` | What an `open` shows, always as a `@region:` reference and never as a bare URL: `@region:detail` for a region of this page, `@region:users.detail` for one of another page's, which then loads under *that* page's permissions. A raw URL is refused because it cannot be permission-checked against a page, and would become the obvious way to smuggle content into the admin frame. Required on an `open` and refused on anything else, which uses `to`. |
| `in` | string | — | `offcanvas` | Where an `open` puts what it loads: `modal` for a dialog over the page, `offcanvas` for a panel sliding in from the side. Required on an `open` — there is no sensible default, and guessing one means finding out which you got by clicking. Refused on a `link` or a `post`, neither of which opens anything. |
| `size` | string | — | `lg` | How wide the overlay is: `sm`, `md`, `lg` or `xl` for a modal, `sm` or `lg` for an offcanvas. A size the stylesheet has no rule for is refused at load rather than quietly drawing the default. Optional; each overlay has its own default. |
| `title` | string | — | `Ad #{id}` | The heading on the overlay, with row placeholders: `Ad #{id}`. Shown while the fragment is still loading; once it arrives, a `data-ra-title` on its root wins, because by then the answer knows which row it is about. |
| `params` | array | — | `['id' => '{id}']` | The query the fragment is fetched with, as a map of parameter to value, where a value may be a `{column}` of the row the control was drawn on: `['id' => '{user_id}']` on an ads grid means that ad's seller. URL-encoded on substitution, exactly as a `to` is. Not to be confused with `{{user.id}}`, which resolves once at load to the signed-in administrator — a mistake that looks correct in testing, because the developer is usually looking at their own records. |
| `permission` | string | — | `ads.update` | The permission a reader needs before the action is drawn for them at all, and before a `post` to it is accepted. One of the generated `page.action` names; anything outside that catalogue is refused when the gate is built. Set it on every action that changes data. |
| `confirm` | string | — | `Archive this ad?` | The question asked before a `post` runs. Required on every `post` — one that changes data and asks nothing is a mis-click away from a deletion — and refused on a `link`, because confirming something that only navigates teaches people to dismiss confirmations. |
| `when` | array | — | `['state' => 'active']` | Row conditions deciding whether the action is offered: a map of column key to the one value that column must hold. Every key must name a column the list region declares. It decides what is *offered*, so it is re-checked on the server when the action is performed — a client that was never shown it can still post it. |
| `set` | array | — | `['state' => 'archived']` | What a `post` writes: a map of column key to the one value to store, applied to the row the action was drawn for. This is what makes `archive` mean something — `'set' => ['state' => 'archived']` — and every `post` needs it except one pointing at `@action:delete`, which deletes instead. Values are literals, never expressions and never row placeholders: an action that computes what it writes is a form, and forms already exist. Refused on a `link`, which writes nothing. |
| `handler` | mixed | — | `@handler:archive_ad` | The project's own logic for this write, as a `@handler:` reference naming something it registered: `@handler:archive_ad`. It replaces what the action does, not what surrounds it — the CSRF check, the permission, the `when` re-check, the transaction and the audit row all still happen. Given one, `set` is not needed and is refused alongside it, because only one of the two could run and nobody reading the file would know which. |
| `refresh` | array | — | `['grid']` | Which regions of this page re-render once the action completes, as a list of region keys. Without it the page reloads. Set it when the action changes what one region shows and nothing else. |

## `regions.*`

The regions of the page, each showing data in a different way.

Every entry of `regions`.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `type` | string | — | `list` | The region type: list, preview or form. Refused at load if it names anything else. `nav` and `stat` arrive in later milestones. **Required.** |
| `per_page` | int | — | `25` | Rows to show per page. Only for list regions. |
| `sort` | array | — | `['created_at' => 'desc']` | The default sort order: a map of column key to direction, applied until a user picks their own. |
| `fields` | mixed | — | `['name', 'email', 'created_at']` | Which fields a preview region shows. Omitted, it inherits the page's list region's columns; a list of column keys shows exactly those, in that order; '@all' shows every column of the entity's table; an empty list shows none, which is legal but almost always a mistake, so the loader warns. Only for preview regions. |

## `regions.*.search`

Presentation for the region's search box. Which columns it searches is decided per column, by that column's own `searchable` key.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `placeholder` | string | — | `Search...` | Placeholder text shown in the empty search box. |

## `regions.*.columns.*`

The columns shown in this region, each described by the column schema.

Every entry of `regions.*.columns`.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `type` | string | `text` | `text` | What the column holds: text, int, money, datetime, bool, enum or json — or `actions`, which holds controls rather than a value and takes an `items` list instead of a source. |
| `label` | string | — | `First Name` | The column header, shown above the values. Defaults at load to the key, title-cased. |
| `source` | string | — | `user.name` | A source path: the database column or expression to read. Defaults to the key. Use `.` to join a relation, `->` to traverse JSON. Must not be set on a column carrying `collection`. |
| `display` | string | — | `badge` | How the column looks: plain, badge, check, yesno, progress or percent. Defaults to the type's own. Not every type allows every display. A cell links to the row's detail page independently of this, via the `link` key. |
| `sortable` | bool | `false` | `true` | Whether the column header is a sort link. |
| `searchable` | bool | `false` | `true` | Whether the region's search box looks here. |
| `align` | string | — | `end` | Horizontal alignment: start or end, with left and right accepted as aliases for them. Defaults to the type's own — numbers align to the end so they are readable when skimmed down a column. |
| `width` | string | — | `8rem` | A CSS width for the column, e.g. `8rem`. Without it, the column shares space equally. |
| `class` | string | — | `font-mono` | Extra CSS classes added to every cell in this column. |
| `link` | bool | `false` | `true` | Makes the cell an anchor to the row's detail page. |
| `currency` | string | — | `USD` | ISO 4217 currency code. Shown after the amount. For `money` type only. |
| `format` | string | — | `Y-m-d H:i` | PHP date format string. For `datetime` type only. |
| `max` | int | — | `100` | The value that counts as full. For `progress` display only. |
| `options` | mixed | — | `['active' => ['label' => 'Active', 'color' => 'success'], 'inactive' => 'Inactive']` | The enum values, keyed by their stored value: an `@enum:` reference to a shared enumeration, or a literal map where each entry is either the label as a plain string, or `['label' => ..., 'color' => ...]` when the value needs a badge colour. For `enum` type only. |
| `items` | array | — | `['edit', 'delete']` | The actions this column draws, one group of controls per row. Required on an `actions` column and refused on any other, which holds a value rather than controls. Each entry is one of three forms and they mix freely: the name of an action from the page's `actions` block (`'edit'`); a map carrying `'use' => '@action:edit'` together with the keys to change for this column only; or a keyed entry defining an action inline, written exactly as one in the `actions` block, for an action used only here. Every form is built and refused by the same loader, so an inline `post` still needs its `confirm`. |

## `regions.*.columns.*.filter`

Makes the column filterable. Declares how the filter works.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `type` | string | `text` | `text` | The filter type: text, select, multiselect, range, date or boolean. |
| `op` | string | — | `contains` | The filter operator: equals, not_equals, contains, starts_with, ends_with, gt, gte, lt, lte, between, in, is_null or is_not_null. Defaults per filter type: text uses `contains`, select and boolean use `equals`, multiselect uses `in`, range and date use `between`. |
| `label` | string | — | `Search by name` | The filter label, shown beside the input. Defaults to the column's label. |
| `options` | mixed | — | `['active' => ['label' => 'Active', 'color' => 'success'], 'inactive' => 'Inactive']` | For select and multiselect: the same shapes the column's own `options` accepts — an `@enum:` reference, or a literal map from value to either a label string or `['label' => ..., 'color' => ...]`. Defaults to the column's own options when it is an enum. |
| `placeholder` | string | — | `Type a name...` | Placeholder text shown in an empty text filter. |

## `regions.*.columns.*.collection`

Fetches a one-to-many relationship. The column displays collected values; a supplementary query fetches all of them for the whole page.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `table` | string | — | `order_items` | The table holding the many-side records. **Required.** |
| `foreign_key` | string | — | `order_id` | The column in the collection table that points back to this entity's key. **Required.** |
| `column` | string | — | `sku` | The column in the collection table to collect, one value per related row. **Required.** |

## `regions.*.form`

The fields this form reads and writes, and how copying a row differs from editing one. Only for form regions.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `handler` | mixed | — | `@handler:save_ad` | The project's own logic for saving this form, as a `@handler:` reference naming something it registered: `@handler:save_ad`. It replaces what create, update and delete do, not what surrounds them — validation, the permission, the workspace scope, the transaction and the audit row all still happen, and the operation is told which of the three it is serving. Declared here rather than only in wiring so that this file does not tell half the truth about what saving does. |

## `regions.*.form.fields.*`

The fields of this form, each described by the field schema.

Every entry of `regions.*.form.fields`.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `type` | string | `text` | `text` | What the field holds and which control it renders: text, textarea, number, select, multiselect, checkbox, radio, date, datetime, hidden or password. |
| `label` | string | — | `First Name` | The label shown beside the control. Defaults at load to the key, first letter upper-cased, underscores turned to spaces. |
| `default` | mixed | — | `draft` | What a new row starts with: a literal, a `{{placeholder}}`, or one of the tokens `@now` and `@uuid`. Applies only when a row is created — an existing row is never touched by it. A `{{placeholder}}` default is bound when the row is saved, not when the form is drawn, so its control shows empty until then. May be resolved per request rather than at load. |
| `required` | bool | `false` | `true` | Whether an empty value is refused on submission. |
| `readonly` | bool | `false` | `true` | Renders the control disabled, and the field is never read from a submission — its value on save always comes from its default or from the existing row. |
| `hidden` | bool | `false` | `true` | Renders no control at all. Its default is still applied on save, so a hidden field is how a form carries a value the user never sees or edits, such as a workspace scope. |
| `help` | string | — | `Shown to customers on the storefront.` | Help text shown under the control. |
| `placeholder` | string | — | `Enter a title...` | Placeholder text shown inside an empty text-like control. |
| `options` | mixed | — | `['active' => 'Active', 'inactive' => 'Inactive']` | The choices offered: an `@enum:` reference to a shared enumeration, or a literal map from stored value to label. For `select`, `multiselect` and `radio` only. |
| `min` | int | — | `0` | For a `number` field, the smallest accepted value. For a text-like field, the shortest accepted length. |
| `max` | int | — | `100` | For a `number` field, the largest accepted value. For a text-like field, the longest accepted length. |
| `step` | string | — | `0.01` | The HTML step attribute for a `number` field, e.g. `0.01` to allow cents. It is passed to the control and is not enforced server-side: a submission of `10.5` against a step of `1` is accepted here, and refused by the column it is written to. |
| `rows` | int | — | `4` | How many rows tall a `textarea` control is. |
| `pattern` | string | — | `[A-Z]{2}\d{4}` | A regular expression the whole value must match, written without delimiters. It is anchored at both ends, exactly as the HTML `pattern` attribute is, so `[A-Z]{2}\d{4}` accepts `AB1234` and nothing that merely contains it. Not accepted on a `multiselect` or a `checkbox`, whose values a pattern could never be applied to. |

## `regions.*.form.copy`

How the copy action differs from a plain edit.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `reset` | array | — | `['state', 'published_at']` | Field keys that fall back to their own default on a copy, instead of being carried over from the source row. |
