-- Sample Announcements for Smart Campus System
-- Insert these to populate the announcements dropdown
-- Run Date: March 10, 2026

-- Recent announcement (today)
INSERT INTO announcements (user_id, title, content, created_date, target_role_id) VALUES
    ((SELECT user_id FROM users WHERE email = 'admin@campus.edu' LIMIT 1), 
     'Campus WiFi Maintenance', 
     'WiFi services will be temporarily disrupted on March 12, 2026 from 2:00 AM to 4:00 AM for scheduled maintenance. Please plan accordingly.',
     NOW(),
     NULL);

-- Yesterday
INSERT INTO announcements (user_id, title, content, created_date, target_role_id) VALUES
    ((SELECT user_id FROM users WHERE email = 'admin@campus.edu' LIMIT 1), 
     'New Library Hours',
     'The library will extend its operating hours during exam week. Open from 7:00 AM to 11:00 PM starting next Monday. Good luck with your exams!',
     DATE_SUB(NOW(), INTERVAL 1 DAY),
     NULL);

-- 3 days ago
INSERT INTO announcements (user_id, title, content, created_date, target_role_id) VALUES
    ((SELECT user_id FROM users WHERE email = 'admin@campus.edu' LIMIT 1), 
     'Career Fair 2026',
     'Join us for the annual Career Fair on March 20, 2026. Meet potential employers from top tech companies. Register at the Student Services office.',
     DATE_SUB(NOW(), INTERVAL 3 DAY),
     NULL);

-- 5 days ago    
INSERT INTO announcements (user_id, title, content, created_date, target_role_id) VALUES
    ((SELECT user_id FROM users WHERE email = 'admin@campus.edu' LIMIT 1), 
     'Sports Day Registration Open',
     'Registration is now open for Sports Day 2026! Sign up for your favorite events at the Recreation Center. Limited slots available.',
     DATE_SUB(NOW(), INTERVAL 5 DAY),
     NULL);

-- 1 week ago - Student only
INSERT INTO announcements (user_id, title, content, created_date, target_role_id) VALUES
    ((SELECT user_id FROM users WHERE email = 'admin@campus.edu' LIMIT 1), 
     'Assignment Deadline Reminder',
     'Reminder: All outstanding assignments must be submitted by March 15, 2026. Late submissions will incur penalties.',
     DATE_SUB(NOW(), INTERVAL 7 DAY),
     (SELECT role_id FROM roles WHERE role_name = 'student' LIMIT 1));

-- 2 weeks ago
INSERT INTO announcements (user_id, title, content, created_date, target_role_id) VALUES
    ((SELECT user_id FROM users WHERE email = 'admin@campus.edu' LIMIT 1), 
     'Campus Security Update',
     'New security measures have been implemented. All students must present their ID cards when entering campus buildings.',
     DATE_SUB(NOW(), INTERVAL 14 DAY),
     NULL);
