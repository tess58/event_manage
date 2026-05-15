<?php
require_once 'includes/config.php';
require_login();
if (!is_role('admin')) { header('Location: login.php'); exit; }
$pageTitle = 'Admin Bookings | Event Ethiopia';
$message = '';
$qrColumn = db_has_column($mysqli, 'bookings', 'qr_code') ? 'qr_code' : 'ticket_code';
ensure_booking_qr_schema($mysqli);
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportQuery = "SELECT b.id, u.name AS user_name, e.title AS event_title, b.$qrColumn AS code, b.status, b.check_in_status, b.payment_status, b.checked_in_at, b.created_at
                    FROM bookings b
                    JOIN users u ON u.id=b.user_id
                    JOIN events e ON e.id=b.event_id
                    ORDER BY b.created_at DESC";
    $exportRows = $mysqli->query($exportQuery)->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="bookings-report.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'User', 'Event', 'Code', 'Booking Status', 'Check-In Status', 'Payment Status', 'Checked In At', 'Created At']);
    foreach ($exportRows as $row) {
        fputcsv($out, [$row['id'], $row['user_name'], $row['event_title'], $row['code'], $row['status'], $row['check_in_status'], $row['payment_status'], $row['checked_in_at'], $row['created_at']]);
    }
    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    $id = (int) $_POST['booking_id'];
    $status = in_array($_POST['status'], ['pending','confirmed','cancelled'], true) ? $_POST['status'] : 'confirmed';
    $nextCheckIn = $status === 'cancelled' ? 'cancelled' : 'pending';
    $stmt = $mysqli->prepare("UPDATE bookings SET status=?, check_in_status=? WHERE id=?");
    $stmt->bind_param('ssi', $status, $nextCheckIn, $id);
    $stmt->execute();
    $stmt->close();
    $message = 'Booking status updated.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_checkin'])) {
    $id = (int) $_POST['toggle_checkin'];
    $stmt = $mysqli->prepare("UPDATE bookings SET check_in_status = CASE WHEN check_in_status='checked_in' THEN 'pending' ELSE 'checked_in' END, checked_in_at = CASE WHEN check_in_status='checked_in' THEN NULL ELSE NOW() END WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    $message = 'Check-in status updated.';
}

$where = ["1=1"];
$types = '';
$params = [];
if ($search !== '') {
    $where[] = "(u.name LIKE ? OR e.title LIKE ? OR b.$qrColumn LIKE ?)";
    $types .= 'sss';
    $q = '%' . $search . '%';
    $params[] = $q;
    $params[] = $q;
    $params[] = $q;
}
if (in_array($statusFilter, ['pending', 'confirmed', 'cancelled'], true)) {
    $where[] = "b.status = ?";
    $types .= 's';
    $params[] = $statusFilter;
}

$stmt = $mysqli->prepare("SELECT b.id,b.status,b.payment_status,b.check_in_status,b.checked_in_at,b.created_at,b.$qrColumn ticket_code,u.name user_name,e.title event_title FROM bookings b JOIN users u ON u.id=b.user_id JOIN events e ON e.id=b.event_id WHERE " . implode(' AND ', $where) . " ORDER BY b.created_at DESC");
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= sanitize($pageTitle) ?></title><link rel="stylesheet" href="css/style.css"></head><body>
<main class="page-content wrapper">
<section class="heading-bar"><h2>Bookings</h2><a href="admin-dashboard.php" class="button button-alt">Back</a></section>
<?php if ($message): ?><div class="alert alert-success"><?= sanitize($message) ?></div><?php endif; ?>
<div class="dashboard-card">
<form method="get" class="filter-panel">
<input type="text" name="search" placeholder="Search by user/event/code" value="<?= sanitize($search) ?>">
<select name="status"><option value="">All Statuses</option><option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option><option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option><option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option></select>
<button class="button" type="submit">Filter</button>
<a href="admin-bookings.php?export=csv" class="button button-alt">Export CSV</a>
</form>
</div>
<div class="dashboard-card"><table class="list-table"><thead><tr><th>User</th><th>Event</th><th>Code</th><th>Booking</th><th>Check-In</th><th>Payment</th><th>Date</th><th>Action</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?>
<tr>
<td><?= sanitize($row['user_name']) ?></td><td><?= sanitize($row['event_title']) ?></td><td><?= sanitize($row['ticket_code']) ?></td><td><span class="badge"><?= sanitize($row['status']) ?></span></td><td><span class="badge"><?= sanitize($row['check_in_status']) ?></span></td><td><?= sanitize($row['payment_status']) ?></td><td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
<td><form method="post" style="display:flex;gap:8px;flex-wrap:wrap;"><input type="hidden" name="booking_id" value="<?= (int) $row['id'] ?>"><button class="button button-alt" name="status" value="confirmed">Confirm</button><button class="button" name="status" value="cancelled">Cancel</button><button class="button button-alt" name="toggle_checkin" value="<?= (int) $row['id'] ?>">Toggle Check-In</button></form></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></main></body></html>
