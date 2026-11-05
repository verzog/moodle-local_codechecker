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
 * The audit checklist: every check, how it is checked, and the results of a run.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_codechecker\audit;

/**
 * The audit checklist: every check, how it is checked, and the results of a run.
 *
 * Checks are one of:
 * - auto: fully checked by a MoodleAudit sniff.
 * - partial: a MoodleAudit sniff finds the clear cases; the rest needs manual review.
 * - standard: checked by a Moodle coding style sniff that runs on every check.
 * - plugin: a plugin-level file check (see {@see plugin_checks}).
 * - manual: needs a person to review it.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class checklist {
    /** @var string Prefix of every finding reported by the MoodleAudit sniffs. */
    const AUDIT_SOURCE = 'MoodleAudit.';

    /** @var string Fully checked by a MoodleAudit sniff. */
    const AUTO = 'auto';

    /** @var string Partly checked by a MoodleAudit sniff. */
    const PARTIAL = 'partial';

    /** @var string Checked by the Moodle coding style. */
    const STANDARD = 'standard';

    /** @var string Plugin-level file check. */
    const PLUGIN = 'plugin';

    /** @var string Manual review only. */
    const MANUAL = 'manual';

    /**
     * Get every check, in checklist order.
     *
     * The 'sources' are the Moodle coding style sniffs whose findings also count towards a check.
     *
     * @return array check ID => ['group' => string, 'type' => string, 'sources' => string[]].
     */
    public static function checks(): array {
        $checks = [
            'S0' => [self::MANUAL],
            'S1' => [self::STANDARD, ['moodle.Files.RequireLogin']],
            'S2' => [self::MANUAL],
            'S3' => [self::AUTO],
            'S4' => [self::PARTIAL],
            'S5' => [self::MANUAL],
            'S6' => [self::AUTO],
            'S7' => [self::PARTIAL],
            'S8' => [self::PARTIAL],
            'S9' => [self::AUTO],
            'S10' => [self::MANUAL],
            'S11' => [self::PARTIAL],
            'S12' => [self::PARTIAL],
            'S13' => [self::PARTIAL],
            'S14' => [self::PARTIAL],
            'S15' => [self::MANUAL],
            'S16' => [self::PARTIAL],
            'S17' => [self::PLUGIN],
            'S18' => [self::STANDARD, ['moodle.Files.MoodleInternal']],
            'S19' => [self::MANUAL],
            'S20' => [self::PLUGIN],
            'S21' => [self::MANUAL],
            'Q1' => [self::MANUAL],
            'Q2' => [self::STANDARD, ['moodle.Commenting.MissingDocblock']],
            'Q3' => [self::PARTIAL],
            'Q4' => [self::PLUGIN],
            'Q5' => [self::PLUGIN],
            'Q6' => [self::PLUGIN],
            'Q7' => [self::PARTIAL, ['moodle.PHP.ForbiddenFunctions']],
            'Q8' => [self::PARTIAL, ['moodle.PHP.DeprecatedFunctions']],
            'P1' => [self::PARTIAL],
            'P2' => [self::MANUAL],
            'P3' => [self::MANUAL],
            'P4' => [self::AUTO],
        ];
        $groups = ['S' => 'security', 'Q' => 'quality', 'P' => 'performance'];
        $result = [];
        foreach ($checks as $id => $check) {
            $result[$id] = [
                'group' => $groups[$id[0]],
                'type' => $check[0],
                'sources' => $check[1] ?? [],
            ];
        }
        return $result;
    }

    /**
     * Get the check ID a finding belongs to, from its phpcs source code.
     *
     * @param string $source the source, for example MoodleAudit.Security.Superglobals.S3.
     * @return string|null the check ID, or null if the finding is not part of the checklist.
     */
    public static function check_for_source(string $source): ?string {
        if (strpos($source, self::AUDIT_SOURCE) === 0) {
            $parts = explode('.', $source);
            $id = end($parts);
            return array_key_exists($id, self::checks()) ? $id : null;
        }
        foreach (self::checks() as $id => $check) {
            foreach ($check['sources'] as $prefix) {
                if ($source === $prefix || strpos($source, $prefix . '.') === 0) {
                    return $id;
                }
            }
        }
        return null;
    }

    /**
     * Count the findings of a run for each check.
     *
     * @param \SimpleXMLElement $xml the run's report.
     * @return int[] check ID => number of findings, for every check.
     */
    public static function count_findings(\SimpleXMLElement $xml): array {
        $counts = array_fill_keys(array_keys(self::checks()), 0);
        foreach ($xml->xpath('file/error|file/warning') as $problem) {
            $id = self::check_for_source((string) $problem['source']);
            if ($id !== null) {
                $counts[$id]++;
            }
        }
        return $counts;
    }

    /**
     * Build the checklist rows for a run.
     *
     * @param int[] $counts findings per check, from {@see count_findings()}.
     * @param array $plugins plugin-level results: plugin path => results from {@see plugin_checks::run()}.
     * @return array one row per check: ['id', 'group', 'type', 'description', 'method', 'status', 'results' => string[]].
     */
    public static function rows(array $counts, array $plugins): array {
        $rows = [];
        foreach (self::checks() as $id => $check) {
            $row = [
                'id' => $id,
                'group' => $check['group'],
                'type' => $check['type'],
                'description' => get_string('auditcheck_' . $id, 'local_codechecker'),
                'method' => get_string('audittype_' . $check['type'], 'local_codechecker'),
                'status' => 'info',
                'results' => [],
            ];
            if ($check['type'] === self::MANUAL) {
                $row['results'][] = get_string('auditresult_manual', 'local_codechecker');
            } else if ($check['type'] === self::PLUGIN) {
                self::add_plugin_results($row, $plugins);
            } else {
                $count = $counts[$id] ?? 0;
                $row['status'] = $count ? 'fail' : 'pass';
                $row['results'][] = $count ?
                    get_string('auditresult_findings', 'local_codechecker', $count) :
                    get_string('auditresult_none', 'local_codechecker');
                if ($check['type'] === self::PARTIAL) {
                    $row['results'][] = get_string('auditresult_partial', 'local_codechecker');
                }
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Add the plugin-level results for one check to its row.
     *
     * @param array $row the checklist row, updated in place.
     * @param array $plugins plugin path => results from {@see plugin_checks::run()}.
     */
    protected static function add_plugin_results(array &$row, array $plugins): void {
        if (!$plugins) {
            $row['results'][] = get_string('auditresult_noplugin', 'local_codechecker');
            return;
        }
        $severity = ['info' => 0, 'pass' => 1, 'warning' => 2, 'fail' => 3];
        $row['status'] = 'info';
        foreach ($plugins as $path => $results) {
            foreach ($results[$row['id']] ?? [] as $result) {
                $message = count($plugins) > 1 ? $path . ': ' . $result['message'] : $result['message'];
                $row['results'][] = $message;
                if ($severity[$result['status']] > $severity[$row['status']]) {
                    $row['status'] = $result['status'];
                }
            }
        }
    }
}
