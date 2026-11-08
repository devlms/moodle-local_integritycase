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

use context_course;
use core_user;
use local_integritycase\local\{case_manager, display, workflow};
use moodle_url;
use renderer_base;
use stdClass;

/**
 * One case: header, progress, what was found, history and the viewer's actions.
 *
 * @package    local_integritycase
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class case_view implements \renderable, \templatable {
    /**
     * Constructor.
     *
     * @param stdClass $case
     * @param bool $isstaff
     * @param array $forms each ['title' => string, 'html' => string], already rendered
     * @param int $now
     */
    public function __construct(
        /** @var stdClass */
        private stdClass $case,
        /** @var bool */
        private bool $isstaff,
        /** @var array */
        private array $forms,
        /** @var int */
        private int $now
    ) {
    }

    /**
     * The stages to draw, with the state of each: done, current, skipped or todo.
     *
     * @param stdClass[] $events every event on the case
     * @return array
     */
    private function steps(array $events): array {
        $visited = [workflow::REPORTED => true];
        foreach ($events as $event) {
            if ($event->tostage) {
                $visited[$event->tostage] = true;
            }
        }
        $path = [workflow::REPORTED, workflow::INVESTIGATING, workflow::AWAITING, workflow::DECIDED];
        if (!empty($visited[workflow::APPEALED])) {
            $path[] = workflow::APPEALED;
        }
        $path[] = workflow::CLOSED;
        $current = array_search($this->case->stage, $path, true);
        $steps = [];
        foreach ($path as $i => $stage) {
            if ($i === $current) {
                $state = 'current';
            } else if ($i < $current) {
                $state = empty($visited[$stage]) ? 'skipped' : 'done';
            } else {
                $state = 'todo';
            }
            $steps[] = [
                'key' => $stage,
                'name' => display::stage_label($stage),
                'state' => $state,
                'statelabel' => get_string('step:' . $state, 'local_integritycase'),
                'iscurrent' => $state === 'current',
                'isdone' => $state === 'done',
                'number' => $i + 1,
            ];
        }
        return $steps;
    }

    /**
     * Export for local_integritycase/case_view.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $case = $this->case;
        $context = context_course::instance($case->courseid);
        $student = core_user::get_user($case->studentid);
        $users = [];
        $user = function (int $id) use (&$users): ?stdClass {
            return $id ? ($users[$id] ??= core_user::get_user($id)) : null;
        };

        $history = [];
        foreach (case_manager::events($case, !$this->isstaff) as $event) {
            $what = get_string('action:' . $event->action, 'local_integritycase');
            if ($event->action === 'stage' || $event->action === 'appeal') {
                $what .= ': ' . case_manager::stage_name($event->tostage);
                if ($event->tostage === workflow::DECIDED && $event->outcome) {
                    $what .= ' (' . get_string('outcome:' . $event->outcome, 'local_integritycase') . ')';
                }
            }
            $who = $user((int) $event->userid);
            $history[] = [
                'what' => $what,
                'who' => $who ? fullname($who) : '',
                'picture' => $who ? $output->user_picture($who, ['size' => 35, 'link' => false, 'alttext' => false]) : '',
                'when' => userdate($event->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
                'ago' => display::ago((int) $event->timecreated, $this->now),
                'note' => $event->note ? format_text($event->note, FORMAT_PLAIN, ['context' => $context]) : '',
                'private' => $this->isstaff && !$event->visibletostudent,
                'bystudent' => (int) $event->userid === (int) $case->studentid,
            ];
        }

        $files = [];
        if ($this->isstaff) {
            $stored = get_file_storage()->get_area_files(
                $context->id,
                'local_integritycase',
                'evidence',
                $case->id,
                'filename',
                false
            );
            foreach ($stored as $file) {
                $files[] = [
                    'name' => $file->get_filename(),
                    'size' => display_size($file->get_filesize()),
                    'icon' => $output->pix_icon(file_file_icon($file), '', 'moodle'),
                    'url' => moodle_url::make_pluginfile_url(
                        $context->id,
                        'local_integritycase',
                        'evidence',
                        $case->id,
                        '/',
                        $file->get_filename(),
                        true
                    )->out(false),
                ];
            }
        }

        $activity = '';
        $activityurl = '';
        $activityicon = '';
        if ($case->cmid && ($cm = get_fast_modinfo($case->courseid)->cms[$case->cmid] ?? null)) {
            $activity = $cm->get_formatted_name(['escape' => false]);
            $activityurl = $cm->url ? $cm->url->out(false) : '';
            $activityicon = $output->pix_icon('monologo', '', $cm->modname, ['class' => 'icon me-1']);
        }

        $handler = $user((int) $case->handlerid);
        return [
            'heading' => $this->isstaff ? get_string('caseabout', 'local_integritycase', fullname($student))
                : get_string('yourcase', 'local_integritycase'),
            'student' => fullname($student),
            'picture' => $output->user_picture($student, ['size' => 64, 'link' => false, 'alttext' => false]),
            'stage' => display::stage_label($case->stage),
            'stagebadge' => display::stage_badge($case->stage),
            'outcome' => $case->outcome ? get_string('outcome:' . $case->outcome, 'local_integritycase') : '',
            'activity' => $activity,
            'activityurl' => $activityurl,
            'activityicon' => $activityicon,
            'handler' => $handler ? fullname($handler) : '',
            'opened' => userdate($case->timecreated, get_string('strftimedate', 'langconfig')),
            'updated' => display::ago((int) $case->timemodified, $this->now),
            'hiddenfromstudent' => $this->isstaff && !$case->studentvisible,
            'summary' => $this->isstaff || $case->studentvisible
                ? format_text($case->summary, FORMAT_PLAIN, ['context' => $context]) : '',
            'steps' => $this->steps(case_manager::events($case, false)),
            'history' => $history,
            'hashistory' => (bool) $history,
            'files' => $files,
            'hasfiles' => (bool) $files,
            'isstaff' => $this->isstaff,
            'forms' => array_values($this->forms),
            'hasforms' => (bool) $this->forms,
        ];
    }
}
