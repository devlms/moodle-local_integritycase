# Changes

## 1.0.0 (2026-11-08)

First stable release.

- Deleting a course removes its cases, their history and the evidence files.
- When a case's handler is suspended, deleted or no longer manages cases, student responses and appeals go to every case manager in the course instead of nobody.
- Activity names with "&" no longer show as "&amp;".
- CI covers Moodle 4.5, 5.0, 5.1, 5.2 and 5.3 on PostgreSQL and MariaDB.

Known limits, unchanged: a student's privacy export includes the staff notes and evidence of their own case (their right of access), and case managers see every case in the course regardless of groups.

## 0.1.0 (2026-11-04)

First alpha.
