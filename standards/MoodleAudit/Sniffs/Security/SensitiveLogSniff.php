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
 * Audit S11 and S16: no secrets or the sesskey in logs and error messages.
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
 * Audit S11 and S16: no secrets, PII or the sesskey in logs or user-facing errors.
 *
 * Looks at the arguments of logging and output calls (debugging(), mtrace(), error_log(),
 * cli_writeln(), cli_error()) and of new ...exception(...). A variable or property named
 * like a secret (password, secret, token, API key, private key, credential) is reported
 * as S11; a sesskey() call is reported as S16.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class SensitiveLogSniff implements Sniff {
    /** @var string[] Functions whose arguments end up in logs or on screen. */
    const LOG_FUNCTIONS = ['cli_error', 'cli_writeln', 'debugging', 'error_log', 'mtrace'];

    /** @var string Pattern for variable and property names that hold secrets. */
    const SECRET_PATTERN = '~(password|passwd|secret|token|api_?key|private_?key|credential)~i';

    /**
     * Register the tokens this sniff listens for.
     *
     * @return int[] token codes.
     */
    public function register() {
        return [T_STRING];
    }

    /**
     * Check the arguments of logging calls and exception constructors.
     *
     * @param File $file the file being checked.
     * @param int $pointer position of the token.
     */
    public function process(File $file, $pointer) {
        $tokens = $file->getTokens();
        $name = strtolower($tokens[$pointer]['content']);
        $islog = in_array($name, self::LOG_FUNCTIONS, true) && Audit::is_function_call($file, $pointer);
        $previous = $file->findPrevious(Audit::empty_tokens(), $pointer - 1, null, true);
        $isexception = substr($name, -9) === 'exception' && $previous !== false && $tokens[$previous]['code'] === T_NEW;
        if (!$islog && !$isexception) {
            return;
        }
        $open = $file->findNext(Audit::empty_tokens(), $pointer + 1, null, true);
        if ($open === false || $tokens[$open]['code'] !== T_OPEN_PARENTHESIS || !isset($tokens[$open]['parenthesis_closer'])) {
            return;
        }
        $this->check_arguments($file, $open + 1, $tokens[$open]['parenthesis_closer'] - 1, $pointer);
    }

    /**
     * Report secrets or the sesskey in the given argument tokens.
     *
     * @param File $file the file being checked.
     * @param int $start first token of the arguments.
     * @param int $end last token of the arguments.
     * @param int $report position to report problems at.
     */
    protected function check_arguments(File $file, int $start, int $end, int $report): void {
        $tokens = $file->getTokens();
        for ($i = $start; $i <= $end; $i++) {
            $code = $tokens[$i]['code'];
            $content = $tokens[$i]['content'];
            if ($code === T_STRING && strtolower($content) === 'sesskey' && Audit::is_function_call($file, $i)) {
                $file->addWarning(Audit::message('sesskeylogged'), $report, 'S16');
                return;
            }
            $isname = $code === T_VARIABLE ||
                ($code === T_STRING && in_array($tokens[$i - 1]['code'], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true));
            if ($isname && preg_match(self::SECRET_PATTERN, $content)) {
                $file->addWarning(Audit::message('secretlogged', $content), $report, 'S11');
                return;
            }
            if (in_array($code, [T_DOUBLE_QUOTED_STRING, T_HEREDOC], true)) {
                // Interpolated variables and properties, for example "{$user->password}".
                preg_match_all('~\$\w+(?:->\w+)*~', $content, $matches);
                foreach ($matches[0] as $interpolated) {
                    if (preg_match(self::SECRET_PATTERN, $interpolated)) {
                        $file->addWarning(Audit::message('secretlogged', $interpolated), $report, 'S11');
                        return;
                    }
                }
            }
        }
    }
}
