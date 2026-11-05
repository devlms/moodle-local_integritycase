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
 * How stages and times are shown on the plugin's pages.
 *
 * @package    local_integritycase
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class display {
    /** @var string[] Badge classes per stage. Text colours are set so each passes WCAG AA on 4.5 and 5.x. */
    private const BADGES = [
        workflow::REPORTED => 'bg-secondary text-dark',
        workflow::INVESTIGATING => 'bg-warning text-dark',
        workflow::AWAITING => 'bg-info text-white',
        workflow::DECIDED => 'bg-primary text-white',
        workflow::APPEALED => 'bg-danger text-white',
        workflow::CLOSED => 'bg-light text-dark border',
    ];

    /**
     * The stage name with a capital first letter, for labels and badges.
     *
     * @param string $stage
     * @return string
     */
    public static function stage_label(string $stage): string {
        $name = case_manager::stage_name($stage);
        return \core_text::strtoupper(\core_text::substr($name, 0, 1)) . \core_text::substr($name, 1);
    }

    /**
     * Bootstrap classes for a stage badge.
     *
     * @param string $stage
     * @return string
     */
    public static function stage_badge(string $stage): string {
        return 'badge ' . (self::BADGES[$stage] ?? 'bg-secondary text-dark');
    }

    /**
     * A short relative time: "Just now", "3 hours ago", "Yesterday", "5 days ago", then the date.
     *
     * @param int $time
     * @param int $now
     * @return string
     */
    public static function ago(int $time, int $now): string {
        $diff = max(0, $now - $time);
        if ($diff < HOURSECS) {
            return get_string('ago:now', 'local_integritycase');
        }
        if ($diff < DAYSECS) {
            return get_string('ago:hours', 'local_integritycase', intdiv($diff, HOURSECS));
        }
        if ($diff < 2 * DAYSECS) {
            return get_string('ago:yesterday', 'local_integritycase');
        }
        if ($diff < 14 * DAYSECS) {
            return get_string('ago:days', 'local_integritycase', intdiv($diff, DAYSECS));
        }
        return userdate($time, get_string('strftimedate', 'langconfig'));
    }
}
