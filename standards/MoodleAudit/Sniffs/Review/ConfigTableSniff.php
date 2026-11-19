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
 * Audit R1: plugin settings go through the config API, not the config tables.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace MoodleAudit\Sniffs\Review;

use MoodleAudit\Util\Audit;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHPCSUtils\Utils\PassedParameters;

/**
 * Audit R1: plugin settings are read and written through the config API.
 *
 * Reports $DB calls that name the config or config_plugins table, either as the
 * table argument (get_record('config_plugins', ...), record_exists('config', ...))
 * or as {config_plugins} / {config} in the SQL argument of a *_sql or *_select call.
 * Only those arguments are read, so the names appearing as data (a bound parameter
 * or a record value) are not reported. Named arguments are supported.
 * get_config(), set_config() and unset_config() do the same and keep the config
 * cache in step. Test code is skipped.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ConfigTableSniff implements Sniff {
    /** @var string[] The core tables that hold settings. */
    const TABLES = ['config', 'config_plugins'];

    /**
     * Register the tokens this sniff listens for.
     *
     * @return int[] token codes.
     */
    public function register() {
        return [T_VARIABLE];
    }

    /**
     * Report a $DB call on a config table.
     *
     * @param File $file the file being checked.
     * @param int $pointer position of the token.
     */
    public function process(File $file, $pointer) {
        $call = Audit::db_method_call($file, $pointer);
        if ($call === null || Audit::is_test_file($file)) {
            return;
        }
        [$name, $method] = $call;
        $table = self::table_argument($file, $method) ?? self::table_in_sql($file, $name, $method);
        if ($table !== null) {
            $file->addWarning(Audit::message('configtable', $table), $pointer, 'R1');
        }
    }

    /**
     * The config table named as the call's first (table) argument, if any.
     *
     * @param File $file the file being checked.
     * @param int $method position of the method name.
     * @return string|null the table name, or null.
     */
    protected static function table_argument(File $file, int $method): ?string {
        // Every $DB method that takes a table takes it first, as $table.
        $first = PassedParameters::getParameter($file, $method, 1, 'table');
        if ($first === false) {
            return null;
        }
        $name = trim($first['clean'], '\'"');
        return ($name !== $first['clean'] && in_array($name, self::TABLES, true)) ? $name : null;
    }

    /**
     * The config table referenced as {name} in the SQL argument of a *_sql or *_select call, if any.
     *
     * @param File $file the file being checked.
     * @param string $name the method name, in lower case.
     * @param int $method position of the method name.
     * @return string|null the table name, or null.
     */
    protected static function table_in_sql(File $file, string $name, int $method): ?string {
        if (!isset(Audit::DB_SQL_METHODS[$name])) {
            return null;
        }
        // The SQL argument is $sql in the *_sql methods and execute(), and $select in the *_select ones.
        $paramname = (substr($name, -4) === '_sql' || $name === 'execute') ? 'sql' : 'select';
        $parameter = PassedParameters::getParameter($file, $method, Audit::DB_SQL_METHODS[$name], $paramname);
        if ($parameter === false) {
            return null;
        }
        $tokens = $file->getTokens();
        $strings = [T_CONSTANT_ENCAPSED_STRING, T_DOUBLE_QUOTED_STRING, T_HEREDOC, T_NOWDOC];
        for ($i = $parameter['start']; $i <= $parameter['end']; $i++) {
            if (!in_array($tokens[$i]['code'], $strings, true)) {
                continue;
            }
            foreach (self::TABLES as $table) {
                if (strpos($tokens[$i]['content'], '{' . $table . '}') !== false) {
                    return $table;
                }
            }
        }
        return null;
    }
}
