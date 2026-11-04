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

/**
 * Navigation and file serving.
 *
 * @package    local_integritycase
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add "Integrity cases" to the course navigation for staff, and for students with a visible case.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_integritycase_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context) {
    global $USER;
    $url = new moodle_url('/local/integritycase/index.php', ['courseid' => $course->id]);
    if (has_capability('local/integritycase:view', $context)) {
        $navigation->add(
            get_string('cases', 'local_integritycase'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'local_integritycase',
            new pix_icon('i/report', '')
        );
    } else if (
        has_capability('local/integritycase:respond', $context)
            && \local_integritycase\local\case_manager::for_student((int) $course->id, (int) $USER->id)
    ) {
        $navigation->add(
            get_string('yourcases', 'local_integritycase'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'local_integritycase',
            new pix_icon('i/report', '')
        );
    }
}

/**
 * Serve evidence files to staff who can view cases in the course.
 *
 * @param stdClass $course
 * @param stdClass|null $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool false if the file is not found
 */
function local_integritycase_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_COURSE || $filearea !== 'evidence') {
        return false;
    }
    require_login($course);
    require_capability('local/integritycase:view', $context);
    $caseid = (int) array_shift($args);
    $case = \local_integritycase\local\case_manager::get($caseid);
    if ((int) $case->courseid !== (int) $course->id) {
        return false;
    }
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $file = get_file_storage()->get_file($context->id, 'local_integritycase', 'evidence', $caseid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, true, $options);
}
