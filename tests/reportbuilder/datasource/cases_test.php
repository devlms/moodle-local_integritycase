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

use core_reportbuilder\local\filters\select;
use core_reportbuilder\tests\core_reportbuilder_testcase;
use local_integritycase\local\case_manager;

/**
 * Tests for the cases report source.
 *
 * @package    local_integritycase
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_integritycase\reportbuilder\datasource\cases
 * @covers     \local_integritycase\reportbuilder\local\entities\integritycase
 */
final class cases_test extends core_reportbuilder_testcase {
    /**
     * Two cases in one course: one decided, one still reported.
     *
     * @return array [course, decided student, reported student]
     */
    private function make_cases(): array {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['fullname' => 'Ethics 101']);
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $amy = $gen->create_and_enrol($course, 'student', ['firstname' => 'Amy', 'lastname' => 'A']);
        $zed = $gen->create_and_enrol($course, 'student', ['firstname' => 'Zed', 'lastname' => 'Z']);
        $this->setUser($teacher);
        $case = case_manager::open((int) $course->id, (int) $amy->id, 'x');
        case_manager::move((int) $case->id, 'investigating');
        case_manager::move((int) $case->id, 'decided', '', 'warning');
        case_manager::open((int) $course->id, (int) $zed->id, 'y');
        $this->setAdminUser();
        return [$course, $amy, $zed];
    }

    public function test_default_report(): void {
        $this->resetAfterTest();
        [, $amy, $zed] = $this->make_cases();
        $report = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder')
            ->create_report(['name' => 'Cases', 'source' => cases::class, 'default' => 1]);
        $content = array_map('array_values', $this->get_custom_report_content($report->get('id')));
        usort($content, fn($a, $b) => strcmp($a[1], $b[1]));
        $this->assertSame(['Ethics 101', fullname($amy), 'decided', 'educational warning'], array_slice($content[0], 0, 4));
        $this->assertSame(['Ethics 101', fullname($zed), 'reported', ''], array_slice($content[1], 0, 4));
    }

    public function test_stage_filter(): void {
        $this->resetAfterTest();
        [, $amy] = $this->make_cases();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report(['name' => 'Cases', 'source' => cases::class, 'default' => 0]);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'student:fullname']);
        $generator->create_filter(['reportid' => $report->get('id'), 'uniqueidentifier' => 'integritycase:stage']);
        $content = $this->get_custom_report_content($report->get('id'), 0, [
            'integritycase:stage_operator' => select::EQUAL_TO,
            'integritycase:stage_value' => 'decided',
        ]);
        $this->assertSame([[fullname($amy)]], array_map('array_values', $content));
    }

    public function test_only_available_with_viewall(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->assertTrue(cases::is_available());
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertFalse(cases::is_available());
    }

    public function test_stress(): void {
        $this->resetAfterTest();
        $this->make_cases();
        $this->datasource_stress_test_columns(cases::class);
        $this->datasource_stress_test_columns_aggregation(cases::class);
        $this->datasource_stress_test_conditions(cases::class, 'integritycase:stage');
    }
}
