<?php
/**
 * Smart Campus Management System - User Management
 *
 * Admin-only page for user management features.
 */

include 'includes/check_session.php';
require_once __DIR__ . '/config/db.php';

if ($user_role !== 'admin') {
    header('Location: homepage.php');
    exit();
}

$users = [];
$load_error = null;

try {
    $stmt = $pdo->query(
        "SELECT
            u.user_id,
            u.name,
            u.email,
            u.gender,
            u.phone,
            u.date_joined,
            r.role_name
         FROM users u
         INNER JOIN roles r ON u.role_id = r.role_id
         ORDER BY FIELD(r.role_name, 'admin', 'staff', 'student'), u.name ASC"
    );
    $users = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('User Management load error: ' . $e->getMessage());
    $load_error = 'Unable to load users right now. Please try again later.';
}

$role_counts = [
    'admin' => 0,
    'staff' => 0,
    'student' => 0
];

foreach ($users as $row) {
    $role_key = strtolower((string) ($row['role_name'] ?? ''));
    if (isset($role_counts[$role_key])) {
        $role_counts[$role_key]++;
    }
}

$total_users = count($users);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Campus - User Management</title>
    <link rel="stylesheet" href="assets/css/header.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/homepage.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/user-management.css?v=<?php echo time(); ?>">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <div class="welcome-section">
            <h1>User Management</h1>
            <p>Manage system users, roles, and account operations from one place.</p>
        </div>

        <div class="user-management-layout">
            <div class="main-panel">
                <div class="card users-card">
                    <div class="card-header">All System Users</div>

                    <div class="table-toolbar">
                        <input id="userSearchInput" type="text" placeholder="Search by name, email, role, or phone">
                        <span id="visibleUserCount" class="visible-count"></span>
                    </div>

                    <?php if ($load_error): ?>
                        <div class="table-message error"><?php echo htmlspecialchars($load_error); ?></div>
                    <?php elseif (empty($users)): ?>
                        <div class="table-message">No users found in the system.</div>
                    <?php else: ?>
                        <div class="table-wrapper">
                            <table id="usersTable" class="users-table">
                                <thead>
                                    <tr>
                                        <th>User ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Gender</th>
                                        <th>Phone</th>
                                        <th>Date Joined</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $row): ?>
                                        <?php
                                            $role = strtolower((string) $row['role_name']);
                                            $role_display = $role === 'staff' ? 'Lecturer' : ucfirst($role);
                                            $role_class = 'role-' . preg_replace('/[^a-z0-9_-]/', '', $role);
                                            $joined_date = '';
                                            if (!empty($row['date_joined'])) {
                                                $joined_date = date('d M Y', strtotime((string) $row['date_joined']));
                                            }
                                        ?>
                                        <tr
                                            class="user-row"
                                            data-user-id="<?php echo (int) $row['user_id']; ?>"
                                            data-name="<?php echo htmlspecialchars((string) $row['name']); ?>"
                                            data-email="<?php echo htmlspecialchars((string) $row['email']); ?>"
                                            data-role="<?php echo htmlspecialchars($role); ?>"
                                            data-gender="<?php echo htmlspecialchars((string) ($row['gender'] ?: '')); ?>"
                                            data-phone="<?php echo htmlspecialchars((string) ($row['phone'] ?: '')); ?>"
                                            data-date-joined="<?php echo htmlspecialchars((string) ($row['date_joined'] ?: '')); ?>"
                                        >
                                            <td><?php echo (int) $row['user_id']; ?></td>
                                            <td><?php echo htmlspecialchars((string) $row['name']); ?></td>
                                            <td><?php echo htmlspecialchars((string) $row['email']); ?></td>
                                            <td><span class="role-badge <?php echo htmlspecialchars($role_class); ?>"><?php echo htmlspecialchars($role_display); ?></span></td>
                                            <td><?php echo htmlspecialchars((string) ($row['gender'] ?: '-')); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($row['phone'] ?: '-')); ?></td>
                                            <td><?php echo htmlspecialchars($joined_date); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="side-panel">
                <div class="card side-card">
                    <div class="card-header">Admin Actions</div>
                    <div class="action-buttons" id="actionButtons">
                        <button type="button" class="action-btn" data-action="add">Add New Users</button>
                        <button type="button" class="action-btn" data-action="edit">Edit User Information</button>
                        <button type="button" class="action-btn" data-action="delete">Delete Users</button>
                        <button type="button" class="action-btn" data-action="roles">Manage User Roles</button>
                        <button type="button" class="action-btn" data-action="profile">View Profile</button>
                    </div>
                    <div id="actionFeedback" class="action-feedback hidden"></div>
                </div>

                <div class="card side-card">
                    <div class="card-header">User Summary</div>
                    <div class="summary-grid">
                        <div class="summary-item">
                            <span class="summary-label">Total Users</span>
                            <span class="summary-value"><?php echo $total_users; ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Admins</span>
                            <span class="summary-value"><?php echo $role_counts['admin']; ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Lecturers</span>
                            <span class="summary-value"><?php echo $role_counts['staff']; ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Students</span>
                            <span class="summary-value"><?php echo $role_counts['student']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="userActionModal" class="um-modal hidden" aria-hidden="true">
        <div class="um-modal-backdrop" data-modal-close></div>
        <div class="um-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="umModalTitle">
            <div class="um-modal-header">
                <h3 id="umModalTitle">Action</h3>
                <button type="button" class="um-modal-close" id="umModalCloseBtn" aria-label="Close">&times;</button>
            </div>
            <div id="umModalBody" class="um-modal-body"></div>
            <div id="umModalActions" class="um-modal-actions"></div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/header.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/user-management.js?v=<?php echo time(); ?>"></script>
</body>
</html>
