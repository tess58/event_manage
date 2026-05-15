<?php
require_once 'includes/config.php';
require_login();
if (!is_role('admin')) { header('Location: login.php'); exit; }
$pageTitle = 'Admin Reviews | Event Ethiopia';
$message = '';
db_ensure_column($mysqli, 'users', 'is_blocked', "TINYINT(1) NOT NULL DEFAULT 0");
$search = trim($_GET['search'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_review'])) {
    $id = (int) $_POST['delete_review'];
    $stmt = $mysqli->prepare("DELETE FROM reviews WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    $message = 'Review deleted.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['block_user'])) {
    $id = (int) $_POST['block_user'];
    $stmt = $mysqli->prepare("UPDATE users SET is_blocked = 1 WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    $message = 'User blocked due to spam behavior.';
}

$where = "1=1";
$types = '';
$params = [];
if ($search !== '') {
    $where .= " AND (u.name LIKE ? OR e.title LIKE ? OR r.comment LIKE ?)";
    $q = '%' . $search . '%';
    $types = 'sss';
    $params = [$q, $q, $q];
}

$stmt = $mysqli->prepare("SELECT r.id,r.rating,r.comment,r.created_at,r.user_id,u.name user_name,e.title event_title FROM reviews r JOIN users u ON u.id=r.user_id JOIN events e ON e.id=r.event_id WHERE $where ORDER BY r.created_at DESC");
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$summaryStmt = $mysqli->prepare("SELECT e.title, COUNT(r.id) total_reviews, ROUND(AVG(r.rating),1) avg_rating FROM reviews r JOIN events e ON e.id=r.event_id GROUP BY e.id,e.title ORDER BY avg_rating DESC");
$summaryStmt->execute();
$summaryRows = $summaryStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$summaryStmt->close();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= sanitize($pageTitle) ?></title><link rel="stylesheet" href="css/style.css"></head><body>
<main class="page-content wrapper"><section class="heading-bar"><h2>Reviews</h2><a href="admin-dashboard.php" class="button button-alt">Back</a></section>
<?php if ($message): ?><div class="alert alert-success"><?= sanitize($message) ?></div><?php endif; ?>
<div class="dashboard-card">
<form method="get" class="filter-panel">
<input type="text" name="search" placeholder="Search by user/event/comment" value="<?= sanitize($search) ?>">
<button class="button" type="submit">Search</button>
</form>
</div>
<div class="dashboard-card"><table class="list-table"><thead><tr><th>User</th><th>Event</th><th>Rating</th><th>Comment</th><th>Date</th><th>Action</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?>
<tr><td><?= sanitize($row['user_name']) ?></td><td><?= sanitize($row['event_title']) ?></td><td><?= (int) $row['rating'] ?>/5</td><td><?= sanitize($row['comment']) ?></td><td><?= date('M d, Y', strtotime($row['created_at'])) ?></td><td><form method="post" style="display:flex; gap:8px;"><button class="button button-alt" name="delete_review" value="<?= (int) $row['id'] ?>">Delete</button><button class="button" name="block_user" value="<?= (int) $row['user_id'] ?>">Block User</button></form></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<div class="dashboard-card table-card"><h3>Event Ratings Summary</h3><table class="list-table"><thead><tr><th>Event</th><th>Reviews</th><th>Average Rating</th></tr></thead><tbody><?php foreach ($summaryRows as $row): ?><tr><td><?= sanitize($row['title']) ?></td><td><?= (int) $row['total_reviews'] ?></td><td><?= sanitize($row['avg_rating']) ?></td></tr><?php endforeach; ?></tbody></table></div>
</main></body></html>
