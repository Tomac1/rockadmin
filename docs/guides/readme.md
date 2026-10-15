# Guides

Recipes for the places a project supplies code of its own. The reference
documentation under [`../reference/`](../reference/) says what every
configuration key does, and is generated from the schema; these say how to use
the seams that configuration names.

- [Installing RockAdmin](installation.md) — the four commands, the bootstrap
  file both the web and the command line load, borrowing your connection, and
  what to run on a deploy.
- [Your own write logic](own-write-logic.md) — replacing what a save, a delete
  or a configured action does with your own `Operation`, and telling a
  multi-tenant host which workspace a write belongs to.
- [Administrators, mail and password reset](managing-administrators.md) — the
  first account, the page that manages the rest, the four mail drivers, and
  what the reset flow does and does not promise.
- [Files and images](files-and-images.md) — the `file` field and the `image`
  column, a directory or an S3 bucket, and who may actually fetch what has
  been uploaded.
