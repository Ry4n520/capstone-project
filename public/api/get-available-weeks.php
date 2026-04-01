<?php
/**
 * GPSchool Management System - Available Timetable Weeks API
 *
 * Returns weeks available for timetable navigation.
 * - Students: released weeks only (current + future), only for enrolled sections.
 * - Staff: current + future weeks for taught sections (pending/released/cancelled).
 * - Admin: all current + future weeks.
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$user_id = $_SESSION['user_id'];
$role_name = $_SESSION['role'];

$today = new DateTimeImmutable('today');
$current_week_start = $today->modify('-' . ((int) $today->format('N') - 1) . ' days')->format('Y-m-d');

try {
    if ($role_name === 'student') {
        $query = "
            SELECT
                t.week_start_date,
                t.week_end_date,
                'released' AS status
            FROM timetables t
            JOIN course_sections cs ON t.section_id = cs.section_id
            JOIN enrollments e ON cs.section_id = e.section_id
            WHERE e.student_id = :user_id
              AND e.status = 'active'
              AND t.week_start_date >= :current_week_start
              AND t.status = 'released'
            GROUP BY t.week_start_date, t.week_end_date
            ORDER BY t.week_start_date ASC
        ";

        $params = [
            ':user_id' => $user_id,
            ':current_week_start' => $current_week_start
        ];
    } elseif ($role_name === 'staff') {
        $query = "
            SELECT
                t.week_start_date,
                t.week_end_date,
                'released' AS status
            FROM timetables t
            JOIN course_sections cs ON t.section_id = cs.section_id
            WHERE cs.lecturer_id = :user_id
              AND t.week_start_date >= :current_week_start
              AND t.status = 'released'
            GROUP BY t.week_start_date, t.week_end_date
            ORDER BY t.week_start_date ASC
        ";

        $params = [
            ':user_id' => $user_id,
            ':current_week_start' => $current_week_start
        ];
    } elseif ($role_name === 'admin') {
        $query = "
            SELECT
                t.week_start_date,
                t.week_end_date,
                CASE
                    WHEN SUM(t.status = 'pending') > 0 THEN 'pending'
                    WHEN SUM(t.status = 'released') > 0 THEN 'released'
                    WHEN SUM(t.status = 'cancelled') > 0 THEN 'cancelled'
                    ELSE 'pending'
                END AS status
            FROM timetables t
            WHERE t.week_start_date >= :current_week_start
            GROUP BY t.week_start_date, t.week_end_date
            ORDER BY t.week_start_date ASC
        ";

        $params = [
            ':current_week_start' => $current_week_start
        ];
    } else {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid user role']);
        exit;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fallback: if there are no current/future rows yet, expose historical weeks
    // so users can still view the most recent timetable data.
    if (empty($weeks)) {
        if ($role_name === 'student') {
            $fallback_query = "
                SELECT
                    t.week_start_date,
                    t.week_end_date,
                    'released' AS status
                FROM timetables t
                JOIN course_sections cs ON t.section_id = cs.section_id
                JOIN enrollments e ON cs.section_id = e.section_id
                WHERE e.student_id = :user_id
                  AND e.status = 'active'
                  AND t.status = 'released'
                GROUP BY t.week_start_date, t.week_end_date
                ORDER BY t.week_start_date ASC
            ";

            $fallback_params = [':user_id' => $user_id];
        } elseif ($role_name === 'staff') {
            $fallback_query = "
                SELECT
                    t.week_start_date,
                    t.week_end_date,
                    'released' AS status
                FROM timetables t
                JOIN course_sections cs ON t.section_id = cs.section_id
                WHERE cs.lecturer_id = :user_id
                  AND t.status = 'released'
                GROUP BY t.week_start_date, t.week_end_date
                ORDER BY t.week_start_date ASC
            ";

            $fallback_params = [':user_id' => $user_id];
        } else {
            $fallback_query = "
                SELECT
                    t.week_start_date,
                    t.week_end_date,
                    CASE
                        WHEN SUM(t.status = 'pending') > 0 THEN 'pending'
                        WHEN SUM(t.status = 'released') > 0 THEN 'released'
                        WHEN SUM(t.status = 'cancelled') > 0 THEN 'cancelled'
                        ELSE 'pending'
                    END AS status
                FROM timetables t
                GROUP BY t.week_start_date, t.week_end_date
                ORDER BY t.week_start_date ASC
            ";

            $fallback_params = [];
        }

        $fallback_stmt = $pdo->prepare($fallback_query);
        $fallback_stmt->execute($fallback_params);
        $weeks = $fallback_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'user_role' => $role_name,
        'current_week_start' => $current_week_start,
        'weeks' => $weeks
    ], JSON_PRETTY_PRINT);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
?>
