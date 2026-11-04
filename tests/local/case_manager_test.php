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

namespace local_integritycase\local;

use local_integritycase\event\case_created;
use local_integritycase\event\case_updated;

/**
 * Tests for opening and moving cases.
 *
 * @package    local_integritycase
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_integritycase\local\case_manager
 */
final class case_manager_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;
    /** @var \stdClass */
    private \stdClass $teacher;
    /** @var \stdClass */
    private \stdClass $student;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->teacher = $gen->create_and_enrol($this->course, 'editingteacher', ['firstname' => 'Tess']);
        $this->student = $gen->create_and_enrol($this->course, 'student', ['firstname' => 'Sam']);
    }

    /**
     * Open a case as the teacher.
     *
     * @return \stdClass
     */
    private function open(): \stdClass {
        $this->setUser($this->teacher);
        return case_manager::open((int) $this->course->id, (int) $this->student->id, 'Two paragraphs match a 2019 essay.');
    }

    public function test_open_creates_a_hidden_case_with_an_audit_event(): void {
        $sink = $this->redirectEvents();
        $case = $this->open();
        $this->assertSame('reported', $case->stage);
        $this->assertSame(0, (int) $case->studentvisible);
        $this->assertEquals($this->teacher->id, $case->handlerid);
        $this->assertEquals($this->teacher->id, $case->createdby);

        $events = case_manager::events($case, false);
        $this->assertCount(1, $events);
        $this->assertSame('created', reset($events)->action);
        $this->assertSame([], case_manager::events($case, true));

        $logged = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof case_created));
        $this->assertCount(1, $logged);
        $this->assertEquals($this->student->id, $logged[0]->relateduserid);
    }

    public function test_open_needs_manage(): void {
        $this->setUser($this->student);
        $this->expectException(\required_capability_exception::class);
        case_manager::open((int) $this->course->id, (int) $this->student->id, 'x');
    }

    public function test_open_needs_an_enrolled_student(): void {
        $outsider = $this->getDataGenerator()->create_user();
        $this->setUser($this->teacher);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('not enrolled');
        case_manager::open((int) $this->course->id, (int) $outsider->id, 'x');
    }

    public function test_open_checks_the_activity_belongs_to_the_course(): void {
        $other = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $other->id]);
        $this->setUser($this->teacher);
        $this->expectException(\moodle_exception::class);
        case_manager::open((int) $this->course->id, (int) $this->student->id, 'x', (int) $assign->cmid);
    }

    public function test_open_with_an_activity_and_a_handler(): void {
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id]);
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($this->teacher);
        $case = case_manager::open((int) $this->course->id, (int) $this->student->id, 'x', (int) $assign->cmid, (int) $other->id);
        $this->assertEquals($assign->cmid, $case->cmid);
        $this->assertEquals($other->id, $case->handlerid);
    }

    public function test_handler_must_be_staff_in_the_course(): void {
        $this->setUser($this->teacher);
        $this->expectException(\moodle_exception::class);
        case_manager::open((int) $this->course->id, (int) $this->student->id, 'x', 0, (int) $this->student->id);
    }

    public function test_move_follows_the_rules(): void {
        $case = $this->open();
        $this->expectException(\moodle_exception::class);
        case_manager::move((int) $case->id, 'decided', '', 'warning');
    }

    public function test_awaiting_response_reveals_the_case_and_notifies_the_student(): void {
        $case = $this->open();
        case_manager::move((int) $case->id, 'investigating', 'Checking the sources.');
        $sink = $this->redirectMessages();
        $case = case_manager::move((int) $case->id, 'awaitingresponse', 'Please explain how you wrote section 2.');
        $this->assertSame(1, (int) $case->studentvisible);

        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertEquals($this->student->id, $messages[0]->useridto);
        $this->assertSame('casestudent', $messages[0]->eventtype);

        // The student sees the request, not the investigation note before it.
        $seen = array_column(case_manager::events($case, true), 'note');
        $this->assertSame(['Please explain how you wrote section 2.'], $seen);
    }

    public function test_moves_before_reveal_send_nothing(): void {
        $case = $this->open();
        $sink = $this->redirectMessages();
        case_manager::move((int) $case->id, 'investigating');
        case_manager::move((int) $case->id, 'closed', 'False alarm.');
        $this->assertSame(0, $sink->count());
        $this->assertSame([], case_manager::for_student((int) $this->course->id, (int) $this->student->id));
    }

    public function test_decision_needs_a_valid_outcome(): void {
        $case = $this->open();
        case_manager::move((int) $case->id, 'investigating');
        try {
            case_manager::move((int) $case->id, 'decided');
            $this->fail('Decided without an outcome.');
        } catch (\moodle_exception $e) {
            $this->assertSame('outcomerequired', $e->errorcode);
        }
        $this->expectException(\moodle_exception::class);
        case_manager::move((int) $case->id, 'decided', '', 'expelled');
    }

    public function test_decide_close_and_reopen(): void {
        $case = $this->open();
        case_manager::move((int) $case->id, 'investigating');
        $case = case_manager::move((int) $case->id, 'decided', 'Cited sources were missing.', 'warning');
        $this->assertSame('warning', $case->outcome);
        $this->assertSame(1, (int) $case->studentvisible);

        $case = case_manager::move((int) $case->id, 'closed');
        $this->assertGreaterThan(0, (int) $case->timeclosed);
        $this->assertSame('warning', $case->outcome, 'Closing keeps the decision.');

        $case = case_manager::move((int) $case->id, 'investigating', 'New evidence.');
        $this->assertSame(0, (int) $case->timeclosed);
        $this->assertNull($case->outcome, 'Reopening clears the decision until a new one is made.');
    }

    public function test_move_logs_an_update(): void {
        $case = $this->open();
        $sink = $this->redirectEvents();
        case_manager::move((int) $case->id, 'investigating');
        $logged = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof case_updated));
        $this->assertCount(1, $logged);
        $this->assertSame('investigating', $logged[0]->other['stage']);
    }

    public function test_private_notes_never_reach_the_student(): void {
        $case = $this->open();
        case_manager::move((int) $case->id, 'investigating');
        case_manager::move((int) $case->id, 'awaitingresponse', 'Tell us about section 2.');
        case_manager::add_note((int) $case->id, 'Same wording as last year, check with the module lead.');
        $staff = array_column(case_manager::events(case_manager::get((int) $case->id), false), 'note');
        $this->assertContains('Same wording as last year, check with the module lead.', $staff);
        $student = array_column(case_manager::events(case_manager::get((int) $case->id), true), 'note');
        $this->assertNotContains('Same wording as last year, check with the module lead.', $student);
    }

    public function test_student_responds_and_the_handler_hears(): void {
        $case = $this->open();
        case_manager::move((int) $case->id, 'investigating');
        case_manager::move((int) $case->id, 'awaitingresponse', 'Tell us about section 2.');
        $this->setUser($this->student);
        $sink = $this->redirectMessages();
        case_manager::respond((int) $case->id, 'I used my notes from the 2019 module.');
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertEquals($this->teacher->id, $messages[0]->useridto);
        $this->assertSame('casehandler', $messages[0]->eventtype);
        $this->assertSame('awaitingresponse', case_manager::get((int) $case->id)->stage);
        $this->assertContains('I used my notes from the 2019 module.', array_column(
            case_manager::events(case_manager::get((int) $case->id), true),
            'note'
        ));
    }

    public function test_student_can_only_respond_when_asked(): void {
        $case = $this->open();
        $this->setUser($this->student);
        $this->expectException(\moodle_exception::class);
        case_manager::respond((int) $case->id, 'x');
    }

    public function test_another_student_cannot_respond(): void {
        $case = $this->open();
        case_manager::move((int) $case->id, 'investigating');
        case_manager::move((int) $case->id, 'awaitingresponse');
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($other);
        $this->expectException(\moodle_exception::class);
        case_manager::respond((int) $case->id, 'x');
    }

    public function test_appeal_after_a_decision(): void {
        $case = $this->open();
        case_manager::move((int) $case->id, 'investigating');
        case_manager::move((int) $case->id, 'decided', '', 'markreduced');
        $this->setUser($this->student);
        $sink = $this->redirectMessages();
        $case = case_manager::appeal((int) $case->id, 'The matching text is my own earlier work.');
        $this->assertSame('appealed', $case->stage);
        $this->assertSame(1, $sink->count());
        $this->setUser($this->teacher);
        $this->assertSame(['decided', 'closed'], workflow::next_stages($case->stage));
    }

    public function test_cannot_appeal_before_a_decision(): void {
        $case = $this->open();
        case_manager::move((int) $case->id, 'investigating');
        case_manager::move((int) $case->id, 'awaitingresponse');
        $this->setUser($this->student);
        $this->expectException(\moodle_exception::class);
        case_manager::appeal((int) $case->id, 'x');
    }

    public function test_lists_for_staff_and_student(): void {
        $first = $this->open();
        $second = $this->open();
        case_manager::move((int) $second->id, 'investigating');
        case_manager::move((int) $second->id, 'decided', '', 'nocase');
        $this->assertCount(2, case_manager::for_course((int) $this->course->id));
        $mine = case_manager::for_student((int) $this->course->id, (int) $this->student->id);
        $this->assertSame([(int) $second->id], array_map('intval', array_keys($mine)));
        $this->assertNotEmpty($first);
    }

    public function test_can_view(): void {
        $case = $this->open();
        $this->assertTrue(case_manager::can_view($case));
        $this->setUser($this->student);
        $this->assertFalse(case_manager::can_view($case), 'Hidden while staff investigate.');
        $this->setUser($this->teacher);
        case_manager::move((int) $case->id, 'investigating');
        $case = case_manager::move((int) $case->id, 'awaitingresponse');
        $this->setUser($this->student);
        $this->assertTrue(case_manager::can_view($case));
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'student'));
        $this->assertFalse(case_manager::can_view($case));
    }

    public function test_evidence_is_saved_and_logged_privately(): void {
        global $USER;
        $case = $this->open();
        $draftid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id, 'component' => 'user', 'filearea' => 'draft',
            'itemid' => $draftid, 'filepath' => '/', 'filename' => 'similarity.pdf',
        ], 'pdf');
        case_manager::save_evidence((int) $case->id, $draftid);
        $files = get_file_storage()->get_area_files(
            \context_course::instance($this->course->id)->id,
            'local_integritycase',
            'evidence',
            $case->id,
            'filename',
            false
        );
        $this->assertSame(['similarity.pdf'], array_values(array_map(fn($f) => $f->get_filename(), $files)));
        $actions = array_column(case_manager::events(case_manager::get((int) $case->id), false), 'action');
        $this->assertSame(['created', 'evidence'], $actions);
        $this->setUser($this->student);
        $this->expectException(\required_capability_exception::class);
        case_manager::save_evidence((int) $case->id, $draftid);
    }
}
