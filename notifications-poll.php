<?php
require_once 'includes/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!is_logged_in() || !is_role('user')) {
    echo json_encode(['ok' => false, 'count' => 0]);
    exit;
}
$userId = (int) $_SESSION['user_id'];
ensure_event_announcement_schema($mysqli);
$unread = 0;
$ns = $mysqli->prepare("SELECT COUNT(*) AS c FROM notifications WHERE user_id=? AND is_read=0");
if ($ns) {
    $ns->bind_param('i', $userId);
    $ns->execute();
    $unread += (int) ($ns->get_result()->fetch_assoc()['c'] ?? 0);
    $ns->close();
}
$as = $mysqli->prepare(
    "SELECT COUNT(*) AS c FROM event_announcements a
     INNER JOIN bookings b ON b.event_id = a.event_id AND b.user_id = ? AND b.status = 'confirmed'
     LEFT JOIN event_announcement_dismissals d ON d.announcement_id = a.id AND d.user_id = ?
     WHERE d.id IS NULL"
);
if ($as) {
    $as->bind_param('ii', $userId, $userId);
    $as->execute();
    $unread += (int) ($as->get_result()->fetch_assoc()['c'] ?? 0);
    $as->close();
}
echo json_encode(['ok' => true, 'count' => $unread]);
