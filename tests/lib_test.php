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

namespace local_integritycase;

use local_integritycase\form\{evidence_form, move_form, open_form, text_form};
use local_integritycase\local\case_manager;

/**
 * Tests for navigation, evidence file access and the forms.
 *
 * @package    local_integritycase
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::local_integritycase_extend_navigation_course
 * @covers     ::local_integritycase_pluginfile
 * @covers     \local_integritycase\form\open_form
 * @covers     \local_integritycase\form\move_form
 * @covers     \local_integritycase\form\text_form
 * @covers     \local_integritycase\form\evidence_form
 */
final class lib_test extends \advanced_testcase {
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/local/integritycase/lib.php');
    }

    /**
     * Run the navigation hook for the current user.
     *
     * @param \stdClass $course
     * @return string[] node texts added
     */
    private function nav(\stdClass $course): array {
        $root = \navigation_node::create('root');
        local_integritycase_extend_navigation_course($root, $course, \context_course::instance($course->id));
        return array_map(fn($n) => (string) $n->text, iterator_to_array($root->children));
    }

    public function test_navigation(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($teacher);
        $this->assertSame(['Integrity cases'], array_values($this->nav($course)));
        $this->setUser($student);
        $this->assertSame([], array_values($this->nav($course)), 'No link while the student has no visible case.');
        $this->setUser($teacher);
        $case = case_manager::open((int) $course->id, (int) $student->id, 'x');
        case_manager::move((int) $case->id, 'investigating');
        case_manager::move((int) $case->id, 'awaitingresponse');
        $this->setUser($student);
        $this->assertSame(['Your integrity cases'], array_values($this->nav($course)));
    }

    public function test_pluginfile_refuses_other_areas_and_students(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $this->assertFalse(local_integritycase_pluginfile($course, null, \context_system::instance(), 'evidence', [1, 'a'], true));
        $this->assertFalse(local_integritycase_pluginfile($course, null, $context, 'other', [1, 'a'], true));
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        local_integritycase_pluginfile($course, null, $context, 'evidence', [1, 'a'], true);
    }

    public function test_pluginfile_missing_file_or_wrong_course(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->getDataGenerator()->enrol_user($teacher->id, $other->id, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($teacher);
        $case = case_manager::open((int) $course->id, (int) $student->id, 'x');
        $context = \context_course::instance($course->id);
        $this->assertFalse(local_integritycase_pluginfile($course, null, $context, 'evidence', [$case->id, 'none.pdf'], true));
        $this->assertFalse(local_integritycase_pluginfile(
            $other,
            null,
            \context_course::instance($other->id),
            'evidence',
            [$case->id, 'none.pdf'],
            true
        ));
    }

    public function test_forms_build_and_validate(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $url = new \moodle_url('/local/integritycase/view.php');
        $open = new open_form($url, ['students' => [2 => 'Sam'], 'activities' => [], 'handlers' => [2 => 'Tess']]);
        $this->assertStringContainsString('name="summary"', $open->render());
        $move = new move_form($url, ['stages' => ['decided' => 'decided']]);
        $this->assertArrayHasKey('outcome', $move->validation(['stage' => 'decided', 'outcome' => ''], []));
        $this->assertSame([], $move->validation(['stage' => 'decided', 'outcome' => 'warning'], []));
        $this->assertSame([], $move->validation(['stage' => 'closed'], []));
        $text = new text_form($url, ['action' => 'note', 'header' => 'h', 'label' => 'l', 'button' => 'b']);
        $this->assertStringContainsString('name="text"', $text->render());
        $evidence = new evidence_form($url);
        $this->assertStringContainsString('name="evidence"', $evidence->render());
    }
}
