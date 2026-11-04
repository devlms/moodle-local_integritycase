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

use context_course;
use core_user;
use local_integritycase\event\case_created;
use local_integritycase\event\case_updated;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Opens cases and moves them through the workflow, keeping an audit trail of every action.
 *
 * Every write checks the capability of the current user and records an event row. Pages call these
 * methods; they never write to the tables themselves.
 *
 * @package    local_integritycase
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class case_manager {
    /** @var string Case table. */
    public const TABLE = 'local_integritycase';
    /** @var string Audit trail table. */
    public const EVENTS = 'local_integritycase_event';

    /**
     * Open a case about a student in a course.
     *
     * @param int $courseid
     * @param int $studentid Must be enrolled in the course.
     * @param string $summary What was found, plain text.
     * @param int $cmid Related activity in the course, 0 for none.
     * @param int|null $handlerid Staff member handling it; the current user when null.
     * @return stdClass The case.
     */
    public static function open(int $courseid, int $studentid, string $summary, int $cmid = 0, ?int $handlerid = null): stdClass {
        global $DB, $USER;
        $context = context_course::instance($courseid);
        require_capability('local/integritycase:manage', $context);
        if (!is_enrolled($context, $studentid)) {
            throw new moodle_exception('error:notenrolled', 'local_integritycase');
        }
        if ($cmid && !$DB->record_exists('course_modules', ['id' => $cmid, 'course' => $courseid])) {
            throw new moodle_exception('error:cmnotincourse', 'local_integritycase');
        }
        $handlerid = $handlerid ?? (int) $USER->id;
        if (!has_capability('local/integritycase:manage', $context, $handlerid)) {
            throw new moodle_exception('error:handlernotstaff', 'local_integritycase');
        }
        $now = time();
        $id = (int) $DB->insert_record(self::TABLE, (object) [
            'courseid' => $courseid,
            'studentid' => $studentid,
            'cmid' => $cmid,
            'handlerid' => $handlerid,
            'createdby' => $USER->id,
            'summary' => $summary,
            'summaryformat' => FORMAT_PLAIN,
            'stage' => workflow::REPORTED,
            'outcome' => null,
            'studentvisible' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
            'timeclosed' => 0,
        ]);
        self::record($id, 'created', null, workflow::REPORTED, null, null, false);
        case_created::create([
            'objectid' => $id,
            'context' => $context,
            'relateduserid' => $studentid,
        ])->trigger();
        return self::get($id);
    }

    /**
     * Move a case to another stage. Staff only.
     *
     * Reaching "awaiting response" or "decided" makes the case visible to the student, who is then
     * told about every later move. A move into "decided" needs an outcome; moving back to
     * "investigating" clears it until a new decision is made.
     *
     * @param int $caseid
     * @param string $to
     * @param string $note Shown to the student once the case is visible.
     * @param string|null $outcome Required when $to is "decided".
     * @return stdClass The updated case.
     */
    public static function move(int $caseid, string $to, string $note = '', ?string $outcome = null): stdClass {
        global $DB;
        $case = self::get($caseid);
        $context = context_course::instance($case->courseid);
        require_capability('local/integritycase:manage', $context);
        if (!workflow::can_move($case->stage, $to)) {
            throw new moodle_exception('error:nomove', 'local_integritycase', '', (object) [
                'from' => self::stage_name($case->stage),
                'to' => self::stage_name($to),
            ]);
        }
        if ($to === workflow::DECIDED && ($outcome === null || !workflow::is_outcome($outcome))) {
            throw new moodle_exception('outcomerequired', 'local_integritycase');
        }

        $from = $case->stage;
        $case->stage = $to;
        if ($to === workflow::DECIDED) {
            $case->outcome = $outcome;
        } else if ($to === workflow::INVESTIGATING) {
            $case->outcome = null;
        }
        $case->studentvisible = (int) $case->studentvisible || workflow::reveals($to) ? 1 : 0;
        $case->timeclosed = $to === workflow::CLOSED ? time() : 0;
        $case->timemodified = time();
        $DB->update_record(self::TABLE, $case);

        $visible = (bool) $case->studentvisible;
        self::record($caseid, 'stage', $from, $to, $case->outcome, $note, $visible);
        self::log($case, $context);
        if ($visible) {
            self::notify_student($case, $note);
        }
        return self::get($caseid);
    }

    /**
     * Add a note only staff can see.
     *
     * @param int $caseid
     * @param string $note
     */
    public static function add_note(int $caseid, string $note): void {
        $case = self::get($caseid);
        $context = context_course::instance($case->courseid);
        require_capability('local/integritycase:manage', $context);
        self::record($caseid, 'note', null, null, null, $note, false);
        self::log($case, $context);
    }

    /**
     * Replace the case's evidence files with those in a draft area. Staff only, logged privately.
     *
     * @param int $caseid
     * @param int $draftitemid
     */
    public static function save_evidence(int $caseid, int $draftitemid): void {
        $case = self::get($caseid);
        $context = context_course::instance($case->courseid);
        require_capability('local/integritycase:manage', $context);
        file_save_draft_area_files(
            $draftitemid,
            $context->id,
            'local_integritycase',
            'evidence',
            $caseid,
            ['subdirs' => 0, 'maxfiles' => 20]
        );
        self::record($caseid, 'evidence', null, null, null, null, false);
        self::log($case, $context);
    }

    /**
     * The student gives their side while the case awaits it. The handler is told.
     *
     * @param int $caseid
     * @param string $text
     */
    public static function respond(int $caseid, string $text): void {
        $case = self::student_case($caseid, workflow::AWAITING);
        self::record($caseid, 'response', null, null, null, $text, true);
        self::touch($case);
        self::notify_handler($case, 'response');
    }

    /**
     * The student appeals a decision. The case moves to "appealed" and the handler is told.
     *
     * @param int $caseid
     * @param string $reason
     * @return stdClass The updated case.
     */
    public static function appeal(int $caseid, string $reason): stdClass {
        global $DB;
        $case = self::student_case($caseid, workflow::DECIDED);
        $DB->set_field(self::TABLE, 'stage', workflow::APPEALED, ['id' => $caseid]);
        $case->stage = workflow::APPEALED;
        self::record($caseid, 'appeal', workflow::DECIDED, workflow::APPEALED, null, $reason, true);
        self::touch($case);
        self::notify_handler($case, 'appeal');
        return self::get($caseid);
    }

    /**
     * Load a case.
     *
     * @param int $caseid
     * @return stdClass
     */
    public static function get(int $caseid): stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $caseid], '*', MUST_EXIST);
    }

    /**
     * The audit trail, oldest first.
     *
     * @param stdClass $case
     * @param bool $forstudent Only what the student may see.
     * @return stdClass[]
     */
    public static function events(stdClass $case, bool $forstudent): array {
        global $DB;
        $conditions = ['caseid' => $case->id];
        if ($forstudent) {
            $conditions['visibletostudent'] = 1;
        }
        return array_values($DB->get_records(self::EVENTS, $conditions, 'timecreated ASC, id ASC'));
    }

    /**
     * All cases in a course, most recently changed first. Callers check local/integritycase:view.
     *
     * @param int $courseid
     * @return stdClass[] Keyed by id.
     */
    public static function for_course(int $courseid): array {
        global $DB;
        return $DB->get_records(self::TABLE, ['courseid' => $courseid], 'timemodified DESC, id DESC');
    }

    /**
     * The cases a student can see in a course.
     *
     * @param int $courseid
     * @param int $studentid
     * @return stdClass[] Keyed by id.
     */
    public static function for_student(int $courseid, int $studentid): array {
        global $DB;
        return $DB->get_records(
            self::TABLE,
            ['courseid' => $courseid, 'studentid' => $studentid, 'studentvisible' => 1],
            'timemodified DESC, id DESC'
        );
    }

    /**
     * Whether the current user may see this case: staff with view, or the student once it is visible.
     *
     * @param stdClass $case
     * @return bool
     */
    public static function can_view(stdClass $case): bool {
        global $USER;
        $context = context_course::instance($case->courseid);
        if (has_capability('local/integritycase:view', $context)) {
            return true;
        }
        return (int) $case->studentid === (int) $USER->id && $case->studentvisible
            && has_capability('local/integritycase:respond', $context);
    }

    /**
     * Human name of a stage.
     *
     * @param string $stage
     * @return string
     */
    public static function stage_name(string $stage): string {
        return get_string_manager()->string_exists("stage:$stage", 'local_integritycase')
            ? get_string("stage:$stage", 'local_integritycase') : $stage;
    }

    /**
     * Load the current user's own visible case at the given stage, or fail.
     *
     * @param int $caseid
     * @param string $stage
     * @return stdClass
     */
    private static function student_case(int $caseid, string $stage): stdClass {
        global $USER;
        $case = self::get($caseid);
        require_capability('local/integritycase:respond', context_course::instance($case->courseid));
        if ((int) $case->studentid !== (int) $USER->id || !$case->studentvisible) {
            throw new moodle_exception('error:notyourcase', 'local_integritycase');
        }
        if ($case->stage !== $stage) {
            throw new moodle_exception('error:wrongstage', 'local_integritycase', '', self::stage_name($case->stage));
        }
        return $case;
    }

    /**
     * Write an audit trail row.
     *
     * @param int $caseid
     * @param string $action created, stage, note, response or appeal.
     * @param string|null $from
     * @param string|null $to
     * @param string|null $outcome
     * @param string|null $note
     * @param bool $visible Whether the student may see it.
     */
    private static function record(
        int $caseid,
        string $action,
        ?string $from,
        ?string $to,
        ?string $outcome,
        ?string $note,
        bool $visible
    ): void {
        global $DB, $USER;
        $DB->insert_record(self::EVENTS, (object) [
            'caseid' => $caseid,
            'userid' => $USER->id,
            'action' => $action,
            'fromstage' => $from,
            'tostage' => $to,
            'outcome' => $outcome,
            'note' => $note === '' ? null : $note,
            'noteformat' => FORMAT_PLAIN,
            'visibletostudent' => $visible ? 1 : 0,
            'timecreated' => time(),
        ]);
    }

    /**
     * Mark a case as changed and log it.
     *
     * @param stdClass $case
     */
    private static function touch(stdClass $case): void {
        global $DB;
        $DB->set_field(self::TABLE, 'timemodified', time(), ['id' => $case->id]);
        self::log($case, context_course::instance($case->courseid));
    }

    /**
     * Trigger the case updated log event.
     *
     * @param stdClass $case
     * @param context_course $context
     */
    private static function log(stdClass $case, context_course $context): void {
        case_updated::create([
            'objectid' => $case->id,
            'context' => $context,
            'relateduserid' => $case->studentid,
            'other' => ['stage' => $case->stage],
        ])->trigger();
    }

    /**
     * Tell the student where their case is now.
     *
     * @param stdClass $case
     * @param string $note
     */
    private static function notify_student(stdClass $case, string $note): void {
        $course = get_course($case->courseid);
        $coursename = format_string($course->fullname, true, ['context' => context_course::instance($course->id)]);
        $stage = self::stage_name($case->stage);
        if ($case->outcome && in_array($case->stage, [workflow::DECIDED, workflow::CLOSED], true)) {
            $stage .= ' (' . get_string('outcome:' . $case->outcome, 'local_integritycase') . ')';
        }
        $text = get_string('message:student', 'local_integritycase', (object) ['course' => $coursename, 'stage' => $stage]);
        if ($note !== '') {
            $text .= "\n\n" . $note;
        }
        self::send(
            $case,
            'casestudent',
            (int) $case->studentid,
            get_string('message:subject:student', 'local_integritycase', $coursename),
            $text
        );
    }

    /**
     * Tell the handler the student responded or appealed.
     *
     * @param stdClass $case
     * @param string $what response or appeal.
     */
    private static function notify_handler(stdClass $case, string $what): void {
        $course = get_course($case->courseid);
        $student = fullname(core_user::get_user($case->studentid));
        $a = (object) [
            'student' => $student,
            'course' => format_string($course->fullname, true, ['context' => context_course::instance($course->id)]),
        ];
        self::send(
            $case,
            'casehandler',
            (int) $case->handlerid,
            get_string('message:subject:handler', 'local_integritycase', $student),
            get_string("message:handler:$what", 'local_integritycase', $a)
        );
    }

    /**
     * Send a notification about a case.
     *
     * @param stdClass $case
     * @param string $provider
     * @param int $userid
     * @param string $subject
     * @param string $text Plain text.
     */
    private static function send(stdClass $case, string $provider, int $userid, string $subject, string $text): void {
        $url = new moodle_url('/local/integritycase/view.php', ['id' => $case->id]);
        $message = new \core\message\message();
        $message->component = 'local_integritycase';
        $message->name = $provider;
        $message->userfrom = core_user::get_noreply_user();
        $message->userto = $userid;
        $message->subject = $subject;
        $message->fullmessage = $text;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = text_to_html(s($text), false, false, true);
        $message->smallmessage = $subject;
        $message->notification = 1;
        $message->contexturl = $url->out(false);
        $message->contexturlname = get_string('pluginname', 'local_integritycase');
        $message->courseid = $case->courseid;
        message_send($message);
    }
}
