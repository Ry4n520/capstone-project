/* Smart Campus Management System - Database Schema */
/* Overhauled design with Course Sections, Class Sessions, and Schedule Requests */

CREATE DATABASE IF NOT EXISTS capstone_db;
USE capstone_db;

-- ===========================================
-- 1. Role Table
-- Stores system roles (Admin, Lecturer, Student)
-- ===========================================
CREATE TABLE IF NOT EXISTS roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    role_description VARCHAR(255)
);

-- ===========================================
-- 2. User Table
-- Stores all system users
-- ===========================================
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    gender VARCHAR(20),
    phone VARCHAR(30),
    email VARCHAR(120) UNIQUE NOT NULL,
    profile_image VARCHAR(255),
    password_hash VARCHAR(255) NOT NULL,
    date_joined DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id) REFERENCES roles(role_id)
);

-- ===========================================
-- 3. Course Table
-- Stores available courses
-- ===========================================
CREATE TABLE IF NOT EXISTS courses (
    course_id INT AUTO_INCREMENT PRIMARY KEY,
    course_name VARCHAR(150) NOT NULL,
    credit_hour INT NOT NULL
);

-- ===========================================
-- 4. Course_Section Table
-- Represents course groups taught by lecturers
-- Example: Group 1, Group 2
-- ===========================================
CREATE TABLE IF NOT EXISTS course_sections (
    section_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    lecturer_id INT NOT NULL,
    section_code VARCHAR(50),
    semester VARCHAR(20),
    year INT,
    CONSTRAINT fk_course_sections_course
        FOREIGN KEY (course_id) REFERENCES courses(course_id),
    CONSTRAINT fk_course_sections_lecturer
        FOREIGN KEY (lecturer_id) REFERENCES users(user_id)
);

-- ===========================================
-- 5. Enrollment Table
-- Stores student enrollment into course sections
-- ===========================================
CREATE TABLE IF NOT EXISTS enrollments (
    enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    section_id INT NOT NULL,
    enrollment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(30) DEFAULT 'active',
    CONSTRAINT fk_enrollments_student
        FOREIGN KEY (student_id) REFERENCES users(user_id),
    CONSTRAINT fk_enrollments_section
        FOREIGN KEY (section_id) REFERENCES course_sections(section_id)
);

-- ===========================================
-- 6. Classroom Table
-- Stores classroom information
-- ===========================================
CREATE TABLE IF NOT EXISTS classrooms (
    room_id INT AUTO_INCREMENT PRIMARY KEY,
    room_name VARCHAR(100) NOT NULL,
    building VARCHAR(100),
    capacity INT,
    room_type VARCHAR(50)
);

-- ===========================================
-- 7. Schedule_Request Table
-- Stores classroom schedule requests by lecturers
-- ===========================================
CREATE TABLE IF NOT EXISTS schedule_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    room_id INT NOT NULL,
    day_of_week VARCHAR(20) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status VARCHAR(30) DEFAULT 'pending',
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_schedule_requests_section
        FOREIGN KEY (section_id) REFERENCES course_sections(section_id),
    CONSTRAINT fk_schedule_requests_room
        FOREIGN KEY (room_id) REFERENCES classrooms(room_id)
);

-- ===========================================
-- 8. Timetable Table
-- Stores approved class schedules
-- ===========================================
CREATE TABLE IF NOT EXISTS timetables (
    timetable_id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    room_id INT NOT NULL,
    month VARCHAR(20) NOT NULL,
    week VARCHAR(50) NOT NULL,
    day_of_week VARCHAR(20) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    session_code VARCHAR(50),
    CONSTRAINT fk_timetables_section
        FOREIGN KEY (section_id) REFERENCES course_sections(section_id),
    CONSTRAINT fk_timetables_room
        FOREIGN KEY (room_id) REFERENCES classrooms(room_id)
);

