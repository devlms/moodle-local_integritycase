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

namespace local_integritycase\output;

use local_integritycase\local\case_manager;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_integritycase\output\case_list::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_integritycase\output\case_view::class)]
/**
 * Tests for the data behind the case list and case pages.
 *
 * @package    local_integritycase
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_integritycase\output\case_list
 * @covers     \local_integritycase\output\case_view
 */
final class output_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;
    /** @var \stdClass */
    private \stdClass $teacher;
    /** @var \stdClass */
    private \stdClass $student;
    /** @var \stdClass */
    private \stdClass $other;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->teacher = $gen->create_and_enrol($this->course, 'editingteacher', ['firstname' => 'Tess', 'lastname' => 'T']);
        $this->student = $gen->create_and_enrol($this->course, 'student', ['firstname' => 'Sam', 'lastname' => 'S']);
        $this->other = $gen->create_and_enrol($this->course, 'student', ['firstname' => 'Ola', 'lastname' => 'O']);
        $this->setUser($this->teacher);
    }

    /**
     * Export a renderable for its template.
     *
     * @param \templatable $page
     * @return array
     */
    private function export(\templatable $page): array {
        global $PAGE;
        return json_decode(json_encode($page->export_for_template($PAGE->get_renderer('core'))), true);
    }

    public function test_list_filters_open_and_closed(): void {
        $open = case_manager::open($this->course->id, $this->student->id, 'Matches a 2019 essay.');
        $closed = case_manager::open($this->course->id, $this->other->id, 'False alarm.');
        case_manager::move($closed->id, 'closed', 'Checked, nothing found.');

        $cases = case_manager::for_course($this->course->id);
        $data = $this->export(new case_list($this->course, $cases, true, true, 'open', time()));
        $this->assertSame([(int) $open->id], array_map(fn($r) => (int) $r['id'], $data['rows']));
        $this->assertSame([1, 1, 2], array_column($data['filters'], 'count'));
        $this->assertTrue($data['filters'][0]['active']);
        $this->assertTrue($data['canmanage']);

        $row = $data['rows'][0];
        $this->assertSame('Sam S', $row['student']);
        $this->assertSame('Reported', $row['stage']);
        $this->assertStringContainsString('badge', $row['stagebadge']);
        $this->assertTrue($row['hidden']);
        $this->assertSame('Just now', $row['updated']);

        $data = $this->export(new case_list($this->course, $cases, true, true, 'closed', time()));
        $this->assertSame([(int) $closed->id], array_map(fn($r) => (int) $r['id'], $data['rows']));
    }

    public function test_student_list_has_no_filters_and_no_names(): void {
        case_manager::open($this->course->id, $this->student->id, 'Matches a 2019 essay.');
        $cases = case_manager::for_course($this->course->id);
        $data = $this->export(new case_list($this->course, $cases, false, false, 'open', time()));
        $this->assertSame([], $data['filters']);
        $this->assertArrayNotHasKey('student', $data['rows'][0]);
        $this->assertArrayNotHasKey('handler', $data['rows'][0]);
    }

    public function test_empty_list(): void {
        $data = $this->export(new case_list($this->course, [], true, true, 'open', time()));
        $this->assertFalse($data['hasrows']);
    }

    public function test_case_steps_follow_the_path_taken(): void {
        $case = case_manager::open($this->course->id, $this->student->id, 'Matches a 2019 essay.');
        case_manager::move($case->id, 'investigating');
        case_manager::move($case->id, 'decided', 'Warning.', 'warning');
        $case = case_manager::get($case->id);

        $data = $this->export(new case_view($case, true, [], time()));
        $states = array_column($data['steps'], 'state', 'key');
        $this->assertSame([
            'reported' => 'done',
            'investigating' => 'done',
            'awaitingresponse' => 'skipped',
            'decided' => 'current',
            'closed' => 'todo',
        ], $states);
        $this->assertSame('Decided', $data['stage']);
        $this->assertSame('educational warning', $data['outcome']);
        $this->assertSame('Sam S', $data['student']);
        $this->assertSame('Tess T', $data['handler']);
    }

    public function test_appeal_adds_a_step(): void {
        $case = case_manager::open($this->course->id, $this->student->id, 'Matches a 2019 essay.');
        case_manager::move($case->id, 'investigating');
        case_manager::move($case->id, 'awaitingresponse', 'Tell us how you wrote it.');
        case_manager::move($case->id, 'decided', 'Warning.', 'warning');
        $this->setUser($this->student);
        case_manager::appeal($case->id, 'My own earlier work.');
        $case = case_manager::get($case->id);

        $data = $this->export(new case_view($case, false, [], time()));
        $this->assertSame(
            ['reported', 'investigating', 'awaitingresponse', 'decided', 'appealed', 'closed'],
            array_column($data['steps'], 'key')
        );
        $this->assertSame('current', $data['steps'][4]['state']);
        $last = end($data['history']);
        $this->assertTrue($last['bystudent']);
        $this->assertFalse($last['private']);
    }

    public function test_staff_see_private_entries_and_forms(): void {
        $case = case_manager::open($this->course->id, $this->student->id, 'Matches a 2019 essay.');
        case_manager::add_note($case->id, 'Same wording as a past submission.');
        $case = case_manager::get($case->id);

        $data = $this->export(new case_view($case, true, [['title' => 'Next step', 'html' => '<form></form>']], time()));
        $this->assertTrue($data['hiddenfromstudent']);
        $this->assertTrue($data['history'][1]['private']);
        $this->assertTrue($data['hasforms']);
        $this->assertSame('Next step', $data['forms'][0]['title']);

        $data = $this->export(new case_view($case, false, [], time()));
        $this->assertFalse($data['hiddenfromstudent']);
        $this->assertCount(0, $data['history']);
    }
}
