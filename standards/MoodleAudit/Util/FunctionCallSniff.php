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
 * Base class for the audit sniffs that look for calls to named global functions.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace MoodleAudit\Util;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Base class for the audit sniffs that look for calls to named global functions.
 *
 * Subclasses list the functions in functions() and report each call in found().
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class FunctionCallSniff implements Sniff {
    /**
     * The functions this sniff looks for.
     *
     * @return array lower-case function names as keys, with any per-function data as values.
     */
    abstract protected function functions(): array;

    /**
     * Report one call to a listed function.
     *
     * @param File $file the file being checked.
     * @param int $pointer position of the function name.
     * @param string $function lower-case function name.
     * @param mixed $data the value listed for this function in functions().
     */
    abstract protected function found(File $file, int $pointer, string $function, $data): void;

    /**
     * Whether the whole file should be skipped.
     *
     * @param File $file the file being checked.
     * @return bool true to skip the file.
     */
    protected function skip_file(File $file): bool {
        unset($file); // Not used by default; subclasses may skip some files.
        return false;
    }

    /**
     * Register the tokens this sniff listens for.
     *
     * @return int[] token codes.
     */
    public function register() {
        return [T_STRING];
    }

    /**
     * Process a T_STRING token.
     *
     * @param File $file the file being checked.
     * @param int $pointer position of the token.
     * @return int|void a position to skip to, when the file is skipped.
     */
    public function process(File $file, $pointer) {
        if ($this->skip_file($file)) {
            return $file->numTokens;
        }
        $tokens = $file->getTokens();
        $function = strtolower($tokens[$pointer]['content']);
        $functions = $this->functions();
        if (!array_key_exists($function, $functions) || !Audit::is_function_call($file, $pointer)) {
            return;
        }
        $this->found($file, $pointer, $function, $functions[$function]);
    }
}
