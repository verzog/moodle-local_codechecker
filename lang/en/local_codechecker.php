<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Language strings
 *
 * @package    local_codechecker
 * @category   string
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['audit'] = 'Run audit checks (security, code quality and performance)';
$string['audit_help'] = 'Runs the audit checks on top of the Moodle coding style and shows the audit checklist (S0 to S21, Q1 to Q8 and P1 to P4). Problems a tool can detect are reported in the file results, with the check ID at the start of each message. Plugin-level checks run when you check a plugin\'s root folder (the folder with version.php). Everything else is listed for manual review. Most audit findings are warnings, so keep "Include warnings" ticked. Leave this option off to get the same results as moodle-plugin-ci phpcs in CI.';
$string['auditcheck_P1'] = 'No per-iteration database queries (direct $DB calls, or helpers that query on every call) in loops over data that grows with site use, such as users, courses, enrolments or submissions; batch them. Loops over small fixed sets or a single page are bounded.';
$string['auditcheck_P2'] = 'db/install.xml covers each demonstrated selective query predicate (WHERE, JOIN or ORDER BY) on growing tables with a key or index. Primary, unique and foreign keys and the leading columns of composite indexes count as coverage.';
$string['auditcheck_P3'] = 'Request paths (pages, AJAX/external functions, and callbacks, renderers and forms during page requests) do not run work that grows with site data or blocks on external services; such work belongs in \core\task\adhoc_task or scheduled_task.';
$string['auditcheck_P4'] = 'No sleep() / usleep() / polling on the request path.';
$string['auditcheck_Q1'] = 'Moodle namespaces + PSR-4 file naming.';
$string['auditcheck_Q2'] = 'PHPDoc on all classes + public methods (@param, @return, @throws).';
$string['auditcheck_Q3'] = 'No runtime writes or replacements of core globals ($CFG, $USER, $SESSION, $COURSE, $PAGE, $OUTPUT, $DB and similar) that change later behaviour. Expected patterns are allowed: $PAGE setup for the plugin\'s own page, plugin-namespaced $SESSION keys, and core APIs for settings, preferences, login context and task/CLI user switching.';
$string['auditcheck_Q4'] = 'PHPUnit test-file presence under tests/ (not execution or coverage). Missing tests are a Low recommendation, not a universal plugin-submission requirement.';
$string['auditcheck_Q5'] = 'Optional db/uninstall.php hook presence only; Moodle removes plugin tables and capabilities without this hook. Additional clean-up needs manual review.';
$string['auditcheck_Q6'] = 'Nonempty root README presence only; documentation is recommended with no minimum line count, and quality is not verified.';
$string['auditcheck_Q7'] = 'No genuinely unused code once framework callbacks, registrations and dynamic references are credited; no substantive commented-out logic, unreachable or placeholder logic, or debug artifacts such as var_dump()/print_r() output. debugging() and mtrace() are legitimate.';
$string['auditcheck_Q8'] = 'No deprecated core APIs, such as add_to_log(), print_error(), get_context_instance(), get_referer(), cron_setup_user() or lib.php callbacks migrated to hooks. Recommend replacements that fit the plugin\'s declared Moodle compatibility; deprecation and removal versions require a cited Moodle source.';
$string['auditcheck_S0'] = 'Obvious security-critical logic error not covered by any other check: authentication bypass, authorisation bypass, or sensitive-data exposure caused by an incorrect return value, wrong condition, or broken control flow in a security-sensitive function.';
$string['auditcheck_S1'] = 'Code a direct web request can run (entry and standalone scripts, lib.php callbacks, router routes) logs in before its first protected effect: require_login()/require_course_login(), with the resource\'s own course/cm where participation governs access. Public and sessionless endpoints serve only public data or verify a request credential first.';
$string['auditcheck_S10'] = 'No undocumented external transmission. External data cleaned with clean_param() / PARAM_* before use.';
$string['auditcheck_S11'] = 'No secrets/PII in logs or user-facing errors.';
$string['auditcheck_S12'] = 'Significant actions fire \core\event\... events. No legacy add_to_log().';
$string['auditcheck_S13'] = 'Bulk-destructive operations have confirmation. Capabilities carry RISK_DATALOSS.';
$string['auditcheck_S14'] = 'db/access.php capabilities carry correct risk bitmasks.';
$string['auditcheck_S15'] = 'Untrusted-user files served via pluginfile.php; binary files force-downloaded.';
$string['auditcheck_S16'] = 'Sesskey not in plugin-controlled logs or events, external-domain URLs, or Referer-unsafe GETs.';
$string['auditcheck_S17'] = 'Every plugin requires classes/privacy/provider.php, including a null_provider when appropriate. A pass confirms file presence only; metadata/request interfaces, declarations, export/delete behaviour, preferences, external transfers and subsystem storage require manual review.';
$string['auditcheck_S18'] = 'PHP files with top-level side effects (executable statements or legacy dependency includes) establish an early config.php bootstrap or guard against direct web access via defined(\'MOODLE_INTERNAL\') || die(); (or an equivalent AJAX/CLI guard). Side-effect-free class/interface/trait/enum files are exempt; namespace, use, const and declare statements alone do not remove that exemption, and a namespace or classes/ location alone does not grant it.';
$string['auditcheck_S19'] = 'External functions (\core_external\external_api, legacy external_api, and methods registered in db/services.php) call validate_parameters() against their matching declaration and use the returned values, call validate_context() on a context derived from validated input before privileged work, apply any independently required capability checks, and declare exactly the parameters they read and the fields they return.';
$string['auditcheck_S2'] = 'has_capability() / require_capability() used with the correct context. Group membership checked via the Groups API where relevant.';
$string['auditcheck_S20'] = 'Declared or recognised third-party libraries require manual review of entry points, declarations and licensing. Supported source receives S21 review; Moodle rules and static tools are excluded. Missing/invalid declarations are explicit warnings. Medium is service policy.';
$string['auditcheck_S21'] = 'Third-party source: concrete injection/XSS, unsafe execution or deserialisation, path traversal, SSRF, authentication/authorisation bypass, and sensitive-data exposure. Require code evidence; do not demand Moodle APIs or report style, performance or speculative hardening.';
$string['auditcheck_S3'] = 'All input via optional_param() / required_param() with strict PARAM_* types. PARAM_RAW only if justified. $_GET/$_POST/$_REQUEST/$_COOKIE/$_SESSION never accessed directly (use $SESSION and the Moodle cookie APIs).';
$string['auditcheck_S4'] = 'Forms use moodleform with setType() for every field.';
$string['auditcheck_S5'] = 'Output uses context-appropriate encoding. Stored-XSS claims require a verified raw-input to unsafe-persistence to executable-sink path; uncertain provenance is reported as a complete Medium finding with a concise uncertainty note, while unsafe attribute, URL and JavaScript contexts remain reportable.';
$string['auditcheck_S6'] = 'No hardcoded external script or stylesheet loads, whether by <script>/<link> tag or JavaScript injection.';
$string['auditcheck_S7'] = '$DB queries bind every value through placeholders. SQL text may combine {table} names, get_in_or_equal() and $DB->sql_*() fragments, and identifiers chosen from fixed values or an explicit allow-list.';
$string['auditcheck_S8'] = 'Session-authenticated state-changing requests use POST and an effective sesskey check before the change: require_sesskey(), a confirm_sesskey() whose failure stops the action, a Form API submission, or core AJAX dispatch of a login-required external function.';
$string['auditcheck_S9'] = 'No exec/system/passthru/shell_exec/popen/proc_open. If unavoidable, escapeshellarg() + escapeshellcmd().';
$string['auditchecklist'] = 'Audit checklist';
$string['auditcolumn_check'] = 'Check';
$string['auditcolumn_id'] = 'ID';
$string['auditcolumn_method'] = 'How it is checked';
$string['auditcolumn_result'] = 'Result';
$string['auditgroup_performance'] = 'Performance';
$string['auditgroup_quality'] = 'Code quality and architecture';
$string['auditgroup_security'] = 'Security';
$string['auditmsg_callbackhook'] = '[Q8] The {$a->function}() callback has been replaced by the {$a->hook} hook. Use the hook if every Moodle version the plugin supports has it.';
$string['auditmsg_capdataloss'] = '[S13] Capability {$a} looks like a destructive or bulk action but its riskbitmask has no RISK_DATALOSS.';
$string['auditmsg_capdefaultgrant'] = '[S14] Capability {$a->cap} carries {$a->risks} but is allowed for archetypes by default. Remove the default, or justify it in a comment in its definition.';
$string['auditmsg_capnorisk'] = '[S14] Write capability {$a} has no riskbitmask. Add the risks that apply, or confirm that none do.';
$string['auditmsg_debugoutput'] = '[Q7] Debug output {$a}() left in the code. Remove it, or use debugging() or mtrace().';
$string['auditmsg_deprecated'] = '[{$a->id}] {$a->function}() is a deprecated core API. Use {$a->replacement} instead.';
$string['auditmsg_externalasset_inject'] = '[S6] JavaScript creates a script or link element. Bundle the code as an AMD module instead of loading it at run time.';
$string['auditmsg_externalasset_requires'] = '[S6] External URL passed to $PAGE->requires->{$a}(). Bundle the file with the plugin instead of loading it from another site.';
$string['auditmsg_externalasset_tag'] = '[S6] Hardcoded external script or stylesheet. Bundle it with the plugin (as an AMD module, or in styles.css) instead of loading it from another site.';
$string['auditmsg_formsettype'] = '[S4] Form element \'{$a}\' has no setType(). Every text, textarea, hidden and password field needs a strict PARAM_* type.';
$string['auditmsg_globalproperty'] = '[Q3] Writes a property of {$a}, changing a core global for the rest of the request. Use the core API instead (for example set_config(), set_user_preference() or the $PAGE methods).';
$string['auditmsg_globalreplace'] = '[Q3] Replaces the core global {$a}. Use the core API instead (for example \core\session\manager::set_user() or \core\cron::setup_user() to switch users).';
$string['auditmsg_paramraw'] = '[S3] PARAM_RAW used with {$a}(). Use a stricter PARAM_* type, or add a comment explaining why raw input is needed.';
$string['auditmsg_queryinloop'] = '[P1] {$a} runs a database query on every loop iteration. If the loop grows with site data, load the records in one query before the loop.';
$string['auditmsg_secretlogged'] = '[S11] {$a} looks like a secret and is passed to a log, debugging output or error message. Do not log or show secrets or personal data.';
$string['auditmsg_sesskey'] = '[S8] This page reads request parameters and writes to the database, but has no require_sesskey(), confirm_sesskey() or Form API submission. Check the sesskey before the change.';
$string['auditmsg_sesskeylogged'] = '[S16] sesskey() is passed to a log, debugging output or error message. Keep the sesskey out of logs, events and error messages.';
$string['auditmsg_shell'] = '[S9] Shell execution with {$a}(). Avoid it; if it is unavoidable, pass every argument through escapeshellarg() and the command through escapeshellcmd().';
$string['auditmsg_sleep'] = '[P4] {$a}() on the request path blocks the page. Move waiting or polling work to a \core\task\adhoc_task or scheduled_task.';
$string['auditmsg_sqlinput'] = '[S7] SQL is built from request input ({$a}). Bind every value through a placeholder (? or :name) and pass it in the params array.';
$string['auditmsg_superglobal_cookie'] = '[S3] Direct access to {$a}. Use the Moodle cookie APIs instead.';
$string['auditmsg_superglobal_param'] = '[S3] Direct access to {$a}. Use optional_param() or required_param() with a strict PARAM_* type instead.';
$string['auditmsg_superglobal_session'] = '[S3] Direct access to {$a}. Use $SESSION instead.';
$string['auditnowarnings'] = 'Warnings are not included, so most audit findings are hidden. Tick "Include warnings" to see them.';
$string['auditplugin_privacy_missing'] = 'classes/privacy/provider.php is missing. Every plugin needs a privacy provider (a null_provider if it stores no personal data).';
$string['auditplugin_privacy_present'] = 'classes/privacy/provider.php is present. Its metadata and request interfaces, declarations, export and delete behaviour, preferences, external transfers and subsystem storage need a manual review.';
$string['auditplugin_readme_empty'] = 'The README ({$a}) is empty.';
$string['auditplugin_readme_missing'] = 'No README in the plugin root. A README is recommended (there is no minimum length).';
$string['auditplugin_readme_present'] = 'README present ({$a}). Its quality is not checked.';
$string['auditplugin_tests_missing'] = 'No PHPUnit tests (*_test.php) under tests/. Tests are recommended, not required.';
$string['auditplugin_tests_present'] = 'PHPUnit tests present under tests/. They are not run here, and coverage is not checked.';
$string['auditplugin_thirdparty_badlocation'] = 'thirdpartylibs.xml declares {$a->name} at "{$a->location}", which does not exist or is outside the plugin.';
$string['auditplugin_thirdparty_declared'] = 'Third-party library {$a->name} {$a->version} ({$a->license}) at {$a->location}: review its entry points, declaration and licence manually, and its source under S21.';
$string['auditplugin_thirdparty_incomplete'] = 'The thirdpartylibs.xml entry for {$a->location} is missing: {$a->fields}.';
$string['auditplugin_thirdparty_invalid'] = 'thirdpartylibs.xml is not valid XML with a libraries root element.';
$string['auditplugin_thirdparty_none'] = 'No third-party libraries declared or found.';
$string['auditplugin_thirdparty_undeclared'] = 'The {$a}/ folder looks like bundled third-party code but is not declared in thirdpartylibs.xml.';
$string['auditplugin_uninstall_absent'] = 'No db/uninstall.php. This hook is optional: Moodle removes the plugin\'s tables and capabilities without it. Check manually whether any other data needs cleaning up.';
$string['auditplugin_uninstall_present'] = 'db/uninstall.php is present. Its extra clean-up needs a manual review.';
$string['auditresult_findings'] = '{$a} finding(s) in the file results';
$string['auditresult_manual'] = 'Needs manual review';
$string['auditresult_none'] = 'No findings';
$string['auditresult_noplugin'] = 'Not run: check a plugin\'s root folder (the folder with version.php) to run this check.';
$string['auditresult_partial'] = 'The automated check finds the clear cases only; review the rest manually.';
$string['audittype_auto'] = 'Automatic';
$string['audittype_manual'] = 'Manual review';
$string['audittype_partial'] = 'Partly automatic';
$string['audittype_plugin'] = 'Plugin files';
$string['audittype_standard'] = 'Moodle coding style';
$string['check'] = 'Check code';
$string['clihelp'] = 'Check some PHP code against the Moodle coding guidelines.
Options:
  --interactive, -i Stops after every file allowing to continue with the next
                    file or to repeat the check against the same one.
  --exclude, -e     Exclude files with a comma separated list of substr matching paths,
                    Asterisks are allowed as wildcards at any place.
  --skiplicence, -l Skip the Moodle GPL boilerplate and licence/copyright tag checks,
                    for proprietary or third-party code with its own headers.
  --audit, -a       Also run the security, code quality and performance audit checks,
                    and print the audit checklist.
  --help, -h        Shows this information
