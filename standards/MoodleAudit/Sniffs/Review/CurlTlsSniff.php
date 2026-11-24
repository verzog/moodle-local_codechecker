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
 * Audit R3: outbound HTTPS verifies certificates.
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
 * Audit R3: outbound HTTPS verifies certificates.
 *
 * Moodle's curl class sets CURLOPT_SSL_VERIFYPEER to 0 in curl::resetopt(), so a client built from
 * the curl class accepts any certificate unless the caller switches checking on - as core's update
 * checker and plugin installer do. Reports each curl client whose function (or, outside a function,
 * the rest of the file) never sets CURLOPT_SSL_VERIFYPEER to true or 1 after it - through setopt(),
 * the request options or an options array built first - and anywhere certificate or host-name
 * checking is switched off. Matches moodle-security-ci's R3-curl-no-verify and R3-tls-disabled.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class CurlTlsSniff implements Sniff {
    /** @var array Option name (upper case, unquoted) => short name. */
    const CURL_OPTIONS = [
        'CURLOPT_SSL_VERIFYPEER' => 'verifypeer', 'SSL_VERIFYPEER' => 'verifypeer',
        'CURLOPT_SSL_VERIFYHOST' => 'verifyhost', 'SSL_VERIFYHOST' => 'verifyhost',
    ];

    /** @var array Stream context option (upper case) => short name; only ever quoted array keys. */
    const STREAM_OPTIONS = [
        'VERIFY_PEER' => 'verify_peer', 'VERIFY_PEER_NAME' => 'verify_peer_name', 'ALLOW_SELF_SIGNED' => 'allow_self_signed',
    ];

    /**
     * The tokens this sniff looks at.
     *
     * @return array token types.
     */
    public function register(): array {
        return [T_NEW, T_CONSTANT_ENCAPSED_STRING, T_STRING];
    }

    /**
     * Check one token.
     *
     * @param File $file the file being checked.
     * @param int $pointer position of the token.
     * @return void
     */
    public function process(File $file, $pointer) {
        if (Audit::is_test_file($file)) {
            return;
        }
        $tokens = $file->getTokens();

        if ($tokens[$pointer]['code'] !== T_NEW) {
            $option = $this->option_name($tokens[$pointer]);
            if ($option === null) {
                return;
            }
            $value = $this->option_value($file, $pointer);
            $off = in_array($value, ['false', '0'], true);
            $on = in_array($value, ['true', '1'], true);
            if (($option === 'allow_self_signed' && $on) || ($option !== 'allow_self_signed' && $off)) {
                $file->addWarning(Audit::message('tlsdisabled', $option), $pointer, 'R3');
            }
            return;
        }

        // The class after "new": curl or \curl, not \curl\something or curl_something.
        $class = $file->findNext(Audit::empty_tokens(), $pointer + 1, null, true);
        if ($class !== false && $tokens[$class]['code'] === T_NS_SEPARATOR) {
            $class = $file->findNext(Audit::empty_tokens(), $class + 1, null, true);
        }
        if ($class === false || $tokens[$class]['code'] !== T_STRING || strtolower($tokens[$class]['content']) !== 'curl') {
            return;
        }
        $after = $file->findNext(Audit::empty_tokens(), $class + 1, null, true);
        if ($after !== false && $tokens[$after]['code'] === T_NS_SEPARATOR) {
            return;
        }

        // Search to the end of the innermost function or closure (or the end of the file).
        $end = count($tokens) - 1;
        foreach (array_reverse($tokens[$pointer]['conditions'], true) as $scope => $code) {
            if (in_array($code, [T_FUNCTION, T_CLOSURE], true) && isset($tokens[$scope]['scope_closer'])) {
                $end = $tokens[$scope]['scope_closer'];
                break;
            }
        }
        for ($i = $class + 1; $i <= $end; $i++) {
            // Skip nested function and closure bodies: verification set in another execution
            // scope cannot switch checking on for this client.
            if (
                in_array($tokens[$i]['code'], [T_FUNCTION, T_CLOSURE, T_FN], true)
                    && isset($tokens[$i]['scope_closer'])
            ) {
                $i = $tokens[$i]['scope_closer'];
                continue;
            }
            if (
                $this->option_name($tokens[$i]) === 'verifypeer'
                    && in_array($this->option_value($file, $i), ['true', '1'], true)
            ) {
                return;
            }
        }
        $file->addWarning(Audit::message('curlnoverify'), $pointer, 'R3');
    }

    /**
     * Which TLS option a token names, if any.
     *
     * @param array $token a token.
     * @return string|null 'verifypeer', 'verifyhost', 'verify_peer', 'verify_peer_name', 'allow_self_signed' or null.
     */
    protected function option_name(array $token): ?string {
        if ($token['code'] !== T_CONSTANT_ENCAPSED_STRING && $token['code'] !== T_STRING) {
            return null;
        }
        $name = strtoupper(trim($token['content'], '\'"'));
        if (isset(self::CURL_OPTIONS[$name])) {
            return self::CURL_OPTIONS[$name];
        }
        return ($token['code'] === T_CONSTANT_ENCAPSED_STRING) ? (self::STREAM_OPTIONS[$name] ?? null) : null;
    }

    /**
     * The literal value given to an option: ['KEY' => value], $o['KEY'] = value or curl_setopt($h, KEY, value).
     *
     * @param File $file the file being checked.
     * @param int $pointer position of the option name.
     * @return string|null lower-case literal ('true', 'false', '0', '1', ...), or null when not a literal.
     */
    protected function option_value(File $file, int $pointer): ?string {
        $tokens = $file->getTokens();
        $next = $file->findNext(Audit::empty_tokens(), $pointer + 1, null, true);
        if ($next !== false && $tokens[$next]['code'] === T_CLOSE_SQUARE_BRACKET) {
            // Array-element access ($o['KEY']): only an assignment sets the option. A read used
            // as an argument ($o['KEY'], ...) must not be treated as a value.
            $next = $file->findNext(Audit::empty_tokens(), $next + 1, null, true);
            if ($next === false || $tokens[$next]['code'] !== T_EQUAL) {
                return null;
            }
        } else if ($next !== false && $tokens[$next]['code'] === T_COMMA) {
            // A bare option name before a comma is an option/value pair only inside curl_setopt().
            if (!$this->in_curl_setopt_call($file, $pointer)) {
                return null;
            }
        } else if ($next === false || !in_array($tokens[$next]['code'], [T_DOUBLE_ARROW, T_EQUAL], true)) {
            return null;
        }
        $value = $file->findNext(Audit::empty_tokens(), $next + 1, null, true);
        if ($value === false || !in_array($tokens[$value]['code'], [T_TRUE, T_FALSE, T_LNUMBER, T_STRING], true)) {
            return null;
        }
        return strtolower($tokens[$value]['content']);
    }

    /**
     * Whether the option name token is an argument directly inside a curl_setopt() call.
     *
     * @param File $file the file being checked.
     * @param int $pointer position of the option name.
     * @return bool true when the innermost enclosing call is curl_setopt().
     */
    protected function in_curl_setopt_call(File $file, int $pointer): bool {
        $tokens = $file->getTokens();
        if (empty($tokens[$pointer]['nested_parenthesis'])) {
            return false;
        }
        $opens = array_keys($tokens[$pointer]['nested_parenthesis']);
        $open = (int) end($opens);
        $name = $file->findPrevious(Audit::empty_tokens(), $open - 1, null, true);
        return $name !== false
            && $tokens[$name]['code'] === T_STRING
            && strtolower($tokens[$name]['content']) === 'curl_setopt';
    }
}
