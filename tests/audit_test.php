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

namespace local_codechecker;

use local_codechecker\audit\asset_scanner;
use local_codechecker\audit\checklist;
use local_codechecker\audit\plugin_checks;

/**
 * Tests for the audit checks: the MoodleAudit sniffs, plugin-level checks and the checklist.
 *
 * The fixtures are written to a request directory at run time, so the code checker's own
 * tests/ folder holds no deliberately bad code.
 *
 * @package    local_codechecker
 * @category   test
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_codechecker\runner::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_codechecker\audit\asset_scanner::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_codechecker\audit\checklist::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_codechecker\audit\plugin_checks::class)]
#[\PHPUnit\Framework\Attributes\CoversFunction('local_codechecker_audit')]
final class audit_test extends \advanced_testcase {
    /**
     * Load the plugin library and the bundled PHP_CodeSniffer.
     *
     * Under PHPUnit, moodle-cs replaces the global $CFG with a minimal one when it looks up the
     * component of a checked file (see MoodleUtil::calculateAllComponents()), which breaks the rest
     * of the test. Giving it a mocked component list, its own test hook, stops that.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/local/codechecker/locallib.php');
        autoload_tools();
        if (!class_exists(\MoodleHQ\MoodleCS\moodle\Util\MoodleUtil::class, false)) {
            require_once($CFG->dirroot . '/local/codechecker/vendor/moodlehq/moodle-cs/moodle/Util/MoodleUtil.php');
        }
        \MoodleHQ\MoodleCS\moodle\Util\MoodleUtil::setMockedComponentMappings(['local_demo' => '/nonexistent/local/demo']);
        $this->resetAfterTest();
    }

    /**
     * Remove the mocked moodle-cs component list.
     */
    protected function tearDown(): void {
        \MoodleHQ\MoodleCS\moodle\Util\MoodleUtil::setMockedComponentMappings([]);
        parent::tearDown();
    }

    /**
     * Write files into a new folder.
     *
     * @param array $files relative path => contents.
     * @return string the folder.
     */
    protected function write_files(array $files): string {
        $root = make_request_directory() . '/local/demo';
        foreach ($files as $path => $contents) {
            if (!is_dir(dirname($root . '/' . $path))) {
                mkdir(dirname($root . '/' . $path), 0777, true);
            }
            file_put_contents($root . '/' . $path, $contents);
        }
        return $root;
    }

    /**
     * Run the checker on a path and return the report.
     *
     * @param string $path full path to check.
     * @param bool $audit whether to run the audit checks.
     * @return \SimpleXMLElement the report.
     */
    protected function run_checker(string $path, bool $audit = true): \SimpleXMLElement {
        $runner = new runner();
        $reportfile = make_request_directory() . '/phpcs.xml';
        $runner->set_reportfile($reportfile);
        $runner->set_includewarnings(true);
        $runner->set_audit($audit);
        $runner->set_files([$path]);
        $runner->run();
        return simplexml_load_file($reportfile);
    }

    /**
     * Get the checklist IDs of the audit findings in a report, sorted.
     *
     * @param \SimpleXMLElement $xml the report.
     * @return string[] one ID per finding.
     */
    protected function audit_ids(\SimpleXMLElement $xml): array {
        $ids = [];
        foreach ($xml->xpath('file/error|file/warning') as $problem) {
            if (strpos((string) $problem['source'], checklist::AUDIT_SOURCE) === 0) {
                $ids[] = checklist::check_for_source((string) $problem['source']);
            }
        }
        sort($ids);
        return $ids;
    }

    /**
     * Data provider for test_audit_sniffs().
     *
     * @return array fixture file, its code (without the opening tag), and the expected check IDs.
     */
    public static function audit_sniffs_provider(): array {
        $access = "\$capabilities = [\n" .
            "    'local/demo:view' => ['captype' => 'read', 'contextlevel' => CONTEXT_SYSTEM],\n" .
            "    'local/demo:edit' => ['captype' => 'write', 'contextlevel' => CONTEXT_SYSTEM],\n" .
            "    'local/demo:config' => [\n" .
            "        'riskbitmask' => RISK_CONFIG | RISK_XSS,\n" .
            "        'captype' => 'write',\n" .
            "        'contextlevel' => CONTEXT_SYSTEM,\n" .
            "        'archetypes' => ['manager' => CAP_ALLOW],\n" .
            "    ],\n" .
            "    'local/demo:justified' => [\n" .
            "        // Managers need this by default.\n" .
            "        'riskbitmask' => RISK_CONFIG,\n" .
            "        'captype' => 'write',\n" .
            "        'contextlevel' => CONTEXT_SYSTEM,\n" .
            "        'archetypes' => ['manager' => CAP_ALLOW],\n" .
            "    ],\n" .
            "    'local/demo:deleteall' => [\n" .
            "        'riskbitmask' => RISK_CONFIG,\n" .
            "        'captype' => 'write',\n" .
            "        'contextlevel' => CONTEXT_SYSTEM,\n" .
            "    ],\n" .
            "];\n";
        $form = "class demo_form extends \\moodleform {\n" .
            "    public function definition() {\n" .
            "        \$mform = \$this->_form;\n" .
            "        \$mform->addElement('text', 'untyped', 'x');\n" .
            "        \$mform->addElement('text', 'typed', 'x');\n" .
            "        \$mform->setType('typed', PARAM_TEXT);\n" .
            "        \$mform->addElement('checkbox', 'box', 'x');\n" .
            "    }\n" .
            "}\n";
        return [
            'S3 superglobals' => ['page.php', "\$x = \$_GET['x'];\n\$_SESSION['y'] = \$_SERVER['z'];\n", ['S3', 'S3']],
            'S3 PARAM_RAW' => [
                'page.php',
                "\$a = optional_param('a', '', PARAM_RAW);\n// Raw: justified.\n\$b = optional_param('b', '', PARAM_RAW);\n" .
                    "\$c = optional_param('c', '', PARAM_RAW); // Raw: justified.\n\$d = optional_param('d', 0, PARAM_INT);\n",
                ['S3'],
            ],
            'S4 form setType' => ['classes/form/demo.php', $form, ['S4']],
            'S6 external assets' => [
                'page.php',
                "echo '<script src=\"https://cdn.example.com/x.js\"></script>';\n" .
                    "echo '<link rel=\"canonical\" href=\"https://example.com/\">';\n" .
                    "\$PAGE->requires->js(new moodle_url('https://cdn.example.com/y.js'));\n" .
                    "\$PAGE->requires->js('/local/demo/ok.js');\n",
                ['S6', 'S6'],
            ],
            'S7 SQL input' => [
                'page.php',
                "\$id = optional_param('id', 0, PARAM_INT);\n" .
                    "\$DB->get_records_sql(\"SELECT * FROM {user} WHERE id = \$id\");\n" .
                    "\$DB->get_records_sql('SELECT * FROM {user} WHERE id = ?', [\$id]);\n" .
                    "\$DB->get_records_select('user', 'id = ' . optional_param('z', 0, PARAM_INT));\n",
                ['S7', 'S7'],
            ],
            'S8 no sesskey' => [
                'page.php',
                "require_once(__DIR__ . '/../../config.php');\n\$id = required_param('id', PARAM_INT);\n" .
                    "\$DB->delete_records('user', ['id' => \$id]);\n",
                ['S8'],
            ],
            'S8 sesskey checked' => [
                'page.php',
                "require_once(__DIR__ . '/../../config.php');\n\$id = required_param('id', PARAM_INT);\nrequire_sesskey();\n" .
                    "\$DB->delete_records('user', ['id' => \$id]);\n",
                [],
            ],
            'S9 shell' => ['page.php', "exec('ls');\n\$runner->exec('ls');\n", ['S9']],
            'S11 and S16 logs' => [
                'page.php',
                "debugging('x ' . \$user->password);\nmtrace(\"t {\$token}\");\ndebugging('k ' . sesskey());\n" .
                    "mtrace('count ' . \$count);\n",
                ['S11', 'S11', 'S16'],
            ],
            'S13 and S14 capabilities' => ['db/access.php', $access, ['S13', 'S14', 'S14']],
            'S12 and Q8 deprecated' => [
                'page.php',
                "add_to_log(1, 'x');\n\$c = get_context_instance(CONTEXT_SYSTEM);\n",
                ['Q8', 'S12'],
            ],
            'Q8 lib.php callback' => ['lib.php', "function local_demo_before_footer() {\n    return '';\n}\n", ['Q8']],
            'Q7 debug output' => ['page.php', "var_dump(\$a);\n\$s = var_export(\$a, true);\nvar_export(\$a);\n", ['Q7', 'Q7']],
            'Q3 core globals' => [
                'page.php',
                "\$CFG->debug = 1;\n\$USER = get_admin();\n\$SESSION->local_demo_x = 1;\n\$PAGE->set_url('/x');\n" .
                    "\$debug = \$CFG->debug == 1;\n",
                ['Q3', 'Q3'],
            ],
            'P1 query in loop' => [
                'page.php',
                "foreach (\$users as \$u) {\n    \$DB->get_record('user', ['id' => \$u->id]);\n    \$DB->sql_like('a', '?');\n}\n" .
                    "\$DB->get_record('user', ['id' => 1]);\n",
                ['P1'],
            ],
            'P4 sleep on a page' => ['page.php', "sleep(1);\n", ['P4']],
            'P4 sleep in a task' => ['classes/task/demo.php', "sleep(1);\n", []],
            'P4 sleep in a CLI script' => ['cli/demo.php', "define('CLI_SCRIPT', true);\nsleep(1);\n", []],
        ];
    }

    /**
     * Each MoodleAudit sniff reports its check ID, and only for the problem cases.
     *
     * @param string $file fixture file path, relative to the fake plugin.
     * @param string $code fixture code, without the opening tag.
     * @param string[] $expected expected check IDs, one per finding.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('audit_sniffs_provider')]
    public function test_audit_sniffs(string $file, string $code, array $expected): void {
        $root = $this->write_files([$file => "<?php\n" . $code]);
        sort($expected);
        $this->assertSame($expected, $this->audit_ids($this->run_checker($root . '/' . $file)));
    }

    /**
     * Without the audit option, no audit sniff runs.
     */
    public function test_audit_is_opt_in(): void {
        $root = $this->write_files(['page.php' => "<?php\n\$x = \$_GET['x'];\nexec('ls');\n"]);
        $this->assertSame([], $this->audit_ids($this->run_checker($root . '/page.php', false)));
        $this->assertSame(['S3', 'S9'], $this->audit_ids($this->run_checker($root . '/page.php', true)));
    }

    /**
     * Plugin-level checks on a bare plugin report what is missing.
     */
    public function test_plugin_checks_bare_plugin(): void {
        $root = $this->write_files(['version.php' => "<?php\n"]);
        $results = plugin_checks::run($root);
        $this->assertSame('fail', $results['S17'][0]['status']);
        $this->assertSame('warning', $results['Q4'][0]['status']);
        $this->assertSame('info', $results['Q5'][0]['status']);
        $this->assertSame('warning', $results['Q6'][0]['status']);
        $this->assertCount(1, $results['S20']);
        $this->assertSame('pass', $results['S20'][0]['status']);
    }

    /**
     * Plugin-level checks on a complete plugin, including thirdpartylibs.xml problems.
     */
    public function test_plugin_checks_complete_plugin(): void {
        $thirdparty = '<?xml version="1.0"?><libraries>' .
            '<library><location>lib/good</location><name>Good</name><version>1.0</version><license>MIT</license></library>' .
            '<library><location>../outside</location><name>Outside</name><version>1.0</version><license>MIT</license></library>' .
            '<library><location>lib/good</location><name>Partial</name></library>' .
            '</libraries>';
        $root = $this->write_files([
            'version.php' => "<?php\n",
            'classes/privacy/provider.php' => "<?php\n",
            'tests/demo_test.php' => "<?php\n",
            'db/uninstall.php' => "<?php\n",
            'README.md' => "Demo plugin.\n",
            'thirdpartylibs.xml' => $thirdparty,
            'lib/good/good.php' => "<?php\n",
            'vendor/autoload.php' => "<?php\n",
        ]);
        $results = plugin_checks::run($root);
        $this->assertSame('pass', $results['S17'][0]['status']);
        $this->assertSame('pass', $results['Q4'][0]['status']);
        $this->assertSame('info', $results['Q5'][0]['status']);
        $this->assertSame('pass', $results['Q6'][0]['status']);
        $this->assertSame(['info', 'warning', 'warning', 'warning'], array_column($results['S20'], 'status'));
        $this->assertStringContainsString('vendor/', $results['S20'][3]['message']);
    }

    /**
     * An empty README and an invalid thirdpartylibs.xml are reported.
     */
    public function test_plugin_checks_empty_readme_invalid_xml(): void {
        $root = $this->write_files(['version.php' => "<?php\n", 'README' => "\n", 'thirdpartylibs.xml' => 'not xml']);
        $results = plugin_checks::run($root);
        $this->assertSame('warning', $results['Q6'][0]['status']);
        $this->assertStringContainsString('README', $results['Q6'][0]['message']);
        $this->assertSame('warning', $results['S20'][0]['status']);
    }

    /**
     * Only checked folders holding a version.php are plugin roots.
     */
    public function test_find_plugin_roots(): void {
        $root = $this->write_files(['version.php' => "<?php\n", 'classes/demo.php' => "<?php\n"]);
        $roots = plugin_checks::find_plugin_roots([$root, $root . '/version.php', $root . '/classes', $root . '/']);
        $this->assertSame([$root], $roots);
    }

    /**
     * Templates and JavaScript sources are scanned for external loads; builds and tests are not.
     */
    public function test_asset_scanner(): void {
        $root = $this->write_files([
            'templates/demo.mustache' => "<div>\n<script src=\"https://cdn.example.com/a.js\"></script>\n</div>\n",
            'amd/src/demo.js' => "const s = document.createElement('script');\n",
            'amd/build/demo.min.js' => "document.createElement('script');\n",
            'tests/behat/demo.mustache' => "<script src=\"https://cdn.example.com/a.js\"></script>\n",
            'templates/fine.mustache' => "<link rel=\"canonical\" href=\"https://example.com/\">\n",
            'templates/multiline.mustache' => "<p>x</p>\n<script\n    src=\"https://cdn.example.com/b.js\"></script>\n",
        ]);
        $findings = asset_scanner::scan($root, ['*/amd/build/*']);
        $found = array_map(function ($finding) use ($root) {
            return substr($finding['file'], strlen($root) + 1) . ':' . $finding['line'];
        }, $findings);
        sort($found);
        $this->assertSame(['amd/src/demo.js:1', 'templates/demo.mustache:2', 'templates/multiline.mustache:2'], $found);

        $xml = new \SimpleXMLElement('<phpcs></phpcs>');
        asset_scanner::add_to_report($xml, $findings);
        $this->assertSame([3, 0], local_codechecker_count_problems($xml));
        $this->assertSame(3, checklist::count_findings($xml)['S6']);
    }

    /**
     * The checklist lists every check once, in order.
     */
    public function test_checklist_checks(): void {
        $ids = array_keys(checklist::checks());
        $this->assertCount(34, $ids);
        $this->assertSame(['S0', 'S1'], array_slice($ids, 0, 2));
        $this->assertSame('P4', end($ids));
        foreach ($ids as $id) {
            $this->assertNotEmpty(get_string('auditcheck_' . $id, 'local_codechecker'));
        }
    }

    /**
     * Data provider for test_check_for_source().
     *
     * @return array phpcs source and the expected check ID.
     */
    public static function check_for_source_provider(): array {
        return [
            'audit sniff' => ['MoodleAudit.Security.Superglobals.S3', 'S3'],
            'unknown audit ID' => ['MoodleAudit.Security.Superglobals.S99', null],
            'Moodle internal check' => ['moodle.Files.MoodleInternal.MoodleInternalGlobalState', 'S18'],
            'Moodle login check' => ['moodle.Files.RequireLogin.Missing', 'S1'],
            'Moodle deprecated function' => ['moodle.PHP.DeprecatedFunctions.Deprecated', 'Q8'],
            'unrelated style check' => ['moodle.Files.LineLength.TooLong', null],
            'prefix only on a whole part' => ['moodle.Files.MoodleInternalX.Found', null],
        ];
    }

    /**
     * Findings are mapped to the right checklist item.
     *
     * @param string $source phpcs source code.
     * @param string|null $expected expected check ID.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('check_for_source_provider')]
    public function test_check_for_source(string $source, ?string $expected): void {
        $this->assertSame($expected, checklist::check_for_source($source));
    }

    /**
     * The checklist rows show findings, plugin results and manual checks.
     */
    public function test_checklist_rows(): void {
        $counts = array_fill_keys(array_keys(checklist::checks()), 0);
        $counts['S3'] = 2;

        $rows = array_column(checklist::rows($counts, []), null, 'id');
        $this->assertSame('fail', $rows['S3']['status']);
        $this->assertSame(get_string('auditresult_findings', 'local_codechecker', 2), $rows['S3']['results'][0]);
        $this->assertSame('pass', $rows['S9']['status']);
        $this->assertCount(2, $rows['S7']['results']); // Partial checks also say what is left to review.
        $this->assertSame([get_string('auditresult_manual', 'local_codechecker')], $rows['S0']['results']);
        $this->assertSame([get_string('auditresult_noplugin', 'local_codechecker')], $rows['S17']['results']);

        $root = $this->write_files(['version.php' => "<?php\n"]);
        $rows = array_column(checklist::rows($counts, ['local/demo' => plugin_checks::run($root)]), null, 'id');
        $this->assertSame('fail', $rows['S17']['status']);
        $this->assertSame('warning', $rows['Q6']['status']);
        $this->assertSame('info', $rows['Q5']['status']);
    }

    /**
     * The whole audit on a plugin folder: sniffs, template scan and plugin-level checks.
     */
    public function test_local_codechecker_audit(): void {
        $root = $this->write_files([
            'version.php' => "<?php\n",
            'page.php' => "<?php\n\$x = \$_GET['x'];\n",
            'templates/demo.mustache' => "<script src=\"//cdn.example.com/a.js\"></script>\n",
        ]);
        $xml = $this->run_checker($root);
        $rows = array_column(local_codechecker_audit([$root], ['*/amd/build/*'], $xml), null, 'id');
        $this->assertSame('fail', $rows['S3']['status']);
        $this->assertSame('fail', $rows['S6']['status']);
        $this->assertSame('fail', $rows['S17']['status']);
        $this->assertSame(1, checklist::count_findings($xml)['S6']);
    }
}
