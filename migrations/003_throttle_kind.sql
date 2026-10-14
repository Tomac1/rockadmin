-- What a recorded attempt was an attempt at.
--
-- `ra_login_attempts` counts attempts for the login throttle, and password
-- reset needs the same counting: by address, so one account cannot be
-- hammered, and by IP, so one machine cannot work through a list of
-- addresses.
--
-- Sharing one counter would have been the smaller change and is the wrong
-- one. Ten forgotten passwords would exhaust the login allowance and lock
-- somebody out of the form they were trying to reach -- and the reverse is
-- worse: a login lockout that also blocks the reset request removes the one
-- self-service way out of it, which is exactly the situation where somebody
-- is already stuck.
--
-- So each row says which budget it belongs to. A second table was the other
-- option; one table with a kind keeps "how many attempts has this address
-- made at anything" answerable in one query, which is the question asked
-- after an incident rather than during one.
--
-- DEFAULT 'login' rather than NOT NULL with no default: rows written before
-- this migration were login attempts, and an existing installation applying
-- it must not have to decide what they were.

ALTER TABLE ra_login_attempts ADD COLUMN kind VARCHAR(20) NOT NULL DEFAULT 'login';

-- The two indexes from 001 lead with the column that is looked up and the
-- time it is bounded by; the kind goes on the end of both rather than the
-- front, because every query filters all three and the leading column is the
-- selective one. Replacing them would need a dialect difference to drop an
-- index MySQL and PostgreSQL name differently (the same reason 002 kept the
-- constraint it made redundant), so these are added alongside and the planner
-- picks.
CREATE INDEX ra_login_attempts_kind_email_at ON ra_login_attempts (kind, email, at);
CREATE INDEX ra_login_attempts_kind_ip_at ON ra_login_attempts (kind, ip, at);
