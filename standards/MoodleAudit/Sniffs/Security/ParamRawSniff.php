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
 * Audit S3: PARAM_RAW needs a justification.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace MoodleAudit\Sniffs\Security;

use MoodleAudit\Util\Audit;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Audit S3: input is read with strict PARAM_* types; PARAM_RAW only if justified.
 *
 * Reports PARAM_RAW passed to optional_param(), required_param() and their array
 * variants, and to a form's setType(). A comment on the same line, or on the line
 * just above the statement, counts as the justification.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ParamRawSniff implements Sniff {
    /**
     * Register the tokens this sniff listens for.
     *
     * @return int[] token codes.
     */
    public function register() {
        return [T_STRING];
    }

    /**
     * Report PARAM_RAW used for request input or form fields.
     *
     * @param File $file the file being checked.
     * @param int $pointer position of the token.
     */
    public function process(File $file, $pointer) {
        $tokens = $file->getTokens();
        if ($tokens[$pointer]['content'] !== 'PARAM_RAW' || empty($tokens[$pointer]['nested_parenthesis'])) {
            return;
        }
        // The innermost call this constant is an argument of.
        $parentheses = array_keys($tokens[$pointer]['nested_parenthesis']);
        $open = end($parentheses);
        $name = $file->findPrevious(Audit::empty_tokens(), $open - 1, null, true);
        if ($name === false || $tokens[$name]['code'] !== T_STRING) {
            return;
        }
        $function = strtolower($tokens[$name]['content']);
        if (!in_array($function, Audit::PARAM_FUNCTIONS, true) && $function !== 'settype') {
            return;
        }
        if (!$this->is_justified($file, $pointer)) {
            $file->addWarning(Audit::message('paramraw', $tokens[$name]['content']), $pointer, 'S3');
        }
    }

    /**
     * Whether a comment next to the statement explains the use of PARAM_RAW.
     *
     * @param File $file the file being checked.
     * @param int $pointer position of PARAM_RAW.
     * @return bool true if a comment is on the same line or on the line just above.
     */
    protected function is_justified(File $file, int $pointer): bool {
        $tokens = $file->getTokens();
        $line = $tokens[$pointer]['line'];
        // Any comment on the same line.
        for ($i = $pointer; $i < $file->numTokens && $tokens[$i]['line'] === $line; $i++) {
            if ($tokens[$i]['code'] === T_COMMENT) {
                return true;
            }
        }
        // A comment ending on the line above, with only whitespace before this line's code.
        $first = $pointer;
        while ($first > 0 && $tokens[$first - 1]['line'] === $line) {
            $first--;
        }
        $before = $file->findPrevious(T_WHITESPACE, $first - 1, null, true);
        return $before !== false && $tokens[$before]['code'] === T_COMMENT && $tokens[$before]['line'] === $line - 1;
    }
}
