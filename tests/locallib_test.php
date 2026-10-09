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
 * Tests related with local_codechecker locallib.php
 *
 * @package    local_codechecker
 * @category   test
 * @copyright  2022 onwards Eloy Lafuente (stronk7) {@link https://stronk7.com}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('local_codechecker_find_other_files')]
#[\PHPUnit\Framework\Attributes\CoversFunction('local_codechecker_check_other_file')]
#[\PHPUnit\Framework\Attributes\CoversFunction('local_codechecker_count_problems')]
#[\PHPUnit\Framework\Attributes\CoversFunction('local_codechecker_licence_sniffs')]
#[\PHPUnit\Framework\Attributes\CoversFunction('local_codechecker_pretty_path')]
#[\PHPUnit\Framework\Attributes\CoversFunction('local_codesniffer_get_ignores')]
final class locallib_test extends \basic_testcase {
    /**
     * Data provider for test_local_codechecker_find_other_files()
     */
    public static function local_codechecker_find_other_files_provider(): array {
        $defaultextensions = ['txt', 'html', 'csv'];
        return [
            'one wrong file' => [
                'path' => 'local/codechecker/tests/nonono_test.php',
                'ignores' => [],
                'extensions' => $defaultextensions,
                'matches' => [\moodle_exception::class],
                'nomatches' => [],
            ],
            'one wrong dir' => [
                'path' => 'local/codechecker/nononotests/',
                'ignores' => [],
                'extensions' => $defaultextensions,
                'matches' => [\moodle_exception::class],
                'nomatches' => [],
            ],
            'one file' => [
                'path' => 'local/codechecker/tests/locallib_test.php',
                'ignores' => [],
                'extensions' => $defaultextensions,
                'matches' => [],
                'nomatches' => [],
            ],
            'one php file' => [
                'path' => 'local/codechecker/tests/locallib_test.php',
                'ignores' => [],
                'extensions' => ['php'],
                'matches' => ['local/codechecker/tests/locallib_test.php'],
                'nomatches' => [],
            ],
            'one dir' => [
                'path' => 'local/codechecker/tests',
                'ignores' => [],
                'extensions' => $defaultextensions,
                'matches' => ['one.txt', 'two.txt'],
                'nomatches' => [],
            ],
            'one dir with php files' => [
                'path' => 'local/codechecker/tests',
                'ignores' => [],
                'extensions' => array_merge($defaultextensions, ['php']),
                'matches' => ['locallib_test.php', 'one.txt', 'two.txt'],
                'nomatches' => [],
            ],
            'one dir one ignored file' => [
                'path' => 'local/codechecker/tests',
                'ignores' => ['one.txt'],
                'extensions' => $defaultextensions,
                'matches' => ['two.txt'],
                'nomatches' => ['one.txt'],
            ],
            'one dir many ignored files' => [
                'path' => 'local/codechecker/tests',
                'ignores' => ['one.txt', 'three.txt'],
                'extensions' => $defaultextensions,
                'matches' => ['two.txt'],
                'nomatches' => ['one.txt', 'three.txt'],
            ],
            'one dir one wildchar ignored' => [
                'path' => 'local/codechecker/tests',
                'ignores' => ['fixtures*three'],
                'extensions' => $defaultextensions,
                'matches' => ['one.txt', 'two.txt'],
                'nomatches' => ['three.txt'],
            ],
            'one dir regex-quoted ignore' => [
                'path' => 'local/codechecker/tests',
                'ignores' => [preg_quote('fixtures/three.txt')],
                'extensions' => $defaultextensions,
                'matches' => ['one.txt', 'two.txt'],
                'nomatches' => ['three.txt'],
            ],
        ];
    }

    /**
     * Verify local_codechecker_find_other_files() behaviour.
     *
     * @param string $path dirroot relative path to be examined (file or folder).
     * @param string[] $ignores substring-matching, accepting wild-chars array strings to ignore.
     * @param string[] $extensions list of extensions to look for.
     * @param string[] $matches list of substring-matching strings expected to be in the results.
     * @param string[] $nomatches list of substring-matching strings not expected to be in the results.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('local_codechecker_find_other_files_provider')]
    public function test_local_codechecker_find_other_files(
        string $path,
        array $ignores,
        array $extensions,
        array $matches,
        array $nomatches
    ): void {

        global $CFG;
        require_once(__DIR__ . '/../locallib.php');

        $results = [];

        // If matches has moodle_exception, then we expect it to happen.
        if ($matches && $matches[0] === \moodle_exception::class) {
            $this->expectException(\moodle_exception::class);
        }

        // Look for results.
        local_codechecker_find_other_files($results, $CFG->dirroot . '/' . $path, $ignores, $extensions);

        // Empty results, means we expect also empty matches.
        if (empty($results)) {
            $this->assertSame($matches, $results);
            return; // We have ended, no more assertions for this case.
        }

        // We have results, let's check them.
        $results = implode(' ', $results);

        // Let's perform simple substring matching, that's enough.
        foreach ($matches as $match) {
            $this->assertStringContainsString($match, $results);
        }
        foreach ($nomatches as $nomatch) {
            $this->assertStringNotContainsString($nomatch, $results);
        }
    }

    /**
     * Verify test_local_codechecker_check_other_file() behaviour.
     */
    public function test_local_codechecker_check_other_file(): void {

        require_once(__DIR__ . '/../locallib.php');

        // Verify that lf files are ok.
        $xml = new \SimpleXMLElement('<xml/>');
        local_codechecker_check_other_file(__DIR__ . '/../version.php', $xml);
        $this->assertStringContainsString('errors="0" warnings="0"', $xml->asXML());

        // Verify crlf files in /tests/fixtures/ locations are ok.
        $xml = new \SimpleXMLElement('<xml/>');
        local_codechecker_check_other_file(__DIR__ . '/fixtures/crlf.csv', $xml);
        $this->assertStringContainsString('errors="0" warnings="0"', $xml->asXML());

        // Verify crlf files not in /tests/fixtures/ locations are wrong. The file is
        // created at runtime so the plugin itself has no CRLF file outside the
        // fixtures, which would make checking local/codechecker report an error.
        $file = make_request_directory() . '/crlf.csv';
        file_put_contents($file, "f1,f2,f3\r\n1,2,3\r\n");
        $xml = new \SimpleXMLElement('<xml/>');
        local_codechecker_check_other_file($file, $xml);
        $this->assertStringContainsString('errors="1" warnings="0"', $xml->asXML());
        $this->assertStringContainsString('Windows (CRLF) line ending instead of just LF', $xml->asXML());
    }

    /**
     * Verify local_codechecker_check_other_file() copes with quotes in file names
     * and reuses a file entry that is already in the report.
     */
    public function test_local_codechecker_check_other_file_quoted_name(): void {
        require_once(__DIR__ . '/../locallib.php');

        $file = make_request_directory() . "/it's \"quoted\".txt";
        file_put_contents($file, "trailing space \n");

        $xml = new \SimpleXMLElement('<xml/>');
        $existing = $xml->addChild('file');
        $existing->addAttribute('name', $file);
        $existing->addAttribute('errors', 0);
        $existing->addAttribute('warnings', 0);

        local_codechecker_check_other_file($file, $xml);

        $this->assertCount(1, $xml->file);
        $this->assertSame('1', (string) $xml->file[0]['errors']);
        $this->assertSame('Whitespace at end of line', (string) $xml->file[0]->error);
    }

    /**
     * Verify local_codechecker_count_problems() adds up every file in the report.
     */
    public function test_local_codechecker_count_problems(): void {
        require_once(__DIR__ . '/../locallib.php');

        $xml = new \SimpleXMLElement('<xml>
            <file name="a.php" errors="2" warnings="1"/>
            <file name="b.php" errors="0" warnings="0"/>
            <file name="c.txt" errors="3" warnings="4"/>
        </xml>');
        $this->assertSame([5, 5], local_codechecker_count_problems($xml));

        $this->assertSame([0, 0], local_codechecker_count_problems(new \SimpleXMLElement('<xml/>')));
    }

    /**
     * Verify the licence sniffs excluded by the "skip licence checks" option.
     */
    public function test_local_codechecker_licence_sniffs(): void {
        require_once(__DIR__ . '/../locallib.php');

        $this->assertSame(
            ['moodle.Files.BoilerplateComment', 'moodle.Commenting.FileExpectedTags'],
            local_codechecker_licence_sniffs()
        );
    }

    /**
     * Verify local_codechecker_pretty_path() strips the Moodle code root.
     */
    public function test_local_codechecker_pretty_path(): void {
        global $CFG;
        require_once(__DIR__ . '/../locallib.php');

        $this->assertSame(
            'local/codechecker/version.php',
            local_codechecker_pretty_path($CFG->dirroot . '/local/codechecker/version.php')
        );
        // PHP_CodeSniffer reports resolved paths; those must be handled too.
        $this->assertSame(
            'local/codechecker/version.php',
            local_codechecker_pretty_path(realpath($CFG->dirroot) . '/local/codechecker/version.php')
        );
    }

    /**
     * Verify local_codesniffer_get_ignores() builds the expected ignore patterns.
     */
    public function test_local_codesniffer_get_ignores(): void {
        require_once(__DIR__ . '/../locallib.php');

        $ignores = local_codesniffer_get_ignores(' *one* , , db ');

        // Extra ignores are trimmed and empty entries are dropped.
        $this->assertArrayHasKey('*one*', $ignores);
        $this->assertArrayHasKey('db', $ignores);
        $this->assertArrayNotHasKey('', $ignores);

        // Compiled JS is always ignored.
        $this->assertArrayHasKey('*/amd/build/*', $ignores);
        $this->assertArrayHasKey('*/yui/build/*', $ignores);

        // Our own bundled tools are ignored through the plugin thirdpartylibs.xml.
        $this->assertArrayHasKey(preg_quote(local_codechecker_clean_path('/local/codechecker/vendor')), $ignores);

        // Every pattern is marked as absolute.
        $this->assertSame(['absolute'], array_values(array_unique($ignores)));
    }
}
