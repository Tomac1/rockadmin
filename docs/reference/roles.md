# Role reference

<!-- Generated from the schema by `composer run docs:reference`. Do not edit by hand. -->

Every key a role accepts. Roles live in `config/roles.php`, next to
`enums.php`, and that file's top level is a map of role key to the keys below:

```php
<?php return [
    'superadmin' => ['permissions' => ['*']],
    'editor' => [
        'label' => 'Editor',
        'permissions' => ['ads.*', 'users.view', 'media.*', '!ads.delete'],
    ],
    'viewer' => ['permissions' => ['*.view']],
];
```

**Roles are configuration; assignment is data.** This file says what a role
may do and lives in git; which roles a person holds is a column of `ra_users`
and lives in the database.

**Permission names are generated from the pages**, so adding a page file adds
its permissions and there is no second list to keep in step. A page generates
`{page}.view`, and — only if it declares a form region, the same fact that
decides whether its grid draws an edit link — `{page}.create`,
`{page}.update` and `{page}.delete`.

**`*` widens, `!` subtracts, and the order does not matter.** Every widening
is applied first and every subtraction afterwards, so `['ads.*',
'!ads.delete']` and `['!ads.delete', 'ads.*']` mean the same thing. A role
whose meaning depended on the order its author happened to type is not one
anybody can reason about.

Four things are refused when the roles load, rather than quietly denying a
request months later: a pattern naming a page that does not exist (the nearest
page is suggested), a pattern naming an action that is not one of the four, a
role with no `permissions` key, and `!*`, which subtracts everything and is
never what somebody meant. A pattern that names both a page and an action must
name a permission that exists; a pattern with a `*` in it may match nothing,
because a widening over an admin of read-only pages legitimately does.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `label` | string | — | `Editor` | A human-readable name for the role, shown wherever roles are listed. Defaults to the role's own key. |
| `permissions` | array | — | `['ads.*', 'users.view', '!ads.delete']` | What this role may do, as a list of `page.action` patterns. `*` stands for every page or every action, and a leading `!` subtracts. Every subtraction is applied after every widening, so the order the patterns are written in does not change what the role means. The actions are `view`, `create`, `update` and `delete`; a page with no form region has only `view`, so `!*` and a pattern naming a page or an action that does not exist are refused at load rather than silently granting nothing. **Required.** |
