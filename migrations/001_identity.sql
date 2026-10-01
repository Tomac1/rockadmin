-- The identity tables of specification 9.1, written once for both servers.
--
-- Every {{ddl.*}} placeholder is a thing MySQL and PostgreSQL spell
-- differently and nothing else; Dialect::ddl() is where the two spellings
-- live, so this file never forks. Timestamps hold UTC, written by the
-- application -- neither server is asked to supply a default, because their
-- defaults differ and the application already knows what time it is.

CREATE TABLE ra_users (
    id {{ddl.key}},
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255),
    name VARCHAR(255) NOT NULL,
    -- is_active defaults to true because a row in this table exists only
    -- because somebody with database access put it there. Defaulting to
    -- inactive would make the first administrator created by the CLI unable
    -- to log in, which people fix by flipping the column and never think
    -- about again -- and it buys nothing, since the insert is already the
    -- privileged act.
    is_active {{ddl.bool}} NOT NULL DEFAULT TRUE,
    roles {{ddl.json}} NOT NULL,
    workspaces {{ddl.json}} NOT NULL,
    auth_provider VARCHAR(50),
    auth_subject VARCHAR(255),
    last_login_at {{ddl.timestamp}},
    created_at {{ddl.timestamp}} NOT NULL
);

CREATE UNIQUE INDEX ra_users_provider_subject ON ra_users (auth_provider, auth_subject);

CREATE TABLE ra_login_attempts (
    id {{ddl.key}},
    email VARCHAR(255) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    at {{ddl.timestamp}} NOT NULL
);

CREATE INDEX ra_login_attempts_email_at ON ra_login_attempts (email, at);
CREATE INDEX ra_login_attempts_ip_at ON ra_login_attempts (ip, at);

CREATE TABLE ra_password_resets (
    id {{ddl.key}},
    email VARCHAR(255) NOT NULL,
    token_hash VARCHAR(64) NOT NULL UNIQUE,
    expires_at {{ddl.timestamp}} NOT NULL,
    used_at {{ddl.timestamp}}
);

CREATE INDEX ra_password_resets_email ON ra_password_resets (email);

CREATE TABLE ra_activity_log (
    id {{ddl.key}},
    user_id {{ddl.bigint}},
    page VARCHAR(100) NOT NULL,
    action VARCHAR(30) NOT NULL,
    row_id VARCHAR(255),
    at {{ddl.timestamp}} NOT NULL,
    diff {{ddl.json}}
);

CREATE INDEX ra_activity_log_user_at ON ra_activity_log (user_id, at);
CREATE INDEX ra_activity_log_page_row ON ra_activity_log (page, row_id);
