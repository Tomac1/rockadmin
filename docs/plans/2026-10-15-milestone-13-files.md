# Milestone 13 — Files: uploading them, showing them, keeping them somewhere

The `file` field type and the `image` column type from specification 13, and
the thing both of them need underneath: somewhere to put bytes. Two somewheres
— a local directory and Cloudflare R2 — because an admin that can only write
to the disk it runs on is not deployable on half the hosting this project
targets, and one that can only talk to an object store is not runnable on a
laptop.

This is the largest remaining functional hole in v1. An inzerát with no photo
and a user with no avatar is not a complete admin, and nothing in the project
can store a byte today.

## Decisions this plan makes

**`Storage` is a seam, two implementations ship, and this partly disagrees
with the specification.** Section 3 names "an S3 uploader" as precisely the
kind of thing that should be an interface in the core plus an implementation
the project installs. I am shipping `S3Storage` in the core anyway, and the
disagreement is worth stating rather than glossing:

- the same argument that justified two hundred lines of socket SMTP applies
  here almost word for word — SigV4 is `hash_hmac('sha256', …)` repeated five
  times, and the API surface an admin needs is PUT, GET, DELETE and HEAD;
- the alternative is that every project wanting R2 installs `aws-sdk-php`
  (a hundred packages) or `league/flysystem` plus its R2 adapter, to do what
  fits in one file;
- and a project that *already* has either of those keeps the spec's own
  arrangement: it wires its own `Storage` in one constructor argument and this
  code never runs.

So the rule is kept where it matters — `composer.json` gains nothing — and the
specification's example is departed from deliberately. If that reads wrong
later, deleting `S3Storage` leaves the seam and the local driver intact.

**The column holds a key, never a URL.** `ads.photo` holds
`ads/2026/10/8f3a….jpg`, and the URL is built at render time. A stored URL is a
stored decision about which bucket, which CDN domain and whether the file is
public — three things that change — and a stored *presigned* URL is a stored
timebomb that expires in an hour.

**Private by default, on both drivers.** Local files live outside the document
root, the same rule `paths.logs` already follows, and are served through a
route that requires a signed-in administrator. R2 is presigned for a few
minutes, which costs no network call: presigning is a signature, not a request.
A public base URL is configurable for both, because a product photo should
come from a CDN rather than from PHP — but it is opt-in, because the default
must not put somebody's scanned invoices on the open internet.

**The browser's content type is a claim, and the uploaded filename is worse.**
The stored type is derived from the bytes — a magic-number check of our own for
the handful of types an admin accepts, plus `finfo` as a second opinion when
the extension happens to be loaded — and the extension on the stored key comes
from *our* mapping of the detected type. The name the person uploaded reaches
the key the way an address reaches a filename in `LogMailer`: slugged, or not
at all. A file called `faktura.php.jpg` is stored as `….jpg` and cannot be
executed even by a web server somebody later points at the directory by
mistake.

**A request that exceeded `post_max_size` looks exactly like a CSRF attack,
and must not be reported as one.** PHP discards the whole body — `$_POST` is
empty, `$_FILES` is empty, no warning reaches the application — so the token
check fails and the honest-looking answer is "this form was open too long".
The handler has to notice `CONTENT_LENGTH` against the limit and say what
actually happened, or every oversized upload becomes a support ticket about a
broken login.

**Deleting a file happens after the commit.** A replaced file and the files of
a deleted row both go through milestone 11's `afterCommit`: a delete inside the
transaction is a file gone for a row that rolled back. The trade is stated
rather than hidden — a failed deletion leaves an orphan in the bucket, which is
the better of the two failures, and nothing in this milestone sweeps them up.

**No image processing of any kind.** No resizing, no thumbnails, no EXIF
stripping: all of it needs `ext-gd` or ImageMagick, and which one is a decision
that belongs to a project. The grid renders the stored image at CSS size. The
consequence worth writing down is a privacy one: a photo taken on a phone
carries GPS coordinates, and RockAdmin stores it exactly as it arrived.

**`file` and `image` are not the same feature.** `file` is how a value gets in;
`image` is how it is shown. The common pairing is a `file` field with an
`image` display over the same column, and specification 13 lists them
separately for exactly that reason.

## Pre-flight scan

