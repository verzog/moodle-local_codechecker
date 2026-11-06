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
 * S4/S2: the check form sets a type for every field, and only site administrators reach the checker.
 *
 * The plugin has one form and no web services. advanced_testcase fails on any unexpected debugging()
 * call, so a field without setType() ("Did you remember to call setType()...") fails the form test.
 *
 * @package    local_codechecker
 * @category   test
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_codechecker_form::class)]
final class forms_and_access_test extends \advanced_testcase {
    /**
     * Load the form and the admin tree code.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/local/codechecker/locallib.php');
        require_once($CFG->libdir . '/adminlib.php');
    }

    /**
     * Forms with example submitted values (every field the form defines).
     *
     * @return array Test cases.
     */
    public static function form_provider(): array {
        return [
            'Check form' => [
                \local_codechecker_form::class,
                [
                    'path' => "local/codechecker\nlocal/codechecker/locallib.php",
                    'exclude' => 'db, backup/*1, *lib*',
                    'includewarnings' => 1,
                    'showstandard' => 0,
                    'skiplicencechecks' => 0,
                    'audit' => 1,
                ],
            ],
        ];
    }

    /**
     * Submitting the form produces no setType() debugging, and its values come back typed.
     *
     * @param string $class Form class.
     * @param array $values Submitted values.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('form_provider')]
    public function test_form_fields_have_types(string $class, array $values): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/local/codechecker/index.php');
        $class::mock_submit($values);
        $form = new $class();
        $data = $form->get_data();
        $this->assertDebuggingNotCalled();
        $this->assertNotNull($data, 'The example values must make a valid submission.');
        foreach (['includewarnings', 'showstandard', 'skiplicencechecks', 'audit'] as $checkbox) {
            $this->assertSame((bool) $values[$checkbox], (bool) $data->$checkbox);
        }
    }

    /**
     * The checker's admin page needs moodle/site:config: a user without it is refused, an admin gets in.
     */
    public function test_checker_needs_site_config(): void {
        $this->resetAfterTest();
        // The page is only added to the admin tree for users with site config, so build the tree as admin.
        $this->setAdminUser();
        $page = admin_get_root(true, false)->locate('local_codechecker');
        $this->assertInstanceOf(\admin_externalpage::class, $page);
        $this->assertSame(['moodle/site:config'], $page->req_capability);
        $this->assertTrue($page->check_access());

        $course = $this->getDataGenerator()->create_course();
        foreach (['student', 'editingteacher', 'manager'] as $role) {
            $this->setUser($this->getDataGenerator()->create_and_enrol($course, $role));
            $this->assertFalse($page->check_access(), "A {$role} must not reach the code checker.");
        }
    }
}
