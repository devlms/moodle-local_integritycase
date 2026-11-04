# local_integritycase design (2026-11-04)

## Why
Detection plugins (Turnitin, Originality.ai, Copyleaks, Compilatio, Pangram) put a flag next to a
submission. Every vendor says a flag is not a finding. What happens next, the review, the student's
side, the decision, the appeal, lives in email threads and spreadsheets. Nothing on the Moodle
Marketplace tracks the case itself (checked 2026-11-04: the "Academic integrity" plugin is a statement
students accept; the rest are detectors or proctoring). Demand seen: Upwork job "Academic Integrity
Decision Support System" (Nov 2026), plus university policy workflows (Monash AI statement workflow).

Built as an open source DevLMS showcase: real workflow, permissions, audit trail, privacy, reporting.

## Goals
- Teachers open a case on a student in a course, optionally linked to an activity.
- Fixed stages: reported, investigating, awaiting response, decided, appealed, closed.
  Allowed moves are enforced on the server. Closed cases can be reopened by a manager of the case.
- The student sees nothing while staff investigate. The case becomes visible to the student when it
  first reaches "awaiting response" or "decided", and stays visible after that.
- The student can write a response while the case awaits it, and lodge an appeal once decided.
- A decision needs an outcome: no case to answer, educational warning, resubmission, mark reduced,
  referred to a panel.
- Every action is an event row: who, when, from and to stage, note, whether the student can see it.
  Staff notes are never shown to the student.
- Evidence files on the case (staff only).
- Notifications through the message API: student on stage changes they can see; the case handler
  when the student responds or appeals.
- Moodle log events: case created, case updated.
- Privacy provider: export and delete for students, handlers and note authors, evidence files.
- Report builder data source "Academic integrity cases", available only to users with
  local/integritycase:viewall at site level.

## Non-goals (0.1)
- Detecting anything. Pairs with any detector.
- Hooking into the assignment grader. Entry is a course navigation link "Integrity cases".
- Configurable stage lists, panels, multi-person decisions.

## Anti-goals
- A student ever seeing staff notes or a case under investigation.
- Any automatic decision.

## Capabilities
- local/integritycase:view (course): editingteacher, teacher, manager.
- local/integritycase:manage (course): editingteacher, manager. Open, move, decide, notes, evidence.
- local/integritycase:respond (course): student. See own visible cases, respond, appeal.
- local/integritycase:viewall (system): manager. Report builder source.

## Data
local_integritycase: id, courseid, studentid, cmid (0 none), handlerid, createdby, summary,
summaryformat, stage, outcome (null until decided), studentvisible, timecreated, timemodified,
timeclosed.
local_integritycase_event: id, caseid, userid, action, fromstage, tostage, outcome, note, noteformat,
visibletostudent, timecreated.

## Builder's calls
Page layout, string wording, form details.