-- ===========================================
-- 9. Class_Session Table
-- Represents actual class occurrences on specific dates
-- ===========================================
CREATE TABLE IF NOT EXISTS class_sessions (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    timetable_id INT NOT NULL,
    session_date DATE NOT NULL,
    attendance_code VARCHAR(10),
    code_expiry DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_class_sessions_timetable
        FOREIGN KEY (timetable_id) REFERENCES timetables(timetable_id)
);

-- ===========================================
-- 10. Attendance Table
-- Stores student attendance records
-- ===========================================
CREATE TABLE IF NOT EXISTS attendance (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_id INT NOT NULL,
    session_id INT NOT NULL,
    status VARCHAR(30) NOT NULL,
    marked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_attendance_enrollment
        FOREIGN KEY (enrollment_id) REFERENCES enrollments(enrollment_id),
    CONSTRAINT fk_attendance_session
        FOREIGN KEY (session_id) REFERENCES class_sessions(session_id)
);

-- ===========================================
-- 11. Facility Table
-- Stores campus facilities
-- ===========================================
CREATE TABLE IF NOT EXISTS facilities (
    facility_id INT AUTO_INCREMENT PRIMARY KEY,
    facility_name VARCHAR(120) NOT NULL,
    location VARCHAR(120),
    facility_type VARCHAR(50)
);

-- ===========================================
-- 12. Booking Table
-- Stores facility reservations
-- ===========================================
CREATE TABLE IF NOT EXISTS bookings (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    facility_id INT NOT NULL,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    booking_status VARCHAR(30) NOT NULL,
    CONSTRAINT fk_bookings_user
        FOREIGN KEY (user_id) REFERENCES users(user_id),
    CONSTRAINT fk_bookings_facility
        FOREIGN KEY (facility_id) REFERENCES facilities(facility_id)
);

-- ===========================================
-- 13. Announcement Table
-- Stores system announcements
-- ===========================================
CREATE TABLE IF NOT EXISTS announcements (
    announcement_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    content TEXT NOT NULL,
    created_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    target_role_id INT,
    CONSTRAINT fk_announcements_user
        FOREIGN KEY (user_id) REFERENCES users(user_id),
    CONSTRAINT fk_announcements_role
        FOREIGN KEY (target_role_id) REFERENCES roles(role_id)
);

-- ===========================================
-- Seed Data
-- ===========================================

-- Insert default roles
INSERT INTO roles (role_name, role_description) VALUES
    ('admin', 'System administrator'),
    ('student', 'Student user'),
    ('staff', 'Staff/Lecturer user')
ON DUPLICATE KEY UPDATE role_description = VALUES(role_description);

-- Insert test users - Password: password123
INSERT INTO users (name, email, password_hash, role_id, gender, phone)
VALUES 
    ('John Student', 'john.student@campus.edu', 'password123', (SELECT role_id FROM roles WHERE role_name = 'student'), 'Male', '0123456789'),
    ('Sarah Osman', 'sarah.osman@campus.edu', 'password123', (SELECT role_id FROM roles WHERE role_name = 'student'), 'Female', '0123456790'),
    ('Ali Rahman', 'ali.rahman@campus.edu', 'password123', (SELECT role_id FROM roles WHERE role_name = 'student'), 'Male', '0123456791'),
    ('Maya Sern', 'maya.sern@campus.edu', 'password123', (SELECT role_id FROM roles WHERE role_name = 'student'), 'Female', '0123456792'),
    ('Prof. Smith', 'smith@campus.edu', 'password123', (SELECT role_id FROM roles WHERE role_name = 'staff'), 'Male', '0187654321'),
    ('Dr. Lim', 'lim@campus.edu', 'password123', (SELECT role_id FROM roles WHERE role_name = 'staff'), 'Male', '0187654322'),
    ('Ms. Farah', 'farah@campus.edu', 'password123', (SELECT role_id FROM roles WHERE role_name = 'staff'), 'Female', '0187654323'),
    ('Mr. Kumar', 'kumar@campus.edu', 'password123', (SELECT role_id FROM roles WHERE role_name = 'staff'), 'Male', '0187654324'),
    ('Admin User', 'admin@campus.edu', 'password123', (SELECT role_id FROM roles WHERE role_name = 'admin'), 'Male', '0198765432')
