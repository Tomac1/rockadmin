# Files and images

An admin that cannot take a photograph of the thing it is administering is
not finished. This is the `file` field, the `image` column, the two drivers
that ship, and the handful of rules the whole feature is built on.

## The one rule everything follows

**A column holds a key, never a URL and never a path.**

```
ads/2026/10/4f2a9c08d1b74e3a95c6e0f1a2b3c4d5.jpg
```

That is what is in the database. It is opaque, it is dated so a bucket with
three years of files in it can still be looked through by hand, and the name
is sixteen random bytes, so nothing about the row can be guessed from it — not
the id, not the title, not the order things were uploaded in.

A key becomes something a browser can fetch at the moment a page is drawn,
because that is when the answer is actually known: a bucket can move, a CDN
domain can change, and a signed link expires. A URL written into the database
is a URL that is wrong later.

## The field

```php
// config/pages/ads.php
'regions' => [
    'form' => [
        'type' => 'form',
        'form' => [
            'fields' => [
                'photo' => [
                    'type' => 'file',
                    'label' => 'Photo',
                    'accept' => ['image/jpeg', 'image/png'],
                    'max_bytes' => 1048576,
                    'help' => 'A JPEG or a PNG, up to 1 MB.',
                ],
            ],
        ],
    ],
],
```

A `file` field has **no value of its own**. It is not posted back, it cannot
be defaulted, and it cannot be required on a row that already has a file — the
form shows what is stored and offers to replace it. What it writes to the
column is a key the storage assigned.

`accept` and `max_bytes` **may narrow and may never widen** what the
installation allows:

```php
// config/rockadmin.php
'uploads' => [
    'max_bytes' => 2097152,
    'types' => ['image/jpeg', 'image/png', 'image/webp'],
],
```

A field naming a type the installation does not allow is refused when the page
loads, not when somebody finally uploads one.

### Nothing the browser says is believed

The content type in the request and the filename the browser sent are both
ignored. The type is read from the bytes — the first few bytes against a table
of signatures, confirmed by `finfo` where the extension exists — and the
extension on the key comes from that same table. A file called `photo.jpg`
that is a PDF is refused; a file called `invoice.txt` that is a PNG is stored
as `.png`.

This also means **nothing processes your images**. They are stored as they
arrived, EXIF and GPS included. If that matters for your project, strip it in
an `Operation` — see [Your own write logic](own-write-logic.md).

## The column

```php
'columns' => [
    'photo' => [
        'type' => 'image',
        'label' => 'Photo',
    ],
],
```

An `image` column shows the picture. `'display' => 'plain'` on the same column
shows the file's name instead, which is what a grid of forty rows sometimes
wants.

A cell quietly shows the name rather than a broken image when the key cannot
honestly be resolved: no storage is wired, the key belongs to another page, the
value is not a key at all, or the storage has no way to build a URL.

## The two drivers

### A directory

```php
'storage' => [
    'driver' => 'local',
    'path' => '/var/lib/myapp/uploads',
    'link_minutes' => 10,
],
```

**Put the directory outside the document root.** A directory of uploads inside
it is one a stranger can fetch by guessing, and no amount of care in this SDK
changes that.

### A bucket

```php
'storage' => [
    'driver' => 's3',
    'endpoint' => '{{env.R2_ENDPOINT}}',   // the account endpoint, host only
    'bucket' => '{{env.R2_BUCKET}}',
    'key' => '{{env.R2_ACCESS_KEY_ID}}',
    'secret' => '{{env.R2_SECRET_ACCESS_KEY}}',
    'region' => 'auto',
    'link_minutes' => 10,
],
```

S3 here means the protocol, not the vendor: Cloudflare R2, MinIO, Backblaze
B2, DigitalOcean Spaces and Amazon itself all speak it, and the driver has been
run against a real R2 bucket. The endpoint is the **account** endpoint with no
path — the bucket goes in `bucket` and nowhere else, and an endpoint carrying
one is refused rather than quietly storing everything under `test/test/`.

Signing is SigV4, implemented here, with no SDK and no dependency. A bucket is
read through presigned URLs, so the browser fetches the file from the bucket
and the admin never proxies the bytes.

### Somebody else's

A host with its own disk abstraction — Laravel's `Storage`, Flysystem, an
existing wrapper with its own credentials and retry policy — implements
`RockAdmin\Storage\Storage` and passes it in. Five methods, and the drivers
above are examples of them. Set no `storage.driver` in that case.

## Who may fetch a file

Both drivers are **private by default**, and a private link is signed.

For a bucket that is an ordinary presigned URL. For a directory it is the
admin's own `/_file/{key}` route, and the URL carries an HMAC over the key, an
expiry, and the session it was built for — the secret is the session's own,
generated on demand and kept where the CSRF token is kept.

This matters more than it looks. The route requires signing in, which keeps
strangers out and says *nothing* about one administrator fetching another's
file. A sixteen-byte key is hard to guess, but hard-to-guess is a secret, not
a permission: it stops being secret the moment it appears in a log, a database
export, a screenshot or a bug report. In a multi-tenant admin that is the
difference between an awkward URL and somebody else's photograph.

What the signature promises is narrow and worth stating plainly: **this link
was made for you, minutes ago**. A link forwarded by the person it was made
for, inside its lifetime, works — exactly as a presigned S3 URL does. The
expiry is what bounds that, and `link_minutes` is what sets it.

**Per-page and per-row permissions on files are not in v1.** Specification 13
puts column-level permissions out of v1 and this inherits it. An administrator
who may open one page and is handed a live link to another page's file can
follow it. If that is not acceptable for your installation, keep the files in
a bucket your own code gates instead.

An installation that deliberately serves the directory over HTTP sets
`storage.public_url`, and then nothing above applies: the files are public,
which is a trade made knowingly.

## What happens when you save

One write path, files included:

1. the file is validated and **stored before the row is written**;
2. the row is written, inside the transaction, with the key in the column;
3. after the commit, the file the new one replaced is forgotten.

A write that is refused at any point discards what it had stored, so nothing
is left behind. A write that succeeds leaves no orphan. Deleting a row forgets
its file after the commit, for the same reason and in the same place.

## Two consequences worth knowing before you build on this

**Renaming a page strands its files.** A key is written under the page's own
folder, and display and deletion both check that a key is under the page
asking. Rename `ads` to `listings` and the old files stop being shown and stop
being deleted — they are orphans in the storage, not broken rows. That is the
safe direction of the two, and it is the direction this was chosen for: the
alternative is a page that can delete files it does not own. Move the folder
yourself, or leave them.

**Nothing sweeps the storage.** A file whose row was deleted by hand, by
another application, or by a migration is still there. A file stranded by a
rename is still there. There is no garbage collector in v1.
