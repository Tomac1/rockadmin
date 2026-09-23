# RockAdmin demo

```bash
php -S localhost:8080 -t demo demo/index.php
```

Open <http://localhost:8080/>.

This is the design surface the default theme is tuned against — a themed
admin shell with a navbar, a menu, a page header with buttons, and a flash
message shown once as a toast. It is kept honest to the general case: it
loads a small but real `config/rockadmin.php` through the same
`RockAdmin\Config\Loader` a project uses, and wires the same `Kernel`,
`Router` and `Renderer` a project wires — nothing here is demo-only
machinery.

A real project is different: it is mounted through a Composer path
repository (or a tagged release), its `rockadmin.php` describes actual
pages, and its templates directory very likely overrides some of the SDK's
own. This demo is where the general case is enough; a real project is where
it stops being enough for one page and a template gets copied and edited.

There is no grid yet, because regions arrive in milestone 6 — what this
demo shows is exactly what milestone 4 built. Toggle your operating
system's dark mode to see both themes; `config/rockadmin.php` sets
`theme.dark` to `'auto'`, which is what makes that toggle work.
