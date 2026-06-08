# CLAUDE.md — Moodle Plugin Development

Guidance for Claude when developing Moodle plugins for Australian deployments.
These rules are drawn from real mistakes and CI failures across the
`verzog/moodle-*` plugin suite — treat them as a pre-flight checklist, not
background reading.

**Requirements:** PHP 8.2 – 8.4, Moodle 5.0 – 5.2, plugin with `version.php` and
`$plugin->component`. CI via GitHub Actions only.

> **Project baseline:** this codebase targets **Moodle 5.0 – 5.2**. Moodle 5.0
> drops PHP 8.1, so the PHP floor is **8.2**. Moodle 5.2 supports PHP 8.3 – 8.4.
> No Moodle 4.x or PHP 8.1 compatibility is built, tested, or supported.

---

## 1. CI — GitHub Actions Setup

GitHub Actions (GHA) is the only supported CI provider. No Travis CI references.

### Configuration

- **Runner image:** `ubuntu-24.04`. `ubuntu-22.04` has been retired and every
  job will fail with `moodle-plugin-ci: command not found`.
- **Workflow file:** Copy `gha.dist.yml` from `moodle-plugin-ci` and save as
  `.github/workflows/moodle-ci.yml`.
- **AU timezone:** Add `TZ: Australia/Sydney` to the workflow `env` so date/time
  tests respect AEST/AWST offsets.
- **`max_input_vars`:** Set `PHP_INI_VALUES: max_input_vars=5000` (or equivalent
  `php.ini` step) — Moodle's PHPUnit bootstrap requires it.
- **Matrix:** PHP 8.2, 8.3, 8.4 × `pgsql` + `mysqli`, branches
  `MOODLE_500_STABLE`, `MOODLE_501_STABLE`, `MOODLE_502_STABLE`, and `main`
  (covers 5.3-dev until cut). Moodle 5.1 supports PHP 8.2 – 8.4; 5.2 supports
  PHP 8.3 – 8.4 — gate matrix combos accordingly.
- **`mysqli` driver:** run against `mariadb:10.11` (moodle-plugin-ci's own
  `gha.dist` choice). Avoids the `mysql:8.0` init-health race.
- **Service images:** pin `postgres:16.6` and `mysql:8.4.3` (or `mariadb:10.11`)
  by tag — `:latest` drift caused silent breakage.
- **Install resiliency:** wrap `composer create-project` and `moodle-plugin-ci
  install` in a 3-attempt retry with linear backoff; give the install step
  `id: install` and gate later steps on `steps.install.conclusion == 'success'`
  so a flake doesn't cascade into `command not found` noise.

### CI Commands

| Command        | Purpose                        | AU Compliance Note                                    |
|----------------|--------------------------------|-------------------------------------------------------|
| `phplint`      | PHP syntax errors              | PHP 8.2 – 8.4 compatibility                           |
| `codechecker`  | Moodle Coding Standards        | 4-space indent; new line before `{` on classes/fns    |
| `phpdoc`       | PHPDoc checker                 | Requires `@package`, `@copyright`, `@license`         |
| `validate`     | Plugin structure               | Checks `version.php` and Frankenstyle component name  |
| `savepoints`   | Upgrade step validation        | `db/upgrade.php` version increments                   |
| `mustache`     | Mustache template lint         | No hardcoded text; use `{{#str}}` tags                |
| `grunt`        | Compile/lint JS & CSS          | SCSS lint; AMD modules in `amd/src/`                  |
| `phpunit`      | Back-end unit tests            | Extends `advanced_testcase`; `$this->resetAfterTest()`|
| `behat`        | Acceptance tests               | Use DD/MM/YYYY in all scenarios; `--auto-rerun 3`     |

### CI Failure Diagnosis

If **every** matrix job fails identically within a few seconds (before the
install step), suspect infrastructure — not code. Check for a retired runner
image, an org Actions policy, or a GitHub billing/spending limit. Diagnose
before pushing workflow edits; a blind runner-image bump wastes a cycle.

### Optimisation

- Remove unused steps (e.g. skip Mobile App testing for desktop-only plugins).
- Use Behat tags (`--tags="@local_myplugin"`) to focus tests during development.
- Use `--auto-rerun 3` in Behat to absorb flaky failures.

### `$plugin->supported` and `main`

Don't add `$plugin->supported` if the matrix also tests `main`. An upper bound
fails `validate` on `main` jobs (the dev branch reports a higher version than
the cap). Use the `requires` floor + README compatibility strapline instead.

---

## 2. Australian Locale & Coding Style

### Language & Spelling

