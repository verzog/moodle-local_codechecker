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
 * Audit S9: no shell execution.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace MoodleAudit\Sniffs\Security;

use MoodleAudit\Util\Audit;
use MoodleAudit\Util\FunctionCallSniff;
use PHP_CodeSniffer\Files\File;

/**
 * Audit S9: no exec(), system(), passthru(), shell_exec(), popen() or proc_open().
 *
 * If one is unavoidable, every argument must go through escapeshellarg() and the
 * command through escapeshellcmd(). Backticks are already forbidden by the Moodle standard.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ShellExecutionSniff extends FunctionCallSniff {
    /**
     * The functions this sniff looks for.
     *
     * @return array lower-case function names as keys.
     */
    protected function functions(): array {
        return array_fill_keys(['exec', 'passthru', 'pcntl_exec', 'popen', 'proc_open', 'shell_exec', 'system'], true);
    }

    /**
     * Report one shell execution call.
     *
     * @param File $file the file being checked.
     * @param int $pointer position of the function name.
     * @param string $function lower-case function name.
     * @param mixed $data not used.
     */
    protected function found(File $file, int $pointer, string $function, $data): void {
        unset($data); // Every listed function gets the same message.
        $file->addWarning(Audit::message('shell', $function), $pointer, 'S9');
    }
}
