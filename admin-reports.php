<?php
require_once 'includes/config.php';
require_login();
if (!is_role('admin')) { header('Location: login.php'); exit; }
$pageTitle = 'Admin Reports | Event Ethiopia';
$priceExpr = db_has_column($mysqli, 'events', 'price') ? 'e.price' : '0';

$summaryStmt = $mysqli->prepare("SELECT (SELECT COUNT(*) FROM users) users_total,(SELECT COUNT(*) FROM events) events_total,(SELECT COUNT(*) FROM bookings) bookings_total,(SELECT ROUND(AVG(rating),1) FROM reviews) avg_rating");
$summaryStmt->execute();
$summary = $summaryStmt->get_result()->fetch_assoc();
$summaryStmt->close();

$topEventsStmt = $mysqli->prepare("SELECT e.title, COUNT(b.id) bookings, SUM($priceExpr) revenue FROM events e LEFT JOIN bookings b ON b.event_id=e.id GROUP BY e.id,e.title ORDER BY bookings DESC LIMIT 10");
$topEventsStmt->execute();
$topEvents = $topEventsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$topEventsStmt->close();

$monthlyStmt = $mysqli->prepare(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_label, COUNT(*) AS total_bookings
     FROM bookings
     GROUP BY DATE_FORMAT(created_at, '%Y-%m')
     ORDER BY month_label DESC
     LIMIT 12"
);
$monthlyStmt->execute();
$monthlyBookings = $monthlyStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$monthlyStmt->close();

$userGrowthStmt = $mysqli->prepare(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_label, COUNT(*) AS total_users
     FROM users
     GROUP BY DATE_FORMAT(created_at, '%Y-%m')
     ORDER BY month_label DESC
     LIMIT 12"
);
$userGrowthStmt->execute();
$userGrowth = $userGrowthStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$userGrowthStmt->close();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= sanitize($pageTitle) ?></title><link rel="stylesheet" href="css/style.css"></head><body>
<main class="page-content wrapper">
<section class="heading-bar"><h2>Reports</h2><a href="admin-dashboard.php" class="button button-alt">Back</a></section>
<div class="dashboard-grid">
<div class="dashboard-card"><h3>Total Users</h3><strong><?= (int) ($summary['users_total'] ?? 0) ?></strong></div>
<div class="dashboard-card"><h3>Total Events</h3><strong><?= (int) ($summary['events_total'] ?? 0) ?></strong></div>
<div class="dashboard-card"><h3>Total Bookings</h3><strong><?= (int) ($summary['bookings_total'] ?? 0) ?></strong></div>
<div class="dashboard-card"><h3>Average Rating</h3><strong><?= sanitize($summary['avg_rating'] ?? 'N/A') ?></strong></div>
</div>
<div class="dashboard-card table-card">
<h3>Top Events by Bookings</h3>
<table class="list-table"><thead><tr><th>Event</th><th>Bookings</th><th>Revenue</th></tr></thead><tbody>
<?php foreach ($topEvents as $event): ?><tr><td><?= sanitize($event['title']) ?></td><td><?= (int) $event['bookings'] ?></td><td>ETB <?= number_format($event['revenue'] ?? 0) ?></td></tr><?php endforeach; ?>
</tbody></table>
</div>
<div class="dashboard-grid">
<div class="dashboard-card">
<h3>Bookings Per Month</h3>
<table class="list-table"><thead><tr><th>Month</th><th>Bookings</th></tr></thead><tbody>
<?php foreach ($monthlyBookings as $row): ?><tr><td><?= sanitize($row['month_label']) ?></td><td><?= (int) $row['total_bookings'] ?></td></tr><?php endforeach; ?>
</tbody></table>
</div>
<div class="dashboard-card">
<h3>User Growth Per Month</h3>
<table class="list-table"><thead><tr><th>Month</th><th>New Users</th></tr></thead><tbody>
<?php foreach ($userGrowth as $row): ?><tr><td><?= sanitize($row['month_label']) ?></td><td><?= (int) $row['total_users'] ?></td></tr><?php endforeach; ?>
</tbody></table>
</div>
</div>
</main></body></html>
