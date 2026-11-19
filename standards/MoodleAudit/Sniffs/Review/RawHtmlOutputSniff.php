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
 * Audit R2: pages build their HTML with templates and the Output API.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace MoodleAudit\Sniffs\Review;

use MoodleAudit\Util\Audit;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Audit R2: pages build their HTML with Mustache templates, not by writing it out.
 *
 * Checks page scripts (files that include config.php, other than CLI scripts):
 * every echo or print whose output includes HTML markup in a string, or
 * html_writer output, counts as a raw HTML write, as does HTML outside the PHP
 * tags. Output API calls such as echo $OUTPUT->header() do not count. A page with
 * any raw HTML writes gets one finding, at the first, giving the number of writes.
 * Classes, renderers and test code are not checked.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class RawHtmlOutputSniff implements Sniff {
    /** @var string An HTML opening or closing tag inside a string. */
    const TAG_PATTERN = '~</?[a-z][a-z0-9]*(\s|/?>)~i';

    /**
     * Register the tokens this sniff listens for.
     *
     * @return int[] token codes.
     */
    public function register() {
        return [T_OPEN_TAG];
    }

    /**
     * Count the raw HTML writes in a page script and report them once.
     *
     * @param File $file the file being checked.
     * @param int $pointer position of the token.
     * @return int the end of the file, so the file is processed once.
     */
    public function process(File $file, $pointer) {
        $end = $file->numTokens;
        if (Audit::is_test_file($file) || Audit::is_offline_file($file) || Audit::config_inclusion($file) === false) {
            return $end;
        }
        $tokens = $file->getTokens();
        $first = null;
        $count = 0;
        for ($i = 0; $i < $end; $i++) {
            $code = $tokens[$i]['code'];
            if ($code === T_INLINE_HTML) {
                $writes = preg_match(self::TAG_PATTERN, $tokens[$i]['content']) ? 1 : 0;
            } else if ($code === T_ECHO || $code === T_PRINT) {
                $writes = self::writes_html($file, $i, $file->findEndOfStatement($i)) ? 1 : 0;
            } else {
                continue;
            }
            if ($writes) {
                $first ??= $i;
                $count++;
            }
        }
        if ($count > 0) {
            $file->addWarning(Audit::message('rawhtml', $count), $first, 'R2');
        }
        return $end;
    }

    /**
     * Whether an echo or print statement outputs HTML markup or html_writer output.
     *
     * @param File $file the file being checked.
     * @param int $start position of the echo or print.
     * @param int $end position of the end of the statement.
     * @return bool true for a raw HTML write.
     */
    protected static function writes_html(File $file, int $start, int $end): bool {
        $tokens = $file->getTokens();
        $strings = [T_CONSTANT_ENCAPSED_STRING, T_DOUBLE_QUOTED_STRING, T_HEREDOC, T_NOWDOC];
        for ($i = $start + 1; $i <= $end; $i++) {
            if (in_array($tokens[$i]['code'], $strings, true) && preg_match(self::TAG_PATTERN, $tokens[$i]['content'])) {
                return true;
            }
            if ($tokens[$i]['code'] === T_STRING && strtolower($tokens[$i]['content']) === 'html_writer') {
                $next = $file->findNext(Audit::empty_tokens(), $i + 1, null, true);
                if ($next !== false && $tokens[$next]['code'] === T_DOUBLE_COLON) {
                    return true;
                }
            }
        }
        return false;
    }
}
