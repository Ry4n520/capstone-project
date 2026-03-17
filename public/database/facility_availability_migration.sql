USE capstone_db;

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