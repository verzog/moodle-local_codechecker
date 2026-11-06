# Testing plan — local_codechecker

This is the standing test plan. Each release also gets a filled-in record in
`tests/release/<tag>.md` (copied from `tests/release/TEMPLATE.md`); the release workflow checks it
and attaches it to the GitHub release next to the zip.

Both files are `export-ignore`d, so they are not in the installable zip.

## What runs automatically

| When | Check | Catches (audit IDs) |
|---|---|---|
| Every push / MR | moodle-plugin-ci: phplint, phpcs, phpdoc, validate, savepoints, mustache, grunt | Style, docs, templates, AMD build |
| Every push / MR | `security-lint` (moodle-security-ci) | S1, S3, S5–S9, S11, S12, S14–S20, P1–P4, Q3, Q4, Q6–Q8 patterns |
| Every push / MR | OSV known-vulnerability check | S20/S21 for lockfiles |
| Every push / MR | PHPMD unused-code ruleset (blocking) | Q7 unused variables, private methods, private fields |
| Every push / MR | PHPUnit, including the security tests below | P1 by measurement, S12, S17, S5, S10, S4, S2/S19 |
| Every push / MR | Behat (one cell) | User journeys |
| Release (tag published) | Tag = `$plugin->release`, security-lint, OSV, test record complete | Releasing untested or unreviewed code |

## Security and performance tests in this plugin

Keep one row per data-provider entry current. A new loop over site data, auditable action, adhoc
task, form, web service or template is not finished until it has a row here and in the test.

| Test file | Covers | Entries |
|---|---|---|
| `tests/output_safety_test.php` | Checked code stays inert (S5/S10) | File list, invalid path, audit results, full report |
| `tests/forms_and_access_test.php` | Typed form fields; admins only (S4/S2) | Check form; admin page needs `moodle/site:config` |
| `tests/privacy_test.php` | Null provider has a reason; every adhoc task is classified (S17) | No tasks today |
| `tests/locallib_test.php` | The checker's own helpers | Existing unit tests |
| `tests/audit_test.php` | The MoodleAudit phpcs standard | Existing unit tests |

Not needed here, from moodle-security-ci's five templates:

- `query_scaling_test.php` (P1/P3): the plugin reads no database rows. It loops over files on disk, not site
  data. Add it if a loop over site data appears.
- `events_test.php` (S12): the plugin changes no data. It reads code and shows a report, so there is no
  auditable action to log. Add it with the first create, update, delete or export action.

`output_safety_test` feeds the report the lines of `tests/fixtures/hostile.txt`, with the same payloads as its
phpcs messages and sniff names. `forms_and_access_test` also checks that a student, teacher and manager are refused.

The three that apply keep a new risk from going unnoticed: a new output path needs a payload row, a new
form needs a provider row, and a new adhoc task fails `privacy_test` until it is classified.

## Manual checks every release

These cannot be automated reliably, so they are in the release record:

1. Second read of every auth/permission logic change (S0), and role matrix on new or changed pages and web
   services (S1/S2).
2. State-changing actions are POST + sesskey; a pasted GET URL changes nothing (S8); destructive actions
   confirm first (S13).
3. Hostile-input payloads in every new free-text field, URL field, admin setting and external feed (S3/S5/S10).
4. Privacy export and delete through the Data requests tool (S17).
5. Bulk paths against a large dataset, with timings (P1/P3/P4).
6. Upgrade from the previous release, backup/restore, lowest and highest supported versions.
7. Moodle Audit Service report on the release commit, every finding triaged.

## Suppressions

`security-lint` findings that are deliberate are suppressed in the code, with a reason:

```php
// Security-lint: ignore P1 (bounded: loops over the three fixed template types).
```

Each release record lists suppressions added since the previous release, so they are reviewed once.
