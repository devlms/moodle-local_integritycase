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

namespace local_integritycase\output;

use core_user;
use local_integritycase\local\{case_manager, display, workflow};
use moodle_url;
use renderer_base;
use stdClass;

/**
 * The cases in a course: filters for staff, one row per case.
 *
 * @package    local_integritycase
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class case_list implements \renderable, \templatable {
    /** @var string[] Filters, in the order shown. */
    public const FILTERS = ['open', 'closed', 'all'];

    /**
     * Constructor.
     *
     * @param stdClass $course
     * @param stdClass[] $cases the cases the viewer may see
     * @param bool $isstaff
     * @param bool $canmanage
     * @param string $filter open, closed or all (staff only)
     * @param int $now
     */
    public function __construct(
        /** @var stdClass */
        private stdClass $course,
        /** @var stdClass[] */
        private array $cases,
        /** @var bool */
        private bool $isstaff,
        /** @var bool */
        private bool $canmanage,
        /** @var string */
        private string $filter,
        /** @var int */
        private int $now
    ) {
    }

    /**
     * Whether a case passes a filter.
     *
     * @param stdClass $case
     * @param string $filter
     * @return bool
     */
    private static function matches(stdClass $case, string $filter): bool {
        return $filter === 'all' || ($filter === 'closed') === ($case->stage === workflow::CLOSED);
    }

    /**
     * Export for local_integritycase/case_list.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $filter = $this->isstaff && in_array($this->filter, self::FILTERS, true) ? $this->filter : 'all';
        $filters = [];
        if ($this->isstaff) {
            foreach (self::FILTERS as $name) {
                $filters[] = [
                    'name' => get_string('filter:' . $name, 'local_integritycase'),
                    'count' => count(array_filter($this->cases, fn($c) => self::matches($c, $name))),
                    'url' => (new moodle_url(
                        '/local/integritycase/index.php',
                        ['courseid' => $this->course->id, 'show' => $name]
                    ))->out(false),
                    'active' => $name === $filter,
                ];
            }
        }
        $modinfo = get_fast_modinfo($this->course);
        $rows = [];
        foreach ($this->cases as $case) {
            if (!self::matches($case, $filter)) {
                continue;
            }
            $cm = $case->cmid ? ($modinfo->cms[$case->cmid] ?? null) : null;
            $row = [
                'id' => (int) $case->id,
                'url' => (new moodle_url('/local/integritycase/view.php', ['id' => $case->id]))->out(false),
                'activity' => $cm ? $cm->get_formatted_name() : '',
                'stage' => display::stage_label($case->stage),
                'stagebadge' => display::stage_badge($case->stage),
                'outcome' => $case->outcome ? get_string('outcome:' . $case->outcome, 'local_integritycase') : '',
                'updated' => display::ago((int) $case->timemodified, $this->now),
                'updatedfull' => userdate($case->timemodified),
                'hidden' => $this->isstaff && !$case->studentvisible,
            ];
            if ($this->isstaff) {
                $student = core_user::get_user($case->studentid);
                $row['student'] = fullname($student);
                $row['picture'] = $output->user_picture($student, ['size' => 35, 'link' => false, 'alttext' => false]);
                $row['handler'] = $case->handlerid ? fullname(core_user::get_user($case->handlerid)) : '';
            }
            $rows[] = $row;
        }
        return [
            'isstaff' => $this->isstaff,
            'canmanage' => $this->canmanage,
            'newcaseurl' => (new moodle_url('/local/integritycase/open.php', ['courseid' => $this->course->id]))->out(false),
            'filters' => $filters,
            'rows' => $rows,
            'hasrows' => (bool) $rows,
            'emptymessage' => get_string($this->cases ? 'nocasesinfilter' : 'nocases', 'local_integritycase'),
            'emptyhint' => $this->canmanage && !$this->cases ? get_string('nocases_staff', 'local_integritycase') : '',
        ];
    }
}
