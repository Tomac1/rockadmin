-- Only one account can ever be the founder, and the database says so.
--
-- `_setup` creates an installation's first administrator and is reachable
-- only while `ra_users` is empty. That guard is a read followed by a write,
-- and between the two another request can do exactly the same thing: two
-- people opening the page on a freshly migrated admin at the same moment, or
-- a stranger who found the URL during the minute between `migrate` and the
-- operator filling the form in. The unique index on the address does not
-- catch it, because the two accounts have different addresses -- so both
-- succeed, and the installation starts life with a superadmin nobody meant to
-- create.
--
-- The claim below is what makes "first wins" true rather than likely. The
-- account `_setup` creates writes 'yes' here; every other account writes
-- NULL, and both servers permit any number of NULLs in a unique index. So the
-- second concurrent setup is refused by the database, at the moment of the
-- insert, with no lock taken and no transaction isolation to reason about.
--
-- It is also a record worth having on its own: which account an installation
-- was founded with is a question an audit asks, and nothing else in the
-- schema answers it.
--
-- VARCHAR rather than a boolean: a boolean column with a unique index would
-- permit one false as well as one true, which is the opposite of what is
-- wanted on every other row.

ALTER TABLE ra_users ADD COLUMN founder VARCHAR(8);

CREATE UNIQUE INDEX ra_users_founder ON ra_users (founder);
