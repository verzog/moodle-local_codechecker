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
 * Audit S7: SQL binds request input through placeholders.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace MoodleAudit\Sniffs\Security;

use MoodleAudit\Util\Audit;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHPCSUtils\Utils\PassedParameters;

/**
 * Audit S7: $DB queries bind every value through placeholders.
 *
 * Reports SQL text (the SQL or WHERE argument of the $DB *_sql() and *_select() methods
 * and execute()) built from request input: a superglobal, an optional_param() /
 * required_param() call, or a variable assigned straight from one of those calls
 * earlier in the same file. Other concatenated variables are left to manual review,
 * because {table} names, get_in_or_equal() and $DB->sql_*() fragments are allowed.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class SqlUserInputSniff implements Sniff {
    /**
     * Register the tokens this sniff listens for.
     *
     * @return int[] token codes.
     */
    public function register() {
        return [T_OPEN_TAG];
    }

    /**
     * Check every $DB SQL call in the file once.
     *
     * @param File $file the file being checked.
     * @param int $pointer position of the token.
     * @return int position to skip to (the end of the file).
     */
    public function process(File $file, $pointer) {
        $tokens = $file->getTokens();
        $tainted = $this->request_variables($file);
        for ($i = $pointer; $i < $file->numTokens; $i++) {
            if ($tokens[$i]['code'] !== T_VARIABLE) {
                continue;
            }
            $call = Audit::db_method_call($file, $i);
            if ($call === null || !isset(Audit::DB_SQL_METHODS[$call[0]])) {
                continue;
            }
            $sql = PassedParameters::getParameter($file, $call[1], Audit::DB_SQL_METHODS[$call[0]], ['sql', 'select']);
            if ($sql !== false) {
                $this->check_sql($file, $sql['start'], $sql['end'], $tainted, $call[1]);
            }
        }
        return $file->numTokens;
    }

    /**
     * Find the variables assigned directly from a request parameter function.
     *
     * @param File $file the file being checked.
     * @return string[] variable names, including the $.
     */
    protected function request_variables(File $file): array {
        $tokens = $file->getTokens();
        $names = [];
        for ($i = 0; $i < $file->numTokens; $i++) {
            if ($tokens[$i]['code'] !== T_VARIABLE) {
                continue;
            }
            $equal = $file->findNext(Audit::empty_tokens(), $i + 1, null, true);
            if ($equal === false || $tokens[$equal]['code'] !== T_EQUAL) {
                continue;
            }
            $value = $file->findNext(Audit::empty_tokens(), $equal + 1, null, true);
            if (
                $value !== false && $tokens[$value]['code'] === T_STRING &&
                    in_array(strtolower($tokens[$value]['content']), Audit::PARAM_FUNCTIONS, true)
            ) {
                $names[] = $tokens[$i]['content'];
            }
        }
        return array_unique($names);
    }

    /**
     * Report request input found in an SQL argument.
     *
     * @param File $file the file being checked.
     * @param int $start first token of the SQL argument.
     * @param int $end last token of the SQL argument.
     * @param string[] $tainted variables holding request input.
     * @param int $report position to report the problem at.
     */
    protected function check_sql(File $file, int $start, int $end, array $tainted, int $report): void {
        $tokens = $file->getTokens();
        for ($i = $start; $i <= $end; $i++) {
            $content = $tokens[$i]['content'];
            $found = null;
            if ($tokens[$i]['code'] === T_VARIABLE && (strpos($content, '$_') === 0 || in_array($content, $tainted, true))) {
                $found = $content;
            } else if ($tokens[$i]['code'] === T_STRING && in_array(strtolower($content), Audit::PARAM_FUNCTIONS, true)) {
                $found = $content . '()';
            } else if (in_array($tokens[$i]['code'], [T_DOUBLE_QUOTED_STRING, T_HEREDOC], true)) {
                foreach (array_merge(['$_'], $tainted) as $name) {
                    if (preg_match('~' . preg_quote($name, '~') . ($name === '$_' ? '' : '\b') . '~', $content)) {
                        $found = $name;
                        break;
                    }
                }
            }
            if ($found !== null) {
                $file->addError(Audit::message('sqlinput', $found), $report, 'S7');
                return;
            }
        }
    }
}
