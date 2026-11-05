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
 * Audit P4: no sleeping or polling on the request path.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace MoodleAudit\Sniffs\Performance;

use MoodleAudit\Util\Audit;
use MoodleAudit\Util\FunctionCallSniff;
use PHP_CodeSniffer\Files\File;

/**
 * Audit P4: no sleep(), usleep() or polling on the request path.
 *
 * CLI scripts, scheduled and adhoc tasks, upgrade scripts and test code are skipped,
 * because they do not run during a web request.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class SleepSniff extends FunctionCallSniff {
    /**
     * The functions this sniff looks for.
     *
     * @return array lower-case function names as keys.
     */
    protected function functions(): array {
        return array_fill_keys(['sleep', 'time_nanosleep', 'time_sleep_until', 'usleep'], true);
    }

    /**
     * Skip files that never run during a web request.
     *
     * @param File $file the file being checked.
     * @return bool true to skip the file.
     */
    protected function skip_file(File $file): bool {
        return Audit::is_test_file($file) || Audit::is_offline_file($file);
    }

    /**
     * Report one sleep call.
     *
     * @param File $file the file being checked.
     * @param int $pointer position of the function name.
     * @param string $function lower-case function name.
     * @param mixed $data not used.
     */
    protected function found(File $file, int $pointer, string $function, $data): void {
        unset($data); // Every listed function gets the same message.
        $file->addWarning(Audit::message('sleep', $function), $pointer, 'P4');
    }
}
