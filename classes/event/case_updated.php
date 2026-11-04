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

namespace local_integritycase\event;

/**
 * Logged when an integrity case is moved, noted, answered or appealed.
 *
 * @package    local_integritycase
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class case_updated extends \core\event\base {
    /**
     * Set the basic event data.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'local_integritycase';
    }

    /**
     * Event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event:caseupdated', 'local_integritycase');
    }

    /**
     * Event description for the logs.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' updated integrity case '{$this->objectid}' "
            . "about the user with id '{$this->relateduserid}'.";
    }

    /**
     * Where the case can be seen.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/integritycase/view.php', ['id' => $this->objectid]);
    }

    /**
     * Check the stage is given.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The relateduserid must be set.');
        }
        if (!isset($this->other['stage'])) {
            throw new \coding_exception('The stage must be set in other.');
        }
    }

    /**
     * Backup mapping for the object id.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'local_integritycase', 'restore' => self::NOT_MAPPED];
    }
}
