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

namespace local_integritycase\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use local_integritycase\local\case_manager;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_integritycase\privacy\provider::class)]
/**
 * Tests for the privacy provider.
 *
 * @package    local_integritycase
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_integritycase\privacy\provider
 */
final class provider_test extends provider_testcase {
    /** @var \stdClass */
    private \stdClass $course;
    /** @var \stdClass */
    private \stdClass $teacher;
    /** @var \stdClass */
    private \stdClass $student;
    /** @var \stdClass */
    private \stdClass $case;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->teacher = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->student = $gen->create_and_enrol($this->course, 'student');
        $this->setUser($this->teacher);
        $this->case = case_manager::open((int) $this->course->id, (int) $this->student->id, 'Copied answers.');
        case_manager::move((int) $this->case->id, 'investigating');
        case_manager::move((int) $this->case->id, 'awaitingresponse', 'Explain question 3.');
        case_manager::add_note((int) $this->case->id, 'Private: matches a group chat.');
        $this->setUser($this->student);
        case_manager::respond((int) $this->case->id, 'We studied together.');
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => \context_course::instance($this->course->id)->id,
            'component' => 'local_integritycase',
            'filearea' => 'evidence',
            'itemid' => $this->case->id,
            'filepath' => '/',
            'filename' => 'report.txt',
        ], 'similarity 64%');
        $this->setUser();
    }

    public function test_metadata(): void {
        $items = provider::get_metadata(new \core_privacy\local\metadata\collection('local_integritycase'))->get_collection();
        $names = array_map(fn($i) => $i->get_name(), $items);
        $this->assertContains('local_integritycase', $names);
        $this->assertContains('local_integritycase_event', $names);
        $this->assertContains('core_files', $names);
        $this->assertContains('core_message', $names);
    }

    public function test_contexts_for_student_teacher_and_stranger(): void {
        $coursecontext = \context_course::instance($this->course->id);
        $this->assertSame(
            [(int) $coursecontext->id],
            array_map('intval', provider::get_contexts_for_userid((int) $this->student->id)->get_contextids())
        );
        $this->assertSame(
            [(int) $coursecontext->id],
            array_map('intval', provider::get_contexts_for_userid((int) $this->teacher->id)->get_contextids())
        );
        $stranger = $this->getDataGenerator()->create_user();
        $this->assertSame([], provider::get_contexts_for_userid((int) $stranger->id)->get_contextids());
    }

    public function test_users_in_context(): void {
        $coursecontext = \context_course::instance($this->course->id);
        $list = new userlist($coursecontext, 'local_integritycase');
        provider::get_users_in_context($list);
        $ids = array_map('intval', $list->get_userids());
        sort($ids);
        $expected = [(int) $this->teacher->id, (int) $this->student->id];
        sort($expected);
        $this->assertSame($expected, $ids);
        $list = new userlist(\context_system::instance(), 'local_integritycase');
        provider::get_users_in_context($list);
        $this->assertSame([], $list->get_userids());
    }

    public function test_export_for_the_student_includes_everything_about_them(): void {
        $coursecontext = \context_course::instance($this->course->id);
        provider::export_user_data(new approved_contextlist($this->student, 'local_integritycase', [$coursecontext->id]));
        $writer = writer::with_context($coursecontext);
        $path = [get_string('pluginname', 'local_integritycase'), $this->case->id];
        $data = $writer->get_data($path);
        $this->assertSame('Copied answers.', $data->summary);
        $this->assertSame('awaiting student response', $data->stage);
        $notes = array_column($data->history, 'note');
        $this->assertContains('We studied together.', $notes);
        $this->assertContains('Private: matches a group chat.', $notes, 'A data subject request covers staff notes about them.');
        $this->assertNotEmpty($writer->get_files($path));
    }

    public function test_export_for_the_teacher_covers_only_their_own_actions(): void {
        $coursecontext = \context_course::instance($this->course->id);
        provider::export_user_data(new approved_contextlist($this->teacher, 'local_integritycase', [$coursecontext->id]));
        $data = writer::with_context($coursecontext)->get_data([get_string('pluginname', 'local_integritycase'), $this->case->id]);
        $this->assertObjectNotHasProperty('summary', $data);
        $this->assertNotContains('We studied together.', array_column($data->history, 'note'));
        $this->assertContains('Private: matches a group chat.', array_column($data->history, 'note'));
    }

    public function test_delete_for_the_student_removes_the_case_and_files(): void {
        global $DB;
        $coursecontext = \context_course::instance($this->course->id);
        provider::delete_data_for_user(new approved_contextlist($this->student, 'local_integritycase', [$coursecontext->id]));
        $this->assertFalse($DB->record_exists('local_integritycase', []));
        $this->assertFalse($DB->record_exists('local_integritycase_event', []));
        $files = get_file_storage()->get_area_files($coursecontext->id, 'local_integritycase', 'evidence', false, '', false);
        $this->assertSame([], $files);
    }

    public function test_delete_for_staff_keeps_the_case_but_removes_their_name(): void {
        global $DB;
        $coursecontext = \context_course::instance($this->course->id);
        provider::delete_data_for_user(new approved_contextlist($this->teacher, 'local_integritycase', [$coursecontext->id]));
        $case = $DB->get_record('local_integritycase', ['id' => $this->case->id]);
        $this->assertSame(0, (int) $case->handlerid);
        $this->assertSame(0, (int) $case->createdby);
        $this->assertFalse($DB->record_exists('local_integritycase_event', ['userid' => $this->teacher->id]));
        $note = $DB->get_field('local_integritycase_event', 'note', ['userid' => $this->student->id]);
        $this->assertSame('We studied together.', $note);
        $this->assertTrue(
            $DB->record_exists('local_integritycase_event', ['userid' => 0, 'action' => 'note']),
            'The note stays for the record, without its author.'
        );
    }

    public function test_delete_users_and_whole_context(): void {
        global $DB;
        $coursecontext = \context_course::instance($this->course->id);
        provider::delete_data_for_users(new approved_userlist($coursecontext, 'local_integritycase', [$this->student->id]));
        $this->assertFalse($DB->record_exists('local_integritycase', []));

        $this->setUser($this->teacher);
        case_manager::open((int) $this->course->id, (int) $this->student->id, 'Again.');
        provider::delete_data_for_all_users_in_context(\context_system::instance());
        $this->assertTrue($DB->record_exists('local_integritycase', []), 'Other contexts are left alone.');
        provider::delete_data_for_all_users_in_context($coursecontext);
        $this->assertFalse($DB->record_exists('local_integritycase', []));
    }
}
