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
 * Audit P1: no per-iteration database queries.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace MoodleAudit\Sniffs\Performance;

use MoodleAudit\Util\Audit;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Audit P1: no per-iteration database queries in loops over growing data.
 *
 * Reports $DB queries, and core helpers that query on every call, made inside a
 * foreach, for, while or do loop. A loop over a small fixed set or a single page is
 * bounded and fine, so each report needs a quick manual look. Test code is skipped.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class QueryInLoopSniff implements Sniff {
    /** @var string Pattern for $DB methods that run a query. */
    const DB_PATTERN = '~^(get_(record|records|field|fieldset|recordset)|record_exists|count_records|' .
        'insert_record|update_record|delete_records|set_field|execute)~';

    /** @var string[] Core helpers that query the database on every call. */
    const HELPERS = ['get_complete_user_data', 'get_course', 'get_coursemodule_from_id', 'get_coursemodule_from_instance'];

    /** @var int[] Loop tokens. */
    const LOOPS = [T_DO, T_FOR, T_FOREACH, T_WHILE];

    /**
     * Register the tokens this sniff listens for.
     *
     * @return int[] token codes.
     */
    public function register() {
        return [T_VARIABLE, T_STRING];
    }

    /**
     * Report a query inside a loop.
     *
     * @param File $file the file being checked.
     * @param int $pointer position of the token.
     */
    public function process(File $file, $pointer) {
        $tokens = $file->getTokens();
        if (empty($tokens[$pointer]['conditions']) || !array_intersect($tokens[$pointer]['conditions'], self::LOOPS)) {
            return;
        }
        if (Audit::is_test_file($file)) {
            return;
        }
        if ($tokens[$pointer]['code'] === T_VARIABLE) {
            $call = Audit::db_method_call($file, $pointer);
            if ($call !== null && preg_match(self::DB_PATTERN, $call[0])) {
                $file->addWarning(Audit::message('queryinloop', '$DB->' . $call[0] . '()'), $pointer, 'P1');
            }
            return;
        }
        $function = strtolower($tokens[$pointer]['content']);
        if (in_array($function, self::HELPERS, true) && Audit::is_function_call($file, $pointer)) {
            $file->addWarning(Audit::message('queryinloop', $function . '()'), $pointer, 'P1');
        }
    }
}
