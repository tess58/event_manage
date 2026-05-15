<?php
require_once 'includes/config.php';
require_login();
if (!is_role('admin')) { header('Location: login.php'); exit; }
$pageTitle = 'Admin Events | Event Ethiopia';
$message = '';
db_ensure_column($mysqli, 'events', 'is_featured', "TINYINT(1) NOT NULL DEFAULT 0");
$hasFeatured = db_has_column($mysqli, 'events', 'is_featured');
$hasPrice = db_has_column($mysqli, 'events', 'price');
$priceExpr = $hasPrice ? 'e.price' : '0';
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$categoryFilter = (int) ($_GET['category_id'] ?? 0);
$organizerFilter = (int) ($_GET['organizer_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_event'])) {
        $id = (int) $_POST['delete_event'];
        $stmt = $mysqli->prepare("DELETE FROM events WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $message = 'Event deleted.';
    }
    if (isset($_POST['set_status'])) {
        $id = (int) $_POST['event_id'];
        $status = in_array($_POST['set_status'], ['published','draft','cancelled'], true) ? $_POST['set_status'] : 'draft';
        $stmt = $mysqli->prepare("UPDATE events SET status=? WHERE id=?");
        $stmt->bind_param('si', $status, $id);
        $stmt->execute();
        $stmt->close();
        $message = 'Event status updated.';
    }
    if (isset($_POST['toggle_feature']) && $hasFeatured) {
        $id = (int) $_POST['toggle_feature'];
        $stmt = $mysqli->prepare("UPDATE events SET is_featured = CASE WHEN is_featured = 1 THEN 0 ELSE 1 END WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $message = 'Featured flag updated.';
    }
    if (isset($_POST['edit_event'])) {
        $id = (int) $_POST['event_id'];
        $title = sanitize($_POST['title'] ?? '');
        $location = sanitize($_POST['location'] ?? '');
        $date = sanitize($_POST['date'] ?? '');
        $status = in_array($_POST['status'], ['published', 'draft', 'cancelled'], true) ? $_POST['status'] : 'draft';
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $organizerId = (int) ($_POST['organizer_id'] ?? 0);
        if ($hasPrice) {
            $price = (float) ($_POST['price'] ?? 0);
            $stmt = $mysqli->prepare("UPDATE events SET title=?, date=?, location=?, status=?, category_id=?, organizer_id=?, price=? WHERE id=?");
            $stmt->bind_param('ssssiidi', $title, $date, $location, $status, $categoryId, $organizerId, $price, $id);
        } else {
            $stmt = $mysqli->prepare("UPDATE events SET title=?, date=?, location=?, status=?, category_id=?, organizer_id=? WHERE id=?");
            $stmt->bind_param('ssssiii', $title, $date, $location, $status, $categoryId, $organizerId, $id);
        }
        $stmt->execute();
        $stmt->close();
        $message = 'Event updated.';
    }
}

$categories = $mysqli->query("SELECT id, name FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$organizers = $mysqli->query("SELECT id, name FROM users WHERE role='organizer' ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$where = ["1=1"];
$types = '';
$params = [];
if ($search !== '') {
    $where[] = "(e.title LIKE ? OR e.location LIKE ?)";
    $types .= 'ss';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}
if (in_array($statusFilter, ['published', 'draft', 'cancelled'], true)) {
    $where[] = "e.status = ?";
    $types .= 's';
    $params[] = $statusFilter;
}
if ($categoryFilter > 0) {
    $where[] = "e.category_id = ?";
    $types .= 'i';
    $params[] = $categoryFilter;
}
if ($organizerFilter > 0) {
    $where[] = "e.organizer_id = ?";
    $types .= 'i';
    $params[] = $organizerFilter;
}

$stmt = $mysqli->prepare("SELECT e.id,e.title,e.date,e.location,e.status,$priceExpr AS price,e.category_id,e.organizer_id," . ($hasFeatured ? "e.is_featured," : "0 AS is_featured,") . " c.name category_name,u.name organizer_name,COUNT(b.id) AS attendees,SUM($priceExpr) AS revenue FROM events e LEFT JOIN categories c ON c.id=e.category_id LEFT JOIN users u ON u.id=e.organizer_id LEFT JOIN bookings b ON b.event_id=e.id WHERE " . implode(' AND ', $where) . " GROUP BY e.id ORDER BY e.date DESC");
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= sanitize($pageTitle) ?></title><link rel="stylesheet" href="css/style.css"></head>
<body><main class="page-content wrapper">
<section class="heading-bar"><h2>All Events</h2><a href="admin-dashboard.php" class="button button-alt">Back</a></section>
<?php if ($message): ?><div class="alert alert-success"><?= sanitize($message) ?></div><?php endif; ?>
<div class="dashboard-card">
<form method="get" class="filter-panel">
<input type="text" name="search" placeholder="Search title/location" value="<?= sanitize($search) ?>">
<select name="status"><option value="">All Statuses</option><option value="published" <?= $statusFilter === 'published' ? 'selected' : '' ?>>Published</option><option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option><option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option></select>
<select name="category_id"><option value="0">All Categories</option><?php foreach ($categories as $cat): ?><option value="<?= (int) $cat['id'] ?>" <?= $categoryFilter === (int) $cat['id'] ? 'selected' : '' ?>><?= sanitize($cat['name']) ?></option><?php endforeach; ?></select>
<select name="organizer_id"><option value="0">All Organizers</option><?php foreach ($organizers as $org): ?><option value="<?= (int) $org['id'] ?>" <?= $organizerFilter === (int) $org['id'] ? 'selected' : '' ?>><?= sanitize($org['name']) ?></option><?php endforeach; ?></select>
<button class="button">Apply</button>
</form>
</div>
<div class="dashboard-card"><table class="list-table"><thead><tr><th>Title</th><th>Date</th><th>Location</th><th>Category</th><th>Organizer</th><th>Status</th><th>Insights</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($events as $event): ?>
<tr>
<td><?= sanitize($event['title']) ?></td>
<td><?= date('M d, Y', strtotime($event['date'])) ?></td>
<td><?= sanitize($event['location']) ?></td>
<td><?= sanitize($event['category_name'] ?? 'N/A') ?></td>
<td><?= sanitize($event['organizer_name'] ?? 'N/A') ?></td>
<td><span class="badge"><?= sanitize(ucfirst($event['status'])) ?></span></td>
<td><?= (int) $event['attendees'] ?> attendees<br>ETB <?= number_format($event['revenue'] ?? 0) ?></td>
<td>
<form method="post" style="display:flex;gap:8px;flex-wrap:wrap;">
<input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
<button class="button button-alt" name="set_status" value="published">Publish</button>
<button class="button button-alt" name="set_status" value="cancelled">Cancel</button>
<button class="button button-alt" name="set_status" value="draft">Draft</button>
<?php if ($hasFeatured): ?><button class="button button-alt" name="toggle_feature" value="<?= (int) $event['id'] ?>"><?= (int) $event['is_featured'] === 1 ? 'Unfeature' : 'Feature' ?></button><?php endif; ?>
<button class="button" name="delete_event" value="<?= (int) $event['id'] ?>">Delete</button>
</form>
<details style="margin-top:8px;">
<summary>Edit</summary>
<form method="post" class="form-grid" style="margin-top:8px;">
<input type="hidden" name="edit_event" value="1">
<input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
<input type="text" name="title" value="<?= sanitize($event['title']) ?>" required>
<input type="date" name="date" value="<?= date('Y-m-d', strtotime($event['date'])) ?>" required>
<input type="text" name="location" value="<?= sanitize($event['location']) ?>" required>
<select name="category_id"><?php foreach ($categories as $cat): ?><option value="<?= (int) $cat['id'] ?>" <?= (int) $event['category_id'] === (int) $cat['id'] ? 'selected' : '' ?>><?= sanitize($cat['name']) ?></option><?php endforeach; ?></select>
<select name="organizer_id"><?php foreach ($organizers as $org): ?><option value="<?= (int) $org['id'] ?>" <?= (int) $event['organizer_id'] === (int) $org['id'] ? 'selected' : '' ?>><?= sanitize($org['name']) ?></option><?php endforeach; ?></select>
<select name="status"><option value="published" <?= $event['status'] === 'published' ? 'selected' : '' ?>>Published</option><option value="draft" <?= $event['status'] === 'draft' ? 'selected' : '' ?>>Draft</option><option value="cancelled" <?= $event['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option></select>
<?php if ($hasPrice): ?><input type="number" name="price" step="0.01" min="0" value="<?= sanitize((string) ($event['price'] ?? 0)) ?>"><?php endif; ?>
<button class="button" type="submit">Save</button>
</form>
</details>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</main></body></html>