ON DUPLICATE KEY UPDATE email = VALUES(email);

-- Insert courses
INSERT INTO courses (course_name, credit_hour) VALUES
    ('Data Structures', 3),
    ('Database Systems', 3),
    ('Software Engineering', 3),
    ('Computer Networks', 3),
    ('Web Development', 3),
    ('Algorithms', 3),
    ('Operating Systems', 3),
    ('Object-Oriented Programming', 3)
ON DUPLICATE KEY UPDATE credit_hour = VALUES(credit_hour);

-- Insert classrooms
INSERT INTO classrooms (room_name, building, capacity, room_type) VALUES
    ('Block A-101', 'Block A', 40, 'Lecture Hall'),
    ('Block A-102', 'Block A', 40, 'Lecture Hall'),
    ('Block B-203', 'Block B', 35, 'Seminar Room'),
    ('Block B-204', 'Block B', 35, 'Seminar Room'),
    ('Lab C-105', 'Block C', 30, 'Computer Lab'),
    ('Lab C-106', 'Block C', 30, 'Computer Lab'),
    ('Room A-301', 'Block A', 50, 'Lecture Hall'),
    ('Block D-110', 'Block D', 45, 'Lecture Hall')
ON DUPLICATE KEY UPDATE capacity = VALUES(capacity);

-- Insert course sections (lecturers teaching different groups of courses)
INSERT INTO course_sections (course_id, lecturer_id, section_code, semester, year) VALUES
    -- Data Structures (CSC210) - Group 1 & 2
    (1, (SELECT user_id FROM users WHERE email = 'lim@campus.edu'), 'CSC210-G1', '2', 2026),
    (1, (SELECT user_id FROM users WHERE email = 'smith@campus.edu'), 'CSC210-G2', '2', 2026),
    -- Database Systems (CSC220) - Group 1 & 2
    (2, (SELECT user_id FROM users WHERE email = 'farah@campus.edu'), 'CSC220-G1', '2', 2026),
    (2, (SELECT user_id FROM users WHERE email = 'kumar@campus.edu'), 'CSC220-G2', '2', 2026),
    -- Software Engineering (CSC230)
    (3, (SELECT user_id FROM users WHERE email = 'smith@campus.edu'), 'CSC230-G1', '2', 2026),
    -- Computer Networks (CSC240)
    (4, (SELECT user_id FROM users WHERE email = 'lim@campus.edu'), 'CSC240-G1', '2', 2026),
    -- Web Development (CSC250)
    (5, (SELECT user_id FROM users WHERE email = 'farah@campus.edu'), 'CSC250-G1', '2', 2026),
    -- Algorithms (CSC260)
    (6, (SELECT user_id FROM users WHERE email = 'kumar@campus.edu'), 'CSC260-G1', '2', 2026),
    -- OOP (CSC270)
    (8, (SELECT user_id FROM users WHERE email = 'smith@campus.edu'), 'CSC270-G1', '2', 2026)
ON DUPLICATE KEY UPDATE section_code = VALUES(section_code);

