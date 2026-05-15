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
 * Run the code checker from the web.
 *
 * @package    local_codechecker
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Own stuff (TODO: Some day all these will be moved to classes).
require_once($CFG->dirroot . '/local/codechecker/locallib.php');

// Auto load all the (vendor installed) tools we are going to need.
if (!autoload_tools()) {
    throw new coding_exception('Unable to find the "vendor" directory within the plugin. Please, install the plugin again.');
}

// The admin page URL is deliberately kept clean (no filesystem path in the
// query string): some web-server WAF rules (e.g. nginx LFI / path-traversal
// filters, like YunoHost's defaults) reject such requests with a 403.
admin_externalpage_setup('local_codechecker');

// We are going to need lots of memory and time.
raise_memory_limit(MEMORY_HUGE);
set_time_limit(600);

$mform = new local_codechecker_form(new moodle_url('/local/codechecker/'));

if ($data = $mform->get_data()) {
    // Handle the submission in-place instead of doing a Post/Redirect/Get.
    // A PRG redirect would expose the filesystem path in the URL query
    // string, which the WAF rules mentioned above block before the request
    // ever reaches Moodle. Keeping the path in the POST body avoids that.
    $pathlist = $data->path;
    $exclude = $data->exclude;
    $includewarnings = !empty($data->includewarnings);
    $showstandard = !empty($data->showstandard);
} else {
    // Backwards compatibility for direct GET links. These may still be
    // blocked by the same WAF rules when the path is present in the query.
    $pathlist = optional_param('path', '', PARAM_RAW);
    $exclude = optional_param('exclude', '', PARAM_NOTAGS);
    $includewarnings = optional_param('includewarnings', true, PARAM_BOOL);
    $showstandard = optional_param('showstandard', false, PARAM_BOOL);
}

$mform->set_data((object)[
    'path' => $pathlist,
    'exclude' => $exclude,
    'includewarnings' => $includewarnings,
    'showstandard' => $showstandard,
]);

$output = $PAGE->get_renderer('local_codechecker');

echo $OUTPUT->header();

if ($pathlist) {
    // Unlock the session before processing the files.
    \core\session\manager::write_close();

    $paths = preg_split('~[\r\n]+~', $pathlist);

    $failed = false;
    $fullpaths = [];
    foreach ($paths as $path) {
        $path = trim(clean_param($path, PARAM_PATH));
        if (empty($path)) { // No blanks, we don't want to check the whole dirroot.
            continue;
        }
        $fullpath = $CFG->dirroot . '/' . trim($path, '/');
        if (!is_file($fullpath) && !is_dir($fullpath)) {
            echo $output->invald_path_message($path);
            $failed = true;
            continue;
        }
        $fullpaths[] = local_codechecker_clean_path($fullpath);
    }

    if ($fullpaths && !$failed) {
        // Calculate the ignores.
        $ignores = local_codesniffer_get_ignores($exclude);

        // Let's use our own Runner, all we need is to pass some
        // configuration settings (reportfile, show warnings) and
        // override the init() method to set all our config options.
        // Finally, use own run() method, much simplified from
        // the runPHPCS() upstream one.
        $runner = new \local_codechecker\runner();

        $reportfile = make_temp_directory('phpcs') . '/phpcs_' . random_string(10) . '.xml';
        $runner->set_reportfile($reportfile);
        $runner->set_includewarnings($includewarnings);
        $runner->set_ignorepatterns($ignores);
        $runner->set_files($fullpaths);

        $runner->run();

        // Load the XML file to proceed with the rest of checks.
        $xml = simplexml_load_file($reportfile);

        // Look for other problems, not handled by codesniffer. Use same list of ignored (originally in keys, now in values).
        local_codechecker_check_other_files(local_codechecker_clean_path($fullpath), $xml, array_keys($ignores));
        [$numerrors, $numwarnings] = local_codechecker_count_problems($xml);

        // Output the results report.
        echo $output->report($xml, $numerrors, $numwarnings, $showstandard);

        // And clean the report temp file.
        @unlink($reportfile);
    }
}

$mform->display();
echo $OUTPUT->footer();
