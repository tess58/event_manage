<?php
require_once 'includes/config.php';
require_login();
if (!is_role('organizer')) { header('Location: login.php'); exit; }
$pageTitle = 'Organizer Events | Event Ethiopia';
$message = '';
$error = '';
$organizerId = (int) $_SESSION['user_id'];
db_ensure_column($mysqli, 'events', 'event_time', "TIME NULL");
db_ensure_column($mysqli, 'events', 'capacity', "INT DEFAULT 0");
db_ensure_column($mysqli, 'events', 'registration_deadline', "DATE NULL");
db_ensure_column($mysqli, 'events', 'ticket_type', "VARCHAR(50) DEFAULT 'Regular'");
db_ensure_column($mysqli, 'events', 'lifecycle_status', "VARCHAR(20) DEFAULT NULL");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_event'])) {
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $date = sanitize($_POST['date'] ?? '');
        $eventTime = sanitize($_POST['event_time'] ?? '');
        $location = sanitize($_POST['location'] ?? '');
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $imageUrl = filter_var($_POST['image_url'] ?? '', FILTER_SANITIZE_URL);
        $capacity = max(0, (int) ($_POST['capacity'] ?? 0));
        $ticketType = in_array($_POST['ticket_type'] ?? 'Regular', ['VIP', 'Regular', 'Free'], true) ? $_POST['ticket_type'] : 'Regular';
        $registrationDeadline = sanitize($_POST['registration_deadline'] ?? '');
        if (!$title || !$description || !$date || !$location || $categoryId <= 0 || !$eventTime) {
            $error = 'Please fill all required fields.';
        } else {
            $status = 'published';
            if (db_has_column($mysqli, 'events', 'price')) {
                $price = (float) ($_POST['price'] ?? 0);
                $stmt = $mysqli->prepare("INSERT INTO events (title,description,date,event_time,location,capacity,ticket_type,registration_deadline,price,image_url,category_id,organizer_id,status,lifecycle_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NULL)");
                $stmt->bind_param('sssssissdsiis', $title, $description, $date, $eventTime, $location, $capacity, $ticketType, $registrationDeadline, $price, $imageUrl, $categoryId, $organizerId, $status);
            } else {
                $stmt = $mysqli->prepare("INSERT INTO events (title,description,date,event_time,location,capacity,ticket_type,registration_deadline,image_url,category_id,organizer_id,status,lifecycle_status) VALUES (?,?,?,?,?,?,?,?,?,?,?, ?,NULL)");
                $stmt->bind_param('sssssisssiis', $title, $description, $date, $eventTime, $location, $capacity, $ticketType, $registrationDeadline, $imageUrl, $categoryId, $organizerId, $status);
            }
            $stmt->execute();
            $stmt->close();
            $message = 'Event created.';
        }
    } elseif (isset($_POST['update_event'])) {
        $id = (int) ($_POST['event_id'] ?? 0);
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $date = sanitize($_POST['date'] ?? '');
        $eventTime = sanitize($_POST['event_time'] ?? '');
        $location = sanitize($_POST['location'] ?? '');
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $capacity = max(0, (int) ($_POST['capacity'] ?? 0));
        $ticketType = in_array($_POST['ticket_type'] ?? 'Regular', ['VIP', 'Regular', 'Free'], true) ? $_POST['ticket_type'] : 'Regular';
        $registrationDeadline = sanitize($_POST['registration_deadline'] ?? '');
        $imageUrl = filter_var($_POST['image_url'] ?? '', FILTER_SANITIZE_URL);
        $lifecycleStatus = in_array($_POST['lifecycle_status'] ?? '', ['', 'cancelled', 'completed'], true) ? ($_POST['lifecycle_status'] ?: null) : null;
        if (db_has_column($mysqli, 'events', 'price')) {
            $price = (float) ($_POST['price'] ?? 0);
            $stmt = $mysqli->prepare("UPDATE events SET title=?,description=?,date=?,event_time=?,location=?,capacity=?,ticket_type=?,registration_deadline=?,price=?,image_url=?,category_id=?,lifecycle_status=? WHERE id=? AND organizer_id=?");
            $stmt->bind_param('sssssissdsiisi', $title, $description, $date, $eventTime, $location, $capacity, $ticketType, $registrationDeadline, $price, $imageUrl, $categoryId, $lifecycleStatus, $id, $organizerId);
        } else {
            $stmt = $mysqli->prepare("UPDATE events SET title=?,description=?,date=?,event_time=?,location=?,capacity=?,ticket_type=?,registration_deadline=?,image_url=?,category_id=?,lifecycle_status=? WHERE id=? AND organizer_id=?");
            $stmt->bind_param('sssssisssisii', $title, $description, $date, $eventTime, $location, $capacity, $ticketType, $registrationDeadline, $imageUrl, $categoryId, $lifecycleStatus, $id, $organizerId);
        }
        $stmt->execute();
        $stmt->close();
        $message = 'Event updated.';
    } elseif (isset($_POST['toggle_publish'])) {
        $id = (int) ($_POST['event_id'] ?? 0);
        $next = ($_POST['next_status'] ?? 'draft') === 'published' ? 'published' : 'draft';
        $stmt = $mysqli->prepare("UPDATE events SET status=? WHERE id=? AND organizer_id=?");
        $stmt->bind_param('sii', $next, $id, $organizerId);
        $stmt->execute();
        $stmt->close();
        $message = 'Event status updated.';
    } elseif (isset($_POST['duplicate_event'])) {
        $id = (int) ($_POST['event_id'] ?? 0);
        $stmt = $mysqli->prepare("INSERT INTO events (title,description,date,event_time,location,capacity,ticket_type,registration_deadline,image_url,category_id,organizer_id,status,lifecycle_status" . (db_has_column($mysqli, 'events', 'price') ? ",price" : "") . ")
            SELECT CONCAT(title,' (Copy)'),description,date,event_time,location,capacity,ticket_type,registration_deadline,image_url,category_id,organizer_id,'published',NULL" . (db_has_column($mysqli, 'events', 'price') ? ",price" : "") . " FROM events WHERE id=? AND organizer_id=?");
        $stmt->bind_param('ii', $id, $organizerId);
        $stmt->execute();
        $stmt->close();
        $message = 'Event duplicated and published.';
    } elseif (isset($_POST['delete_event'])) {
        $id = (int) $_POST['delete_event'];
        $stmt = $mysqli->prepare("DELETE FROM events WHERE id=? AND organizer_id=?");
        $stmt->bind_param('ii', $id, $organizerId);
        $stmt->execute();
        $stmt->close();
        $message = 'Event deleted.';
    }
}

