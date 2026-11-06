# Release test record — local_codechecker vX.Y.Z

Copy this file to `tests/release/vX.Y.Z.md` (same name as the release tag), fill it in, and merge it
with the `version.php` bump. The release workflow refuses to build the zip until every box is ticked
and the result is PASS. Tick an item that does not apply and write `N/A: <reason>` after it.

Date: DD/MM/YYYY
Tester:
Previous release: vX.Y.Z
Result: FAIL

## 1. Automated (CI)

- [ ] CI green on the release commit on the plugin repo's `main` (paste the run link):
- [ ] security-lint: 0 findings.
- [ ] OSV check: no known vulnerabilities; each library it listed as "check by hand" looked up on its
      advisories page (list them, or "none"):
- [ ] New `Security-lint: ignore` suppressions since the previous release reviewed (list each, or "none"):
      `git diff <previous tag>..HEAD | grep -n "Security-lint: ignore"`
- [ ] PHPUnit security tests present and passing: query scaling, events, privacy, output safety, forms/access.
- [ ] Every new loop over site data, auditable action, adhoc task, form, web service and template since the
      previous release has a row in the matching test's data provider.

## 2. Access, logic and requests (S0, S1, S2, S8, S13)

- [ ] Every change to login, capability, group or "who can see this" logic read line by line by someone other
      than the author (or re-read the next day): conditions, return values, early exits (S0).
- [ ] Each new or changed page/web service opened as: admin, manager, editing teacher, teacher, student, guest,
      logged out. Only the intended roles get in; others see an error, not data.
- [ ] Each state-changing action checked in the browser network tab: it is a POST with a sesskey.
- [ ] Pasting the action's URL (GET) into the address bar changes nothing.
- [ ] Every bulk or destructive action shows a confirmation naming what will happen ("Yes, delete 12 courses")
      and its capability has RISK_DATALOSS (S13).

## 3. Hostile input (S3, S5, S10)

Payloads: `<</style/style><script>alert(1)</script>` · `<img src=x onerror=alert(1)>` ·
`"><svg onload=alert(1)>` · `javascript:alert(1)` · `JaVaScRiPt:alert(1)`

- [ ] Each payload entered in every new or changed free-text field, URL field and admin setting; no alert fires
      on save, on view, in the editor, in modals or in exports.
- [ ] Data from external systems (iMIS, CSV imports, APIs) tested with the same payloads in names and messages.

## 4. Privacy (S17)

- [ ] Admin > Users > Privacy and policies > Data requests: export for a test learner includes this plugin's data.
- [ ] Delete request for that learner removes the data and any queued tasks carrying it.
- [ ] Privacy metadata (`lang` strings, `get_metadata()`) matches what the plugin now stores or sends.

## 5. Performance (P1, P3, P4)

- [ ] New or changed bulk paths run against a large dataset (at least 1,000 rows/users/courses; record sizes
      and times here):
- [ ] No request takes more than 5 seconds; slow work is queued as an adhoc task.

## 6. Upgrade, backup and compatibility

- [ ] Upgrade from the previous release on a copy of production data; Notifications page shows no errors.
- [ ] Backup and restore of a course using the plugin (if it stores course data).
- [ ] Spot-checked on the lowest (5.0 / PHP 8.2) and highest (5.3 / PHP 8.4) supported versions.
- [ ] AU spelling, `userdate()` dates (DD/MM/YYYY) and AUD amounts in new UI text.

## 7. External audit

- [ ] Moodle Audit Service run on the release commit; every finding has a decision below (or "No findings").

| # | ID | Decision | Note |
|---|----|----------|------|
| 1 | S5 | Fixed / False positive / Accepted risk | Commit or reason |

## 8. Sign-off

- [ ] Marketplace/listing description, README requirements and `$plugin->supported` still match.
- [ ] Result line above changed to `Result: PASS`.
