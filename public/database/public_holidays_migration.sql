-- =====================================================
-- Public Holidays Table and Data Migration
-- =====================================================
-- Creates table for Malaysian public holidays and
-- excludes them from timetable class sessions and bookings
-- =====================================================

-- Create public_holidays table
CREATE TABLE IF NOT EXISTS public_holidays (
    holiday_id INT AUTO_INCREMENT PRIMARY KEY,
    holiday_name VARCHAR(150) NOT NULL,
    holiday_date DATE NOT NULL UNIQUE,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_holiday_date (holiday_date)
);

-- Insert Malaysian public holidays for 2026
INSERT INTO public_holidays (holiday_name, holiday_date, description) VALUES
    ('New Year\'s Day', '2026-01-01', 'New Year'),
    ('Federal Territory Day', '2026-02-01', 'Federal Territory Day'),
    ('Chinese New Year', '2026-02-17', 'Chinese New Year Day 1'),
    ('Chinese New Year', '2026-02-18', 'Chinese New Year Day 2'),
    ('Labour Day', '2026-05-01', 'International Workers Day'),
    ('Wesak Day', '2026-05-31', 'Buddha\'s Birthday'),
    ('Yang di-Pertuan Agong Birthday', '2026-06-06', 'King\'s Birthday'),
    ('Hari Raya Aidiladha', '2026-06-16', 'Feast of Sacrifice'),
    ('Awal Muharram', '2026-07-07', 'Islamic New Year'),
    ('National Day', '2026-08-31', 'Independence Day'),
    ('Prophet Muhammad\'s Birthday', '2026-09-15', 'Maulid Nabi'),
    ('Malaysia Day', '2026-09-16', 'Malaysia Day'),
    ('Deepavali', '2026-11-05', 'Festival of Lights'),
    ('Christmas Day', '2026-12-25', 'Christmas')
ON DUPLICATE KEY UPDATE 
    holiday_name = VALUES(holiday_name),
    description = VALUES(description);

-- =====================================================
-- Modified Class Session Generation
-- =====================================================
-- This query generates class sessions from timetables
-- while EXCLUDING public holidays
-- =====================================================

-- Note: This is a template query. Run this when generating new class sessions.
-- The actual generation should be done in application code or scheduled job.

/*
INSERT INTO class_sessions (timetable_id, session_date, attendance_code, code_expiry, created_at)
SELECT 
    t.timetable_id,
    DATE_ADD(t.week_start_date, 
        INTERVAL (CASE t.day_of_week
            WHEN 'Monday' THEN 0
            WHEN 'Tuesday' THEN 1
            WHEN 'Wednesday' THEN 2
            WHEN 'Thursday' THEN 3
            WHEN 'Friday' THEN 4
        END) DAY
    ) as session_date,
    NULL as attendance_code,
    NULL as code_expiry,
    NOW() as created_at
FROM timetables t
WHERE t.status = 'released'
  -- Only generate for dates up to today
  AND DATE_ADD(t.week_start_date, 
      INTERVAL (CASE t.day_of_week
          WHEN 'Monday' THEN 0
          WHEN 'Tuesday' THEN 1
          WHEN 'Wednesday' THEN 2
          WHEN 'Thursday' THEN 3
          WHEN 'Friday' THEN 4
      END) DAY
  ) <= CURDATE()
  -- EXCLUDE PUBLIC HOLIDAYS (No classes on public holidays)
  AND DATE_ADD(t.week_start_date, 
      INTERVAL (CASE t.day_of_week
          WHEN 'Monday' THEN 0
          WHEN 'Tuesday' THEN 1
          WHEN 'Wednesday' THEN 2
          WHEN 'Thursday' THEN 3
          WHEN 'Friday' THEN 4
      END) DAY
  ) NOT IN (SELECT holiday_date FROM public_holidays)
  -- Prevent duplicate sessions
  AND NOT EXISTS (
      SELECT 1 FROM class_sessions cs 
      WHERE cs.timetable_id = t.timetable_id 
        AND cs.session_date = DATE_ADD(t.week_start_date, 
            INTERVAL (CASE t.day_of_week
                WHEN 'Monday' THEN 0
                WHEN 'Tuesday' THEN 1
                WHEN 'Wednesday' THEN 2
                WHEN 'Thursday' THEN 3
                WHEN 'Friday' THEN 4
            END) DAY
        )
  );
*/

-- =====================================================
-- IMPORTANT NOTES
-- =====================================================
-- 1. Timetable classes: Monday-Friday only (no weekend classes)
-- 2. Facility bookings: 24/7 access, any day EXCEPT public holidays
-- 3. Class sessions automatically skip public holidays
-- 4. Public holidays are checked in real-time during booking
-- =====================================================
