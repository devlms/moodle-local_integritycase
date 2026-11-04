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

use context;
use context_course;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_integritycase\local\case_manager;

/**
 * Privacy provider.
 *
 * A student's request covers whole cases about them, staff notes included. A staff member's request
 * covers their own actions; deleting it keeps the case for the record and removes their name.
 *
 * @package    local_integritycase
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data stored.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_integritycase', [
            'studentid' => 'privacy:metadata:case:studentid',
            'handlerid' => 'privacy:metadata:case:handlerid',
            'createdby' => 'privacy:metadata:case:createdby',
            'summary' => 'privacy:metadata:case:summary',
            'stage' => 'privacy:metadata:case:stage',
            'outcome' => 'privacy:metadata:case:outcome',
            'timecreated' => 'privacy:metadata:case:timecreated',
        ], 'privacy:metadata:case');
        $collection->add_database_table('local_integritycase_event', [
            'userid' => 'privacy:metadata:event:userid',
            'note' => 'privacy:metadata:event:note',
            'timecreated' => 'privacy:metadata:event:timecreated',
        ], 'privacy:metadata:event');
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:core_files');
        $collection->add_subsystem_link('core_message', [], 'privacy:metadata:core_message');
        return $collection;
    }

    /**
     * Course contexts where the user is in a case: as student, handler, opener or actor.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {local_integritycase} c ON c.courseid = ctx.instanceid AND ctx.contextlevel = :level
             LEFT JOIN {local_integritycase_event} e ON e.caseid = c.id
                 WHERE c.studentid = :u1 OR c.handlerid = :u2 OR c.createdby = :u3 OR e.userid = :u4";
        $list = new contextlist();
        $list->add_from_sql($sql, ['level' => CONTEXT_COURSE, 'u1' => $userid, 'u2' => $userid, 'u3' => $userid,
            'u4' => $userid]);
        return $list;
    }

    /**
     * Users with data in a course context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }
        $params = ['courseid' => $context->instanceid];
        foreach (['studentid', 'handlerid', 'createdby'] as $field) {
            $userlist->add_from_sql($field, "SELECT $field FROM {local_integritycase} WHERE courseid = :courseid", $params);
        }
        $userlist->add_from_sql('userid', "SELECT e.userid
                                              FROM {local_integritycase_event} e
                                              JOIN {local_integritycase} c ON c.id = e.caseid
                                             WHERE c.courseid = :courseid", $params);
    }

    /**
     * Export the user's data, one folder per case.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        foreach (self::course_contexts($contextlist->get_contexts()) as $context) {
            foreach ($DB->get_records('local_integritycase', ['courseid' => $context->instanceid]) as $case) {
                $isstudent = (int) $case->studentid === $userid;
                $history = [];
                foreach (case_manager::events($case, false) as $event) {
                    if ($isstudent || (int) $event->userid === $userid) {
                        $history[] = (object) [
                            'action' => get_string('action:' . $event->action, 'local_integritycase'),
                            'stage' => $event->tostage ? case_manager::stage_name($event->tostage) : null,
                            'note' => $event->note,
                            'byyou' => transform::yesno((int) $event->userid === $userid),
                            'time' => transform::datetime($event->timecreated),
                        ];
                    }
                }
                $involved = $isstudent || in_array($userid, [(int) $case->handlerid, (int) $case->createdby], true);
                if (!$involved && !$history) {
                    continue;
                }
                $data = (object) [
                    'stage' => case_manager::stage_name($case->stage),
                    'outcome' => $case->outcome ? get_string('outcome:' . $case->outcome, 'local_integritycase') : null,
                    'youarethehandler' => transform::yesno((int) $case->handlerid === $userid),
                    'youopenedit' => transform::yesno((int) $case->createdby === $userid),
                    'timecreated' => transform::datetime($case->timecreated),
                    'history' => $history,
                ];
                $path = [get_string('pluginname', 'local_integritycase'), $case->id];
                if ($isstudent) {
                    $data->summary = $case->summary;
                    writer::with_context($context)->export_area_files($path, 'local_integritycase', 'evidence', $case->id);
                }
                writer::with_context($context)->export_data($path, $data);
            }
        }
    }

    /**
     * Delete every case in a course context.
     *
     * @param context $context
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;
        if (!$context instanceof context_course) {
            return;
        }
        foreach ($DB->get_fieldset_select('local_integritycase', 'id', 'courseid = ?', [$context->instanceid]) as $caseid) {
            self::delete_case($context, (int) $caseid);
        }
    }

    /**
     * Delete one user's data in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        foreach (self::course_contexts($contextlist->get_contexts()) as $context) {
            self::delete_user($context, (int) $contextlist->get_user()->id);
        }
    }

    /**
     * Delete several users' data in one context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            self::delete_user($context, (int) $userid);
        }
    }

    /**
     * Remove a student's cases, and a staff member's name from the rest.
     *
     * @param context_course $context
     * @param int $userid
     */
    private static function delete_user(context_course $context, int $userid): void {
        global $DB;
        $courseid = $context->instanceid;
        $own = $DB->get_fieldset_select('local_integritycase', 'id', 'courseid = ? AND studentid = ?', [$courseid, $userid]);
        foreach ($own as $caseid) {
            self::delete_case($context, (int) $caseid);
        }
        $DB->set_field('local_integritycase', 'handlerid', 0, ['courseid' => $courseid, 'handlerid' => $userid]);
        $DB->set_field('local_integritycase', 'createdby', 0, ['courseid' => $courseid, 'createdby' => $userid]);
        $DB->execute(
            "UPDATE {local_integritycase_event}
                         SET userid = 0
                       WHERE userid = :userid
                         AND caseid IN (SELECT id FROM {local_integritycase} WHERE courseid = :courseid)",
            ['userid' => $userid, 'courseid' => $courseid]
        );
    }

    /**
     * Delete a case, its trail and its evidence.
     *
     * @param context_course $context
     * @param int $caseid
     */
    private static function delete_case(context_course $context, int $caseid): void {
        global $DB;
        get_file_storage()->delete_area_files($context->id, 'local_integritycase', 'evidence', $caseid);
        $DB->delete_records('local_integritycase_event', ['caseid' => $caseid]);
        $DB->delete_records('local_integritycase', ['id' => $caseid]);
    }

    /**
     * Keep only course contexts.
     *
     * @param context[] $contexts
     * @return context_course[]
     */
    private static function course_contexts(array $contexts): array {
        return array_filter($contexts, fn($c) => $c instanceof context_course);
    }
}
