/* Initializes database schema for the capstone app. */

CREATE DATABASE IF NOT EXISTS capstone_db;
USE capstone_db;

-- Roles
CREATE TABLE IF NOT EXISTS roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    role_description VARCHAR(255)
);

-- Users
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    gender VARCHAR(20),
    phone VARCHAR(30),
    email VARCHAR(120) UNIQUE,
    profile_image VARCHAR(255),
    password VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    date_joined DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id) REFERENCES roles(role_id)
);

-- Students
CREATE TABLE IF NOT EXISTS students (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    intake VARCHAR(50),
    programme VARCHAR(100),
    CONSTRAINT fk_students_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Lecturers
CREATE TABLE IF NOT EXISTS lecturers (
    lecturer_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    department VARCHAR(100),
    office_location VARCHAR(100),
    CONSTRAINT fk_lecturers_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Courses
CREATE TABLE IF NOT EXISTS courses (
    course_id INT AUTO_INCREMENT PRIMARY KEY,
    course_name VARCHAR(150) NOT NULL,
    credit_hour INT NOT NULL
);

-- Classrooms
CREATE TABLE IF NOT EXISTS classrooms (
    room_id INT AUTO_INCREMENT PRIMARY KEY,
    room_name VARCHAR(100) NOT NULL,
    building VARCHAR(100),
    capacity INT,
    type VARCHAR(50)
);

-- Timetable
CREATE TABLE IF NOT EXISTS timetables (
    timetable_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    user_id INT NOT NULL,
    room_id INT NOT NULL,
    day_of_week VARCHAR(20) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    session_code VARCHAR(50),
    type VARCHAR(50),
    CONSTRAINT fk_timetables_course
        FOREIGN KEY (course_id) REFERENCES courses(course_id),
    CONSTRAINT fk_timetables_user
        FOREIGN KEY (user_id) REFERENCES users(user_id),
    CONSTRAINT fk_timetables_room
        FOREIGN KEY (room_id) REFERENCES classrooms(room_id)
);

-- Enrollment
CREATE TABLE IF NOT EXISTS enrollments (
    enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    semester VARCHAR(20),
    year INT,
    CONSTRAINT fk_enrollments_student
        FOREIGN KEY (student_id) REFERENCES students(student_id),
    CONSTRAINT fk_enrollments_course
        FOREIGN KEY (course_id) REFERENCES courses(course_id)
);

-- Attendance
CREATE TABLE IF NOT EXISTS attendance (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_id INT NOT NULL,
    timetable_id INT NOT NULL,
    date DATE NOT NULL,
    status VARCHAR(30) NOT NULL,
    mark_method VARCHAR(50),
    marked_at DATETIME,
    type VARCHAR(50),
    CONSTRAINT fk_attendance_enrollment
        FOREIGN KEY (enrollment_id) REFERENCES enrollments(enrollment_id),
    CONSTRAINT fk_attendance_timetable
        FOREIGN KEY (timetable_id) REFERENCES timetables(timetable_id)
);

-- Facilities
CREATE TABLE IF NOT EXISTS facilities (
    facility_id INT AUTO_INCREMENT PRIMARY KEY,
    facility_name VARCHAR(120) NOT NULL,
    location VARCHAR(120),
    description VARCHAR(255)
);

-- Facility booking
CREATE TABLE IF NOT EXISTS facility_bookings (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    facility_id INT NOT NULL,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    booking_status VARCHAR(30) NOT NULL,
    CONSTRAINT fk_facility_bookings_user
        FOREIGN KEY (user_id) REFERENCES users(user_id),
    CONSTRAINT fk_facility_bookings_facility
        FOREIGN KEY (facility_id) REFERENCES facilities(facility_id)
);

-- Announcements
CREATE TABLE IF NOT EXISTS announcements (
    announcement_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    content TEXT NOT NULL,
    created_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    target_role VARCHAR(50),
    CONSTRAINT fk_announcements_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Seed roles and test users for authentication testing
-- Password for all test users: password123

INSERT INTO roles (role_name, role_description) VALUES
    ('admin', 'System administrator'),
    ('student', 'Student user'),
    ('staff', 'Staff/Lecturer user')
ON DUPLICATE KEY UPDATE role_description = VALUES(role_description);

-- Test users - Password: password123
INSERT INTO users (name, email, password, role_id)
VALUES 
    ('John Student', 'student@campus.edu', 'password123', (SELECT role_id FROM roles WHERE role_name = 'student')),
    ('Prof. Smith', 'staff@campus.edu', 'password123', (SELECT role_id FROM roles WHERE role_name = 'staff')),
    ('Admin User', 'admin@campus.edu', 'password123', (SELECT role_id FROM roles WHERE role_name = 'admin'))
ON DUPLICATE KEY UPDATE email = VALUES(email);