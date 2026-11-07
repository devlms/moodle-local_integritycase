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

namespace local_integritycase\local;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_integritycase\local\workflow::class)]
/**
 * Tests for the stage rules.
 *
 * @package    local_integritycase
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_integritycase\local\workflow
 */
final class workflow_test extends \basic_testcase {
    public function test_stages_in_order(): void {
        $this->assertSame(
            ['reported', 'investigating', 'awaitingresponse', 'decided', 'appealed', 'closed'],
            workflow::stages()
        );
    }

    public function test_staff_moves(): void {
        $this->assertSame(['investigating', 'closed'], workflow::next_stages('reported'));
        $this->assertSame(['awaitingresponse', 'decided', 'closed'], workflow::next_stages('investigating'));
        $this->assertSame(['investigating', 'decided', 'closed'], workflow::next_stages('awaitingresponse'));
        $this->assertSame(['investigating', 'closed'], workflow::next_stages('decided'));
        $this->assertSame(['decided', 'closed'], workflow::next_stages('appealed'));
        $this->assertSame(['investigating'], workflow::next_stages('closed'));
        $this->assertSame([], workflow::next_stages('nonsense'));
    }

    public function test_can_move(): void {
        $this->assertTrue(workflow::can_move('reported', 'investigating'));
        $this->assertFalse(workflow::can_move('reported', 'decided'), 'A decision needs an investigation first.');
        $this->assertFalse(workflow::can_move('decided', 'appealed'), 'Only the student appeals.');
        $this->assertFalse(workflow::can_move('closed', 'closed'));
    }

    public function test_which_stages_reveal_the_case(): void {
        $this->assertFalse(workflow::reveals('reported'));
        $this->assertFalse(workflow::reveals('investigating'));
        $this->assertTrue(workflow::reveals('awaitingresponse'));
        $this->assertTrue(workflow::reveals('decided'));
        $this->assertFalse(workflow::reveals('closed'), 'Closing an unseen case keeps it unseen.');
    }

    public function test_outcomes(): void {
        $this->assertSame(['nocase', 'warning', 'resubmit', 'markreduced', 'referred'], workflow::outcomes());
        $this->assertTrue(workflow::is_outcome('warning'));
        $this->assertFalse(workflow::is_outcome('expelled'));
    }

    public function test_every_stage_and_outcome_has_a_label(): void {
        foreach (workflow::stages() as $stage) {
            $this->assertTrue(get_string_manager()->string_exists("stage:$stage", 'local_integritycase'), $stage);
        }
        foreach (workflow::outcomes() as $outcome) {
            $this->assertTrue(get_string_manager()->string_exists("outcome:$outcome", 'local_integritycase'), $outcome);
        }
    }
}
