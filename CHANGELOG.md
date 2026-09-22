# Changelog

All notable changes to RockAdmin are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- Design specification for v1: architecture, request lifecycle, configuration
  model, data layer, view layer, authentication, workspaces, installation and
  scope (`docs/design/2026-09-21-rockadmin-design.md`).
- Open-source project scaffolding: contribution guide, security policy, code
  of conduct, issue and pull request templates, CI across PHP 8.4 and 8.5 on
  MySQL and PostgreSQL, coding standards and static analysis configuration.
- `ComposerConstraintsTest`, which fails the build if RockAdmin gains a
  runtime dependency beyond PHP and its core extensions.
- HTTP foundation: request and response value objects, the route table and
  matcher, URL generation in path and query modes, the session abstraction,
  CSRF tokens, HTTP exceptions and the kernel.
- Configuration layer: schema with documentation for every key, a validator
  that reports paths and suggests near misses, shared definitions with deep
  merge, placeholders that resolve at load or bind per request, shared
  enumerations, the directory loader with its local override, and a compiled
  cache.

[Unreleased]: https://github.com/tomac1/rockadmin/commits/main
