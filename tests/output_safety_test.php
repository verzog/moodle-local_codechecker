<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_codechecker;

/**
 * S5/S10: hostile values from the code being checked stay inert in the report.
 *
 * The checker prints file paths, lines of source code and phpcs messages (which quote that code)
 * from whatever plugin it is pointed at, so each of them is untrusted. Keep the payload list
 * shared; add to it whenever an audit or bug report finds a new bypass.
 *
 * @package    local_codechecker
 * @category   test
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_codechecker_renderer::class)]
#[\PHPUnit\Framework\Attributes\CoversFunction('local_codechecker_get_line_of_code')]
final class output_safety_test extends \advanced_testcase {
    /** @var string The fixture whose lines are the payloads below, one per line. */
    private const FIXTURE = 'local/codechecker/tests/fixtures/hostile.txt';

    /** @var string Markup that must never reach the page as a tag. */
    private const TAGS = '/<(script|img|svg|style)\b/i';

    /**
     * Load the plugin's function library.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/local/codechecker/locallib.php');
    }

    /**
     * Payloads known to defeat naive filters (the same lines as tests/fixtures/hostile.txt).
     *
     * @return array Test cases.
     */
    public static function payload_provider(): array {
        return [
            'Nested style close (single-pass strip bypass)' => ['<</style/style><script>alert(1)</script>'],
            'Upper-case close tag' => ['</STYLE><script>alert(1)</script>'],
            'Script close' => ['</script><script>alert(1)</script>'],
            'Image onerror' => ['<img src=x onerror=alert(1)>'],
            'Attribute breakout' => ['"><svg onload=alert(1)>'],
            'Entity-encoded markup' => ['&lt;img src=x onerror=alert(1)&gt;'],
        ];
    }

    /**
     * The plugin's renderer.
     *
     * @return \local_codechecker_renderer
     */
    private function renderer(): \local_codechecker_renderer {
        global $PAGE;
        $PAGE->set_url('/local/codechecker/index.php');
        return $PAGE->get_renderer('local_codechecker');
    }

    /**
     * A file name from the checked code is escaped in the list of files, with and without problems.
     *
     * @param string $payload Hostile value.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('payload_provider')]
    public function test_file_names_are_escaped(string $payload): void {
        $this->resetAfterTest();
        $output = $this->renderer();
        $this->assertDoesNotMatchRegularExpression(self::TAGS, $output->summary_line(1, 'local/x/' . $payload, '2 errors'));
        $this->assertDoesNotMatchRegularExpression(self::TAGS, $output->summary_line(1, 'local/x/' . $payload, ''));
    }

    /**
     * A path typed into the form is escaped when it is reported as invalid.
     *
     * @param string $payload Hostile value.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('payload_provider')]
    public function test_invalid_path_is_escaped(string $payload): void {
        $this->resetAfterTest();
        $this->assertDoesNotMatchRegularExpression(self::TAGS, $this->renderer()->invald_path_message($payload));
    }

    /**
     * Results in the audit checklist are escaped.
     *
     * @param string $payload Hostile value.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('payload_provider')]
    public function test_audit_results_are_escaped(string $payload): void {
        $this->resetAfterTest();
        $rows = [[
            'id' => 'S5',
            'group' => 'security',
            'type' => 'auto',
            'description' => $payload,
            'method' => $payload,
            'status' => 'fail',
            'results' => [$payload],
        ]];
        $this->assertDoesNotMatchRegularExpression(self::TAGS, $this->renderer()->audit_checklist($rows, true));
    }

    /**
     * The full report escapes the checked code's lines, its phpcs messages and the sniff names shown with them.
     */
    public function test_report_escapes_code_messages_and_sources(): void {
        global $CFG;
        $this->resetAfterTest();
        $payloads = array_column(self::payload_provider(), 0);
        $this->assertSame(
            $payloads,
            file($CFG->dirroot . '/' . self::FIXTURE, FILE_IGNORE_NEW_LINES),
            'The fixture must hold the same payloads, one per line, so every line of code shown is hostile.'
        );

        $problems = '';
        foreach ($payloads as $index => $payload) {
            $xmlsafe = htmlspecialchars($payload, ENT_XML1 | ENT_QUOTES);
            $problems .= '<error line="' . ($index + 1) . '" column="1" severity="5" source="moodle.Test.' . $xmlsafe . '">' .
                $xmlsafe . '</error>';
        }
        $name = htmlspecialchars($CFG->dirroot . '/' . self::FIXTURE, ENT_XML1 | ENT_QUOTES);
        $xml = new \SimpleXMLElement(
            '<phpcs><file name="' . $name . '" errors="' . count($payloads) . '" warnings="0">' . $problems . '</file></phpcs>'
        );

        $html = $this->renderer()->report($xml, count($payloads), 0, true);
        $this->assertStringContainsString('resultfile', $html, 'The file with problems is listed in the details.');
        $this->assertDoesNotMatchRegularExpression(self::TAGS, $html);
    }
}
