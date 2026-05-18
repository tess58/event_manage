<?php
require_once 'includes/config.php';
require_login();
if (!is_role('admin')) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Admin Users | Event Ethiopia';
$message = '';
db_ensure_column($mysqli, 'users', 'is_blocked', "TINYINT(1) NOT NULL DEFAULT 0");

$search = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$selectedUserId = (int) ($_GET['view'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_user'])) {
        $userId = (int) $_POST['delete_user'];
        if ($userId !== (int) $_SESSION['user_id']) {
            $deleteStmt = $mysqli->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
            $deleteStmt->bind_param('i', $userId);
            $deleteStmt->execute();
            $deleteStmt->close();
            $message = 'User removed successfully.';
        }
    } elseif (isset($_POST['toggle_block'])) {
        $userId = (int) $_POST['toggle_block'];
        $stmt = $mysqli->prepare("UPDATE users SET is_blocked = CASE WHEN is_blocked = 1 THEN 0 ELSE 1 END WHERE id = ? AND role != 'admin'");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
        $message = 'User block status updated.';
    } elseif (isset($_POST['reset_password'])) {
        $userId = (int) $_POST['reset_password'];
        $newPassword = 'password123';
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param('si', $hash, $userId);
        $stmt->execute();
        $stmt->close();
        $message = 'Password reset to password123.';
    }
}

$where = ["role != 'admin'"];
$bindTypes = '';
$bindValues = [];
if ($search !== '') {
    $where[] = "(name LIKE ? OR email LIKE ?)";
    $bindTypes .= 'ss';
    $term = '%' . $search . '%';
    $bindValues[] = $term;
    $bindValues[] = $term;
}
if (in_array($roleFilter, ['user', 'organizer'], true)) {
    $where[] = "role = ?";
    $bindTypes .= 's';
    $bindValues[] = $roleFilter;
}
if (in_array($statusFilter, ['approved', 'pending', 'rejected'], true)) {
    $where[] = "status = ?";
    $bindTypes .= 's';
    $bindValues[] = $statusFilter;
}

$sql = "SELECT id, name, email, role, status, is_blocked, created_at FROM users WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC";
$usersStmt = $mysqli->prepare($sql);
if ($bindTypes !== '') {
    $usersStmt->bind_param($bindTypes, ...$bindValues);
}
$usersStmt->execute();
$users = $usersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$usersStmt->close();

