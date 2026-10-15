<?php

declare(strict_types=1);

/**
 * The configuration the demo loads.
 *
 * Small but real: every key here is one an actual project would set, and
 * every key is declared in RootSchema — that is what \RockAdmin\Config\Loader
 * checks it against. There is nothing demo-specific about this format; a
 * project's own rockadmin.php looks exactly like this, only bigger.
 *
 * `pages_path` is left at its default, so `demo/config/pages/ads.php` is the
 * `ads` page, reachable at `/p/ads` and refreshed a region at a time from
 * `/r/ads/{region}`.
 */
return [
    'brand' => 'RockAdmin Demo',
    // 'path' needs a rewrite rule; RA_URL_MODE=query runs the same demo with
    // none, e.g.: RA_URL_MODE=query php -S localhost:8080 -t demo demo/index.php
    // Query mode is otherwise never exercised anywhere in this repository --
    // this is what makes it possible to look at, not only read about.
    'url_mode' => getenv('RA_URL_MODE') !== false ? getenv('RA_URL_MODE') : 'path',
    'debug' => true,
    'paths' => [
        'logs' => __DIR__ . '/../storage/logs',
    ],
    'theme' => [
        'dark' => 'auto',
    ],

    /*
     * Where this demo is reachable from outside, for the links in mail.
     *
     * It is read from the environment because a demo is run on whatever port
     * is free, and left empty when nothing says. Empty is a real state and the
     * reset form says so plainly rather than sending a link to nowhere --
     * which is better than the alternative of building one from the Host
     * header, a value whoever sent the request chose.
     *
     *     RA_PUBLIC_URL=http://localhost:8080 php -S localhost:8080 -t demo demo/index.php
     */
    'public_url' => getenv('RA_PUBLIC_URL') !== false ? getenv('RA_PUBLIC_URL') : '',

    /*
     * Uploaded files, in a directory beside the logs.
     *
     * `storage/uploads` is inside the demo folder, which the built-in server
     * also serves -- so this demo would be the one arrangement the
     * documentation warns about, were the files reachable by their path. They
     * are not: a key is not a path, the directory has no index, and the
     * only way to a file is `/_file/{key}` with a signature made for the
     * session asking. A real project still puts this outside the document
     * root, and `docs/guides/files-and-images.md` says so first.
     *
     * An R2 bucket is the same four lines with `driver => 's3'`; the guide
     * has them.
     */
    'storage' => [
        'driver' => 'local',
        'path' => __DIR__ . '/../storage/uploads',
        'link_minutes' => 10,
    ],

    /*
     * What may be uploaded at all. Smaller than the 5 MB default, because a
     * photograph of a bicycle is not a scan of a book and a demo is a good
     * place to show the refusal working.
     */
    'uploads' => [
        'max_bytes' => 2097152,
        'types' => ['image/jpeg', 'image/png', 'image/webp'],
    ],

    /*
     * The default driver, spelled out rather than left implicit, because the
     * point of this demo is that every key is one a project would set. It
     * writes each message to storage/logs/mail as an .eml a mail client opens;
     * nothing is sent, and the forgotten-password flow works end to end with
     * no mail server anywhere.
     */
    'mail' => [
        'driver' => 'log',
        'from' => [
            'address' => 'admin@example.com',
            'name' => 'RockAdmin Demo',
        ],
    ],
];
