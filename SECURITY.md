# Security Policy

## Reporting a vulnerability

**Please do not open a public issue for a security problem.**

Report it privately through GitHub:
[Report a vulnerability](https://github.com/tomac1/rockadmin/security/advisories/new).
The report is visible only to the maintainers until a fix is published.

Please include:

- what the vulnerability allows an attacker to do
- the smallest configuration and steps that demonstrate it
- affected versions, PHP version and database, if relevant

You can expect an acknowledgement within a few days and an assessment of
whether the report is confirmed. If it is, you will be told when a fix is
planned, and credited in the advisory and the changelog unless you prefer
otherwise.

Please give us a reasonable opportunity to publish a fix before disclosing the
issue publicly.

## Supported versions

RockAdmin has not had its first release yet. Once it does, this section will
list which versions receive security fixes. Until then, only `main` is
supported.

## Scope

RockAdmin is an administration interface, so the areas most worth attention
are:

- authentication, session handling, CSRF, and the password reset flow
- the permission gate — in particular whether a check can be bypassed through
  a region fragment (`/r/...`) or an action (`/a/...`) rather than a full page
- workspace scoping — whether a user can read or write a row belonging to a
  workspace they have no access to, for example by editing an id in the URL
- SQL injection through filters, sorting, or configuration placeholders
- output escaping in templates
- the `_ret` return parameter, which must never permit an external redirect
- the setup route and the diagnostics route, which must not be reachable
  without their respective token and permission

## Out of scope

- Vulnerabilities in a host application that merely uses RockAdmin
- Configuration that a project wrote insecurely, such as `raw_condition` built
  from request input, which the documentation explicitly marks as dangerous
- The development console being exposed on a server where the project
  deliberately enabled it in production

If you are unsure whether something is in scope, report it anyway.