Example:
  php run.php local/codechecker';
$string['error_find'] = 'Folder search failed';
$string['error_processing'] = 'Problem during processing; checking has been aborted. The error message was: {$a}';
$string['example_file'] = '{$a} - a single file';
$string['example_module'] = '{$a} - an activity module';
$string['example_plugin'] = '{$a} - a plugin';
$string['example_subplugin'] = '{$a->path} - a subplugin (here the {$a->component} TinyMCE editor plugin)';
$string['exclude'] = 'Exclude';
$string['filesfound'] = 'Files found: {$a}';
$string['filesummary'] = '{$a->path} - {$a->count}';
$string['includewarnings'] = 'Include warnings';
$string['info'] = '<p>Checks code against some aspects of the {$a->link}.</p>
<p>Enter one or more paths (one per line) relative to the Moodle code root.
The component name maps to its directory, for example:</p>
{$a->path}
<p>You can enter either a specific PHP file, or a folder to check all the files it contains.
Multiple entries are supported (files or folders), one per line.</p>
<p>To exclude files, a comma separated list of substr matching paths can be used, for example: {$a->excludeexample}.
Asterisks are allowed as wildcards at any place.</p>
<p>When checking proprietary or third-party code that intentionally carries its own file headers instead of the
Moodle GPL ones, tick "Skip Moodle licence and boilerplate checks" so only the genuine coding-style problems are
reported. This skips only the GPL boilerplate check and the @copyright / @license tag checks. The comment lines of
your own header are still checked like any other comment, as they are in CI (for example, the last line must end
with a full stop).</p>';
$string['invalidpath'] = 'Invalid path {$a}';
$string['moodlecodingguidelines'] = 'Moodle coding guidelines';
$string['numerrorswarnings'] = '{$a->errors} error(s) and {$a->warnings} warning(s)';
$string['other_crlf'] = 'Windows (CRLF) line ending instead of just LF (reporting only first occurrence)';
$string['other_eol'] = 'Whitespace at end of line';
$string['other_extralfs'] = 'Extra blank line(s) at end of file (use exactly one)';
$string['other_missinglf'] = 'Missing LF at end of file (use exactly one)';
$string['other_ratherlong'] = 'Line longer than recommended 132 characters';
$string['other_tab'] = 'Tab character not permitted';
$string['other_toolong'] = 'Line longer than maximum 180 characters';
$string['path'] = 'Path(s) to check';
$string['pluginname'] = 'Code checker';
$string['privacy:metadata'] = 'The Code checker plugin does not store any personal data.';
$string['recheckfile'] = 'Re-check just this file';
$string['showstandard'] = 'Display phpcs standard associated with a problem';
$string['skiplicencechecks'] = 'Skip Moodle licence and boilerplate checks';
$string['success'] = 'Well done!';
$string['summary'] = 'Total: {$a}';
