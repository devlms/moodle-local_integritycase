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

namespace local_integritycase\reportbuilder\datasource;

use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\{course, user};
use lang_string;
use local_integritycase\reportbuilder\local\entities\integritycase;

/**
 * Report source for integrity cases across the site.
 *
 * Only offered to, and only usable by, people with local/integritycase:viewall at site level: a
 * custom report built on it shows cases from every course.
 *
 * @package    local_integritycase
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cases extends datasource {
    /**
     * Source name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('casesource', 'local_integritycase');
    }

    /**
     * Only for people allowed to see every case.
     *
     * @return bool
     */
    public static function is_available(): bool {
        return has_capability('local/integritycase:viewall', \context_system::instance());
    }

    /**
     * Join the case to its course, student and handler.
     */
    protected function initialise(): void {
        $caseentity = new integritycase();
        $alias = $caseentity->get_table_alias('local_integritycase');
        $this->set_main_table('local_integritycase', $alias);
        $this->add_entity($caseentity);

        $courseentity = new course();
        $coursealias = $courseentity->get_table_alias('course');
        $this->add_entity($courseentity->add_join("JOIN {course} {$coursealias} ON {$coursealias}.id = {$alias}.courseid"));

        $student = (new user())->set_entity_name('student')->set_entity_title(new lang_string('student', 'local_integritycase'));
        $studentalias = $student->get_table_alias('user');
        $this->add_entity($student->add_join("JOIN {user} {$studentalias} ON {$studentalias}.id = {$alias}.studentid"));

        $handler = (new user())->set_entity_name('handler')->set_entity_title(new lang_string('handler', 'local_integritycase'));
        $handleralias = $handler->get_table_alias('user');
        $this->add_entity($handler->add_join("LEFT JOIN {user} {$handleralias} ON {$handleralias}.id = {$alias}.handlerid"));

        $this->add_all_from_entities();
    }

    /**
     * Columns of a new report.
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        return ['course:fullname', 'student:fullname', 'integritycase:stage', 'integritycase:outcome',
            'integritycase:timecreated'];
    }

    /**
     * Sorting of a new report.
     *
     * @return int[]
     */
    public function get_default_column_sorting(): array {
        return ['integritycase:timecreated' => SORT_DESC];
    }

    /**
     * Filters of a new report.
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return ['integritycase:stage', 'integritycase:outcome', 'course:fullname'];
    }

    /**
     * Conditions of a new report.
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return ['integritycase:stage'];
    }
}