Use AU/UK English exclusively in `lang/en/` files.

| Forbidden  | Required   |
|------------|------------|
| Customize  | Customise  |
| Organize   | Organise   |
| Color      | Colour     |
| Behavior   | Behaviour  |
| Enrollment | Enrolment  |

### Dates & Currency

- Render all user-facing dates via `userdate()` — defaults to DD/MM/YYYY.
- Currency defaults to AUD with 2-decimal precision.
- For locale-formatted decimal input in forms, parse via `unformat_float()`.
- When offering a currency picker, source it from
  `\core_payment\helper::get_supported_currencies()` so it only lists
  currencies a configured gateway will accept.

### Privacy — Australian Privacy Principles (APP)

Implement `privacy/classes/provider.php` using the Moodle Privacy API to
comply with the APP. Plugins that store no personal data of their own
(themes, presentation blocks, façades over another plugin's data) should
implement `\core_privacy\local\metadata\null_provider` rather than skipping.

See §4 Security Defaults for the `MOODLE_INTERNAL` rule.

---

## 3. Hard-Won Rules (Real CI Failures)

### 3.1 Language Files: Strict Ordering, No Section Comments

`phpcs` runs as `moodle-plugin-ci phpcs --max-warnings 0` — warnings fail
the build. The `moodle.Files.LangFilesOrdering` sniff requires:

- Every `$string['key']` in **ascending byte order** (`strcmp` / `LC_ALL=C sort`
  — case-sensitive; `:` < `A` < `_` < `a`).
- **No comments between strings.** The `// Navigation.`-style dividers are
  flagged as `UnexpectedComment`. Only the license header comment is allowed.

Add strings anywhere, then re-sort the whole file. Verify with:

```bash
grep -oP "^\$string\['\K[^']+" lang/en/local_myplugin.php > /tmp/k
LC_ALL=C sort /tmp/k | diff - /tmp/k && echo "ORDER OK"
```

Lang files do **not** get a `defined('MOODLE_INTERNAL') || die();` guard —
`phpcs` flags it as unnecessary there.

### 3.2 `fullname()` Needs the Full Name Field Set

Never hand-pick `u.firstname, u.lastname` for a record passed to `fullname()` —
it raises an `E_USER_NOTICE` about missing phonetic/alternate fields. Use:

```php
$namefields = \core_user\fields::for_name()->get_sql('u', true)->selects;
$sql = "SELECT DISTINCT u.id{$namefields} FROM {user} u ...";
```

With `SELECT DISTINCT`, every `ORDER BY` column must appear in the select list
— PostgreSQL enforces this and CI will catch it.

### 3.3 Rewrite `@@PLUGINFILE@@` Before Formatting

Editor content saved via `file_postupdate_standard_editor()` stores embedded
images as `@@PLUGINFILE@@` placeholders. They will 404 on the rendered page
unless you rewrite before calling `format_text()`:

```php
format_text(
    file_rewrite_pluginfile_urls(
        $html, 'pluginfile.php',
        $context->id, 'local_myplugin', MY_FILEAREA, $record->id
    ),
    $format, ['context' => $context]
);
```

Every file area you serve must also be whitelisted in the plugin's
`local_myplugin_pluginfile()` callback, or it 404s even after rewriting.

### 3.4 AMD: Edit src, Rebuild build via Moodle's Grunt, Bump Version

`amd/src/*.js` is not what runs — Moodle loads `amd/build/*.min.js`. After
editing source, regenerate the minified bundle using **Moodle's own grunt
pipeline** (`grunt amd` from the moodleroot), not a standalone bundler — the
wrapper, source maps, and AMD shim it produces are what `mustache` and the
loader expect. If grunt is unavailable, produce a hand-minified build matching
the existing wrapper byte-for-byte. Then bump `version.php` and purge all
caches.

### 3.5 Bump `version.php` for Any Cached Asset Change

Templates, AMD, lang strings, DB schema, capabilities, and Mustache helpers
all require a higher `$plugin->version` to take effect on an existing install.
When parallel PRs are in flight, give each a **distinct** version number to
avoid merge collisions. For static JS/CSS loaded outside AMD, append
`?v=$plugin->version` to cache-bust browsers.

### 3.6 Optional File Areas Need an Explicit Clear-on-Disable

`file_prepare_draft_area()` always populates the draft, so a hidden filemanager
still round-trips its existing files. Delete the area on save when a toggle is
off:

```php
if (!empty($data->haspanorama)) {
    file_save_draft_area_files(...);
} else {
    get_file_storage()->delete_area_files(
        $context->id, 'local_myplugin', FILEAREA_PANORAMA, $record->id
    );
}
```

Use `advcheckbox` + `$mform->hideIf('panorama_image', 'haspanorama', 'notchecked')`
for the reveal.

### 3.7 Form Actions: Point to Explicit `index.php`, Not the Directory

A `moodleform` action of `new moodle_url('/local/myplugin/')` (bare directory)
forces the web server to resolve the directory index on submit — a different,
stricter-permission path than the page load. If the plugin directory isn't
traversable by the web server user, the page loads fine but **Submit returns a
bare 403**. Always target the explicit endpoint the plugin registers:

```php
$mform = new myplugin_form(new moodle_url('/local/myplugin/index.php'));
```

Same lesson, GET variant: don't shove filesystem-looking values into the query
string. nginx/WAF LFI rules (e.g. YunoHost defaults) reject those with a bare
403 before they reach Moodle. Handle the POST in-place; keep the admin page
URL clean.

### 3.8 Moodle 5.1+ `public/` Directory Layout

On Moodle 5.1+ the docroot is `<moodleroot>/public/`; plugins live at
`public/local/...`, `$CFG->dirroot` points at the `public/` dir, and
`config.php` may sit in `public/` (back-compat) or the project root. Account
for both layouts when reasoning about paths or bootstrap
(`require '../../config.php'`). Note: `moodle-plugin-ci` does not exercise the
`public/` split — passing CI says nothing about path resolution on 5.1+.

### 3.9 PHP 8.4: No Implicit-Nullable Parameters

PHP 8.4 deprecates `function foo(array $x = null)`. `phpunit --fail-on-warning`
fails on 8.4 while 8.2 / 8.3 pass silently. Mark every nullable parameter
explicitly:

```php
public function add_instance($course, ?array $fields = null) { ... }
```

Audit for `lcg_value`, deprecated `mysqli_*`, and removed curly-brace string
offsets at the same time.

### 3.10 Enrol-instance Forms: Use `get_default_enrol_roles()`

The default-role select on an enrol-instance edit form uses
`get_default_enrol_roles($context, $defaultroleid)` — the helper that
`enrol_fee` / `enrol_self` / `enrol_paypal` use. `extend_assignable_roles()`
does **not** exist on `enrol_plugin` and calling it fatals on form open.

### 3.11 Enrol Cost/Currency Live on `mdl_enrol`

The enrolment subsystem already owns `mdl_enrol.cost` and `mdl_enrol.currency`
(shared with `enrol_fee` / `enrol_paypal`). Add the fields to the
instance-edit form and write to those columns — **no schema change needed**.
Validate with `unformat_float()` and reject negatives.

### 3.12 Cross-plugin Dependencies: Pin to a Numeric Version

`$plugin->dependencies = ['local_educheckout' => 2026060200];` — don't use
`ANY_VERSION` once the suite is shipping together. CI dependency checkouts
(`actions/checkout` with `repository: verzog/moodle-foo`) should use an
**explicit `ref:`** (after a `master` → `main` rename, the GitHub redirect
silently keeps things working until it doesn't — be explicit).

### 3.13 Block Migration: `{block}.name` Unique Violation

Block plugin install registers a row in `{block}` for the new component
**before** `db/install.php` runs. If you `set_field('block', 'name',
'newname', ['name' => 'oldname'])` during migration, you hit the unique
`name` index and the install aborts. Detect the new-name row first and **drop
the leftover old row** in that case; only rename when the new row isn't there
yet.

### 3.14 N+1 Queries in Cron / Migration Loops

`get_record(...)` inside a per-row loop is the classic N+1 trap (e.g.
`sync_enrolments()` lazy-loading the enrol instance for every user-enrolment
row). Pre-load with a single `get_records(..., '', 'id, ...')` keyed by id
**before** the loop; `continue` past stale ids defensively. For bulk renames
across DB engines, use a single SQL `UPDATE ... SET col = REPLACE(col, 'a',
'b')` — `REPLACE()` is supported on MySQL/MariaDB and PostgreSQL.

### 3.15 Server-side Validation of JSON Payloads

External functions that accept JSON-encoded data (e.g. rubric scores) must
validate after `json_decode`: array shape, expected length, every value in
the allowed set. Throw `invalid_parameter_exception` before any DB write.
Don't trust the front-end to enforce structural constraints.

### 3.16 Stranded Commits After Merge

If a PR is merged and then more commits are pushed to the (now-deleted) head
branch, those commits are **stranded** — no open PR points at them and `main`
never receives them. Always confirm the PR diff contains the intended change
**before** merging, or open a follow-up PR for additional commits.

### 3.17 Revert PRs: Verify the Root Cause First

Don't immediately revert a merged PR because it superficially resembles a
suspected regression. Verify the cause first; an unnecessary revert + re-land
cycle (PR → revert → re-land) wastes a release version and pollutes history.

### 3.18 CI Badge: Point at the Fork

A README badge hardcoded to `moodlehq/moodle-…` renders **upstream's** status
on a fork (typically red). Repoint to `verzog/<repo>`, pin to `main`.

### 3.19 Marketplace Hygiene

Before first directory submission:

- `LICENSE` file at plugin root (GPLv3 stub) — Marketplace blocker.
- `$plugin->maturity = MATURITY_BETA;` on the first submission of a suite.
  Promote to `MATURITY_STABLE` only after a clean fresh-install + upgrade
  pass in production.
- `TERMS.md` if the plugin will be sold via the Marketplace — adapt the
  template from `moodle-tool_installfromgithub`, scope §8 (Acceptable use)
  and §9 (Privacy) to the plugin.
- Document install-time migration behaviour (`db/install.php`) in the README
  so reviewers understand what touches the DB on first install.

### 3.20 Modals: Use `core/modal`, Not Editor `windowManager`

For TinyMCE plugins and the like, build dialogs on `core/modal` rather than
fighting TinyMCE's `windowManager` for z-index. Filepicker overlay z-index
hacks (`yui3-widget-mask`, `moodle-dialogue-lightbox`, `!important`) are a
smell — switch to `core/modal` instead. Scope any `file_picker_callback` to
your own dialogs.

### 3.21 Payment Flow Hardening

For `core_payment`-driven flows: enforce idempotency on the callback (same
`paymentid` must not double-enrol), guard against logged-out / mismatched
accounts, and write structured logs for every state transition. Server-side
capacity / sold-out checks must live in **every** add-to-cart entry point,
not just the UI button.

---

## 4. Security Defaults

- **`MOODLE_INTERNAL` guard:** Add `defined('MOODLE_INTERNAL') || die();` to
  every PHP file **except** lang files and `lib.php` files that contain only
  function declarations (phpcs flags the guard as unnecessary in those cases).
- **SQL:** Always use Moodle's query placeholders (`?` or named params) — never
  interpolate user input into SQL strings.
- **Capabilities:** check `require_capability()` at the entry point of every
  external function and admin action. Don't rely on UI gating alone.
- **JS:** Set user/content text with `textContent` /
  `document.createTextNode`, never `innerHTML`.
- **Inline style/script from settings:** Strip the closing tag
  (`str_ireplace('</style', ...)`) to prevent breakout.
- **Scope admin custom CSS/JS** to the plugin's own pages unless the user
  explicitly requests site-wide — a bad rule shouldn't break all of Moodle.
- **Validate at boundaries** with the correct `PARAM_*` type; trust internal code.
- **Never commit secrets.** If a user pastes a stack trace or dump containing
  live cookies, tokens, or credentials, flag it and recommend rotation — do
  not echo decoded values back.

---

## 5. Plugin Structure & Boilerplate

- **Standard PHP header** on every file:
  ```php
  // This file is part of Moodle - https://moodle.org/
  ```
  followed by a `@package`, `@copyright`, `@license` docblock. Preserve
  upstream `@copyright` lines (e.g. `2010 Petr Skoda` for anything derived
  from `enrol_manual`) — required by GPLv3.
- **`README.md`** follows the `moodle-tool_pluginskel` template: short
  description, "Installing via uploaded ZIP file", "Installing manually",
  Requirements, License (GPLv3 block matching file headers).
- **`LICENSE`** at plugin root (GPLv3 stub) — required for Marketplace.
- **Third-party libraries:** declare in `thirdpartylibs.xml`, keep the upstream
  `LICENSE` in-tree, and attribute in README (name, version, license, copyright
  holder, upstream URL, "unmodified").
- **Privacy API:** implement `privacy/classes/provider.php` (or `null_provider`
  if no personal data is stored) for APP compliance.

---

## 6. Local Development

Install the CI toolchain:

```bash
php composer.phar create-project moodlehq/moodle-plugin-ci ../moodle-plugin-ci ^4
```

Run CodeSniffer locally:

```bash
# Check violations
../moodle-plugin-ci/vendor/bin/phpcs ./index.php

# Auto-fix formatting (tabs → spaces, etc.)
../moodle-plugin-ci/vendor/bin/phpcbf ./index.php
```

---

## 7. Pre-PR Checklist

- [ ] `php -l` clean on every changed `.php` file (run under PHP 8.4).
- [ ] No implicit-nullable params; `?type` on every nullable.
- [ ] Lang file re-sorted; no interspersed comments.
- [ ] AU/UK English throughout `lang/en/` (check `-ise`, `-our`, `Enrolment`).
- [ ] `@@PLUGINFILE@@` rewritten anywhere editor content is rendered.
- [ ] New file areas whitelisted in `*_pluginfile()`.
- [ ] AMD `build/` regenerated via Moodle's grunt if `src/` changed.
- [ ] `version.php` bumped (distinct number if parallel PRs).
- [ ] `$plugin->dependencies` pinned to a numeric version, not `ANY_VERSION`.
- [ ] User-facing strings in `lang/en/` — no hardcoded text anywhere.
- [ ] Dates rendered via `userdate()`; currency defaults to AUD.
- [ ] Cost/currency forms validate with `unformat_float()`; currency picker
      sourced from `\core_payment\helper::get_supported_currencies()`.
- [ ] No `innerHTML` with untrusted/user data.
- [ ] Capability check at the top of every external function and admin entry.
- [ ] Privacy API implemented (or `null_provider`).
- [ ] `LICENSE` present at plugin root (and `TERMS.md` if Marketplace-bound).
- [ ] Workflow file is GitHub Actions only — no Travis CI references.
- [ ] Runner is `ubuntu-24.04`; `TZ: Australia/Sydney`; `max_input_vars=5000`.
- [ ] Service images pinned; install step retried; later steps gated on it.
- [ ] CI matrix: PHP 8.2/8.3/8.4 × pgsql/mysqli × Moodle 5.0/5.1/5.2/main.
- [ ] `$plugin->supported` absent if `main` is in the matrix.
- [ ] Form actions point to explicit `index.php`, not a bare directory URL.
- [ ] Block migrations check for the new-component `{block}` row before
      renaming the old one.
- [ ] No N+1 loops; bulk renames use a single `UPDATE … REPLACE()`.
- [ ] PR diff confirmed to contain the intended change before merging.

---

## 8. Workflow

- One concern per PR; branch off the latest `main`.
- Default branch is `main`. After a `master` → `main` rename, update the
  workflow's push trigger and any cross-repo `actions/checkout` `ref:` values
  — the GitHub redirect handles refs but not file contents.
- If a branch falls behind merged work, rebase onto `origin/main` and
  force-with-lease.
- Open PRs **ready for review** (not draft); keep PR bodies to a summary +
  test plan.
- CI runs: `phplint`, `phpcs --max-warnings 0`, `phpdoc`, `validate`,
  `savepoints`, `phpunit --fail-on-warning`, `behat` across a PHP × Moodle-
  branch matrix. **Warnings are failures.**
- Don't merge until the fix commits are on the branch. Commits pushed after a
  merge are stranded with no open PR (§3.16).
- Don't revert a merged PR without verifying root cause (§3.17).

---

## 9. Deployment & Live-Site Diagnosis

CI green and PR merged does **not** mean the change is live. Most "still broken
after the fix" reports are deployment issues, not code.

### Merge ≠ Deploy

`main` on GitHub never touches a running site. After merging: redeploy the
files, then run the Moodle upgrade (*Site admin → Notifications* or
`php admin/cli/upgrade.php`). `version.php` must be bumped (§3.5) or no
upgrade/cache-purge fires.

### Verify the Bytes on Disk

Grep a known marker from the new code in the deployed file before trusting it:

```bash
grep -n "MARKER_FROM_THE_FIX" /path/to/moodle/local/myplugin/index.php
```

Don't assume a deploy worked — confirm the file changed.

### Deploy Ownership

Deploying as a non-web user (rsync/git pull as a deploy account) sets the
wrong group, so the web server can't traverse the plugin directory → 403 on
directory-resolved requests. Match owner:group to the rest of the Moodle tree:

```bash
rsync -a --chown=appuser:www-data ...
```

Re-apply after every deploy if needed.

### Get Server Evidence Before Theorising

For "works in CI / fails on server", read the per-vhost nginx logs — not the
global ones:

```bash
sudo tail -n 50 /var/log/nginx/<vhost>-error.log
sudo grep ' 403 ' /var/log/nginx/<vhost>-access.log | tail
```

The access log line shows exact method and URL. `(13: Permission denied)` means
filesystem permissions; a WAF or nginx `deny` logs differently. Get this
first — hours were lost theorising (WAF, redirects, framework layout) before
the log named the actual cause.