$catStmt = $mysqli->prepare("SELECT id,name FROM categories ORDER BY name");
$catStmt->execute();
$categories = $catStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$catStmt->close();

$eventsStmt = $mysqli->prepare("SELECT e.id,e.title,e.description,e.date,e.event_time,e.location,e.capacity,e.ticket_type,e.registration_deadline,e.status,e.lifecycle_status,e.image_url,e.category_id," . (db_has_column($mysqli, 'events', 'price') ? "e.price," : "0 AS price,") . " c.name category_name FROM events e LEFT JOIN categories c ON c.id=e.category_id WHERE e.organizer_id=? ORDER BY e.date DESC");
$eventsStmt->bind_param('i', $organizerId);
$eventsStmt->execute();
$events = $eventsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$eventsStmt->close();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= sanitize($pageTitle) ?></title><link rel="stylesheet" href="css/style.css"></head><body>
<main class="page-content wrapper"><section class="heading-bar"><h2>My Events</h2><a href="organizer-dashboard.php" class="button button-alt">Back</a></section>
<?php if ($message): ?><div class="alert alert-success"><?= sanitize($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= sanitize($error) ?></div><?php endif; ?>
<div class="grid-2">
<div class="form-card" id="create-event">
<h3>Create Event</h3>
<form method="post" class="form-grid">
<input type="hidden" name="create_event" value="1">
<p style="margin:0;color:var(--text-secondary);">Step 1: Basics</p>
<input type="text" name="title" placeholder="Event title" required>
<textarea name="description" class="form-textarea" rows="4" placeholder="Description" required></textarea>
<select name="category_id" required><option value="">Category</option><?php foreach ($categories as $cat): ?><option value="<?= (int) $cat['id'] ?>"><?= sanitize($cat['name']) ?></option><?php endforeach; ?></select>
<input type="url" name="image_url" placeholder="Poster / Banner URL">
<p style="margin:0;color:var(--text-secondary);">Step 2: Schedule and Venue</p>
<input type="date" name="date" required>
<input type="time" name="event_time" required>
<input type="text" name="location" placeholder="Location" required>
<input type="date" name="registration_deadline" placeholder="Registration deadline">
<p style="margin:0;color:var(--text-secondary);">Step 3: Tickets</p>
<select name="ticket_type"><option value="Regular">Regular</option><option value="VIP">VIP</option><option value="Free">Free</option></select>
<input type="number" name="capacity" placeholder="Capacity" min="0">
<?php if (db_has_column($mysqli, 'events', 'price')): ?><input type="number" name="price" placeholder="Price" min="0" step="0.01"><?php endif; ?>
<button class="button" type="submit">Create</button>
</form>
</div>
<div class="dashboard-card">
<h3>Event List</h3>
<table class="list-table"><thead><tr><th>Title</th><th>Date/Time</th><th>Location</th><th>Type</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($events as $event): $displayStatus = $event['lifecycle_status'] ?: ((strtotime($event['date']) < strtotime(date('Y-m-d')) && $event['status'] === 'published') ? 'completed' : $event['status']); ?><tr><td><strong><?= sanitize($event['title']) ?></strong><br><small><?= sanitize($event['category_name'] ?? 'N/A') ?> | Capacity <?= (int) $event['capacity'] ?></small></td><td><?= date('M d, Y', strtotime($event['date'])) ?><br><small><?= sanitize(substr((string) $event['event_time'], 0, 5)) ?></small></td><td><?= sanitize($event['location']) ?></td><td><?= sanitize($event['ticket_type'] ?? 'Regular') ?><?php if (db_has_column($mysqli, 'events', 'price')): ?><br><small><?= (float) $event['price'] > 0 ? 'ETB '.number_format((float) $event['price']) : 'Free' ?></small><?php endif; ?></td><td><span class="badge"><?= sanitize($displayStatus) ?></span></td><td><details><summary class="button button-alt" style="cursor:pointer;">Manage</summary><form method="post" class="form-grid" style="margin-top:10px;"><input type="hidden" name="update_event" value="1"><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>"><input type="text" name="title" value="<?= sanitize($event['title']) ?>" required><textarea name="description" class="form-textarea" rows="3" required><?= sanitize($event['description']) ?></textarea><input type="date" name="date" value="<?= sanitize($event['date']) ?>" required><input type="time" name="event_time" value="<?= sanitize(substr((string) $event['event_time'], 0, 5)) ?>" required><input type="text" name="location" value="<?= sanitize($event['location']) ?>" required><select name="category_id" required><?php foreach ($categories as $cat): ?><option value="<?= (int) $cat['id'] ?>" <?= (int) $cat['id'] === (int) ($event['category_id'] ?? 0) ? 'selected' : '' ?>><?= sanitize($cat['name']) ?></option><?php endforeach; ?></select><select name="ticket_type"><option value="Regular" <?= ($event['ticket_type'] ?? '') === 'Regular' ? 'selected' : '' ?>>Regular</option><option value="VIP" <?= ($event['ticket_type'] ?? '') === 'VIP' ? 'selected' : '' ?>>VIP</option><option value="Free" <?= ($event['ticket_type'] ?? '') === 'Free' ? 'selected' : '' ?>>Free</option></select><input type="number" name="capacity" value="<?= (int) $event['capacity'] ?>" min="0"><input type="date" name="registration_deadline" value="<?= sanitize($event['registration_deadline'] ?? '') ?>"><?php if (db_has_column($mysqli, 'events', 'price')): ?><input type="number" name="price" value="<?= sanitize((string) $event['price']) ?>" min="0" step="0.01"><?php endif; ?><input type="url" name="image_url" value="<?= sanitize($event['image_url']) ?>"><select name="lifecycle_status"><option value="">Normal</option><option value="cancelled" <?= ($event['lifecycle_status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option><option value="completed" <?= ($event['lifecycle_status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option></select><button class="button" type="submit">Save</button></form><form method="post" style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;"><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>"><input type="hidden" name="next_status" value="<?= $event['status'] === 'published' ? 'draft' : 'published' ?>"><button class="button button-alt" name="toggle_publish" value="1"><?= $event['status'] === 'published' ? 'Unpublish' : 'Publish' ?></button><button class="button button-alt" name="duplicate_event" value="1">Duplicate</button><button class="button" name="delete_event" value="<?= (int) $event['id'] ?>">Delete</button></form></details></td></tr><?php endforeach; ?>
</tbody></table>
</div>
</div></main></body></html>
