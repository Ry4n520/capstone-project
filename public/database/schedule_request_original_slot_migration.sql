USE capstone_db;

ALTER TABLE schedule_requests
    ADD COLUMN source_timetable_id INT NULL AFTER request_id,
    ADD COLUMN original_room_id INT NULL AFTER room_id,
    ADD COLUMN original_day_of_week VARCHAR(20) NULL AFTER original_room_id,
    ADD COLUMN original_start_time TIME NULL AFTER week_end_date,
    ADD COLUMN original_end_time TIME NULL AFTER original_start_time,
    ADD CONSTRAINT fk_schedule_requests_source_timetable
        FOREIGN KEY (source_timetable_id) REFERENCES timetables(timetable_id),
    ADD CONSTRAINT fk_schedule_requests_original_room
        FOREIGN KEY (original_room_id) REFERENCES classrooms(room_id);

UPDATE schedule_requests sr
JOIN timetables t
  ON t.section_id = sr.section_id
 AND t.week_start_date = sr.week_start_date
 AND t.start_time = sr.start_time
 AND t.end_time = sr.end_time
SET sr.source_timetable_id = COALESCE(sr.source_timetable_id, t.timetable_id),
    sr.original_room_id = COALESCE(sr.original_room_id, t.room_id),
    sr.original_day_of_week = COALESCE(sr.original_day_of_week, t.day_of_week),
    sr.original_start_time = COALESCE(sr.original_start_time, t.start_time),
    sr.original_end_time = COALESCE(sr.original_end_time, t.end_time)
WHERE sr.source_timetable_id IS NULL;

UPDATE schedule_requests sr
JOIN timetables t
  ON t.section_id = sr.section_id
 AND t.week_start_date = sr.week_start_date
 AND t.day_of_week = sr.day_of_week
SET sr.source_timetable_id = COALESCE(sr.source_timetable_id, t.timetable_id),
    sr.original_room_id = COALESCE(sr.original_room_id, t.room_id),
    sr.original_day_of_week = COALESCE(sr.original_day_of_week, t.day_of_week),
    sr.original_start_time = COALESCE(sr.original_start_time, t.start_time),
    sr.original_end_time = COALESCE(sr.original_end_time, t.end_time)
WHERE sr.source_timetable_id IS NULL;