**Nothing in the project stores or receives a file.** `Request::fromGlobals()`
builds from `$_GET`, `$_POST`, cookies and headers; `$_FILES` is a second,
differently shaped superglobal that nothing reads. `Submission` is built from
`$request->body`.

**`FieldType` has eleven cases and no `file`.** Adding one is a case, a schema
entry, a validator branch and a template — the shape `password` and
`multiselect` already follow.

**`ColumnType` has seven cases and no `image`; `Display` has six and no
`image`.** `CellFormatter` dispatches on both.

**Milestone 11 gives the write path everything this needs.** `afterCommit` is
already drained after the commit and already discarded on a rollback, and the
`Operation` seam means a project can take the whole thing over.

**The design system has `.ra-avatar` with a square variant**, which is what an
image cell wants, and no file-upload control — so the control is built from
`ra-btn`, `ra-input` and `ra-field` primitives, which is what the design system
expects when it has no component for something.

**`SmtpTransport` is the shape to copy for R2.** A seam over the bytes, a real
implementation, and a scripted fake that makes the protocol testable without a
server.

## Global constraints

- **No runtime dependencies.** No AWS SDK, no Flysystem, no Guzzle.
  `composer.json` is unchanged; `ext-curl` and `ext-fileinfo` are used when
  present and never required.
- **No framework coupling.** `Storage` is the seam; a host with its own disk
  abstraction wires it in one argument.
- **Every configuration key is in the schema**, or `ReferenceIsCurrentTest`
  fails.
- **Both databases.** A file's key is a column value like any other.
- **Both drivers.** Every test that can run against local and R2 alike runs
  against both, with R2 behind a scripted HTTP client; a test against a real
  bucket runs only when `RA_TEST_R2_*` is set, the way the database tests do.

---

### Task 1: Somewhere to put bytes

**Files:** create `src/Storage/Storage.php`, `StoredFile.php`,
`StorageException.php`, `LocalStorage.php`; modify `src/Config/RootSchema.php`.
Test: `tests/Unit/Storage/LocalStorageTest.php`.

The seam — `put`, `delete`, `exists`, `url` — and the driver that needs no
configuration beyond a directory. Keys are validated on the way in: no `..`, no
leading slash, no backslash, no empty segment.

**The test that matters:** a key that tries to escape the directory is refused
rather than normalised, on a path built from a value a form supplied.

---

### Task 2: Receiving one

**Files:** create `src/Http/UploadedFile.php`; modify `src/Http/Request.php`,
`src/Form/Submission.php`, `demo/index.php`.
Test: `tests/Unit/Http/UploadedFileTest.php`.

`$_FILES` as one value object per field, with the PHP upload error codes
translated into sentences a person can act on. `Request::fromGlobals()` reads
it; `Request` keeps taking files as an argument, so tests build one without
touching a superglobal.

**The test that matters:** a POST larger than `post_max_size` — empty body,
empty files, a `CONTENT_LENGTH` that exceeds the limit — is reported as "that
file was too large" and never as a failed token.

---

### Task 3: The `file` field

**Files:** modify `src/Page/FieldType.php`, `FieldSchema.php`,
`FieldDefinition.php`, `src/Form/FieldValidator.php`, `src/Form/FormRegion.php`;
create `templates/region/form/field/file.php`.
Test: `tests/Unit/Form/FileFieldTest.php`.

The type, its keys (`accept`, `max_bytes`), its validation, and a control that
shows what is already there with a way to replace it or take it off.

**The test that matters:** an edit that uploads nothing keeps the stored key,
and ticking "remove" clears it — the same distinction the password box makes,
and the same silent damage if it is got wrong.

---

### Task 4: Writing it

**Files:** create `src/Form/FileWrites.php`; modify
`src/Http/ActionHandler.php`.
Test: `tests/Integration/Form/FileWriteFlowTest.php`.

Store before the write, put the key in the column, and queue the old file's
deletion for after the commit. A write that is refused — by validation, by the
database, by an operation — leaves nothing behind.

**The test that matters:** a create whose INSERT violates a constraint leaves
no file in storage; an update that replaces a file leaves exactly one, and the
old one is gone only once the transaction committed.

---

### Task 5: Showing it

**Files:** modify `src/Page/ColumnType.php`, `Display.php`, `ColumnSchema.php`,
`src/Grid/CellFormatter.php`; create `templates/region/list/cell/image.php`.
Test: `tests/Unit/Grid/ImageCellTest.php`.