-- Insert student enrollments
INSERT INTO enrollments (student_id, section_id, enrollment_date, status) VALUES
    -- John Student enrolled in
    ((SELECT user_id FROM users WHERE email = 'john.student@campus.edu'), (SELECT section_id FROM course_sections WHERE section_code = 'CSC210-G1'), NOW(), 'active'),
    ((SELECT user_id FROM users WHERE email = 'john.student@campus.edu'), (SELECT section_id FROM course_sections WHERE section_code = 'CSC220-G1'), NOW(), 'active'),
    ((SELECT user_id FROM users WHERE email = 'john.student@campus.edu'), (SELECT section_id FROM course_sections WHERE section_code = 'CSC230-G1'), NOW(), 'active'),
    ((SELECT user_id FROM users WHERE email = 'john.student@campus.edu'), (SELECT section_id FROM course_sections WHERE section_code = 'CSC240-G1'), NOW(), 'active'),
    -- Sarah Osman enrolled in
    ((SELECT user_id FROM users WHERE email = 'sarah.osman@campus.edu'), (SELECT section_id FROM course_sections WHERE section_code = 'CSC210-G1'), NOW(), 'active'),
    ((SELECT user_id FROM users WHERE email = 'sarah.osman@campus.edu'), (SELECT section_id FROM course_sections WHERE section_code = 'CSC220-G1'), NOW(), 'active'),
    ((SELECT user_id FROM users WHERE email = 'sarah.osman@campus.edu'), (SELECT section_id FROM course_sections WHERE section_code = 'CSC250-G1'), NOW(), 'active'),
    -- Ali Rahman enrolled in
    ((SELECT user_id FROM users WHERE email = 'ali.rahman@campus.edu'), (SELECT section_id FROM course_sections WHERE section_code = 'CSC210-G2'), NOW(), 'active'),
    ((SELECT user_id FROM users WHERE email = 'ali.rahman@campus.edu'), (SELECT section_id FROM course_sections WHERE section_code = 'CSC220-G2'), NOW(), 'active'),
    ((SELECT user_id FROM users WHERE email = 'ali.rahman@campus.edu'), (SELECT section_id FROM course_sections WHERE section_code = 'CSC260-G1'), NOW(), 'active'),
    -- Maya Sern enrolled in
    ((SELECT user_id FROM users WHERE email = 'maya.sern@campus.edu'), (SELECT section_id FROM course_sections WHERE section_code = 'CSC210-G2'), NOW(), 'active'),
    ((SELECT user_id FROM users WHERE email = 'maya.sern@campus.edu'), (SELECT section_id FROM course_sections WHERE section_code = 'CSC230-G1'), NOW(), 'active'),
    ((SELECT user_id FROM users WHERE email = 'maya.sern@campus.edu'), (SELECT section_id FROM course_sections WHERE section_code = 'CSC270-G1'), NOW(), 'active'),
    ((SELECT user_id FROM users WHERE email = 'maya.sern@campus.edu'), (SELECT section_id FROM course_sections WHERE section_code = 'CSC250-G1'), NOW(), 'active')
ON DUPLICATE KEY UPDATE enrollment_date = VALUES(enrollment_date);

