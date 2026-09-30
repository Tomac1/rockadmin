<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * An applied migration's file no longer matches the checksum recorded for it.
 *
 * A type rather than a string to match, for the same reason
 * `NoSuchRowException` is one: a caller has to tell this apart from every other
 * way the data layer fails, and the two answers are opposite. "The database is
 * unreachable" is a retry in ten seconds; "the migrations have been edited
 * under an installation that already ran them" is a deploy that must stop and
 * a person who must look at it. A deploy script deciding between those by
 * matching the message would be tied to wording that is free to change, in
 * this project and in any driver whose text it also has to sift.
 *
 * It carries no SQLSTATE because no driver raised it — `Migrator` discovered
 * the disagreement itself, by hashing the file and comparing.
 *
 * What it does *not* mean is worth stating, because the guarantee is narrower
 * than it first reads: this can only fire where the migration has already been
 * applied. An installation that has never applied a file receives the edited
 * version and applies it, correctly — there is nothing to compare against. The
 * invariant is the rule that an applied migration is never edited; the
 * checksum enforces that rule only where somebody already ran it.
 */
final class MigrationConflictException extends DbException
{
    public static function edited(string $name, string $ledger): self
    {
        return new self(\sprintf(
            "Migration '%s' has been edited since it was applied: the file no longer matches the "
                . 'checksum recorded in %s. An applied migration must never be changed, because an '
                . 'installation that has already run it will never read it again -- the two would hold '
                . 'different schemas from the same commit. Put the change in a new migration and '
                . 'restore this file.',
            $name,
            $ledger,
        ));
    }
}
