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
 * Shared helpers for the MoodleAudit sniffs.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace MoodleAudit\Util;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;
use PHPCSUtils\Utils\PassedParameters;

/**
 * Shared helpers for the MoodleAudit sniffs.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class Audit {
    /** @var string[] $DB methods that write to the database. */
    const DB_WRITE_METHODS = [
        'delete_records', 'delete_records_list', 'delete_records_select', 'delete_records_subquery',
        'execute', 'insert_record', 'insert_record_raw', 'insert_records', 'set_field', 'set_field_select',
        'update_record', 'update_record_raw',
    ];

    /** @var int[] $DB methods that take SQL text or an SQL WHERE fragment, with the position of that argument. */
    const DB_SQL_METHODS = [
        'count_records_select' => 2, 'count_records_sql' => 1, 'delete_records_select' => 2, 'execute' => 1,
        'get_field_select' => 3, 'get_field_sql' => 1, 'get_fieldset_select' => 3, 'get_fieldset_sql' => 1,
        'get_record_select' => 2, 'get_record_sql' => 1, 'get_records_select' => 2, 'get_records_select_menu' => 2,
        'get_records_sql' => 1, 'get_records_sql_menu' => 1, 'get_recordset_select' => 2, 'get_recordset_sql' => 1,
        'record_exists_select' => 2, 'record_exists_sql' => 1, 'set_field_select' => 4,
    ];

    /** @var string[] Functions that read request input. */
    const PARAM_FUNCTIONS = ['optional_param', 'optional_param_array', 'required_param', 'required_param_array'];

    /** @var string Pattern for a script tag that loads an external script. */
    const SCRIPT_PATTERN = '~<script\b[^>]*\bsrc\s*=\s*\\\\?["\']?\s*(https?:)?//~i';

    /** @var string Pattern for a link tag with an external URL (a stylesheet when it also has rel="stylesheet"). */
    const LINK_PATTERN = '~<link\b[^>]*\bhref\s*=\s*\\\\?["\']?\s*(https?:)?//[^>]*>~i';

    /**
     * Whether some markup loads an external script or stylesheet.
     *
     * Used by the ExternalAsset sniff for PHP code and by \local_codechecker\audit\asset_scanner
     * for Mustache templates and AMD sources, so this method must not need PHP_CodeSniffer.
     *
     * @param string $markup HTML, a PHP string or template text.
     * @return bool true if an external script or stylesheet tag is found.
     */
    public static function loads_external_asset(string $markup): bool {
        return self::external_asset_offsets($markup) !== [];
    }

    /**
     * Find where some markup loads external scripts or stylesheets.
     *
     * Tags may span several lines.
     *
     * @param string $markup HTML, a PHP string or template text.
     * @return int[] byte offset of each external script or stylesheet tag, in order.
     */
    public static function external_asset_offsets(string $markup): array {
        $offsets = [];
        preg_match_all(self::SCRIPT_PATTERN, $markup, $scripts, PREG_OFFSET_CAPTURE);
        foreach ($scripts[0] as $script) {
            $offsets[] = $script[1];
        }
        preg_match_all(self::LINK_PATTERN, $markup, $links, PREG_OFFSET_CAPTURE);
        foreach ($links[0] as $link) {
            if (stripos($link[0], 'stylesheet') !== false) {
                $offsets[] = $link[1];
            }
        }
        sort($offsets);
        return $offsets;
    }

    /**
     * Get the tokens that are whitespace or comments.
     *
     * @return array token codes as keys, as in PHP_CodeSniffer's Tokens::$emptyTokens.
     */
    public static function empty_tokens(): array {
        // phpcs:ignore moodle.NamingConventions.ValidVariableName.VariableNameLowerCase
        return Tokens::$emptyTokens;
    }

    /**
     * Get the assignment operator tokens.
     *
     * @return array token codes as keys, as in PHP_CodeSniffer's Tokens::$assignmentTokens.
     */
    public static function assignment_tokens(): array {
        // phpcs:ignore moodle.NamingConventions.ValidVariableName.VariableNameLowerCase
        return Tokens::$assignmentTokens;
    }

    /**
     * Get a message for a finding, from the local_codechecker language pack.
     *
     * The sniffs run inside Moodle, so the strings come from get_string(). When the
     * standard is run by a bare phpcs without Moodle loaded, the string key is used.
     *
     * @param string $key string key without the 'auditmsg_' prefix.
     * @param mixed $a optional value(s) for the string placeholders.
     * @return string the message.
     */
    public static function message(string $key, $a = null): string {
        if (function_exists('get_string')) {
            return get_string('auditmsg_' . $key, 'local_codechecker', $a);
        }
        return 'auditmsg_' . $key;
    }

    /**
     * Get the path of the file being checked, with forward slashes.
     *
     * @param File $file the file being checked.
     * @return string the normalised path.
     */
    public static function path(File $file): string {
        return str_replace('\\', '/', (string) $file->getFilename());
    }

    /**
     * Whether the file is test code (PHPUnit, Behat, generators or fixtures).
     *
     * @param File $file the file being checked.
     * @return bool true for test code.
     */
    public static function is_test_file(File $file): bool {
        return strpos(self::path($file), '/tests/') !== false;
    }

    /**
     * Whether the file is a command-line script, a scheduled or adhoc task, or an upgrade script.
     *
     * These never run on a web request path.
     *
     * @param File $file the file being checked.
     * @return bool true when the file does not run during a web request.
     */
    public static function is_offline_file(File $file): bool {
        $path = self::path($file);
        if (
            strpos($path, '/cli/') !== false || strpos($path, '/classes/task/') !== false ||
                substr($path, -15) === '/db/upgrade.php'
        ) {
            return true;
        }
        return self::defines_constant($file, 'CLI_SCRIPT');
    }

    /**
     * Whether the file defines the given constant with define().
     *
     * @param File $file the file being checked.
     * @param string $constant the constant name.
     * @return bool true if define('CONSTANT', ...) is found.
     */
    public static function defines_constant(File $file, string $constant): bool {
        $tokens = $file->getTokens();
        $pointer = 0;
        while (($pointer = $file->findNext(T_STRING, $pointer + 1)) !== false) {
            if (strtolower($tokens[$pointer]['content']) !== 'define' || !self::is_function_call($file, $pointer)) {
                continue;
            }
            $first = PassedParameters::getParameter($file, $pointer, 1, 'constant_name');
            if ($first !== false && trim($first['clean'], '\'"') === $constant) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether a T_STRING token is a call to a global function (not a method, class or declaration).
     *
     * @param File $file the file being checked.
     * @param int $pointer position of the T_STRING token.
     * @return bool true for a function call.
     */
    public static function is_function_call(File $file, int $pointer): bool {
        $tokens = $file->getTokens();
        $next = $file->findNext(self::empty_tokens(), $pointer + 1, null, true);
        if ($next === false || $tokens[$next]['code'] !== T_OPEN_PARENTHESIS) {
            return false;
        }
        $previous = $file->findPrevious(self::empty_tokens(), $pointer - 1, null, true);
        if ($previous === false) {
            return true;
        }
        if ($tokens[$previous]['code'] === T_NS_SEPARATOR) {
            // A fully qualified \name() call is still a global call; look before the separator.
            $previous = $file->findPrevious(self::empty_tokens(), $previous - 1, null, true);
            if ($previous === false) {
                return true;
            }
        }
        $notcalls = [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, T_CONST, T_STRING];
        return !in_array($tokens[$previous]['code'], $notcalls, true);
    }

    /**
     * Get the method name when the token is the $DB variable of a $DB->method( call.
     *
     * @param File $file the file being checked.
     * @param int $pointer position of a T_VARIABLE token.
     * @return array|null [method name in lower case, position of the method name] or null if not a $DB call.
     */
    public static function db_method_call(File $file, int $pointer): ?array {
        $tokens = $file->getTokens();
        if ($tokens[$pointer]['content'] !== '$DB') {
            return null;
        }
        $operator = $file->findNext(self::empty_tokens(), $pointer + 1, null, true);
        if ($operator === false || $tokens[$operator]['code'] !== T_OBJECT_OPERATOR) {
            return null;
        }
        $method = $file->findNext(self::empty_tokens(), $operator + 1, null, true);
        if ($method === false || $tokens[$method]['code'] !== T_STRING) {
            return null;
        }
        $open = $file->findNext(self::empty_tokens(), $method + 1, null, true);
        if ($open === false || $tokens[$open]['code'] !== T_OPEN_PARENTHESIS) {
            return null;
        }
        return [strtolower($tokens[$method]['content']), $method];
    }

    /**
     * Whether the file includes config.php (a web or CLI entry point).
     *
     * @param File $file the file being checked.
     * @return int|false position of the include statement, or false if none.
     */
    public static function config_inclusion(File $file) {
        $tokens = $file->getTokens();
        $includes = [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE];
        $pointer = 0;
        while (($pointer = $file->findNext($includes, $pointer + 1)) !== false) {
            $end = $file->findEndOfStatement($pointer);
            for ($i = $pointer; $i <= $end; $i++) {
                if (
                    in_array($tokens[$i]['code'], [T_CONSTANT_ENCAPSED_STRING, T_DOUBLE_QUOTED_STRING], true) &&
                        preg_match('~config\.php[\'"]$~', $tokens[$i]['content'])
                ) {
                    return $pointer;
                }
            }
        }
        return false;
    }
}
