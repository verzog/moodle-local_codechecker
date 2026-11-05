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
 * Audit Q7: no debug output left in the code.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace MoodleAudit\Sniffs\Quality;

use MoodleAudit\Util\Audit;
use MoodleAudit\Util\FunctionCallSniff;
use PHP_CodeSniffer\Files\File;
use PHPCSUtils\Utils\PassedParameters;

/**
 * Audit Q7: no debug artifacts such as var_dump() output.
 *
 * print_r() and print_object() are already reported by the Moodle standard, and
 * commented-out code by its inline comment checks. var_export() is reported only
 * when it prints (no second argument of true). debugging() and mtrace() are legitimate.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class DebugOutputSniff extends FunctionCallSniff {
    /**
     * The functions this sniff looks for.
     *
     * @return array lower-case function names as keys.
     */
    protected function functions(): array {
        return array_fill_keys(['debug_print_backtrace', 'debug_zval_dump', 'phpinfo', 'var_dump', 'var_export'], true);
    }

    /**
     * Report one debug output call.
     *
     * @param File $file the file being checked.
     * @param int $pointer position of the function name.
     * @param string $function lower-case function name.
     * @param mixed $data not used.
     */
    protected function found(File $file, int $pointer, string $function, $data): void {
        unset($data); // Every listed function gets the same message.
        if ($function === 'var_export') {
            $return = PassedParameters::getParameter($file, $pointer, 2, 'return');
            if ($return !== false && strtolower($return['clean']) !== 'false') {
                return; // Returns the string instead of printing it.
            }
        }
        $file->addWarning(Audit::message('debugoutput', $function), $pointer, 'Q7');
    }
}
