# Release test record — local_codechecker vX.Y.Z

Copy this file to `tests/release/vX.Y.Z.md` (same name as the release tag), fill it in, and merge it
with the `version.php` bump. The "Release zip" workflow refuses to build the zip until every box is
ticked and the result is PASS. Tick an item that does not apply and write `N/A: <reason>` after it.
See `TESTING.md` for why each check is here.

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

## 2. Access and logic (S0, S1, S2, S8)

- [ ] Every change to path handling, access checks or output escaping read line by line by someone
      other than the author (or re-read the next day): conditions, return values, early exits (S0).
- [ ] *Site administration → Development → Code checker* opened as: admin (works); manager, teacher,
      student, guest and logged out (each refused, no results shown).
- [ ] Running a check from the form is a POST with a sesskey (browser network tab). The GET form of
      the URL only runs a read-only check and changes nothing (S8).

## 3. Paths and hostile input (S3, S5)

Path payloads: `../../config.php` · `/etc/passwd` · `local/codechecker/../../../` · `C:\Windows` ·
an empty line · a path to a file that does not exist.

Content payloads: `<script>alert(1)</script>` · `<img src=x onerror=alert(1)>` · `"><svg onload=alert(1)>`

- [ ] Each path payload in the path box and in the exclude box: refused with the "invalid path"
      message or ignored; nothing outside the Moodle code tree is read or shown.
- [ ] A test plugin file containing each content payload (in a comment, a string and a filename) is
      checked: the source lines and messages show the payload as text; no alert fires.
- [ ] Same with *Run audit checks (security, code quality and performance)* ticked, and with
      *Display phpcs standard associated with a problem* ticked.

## 4. Privacy (S17)

- [ ] The plugin still stores no personal data, so `classes/privacy/provider.php` stays a
      `null_provider` (or the provider and its strings were updated to match).

## 5. Performance (P3)

- [ ] A large check on the web page (for example all of `mod/assign` or `lib/`, with and without
      audit checks), with the time it took (record the path and seconds):
- [ ] The same check from the CLI (`php local/codechecker/run.php <path>`) completes.

## 6. Upgrade and compatibility

- [ ] Upgrade from the previous release; the Notifications page shows no errors.
- [ ] Spot-checked on the lowest (5.0 / PHP 8.2) and highest (5.3 / PHP 8.4) supported versions.
- [ ] AU spelling and `userdate()` dates (DD/MM/YYYY) in any new UI text.

## 7. External audit

- [ ] Moodle Audit Service run on the release commit; every finding has a decision below (or "No findings").

| # | ID | Decision | Note |
|---|----|----------|------|
| 1 | S5 | Fixed / False positive / Accepted risk | Commit or reason |

## 8. Sign-off

- [ ] The installable zip from a test build has a `codechecker/` root and no `.github/`, `tests/`,
      `TESTING.md` or `CLAUDE.md`.
- [ ] README requirements, `$plugin->supported` and any listing description still match.
- [ ] Result line above changed to `Result: PASS`.
