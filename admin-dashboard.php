<?php
require_once 'includes/config.php';
require_login();

if (!is_role('admin')) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Admin Dashboard | Event Ethiopia';
$hasEventPrice = db_has_column($mysqli, 'events', 'price');
$priceExpr = $hasEventPrice ? 'e.price' : '0';

// Get stats
$totalUsersStmt = $mysqli->prepare("SELECT COUNT(*) as count FROM users");
$totalUsersStmt->execute();
$totalUsers = $totalUsersStmt->get_result()->fetch_assoc()['count'];
$totalUsersStmt->close();

$totalEventsStmt = $mysqli->prepare("SELECT COUNT(*) as count FROM events");
$totalEventsStmt->execute();
$totalEvents = $totalEventsStmt->get_result()->fetch_assoc()['count'];
$totalEventsStmt->close();

$totalBookingsStmt = $mysqli->prepare("SELECT COUNT(*) as count FROM bookings");
$totalBookingsStmt->execute();
$totalBookings = $totalBookingsStmt->get_result()->fetch_assoc()['count'];
$totalBookingsStmt->close();

$totalRevenueStmt = $mysqli->prepare(
    "SELECT SUM($priceExpr) as total FROM bookings b JOIN events e ON e.id = b.event_id"
);
$totalRevenueStmt->execute();
$totalRevenue = $totalRevenueStmt->get_result()->fetch_assoc()['total'] ?? 0;
$totalRevenueStmt->close();

$pendingOrgStmt = $mysqli->prepare("SELECT COUNT(*) AS count FROM users WHERE role='organizer' AND status='pending'");
$pendingOrgStmt->execute();
$pendingOrganizers = (int) ($pendingOrgStmt->get_result()->fetch_assoc()['count'] ?? 0);
$pendingOrgStmt->close();

$upcomingEventsStmt = $mysqli->prepare("SELECT COUNT(*) AS count FROM events WHERE date >= CURDATE() AND status IN ('published','active')");
$upcomingEventsStmt->execute();
$upcomingEvents = (int) ($upcomingEventsStmt->get_result()->fetch_assoc()['count'] ?? 0);
$upcomingEventsStmt->close();

// Get recent bookings
$recentBookingsStmt = $mysqli->prepare(
    "SELECT b.id, u.name as user_name, e.title as event_title, b.created_at, b.status, $priceExpr as price
    FROM bookings b
    JOIN users u ON u.id = b.user_id
    JOIN events e ON e.id = b.event_id
    ORDER BY b.created_at DESC
    LIMIT 5"
);
$recentBookingsStmt->execute();
$recentBookings = $recentBookingsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recentBookingsStmt->close();

$recentEventsStmt = $mysqli->prepare(
    "SELECT e.title, e.created_at, u.name AS organizer_name
     FROM events e
     LEFT JOIN users u ON u.id = e.organizer_id
     ORDER BY e.created_at DESC
     LIMIT 5"
);
$recentEventsStmt->execute();
$recentEvents = $recentEventsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recentEventsStmt->close();

$recentUsersStmt = $mysqli->prepare(
    "SELECT name, role, created_at
     FROM users
     ORDER BY created_at DESC
     LIMIT 5"
);
$recentUsersStmt->execute();
$recentUsers = $recentUsersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recentUsersStmt->close();

