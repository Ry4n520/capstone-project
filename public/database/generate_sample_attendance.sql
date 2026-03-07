-- Generate sample attendance for past sessions only.
-- Weights: present 80%, absent 15%, late 5%.
-- Safe to run multiple times due to NOT EXISTS duplicate guard.

USE capstone_db;

INSERT INTO attendance (enrollment_id, session_id, status, marked_at)
SELECT
    x.enrollment_id,
    x.session_id,
    CASE
        WHEN x.random_value < 0.80 THEN 'present'
        WHEN x.random_value < 0.95 THEN 'absent'
        ELSE 'late'
    END AS status,
    CAST(CONCAT(x.session_date, ' 08:00:00') AS DATETIME) AS marked_at
FROM (
    SELECT
        e.enrollment_id,
        cs.session_id,
        cs.session_date,
        RAND() AS random_value
    FROM class_sessions cs
    JOIN timetables t ON cs.timetable_id = t.timetable_id
    JOIN course_sections sec ON t.section_id = sec.section_id
    JOIN enrollments e ON sec.section_id = e.section_id
    WHERE cs.session_date < CURDATE()
      AND e.status = 'active'
      AND NOT EXISTS (
          SELECT 1
          FROM attendance a
          WHERE a.session_id = cs.session_id
            AND a.enrollment_id = e.enrollment_id
      )
) x;

-- Verification breakdown
SELECT status, COUNT(*) AS total
FROM attendance
GROUP BY status
ORDER BY status;
