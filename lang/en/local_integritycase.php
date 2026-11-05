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
 * English strings for local_integritycase.
 *
 * @package    local_integritycase
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action:appeal'] = 'Appeal';
$string['action:created'] = 'Case opened';
$string['action:evidence'] = 'Evidence updated';
$string['action:note'] = 'Private note';
$string['action:response'] = 'Student response';
$string['action:stage'] = 'Stage changed';
$string['activity'] = 'Related activity';
$string['addnote'] = 'Add private note';
$string['ago:days'] = '{$a} days ago';
$string['ago:hours'] = '{$a} hours ago';
$string['ago:now'] = 'Just now';
$string['ago:yesterday'] = 'Yesterday';
$string['appeal'] = 'Appeal the decision';
$string['appealheader'] = 'Appeal';
$string['appealreason'] = 'Why you are appealing';
$string['backtocases'] = 'Back to cases';
$string['caseabout'] = 'Case about {$a}';
$string['cases'] = 'Integrity cases';
$string['casesource'] = 'Academic integrity cases';
$string['changestage'] = 'Move the case';
$string['entitycase'] = 'Integrity case';
$string['error:cmnotincourse'] = 'That activity is not in this course.';
$string['error:handlernotstaff'] = 'The handler must be able to manage integrity cases in this course.';
$string['error:nomove'] = 'A case cannot move from {$a->from} to {$a->to}.';
$string['error:notenrolled'] = 'The student is not enrolled in this course.';
$string['error:notyourcase'] = 'This is not your case.';
$string['error:wrongstage'] = 'This cannot be done while the case is at {$a}.';
$string['event:casecreated'] = 'Integrity case opened';
$string['event:caseupdated'] = 'Integrity case updated';
$string['evidence'] = 'Evidence files';
$string['evidence_help'] = 'Reports, screenshots and other files. Only staff can see them.';
$string['filter'] = 'Show cases';
$string['filter:all'] = 'All';
$string['filter:closed'] = 'Closed';
$string['filter:open'] = 'Open';
$string['handler'] = 'Handler';
$string['hiddenbadge'] = 'Hidden from student';
$string['history'] = 'History';
$string['integritycase:manage'] = 'Open, move and decide integrity cases';
$string['integritycase:respond'] = 'Respond to and appeal own integrity cases';
$string['integritycase:view'] = 'View integrity cases in a course';
$string['integritycase:viewall'] = 'Report on integrity cases across the site';
$string['message:handler:appeal'] = '{$a->student} appealed the decision in their integrity case in {$a->course}.';
$string['message:handler:response'] = '{$a->student} responded to their integrity case in {$a->course}.';
$string['message:student'] = 'Your academic integrity case in {$a->course} is now: {$a->stage}.';
$string['message:subject:handler'] = 'Integrity case update from {$a}';
$string['message:subject:student'] = 'Your academic integrity case in {$a}';
$string['messageprovider:casehandler'] = 'A student responded to or appealed an integrity case you handle';
$string['messageprovider:casestudent'] = 'Updates on your academic integrity case';
$string['newcase'] = 'Open a case';
$string['nextstep'] = 'Next step';
$string['nocases'] = 'No cases yet.';
$string['nocases_staff'] = 'Open a case when a detector score, a marker or your own reading suggests something is wrong. The student is not told until you move the case to awaiting student response.';
$string['nocasesinfilter'] = 'No cases here. Try another filter.';
$string['nohistory'] = 'Nothing to show yet.';
$string['note'] = 'Note';
$string['note_help'] = 'Once the student can see the case, this note is shown to them. Use a private note for internal remarks.';
$string['opened'] = 'Opened {$a}';
$string['outcome'] = 'Outcome';
$string['outcome:markreduced'] = 'mark reduced';
$string['outcome:nocase'] = 'no case to answer';
$string['outcome:referred'] = 'referred to a panel';
$string['outcome:resubmit'] = 'resubmission required';
$string['outcome:warning'] = 'educational warning';
$string['outcomerequired'] = 'Choose an outcome to record a decision.';
$string['pluginname'] = 'Academic integrity cases';
$string['privacy:metadata:case'] = 'Academic integrity cases.';
$string['privacy:metadata:case:createdby'] = 'The staff member who opened the case.';
$string['privacy:metadata:case:handlerid'] = 'The staff member handling the case.';
$string['privacy:metadata:case:outcome'] = 'The decision.';
$string['privacy:metadata:case:stage'] = 'Where the case is.';
$string['privacy:metadata:case:studentid'] = 'The student the case is about.';
$string['privacy:metadata:case:summary'] = 'What was found.';
$string['privacy:metadata:case:timecreated'] = 'When the case was opened.';
$string['privacy:metadata:core_files'] = 'Evidence files attached to a case.';
$string['privacy:metadata:core_message'] = 'Notifications about case updates.';
$string['privacy:metadata:event'] = 'Every action on a case: who acted, when, and what they wrote.';
$string['privacy:metadata:event:note'] = 'The note, response or appeal text.';
$string['privacy:metadata:event:timecreated'] = 'When the action happened.';
$string['privacy:metadata:event:userid'] = 'The user who acted.';
$string['privatenote'] = 'Private note';
$string['progress'] = 'Progress';
$string['respond'] = 'Send your response';
$string['response'] = 'Your response';
$string['staffonly'] = 'Staff only';
$string['stage'] = 'Stage';
$string['stage:appealed'] = 'appealed';
$string['stage:awaitingresponse'] = 'awaiting student response';
$string['stage:closed'] = 'closed';
$string['stage:decided'] = 'decided';
$string['stage:investigating'] = 'investigating';
$string['stage:reported'] = 'reported';
$string['step:current'] = 'current stage';
$string['step:done'] = 'done';
$string['step:skipped'] = 'skipped';
$string['step:todo'] = 'not reached';
$string['student'] = 'Student';
$string['studentcannotsee'] = 'The student cannot see this case yet.';
$string['studentvisible'] = 'Visible to the student';
$string['summary'] = 'What was found';
$string['timeclosed'] = 'Time closed';
$string['updated'] = 'Updated';
$string['yourcase'] = 'Your integrity case';
$string['yourcases'] = 'Your integrity cases';
$string['yourside'] = 'Your side of the case';
