<?php
require_once 'includes/config.php';
require_login();
if (!is_role('organizer')) { header('Location: login.php'); exit; }
$pageTitle = 'Organizer Reports | Event Ethiopia';
$organizerId = (int) $_SESSION['user_id'];
$priceExpr = db_has_column($mysqli, 'events', 'price') ? 'e.price' : '0';
db_ensure_column($mysqli, 'bookings', 'check_in_status', "TINYINT(1) NOT NULL DEFAULT 0");

$summaryStmt = $mysqli->prepare("SELECT (SELECT COUNT(*) FROM events WHERE organizer_id=?) events_total,(SELECT COUNT(*) FROM bookings b JOIN events e ON e.id=b.event_id WHERE e.organizer_id=?) bookings_total,(SELECT SUM($priceExpr) FROM bookings b JOIN events e ON e.id=b.event_id WHERE e.organizer_id=?) total_revenue");
$summaryStmt->bind_param('iii', $organizerId, $organizerId, $organizerId);
$summaryStmt->execute();
$summary = $summaryStmt->get_result()->fetch_assoc();
$summaryStmt->close();

$eventsStmt = $mysqli->prepare("SELECT e.title,COUNT(b.id) bookings,SUM($priceExpr) revenue FROM events e LEFT JOIN bookings b ON b.event_id=e.id WHERE e.organizer_id=? GROUP BY e.id,e.title ORDER BY bookings DESC");
$eventsStmt->bind_param('i', $organizerId);
$eventsStmt->execute();
$rows = $eventsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$eventsStmt->close();

$monthlyStmt = $mysqli->prepare(
    "SELECT DATE_FORMAT(b.created_at, '%Y-%m') month_key, COUNT(*) bookings, SUM($priceExpr) revenue
    FROM bookings b
    JOIN events e ON e.id = b.event_id
    WHERE e.organizer_id = ?
    GROUP BY DATE_FORMAT(b.created_at, '%Y-%m')
    ORDER BY month_key DESC
    LIMIT 12"
);
$monthlyStmt->bind_param('i', $organizerId);
$monthlyStmt->execute();
$monthlyRows = $monthlyStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$monthlyStmt->close();

$attendanceStmt = $mysqli->prepare(
    "SELECT COUNT(*) total_bookings, SUM(CASE WHEN b.check_in_status = 1 THEN 1 ELSE 0 END) checked_in
    FROM bookings b
    JOIN events e ON e.id = b.event_id
    WHERE e.organizer_id = ?"
);
$attendanceStmt->bind_param('i', $organizerId);
$attendanceStmt->execute();
$attendance = $attendanceStmt->get_result()->fetch_assoc();
$attendanceStmt->close();
$attendanceRate = (int) ($attendance['total_bookings'] ?? 0) > 0 ? ((int) $attendance['checked_in'] / (int) $attendance['total_bookings']) * 100 : 0;
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= sanitize($pageTitle) ?></title><link rel="stylesheet" href="css/style.css"></head><body>
<main class="page-content wrapper"><section class="heading-bar"><h2>Performance Reports</h2><a href="organizer-dashboard.php" class="button button-alt">Back</a></section>
<div class="dashboard-grid"><div class="dashboard-card"><h3>My Events</h3><strong><?= (int) ($summary['events_total'] ?? 0) ?></strong></div><div class="dashboard-card"><h3>Total Bookings</h3><strong><?= (int) ($summary['bookings_total'] ?? 0) ?></strong></div><div class="dashboard-card"><h3>Total Revenue</h3><strong>ETB <?= number_format($summary['total_revenue'] ?? 0) ?></strong></div><div class="dashboard-card"><h3>Attendance Rate</h3><strong><?= number_format($attendanceRate, 1) ?>%</strong></div></div>
<div class="dashboard-card table-card"><h3>Event Breakdown</h3><table class="list-table"><thead><tr><th>Event</th><th>Bookings</th><th>Revenue</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><?= sanitize($row['title']) ?></td><td><?= (int) $row['bookings'] ?></td><td>ETB <?= number_format($row['revenue'] ?? 0) ?></td></tr><?php endforeach; ?></tbody></table></div>
<div class="dashboard-card table-card"><h3>Monthly Booking Trends</h3><table class="list-table"><thead><tr><th>Month</th><th>Bookings</th><th>Revenue</th></tr></thead><tbody><?php foreach ($monthlyRows as $row): ?><tr><td><?= sanitize($row['month_key']) ?></td><td><?= (int) $row['bookings'] ?></td><td>ETB <?= number_format($row['revenue'] ?? 0) ?></td></tr><?php endforeach; ?></tbody></table></div>
</main></body></html>
