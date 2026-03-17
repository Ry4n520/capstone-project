-- Facility seed migration
-- 1) Add capacity column
-- 2) Add is_available column
-- 3) Import classrooms into facilities
-- 4) Insert meeting/sport facilities
-- 5) Apply capacities by type

USE capstone_db;

SET @has_capacity := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'facilities'
      AND COLUMN_NAME = 'capacity'
);
SET @capacity_sql := IF(
    @has_capacity = 0,
    'ALTER TABLE facilities ADD COLUMN capacity INT AFTER location',
    'SELECT 1'
);
PREPARE stmt_capacity FROM @capacity_sql;
EXECUTE stmt_capacity;
DEALLOCATE PREPARE stmt_capacity;

SET @has_is_available := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'facilities'
      AND COLUMN_NAME = 'is_available'
);
SET @is_available_sql := IF(
    @has_is_available = 0,
    'ALTER TABLE facilities ADD COLUMN is_available TINYINT(1) NOT NULL DEFAULT 1 AFTER facility_type',
    'SELECT 1'
);
PREPARE stmt_is_available FROM @is_available_sql;
EXECUTE stmt_is_available;
DEALLOCATE PREPARE stmt_is_available;

UPDATE facilities
SET is_available = 1
WHERE is_available IS NULL;


-- Import all existing classrooms as classroom facilities.
INSERT INTO facilities (facility_name, location, facility_type)
SELECT
    CONCAT(c.room_name, ' - ', c.building) AS facility_name,
    c.building AS location,
    'classroom' AS facility_type
FROM classrooms c
WHERE NOT EXISTS (
    SELECT 1
    FROM facilities f
    WHERE f.facility_name = CONCAT(c.room_name, ' - ', c.building)
      AND f.location = c.building
      AND f.facility_type = 'classroom'
);

-- Meeting rooms.
INSERT INTO facilities (facility_name, location, facility_type)
SELECT 'Meeting Room 1', 'Block A', 'meeting_room'
WHERE NOT EXISTS (SELECT 1 FROM facilities WHERE facility_name = 'Meeting Room 1' AND facility_type = 'meeting_room');
INSERT INTO facilities (facility_name, location, facility_type)
SELECT 'Meeting Room 2', 'Block A', 'meeting_room'
WHERE NOT EXISTS (SELECT 1 FROM facilities WHERE facility_name = 'Meeting Room 2' AND facility_type = 'meeting_room');
INSERT INTO facilities (facility_name, location, facility_type)
SELECT 'Meeting Room 3', 'Block B', 'meeting_room'
WHERE NOT EXISTS (SELECT 1 FROM facilities WHERE facility_name = 'Meeting Room 3' AND facility_type = 'meeting_room');
INSERT INTO facilities (facility_name, location, facility_type)
SELECT 'Meeting Room 4', 'Block B', 'meeting_room'
WHERE NOT EXISTS (SELECT 1 FROM facilities WHERE facility_name = 'Meeting Room 4' AND facility_type = 'meeting_room');
INSERT INTO facilities (facility_name, location, facility_type)
SELECT 'Meeting Room 5', 'Block C', 'meeting_room'
WHERE NOT EXISTS (SELECT 1 FROM facilities WHERE facility_name = 'Meeting Room 5' AND facility_type = 'meeting_room');
INSERT INTO facilities (facility_name, location, facility_type)
SELECT 'Conference Room A', 'Block D', 'meeting_room'
WHERE NOT EXISTS (SELECT 1 FROM facilities WHERE facility_name = 'Conference Room A' AND facility_type = 'meeting_room');
INSERT INTO facilities (facility_name, location, facility_type)
SELECT 'Conference Room B', 'Block D', 'meeting_room'
WHERE NOT EXISTS (SELECT 1 FROM facilities WHERE facility_name = 'Conference Room B' AND facility_type = 'meeting_room');
INSERT INTO facilities (facility_name, location, facility_type)
SELECT 'Board Room', 'Admin Block', 'meeting_room'
WHERE NOT EXISTS (SELECT 1 FROM facilities WHERE facility_name = 'Board Room' AND facility_type = 'meeting_room');

