<?php
/**
 * User Management API (Admin only)
 *
 * Actions:
 * - create
 * - update
 * - update_role
 * - delete
 * - get_profile
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

function read_input_data()
{
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    return $_POST;
}

function find_role_id(PDO $pdo, $role_name)
{
    $stmt = $pdo->prepare('SELECT role_id FROM roles WHERE role_name = :role_name LIMIT 1');
    $stmt->execute([':role_name' => $role_name]);
    $role_id = $stmt->fetchColumn();
    return $role_id !== false ? (int) $role_id : null;
}

function find_user_reference_count(PDO $pdo, $user_id)
{
    $queries = [
        'SELECT COUNT(*) FROM enrollments WHERE student_id = :user_id',
        'SELECT COUNT(*) FROM course_sections WHERE lecturer_id = :user_id',
        'SELECT COUNT(*) FROM bookings WHERE user_id = :user_id',
        'SELECT COUNT(*) FROM announcements WHERE user_id = :user_id',
        'SELECT COUNT(*) FROM schedule_requests WHERE approved_by = :user_id',
        'SELECT COUNT(*) FROM timetables WHERE created_by = :user_id'
    ];

    $total = 0;
    foreach ($queries as $sql) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);
        $total += (int) $stmt->fetchColumn();
    }

    return $total;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = ($method === 'POST') ? read_input_data() : $_GET;
$action = strtolower(trim((string) ($input['action'] ?? '')));

if ($action === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing action']);
    exit;
}

$allowed_roles = ['admin', 'staff', 'student'];
$current_admin_id = (int) $_SESSION['user_id'];

try {
    if ($action === 'get_profile') {
        $user_id = (int) ($input['user_id'] ?? 0);
        if ($user_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid user_id is required']);
            exit;
        }

        $stmt = $pdo->prepare(
            'SELECT
                u.user_id,
                u.name,
                u.email,
                u.gender,
                u.phone,
                u.date_joined,
                r.role_name
             FROM users u
             INNER JOIN roles r ON u.role_id = r.role_id
             WHERE u.user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute([':user_id' => $user_id]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$profile) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }

        echo json_encode(['success' => true, 'profile' => $profile]);
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    if ($action === 'create') {
        $name = trim((string) ($input['name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $role = strtolower(trim((string) ($input['role'] ?? 'student')));
        $password = (string) ($input['password'] ?? '');
        $gender = trim((string) ($input['gender'] ?? ''));
        $phone = trim((string) ($input['phone'] ?? ''));

        if ($name === '' || $email === '' || $password === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Name, email, and password are required']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Invalid email format']);
            exit;
        }

        if (!in_array($role, $allowed_roles, true)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Invalid role']);
            exit;
        }

        $role_id = find_role_id($pdo, $role);
        if ($role_id === null) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Role does not exist in database']);
            exit;
        }

        $check_stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = :email LIMIT 1');
        $check_stmt->execute([':email' => $email]);
        if ($check_stmt->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            exit;
        }

        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        $insert_stmt = $pdo->prepare(
            'INSERT INTO users (role_id, name, gender, phone, email, password_hash)
             VALUES (:role_id, :name, :gender, :phone, :email, :password_hash)'
        );
        $insert_stmt->execute([
            ':role_id' => $role_id,
            ':name' => $name,
            ':gender' => ($gender !== '' ? $gender : null),
            ':phone' => ($phone !== '' ? $phone : null),
            ':email' => $email,
            ':password_hash' => $password_hash
        ]);

        echo json_encode(['success' => true, 'message' => 'User created successfully']);
        exit;
    }

    if ($action === 'update') {
        $user_id = (int) ($input['user_id'] ?? 0);
        $name = trim((string) ($input['name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $gender = trim((string) ($input['gender'] ?? ''));
        $phone = trim((string) ($input['phone'] ?? ''));

        if ($user_id <= 0 || $name === '' || $email === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'user_id, name, and email are required']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Invalid email format']);
            exit;
        }

        $exists_stmt = $pdo->prepare('SELECT user_id FROM users WHERE user_id = :user_id LIMIT 1');
        $exists_stmt->execute([':user_id' => $user_id]);
        if (!$exists_stmt->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }

        $email_stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = :email AND user_id <> :user_id LIMIT 1');
        $email_stmt->execute([
            ':email' => $email,
            ':user_id' => $user_id
        ]);
        if ($email_stmt->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            exit;
        }

        $update_stmt = $pdo->prepare(
            'UPDATE users
             SET name = :name,
                 email = :email,
                 gender = :gender,
                 phone = :phone
             WHERE user_id = :user_id'
        );
        $update_stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':gender' => ($gender !== '' ? $gender : null),
            ':phone' => ($phone !== '' ? $phone : null),
            ':user_id' => $user_id
        ]);

        echo json_encode(['success' => true, 'message' => 'User details updated successfully']);
        exit;
    }

    if ($action === 'update_role') {
        $user_id = (int) ($input['user_id'] ?? 0);
        $role = strtolower(trim((string) ($input['role'] ?? '')));

        if ($user_id <= 0 || $role === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'user_id and role are required']);
            exit;
        }

        if (!in_array($role, $allowed_roles, true)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Invalid role']);
            exit;
        }

        if ($user_id === $current_admin_id) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'You cannot change your own role']);
            exit;
        }

        $role_id = find_role_id($pdo, $role);
        if ($role_id === null) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Role does not exist in database']);
            exit;
        }

        $update_stmt = $pdo->prepare('UPDATE users SET role_id = :role_id WHERE user_id = :user_id');
        $update_stmt->execute([
            ':role_id' => $role_id,
            ':user_id' => $user_id
        ]);

        if ($update_stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found or unchanged']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'User role updated successfully']);
        exit;
    }

    if ($action === 'delete') {
        $user_id = (int) ($input['user_id'] ?? 0);

        if ($user_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid user_id is required']);
            exit;
        }

        if ($user_id === $current_admin_id) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
            exit;
        }

        $admin_count_stmt = $pdo->query(
            "SELECT COUNT(*)
             FROM users u
             INNER JOIN roles r ON u.role_id = r.role_id
             WHERE r.role_name = 'admin'"
        );
        $admin_count = (int) $admin_count_stmt->fetchColumn();

        $target_role_stmt = $pdo->prepare(
            'SELECT r.role_name
             FROM users u
             INNER JOIN roles r ON u.role_id = r.role_id
             WHERE u.user_id = :user_id
             LIMIT 1'
        );
        $target_role_stmt->execute([':user_id' => $user_id]);
        $target_role = $target_role_stmt->fetchColumn();

        if ($target_role === false) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }

        if ($target_role === 'admin' && $admin_count <= 1) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Cannot delete the last admin user']);
            exit;
        }

        $reference_count = find_user_reference_count($pdo, $user_id);
        if ($reference_count > 0) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'User has related records and cannot be deleted']);
            exit;
        }

        $delete_stmt = $pdo->prepare('DELETE FROM users WHERE user_id = :user_id');
        $delete_stmt->execute([':user_id' => $user_id]);

        if ($delete_stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unsupported action']);
} catch (Throwable $e) {
    error_log('User Management API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