$selectedUser = null;
$userBookings = [];
$userReviews = [];
if ($selectedUserId > 0) {
    $profileStmt = $mysqli->prepare("SELECT id, name, email, role, status, is_blocked, created_at FROM users WHERE id = ? LIMIT 1");
    $profileStmt->bind_param('i', $selectedUserId);
    $profileStmt->execute();
    $selectedUser = $profileStmt->get_result()->fetch_assoc();
    $profileStmt->close();

    if ($selectedUser) {
        $bookingsStmt = $mysqli->prepare(
            "SELECT b.created_at, b.status, e.title
             FROM bookings b
             JOIN events e ON e.id = b.event_id
             WHERE b.user_id = ?
             ORDER BY b.created_at DESC
             LIMIT 8"
        );
        $bookingsStmt->bind_param('i', $selectedUserId);
        $bookingsStmt->execute();
        $userBookings = $bookingsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $bookingsStmt->close();

        $reviewsStmt = $mysqli->prepare(
            "SELECT r.rating, r.comment, r.created_at, e.title AS event_title
             FROM reviews r
             JOIN events e ON e.id = r.event_id
             WHERE r.user_id = ?
             ORDER BY r.created_at DESC
             LIMIT 8"
        );
        $reviewsStmt->bind_param('i', $selectedUserId);
        $reviewsStmt->execute();
        $userReviews = $reviewsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $reviewsStmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="site-header" id="site-header">
        <div class="wrapper header-inner" style="gap: 12px; flex-wrap: wrap;">
            <a href="index.php" class="brand-logo" style="margin-right: auto;">Event Ethiopia</a>
            <div class="dashboard-header-actions">
                <a href="admin-dashboard.php" class="button button-alt">Dashboard</a>
                <a href="logout.php" class="button button-alt">Logout</a>
                <button type="button" class="dashboard-drawer-toggle button">Menu</button>
            </div>
        </div>
    </header>
    <main class="page-content wrapper">
        <div class="dashboard-container" id="dashboard-root">
            <aside class="dashboard-sidebar" id="dashboard-sidebar-nav">
                <h3>Admin Panel</h3>
                <nav class="sidebar-nav">
                    <a href="admin-dashboard.php">Dashboard</a>
                    <a href="admin-users.php" class="active">Users</a>
                    <a href="admin-organizers.php">Organizers</a>
                    <a href="admin-events.php">Events</a>
                    <a href="admin-categories.php">Categories</a>
                    <a href="admin-bookings.php">Bookings</a>
                    <a href="admin-reviews.php">Reviews</a>
                    <a href="admin-reports.php">Reports</a>
                    <a href="admin-settings.php">Settings</a>
                    <a href="logout.php">Logout</a>
                </nav>
            </aside>
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <h1>Users Management</h1>
                    <p>All platform users with role and account status.</p>
                </div>
                <?php if ($message): ?><div class="alert alert-success"><?= sanitize($message) ?></div><?php endif; ?>
                <div class="dashboard-card">
                    <form method="get" class="filter-panel">
                        <input type="text" name="search" placeholder="Search users by name/email" value="<?= sanitize($search) ?>">
                        <select name="role">
                            <option value="">All Roles</option>
                            <option value="user" <?= $roleFilter === 'user' ? 'selected' : '' ?>>Attendee</option>
                            <option value="organizer" <?= $roleFilter === 'organizer' ? 'selected' : '' ?>>Organizer</option>
                        </select>
                        <select name="status">
                            <option value="">All Statuses</option>
                            <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                        <button class="button" type="submit">Filter</button>
                    </form>
                </div>
                <div class="dashboard-card">
                    <table class="list-table">
                        <thead>
                            <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= sanitize($user['name']) ?></td>
                                    <td><?= sanitize($user['email']) ?></td>
                                    <td><span class="badge"><?= sanitize(ucfirst($user['role'])) ?></span></td>
                                    <td>
                                        <span class="badge status-<?= sanitize($user['status']) ?>"><?= sanitize(ucfirst($user['status'])) ?></span>
                                        <?php if ((int) $user['is_blocked'] === 1): ?><span class="badge status-rejected">Blocked</span><?php endif; ?>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <?php if ((int) $user['id'] !== (int) $_SESSION['user_id']): ?>
                                            <form method="post" style="display:flex; gap:8px; flex-wrap:wrap;">
                                                <a class="button button-alt" href="admin-users.php?view=<?= (int) $user['id'] ?>">Profile</a>
                                                <button class="button button-alt" name="toggle_block" value="<?= (int) $user['id'] ?>">
                                                    <?= (int) $user['is_blocked'] === 1 ? 'Unblock' : 'Block' ?>
                                                </button>
                                                <button class="button button-alt" name="reset_password" value="<?= (int) $user['id'] ?>">Reset Password</button>
                                                <button class="button button-alt" name="delete_user" value="<?= (int) $user['id'] ?>">Remove</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($selectedUser): ?>
                    <div class="dashboard-card table-card">
                        <h3>User Profile: <?= sanitize($selectedUser['name']) ?></h3>
                        <p><?= sanitize($selectedUser['email']) ?> • <?= sanitize(ucfirst($selectedUser['role'])) ?></p>
                        <div class="dashboard-grid">
                            <div class="dashboard-card">
                                <h3>Recent Bookings</h3>
                                <table class="list-table">
                                    <thead><tr><th>Event</th><th>Status</th><th>Date</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($userBookings as $booking): ?>
                                            <tr>
                                                <td><?= sanitize($booking['title']) ?></td>
                                                <td><?= sanitize($booking['status']) ?></td>
                                                <td><?= date('M d, Y', strtotime($booking['created_at'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="dashboard-card">
                                <h3>Recent Reviews</h3>
                                <table class="list-table">
                                    <thead><tr><th>Event</th><th>Rating</th><th>Date</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($userReviews as $review): ?>
                                            <tr>
                                                <td><?= sanitize($review['event_title']) ?></td>
                                                <td><?= (int) $review['rating'] ?>/5</td>
                                                <td><?= date('M d, Y', strtotime($review['created_at'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    <script src="js/script.js"></script>
</body>
</html>
