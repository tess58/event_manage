<?php
require_once 'includes/config.php';
require_login();
if (!is_role('admin')) {
    header('Location: login.php');
    exit;
}
$pageTitle = 'Admin Organizers | Event Ethiopia';
$message = '';
db_ensure_column($mysqli, 'users', 'is_blocked', "TINYINT(1) NOT NULL DEFAULT 0");
$viewId = (int) ($_GET['view'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve'])) {
        $id = (int) $_POST['approve'];
        $stmt = $mysqli->prepare("UPDATE users SET status='approved' WHERE id=? AND role='organizer'");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $message = 'Organizer approved.';
    } elseif (isset($_POST['reject'])) {
        $id = (int) $_POST['reject'];
        $stmt = $mysqli->prepare("UPDATE users SET status='rejected' WHERE id=? AND role='organizer'");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $message = 'Organizer rejected.';
    } elseif (isset($_POST['toggle_block'])) {
        $id = (int) $_POST['toggle_block'];
        $stmt = $mysqli->prepare("UPDATE users SET is_blocked = CASE WHEN is_blocked=1 THEN 0 ELSE 1 END WHERE id=? AND role='organizer'");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $message = 'Organizer suspension status updated.';
    }
}

$stmt = $mysqli->prepare("SELECT id, name, email, status, is_blocked, created_at FROM users WHERE role='organizer' ORDER BY created_at DESC");
$stmt->execute();
$organizers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$profile = null;
$performance = ['events_total' => 0, 'bookings_total' => 0, 'revenue_total' => 0, 'avg_rating' => null];
$events = [];
if ($viewId > 0) {
    $profileStmt = $mysqli->prepare("SELECT id, name, email, status, is_blocked, created_at FROM users WHERE id=? AND role='organizer' LIMIT 1");
    $profileStmt->bind_param('i', $viewId);
    $profileStmt->execute();
    $profile = $profileStmt->get_result()->fetch_assoc();
    $profileStmt->close();
    if ($profile) {
        $priceExpr = db_has_column($mysqli, 'events', 'price') ? 'e.price' : '0';
        $perfStmt = $mysqli->prepare(
            "SELECT COUNT(DISTINCT e.id) AS events_total,
                    COUNT(b.id) AS bookings_total,
                    SUM($priceExpr) AS revenue_total,
                    ROUND(AVG(r.rating),1) AS avg_rating
             FROM events e
             LEFT JOIN bookings b ON b.event_id = e.id
             LEFT JOIN reviews r ON r.event_id = e.id
             WHERE e.organizer_id = ?"
        );
        $perfStmt->bind_param('i', $viewId);
        $perfStmt->execute();
        $performance = $perfStmt->get_result()->fetch_assoc();
        $perfStmt->close();

        $eventsStmt = $mysqli->prepare("SELECT title, date, status FROM events WHERE organizer_id=? ORDER BY date DESC");
        $eventsStmt->bind_param('i', $viewId);
        $eventsStmt->execute();
        $events = $eventsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $eventsStmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= sanitize($pageTitle) ?></title><link rel="stylesheet" href="css/style.css"></head>
<body>
<main class="page-content wrapper">
<section class="heading-bar"><h2>Organizer Approvals</h2><a href="admin-dashboard.php" class="button button-alt">Back</a></section>
<?php if ($message): ?><div class="alert alert-success"><?= sanitize($message) ?></div><?php endif; ?>
<div class="dashboard-card">
<table class="list-table"><thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($organizers as $row): ?>
<tr>
<td><?= sanitize($row['name']) ?></td>
<td><?= sanitize($row['email']) ?></td>
<td>
<span class="badge status-<?= sanitize($row['status']) ?>"><?= sanitize(ucfirst($row['status'])) ?></span>
<?php if ((int) $row['is_blocked'] === 1): ?><span class="badge status-rejected">Suspended</span><?php endif; ?>
</td>
<td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
<td>
<form method="post" style="display:flex;gap:8px;">
<a class="button button-alt" href="admin-organizers.php?view=<?= (int) $row['id'] ?>">Profile</a>
<button class="button button-alt" name="approve" value="<?= (int) $row['id'] ?>">Approve</button>
<button class="button" name="reject" value="<?= (int) $row['id'] ?>">Reject</button>
<button class="button button-alt" name="toggle_block" value="<?= (int) $row['id'] ?>">
<?= (int) $row['is_blocked'] === 1 ? 'Unsuspend' : 'Suspend' ?>
</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php if ($profile): ?>
<div class="dashboard-card table-card">
<h3>Organizer Profile: <?= sanitize($profile['name']) ?></h3>
<p><?= sanitize($profile['email']) ?> • Joined <?= date('M d, Y', strtotime($profile['created_at'])) ?></p>
<div class="dashboard-grid">
<div class="dashboard-card"><h3>Events</h3><strong><?= (int) ($performance['events_total'] ?? 0) ?></strong></div>
<div class="dashboard-card"><h3>Bookings</h3><strong><?= (int) ($performance['bookings_total'] ?? 0) ?></strong></div>
<div class="dashboard-card"><h3>Revenue</h3><strong>ETB <?= number_format($performance['revenue_total'] ?? 0) ?></strong></div>
<div class="dashboard-card"><h3>Avg Rating</h3><strong><?= sanitize($performance['avg_rating'] ?? 'N/A') ?></strong></div>
</div>
<table class="list-table"><thead><tr><th>Event</th><th>Date</th><th>Status</th></tr></thead><tbody>
<?php foreach ($events as $event): ?><tr><td><?= sanitize($event['title']) ?></td><td><?= date('M d, Y', strtotime($event['date'])) ?></td><td><?= sanitize($event['status']) ?></td></tr><?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
</main>
</body></html>
