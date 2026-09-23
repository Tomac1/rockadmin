# Page reference

<!-- Generated from the schema by `composer run docs:reference`. Do not edit by hand. -->

Every key a page file accepts. One file per page, under the directory
`pages_path` names, and the file's own name is the page's: `pages/ads.php` is
the page `ads`, reachable at `/p/ads`.

A page file is loaded the same way `rockadmin.php` is — shared definitions
expanded, placeholders resolved, validated against this schema, defaults
applied — so `['use' => '@column:id']`, `{{env.*}}` and `@enum:` references
all work here too, and an unknown key is refused rather than ignored.

Two things are worth reading before writing one. A **source** is a path, not
a column name: `title` is a column of the entity's own table, `user.name`
crosses a declared relation, and `stats->daily->views` traverses JSON inside
the row. And a column's **key is its identity** — it names the URL parameter
a filter uses, the CSS class the cell carries, and the template that can
override it. The label is decoration and can change freely; the key cannot.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `title` | string | — | `Orders` | The page title, shown in the browser tab and header. **Required.** |
| `layout` | string | `single` | `single` | The page layout: single, two-column or multi-column. Single is the default. |

## `entity`

The data source and entity being shown.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `table` | string | — | `orders` | The database table holding the primary entity. **Required.** |
| `key` | string | `id` | `id` | The primary key column. Defaults to `id`. |
| `scope` | array | — | `['site_id' => '{{workspace.site_id}}']` | A map of column to value, most often a placeholder. Every entry becomes a filter that is always applied and can never be removed by a URL, which is what makes it a workspace boundary rather than a default. |

## `entity.relations.*`

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

## `regions.*`

Every entry of `regions`.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `type` | string | — | `list` | The region type: list or preview. Refused at load if it names anything else. `form`, `nav` and `stat` arrive in later milestones. **Required.** |
| `per_page` | int | — | `25` | Rows to show per page. Only for list regions. |
| `sort` | array | — | `['created_at' => 'desc']` | The default sort order: a map of column key to direction, applied until a user picks their own. |

## `regions.*.search`

Presentation for the region's search box. Which columns it searches is decided per column, by that column's own `searchable` key.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `placeholder` | string | — | `Search...` | Placeholder text shown in the empty search box. |

## `regions.*.columns.*`

Every entry of `regions.*.columns`.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `type` | string | `text` | `text` | What the column holds: text, int, money, datetime, bool, enum or json. |
| `label` | string | — | `First Name` | The column header, shown above the values. Defaults at load to the key, title-cased. |
| `source` | string | — | `user.name` | A source path: the database column or expression to read. Defaults to the key. Use `.` to join a relation, `->` to traverse JSON. Must not be set on a column carrying `collection`. |
| `display` | string | — | `badge` | How the column looks: plain, badge, check, yesno, progress, percent or link. Defaults to the type's own. Not every type allows every display. |
| `sortable` | bool | `false` | `true` | Whether the column header is a sort link. |
| `searchable` | bool | `false` | `true` | Whether the region's search box looks here. |
| `align` | string | — | `end` | Horizontal alignment: start or end. Defaults to the type's own. Numbers align to the end so they are readable when skimmed. |
| `width` | string | — | `8rem` | A CSS width for the column, e.g. `8rem`. Without it, the column shares space equally. |
| `class` | string | — | `font-mono` | Extra CSS classes added to every cell in this column. |
| `link` | bool | `false` | `true` | Makes the cell an anchor to the row's detail page. |
| `currency` | string | — | `USD` | ISO 4217 currency code. Shown after the amount. For `money` type only. |
| `format` | string | — | `Y-m-d H:i` | PHP date format string. For `datetime` type only. |
| `max` | int | — | `100` | The value that counts as full. For `progress` display only. |
| `options` | mixed | — | `['active' => ['label' => 'Active', 'color' => 'success'], 'inactive' => 'Inactive']` | The enum values, keyed by their stored value: an `@enum:` reference to a shared enumeration, or a literal map where each entry is either the label as a plain string, or `['label' => ..., 'color' => ...]` when the value needs a badge colour. For `enum` type only. |

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
