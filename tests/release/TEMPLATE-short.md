# Release test record (short) — local_codechecker vX.Y.Z

Record: short

For a release that changes none of the paths in `tests/release/risky-paths.txt` since the previous
release (for example only sniffs under `standards/`, language strings, styles or documentation).
Copy this file to `tests/release/vX.Y.Z.md` (same name as the release tag), fill it in, and merge it
with the `version.php` bump. The "Release zip" workflow refuses to build the zip until every box is
ticked and the result is PASS, and it refuses this short record if any risky path changed: then use
`tests/release/TEMPLATE.md` instead. Leave the `Record: short` line as it is. Tick an item that does
not apply and write `N/A: <reason>` after it. See `TESTING.md` for why each check is here.

Date: DD/MM/YYYY
Tester:
Previous release: vX.Y.Z
Result: FAIL

## 1. Automated (CI)

- [ ] CI green on the release commit on `main` (paste the run link):
- [ ] security-lint: 0 findings (the `security` job).
- [ ] OSV check: no known vulnerabilities; no library listed as "check by hand" (if one is, look up
      its advisories and list it here):
- [ ] New `Security-lint: ignore` suppressions since the previous release reviewed (list each, or "none"):
      `git diff <previous tag>..HEAD | grep -n "Security-lint: ignore"`
- [ ] PHPUnit and Behat passing on every Moodle 5.0–5.3 cell, including the security tests: output safety,
      forms/access, privacy (query scaling and events don't apply; see TESTING.md).
- [ ] Every new output path, form and adhoc task since the previous release has a row in the matching
      test's data provider.
- [ ] Bundled `vendor/` changed since the previous release? If so, `thirdpartylibs.xml` versions
      match `composer.lock` and `readme_moodle.txt` is updated (or "unchanged"):

## 2. Scope

- [ ] No path in `tests/release/risky-paths.txt` changed since the previous release. This prints
      nothing (the "Release zip" workflow runs the same check):
      `git diff --name-only <previous tag>..HEAD -- $(grep -vE '^[[:space:]]*(#|$)' tests/release/risky-paths.txt)`
- [ ] The `CHANGES.md` entry describes every change since the previous release.

## 3. Sign-off

- [ ] The installable zip from a test build has a `codechecker/` root and no `.github/`, `tests/`,
      `TESTING.md` or `CLAUDE.md`.
- [ ] README requirements, `$plugin->supported` and any listing description still match.
- [ ] Result line above changed to `Result: PASS`.
