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
 * Audit Q3: no runtime writes to core globals.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace MoodleAudit\Sniffs\Quality;

use MoodleAudit\Util\Audit;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Audit Q3: no runtime writes or replacements of core globals that change later behaviour.
 *
 * Reports assignments to $CFG, $USER, $COURSE, $SITE and $PAGE properties, and
 * replacing any of those, $SESSION, $OUTPUT or $DB. Writing plugin-namespaced
 * $SESSION keys is allowed. Test code is skipped.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class CoreGlobalWriteSniff implements Sniff {
    /** @var bool[] Core globals, true when writing one of their properties is also reported. */
    const GLOBALS = [
        '$CFG' => true,
        '$COURSE' => true,
        '$DB' => false,
        '$OUTPUT' => false,
        '$PAGE' => true,
        '$SESSION' => false,
        '$SITE' => true,
        '$USER' => true,
    ];

    /**
     * Register the tokens this sniff listens for.
     *
     * @return int[] token codes.
     */
    public function register() {
        return [T_VARIABLE];
    }

    /**
     * Report a write to a core global.
     *
     * @param File $file the file being checked.
     * @param int $pointer position of the token.
     */
    public function process(File $file, $pointer) {
        $tokens = $file->getTokens();
        $name = $tokens[$pointer]['content'];
        if (!isset(self::GLOBALS[$name]) || Audit::is_test_file($file) || basename(Audit::path($file)) === 'config.php') {
            return;
        }
        // Walk over any ->property and [index] parts to the operator after them.
        $next = $file->findNext(Audit::empty_tokens(), $pointer + 1, null, true);
        $property = false;
        while ($next !== false) {
            if ($tokens[$next]['code'] === T_OBJECT_OPERATOR) {
                $property = true;
                $next = $file->findNext(Audit::empty_tokens(), $next + 2, null, true);
            } else if ($tokens[$next]['code'] === T_OPEN_SQUARE_BRACKET && isset($tokens[$next]['bracket_closer'])) {
                $property = true;
                $next = $file->findNext(Audit::empty_tokens(), $tokens[$next]['bracket_closer'] + 1, null, true);
            } else {
                break;
            }
        }
        if ($next === false || $tokens[$next]['code'] === T_DOUBLE_ARROW) {
            return;
        }
        if (!isset(Audit::assignment_tokens()[$tokens[$next]['code']])) {
            return;
        }
        if ($property && !self::GLOBALS[$name]) {
            return;
        }
        $file->addWarning(Audit::message($property ? 'globalproperty' : 'globalreplace', $name), $pointer, 'Q3');
    }
}
