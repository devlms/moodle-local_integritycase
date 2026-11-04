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
 * One case: what was found, its history, and what the viewer may do next.
 *
 * @package    local_integritycase
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_integritycase\form\{evidence_form, move_form, text_form};
use local_integritycase\local\{case_manager, workflow};

$id = required_param('id', PARAM_INT);
$case = case_manager::get($id);
$course = get_course($case->courseid);
require_login($course);
$context = context_course::instance($course->id);
if (!case_manager::can_view($case)) {
    throw new required_capability_exception($context, 'local/integritycase:view', 'nopermissions', '');
}
$isstaff = has_capability('local/integritycase:view', $context);
$canmanage = has_capability('local/integritycase:manage', $context);
$isstudent = !$isstaff && (int) $case->studentid === (int) $USER->id;

$url = new moodle_url('/local/integritycase/view.php', ['id' => $id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$student = core_user::get_user($case->studentid);
$title = get_string('caseabout', 'local_integritycase', fullname($student));
$PAGE->set_title($title);
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));
$PAGE->navbar->add(
    get_string($isstaff ? 'cases' : 'yourcases', 'local_integritycase'),
    new moodle_url('/local/integritycase/index.php', ['courseid' => $course->id])
);
$PAGE->navbar->add($title);

// The forms this viewer may use, at this stage.
$forms = [];
if ($canmanage) {
    $stages = [];
    foreach (workflow::next_stages($case->stage) as $stage) {
        $stages[$stage] = case_manager::stage_name($stage);
    }
    $forms['move'] = new move_form($url, ['stages' => $stages]);
    $forms['note'] = new text_form($url, ['action' => 'note', 'header' => get_string('privatenote', 'local_integritycase'), 'label' => get_string('privatenote', 'local_integritycase'),
        'button' => get_string('addnote', 'local_integritycase')]);
    $draft = file_get_submitted_draft_itemid('evidence');
    file_prepare_draft_area($draft, $context->id, 'local_integritycase', 'evidence', $id, ['subdirs' => 0, 'maxfiles' => 20]);
    $forms['evidence'] = new evidence_form($url);
    $forms['evidence']->set_data(['id' => $id, 'evidence' => $draft]);
} else if ($isstudent && $case->stage === workflow::AWAITING) {
    $forms['respond'] = new text_form($url, ['action' => 'respond', 'header' => get_string('yourside', 'local_integritycase'), 'label' => get_string('response', 'local_integritycase'),
        'button' => get_string('respond', 'local_integritycase')]);
} else if ($isstudent && $case->stage === workflow::DECIDED) {
    $forms['appeal'] = new text_form($url, ['action' => 'appeal', 'header' => get_string('appealheader', 'local_integritycase'), 'label' => get_string('appealreason', 'local_integritycase'),
        'button' => get_string('appeal', 'local_integritycase')]);
}

$action = optional_param('action', '', PARAM_ALPHA);
if ($action && isset($forms[$action]) && ($data = $forms[$action]->get_data())) {
    switch ($action) {
        case 'move':
            case_manager::move($id, $data->stage, trim($data->note ?? ''), ($data->outcome ?? '') ?: null);
            break;
        case 'note':
            case_manager::add_note($id, trim($data->text));
            break;
        case 'evidence':
            case_manager::save_evidence($id, (int) $data->evidence);
            break;
        case 'respond':
            case_manager::respond($id, trim($data->text));
            break;
        case 'appeal':
            case_manager::appeal($id, trim($data->text));
            break;
    }
    redirect($url);
}

$history = [];
foreach (case_manager::events($case, !$isstaff) as $event) {
    $line = get_string('action:' . $event->action, 'local_integritycase');
    if ($event->action === 'stage' || $event->action === 'appeal') {
        $line .= ': ' . case_manager::stage_name($event->tostage);
        if ($event->tostage === workflow::DECIDED && $event->outcome) {
            $line .= ' (' . get_string('outcome:' . $event->outcome, 'local_integritycase') . ')';
        }
    }
    $history[] = [
        'what' => $line,
        'who' => $event->userid ? fullname(core_user::get_user($event->userid)) : '',
        'when' => userdate($event->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
        'note' => $event->note ? format_text($event->note, FORMAT_PLAIN, ['context' => $context]) : '',
        'private' => $isstaff && !$event->visibletostudent,
    ];
}
$files = [];
if ($isstaff) {
    foreach (get_file_storage()->get_area_files($context->id, 'local_integritycase', 'evidence', $id, 'filename', false) as $file) {
        $files[] = [
            'name' => $file->get_filename(),
            'url' => moodle_url::make_pluginfile_url(
                $context->id,
                'local_integritycase',
                'evidence',
                $id,
                '/',
                $file->get_filename(),
                true
            )->out(false),
        ];
    }
}
$cmname = '';
if ($case->cmid && ($cm = get_fast_modinfo($course)->cms[$case->cmid] ?? null)) {
    $cmname = $cm->get_formatted_name();
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo $OUTPUT->render_from_template('local_integritycase/case_view', [
    'summary' => format_text($case->summary, FORMAT_PLAIN, ['context' => $context]),
    'stage' => case_manager::stage_name($case->stage),
    'outcome' => $case->outcome ? get_string('outcome:' . $case->outcome, 'local_integritycase') : '',
    'activity' => $cmname,
    'handler' => $case->handlerid ? fullname(core_user::get_user($case->handlerid)) : '',
    'hiddenfromstudent' => $isstaff && !$case->studentvisible,
    'history' => $history,
    'files' => $files,
    'hasfiles' => (bool) $files,
    'isstaff' => $isstaff,
]);
foreach ($forms as $name => $form) {
    if ($name !== 'evidence') {
        $form->set_data(['id' => $id]);
    }
    if ($name === 'move' && !workflow::next_stages($case->stage)) {
        continue;
    }
    $form->display();
}
echo $OUTPUT->footer();
