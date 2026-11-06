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

use local_codechecker\privacy\provider;

/**
 * S17: the plugin stores no personal data, and a new adhoc task can't slip past the privacy review.
 *
 * The plugin is a null provider with no tasks today. The first test fails when an adhoc task is added
 * without a decision; a task that carries a user id then needs a real provider and an erasure test
 * (see moodle-security-ci's templates/tests/privacy_test.php).
 *
 * @package    local_codechecker
 * @category   test
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class privacy_test extends \core_privacy\tests\provider_testcase {
    /** @var string[] Adhoc tasks whose custom data includes a user id and must be erased. */
    private const PERSONAL_TASKS = [];

    /** @var string[] Adhoc tasks reviewed and confirmed to carry no personal data. */
    private const NON_PERSONAL_TASKS = [];

    /**
     * Every adhoc task class must be classified as personal or non-personal.
     */
    public function test_every_adhoc_task_is_classified(): void {
        $classes = \core_component::get_component_classes_in_namespace('local_codechecker', 'task');
        $unclassified = [];
        foreach (array_keys($classes) as $class) {
            if (!is_subclass_of($class, \core\task\adhoc_task::class)) {
                continue;
            }
            if (!in_array($class, self::PERSONAL_TASKS, true) && !in_array($class, self::NON_PERSONAL_TASKS, true)) {
                $unclassified[] = $class;
            }
        }
        $this->assertSame([], $unclassified, 'New adhoc tasks: add each to PERSONAL_TASKS or NON_PERSONAL_TASKS.');
    }

    /**
     * The plugin declares that it stores nothing, with a reason shown to site administrators.
     */
    public function test_null_provider_has_a_reason(): void {
        $this->assertContains(\core_privacy\local\metadata\null_provider::class, class_implements(provider::class));
        $this->assertSame([], self::PERSONAL_TASKS, 'A task with personal data needs a real provider, not a null one.');
        $reason = provider::get_reason();
        $this->assertTrue(get_string_manager()->string_exists($reason, 'local_codechecker'));
        $this->assertNotEmpty(get_string($reason, 'local_codechecker'));
    }
}
