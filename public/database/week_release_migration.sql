-- Smart Campus Management System
-- Week-by-week timetable migration and release workflow support

USE capstone_db;

-- =========================================================
-- 1) ALTER TABLE: timetables
-- =========================================================
ALTER TABLE timetables
    ADD COLUMN week_start_date DATE NULL,
    ADD COLUMN week_end_date DATE NULL,
    ADD COLUMN status VARCHAR(30) DEFAULT 'pending',
    ADD COLUMN created_by INT NULL,
    ADD COLUMN released_at DATETIME NULL;

ALTER TABLE timetables
    ADD INDEX idx_week_dates (week_start_date, week_end_date),
    ADD INDEX idx_status (status),
    ADD CONSTRAINT fk_timetables_created_by
        FOREIGN KEY (created_by) REFERENCES users(user_id);

-- Optional but recommended: one class slot per section/week/day/time.
ALTER TABLE timetables
    ADD UNIQUE KEY uq_timetables_week_slot (section_id, week_start_date, day_of_week, start_time, end_time);

-- =========================================================
-- 2) ALTER TABLE: schedule_requests
-- =========================================================
ALTER TABLE schedule_requests
    ADD COLUMN week_start_date DATE NULL,
    ADD COLUMN week_end_date DATE NULL,
    ADD COLUMN approved_by INT NULL,
    ADD COLUMN approved_at DATETIME NULL,
    ADD COLUMN rejection_reason TEXT NULL;

ALTER TABLE schedule_requests
    ADD INDEX idx_schedule_requests_week (week_start_date, week_end_date),
    ADD INDEX idx_schedule_requests_status (status),
    ADD CONSTRAINT fk_schedule_requests_approved_by
        FOREIGN KEY (approved_by) REFERENCES users(user_id);

-- Backfill week dates for any existing requests without explicit target week.
UPDATE schedule_requests
SET week_start_date = COALESCE(week_start_date, DATE(requested_at)),
    week_end_date = COALESCE(week_end_date, DATE_ADD(DATE(requested_at), INTERVAL 6 DAY))
WHERE week_start_date IS NULL OR week_end_date IS NULL;

ALTER TABLE schedule_requests
    MODIFY COLUMN week_start_date DATE NOT NULL,
    MODIFY COLUMN week_end_date DATE NOT NULL;

-- =========================================================
-- 3) UPDATE existing timetable rows to week-specific values
-- =========================================================
-- Example for a known week label:
UPDATE timetables
SET week_start_date = '2026-03-02',
    week_end_date = '2026-03-08',
    status = 'released',
    released_at = COALESCE(released_at, NOW())
WHERE week = 'Mar 2 - Mar 8';

-- Generic conversion from existing week text labels (e.g. "Mar 2 - Mar 8").
UPDATE timetables
SET week_start_date = COALESCE(
        week_start_date,
        COALESCE(
            STR_TO_DATE(CONCAT(SUBSTRING_INDEX(week, ' - ', 1), ' ', YEAR(CURDATE())), '%b %e %Y'),
            STR_TO_DATE(CONCAT(SUBSTRING_INDEX(week, ' - ', 1), ' ', YEAR(CURDATE())), '%M %e %Y')
        )
    ),
    week_end_date = COALESCE(
        week_end_date,
        COALESCE(
            STR_TO_DATE(CONCAT(SUBSTRING_INDEX(week, ' - ', -1), ' ', YEAR(CURDATE())), '%b %e %Y'),
            STR_TO_DATE(CONCAT(SUBSTRING_INDEX(week, ' - ', -1), ' ', YEAR(CURDATE())), '%M %e %Y')
        )
    )
WHERE week_start_date IS NULL OR week_end_date IS NULL;

-- Release current/past weeks and keep future weeks pending.
UPDATE timetables
SET status = CASE
        WHEN week_start_date <= CURDATE() THEN 'released'
        ELSE 'pending'
    END,
    released_at = CASE
        WHEN week_start_date <= CURDATE() THEN COALESCE(released_at, NOW())
        ELSE NULL
    END
WHERE week_start_date IS NOT NULL;

ALTER TABLE timetables
    MODIFY COLUMN week_start_date DATE NOT NULL,
    MODIFY COLUMN week_end_date DATE NOT NULL;

-- =========================================================
-- End of migration script
-- =========================================================
