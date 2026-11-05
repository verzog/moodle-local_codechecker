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
 * Run the code checker from the command-line.
 *
 * @package    local_codechecker
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php');

// Own library code.
require_once($CFG->dirroot . '/local/codechecker/locallib.php');

// Auto load all the (vendor installed) tools we are going to need.
if (!autoload_tools()) {
    throw new coding_exception('Unable to find the "vendor" directory within the plugin. Please, install the plugin again.');
}

// Get the command-line options.
[$options, $unrecognized] = cli_get_params(
    ['help' => false, 'interactive' => false, 'exclude' => '', 'skiplicence' => false, 'audit' => false],
    ['h' => 'help',
    'i' => 'interactive',
    'e' => 'exclude',
    'l' => 'skiplicence',
    'a' => 'audit',
    ]
);

if (count($unrecognized) != 1) {
    $options['help'] = true;
} else {
    $path = clean_param(reset($unrecognized), PARAM_PATH);
}

if ($options['help']) {
    echo get_string('clihelp', 'local_codechecker'), "\n";
    die();
}

$interactive = false;
if ($options['interactive']) {
    $interactive = true;
}

raise_memory_limit(MEMORY_HUGE);

// Initialize and run the CS.
$runner = new \local_codechecker\runner();
$runner->set_verbosity(1);
$runner->set_interactive($interactive);
$ignores = local_codesniffer_get_ignores($options['exclude']);
$runner->set_ignorepatterns($ignores);
if ($options['skiplicence']) {
    $runner->set_excludedsniffs(local_codechecker_licence_sniffs());
}

$fullpath = local_codechecker_clean_path($CFG->dirroot . '/' . trim($path, '/'));
$runner->set_files([$fullpath]);
$audit = !empty($options['audit']);
if ($audit) {
    // The audit checklist is built from an XML copy of the report.
    $runner->set_audit(true);
    $reportfile = make_request_directory() . '/phpcs.xml';
    $runner->add_reportfile($reportfile);
}

$runner->run();

if ($audit) {
    $xml = simplexml_load_file($reportfile);
    // Template and JavaScript findings are not part of the phpcs report above, so print them here.
    $findings = \local_codechecker\audit\asset_scanner::scan($fullpath, array_keys($ignores));
    foreach ($findings as $finding) {
        cli_writeln(local_codechecker_pretty_path($finding['file']) . ':' . $finding['line'] . ': ' . $finding['message']);
    }
    $rows = local_codechecker_audit([$fullpath], array_keys($ignores), $xml);
    cli_writeln('');
    cli_heading(get_string('auditchecklist', 'local_codechecker'));
    foreach ($rows as $row) {
        cli_writeln($row['id'] . ' [' . $row['method'] . '] ' . $row['description']);
        foreach ($row['results'] as $result) {
            cli_writeln('    ' . $result);
        }
    }
}