-- Sport facilities.
INSERT INTO facilities (facility_name, location, facility_type)
SELECT 'Basketball Court A', 'Sports Complex', 'sport_facility'
WHERE NOT EXISTS (SELECT 1 FROM facilities WHERE facility_name = 'Basketball Court A' AND facility_type = 'sport_facility');
INSERT INTO facilities (facility_name, location, facility_type)
SELECT 'Basketball Court B', 'Sports Complex', 'sport_facility'
WHERE NOT EXISTS (SELECT 1 FROM facilities WHERE facility_name = 'Basketball Court B' AND facility_type = 'sport_facility');
INSERT INTO facilities (facility_name, location, facility_type)
SELECT 'Tennis Court 1', 'Sports Complex', 'sport_facility'
WHERE NOT EXISTS (SELECT 1 FROM facilities WHERE facility_name = 'Tennis Court 1' AND facility_type = 'sport_facility');
INSERT INTO facilities (facility_name, location, facility_type)
SELECT 'Tennis Court 2', 'Sports Complex', 'sport_facility'
WHERE NOT EXISTS (SELECT 1 FROM facilities WHERE facility_name = 'Tennis Court 2' AND facility_type = 'sport_facility');
INSERT INTO facilities (facility_name, location, facility_type)
SELECT 'Badminton Hall', 'Sports Complex', 'sport_facility'
WHERE NOT EXISTS (SELECT 1 FROM facilities WHERE facility_name = 'Badminton Hall' AND facility_type = 'sport_facility');
INSERT INTO facilities (facility_name, location, facility_type)
SELECT 'Futsal Court', 'Sports Complex', 'sport_facility'
WHERE NOT EXISTS (SELECT 1 FROM facilities WHERE facility_name = 'Futsal Court' AND facility_type = 'sport_facility');
INSERT INTO facilities (facility_name, location, facility_type)
SELECT 'Volleyball Court', 'Sports Complex', 'sport_facility'
WHERE NOT EXISTS (SELECT 1 FROM facilities WHERE facility_name = 'Volleyball Court' AND facility_type = 'sport_facility');
INSERT INTO facilities (facility_name, location, facility_type)
SELECT 'Swimming Pool', 'Sports Complex', 'sport_facility'
WHERE NOT EXISTS (SELECT 1 FROM facilities WHERE facility_name = 'Swimming Pool' AND facility_type = 'sport_facility');
INSERT INTO facilities (facility_name, location, facility_type)
SELECT 'Gymnasium', 'Sports Complex', 'sport_facility'
WHERE NOT EXISTS (SELECT 1 FROM facilities WHERE facility_name = 'Gymnasium' AND facility_type = 'sport_facility');
INSERT INTO facilities (facility_name, location, facility_type)
SELECT 'Table Tennis Room', 'Recreation Center', 'sport_facility'
WHERE NOT EXISTS (SELECT 1 FROM facilities WHERE facility_name = 'Table Tennis Room' AND facility_type = 'sport_facility');

-- Classroom capacities from classrooms table.
UPDATE facilities f
JOIN classrooms c ON f.facility_name LIKE CONCAT(c.room_name, '%')
SET f.capacity = c.capacity
WHERE f.facility_type = 'classroom';

-- Meeting room capacities.
UPDATE facilities
SET capacity = CASE
    WHEN facility_name LIKE 'Meeting Room%' THEN 8
    WHEN facility_name LIKE 'Conference Room%' THEN 15
    WHEN facility_name LIKE 'Board Room%' THEN 20
    ELSE 10
END
WHERE facility_type = 'meeting_room';

-- Sport facility capacities.
UPDATE facilities
SET capacity = CASE
    WHEN facility_name LIKE '%Basketball%' THEN 20
    WHEN facility_name LIKE '%Tennis%' THEN 4
    WHEN facility_name LIKE '%Badminton%' THEN 16
    WHEN facility_name LIKE '%Futsal%' THEN 12
    WHEN facility_name LIKE '%Volleyball%' THEN 12
    WHEN facility_name LIKE '%Swimming Pool%' THEN 30
    WHEN facility_name LIKE '%Gymnasium%' THEN 50
    WHEN facility_name LIKE '%Table Tennis%' THEN 8
    ELSE 15
END
WHERE facility_type = 'sport_facility';
