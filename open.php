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
 * Open a case.
 *
 * @package    local_integritycase
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_integritycase\form\open_form;
use local_integritycase\local\case_manager;

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('local/integritycase:manage', $context);

$url = new moodle_url('/local/integritycase/open.php', ['courseid' => $courseid]);
$back = new moodle_url('/local/integritycase/index.php', ['courseid' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('newcase', 'local_integritycase'));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));

$students = [];
foreach (get_enrolled_users($context, 'local/integritycase:respond', 0, 'u.*', 'u.lastname, u.firstname') as $user) {
    $students[$user->id] = fullname($user);
}
$handlers = [];
foreach (get_enrolled_users($context, 'local/integritycase:manage', 0, 'u.*', 'u.lastname, u.firstname') as $user) {
    $handlers[$user->id] = fullname($user);
}
$handlers = [$USER->id => fullname($USER)] + $handlers;
$activities = [];
foreach (get_fast_modinfo($course)->get_cms() as $cm) {
    if ($cm->uservisible && in_array($cm->modname, ['assign', 'quiz', 'workshop', 'forum', 'lesson'], true)) {
        $activities[$cm->id] = $cm->get_formatted_name(['escape' => false]);
    }
}

$form = new open_form($url, ['students' => $students, 'activities' => $activities, 'handlers' => $handlers]);
$form->set_data(['courseid' => $courseid, 'handlerid' => $USER->id, 'evidence' => file_get_submitted_draft_itemid('evidence')]);
if ($form->is_cancelled()) {
    redirect($back);
} else if ($data = $form->get_data()) {
    $case = case_manager::open($courseid, (int) $data->studentid, $data->summary, (int) $data->cmid, (int) $data->handlerid);
    if (!empty($data->evidence) && file_get_all_files_in_draftarea((int) $data->evidence)) {
        case_manager::save_evidence((int) $case->id, (int) $data->evidence);
    }
    redirect(new moodle_url('/local/integritycase/view.php', ['id' => $case->id]));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('newcase', 'local_integritycase'));
$form->display();
echo $OUTPUT->footer();
