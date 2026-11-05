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

/**
 * One text box and a button: a private note, a student response or an appeal.
 *
 * @package    local_integritycase
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class text_form extends \moodleform {
    /**
     * Define the fields. Custom data: action (note, respond or appeal), label, button.
     */
    protected function definition() {
        $mform = $this->_form;
        $data = $this->_customdata;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'action', $data['action']);
        $mform->setType('action', PARAM_ALPHA);
        $mform->addElement('textarea', 'text', $data['label'], ['rows' => 5, 'cols' => 60]);
        $mform->setType('text', PARAM_TEXT);
        $mform->addRule('text', null, 'required', null, 'client');
        $this->add_action_buttons(false, $data['button']);
    }
}
