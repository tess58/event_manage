<?php
require_once 'includes/config.php';
require_login();
if (!is_role('organizer')) { header('Location: login.php'); exit; }
$pageTitle = 'Organizer Bookings | Event Ethiopia';
$organizerId = (int) $_SESSION['user_id'];
$message = '';
$qrColumn = db_has_column($mysqli, 'bookings', 'qr_code') ? 'qr_code' : 'ticket_code';
ensure_booking_qr_schema($mysqli);
$search = trim($_GET['search'] ?? '');
$attendeeFilter = in_array($_GET['attendee_status'] ?? '', ['checked_in', 'pending'], true) ? $_GET['attendee_status'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['status'])) {
        $id = (int) $_POST['booking_id'];
        $status = in_array($_POST['status'], ['pending','confirmed','cancelled'], true) ? $_POST['status'] : 'confirmed';
        $nextCheckIn = $status === 'cancelled' ? 'cancelled' : 'pending';
        $stmt = $mysqli->prepare("UPDATE bookings b JOIN events e ON e.id=b.event_id SET b.status=?, b.check_in_status=? WHERE b.id=? AND e.organizer_id=?");
        $stmt->bind_param('ssii', $status, $nextCheckIn, $id, $organizerId);
        $stmt->execute();
        $stmt->close();
        $message = 'Booking updated.';
    } elseif (isset($_POST['check_in'])) {
        $id = (int) $_POST['booking_id'];
        $stmt = $mysqli->prepare("UPDATE bookings b JOIN events e ON e.id=b.event_id SET b.check_in_status='checked_in',b.checked_in_at=NOW(),e.attendance_count=e.attendance_count+1 WHERE b.id=? AND e.organizer_id=? AND b.check_in_status!='checked_in'");
        $stmt->bind_param('ii', $id, $organizerId);
        $stmt->execute();
        $message = $stmt->affected_rows > 0 ? 'Attendee checked in successfully.' : 'Already checked-in or invalid booking.';
        $stmt->close();
    }
}

$whereParts = ["e.organizer_id=?"];
$types = 'i';
$params = [$organizerId];
if ($search !== '') {
    $whereParts[] = "(u.name LIKE ? OR u.email LIKE ? OR e.title LIKE ? OR b.$qrColumn LIKE ?)";
    $types .= 'ssss';
    $q = '%' . $search . '%';
    $params[] = $q;
    $params[] = $q;
    $params[] = $q;
    $params[] = $q;
}
if ($attendeeFilter === 'checked_in') {
    $whereParts[] = "b.check_in_status='checked_in'";
} elseif ($attendeeFilter === 'pending') {
    $whereParts[] = "b.check_in_status!='checked_in'";
}
$stmt = $mysqli->prepare("SELECT b.id,b.status,b.created_at,b.$qrColumn ticket_code,b.ticket_type,b.payment_status,b.check_in_status,b.checked_in_at,u.name user_name,u.email user_email,e.title event_title FROM bookings b JOIN users u ON u.id=b.user_id JOIN events e ON e.id=b.event_id WHERE " . implode(' AND ', $whereParts) . " ORDER BY b.created_at DESC");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendees.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name', 'Email', 'Event', 'Ticket Type', 'Status', 'Payment', 'Checked In', 'Booking Date']);
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['user_name'],
            $row['user_email'],
            $row['event_title'],
            $row['ticket_type'],
            $row['status'],
            $row['payment_status'],
            ((int) $row['check_in_status'] === 1 ? 'Yes' : 'No'),
            $row['created_at']
        ]);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= sanitize($pageTitle) ?></title><link rel="stylesheet" href="css/style.css"></head><body>
<main class="page-content wrapper"><section class="heading-bar"><h2>Bookings For My Events</h2><div class="hero-cta"><a href="organizer-bookings.php?export=csv" class="button button-alt">Export Attendees CSV</a><a href="organizer-dashboard.php" class="button button-alt">Back</a></div></section>
<?php if ($message): ?><div class="alert alert-success"><?= sanitize($message) ?></div><?php endif; ?>
<div class="dashboard-card"><form method="get" class="filter-panel"><input type="text" name="search" placeholder="Search attendees..." value="<?= sanitize($search) ?>"><select name="attendee_status"><option value="">All attendees</option><option value="checked_in" <?= $attendeeFilter === 'checked_in' ? 'selected' : '' ?>>Checked-in attendees</option><option value="pending" <?= $attendeeFilter === 'pending' ? 'selected' : '' ?>>Pending attendees</option></select><button class="button" type="submit">Apply</button></form></div>
<div class="dashboard-card table-card"><div class="table-scroll"><table class="list-table"><thead><tr><th>User</th><th>Event</th><th>Ticket</th><th>Status</th><th>Check-In</th><th>Date</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><?= sanitize($row['user_name']) ?><br><small><?= sanitize($row['user_email']) ?></small></td><td><?= sanitize($row['event_title']) ?></td><td><small>Code: <?= sanitize($row['ticket_code']) ?></small><br><small>Ticket: <?= sanitize($row['ticket_type']) ?></small><br><small>Payment: <?= sanitize($row['payment_status']) ?></small></td><td><?= sanitize($row['status']) ?></td><td><?= sanitize($row['check_in_status'] === 'checked_in' ? 'Checked-in' : 'Pending') ?></td><td><?= date('M d, Y', strtotime($row['created_at'])) ?></td><td><form method="post" style="display:flex;gap:8px;flex-wrap:wrap;"><input type="hidden" name="booking_id" value="<?= (int) $row['id'] ?>"><button class="button button-alt" name="status" value="confirmed">Approve</button><button class="button button-alt" name="status" value="pending">Pending</button><button class="button" name="status" value="cancelled">Reject</button><?php if ($row['check_in_status'] !== 'checked_in'): ?><button class="button button-alt" name="check_in" value="1">Check-In</button><?php endif; ?></form></td></tr><?php endforeach; ?>
</tbody></table></div></div></main></body></html>
