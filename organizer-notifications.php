<?php
require_once 'includes/config.php';
require_login();
if (!is_role('organizer')) {
    header('Location: login.php');
    exit;
}
$pageTitle = 'Organizer Notifications | Event Ethiopia';
$organizerId = (int) $_SESSION['user_id'];
$message = '';
$error = '';
ensure_event_announcement_schema($mysqli);

$eventsStmt = $mysqli->prepare("SELECT id, title FROM events WHERE organizer_id=? ORDER BY date DESC");
$eventsStmt->bind_param('i', $organizerId);
$eventsStmt->execute();
$orgEvents = $eventsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$eventsStmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_read'])) {
        $id = (int) ($_POST['notification_id'] ?? 0);
        $stmt = $mysqli->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?");
        $stmt->bind_param('ii', $id, $organizerId);
        $stmt->execute();
        $stmt->close();
        $message = 'Marked as read.';
    } elseif (isset($_POST['delete_inbox'])) {
        $id = (int) ($_POST['notification_id'] ?? 0);
        $stmt = $mysqli->prepare("DELETE FROM notifications WHERE id=? AND user_id=?");
        $stmt->bind_param('ii', $id, $organizerId);
        $stmt->execute();
        $deleted = $stmt->affected_rows > 0;
        $stmt->close();
        $message = $deleted ? 'Notification removed.' : 'Nothing to remove.';
    } elseif (isset($_POST['delete_announcement'])) {
        $id = (int) ($_POST['announcement_id'] ?? 0);
        $d = $mysqli->prepare("DELETE FROM event_announcement_dismissals WHERE announcement_id=?");
        if ($d) {
            $d->bind_param('i', $id);
            $d->execute();
            $d->close();
        }
        $stmt = $mysqli->prepare("DELETE FROM event_announcements WHERE id=? AND organizer_id=?");
        $stmt->bind_param('ii', $id, $organizerId);
        $stmt->execute();
        $deleted = $stmt->affected_rows > 0;
        $stmt->close();
        $message = $deleted ? 'Announcement removed for all attendees.' : 'Unable to remove announcement.';
    } elseif (isset($_POST['send_update'])) {
        $eventId = (int) ($_POST['event_id'] ?? 0);
        $annTitle = trim($_POST['ann_title'] ?? '');
        $announcement = trim($_POST['announcement'] ?? '');
        [$ok, $err, $meta] = create_event_announcement($mysqli, $organizerId, $eventId, $annTitle !== '' ? $annTitle : 'Event update', $announcement);
        if ($ok && is_array($meta)) {
            $n = (int) ($meta['recipient_count'] ?? 0);
            $message = 'Update sent. ' . $n . ' confirmed attendee' . ($n === 1 ? '' : 's') . ' can see it in their account.';
        } else {
            $error = $err ?: 'Could not send update.';
        }
    }
}

$stmt = $mysqli->prepare("SELECT id, title, message, type, is_read, created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 100");
$stmt->bind_param('i', $organizerId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$sentStmt = $mysqli->prepare(
    "SELECT a.id, a.title, a.message, a.created_at, e.title AS event_title
     FROM event_announcements a
     JOIN events e ON e.id = a.event_id
     WHERE a.organizer_id=?
     ORDER BY a.created_at DESC LIMIT 50"
);
$sentStmt->bind_param('i', $organizerId);
$sentStmt->execute();
$sentRows = $sentStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$sentStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle) ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<main class="page-content wrapper">
    <section class="heading-bar">
        <h2>Notifications</h2>
        <a href="organizer-dashboard.php" class="button button-alt">Back</a>
    </section>
    <?php if ($message): ?><div class="alert alert-success"><?= sanitize($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= sanitize($error) ?></div><?php endif; ?>

    <div class="grid-2">
        <div class="dashboard-card">
            <h3>Send update to attendees</h3>
            <p class="text-muted" style="margin-top:0;color:var(--text-secondary);font-size:0.95rem;">Confirmed bookings for the selected event will see this message in their dashboard under Notifications.</p>
            <form method="post" class="form-grid">
                <div>
                    <label for="event_id">Event</label>
                    <select id="event_id" name="event_id" required>
                        <option value="">Select event</option>
                        <?php foreach ($orgEvents as $event): ?>
                            <option value="<?= (int) $event['id'] ?>"><?= sanitize($event['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="ann_title">Title</label>
                    <input type="text" id="ann_title" name="ann_title" maxlength="180" placeholder="e.g. Venue change, gate time">
                </div>
                <div>
                    <label for="announcement">Message</label>
                    <textarea class="form-textarea" id="announcement" name="announcement" rows="5" required placeholder="Your message to everyone booked for this event"></textarea>
                </div>
                <button class="button" name="send_update" value="1" type="submit">Send update</button>
            </form>
        </div>
        <div class="dashboard-card table-card">
            <h3>Inbox</h3>
            <div class="table-scroll">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Message</th>
                            <th>Date</th>
                            <th class="table-actions-col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= sanitize($row['title']) ?><?= (int) $row['is_read'] === 0 ? ' •' : '' ?></td>
                                <td><?= sanitize($row['message']) ?></td>
                                <td><?= date('M d, Y H:i', strtotime($row['created_at'])) ?></td>
                                <td class="table-actions-col">
                                    <div class="table-actions">
                                        <?php if ((int) $row['is_read'] === 0): ?>
                                            <form method="post" class="inline-form">
                                                <input type="hidden" name="notification_id" value="<?= (int) $row['id'] ?>">
                                                <button class="button button-alt" name="mark_read" value="1" type="submit">Read</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" class="inline-form" onsubmit="return confirm('Remove this notification?');">
                                            <input type="hidden" name="notification_id" value="<?= (int) $row['id'] ?>">
                                            <button class="button button-alt" name="delete_inbox" value="1" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="4">No messages yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="dashboard-card table-card" style="margin-top:24px;">
        <h3>Sent event updates</h3>
        <div class="table-scroll">
            <table class="list-table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Title</th>
                        <th>Message</th>
                        <th>Sent</th>
                        <th class="table-actions-col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sentRows as $row): ?>
                        <tr>
                            <td><?= sanitize($row['event_title']) ?></td>
                            <td><?= sanitize($row['title']) ?></td>
                            <td><?= sanitize($row['message']) ?></td>
                            <td><?= date('M d, Y H:i', strtotime($row['created_at'])) ?></td>
                            <td class="table-actions-col">
                                <form method="post" onsubmit="return confirm('Delete this update for all attendees?');">
                                    <input type="hidden" name="announcement_id" value="<?= (int) $row['id'] ?>">
                                    <button class="button button-alt" name="delete_announcement" value="1" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($sentRows)): ?>
                        <tr><td colspan="5">No announcements sent yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
    <script src="js/script.js"></script>
</body>
</html>
