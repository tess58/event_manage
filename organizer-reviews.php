<?php
require_once 'includes/config.php';
require_login();
if (!is_role('organizer')) {
    header('Location: login.php');
    exit;
}
$pageTitle = 'Organizer Reviews | Event Ethiopia';
$organizerId = (int) $_SESSION['user_id'];
db_ensure_column($mysqli, 'reviews', 'organizer_reply', "TEXT NULL");
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_reply'])) {
    $reviewId = (int) ($_POST['review_id'] ?? 0);
    $reply = trim($_POST['organizer_reply'] ?? '');
    $stmt = $mysqli->prepare("UPDATE reviews r JOIN events e ON e.id=r.event_id SET r.organizer_reply=? WHERE r.id=? AND e.organizer_id=?");
    $stmt->bind_param('sii', $reply, $reviewId, $organizerId);
    $stmt->execute();
    $stmt->close();
    $message = 'Reply saved.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_reply'])) {
    $reviewId = (int) ($_POST['review_id'] ?? 0);
    $empty = '';
    $stmt = $mysqli->prepare("UPDATE reviews r JOIN events e ON e.id=r.event_id SET r.organizer_reply=NULL WHERE r.id=? AND e.organizer_id=?");
    $stmt->bind_param('ii', $reviewId, $organizerId);
    $stmt->execute();
    $stmt->close();
    $message = 'Reply removed.';
}

$stmt = $mysqli->prepare("SELECT r.id,r.rating,r.comment,r.organizer_reply,r.created_at,u.name user_name,e.title event_title FROM reviews r JOIN users u ON u.id=r.user_id JOIN events e ON e.id=r.event_id WHERE e.organizer_id=? ORDER BY r.created_at DESC");
$stmt->bind_param('i', $organizerId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
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
        <h2>Reviews &amp; Feedback</h2>
        <a href="organizer-dashboard.php" class="button button-alt">Back</a>
    </section>
    <?php if ($message): ?><div class="alert alert-success"><?= sanitize($message) ?></div><?php endif; ?>
    <div class="dashboard-card table-card">
        <div class="table-scroll">
            <table class="list-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Event</th>
                        <th>Rating</th>
                        <th>Review</th>
                        <th>Your reply</th>
                        <th>Date</th>
                        <th class="table-actions-col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= sanitize($row['user_name']) ?></td>
                            <td><?= sanitize($row['event_title']) ?></td>
                            <td>⭐ <?= (int) $row['rating'] ?>/5</td>
                            <td><?= nl2br(sanitize($row['comment'])) ?></td>
                            <td>
                                <?php if (!empty($row['organizer_reply'])): ?>
                                    <div class="review-reply-bubble"><?= nl2br(sanitize($row['organizer_reply'])) ?></div>
                                <?php else: ?>
                                    <span class="text-muted" style="color:var(--text-secondary);">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                            <td class="table-actions-col">
                                <div class="table-actions table-actions--stack">
                                    <form method="post" class="form-grid review-reply-form">
                                        <input type="hidden" name="save_reply" value="1">
                                        <input type="hidden" name="review_id" value="<?= (int) $row['id'] ?>">
                                        <textarea class="form-textarea" name="organizer_reply" rows="2" placeholder="Reply to this guest"><?= sanitize($row['organizer_reply'] ?? '') ?></textarea>
                                        <button class="button button-alt" type="submit">Save reply</button>
                                    </form>
                                    <?php if (!empty($row['organizer_reply'])): ?>
                                        <form method="post" class="inline-form" onsubmit="return confirm('Remove your public reply?');">
                                            <input type="hidden" name="review_id" value="<?= (int) $row['id'] ?>">
                                            <button class="button button-alt" name="delete_reply" value="1" type="submit">Delete reply</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7">No reviews yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>
