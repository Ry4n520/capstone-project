<?php
/**
 * Admin Announcements API
 *
 * Actions:
 * - create
 * - update
 * - delete
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

function resolve_target_role_id(PDO $pdo, $target_role)
{
    $target = strtolower(trim((string) $target_role));

    if ($target === '' || $target === 'all') {
        return null;
    }

    $stmt = $pdo->prepare('SELECT role_id FROM roles WHERE role_name = :role_name LIMIT 1');
    $stmt->execute([':role_name' => $target]);
    $role_id = $stmt->fetchColumn();

    return $role_id !== false ? (int) $role_id : -1;
}

$input = read_input_data();
$action = strtolower(trim((string) ($input['action'] ?? '')));
$admin_user_id = (int) $_SESSION['user_id'];

if ($action === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing action']);
    exit;
}

try {
    if ($action === 'create') {
        $title = trim((string) ($input['title'] ?? ''));
        $content = trim((string) ($input['content'] ?? ''));
        $target_role = (string) ($input['target_role'] ?? 'all');

        if ($title === '' || $content === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Title and content are required']);
            exit;
        }

        $target_role_id = resolve_target_role_id($pdo, $target_role);
        if ($target_role_id === -1) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Invalid target role']);
            exit;
        }

        $insert_stmt = $pdo->prepare(
            'INSERT INTO announcements (user_id, title, content, target_role_id)
             VALUES (:user_id, :title, :content, :target_role_id)'
        );
        $insert_stmt->execute([
            ':user_id' => $admin_user_id,
            ':title' => $title,
            ':content' => $content,
            ':target_role_id' => $target_role_id,
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Announcement posted successfully.',
            'announcement_id' => (int) $pdo->lastInsertId(),
        ]);
        exit;
    }

    if ($action === 'update') {
        $announcement_id = (int) ($input['announcement_id'] ?? 0);
        $title = trim((string) ($input['title'] ?? ''));
        $content = trim((string) ($input['content'] ?? ''));
        $target_role = (string) ($input['target_role'] ?? 'all');

        if ($announcement_id <= 0 || $title === '' || $content === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'announcement_id, title, and content are required']);
            exit;
        }

        $target_role_id = resolve_target_role_id($pdo, $target_role);
        if ($target_role_id === -1) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Invalid target role']);
            exit;
        }

        $exists_stmt = $pdo->prepare('SELECT announcement_id FROM announcements WHERE announcement_id = :announcement_id LIMIT 1');
        $exists_stmt->execute([':announcement_id' => $announcement_id]);
        if (!$exists_stmt->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Announcement not found']);
            exit;
        }

        $update_stmt = $pdo->prepare(
            'UPDATE announcements
             SET title = :title,
                 content = :content,
                 target_role_id = :target_role_id
             WHERE announcement_id = :announcement_id'
        );
        $update_stmt->execute([
            ':title' => $title,
            ':content' => $content,
            ':target_role_id' => $target_role_id,
            ':announcement_id' => $announcement_id,
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Announcement updated successfully.',
        ]);
        exit;
    }

    if ($action === 'delete') {
        $announcement_id = (int) ($input['announcement_id'] ?? 0);
        if ($announcement_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid announcement_id is required']);
            exit;
        }

        $delete_stmt = $pdo->prepare('DELETE FROM announcements WHERE announcement_id = :announcement_id');
        $delete_stmt->execute([':announcement_id' => $announcement_id]);

        if ($delete_stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Announcement not found']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Announcement deleted successfully.',
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unsupported action']);
} catch (Throwable $e) {
    error_log('Announcements Admin API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
