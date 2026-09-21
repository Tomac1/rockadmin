## What this changes

<!-- One or two sentences. Link the issue if there is one. -->

## Why

<!-- What problem this solves. -->

## Checklist

- [ ] `composer run check` passes (coding standards, static analysis, tests)
- [ ] No new Composer runtime dependency (see CONTRIBUTING.md)
- [ ] Every new or changed configuration key is declared in the schema
- [ ] New templates carry `ra-` classes, contain no `<script>`, no hardcoded
      URLs, and escape all output
- [ ] Behaviour works inside a re-rendered fragment, not only on first load
- [ ] Tested against both MySQL and PostgreSQL, if the change touches SQL
- [ ] CHANGELOG.md updated under "Unreleased"
