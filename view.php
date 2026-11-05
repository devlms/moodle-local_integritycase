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
$narrow = ['class' => 'full-width-labels'];
if ($canmanage) {
    $stages = [];
    foreach (workflow::next_stages($case->stage) as $stage) {
        $stages[$stage] = \local_integritycase\local\display::stage_label($stage);
    }
    $forms['move'] = new move_form($url, ['stages' => $stages], 'post', '', $narrow);
    $forms['note'] = new text_form($url, [
        'action' => 'note',
        'label' => get_string('privatenote', 'local_integritycase'),
        'button' => get_string('addnote', 'local_integritycase')], 'post', '', $narrow);
    $draft = file_get_submitted_draft_itemid('evidence');
    file_prepare_draft_area($draft, $context->id, 'local_integritycase', 'evidence', $id, ['subdirs' => 0, 'maxfiles' => 20]);
    $forms['evidence'] = new evidence_form($url, null, 'post', '', $narrow);
    $forms['evidence']->set_data(['id' => $id, 'evidence' => $draft]);
} else if ($isstudent && $case->stage === workflow::AWAITING) {
    $forms['respond'] = new text_form($url, [
        'action' => 'respond',
        'label' => get_string('response', 'local_integritycase'),
        'button' => get_string('respond', 'local_integritycase')], 'post', '', $narrow);
} else if ($isstudent && $case->stage === workflow::DECIDED) {
    $forms['appeal'] = new text_form($url, [
        'action' => 'appeal',
        'label' => get_string('appealreason', 'local_integritycase'),
        'button' => get_string('appeal', 'local_integritycase')], 'post', '', $narrow);
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

$titles = [
    'move' => 'nextstep',
    'note' => 'privatenote',
    'evidence' => 'evidence',
    'respond' => 'yourside',
    'appeal' => 'appealheader',
];
$rendered = [];
foreach ($forms as $name => $form) {
    if ($name === 'move' && !workflow::next_stages($case->stage)) {
        continue;
    }
    if ($name !== 'evidence') {
        $form->set_data(['id' => $id]);
    }
    $rendered[] = ['title' => get_string($titles[$name], 'local_integritycase'), 'html' => $form->render()];
}
$page = new \local_integritycase\output\case_view($case, $isstaff, $rendered, time());

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_integritycase/case_view', $page->export_for_template($OUTPUT));
echo $OUTPUT->footer();
