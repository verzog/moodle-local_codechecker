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
 * Audit S8: state-changing requests check the sesskey.
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
 * Audit S8: session-authenticated state-changing requests check the sesskey first.
 *
 * Reports web entry points (files that include config.php and are not CLI scripts) that
 * read request parameters and write to the database, but contain no require_sesskey(),
 * confirm_sesskey() or Form API submission (->get_data()). External functions are
 * dispatched by core AJAX, which checks the sesskey, so they are not entry points.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class SesskeySniff implements Sniff {
    /** @var string[] Calls that perform an effective sesskey check. */
    const CHECKS = ['confirm_sesskey', 'get_data', 'require_sesskey'];

    /**
     * Register the tokens this sniff listens for.
     *
     * @return int[] token codes.
     */
    public function register() {
        return [T_OPEN_TAG];
    }

    /**
     * Check the file once.
     *
     * @param File $file the file being checked.
     * @param int $pointer position of the token.
     * @return int position to skip to (the end of the file).
     */
    public function process(File $file, $pointer) {
        if (Audit::config_inclusion($file) === false || Audit::defines_constant($file, 'CLI_SCRIPT')) {
            return $file->numTokens;
        }
        $tokens = $file->getTokens();
        $readsinput = false;
        $firstwrite = null;
        for ($i = $pointer; $i < $file->numTokens; $i++) {
            if ($tokens[$i]['code'] === T_STRING) {
                $name = strtolower($tokens[$i]['content']);
                if (in_array($name, self::CHECKS, true)) {
                    return $file->numTokens; // A sesskey check exists; the order is left to manual review.
                }
                if (in_array($name, Audit::PARAM_FUNCTIONS, true)) {
                    $readsinput = true;
                }
            } else if ($tokens[$i]['code'] === T_VARIABLE && $firstwrite === null) {
                $call = Audit::db_method_call($file, $i);
                if ($call !== null && in_array($call[0], Audit::DB_WRITE_METHODS, true)) {
                    $firstwrite = $i;
                }
            }
        }
        if ($readsinput && $firstwrite !== null) {
            $file->addWarning(Audit::message('sesskey'), $firstwrite, 'S8');
        }
        return $file->numTokens;
    }
}
