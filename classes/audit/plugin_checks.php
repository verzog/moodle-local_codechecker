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
 * Plugin-level audit checks: files every plugin should (or may) have.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_codechecker\audit;

/**
 * Plugin-level audit checks (S17, S20, Q4, Q5, Q6).
 *
 * These look only at which files a plugin has. They confirm presence, not quality:
 * the checklist marks what still needs a manual review. thirdpartylibs.xml is
 * parsed as data and never executed, and its locations must stay inside the plugin.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugin_checks {
    /** @var string[] Folders that usually hold bundled third-party code. */
    const THIRDPARTY_FOLDERS = ['node_modules', 'thirdparty', 'third_party', 'vendor'];

    /**
     * Find the plugin root folders among the checked paths.
     *
     * A checked folder is a plugin root when it holds a version.php. Single files
     * and folders inside a plugin are not, so plugin-level checks do not run for them.
     *
     * @param string[] $paths full paths that were checked.
     * @return string[] the plugin root folders.
     */
    public static function find_plugin_roots(array $paths): array {
        $roots = [];
        foreach ($paths as $path) {
            $path = rtrim(str_replace('\\', '/', $path), '/');
            if (is_dir($path) && is_file($path . '/version.php')) {
                $roots[] = $path;
            }
        }
        return array_values(array_unique($roots));
    }

    /**
     * Run every plugin-level check on one plugin.
     *
     * @param string $root full path to the plugin root folder.
     * @return array check ID => list of ['status' => pass|warning|fail|info, 'message' => string].
     */
    public static function run(string $root): array {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        return [
            'S17' => [self::privacy_provider($root)],
            'S20' => self::thirdparty($root),
            'Q4' => [self::tests($root)],
            'Q5' => [self::uninstall($root)],
            'Q6' => [self::readme($root)],
        ];
    }

    /**
     * S17: the plugin has classes/privacy/provider.php.
     *
     * @param string $root plugin root folder.
     * @return array the result.
     */
    protected static function privacy_provider(string $root): array {
        if (is_file($root . '/classes/privacy/provider.php')) {
            return self::result('pass', 'privacy_present');
        }
        return self::result('fail', 'privacy_missing');
    }

    /**
     * Q4: the plugin has PHPUnit tests under tests/.
     *
     * @param string $root plugin root folder.
     * @return array the result.
     */
    protected static function tests(string $root): array {
        if (is_dir($root . '/tests')) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root . '/tests', \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($files as $file) {
                if (substr($file->getFilename(), -9) === '_test.php') {
                    return self::result('pass', 'tests_present');
                }
            }
        }
        return self::result('warning', 'tests_missing');
    }

    /**
     * Q5: whether the plugin has the optional db/uninstall.php hook.
     *
     * @param string $root plugin root folder.
     * @return array the result.
     */
    protected static function uninstall(string $root): array {
        if (is_file($root . '/db/uninstall.php')) {
            return self::result('info', 'uninstall_present');
        }
        return self::result('info', 'uninstall_absent');
    }

    /**
     * Q6: the plugin has a nonempty README in its root folder.
     *
     * @param string $root plugin root folder.
     * @return array the result.
     */
    protected static function readme(string $root): array {
        foreach (scandir($root) ?: [] as $name) {
            if (preg_match('~^readme(\.(md|txt|markdown|rst))?$~i', $name) && is_file($root . '/' . $name)) {
                if (trim((string) file_get_contents($root . '/' . $name)) === '') {
                    return self::result('warning', 'readme_empty', $name);
                }
                return self::result('pass', 'readme_present', $name);
            }
        }
        return self::result('warning', 'readme_missing');
    }

    /**
     * S20: third-party libraries are declared in a valid thirdpartylibs.xml.
     *
     * @param string $root plugin root folder.
     * @return array list of results.
     */
    protected static function thirdparty(string $root): array {
        $results = [];
        $declared = [];
        $xmlfile = $root . '/thirdpartylibs.xml';
        if (is_file($xmlfile)) {
            $previous = libxml_use_internal_errors(true);
            $xml = simplexml_load_string((string) file_get_contents($xmlfile), \SimpleXMLElement::class, LIBXML_NONET);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            if ($xml === false || $xml->getName() !== 'libraries') {
                return [self::result('warning', 'thirdparty_invalid')];
            }
            foreach ($xml->library as $library) {
                $location = trim((string) $library->location);
                $name = trim((string) $library->name);
                $a = (object) ['location' => $location, 'name' => $name];
                if (!self::is_safe_location($location) || !file_exists($root . '/' . $location)) {
                    $results[] = self::result('warning', 'thirdparty_badlocation', $a);
                    continue;
                }
                $declared[] = trim($location, '/');
                $missing = [];
                foreach (['name', 'version', 'license'] as $field) {
                    if (trim((string) $library->$field) === '') {
                        $missing[] = $field;
                    }
                }
                if ($missing) {
                    $a->fields = implode(', ', $missing);
                    $results[] = self::result('warning', 'thirdparty_incomplete', $a);
                } else {
                    $a->version = trim((string) $library->version);
                    $a->license = trim((string) $library->license);
                    $results[] = self::result('info', 'thirdparty_declared', $a);
                }
            }
        }
        foreach (self::THIRDPARTY_FOLDERS as $folder) {
            if (is_dir($root . '/' . $folder) && !self::is_declared($folder, $declared)) {
                $results[] = self::result('warning', 'thirdparty_undeclared', $folder);
            }
        }
        if (!$results) {
            $results[] = self::result('pass', 'thirdparty_none');
        }
        return $results;
    }

    /**
     * Whether a thirdpartylibs.xml location stays inside the plugin.
     *
     * @param string $location the location from the XML file.
     * @return bool true for a non-empty relative path without '..' parts.
     */
    protected static function is_safe_location(string $location): bool {
        $location = str_replace('\\', '/', $location);
        if ($location === '' || $location[0] === '/' || preg_match('~^[a-z]:~i', $location)) {
            return false;
        }
        return !in_array('..', explode('/', $location), true);
    }

    /**
     * Whether a folder is covered by a declared location.
     *
     * @param string $folder folder name relative to the plugin root.
     * @param string[] $declared declared locations.
     * @return bool true if the folder, or a folder inside it, is declared.
     */
    protected static function is_declared(string $folder, array $declared): bool {
        foreach ($declared as $location) {
            if ($location === $folder || strpos($location, $folder . '/') === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build one result.
     *
     * @param string $status pass, warning, fail or info.
     * @param string $key string key without the 'auditplugin_' prefix.
     * @param mixed $a optional value(s) for the string.
     * @return array ['status' => string, 'message' => string].
     */
    protected static function result(string $status, string $key, $a = null): array {
        return ['status' => $status, 'message' => get_string('auditplugin_' . $key, 'local_codechecker', $a)];
    }
}
