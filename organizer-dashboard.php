<?php
require_once 'includes/config.php';
require_login();

if (!is_role('organizer')) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Organizer Dashboard | Event Ethiopia';
$organizerId = $_SESSION['user_id'];
$hasEventPrice = db_has_column($mysqli, 'events', 'price');
$priceExpr = $hasEventPrice ? 'e.price' : '0';
db_ensure_column($mysqli, 'events', 'lifecycle_status', "VARCHAR(20) DEFAULT NULL");

// Get organizer stats
$myEventsStmt = $mysqli->prepare("SELECT COUNT(*) as count FROM events WHERE organizer_id = ?");
$myEventsStmt->bind_param('i', $organizerId);
$myEventsStmt->execute();
$myEventsCount = $myEventsStmt->get_result()->fetch_assoc()['count'];
$myEventsStmt->close();

$activeEventsStmt = $mysqli->prepare("SELECT COUNT(*) as count FROM events WHERE organizer_id = ? AND status = 'published'");
$activeEventsStmt->bind_param('i', $organizerId);
$activeEventsStmt->execute();
$activeEvents = $activeEventsStmt->get_result()->fetch_assoc()['count'];
$activeEventsStmt->close();

$upcomingEventsStmt = $mysqli->prepare("SELECT COUNT(*) as count FROM events WHERE organizer_id = ? AND date >= CURDATE()");
$upcomingEventsStmt->bind_param('i', $organizerId);
$upcomingEventsStmt->execute();
$upcomingEvents = $upcomingEventsStmt->get_result()->fetch_assoc()['count'];
$upcomingEventsStmt->close();

$totalBookingsStmt = $mysqli->prepare(
    "SELECT COUNT(*) as count FROM bookings b 
    JOIN events e ON e.id = b.event_id 
    WHERE e.organizer_id = ?"
);
$totalBookingsStmt->bind_param('i', $organizerId);
$totalBookingsStmt->execute();
$totalBookings = $totalBookingsStmt->get_result()->fetch_assoc()['count'];
$totalBookingsStmt->close();

$totalAttendeesStmt = $mysqli->prepare(
    "SELECT COUNT(*) as count FROM bookings b
    JOIN events e ON e.id = b.event_id
    WHERE e.organizer_id = ? AND b.status = 'confirmed'"
);
$totalAttendeesStmt->bind_param('i', $organizerId);
$totalAttendeesStmt->execute();
$totalAttendees = $totalAttendeesStmt->get_result()->fetch_assoc()['count'];
$totalAttendeesStmt->close();

$totalRevenueStmt = $mysqli->prepare(
    "SELECT SUM($priceExpr) as total FROM bookings b 
    JOIN events e ON e.id = b.event_id 
    WHERE e.organizer_id = ?"
);
$totalRevenueStmt->bind_param('i', $organizerId);
$totalRevenueStmt->execute();
$totalRevenue = $totalRevenueStmt->get_result()->fetch_assoc()['total'] ?? 0;
$totalRevenueStmt->close();

$reviewsStmt = $mysqli->prepare(
    "SELECT COUNT(*) as count, ROUND(AVG(r.rating), 1) as avg_rating
    FROM reviews r 
    JOIN events e ON e.id = r.event_id 
    WHERE e.organizer_id = ?"
);
$reviewsStmt->bind_param('i', $organizerId);
$reviewsStmt->execute();
$reviewStats = $reviewsStmt->get_result()->fetch_assoc();
$reviewsStmt->close();

// Get my events
$myEventsListStmt = $mysqli->prepare(
    "SELECT e.id, e.title, e.date, e.location, e.image_url, 
    COUNT(b.id) as bookings, SUM($priceExpr) as revenue, e.status
    FROM events e
    LEFT JOIN bookings b ON e.id = b.event_id
    WHERE e.organizer_id = ?
    GROUP BY e.id
    ORDER BY e.date DESC
    LIMIT 5"
);
$myEventsListStmt->bind_param('i', $organizerId);
$myEventsListStmt->execute();
$myEventsList = $myEventsListStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$myEventsListStmt->close();

