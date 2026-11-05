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
 * Audit S6 for Mustache templates and JavaScript sources.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_codechecker\audit;

/**
 * Audit S6 for Mustache templates and JavaScript sources.
 *
 * phpcs only checks PHP, so templates and AMD sources are scanned here, line by line,
 * for <script src> / <link rel="stylesheet"> tags with an external URL and for
 * JavaScript that injects a script or stylesheet element. Test code is skipped.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class asset_scanner {
    /** @var string The phpcs-style source reported for these findings. */
    const SOURCE = 'MoodleAudit.Security.ExternalAsset.S6';

    /** @var string Pattern for JavaScript that creates a script or link element. */
    const INJECTION_PATTERN = '~createElement\(\s*[\'"\x60](script|link)[\'"\x60]\s*\)~i';

    /**
     * Scan a checked path for external script and stylesheet loads.
     *
     * @param string $path full path of a file or folder.
     * @param array $ignores ignore patterns, as for local_codechecker_find_other_files().
     * @return array list of ['file' => full path, 'line' => int, 'message' => string].
     */
    public static function scan(string $path, array $ignores): array {
        if (!class_exists(\MoodleAudit\Util\Audit::class, false)) {
            require_once(\local_codechecker\runner::audit_standard() . '/Util/Audit.php');
        }
        $files = [];
        local_codechecker_find_other_files($files, $path, $ignores, ['js', 'mustache']);
        $findings = [];
        foreach ($files as $file) {
            if (strpos(str_replace('\\', '/', $file), '/tests/') !== false) {
                continue; // Test code never loads assets into a real page.
            }
            $isjs = substr($file, -3) === '.js';
            foreach (file($file) ?: [] as $index => $line) {
                if (\MoodleAudit\Util\Audit::loads_external_asset($line)) {
                    $findings[] = self::finding($file, $index + 1, 'externalasset_tag');
                } else if ($isjs && preg_match(self::INJECTION_PATTERN, $line)) {
                    $findings[] = self::finding($file, $index + 1, 'externalasset_inject');
                }
            }
        }
        return $findings;
    }

    /**
     * Add findings to a run's report as errors.
     *
     * @param \SimpleXMLElement $xml the run's report.
     * @param array $findings findings from {@see scan()}.
     */
    public static function add_to_report(\SimpleXMLElement $xml, array $findings): void {
        foreach ($findings as $finding) {
            $fileinxml = null;
            foreach ($xml->file as $candidate) {
                if ((string) $candidate['name'] === $finding['file']) {
                    $fileinxml = $candidate;
                    break;
                }
            }
            if ($fileinxml === null) {
                $fileinxml = $xml->addChild('file');
                $fileinxml->addAttribute('name', $finding['file']);
                $fileinxml->addAttribute('errors', 0);
                $fileinxml->addAttribute('warnings', 0);
            }
            $problem = $fileinxml->addChild('error', htmlspecialchars($finding['message'], ENT_XML1));
            $problem->addAttribute('line', $finding['line']);
            $problem->addAttribute('column', 0);
            $problem->addAttribute('source', self::SOURCE);
            $problem->addAttribute('severity', PHPCS_DEFAULT_ERROR_SEV);
            $fileinxml['errors'] = (int) $fileinxml['errors'] + 1;
        }
    }

    /**
     * Build one finding.
     *
     * @param string $file full path of the file.
     * @param int $line line number.
     * @param string $key string key without the 'auditmsg_' prefix.
     * @return array the finding.
     */
    protected static function finding(string $file, int $line, string $key): array {
        return ['file' => $file, 'line' => $line, 'message' => get_string('auditmsg_' . $key, 'local_codechecker')];
    }
}