-- Insert timetables (weekly schedule)
INSERT INTO timetables (section_id, room_id, month, week, day_of_week, start_time, end_time, session_code) VALUES
    -- CSC210-G1 (Data Structures - Dr. Lim)
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC210-G1'), (SELECT room_id FROM classrooms WHERE room_name = 'Block A-101'), 'March', 'Mar 2 - Mar 8', 'Monday', '09:00:00', '10:30:00', 'DS-MON-09'),
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC210-G1'), (SELECT room_id FROM classrooms WHERE room_name = 'Lab C-105'), 'March', 'Mar 2 - Mar 8', 'Wednesday', '14:00:00', '15:30:00', 'DS-WED-14'),
    -- CSC210-G2 (Data Structures - Prof. Smith)
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC210-G2'), (SELECT room_id FROM classrooms WHERE room_name = 'Block B-203'), 'March', 'Mar 2 - Mar 8', 'Tuesday', '10:00:00', '11:30:00', 'DS2-TUE-10'),
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC210-G2'), (SELECT room_id FROM classrooms WHERE room_name = 'Lab C-106'), 'March', 'Mar 2 - Mar 8', 'Friday', '13:00:00', '14:30:00', 'DS2-FRI-13'),
    -- CSC220-G1 (Database Systems - Ms. Farah)
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC220-G1'), (SELECT room_id FROM classrooms WHERE room_name = 'Block A-102'), 'March', 'Mar 2 - Mar 8', 'Tuesday', '09:00:00', '10:30:00', 'DB-TUE-09'),
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC220-G1'), (SELECT room_id FROM classrooms WHERE room_name = 'Lab C-105'), 'March', 'Mar 2 - Mar 8', 'Thursday', '15:00:00', '16:30:00', 'DB-THU-15'),
    -- CSC220-G2 (Database Systems - Mr. Kumar)
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC220-G2'), (SELECT room_id FROM classrooms WHERE room_name = 'Block B-204'), 'March', 'Mar 2 - Mar 8', 'Monday', '11:00:00', '12:30:00', 'DB2-MON-11'),
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC220-G2'), (SELECT room_id FROM classrooms WHERE room_name = 'Lab C-106'), 'March', 'Mar 2 - Mar 8', 'Wednesday', '15:00:00', '16:30:00', 'DB2-WED-15'),
    -- CSC230 (Software Engineering - Prof. Smith)
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC230-G1'), (SELECT room_id FROM classrooms WHERE room_name = 'Room A-301'), 'March', 'Mar 2 - Mar 8', 'Wednesday', '09:00:00', '10:30:00', 'SE-WED-09'),
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC230-G1'), (SELECT room_id FROM classrooms WHERE room_name = 'Block B-203'), 'March', 'Mar 2 - Mar 8', 'Friday', '10:00:00', '11:30:00', 'SE-FRI-10'),
    -- CSC240 (Computer Networks - Dr. Lim)
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC240-G1'), (SELECT room_id FROM classrooms WHERE room_name = 'Block D-110'), 'March', 'Mar 2 - Mar 8', 'Thursday', '10:00:00', '11:30:00', 'CN-THU-10'),
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC240-G1'), (SELECT room_id FROM classrooms WHERE room_name = 'Block A-102'), 'March', 'Mar 2 - Mar 8', 'Monday', '14:00:00', '15:30:00', 'CN-MON-14'),
    -- CSC250 (Web Development - Ms. Farah)
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC250-G1'), (SELECT room_id FROM classrooms WHERE room_name = 'Block B-204'), 'March', 'Mar 2 - Mar 8', 'Tuesday', '14:00:00', '15:30:00', 'WD-TUE-14'),
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC250-G1'), (SELECT room_id FROM classrooms WHERE room_name = 'Lab C-105'), 'March', 'Mar 2 - Mar 8', 'Friday', '14:00:00', '15:30:00', 'WD-FRI-14'),
    -- CSC260 (Algorithms - Mr. Kumar)
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC260-G1'), (SELECT room_id FROM classrooms WHERE room_name = 'Block A-101'), 'March', 'Mar 2 - Mar 8', 'Wednesday', '11:00:00', '12:30:00', 'ALG-WED-11'),
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC260-G1'), (SELECT room_id FROM classrooms WHERE room_name = 'Block D-110'), 'March', 'Mar 2 - Mar 8', 'Friday', '11:00:00', '12:30:00', 'ALG-FRI-11'),
    -- CSC270 (OOP - Prof. Smith)
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC270-G1'), (SELECT room_id FROM classrooms WHERE room_name = 'Block A-102'), 'March', 'Mar 2 - Mar 8', 'Monday', '10:00:00', '11:30:00', 'OOP-MON-10'),
    ((SELECT section_id FROM course_sections WHERE section_code = 'CSC270-G1'), (SELECT room_id FROM classrooms WHERE room_name = 'Lab C-106'), 'March', 'Mar 2 - Mar 8', 'Thursday', '13:00:00', '14:30:00', 'OOP-THU-13')
ON DUPLICATE KEY UPDATE session_code = VALUES(session_code);