// Get recent bookings
$recentBookingsStmt = $mysqli->prepare(
    "SELECT b.id, u.name as user_name, e.title as event_title, b.created_at, b.status, $priceExpr as price
    FROM bookings b
    JOIN users u ON u.id = b.user_id
    JOIN events e ON e.id = b.event_id
    WHERE e.organizer_id = ?
    ORDER BY b.created_at DESC
    LIMIT 5"
);
$recentBookingsStmt->bind_param('i', $organizerId);
$recentBookingsStmt->execute();
$recentBookings = $recentBookingsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recentBookingsStmt->close();

// Get recent reviews
$recentReviewsStmt = $mysqli->prepare(
    "SELECT r.rating, r.comment, r.created_at, u.name AS user_name, e.title AS event_title
    FROM reviews r
    JOIN users u ON u.id = r.user_id
    JOIN events e ON e.id = r.event_id
    WHERE e.organizer_id = ?
    ORDER BY r.created_at DESC
    LIMIT 5"
);
$recentReviewsStmt->bind_param('i', $organizerId);
$recentReviewsStmt->execute();
$recentReviews = $recentReviewsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recentReviewsStmt->close();

// Get top event
$topEventStmt = $mysqli->prepare(
    "SELECT e.id, e.title, e.image_url, COUNT(b.id) as bookings, SUM($priceExpr) as revenue
    FROM events e
    LEFT JOIN bookings b ON e.id = b.event_id
    WHERE e.organizer_id = ?
    GROUP BY e.id
    ORDER BY bookings DESC
    LIMIT 1"
);
$topEventStmt->bind_param('i', $organizerId);
$topEventStmt->execute();
$topEvent = $topEventStmt->get_result()->fetch_assoc();
$topEventStmt->close();
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
                <a href="organizer-dashboard.php">Dashboard</a>
                <span class="nav-welcome">Welcome, <?= sanitize($_SESSION['user_name']) ?></span>
                <a href="logout.php" class="button button-alt">Logout</a>
            </nav>
        </div>
    </header>
    <main class="page-content wrapper">
        <div class="dashboard-container" id="dashboard-root">
            <button type="button" class="dashboard-drawer-toggle button button-alt" aria-expanded="false" aria-controls="dashboard-sidebar-nav">Menu</button>
            <aside class="dashboard-sidebar" id="dashboard-sidebar-nav">
                <h3>Organizer Panel</h3>
                <nav class="sidebar-nav">
                    <a href="organizer-dashboard.php" class="active">Dashboard</a>
                    <a href="organizer-events.php">My Events</a>
                    <a href="organizer-events.php#create-event">Create Event</a>
                    <a href="organizer-bookings.php">Bookings</a>
                    <a href="organizer-checkin.php">QR Check-In</a>
                    <a href="organizer-reports.php">Analytics</a>
                    <a href="organizer-reviews.php">Reviews</a>
                    <a href="organizer-notifications.php">Notifications</a>
                    <a href="organizer-profile.php">Profile</a>
                    <a href="organizer-settings.php">Settings</a>
                    <a href="logout.php">Logout</a>
                </nav>
            </aside>

            <div class="dashboard-content">
                <div class="dashboard-header">
                    <h1>Welcome, Organizer! 🎉</h1>
                    <p>Manage your events and track your performance.</p>
                </div>

                <!-- Stats Cards -->
                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <h3>My Events</h3>
                        <strong><?= number_format($myEventsCount) ?></strong>
                        <span class="change">Total created</span>
                    </div>
                    <div class="dashboard-card">
                        <h3>Active Events</h3>
                        <strong><?= number_format($activeEvents) ?></strong>
                        <span class="change">Published now</span>
                    </div>
                    <div class="dashboard-card">
                        <h3>Upcoming Events</h3>
                        <strong><?= number_format($upcomingEvents) ?></strong>
                        <span class="change">Scheduled ahead</span>
                    </div>
                    <div class="dashboard-card">
                        <h3>Total Bookings</h3>
                        <strong><?= number_format($totalBookings) ?></strong>
                        <span class="change">All-time bookings</span>
                    </div>
                    <div class="dashboard-card">
                        <h3>Total Attendees</h3>
                        <strong><?= number_format($totalAttendees) ?></strong>
                        <span class="change">Confirmed attendees</span>
                    </div>
                    <div class="dashboard-card">
                        <h3>Total Revenue</h3>
                        <strong>ETB <?= number_format($totalRevenue) ?></strong>
                        <span class="change">From booked tickets</span>
                    </div>
                    <div class="dashboard-card">
                        <h3>Reviews</h3>
                        <strong>⭐ <?= $reviewStats['avg_rating'] ?? 'N/A' ?></strong>
                        <span class="change"><?= $reviewStats['count'] ?> reviews</span>
                    </div>
                </div>

                <!-- Two Column Layout -->
                <div class="organizer-main-grid">
                    <!-- My Events Table -->
                    <div class="dashboard-card">
                        <div class="panel-header">
                            <h3>My Events</h3>
                            <a href="organizer-events.php" class="table-link">View All Events →</a>
                        </div>
                        <table class="list-table">
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Date</th>
                                    <th>Location</th>
                                    <th>Bookings</th>
                                    <th>Revenue</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($myEventsList as $event): ?>
                                    <tr>
                                        <td><?= sanitize($event['title']) ?></td>
                                        <td><?= date('M d', strtotime($event['date'])) ?></td>
                                        <td><?= sanitize($event['location']) ?></td>
                                        <td><?= $event['bookings'] ?? 0 ?></td>
                                        <td>ETB <?= number_format($event['revenue'] ?? 0) ?></td>
                                        <td>
                                            <span class="badge status-<?= $event['status'] ?>">
                                                <?= ucfirst($event['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Top Performing Event -->
                    <?php if ($topEvent): ?>
                        <div class="dashboard-card">
                            <h3>Top Performing Event</h3>
                            <img src="<?= sanitize($topEvent['image_url']) ?>" alt="<?= sanitize($topEvent['title']) ?>" class="top-event-image">
                            <div>
                                <h4 class="top-event-title"><?= sanitize($topEvent['title']) ?></h4>
                                <div class="top-event-metrics">
                                    <div class="top-event-metric-item">
                                        <p>Bookings</p>
                                        <strong><?= $topEvent['bookings'] ?? 0 ?></strong>
                                    </div>
                                    <div class="top-event-metric-item">
                                        <p>Revenue</p>
                                        <strong>ETB <?= number_format($topEvent['revenue'] ?? 0) ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="dashboard-card" style="margin: 24px 0;">
                    <div class="panel-header">
                        <h3>Quick Actions</h3>
                    </div>
                    <div class="hero-cta">
                        <a href="organizer-events.php#create-event" class="button">Quick Create Event</a>
                        <a href="organizer-reports.php" class="button button-alt">View Event Performance</a>
                        <a href="organizer-checkin.php" class="button button-alt">Monitor Attendance</a>
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
                    <a href="organizer-bookings.php" class="table-link">View All Bookings →</a>
                </div>

                <div class="dashboard-card table-card">
                    <h3>Recent Reviews</h3>
                    <table class="list-table table-spaced">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Event</th>
                                <th>Rating</th>
                                <th>Comment</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentReviews as $review): ?>
                                <tr>
                                    <td><?= sanitize($review['user_name']) ?></td>
                                    <td><?= sanitize($review['event_title']) ?></td>
                                    <td>⭐ <?= (int) $review['rating'] ?>/5</td>
                                    <td><?= sanitize($review['comment']) ?></td>
                                    <td><?= date('M d, Y', strtotime($review['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <a href="organizer-reviews.php" class="table-link">View All Reviews →</a>
                </div>
            </div>
        </div>
    </main>
    <footer class="site-footer">
        <div class="wrapper footer-inner">
            <div>
                <h3>Event Ethiopia</h3>
                <p>Organizer Dashboard</p>
            </div>
        </div>
    </footer>
    <script src="js/script.js"></script>
</body>
</html>