`type: image` and `display: image`, rendered through `.ra-avatar--sq` in a grid
and larger in a preview. A key that resolves to nothing draws the empty state
rather than a broken image.

**The test that matters:** the cell renders a URL the storage built, never the
raw column value, and escapes it as a URL rather than as text.

---

### Task 6: Serving it

**Files:** create `src/Http/FileHandler.php`; modify `src/Http/Router.php`,
`src/Auth/Gate.php`, `demo/index.php`.
Test: `tests/Integration/Http/FileRouteTest.php`.

`GET /_file/{key...}`, for the private case: streamed from the storage, with
the stored content type and `Content-Disposition: inline` for images and
`attachment` for everything else.

**The URL is signed, and the gate is the floor rather than the whole answer.**
A security review of task 1 was right about this: `RouteAccess::Authenticated`
means any administrator who can sign in can fetch any key, and a random key is
capability-by-obscurity rather than access control -- which in a multi-tenant
admin means somebody who works in one workspace can fetch a file belonging to
another if the key ever reaches them. So a local URL carries an HMAC over the
key, an expiry and the session it was built for, exactly as the R2 driver
signs one: the link works for the person it was drawn for, for minutes, and a
key on its own is worth nothing. Per-page permissions on files remain v1.1
(specification 13 puts column-level permissions out of v1), and the guide says
so.

**The test that matters:** an anonymous request for a key that exists is
refused, a signed URL from one session does not work in another, an expired
one does not work at all, and a key with `..` in it never reaches the
storage.

---

### Task 7: R2

**Files:** create `src/Storage/S3Storage.php`, `SignatureV4.php`,
`src/Http/HttpClient.php`, `CurlHttpClient.php`, `StreamHttpClient.php`.
Test: `tests/Unit/Storage/S3StorageTest.php`,
`tests/Integration/Storage/R2Test.php` (skipped unless `RA_TEST_R2_*` is set).

SigV4 by hand, path-style addressing against the account endpoint, `auto` as
the region, and presigned GET for reading. The HTTP client is a seam so the
signature and the failure paths are testable without a network.

**The test that matters:** a signature computed against AWS's own published
test vectors matches byte for byte — that is the one part of this that cannot
be "nearly right", because a wrong signature fails identically to wrong
credentials and the server will not say which.

---

### Task 8: The demo, and the recipe

**Files:** `demo/config/pages/ads.php`, `demo/seed.php`, `demo/index.php`,
`docs/guides/files-and-images.md`.
Test: `tests/Integration/Form/DemoFileTest.php`.

A photo on the ad, stored locally by default, with the four lines that move it
to R2 written out — and the three that hand the whole thing to a project's own
Flysystem.

---

### Task 9: Looking at it

**Files:** the plan's own report.
Test: a browser pass.

Upload a photo, see it in the grid and the preview, replace it, remove it,
delete the row, and check the bucket after each. Against R2 as well if
credentials are available; against the local driver regardless.

## Milestone acceptance

1. `composer run check` green with both databases.
2. A file can be uploaded, shown, replaced and removed, with only its key in
   the column.
3. The same page configuration works on local disk and on R2, changing one
   configuration key.
4. The stored type comes from the bytes; the uploaded filename never reaches a
   path.
5. A write that fails leaves no file; a replaced file is deleted after the
   commit and not before.
6. Both drivers are private by default.
7. Every new key is in the generated reference.
8. `composer show --tree` lists no new dependency.

## What this milestone deliberately leaves out

- **Image processing** — resizing, thumbnails, EXIF stripping. All of it needs
  an extension, and which one is the project's decision.
- **Direct browser-to-R2 uploads** with a presigned PUT. It is the right answer
  for large files and it needs JavaScript and a CORS policy on the bucket;
  reserved, not forgotten.
- **Multipart (chunked) uploads.** An admin upload is capped far below the 5 GB
  where S3 requires them.
- **Orphan collection.** Nothing sweeps files whose rows are gone; it is an
  environment job and belongs with the CLI.
- **Per-column file permissions.** Specification 13 puts row-level and
  column-level permissions out of v1, and this inherits that: an administrator
  who may see any page may fetch any uploaded file.
