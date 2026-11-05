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
 * Audit S6: no hardcoded external script or stylesheet loads.
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
 * Audit S6: no hardcoded external script or stylesheet loads.
 *
 * Reports <script src="https://..."> and <link href="https://..."> in PHP strings and
 * inline HTML, and $PAGE->requires->js() / ->css() given an external URL literal.
 * Mustache templates and AMD sources are checked separately by \local_codechecker\audit\asset_scanner.
 * Test code is skipped, as it never loads assets into a real page.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ExternalAssetSniff implements Sniff {
    /** @var string Pattern for a string literal that is an external URL. */
    const URL_PATTERN = '~^\s*["\']\s*(https?:)?//~i';

    /**
     * Register the tokens this sniff listens for.
     *
     * @return int[] token codes.
     */
    public function register() {
        return [T_CONSTANT_ENCAPSED_STRING, T_DOUBLE_QUOTED_STRING, T_HEREDOC, T_NOWDOC, T_INLINE_HTML, T_STRING];
    }

    /**
     * Report external script or stylesheet loads.
     *
     * @param File $file the file being checked.
     * @param int $pointer position of the token.
     * @return int|void a position to skip to, when the file is skipped.
     */
    public function process(File $file, $pointer) {
        if (Audit::is_test_file($file)) {
            return $file->numTokens;
        }
        $tokens = $file->getTokens();
        if ($tokens[$pointer]['code'] === T_STRING) {
            $this->process_requires_call($file, $pointer);
            return;
        }
        if (Audit::loads_external_asset($tokens[$pointer]['content'])) {
            $file->addError(Audit::message('externalasset_tag'), $pointer, 'S6');
        }
    }

    /**
     * Report ->js() / ->css() calls on the page requirements manager given an external URL.
     *
     * @param File $file the file being checked.
     * @param int $pointer position of a T_STRING token.
     */
    protected function process_requires_call(File $file, int $pointer): void {
        $tokens = $file->getTokens();
        $method = strtolower($tokens[$pointer]['content']);
        if ($method !== 'js' && $method !== 'css') {
            return;
        }
        $operator = $file->findPrevious(Audit::empty_tokens(), $pointer - 1, null, true);
        if ($operator === false || $tokens[$operator]['code'] !== T_OBJECT_OPERATOR) {
            return;
        }
        $requires = $file->findPrevious(Audit::empty_tokens(), $operator - 1, null, true);
        if ($requires === false || strtolower($tokens[$requires]['content']) !== 'requires') {
            return;
        }
        $first = PassedParameters::getParameter($file, $pointer, 1, 'url');
        if ($first === false) {
            return;
        }
        // The URL may be a literal, or a literal wrapped in new moodle_url(...).
        for ($i = $first['start']; $i <= $first['end']; $i++) {
            if ($tokens[$i]['code'] === T_CONSTANT_ENCAPSED_STRING && preg_match(self::URL_PATTERN, $tokens[$i]['content'])) {
                $file->addError(Audit::message('externalasset_requires', $method), $pointer, 'S6');
                return;
            }
        }
    }
}
