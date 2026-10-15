<?php

declare(strict_types=1);

namespace RockAdmin\Storage;

/**
 * What every driver agrees a key may look like.
 *
 * One place, because two drivers with two opinions about `..` is one driver
 * with a hole in it -- and because the check has to hold for keys that arrive
 * from a database column, which is to say from whatever was written there
 * before anybody thought about this.
 *
 * **Refused rather than normalised.** `a/../../etc/passwd` could be flattened
 * into something safe, and then an attacker's key and a real key would be the
 * same key: the row would say one thing and the file would be another. A key
 * that is not already safe is not a key this project has ever written, so the
 * honest answer is to refuse it and say which key.
 */
final class StorageKey
{
    /**
     * Lower-case letters, digits and the three punctuation marks a generated
     * key uses, in slash-separated segments.
     *
     * Deliberately narrower than a filesystem allows. Everything this project
     * writes is built by `UploadedFile::keyFor()` out of a date, random hex
     * and an extension from a fixed table, so the pattern only has to admit
     * what that produces -- and a pattern that admits exactly what is written
     * is a pattern that refuses everything else, including a Windows drive
     * letter, a UNC path, a NUL byte and a name that is all dots.
     */
    private const string PATTERN = '~^[a-z0-9][a-z0-9._-]*(/[a-z0-9][a-z0-9._-]*)*$~D';

    public static function isValid(string $key): bool
    {
        if ($key === '' || \strlen($key) > 255) {
            return false;
        }

        if (str_contains($key, '..')) {
            // Caught by the pattern as well, since `..` cannot begin a
            // segment -- but spelled out here because it is the thing this
            // class exists for, and a future edit to the pattern should fail
            // this line rather than quietly admit it.
            return false;
        }

        return preg_match(self::PATTERN, $key) === 1;
    }

    /** @throws StorageException naming the key, because a caller cannot guess which one was wrong */
    public static function require(string $key): string
    {
        if (!self::isValid($key)) {
            throw new StorageException(\sprintf(
                "'%s' is not a storage key this admin wrote. Keys are lower-case, slash-separated, "
                . 'and generated from the uploaded bytes -- anything else reaching here came from '
                . 'somewhere it should not have.',
                // The key is quoted into a message somebody may well paste
                // into a terminal; control characters are the one thing in it
                // that could do something there.
                preg_replace('/[^\x20-\x7E]/', '?', $key) ?? '',
            ));
        }

        return $key;
    }
}
