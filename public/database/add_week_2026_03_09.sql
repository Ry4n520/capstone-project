USE capstone_db;

INSERT INTO timetables (
    section_id,
    room_id,
    month,
    week,
    week_start_date,
    week_end_date,
    day_of_week,
    start_time,
    end_time,
    session_code,
    status,
    created_by,
    released_at
) VALUES
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC210-G1'), (SELECT room_id FROM classrooms WHERE room_name = 'Block A-101'), 'March', 'Mar 9 - Mar 15', '2026-03-09', '2026-03-15', 'Monday', '09:00:00', '10:30:00', 'DS-MON-09-W2', 'released', (SELECT user_id FROM users WHERE email = 'admin@campus.edu'), NOW()),
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC210-G1'), (SELECT room_id FROM classrooms WHERE room_name = 'Lab C-105'), 'March', 'Mar 9 - Mar 15', '2026-03-09', '2026-03-15', 'Wednesday', '14:00:00', '15:30:00', 'DS-WED-14-W2', 'released', (SELECT user_id FROM users WHERE email = 'admin@campus.edu'), NOW()),
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC210-G2'), (SELECT room_id FROM classrooms WHERE room_name = 'Block B-203'), 'March', 'Mar 9 - Mar 15', '2026-03-09', '2026-03-15', 'Tuesday', '10:00:00', '11:30:00', 'DS2-TUE-10-W2', 'released', (SELECT user_id FROM users WHERE email = 'admin@campus.edu'), NOW()),
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC210-G2'), (SELECT room_id FROM classrooms WHERE room_name = 'Lab C-106'), 'March', 'Mar 9 - Mar 15', '2026-03-09', '2026-03-15', 'Friday', '13:00:00', '14:30:00', 'DS2-FRI-13-W2', 'released', (SELECT user_id FROM users WHERE email = 'admin@campus.edu'), NOW()),
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC220-G1'), (SELECT room_id FROM classrooms WHERE room_name = 'Block A-102'), 'March', 'Mar 9 - Mar 15', '2026-03-09', '2026-03-15', 'Tuesday', '09:00:00', '10:30:00', 'DB-TUE-09-W2', 'released', (SELECT user_id FROM users WHERE email = 'admin@campus.edu'), NOW()),
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC220-G1'), (SELECT room_id FROM classrooms WHERE room_name = 'Lab C-105'), 'March', 'Mar 9 - Mar 15', '2026-03-09', '2026-03-15', 'Thursday', '15:00:00', '16:30:00', 'DB-THU-15-W2', 'released', (SELECT user_id FROM users WHERE email = 'admin@campus.edu'), NOW()),
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC220-G2'), (SELECT room_id FROM classrooms WHERE room_name = 'Block B-204'), 'March', 'Mar 9 - Mar 15', '2026-03-09', '2026-03-15', 'Monday', '11:00:00', '12:30:00', 'DB2-MON-11-W2', 'released', (SELECT user_id FROM users WHERE email = 'admin@campus.edu'), NOW()),
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC220-G2'), (SELECT room_id FROM classrooms WHERE room_name = 'Lab C-106'), 'March', 'Mar 9 - Mar 15', '2026-03-09', '2026-03-15', 'Wednesday', '15:00:00', '16:30:00', 'DB2-WED-15-W2', 'released', (SELECT user_id FROM users WHERE email = 'admin@campus.edu'), NOW()),
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC230-G1'), (SELECT room_id FROM classrooms WHERE room_name = 'Room A-301'), 'March', 'Mar 9 - Mar 15', '2026-03-09', '2026-03-15', 'Wednesday', '09:00:00', '10:30:00', 'SE-WED-09-W2', 'released', (SELECT user_id FROM users WHERE email = 'admin@campus.edu'), NOW()),
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC230-G1'), (SELECT room_id FROM classrooms WHERE room_name = 'Block B-203'), 'March', 'Mar 9 - Mar 15', '2026-03-09', '2026-03-15', 'Friday', '10:00:00', '11:30:00', 'SE-FRI-10-W2', 'released', (SELECT user_id FROM users WHERE email = 'admin@campus.edu'), NOW()),
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC240-G1'), (SELECT room_id FROM classrooms WHERE room_name = 'Block D-110'), 'March', 'Mar 9 - Mar 15', '2026-03-09', '2026-03-15', 'Thursday', '10:00:00', '11:30:00', 'CN-THU-10-W2', 'released', (SELECT user_id FROM users WHERE email = 'admin@campus.edu'), NOW()),
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC240-G1'), (SELECT room_id FROM classrooms WHERE room_name = 'Block A-102'), 'March', 'Mar 9 - Mar 15', '2026-03-09', '2026-03-15', 'Monday', '14:00:00', '15:30:00', 'CN-MON-14-W2', 'released', (SELECT user_id FROM users WHERE email = 'admin@campus.edu'), NOW()),
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC250-G1'), (SELECT room_id FROM classrooms WHERE room_name = 'Block B-204'), 'March', 'Mar 9 - Mar 15', '2026-03-09', '2026-03-15', 'Tuesday', '14:00:00', '15:30:00', 'WD-TUE-14-W2', 'released', (SELECT user_id FROM users WHERE email = 'admin@campus.edu'), NOW()),
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC250-G1'), (SELECT room_id FROM classrooms WHERE room_name = 'Lab C-105'), 'March', 'Mar 9 - Mar 15', '2026-03-09', '2026-03-15', 'Friday', '14:00:00', '15:30:00', 'WD-FRI-14-W2', 'released', (SELECT user_id FROM users WHERE email = 'admin@campus.edu'), NOW()),
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC260-G1'), (SELECT room_id FROM classrooms WHERE room_name = 'Block A-101'), 'March', 'Mar 9 - Mar 15', '2026-03-09', '2026-03-15', 'Wednesday', '11:00:00', '12:30:00', 'ALG-WED-11-W2', 'released', (SELECT user_id FROM users WHERE email = 'admin@campus.edu'), NOW()),
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC260-G1'), (SELECT room_id FROM classrooms WHERE room_name = 'Block D-110'), 'March', 'Mar 9 - Mar 15', '2026-03-09', '2026-03-15', 'Friday', '11:00:00', '12:30:00', 'ALG-FRI-11-W2', 'released', (SELECT user_id FROM users WHERE email = 'admin@campus.edu'), NOW()),
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC270-G1'), (SELECT room_id FROM classrooms WHERE room_name = 'Block A-102'), 'March', 'Mar 9 - Mar 15', '2026-03-09', '2026-03-15', 'Monday', '10:00:00', '11:30:00', 'OOP-MON-10-W2', 'released', (SELECT user_id FROM users WHERE email = 'admin@campus.edu'), NOW()),
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC270-G1'), (SELECT room_id FROM classrooms WHERE room_name = 'Lab C-106'), 'March', 'Mar 9 - Mar 15', '2026-03-09', '2026-03-15', 'Thursday', '13:00:00', '14:30:00', 'OOP-THU-13-W2', 'released', (SELECT user_id FROM users WHERE email = 'admin@campus.edu'), NOW())
ON DUPLICATE KEY UPDATE
    room_id = VALUES(room_id),
    session_code = VALUES(session_code),
    status = VALUES(status),
    released_at = VALUES(released_at);
