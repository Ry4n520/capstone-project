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
INSERT INTO users (name, email, password_hash, role_id)
VALUES 
    ('John Student', 'student@campus.edu', 'password123', (SELECT role_id FROM roles WHERE role_name = 'student')),
    ('Prof. Smith', 'staff@campus.edu', 'password123', (SELECT role_id FROM roles WHERE role_name = 'staff')),
    ('Admin User', 'admin@campus.edu', 'password123', (SELECT role_id FROM roles WHERE role_name = 'admin'))
ON DUPLICATE KEY UPDATE email = VALUES(email);