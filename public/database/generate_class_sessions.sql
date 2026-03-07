-- Generate class sessions from released timetable slots.
-- Safe to run multiple times due to NOT EXISTS duplicate guard.

USE capstone_db;

INSERT INTO class_sessions (timetable_id, session_date, attendance_code, code_expiry, created_at)
SELECT
    c.timetable_id,
    c.session_date,
    NULL AS attendance_code,
    NULL AS code_expiry,
    NOW() AS created_at
FROM (
    SELECT
        t.timetable_id,
        DATE_ADD(
            t.week_start_date,
            INTERVAL (
                CASE t.day_of_week
                    WHEN 'Monday' THEN 0
                    WHEN 'Tuesday' THEN 1
                    WHEN 'Wednesday' THEN 2
                    WHEN 'Thursday' THEN 3
                    WHEN 'Friday' THEN 4
                    ELSE 0
                END
            ) DAY
        ) AS session_date
    FROM timetables t
    WHERE t.status = 'released'
) c
WHERE c.session_date <= CURDATE()
  AND NOT EXISTS (
      SELECT 1
      FROM class_sessions existing_cs
      WHERE existing_cs.timetable_id = c.timetable_id
        AND existing_cs.session_date = c.session_date
  );

-- Verification
SELECT COUNT(*) AS total_class_sessions FROM class_sessions;
