# Testing plan — local_codechecker

This is the standing test plan. Each release also gets a filled-in record in
`tests/release/<tag>.md` (copied from `tests/release/TEMPLATE.md`). The "Release zip" workflow
refuses to attach a zip until that record is complete, then attaches it to the GitHub release
next to the zip.

`TESTING.md` and `tests/` are `export-ignore`d, so neither is in the installable zip.

## What this plugin does, for risk purposes

- One admin page (`index.php`, `admin_externalpage_setup('local_codechecker')`, so site
  administrators only) and a CLI script (`run.php`).
- It reads PHP, JS and other files under the Moodle code tree, runs PHP_CodeSniffer and the
  opt-in audit sniffs over them, and **shows lines of source code** in the results.
- It stores nothing: no database tables, no files, no events, no tasks, no web services.
  The privacy provider is a `null_provider`.

So the risks to test are: reading outside the code tree (path traversal), escaping of the
source lines and messages it displays, access to the admin page, and long-running checks on a
web request.

## What runs automatically

| When | Check | Catches (audit IDs) |
|---|---|---|
| Every push / PR | moodle-plugin-ci: phplint, phpcs, phpdoc, validate, savepoints, mustache, grunt | Style, docs, templates, AMD build |
| Every push / PR | `security-lint` (moodle-security-ci) | S1, S3, S5–S9, S11, S12, S14–S20, P1–P4, Q3, Q4, Q6–Q8 patterns |
| Every push / PR | OSV known-vulnerability check on `composer.lock` (the bundled `vendor/`) | S20/S21 |
| Every push / PR | PHPMD unused-code ruleset (blocking) | Q7 |
| Every push / PR | PHPUnit, including the security tests below, and Behat (`ui.feature`), Moodle 5.0–5.3 | S5/S10, S4/S2, S17, plugin logic, UI journey |
| Release (tag published) | Tag = `$plugin->release`, security-lint, OSV, test record complete | Releasing untested or unreviewed code |

## Tests in this plugin

Keep one row per data-provider entry current. A new output path, form or adhoc task is not
finished until it has a row here and in the test.

| Test file | Covers | Entries |
|---|---|---|
| `tests/output_safety_test.php` | Checked code stays inert in the report (S5/S10) | File list, invalid path, audit results, full report |
| `tests/forms_and_access_test.php` | Typed form fields; admins only (S4/S2) | Check form; admin page needs `moodle/site:config` |
| `tests/privacy_test.php` | Null provider has a reason; every adhoc task is classified (S17) | No tasks today |
| `tests/audit_test.php` | The MoodleAudit phpcs standard | Each audit sniff, opt-in, plugin-level checks, checklist |
| `tests/locallib_test.php` | The checker's own helpers | Non-PHP files, problem counts, licence sniffs, paths, ignores |
| `tests/behat/ui.feature` | Running a check from the admin page | UI journey |

`output_safety_test` feeds the report the lines of `tests/fixtures/hostile.txt`, with the same
payloads as its phpcs messages and sniff names. `forms_and_access_test` also checks that a
student, teacher and manager are refused.

Not needed here, from moodle-security-ci's five templates:

- `query_scaling_test.php` (P1/P3): the plugin reads no database rows. It loops over files on
  disk, not site data. Add it if a loop over site data appears.
- `events_test.php` (S12): the plugin changes no data. It reads code and shows a report, so
  there is no auditable action to log. Add it with the first create, update, delete or export
  action.

The three that apply keep a new risk from going unnoticed: a new output path needs a payload
row, a new form needs a provider row, and a new adhoc task fails `privacy_test` until it is
classified. The manual checks below still test the same risks in a real browser, where the
tests can't reach (for example the path box itself and the network tab).

## Manual checks every release

These are in the release record:

1. Second read of any change to path handling, access checks or output escaping (S0).
2. The admin page refuses everyone but site administrators (S1/S2).
3. Path traversal: `../`, absolute paths and paths outside the code tree are refused (S3).
4. Escaping: hostile content in a checked file and in the path field shows as text (S5).
5. Large check timing: a whole large plugin or `lib/` on the web page (P3).
6. Upgrade from the previous release; lowest and highest supported Moodle versions.
7. Moodle Audit Service report on the release commit, every finding triaged.

## Suppressions

`security-lint` findings that are deliberate are suppressed in the code, with a reason:

```php
// Security-lint: ignore S3 (a list of paths, one per line; each line is cleaned with PARAM_PATH below).
```

Each release record lists suppressions added since the previous release, so they are reviewed once.

## Releasing

1. On a branch: bump `version.php` (`version`, `release`), add the `CHANGES.md` entry, copy
   `tests/release/TEMPLATE.md` to `tests/release/vX.Y.Z.md` and work through it. Tick each item,
   or tick it and write `N/A: <reason>`.
2. Set `Result: PASS`, merge to `main`, and confirm CI is green on that commit.
3. Publish the GitHub release with tag `vX.Y.Z` from `main`.
4. Confirm the release has `local_codechecker-vX.Y.Z.zip`, its `.sha256` and the test record.

If a gate fails, nothing is attached. The gates check the code **at the tag**, so a fix merged to
`main` afterwards is not in it:

- **The code or the record is wrong** (lint finding, missing or unticked record): delete the
  release and its tag (no zip was attached, so nobody has it), fix on `main`, and publish the
  release again with the same tag.
- **A temporary failure** (network, GitHub outage), or a zip needs rebuilding later: use
  **Actions → Release zip → Run workflow** with the tag. The gates run again.