// Get events by category
$categoryStmt = $mysqli->prepare(
    "SELECT c.name, COUNT(e.id) as count
    FROM categories c
    LEFT JOIN events e ON c.id = e.category_id
    GROUP BY c.id, c.name
    ORDER BY count DESC"
);
$categoryStmt->execute();
$categoryStats = $categoryStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$categoryStmt->close();
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
        <div class="wrapper header-inner">
            <a href="index.php" class="brand-logo">Event Ethiopia</a>
            <button type="button" class="site-nav-toggle button button-alt" id="siteNavToggle" aria-expanded="false" aria-controls="site-main-nav">Menu</button>
            <nav class="main-nav" id="site-main-nav">
                <a href="admin-dashboard.php">Dashboard</a>
                <span class="nav-welcome">Welcome, <?= sanitize($_SESSION['user_name']) ?></span>
                <a href="logout.php" class="button button-alt">Logout</a>
            </nav>
        </div>
    </header>
    <main class="page-content wrapper">
        <div class="dashboard-container" id="dashboard-root">
            <button type="button" class="dashboard-drawer-toggle button button-alt" aria-expanded="false" aria-controls="dashboard-sidebar-nav">Menu</button>
            <aside class="dashboard-sidebar" id="dashboard-sidebar-nav">
                <h3>Admin Panel</h3>
                <nav class="sidebar-nav">
                    <a href="admin-dashboard.php" class="active">Dashboard</a>
                    <a href="admin-users.php">Users</a>
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
                    <h1>Welcome Admin 👋</h1>
                    <p>System overview, approvals, and recent platform activity.</p>
                </div>

                <!-- Stats Cards -->
                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <h3>Total Accounts</h3>
                        <strong><?= number_format($totalUsers) ?></strong>
                        <span class="change">Users + Organizers</span>
                    </div>
                    <div class="dashboard-card">
                        <h3>Total Events</h3>
                        <strong><?= number_format($totalEvents) ?></strong>
                        <span class="change">Across all organizers</span>
                    </div>
                    <div class="dashboard-card">
                        <h3>Total Bookings</h3>
                        <strong><?= number_format($totalBookings) ?></strong>
                        <span class="change">Completed + Pending</span>
                    </div>
                    <div class="dashboard-card">
                        <h3>Total Revenue</h3>
                        <strong>ETB <?= number_format($totalRevenue) ?></strong>
                        <span class="change">From booking records</span>
                    </div>
                    <div class="dashboard-card">
                        <h3>Upcoming Events</h3>
                        <strong><?= number_format($upcomingEvents) ?></strong>
                        <span class="change">Active/upcoming pipeline</span>
                    </div>
                    <div class="dashboard-card">
                        <h3>Pending Organizers</h3>
                        <strong><?= number_format($pendingOrganizers) ?></strong>
                        <span class="<?= $pendingOrganizers > 0 ? 'change negative' : 'change' ?>">
                            <?= $pendingOrganizers > 0 ? 'Needs review' : 'All reviewed' ?>
                        </span>
                    </div>
                </div>

                <div class="dashboard-card">
                    <h3>Quick Actions</h3>
                    <div class="card-actions">
                        <a class="button" href="admin-users.php">Manage Users</a>
                        <a class="button button-alt" href="admin-events.php">Review Events</a>
                        <a class="button button-alt" href="admin-reports.php">Open Reports</a>
                    </div>
                </div>

                <!-- Recent Bookings -->
                <div class="dashboard-card table-card">
                    <h3>Recent Bookings</h3>
                    <table class="list-table table-spaced">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Event</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentBookings as $booking): ?>
                                <tr>
                                    <td><?= sanitize($booking['user_name']) ?></td>
                                    <td><?= sanitize($booking['event_title']) ?></td>
                                    <td><?= date('M d, Y', strtotime($booking['created_at'])) ?></td>
                                    <td>ETB <?= number_format($booking['price']) ?></td>
                                    <td>
                                        <span class="badge status-<?= $booking['status'] ?>">
                                            <?= ucfirst($booking['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <a href="admin-bookings.php" class="table-link">View All Bookings →</a>
                </div>

                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <h3>Recent Event Activity</h3>
                        <table class="list-table table-spaced">
                            <thead>
                                <tr><th>Event</th><th>Organizer</th><th>Created</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentEvents as $event): ?>
                                    <tr>
                                        <td><?= sanitize($event['title']) ?></td>
                                        <td><?= sanitize($event['organizer_name'] ?? 'N/A') ?></td>
                                        <td><?= date('M d, Y', strtotime($event['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="dashboard-card">
                        <h3>Recent User Activity</h3>
                        <table class="list-table table-spaced">
                            <thead>
                                <tr><th>Name</th><th>Role</th><th>Joined</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentUsers as $user): ?>
                                    <tr>
                                        <td><?= sanitize($user['name']) ?></td>
                                        <td><?= sanitize(ucfirst($user['role'])) ?></td>
                                        <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Category Distribution -->
                <div class="dashboard-card">
                    <h3>Events by Category</h3>
                    <div class="category-stats">
                        <div class="category-stats-grid">
                            <?php foreach ($categoryStats as $cat): ?>
                                <div class="category-stat-item">
                                    <div class="category-stat-name"><?= sanitize($cat['name']) ?></div>
                                    <div class="category-stat-value"><?= $cat['count'] ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <footer class="site-footer">
        <div class="wrapper footer-inner">
            <div>
                <h3>Event Ethiopia</h3>
                <p>Admin Dashboard</p>
            </div>
        </div>
    </footer>
    <script src="js/script.js"></script>
</body>
</html>
