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

/**
 * Cases in a course: all of them for staff, their own visible ones for a student.
 *
 * @package    local_integritycase
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_integritycase\local\case_manager;

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
$isstaff = has_capability('local/integritycase:view', $context);
if (!$isstaff) {
    require_capability('local/integritycase:respond', $context);
}

$PAGE->set_url(new moodle_url('/local/integritycase/index.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$title = get_string($isstaff ? 'cases' : 'yourcases', 'local_integritycase');
$PAGE->set_title($title);
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));

$cases = $isstaff ? case_manager::for_course($courseid) : case_manager::for_student($courseid, (int) $USER->id);
$modinfo = get_fast_modinfo($course);
$rows = [];
foreach ($cases as $case) {
    $rows[] = [
        'url' => (new moodle_url('/local/integritycase/view.php', ['id' => $case->id]))->out(false),
        'student' => fullname(core_user::get_user($case->studentid)),
        'activity' => $case->cmid && isset($modinfo->cms[$case->cmid]) ? $modinfo->cms[$case->cmid]->get_formatted_name() : '',
        'stage' => case_manager::stage_name($case->stage),
        'outcome' => $case->outcome ? get_string('outcome:' . $case->outcome, 'local_integritycase') : '',
        'handler' => $case->handlerid ? fullname(core_user::get_user($case->handlerid)) : '',
        'updated' => userdate($case->timemodified, get_string('strftimedatetimeshort', 'langconfig')),
        'hidden' => !$case->studentvisible,
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
if (has_capability('local/integritycase:manage', $context)) {
    echo $OUTPUT->single_button(
        new moodle_url('/local/integritycase/open.php', ['courseid' => $courseid]),
        get_string('newcase', 'local_integritycase'),
        'get',
        ['type' => single_button::BUTTON_PRIMARY]
    );
}
echo $OUTPUT->render_from_template('local_integritycase/case_list', [
    'rows' => $rows,
    'hasrows' => (bool) $rows,
    'isstaff' => $isstaff,
]);
echo $OUTPUT->footer();
