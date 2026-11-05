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

namespace local_integritycase\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use local_integritycase\local\workflow;

/**
 * Form to move a case to its next stage.
 *
 * @package    local_integritycase
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class move_form extends \moodleform {
    /**
     * Define the fields. Custom data: stages, id => name, the moves allowed from the current stage.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'action', 'move');
        $mform->setType('action', PARAM_ALPHA);
        $mform->addElement('select', 'stage', get_string('stage', 'local_integritycase'), $this->_customdata['stages']);
        $outcomes = ['' => get_string('choosedots')];
        foreach (workflow::outcomes() as $outcome) {
            $outcomes[$outcome] = get_string("outcome:$outcome", 'local_integritycase');
        }
        $mform->addElement('select', 'outcome', get_string('outcome', 'local_integritycase'), $outcomes);
        $mform->hideIf('outcome', 'stage', 'neq', workflow::DECIDED);
        $mform->addElement('textarea', 'note', get_string('note', 'local_integritycase'), ['rows' => 4, 'cols' => 60]);
        $mform->setType('note', PARAM_TEXT);
        $mform->addHelpButton('note', 'note', 'local_integritycase');
        $this->add_action_buttons(false, get_string('changestage', 'local_integritycase'));
    }

    /**
     * A decision needs an outcome.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ($data['stage'] === workflow::DECIDED && !workflow::is_outcome((string) ($data['outcome'] ?? ''))) {
            $errors['outcome'] = get_string('outcomerequired', 'local_integritycase');
        }
        return $errors;
    }
}
