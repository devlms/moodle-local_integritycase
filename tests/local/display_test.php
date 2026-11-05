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

/**
 * Tests for how stages and times are shown.
 *
 * @package    local_integritycase
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_integritycase\local\display
 */
final class display_test extends \advanced_testcase {
    public function test_stage_label_is_sentence_case(): void {
        $this->assertSame('Awaiting student response', display::stage_label('awaitingresponse'));
        $this->assertSame('Investigating', display::stage_label('investigating'));
    }

    public function test_every_stage_has_its_own_badge(): void {
        $classes = array_map([display::class, 'stage_badge'], workflow::stages());
        $this->assertCount(count(workflow::stages()), array_unique($classes));
        $this->assertStringContainsString('bg-danger', display::stage_badge('appealed'));
    }

    public function test_ago(): void {
        $now = 1_800_000_000;
        $this->assertSame('Just now', display::ago($now - 120, $now));
        $this->assertSame('3 hours ago', display::ago($now - 3 * HOURSECS - 60, $now));
        $this->assertSame('Yesterday', display::ago($now - DAYSECS - 60, $now));
        $this->assertSame('5 days ago', display::ago($now - 5 * DAYSECS, $now));
        $this->assertSame(
            userdate($now - 20 * DAYSECS, get_string('strftimedate', 'langconfig')),
            display::ago($now - 20 * DAYSECS, $now)
        );
    }
}
