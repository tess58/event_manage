<?php
require_once 'includes/config.php';
require_login();
if (!is_role('user')) { header('Location: login.php'); exit; }
$pageTitle = 'User Panel | Event Ethiopia';
$userId = (int) $_SESSION['user_id'];
$qrColumn = get_booking_code_column($mysqli);
$hasEventPrice = db_has_column($mysqli, 'events', 'price');
ensure_booking_qr_schema($mysqli);
ensure_event_announcement_schema($mysqli);
db_ensure_column($mysqli, 'users', 'profile_image', "VARCHAR(255) DEFAULT NULL");
db_ensure_column($mysqli, 'reviews', 'organizer_reply', "TEXT NULL");
$mysqli->query("CREATE TABLE IF NOT EXISTS favorites (id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,event_id INT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY unique_user_event (user_id,event_id),FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE)");
$mysqli->query("CREATE TABLE IF NOT EXISTS user_notification_settings (user_id INT PRIMARY KEY,booking_confirmation TINYINT(1) NOT NULL DEFAULT 1,event_reminders TINYINT(1) NOT NULL DEFAULT 1,event_updates TINYINT(1) NOT NULL DEFAULT 1,cancellation_alerts TINYINT(1) NOT NULL DEFAULT 1,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE)");
$message = ''; $error = '';
$tab = $_GET['tab'] ?? 'dashboard';
$allowedTabs = ['dashboard','events','details','bookings','favorites','reviews','profile','notifications'];
if (!in_array($tab, $allowedTabs, true)) { $tab = 'dashboard'; }
$eventId = (int) ($_GET['event_id'] ?? 0);
$search = trim($_GET['search'] ?? '');
$categoryFilter = (int) ($_GET['category'] ?? 0);
$locationFilter = trim($_GET['location'] ?? '');
$dateFilter = trim($_GET['date'] ?? '');
$priceFilter = in_array($_GET['price'] ?? '', ['free','paid'], true) ? $_GET['price'] : '';
$sortInput = $_GET['sort'] ?? 'newest';
$sort = in_array($sortInput, ['newest','popularity','price'], true) ? $sortInput : 'newest';
if (isset($_GET['download_booking']) && is_numeric($_GET['download_booking'])) {
    $downloadId = (int) $_GET['download_booking'];
    $downloadStmt = $mysqli->prepare("SELECT b.id,b.status,b.$qrColumn ticket_code,b.created_at,e.title,e.date,e.location FROM bookings b JOIN events e ON e.id=b.event_id WHERE b.id=? AND b.user_id=? LIMIT 1");
    $downloadStmt->bind_param('ii', $downloadId, $userId); $downloadStmt->execute(); $download = $downloadStmt->get_result()->fetch_assoc(); $downloadStmt->close();
    if ($download) { header('Content-Type: text/plain; charset=utf-8'); header('Content-Disposition: attachment; filename="ticket-'.$download['id'].'.txt"'); echo "Event Ethiopia Ticket\nEvent: {$download['title']}\nDate: ".date('F j, Y', strtotime($download['date']))."\nLocation: {$download['location']}\nStatus: {$download['status']}\nTicket Code: {$download['ticket_code']}\n"; exit; }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'book_ticket') {
        $targetEventId = (int) ($_POST['event_id'] ?? 0);
        $exists = $mysqli->prepare("SELECT id FROM bookings WHERE user_id=? AND event_id=? LIMIT 1");
        $exists->bind_param('ii', $userId, $targetEventId); $exists->execute(); $exists->store_result();
        if ($exists->num_rows > 0) { $error = 'You already booked this event.'; } else {
            [$ok, $bookingError] = create_booking_with_qr($mysqli, $userId, $targetEventId, 'confirmed');
            if ($ok) { $message = 'Booking confirmed and QR generated.'; create_notification($mysqli, $userId, 'Booking Confirmed', 'Your ticket booking has been confirmed.', 'success'); } else { $error = $bookingError ?: 'Unable to complete booking right now.'; }
        }
        $exists->close();
    } elseif ($action === 'cancel_booking') {
        $bookingId = (int) ($_POST['booking_id'] ?? 0);
        $stmt = $mysqli->prepare("UPDATE bookings SET status='cancelled', check_in_status='cancelled' WHERE id=? AND user_id=? AND status!='cancelled'");
        $stmt->bind_param('ii', $bookingId, $userId); $stmt->execute();
        if ($stmt->affected_rows > 0) { $message = 'Booking cancelled.'; create_notification($mysqli, $userId, 'Booking Cancelled', 'A booking has been cancelled from your account.', 'warning'); } else { $error = 'Unable to cancel this booking.'; }
        $stmt->close();
    } elseif ($action === 'toggle_favorite') {
        $targetEventId = (int) ($_POST['event_id'] ?? 0);
        $check = $mysqli->prepare("SELECT id FROM favorites WHERE user_id=? AND event_id=? LIMIT 1");
        $check->bind_param('ii', $userId, $targetEventId); $check->execute(); $check->store_result();
        if ($check->num_rows > 0) { $stmt = $mysqli->prepare("DELETE FROM favorites WHERE user_id=? AND event_id=?"); $stmt->bind_param('ii', $userId, $targetEventId); $stmt->execute(); $stmt->close(); $message = 'Removed from favorites.'; }
        else { $stmt = $mysqli->prepare("INSERT INTO favorites (user_id,event_id) VALUES (?,?)"); $stmt->bind_param('ii', $userId, $targetEventId); if ($stmt->execute()) { $message = 'Saved to favorites.'; } else { $error = 'Unable to save favorite.'; } $stmt->close(); }
        $check->close();
    } elseif ($action === 'save_review') {
        $targetEventId = (int) ($_POST['event_id'] ?? 0); $rating = (int) ($_POST['rating'] ?? 0); $comment = trim($_POST['comment'] ?? '');
        if ($rating < 1 || $rating > 5 || $comment === '') { $error = 'Rating and comment are required.'; } else {
            $check = $mysqli->prepare("SELECT id FROM reviews WHERE user_id=? AND event_id=? LIMIT 1");
            $check->bind_param('ii', $userId, $targetEventId); $check->execute(); $row = $check->get_result()->fetch_assoc(); $check->close();
            if ($row) { $id = (int) $row['id']; $stmt = $mysqli->prepare("UPDATE reviews SET rating=?, comment=? WHERE id=? AND user_id=?"); $stmt->bind_param('isii', $rating, $comment, $id, $userId); $ok = $stmt->execute(); $stmt->close(); $ok ? $message = 'Review updated.' : $error = 'Unable to update review.'; }
            else { $stmt = $mysqli->prepare("INSERT INTO reviews (user_id,event_id,rating,comment) VALUES (?,?,?,?)"); $stmt->bind_param('iiis', $userId, $targetEventId, $rating, $comment); $ok = $stmt->execute(); $stmt->close(); $ok ? $message = 'Review submitted.' : $error = 'Unable to submit review.'; }
        }
    } elseif ($action === 'delete_review') {
        $reviewId = (int) ($_POST['review_id'] ?? 0); $stmt = $mysqli->prepare("DELETE FROM reviews WHERE id=? AND user_id=?"); $stmt->bind_param('ii', $reviewId, $userId); $stmt->execute(); $stmt->affected_rows > 0 ? $message = 'Review deleted.' : $error = 'Unable to delete review.'; $stmt->close();
    } elseif ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? ''); $email = trim($_POST['email'] ?? ''); $profileImage = trim($_POST['profile_image'] ?? ''); $newPassword = trim($_POST['new_password'] ?? '');
        if ($name === '' || $email === '') { $error = 'Name and email are required.'; } else {
            $check = $mysqli->prepare("SELECT id FROM users WHERE email=? AND id!=? LIMIT 1"); $check->bind_param('si', $email, $userId); $check->execute(); $check->store_result();
            if ($check->num_rows > 0) { $error = 'Email already in use.'; } else {
                if ($newPassword !== '') { $hash = password_hash($newPassword, PASSWORD_BCRYPT); $stmt = $mysqli->prepare("UPDATE users SET name=?, email=?, profile_image=?, password=? WHERE id=?"); $stmt->bind_param('ssssi', $name, $email, $profileImage, $hash, $userId); }
                else { $stmt = $mysqli->prepare("UPDATE users SET name=?, email=?, profile_image=? WHERE id=?"); $stmt->bind_param('sssi', $name, $email, $profileImage, $userId); }
                if ($stmt->execute()) { $_SESSION['user_name'] = $name; $message = 'Profile updated successfully.'; } else { $error = 'Unable to update profile.'; }
                $stmt->close();
            }
            $check->close();
        }
    } elseif ($action === 'save_notification_settings') {
        $bookingConfirmation = isset($_POST['booking_confirmation']) ? 1 : 0; $eventReminders = isset($_POST['event_reminders']) ? 1 : 0; $eventUpdates = isset($_POST['event_updates']) ? 1 : 0; $cancellationAlerts = isset($_POST['cancellation_alerts']) ? 1 : 0;
        $stmt = $mysqli->prepare("INSERT INTO user_notification_settings (user_id,booking_confirmation,event_reminders,event_updates,cancellation_alerts) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE booking_confirmation=VALUES(booking_confirmation),event_reminders=VALUES(event_reminders),event_updates=VALUES(event_updates),cancellation_alerts=VALUES(cancellation_alerts)");
        $stmt->bind_param('iiiii', $userId, $bookingConfirmation, $eventReminders, $eventUpdates, $cancellationAlerts); $stmt->execute() ? $message = 'Notification settings updated.' : $error = 'Unable to save notification settings.'; $stmt->close();
    } elseif ($action === 'mark_notification_read') {
        $notificationId = (int) ($_POST['notification_id'] ?? 0); $stmt = $mysqli->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?"); $stmt->bind_param('ii', $notificationId, $userId); $stmt->execute(); $stmt->close();
    } elseif ($action === 'delete_notification') {
        $notificationId = (int) ($_POST['notification_id'] ?? 0);
        $stmt = $mysqli->prepare("DELETE FROM notifications WHERE id=? AND user_id=?");
        $stmt->bind_param('ii', $notificationId, $userId);
        $stmt->execute();
        $stmt->affected_rows > 0 ? $message = 'Notification removed.' : $error = 'Unable to remove notification.';
        $stmt->close();
    } elseif ($action === 'dismiss_announcement') {
        $announcementId = (int) ($_POST['announcement_id'] ?? 0);
        $chk = $mysqli->prepare(
            "SELECT a.id FROM event_announcements a
             INNER JOIN bookings b ON b.event_id = a.event_id AND b.user_id = ? AND b.status = 'confirmed'
             WHERE a.id = ? LIMIT 1"
        );
        $chk->bind_param('ii', $userId, $announcementId);
        $chk->execute();
        $allowed = $chk->get_result()->num_rows > 0;
        $chk->close();
        if (!$allowed) {
            $error = 'Unable to dismiss this update.';
        } else {
            $ins = $mysqli->prepare("INSERT IGNORE INTO event_announcement_dismissals (announcement_id, user_id) VALUES (?,?)");
            $ins->bind_param('ii', $announcementId, $userId);
            $ins->execute();
            $ins->close();
            $message = 'Update removed from your list.';
        }
    }
}
$userStmt = $mysqli->prepare("SELECT id,name,email,profile_image,created_at FROM users WHERE id=? LIMIT 1");
$userStmt->bind_param('i', $userId); $userStmt->execute(); $user = $userStmt->get_result()->fetch_assoc(); $userStmt->close();
$settingsStmt = $mysqli->prepare("SELECT booking_confirmation,event_reminders,event_updates,cancellation_alerts FROM user_notification_settings WHERE user_id=? LIMIT 1");
$settingsStmt->bind_param('i', $userId); $settingsStmt->execute(); $notificationSettings = $settingsStmt->get_result()->fetch_assoc(); $settingsStmt->close();
if (!$notificationSettings) { $notificationSettings = ['booking_confirmation' => 1,'event_reminders' => 1,'event_updates' => 1,'cancellation_alerts' => 1]; }
$categories = $mysqli->query("SELECT id,name FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$locations = $mysqli->query("SELECT DISTINCT location FROM events WHERE status='published' ORDER BY location")->fetch_all(MYSQLI_ASSOC);
$conditionParts = ["e.status='published'"];
if ($search !== '') { $safeSearch = $mysqli->real_escape_string($search); $conditionParts[] = "(e.title LIKE '%$safeSearch%' OR e.description LIKE '%$safeSearch%')"; }
if ($categoryFilter > 0) { $conditionParts[] = "e.category_id=" . (int) $categoryFilter; }
if ($locationFilter !== '') { $safeLocation = $mysqli->real_escape_string($locationFilter); $conditionParts[] = "e.location='$safeLocation'"; }
if ($dateFilter !== '') { $safeDate = $mysqli->real_escape_string($dateFilter); $conditionParts[] = "e.date='$safeDate'"; }
if ($priceFilter === 'free') { $conditionParts[] = ($hasEventPrice ? "COALESCE(e.price,0)<=0" : "1=1"); }
if ($priceFilter === 'paid') { $conditionParts[] = ($hasEventPrice ? "COALESCE(e.price,0)>0" : "1=0"); }
$whereSql = implode(' AND ', $conditionParts); $orderSql = "e.created_at DESC"; if ($sort === 'popularity') { $orderSql = "bookings_count DESC, e.date ASC"; } if ($sort === 'price') { $orderSql = ($hasEventPrice ? "e.price ASC, e.date ASC" : "e.date ASC"); }
$priceSelect = $hasEventPrice ? "COALESCE(e.price,0) AS price" : "0 AS price";
$eventsSql = "SELECT e.id,e.title,e.description,e.date,e.location,e.image_url,$priceSelect,c.name category_name,COUNT(b.id) bookings_count,MAX(CASE WHEN f.user_id IS NULL THEN 0 ELSE 1 END) is_favorite FROM events e JOIN categories c ON c.id=e.category_id LEFT JOIN bookings b ON b.event_id=e.id LEFT JOIN favorites f ON f.event_id=e.id AND f.user_id=" . (int) $userId . " WHERE $whereSql GROUP BY e.id ORDER BY $orderSql";
$events = $mysqli->query($eventsSql)->fetch_all(MYSQLI_ASSOC);
$bookingsStmt = $mysqli->prepare("SELECT b.id,b.status,b.check_in_status,b.checked_in_at,b.ticket_number,b.qr_code_data,b.$qrColumn ticket_code,b.created_at,e.id event_id,e.title,e.date,e.location,e.image_url,c.name category_name FROM bookings b JOIN events e ON e.id=b.event_id JOIN categories c ON c.id=e.category_id WHERE b.user_id=? ORDER BY b.created_at DESC");
$bookingsStmt->bind_param('i', $userId); $bookingsStmt->execute(); $bookings = $bookingsStmt->get_result()->fetch_all(MYSQLI_ASSOC); $bookingsStmt->close();
$favoritesStmt = $mysqli->prepare("SELECT f.created_at,e.id,e.title,e.date,e.location,e.image_url,c.name category_name FROM favorites f JOIN events e ON e.id=f.event_id JOIN categories c ON c.id=e.category_id WHERE f.user_id=? ORDER BY f.created_at DESC");
$favoritesStmt->bind_param('i', $userId); $favoritesStmt->execute(); $favorites = $favoritesStmt->get_result()->fetch_all(MYSQLI_ASSOC); $favoritesStmt->close();
$reviewsStmt = $mysqli->prepare("SELECT r.id,r.event_id,r.rating,r.comment,r.organizer_reply,r.created_at,e.title event_title FROM reviews r JOIN events e ON e.id=r.event_id WHERE r.user_id=? ORDER BY r.created_at DESC");
$reviewsStmt->bind_param('i', $userId); $reviewsStmt->execute(); $myReviews = $reviewsStmt->get_result()->fetch_all(MYSQLI_ASSOC); $reviewsStmt->close();
$mysqli->query(
    "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(150) NOT NULL,
        message TEXT NOT NULL,
        type VARCHAR(30) NOT NULL DEFAULT 'info',
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )"
);
$notifications = [];
$notificationsStmt = $mysqli->prepare("SELECT id,title,message,type,is_read,created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50");
if ($notificationsStmt) {
    $notificationsStmt->bind_param('i', $userId);
    $notificationsStmt->execute();
    $notifications = $notificationsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $notificationsStmt->close();
}
$announcementRows = [];
$annStmt = $mysqli->prepare(
    "SELECT a.id, a.title, a.message, a.created_at, e.title AS event_title
     FROM event_announcements a
     INNER JOIN bookings b ON b.event_id = a.event_id AND b.user_id = ? AND b.status = 'confirmed'
     LEFT JOIN event_announcement_dismissals d ON d.announcement_id = a.id AND d.user_id = ?
     WHERE d.id IS NULL
     ORDER BY a.created_at DESC LIMIT 100"
);
if ($annStmt) {
    $annStmt->bind_param('ii', $userId, $userId);
    $annStmt->execute();
    $announcementRows = $annStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $annStmt->close();
}
$notificationFeed = [];
foreach ($notifications as $notification) {
    $notificationFeed[] = [
        'kind' => 'system',
        'sort' => strtotime($notification['created_at']),
        'id' => (int) $notification['id'],
        'title' => $notification['title'],
        'body' => $notification['message'],
        'created_at' => $notification['created_at'],
        'is_read' => (int) $notification['is_read'],
    ];
}
foreach ($announcementRows as $row) {
    $notificationFeed[] = [
        'kind' => 'update',
        'sort' => strtotime($row['created_at']),
        'id' => (int) $row['id'],
        'title' => $row['title'],
        'body' => $row['message'],
        'created_at' => $row['created_at'],
        'event_title' => $row['event_title'],
        'is_read' => 0,
    ];
}
usort($notificationFeed, static function ($a, $b) {
    return $b['sort'] <=> $a['sort'];
});
$dashboardStats = ['bookings' => count($bookings),'favorites' => count($favorites),'reviews' => count($myReviews),'upcoming' => 0];
foreach ($bookings as $booking) { if ($booking['status'] !== 'cancelled' && strtotime($booking['date']) >= strtotime(date('Y-m-d'))) { $dashboardStats['upcoming']++; } }
$recommended = array_slice($events, 0, 6); $featured = array_slice($events, 0, 3);
$eventDetails = null; $eventReviews = []; $ticketCode = ''; $ticketQrData = '';
if ($eventId > 0) {
    $detailStmt = $mysqli->prepare("SELECT e.id,e.title,e.description,e.date,e.location,e.image_url,$priceSelect,c.name category_name,u.name organizer_name,COUNT(b.id) bookings_count,MAX(CASE WHEN f.user_id IS NULL THEN 0 ELSE 1 END) is_favorite FROM events e JOIN categories c ON c.id=e.category_id JOIN users u ON u.id=e.organizer_id LEFT JOIN bookings b ON b.event_id=e.id LEFT JOIN favorites f ON f.event_id=e.id AND f.user_id=? WHERE e.id=? AND e.status='published' GROUP BY e.id LIMIT 1");
    $detailStmt->bind_param('ii', $userId, $eventId); $detailStmt->execute(); $eventDetails = $detailStmt->get_result()->fetch_assoc(); $detailStmt->close();
    if ($eventDetails) {
        $reviewListStmt = $mysqli->prepare("SELECT r.id,r.rating,r.comment,r.organizer_reply,r.created_at,u.name reviewer_name FROM reviews r JOIN users u ON u.id=r.user_id WHERE r.event_id=? ORDER BY r.created_at DESC");
        $reviewListStmt->bind_param('i', $eventId); $reviewListStmt->execute(); $eventReviews = $reviewListStmt->get_result()->fetch_all(MYSQLI_ASSOC); $reviewListStmt->close();
        $ticketStmt = $mysqli->prepare("SELECT $qrColumn AS ticket_code, qr_code_data FROM bookings WHERE user_id=? AND event_id=? LIMIT 1");
        $ticketStmt->bind_param('ii', $userId, $eventId); $ticketStmt->execute(); $ticketRow = $ticketStmt->get_result()->fetch_assoc(); $ticketCode = $ticketRow['ticket_code'] ?? ''; $ticketStmt->close();
        $ticketQrData = $ticketRow['qr_code_data'] ?? '';
    }
}
$unreadCount = 0;
foreach ($notifications as $notification) {
    if ((int) $notification['is_read'] === 0) {
        $unreadCount++;
    }
}
$unreadCount += count($announcementRows);
include 'includes/header.php';
?>
<?php if ($message): ?><div class="alert alert-success"><?= sanitize($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= sanitize($error) ?></div><?php endif; ?>
<div class="dashboard-container" id="dashboard-root">
<button type="button" class="dashboard-drawer-toggle button button-alt" aria-expanded="false" aria-controls="dashboard-sidebar-nav">Menu</button>
<aside class="dashboard-sidebar" id="dashboard-sidebar-nav"><h3><?= sanitize($user['name']) ?></h3><nav class="sidebar-nav"><?php foreach ($allowedTabs as $item): ?><a href="user-dashboard.php?tab=<?= $item ?>" class="<?= $tab === $item ? 'active' : '' ?>"><?= ucfirst($item) ?><?php if ($item === 'notifications'): ?><span class="sidebar-badge" data-notification-badge data-count="<?= (int) $unreadCount ?>"><?= $unreadCount > 0 ? ' ('.$unreadCount.')' : '' ?></span><?php endif; ?></a><?php endforeach; ?><a href="logout.php">Logout</a></nav></aside>
<section class="dashboard-content">
<?php if ($tab === 'dashboard'): ?>
<section class="heading-bar"><h2>Dashboard</h2><a href="user-dashboard.php?tab=events" class="button">Browse Events</a></section>
<div class="dashboard-grid"><div class="dashboard-card"><h3>Bookings</h3><strong><?= $dashboardStats['bookings'] ?></strong></div><div class="dashboard-card"><h3>Upcoming Events</h3><strong><?= $dashboardStats['upcoming'] ?></strong></div><div class="dashboard-card"><h3>Favorites</h3><strong><?= $dashboardStats['favorites'] ?></strong></div><div class="dashboard-card"><h3>Reviews</h3><strong><?= $dashboardStats['reviews'] ?></strong></div></div>
<div class="dashboard-card"><h3>Recommended Events</h3><div class="cards-grid"><?php foreach ($recommended as $event): ?><article class="card card--clickable"><a class="card-stretch-link" href="user-dashboard.php?tab=details&event_id=<?= (int) $event['id'] ?>" aria-label="Open event: <?= sanitize($event['title']) ?>"><span class="visually-hidden">Open event details</span></a><div class="event-card-media"><img src="<?= sanitize($event['image_url']) ?>" alt="<?= sanitize($event['title']) ?>"><span class="category-badge"><?= sanitize($event['category_name']) ?></span></div><div class="card-body"><h3><?= sanitize($event['title']) ?></h3><div class="meta-row"><span>📅 <?= date('M d, Y', strtotime($event['date'])) ?></span><span>📍 <?= sanitize($event['location']) ?></span></div></div></article><?php endforeach; ?></div></div>
<div class="dashboard-grid"><div class="dashboard-card"><h3>Featured Events</h3><ul class="panel-list"><?php foreach ($featured as $event): ?><li><a href="user-dashboard.php?tab=details&event_id=<?= (int) $event['id'] ?>"><?= sanitize($event['title']) ?></a></li><?php endforeach; ?></ul></div><div class="dashboard-card"><h3>Categories</h3><ul class="panel-list"><?php foreach ($categories as $category): ?><li><a href="user-dashboard.php?tab=events&category=<?= (int) $category['id'] ?>"><?= sanitize($category['name']) ?></a></li><?php endforeach; ?></ul></div></div>
<?php elseif ($tab === 'events'): ?>
<section class="heading-bar"><h2>Events</h2></section>
<form class="filter-panel" method="get"><input type="hidden" name="tab" value="events"><input type="text" name="search" placeholder="Search by event name..." value="<?= sanitize($search) ?>"><select name="category"><option value="0">All Categories</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= $categoryFilter === (int) $category['id'] ? 'selected' : '' ?>><?= sanitize($category['name']) ?></option><?php endforeach; ?></select><select name="location"><option value="">All Locations</option><?php foreach ($locations as $location): ?><option value="<?= sanitize($location['location']) ?>" <?= $locationFilter === $location['location'] ? 'selected' : '' ?>><?= sanitize($location['location']) ?></option><?php endforeach; ?></select><input type="date" name="date" value="<?= sanitize($dateFilter) ?>"><select name="price"><option value="">All prices</option><option value="free" <?= $priceFilter === 'free' ? 'selected' : '' ?>>Free</option><option value="paid" <?= $priceFilter === 'paid' ? 'selected' : '' ?>>Paid</option></select><select name="sort"><option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option><option value="popularity" <?= $sort === 'popularity' ? 'selected' : '' ?>>Popularity</option><option value="price" <?= $sort === 'price' ? 'selected' : '' ?>>Price</option></select><button type="submit" class="button">Apply</button></form>
<div class="cards-grid"><?php foreach ($events as $event): ?><article class="card card--clickable"><a class="card-stretch-link" href="user-dashboard.php?tab=details&event_id=<?= (int) $event['id'] ?>" aria-label="Open event: <?= sanitize($event['title']) ?>"><span class="visually-hidden">Open event details</span></a><div class="event-card-media"><img src="<?= sanitize($event['image_url']) ?>" alt="<?= sanitize($event['title']) ?>"><span class="category-badge"><?= sanitize($event['category_name']) ?></span></div><div class="card-body"><h3><?= sanitize($event['title']) ?></h3><div class="meta-row"><span>📅 <?= date('M d, Y', strtotime($event['date'])) ?></span><span>📍 <?= sanitize($event['location']) ?></span></div><div class="meta-row"><span>🔥 <?= (int) $event['bookings_count'] ?> bookings</span><?php if ($hasEventPrice): ?><span><?= (float) $event['price'] > 0 ? 'ETB '.number_format((float) $event['price']) : 'Free' ?></span><?php endif; ?></div><div class="card-interactive"><form method="post"><input type="hidden" name="action" value="toggle_favorite"><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>"><button type="submit" class="button button-alt"><?= (int) $event['is_favorite'] === 1 ? 'Unsave' : 'Save' ?></button></form></div></div></article><?php endforeach; ?></div>
<?php elseif ($tab === 'details' && $eventDetails): ?>
<section class="heading-bar"><h2><?= sanitize($eventDetails['title']) ?></h2><a href="user-dashboard.php?tab=events" class="button button-alt">Back to Events</a></section>
<div class="ticket-card"><div class="dashboard-card"><img src="<?= sanitize($eventDetails['image_url']) ?>" alt="<?= sanitize($eventDetails['title']) ?>" style="width:100%;border-radius:12px;margin-bottom:14px;"><p><?= nl2br(sanitize($eventDetails['description'])) ?></p><div class="meta-row"><span>📅 <?= date('F j, Y', strtotime($eventDetails['date'])) ?></span><span>📍 <?= sanitize($eventDetails['location']) ?></span></div><div class="meta-row"><span>Organizer: <?= sanitize($eventDetails['organizer_name']) ?></span><span>Category: <?= sanitize($eventDetails['category_name']) ?></span></div><div class="dashboard-card" style="margin-top:12px;"><h3>Reviews</h3><?php foreach (array_slice($eventReviews, 0, 5) as $review): ?><div class="review-thread-block"><p style="margin:0 0 8px;"><strong><?= sanitize($review['reviewer_name']) ?></strong> <span style="color:#fbbf24;">(<?= (int) $review['rating'] ?>/5)</span></p><p style="margin:0;color:var(--text-secondary);"><?= nl2br(sanitize($review['comment'])) ?></p><?php if (!empty($review['organizer_reply'])): ?><div class="review-reply-bubble"><strong>Organizer reply</strong><p style="margin:8px 0 0;"><?= nl2br(sanitize($review['organizer_reply'])) ?></p></div><?php endif; ?></div><?php endforeach; ?></div></div><div class="dashboard-card"><h3>Ticket</h3><p><?= $hasEventPrice && (float) $eventDetails['price'] > 0 ? 'ETB '.number_format((float) $eventDetails['price']) : 'Free' ?></p><p>Bookings: <?= (int) $eventDetails['bookings_count'] ?></p><?php if ($ticketCode !== ''): ?><div><p><strong>Booking confirmed</strong></p><img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=<?= urlencode($ticketQrData ?: $ticketCode) ?>" alt="QR Ticket"><p>Ticket: <?= sanitize($ticketCode) ?></p><a href="user-dashboard.php?tab=bookings" class="button button-alt" style="display:inline-flex;margin-top:8px;">My Bookings</a></div><?php else: ?><form method="post"><input type="hidden" name="action" value="book_ticket"><input type="hidden" name="event_id" value="<?= (int) $eventDetails['id'] ?>"><button class="button" type="submit">Book Ticket</button></form><?php endif; ?><form method="post" style="margin-top:8px;"><input type="hidden" name="action" value="toggle_favorite"><input type="hidden" name="event_id" value="<?= (int) $eventDetails['id'] ?>"><button type="submit" class="button button-alt"><?= (int) $eventDetails['is_favorite'] === 1 ? 'Remove Favorite' : 'Save to Favorites' ?></button></form><button class="button button-alt" type="button" onclick="navigator.clipboard.writeText(window.location.href)">Share Event</button></div></div>
<div class="dashboard-card review-box"><h3>Write / Edit Your Review</h3><form method="post" class="form-grid"><input type="hidden" name="action" value="save_review"><input type="hidden" name="event_id" value="<?= (int) $eventDetails['id'] ?>"><div><label>Rating</label><select name="rating" required><option value="">Select</option><?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= $i ?> star<?= $i > 1 ? 's' : '' ?></option><?php endfor; ?></select></div><div><label>Comment</label><textarea name="comment" class="form-textarea" rows="4" required></textarea></div><button class="button" type="submit">Save Review</button></form></div>
<?php elseif ($tab === 'bookings'): ?>
<section class="heading-bar"><h2>My Bookings</h2></section>
<div class="dashboard-card table-card"><table class="list-table"><thead><tr><th>Event</th><th>Date</th><th>Booking</th><th>Check-In</th><th>QR</th><th>Actions</th></tr></thead><tbody><?php foreach ($bookings as $booking): ?><tr><td><?= sanitize($booking['title']) ?><br><small><?= sanitize($booking['location']) ?></small></td><td><?= date('M d, Y', strtotime($booking['date'])) ?></td><td><span class="badge status-<?= sanitize($booking['status']) ?>"><?= sanitize(ucfirst($booking['status'])) ?></span></td><td><?= sanitize((string) ($booking['check_in_status'] === 'checked_in' ? 'Checked-in' : ($booking['check_in_status'] === 'cancelled' ? 'Cancelled' : 'Not checked-in'))) ?></td><td><img src="https://api.qrserver.com/v1/create-qr-code/?size=90x90&data=<?= urlencode($booking['qr_code_data'] ?: $booking['ticket_code']) ?>" alt="QR"></td><td><a href="user-dashboard.php?download_booking=<?= (int) $booking['id'] ?>" class="button button-alt">Download Ticket</a><a href="https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=<?= urlencode($booking['qr_code_data'] ?: $booking['ticket_code']) ?>" class="button button-alt" target="_blank" rel="noopener">Download QR Image</a><?php if ($booking['status'] !== 'cancelled'): ?><form method="post" style="display:inline;"><input type="hidden" name="action" value="cancel_booking"><input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>"><button class="button button-alt" type="submit">Cancel</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php elseif ($tab === 'favorites'): ?>
<section class="heading-bar"><h2>Favorites</h2></section>
<div class="cards-grid"><?php foreach ($favorites as $favorite): ?><article class="card card--clickable"><a class="card-stretch-link" href="user-dashboard.php?tab=details&event_id=<?= (int) $favorite['id'] ?>" aria-label="Open event: <?= sanitize($favorite['title']) ?>"><span class="visually-hidden">Open event details</span></a><div class="event-card-media"><img src="<?= sanitize($favorite['image_url']) ?>" alt="<?= sanitize($favorite['title']) ?>"><span class="category-badge"><?= sanitize($favorite['category_name']) ?></span></div><div class="card-body"><h3><?= sanitize($favorite['title']) ?></h3><div class="meta-row"><span>📅 <?= date('M d, Y', strtotime($favorite['date'])) ?></span><span>📍 <?= sanitize($favorite['location']) ?></span></div><div class="card-interactive"><form method="post"><input type="hidden" name="action" value="toggle_favorite"><input type="hidden" name="event_id" value="<?= (int) $favorite['id'] ?>"><button class="button button-alt" type="submit">Remove</button></form></div></div></article><?php endforeach; ?></div>
<?php elseif ($tab === 'reviews'): ?>
<section class="heading-bar"><h2>Reviews & Ratings</h2></section>
<div class="dashboard-card table-card"><div class="table-scroll"><table class="list-table"><thead><tr><th>Event</th><th>Rating</th><th>Your review &amp; replies</th><th>Date</th><th class="table-actions-col">Actions</th></tr></thead><tbody><?php foreach ($myReviews as $review): ?><tr><td><?= sanitize($review['event_title']) ?></td><td><?= (int) $review['rating'] ?>/5</td><td><div class="review-thread-block"><p style="margin:0 0 6px;"><?= nl2br(sanitize($review['comment'])) ?></p><?php if (!empty($review['organizer_reply'])): ?><div class="review-reply-bubble"><strong>Organizer</strong><p style="margin:6px 0 0;"><?= nl2br(sanitize($review['organizer_reply'])) ?></p></div><?php endif; ?></div></td><td><?= date('M d, Y', strtotime($review['created_at'])) ?></td><td class="table-actions-col"><div class="table-actions"><a class="button button-alt" href="user-dashboard.php?tab=details&event_id=<?= (int) $review['event_id'] ?>">Edit</a><form method="post" class="inline-form" onsubmit="return confirm('Delete your review?');"><input type="hidden" name="action" value="delete_review"><input type="hidden" name="review_id" value="<?= (int) $review['id'] ?>"><button class="button button-alt" type="submit">Delete</button></form></div></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php elseif ($tab === 'profile'): ?>
<section class="heading-bar"><h2>Profile</h2></section>
<div class="dashboard-grid"><div class="dashboard-card"><h3>Activity</h3><ul class="panel-list"><li>Bookings: <?= $dashboardStats['bookings'] ?></li><li>Reviews: <?= $dashboardStats['reviews'] ?></li><li>Favorites: <?= $dashboardStats['favorites'] ?></li></ul></div><div class="dashboard-card"><h3>Member Since</h3><strong><?= date('M Y', strtotime($user['created_at'])) ?></strong></div></div>
<div class="dashboard-card"><h3>Account Settings</h3><form method="post" class="form-grid"><input type="hidden" name="action" value="update_profile"><div><label>Name</label><input class="form-input" type="text" name="name" value="<?= sanitize($user['name']) ?>" required></div><div><label>Email</label><input class="form-input" type="email" name="email" value="<?= sanitize($user['email']) ?>" required></div><div><label>Profile Picture URL</label><input class="form-input" type="text" name="profile_image" value="<?= sanitize($user['profile_image'] ?? '') ?>"></div><div><label>New Password (optional)</label><input class="form-input" type="password" name="new_password" minlength="6"></div><button class="button" type="submit">Update Profile</button></form></div>
<?php elseif ($tab === 'notifications'): ?>
<section class="heading-bar"><h2>Notifications</h2></section>
<div class="dashboard-card"><h3>Notification Settings</h3><form method="post" class="form-grid"><input type="hidden" name="action" value="save_notification_settings"><label><input type="checkbox" name="booking_confirmation" <?= (int) $notificationSettings['booking_confirmation'] === 1 ? 'checked' : '' ?>> Booking confirmation</label><label><input type="checkbox" name="event_reminders" <?= (int) $notificationSettings['event_reminders'] === 1 ? 'checked' : '' ?>> Event reminders</label><label><input type="checkbox" name="event_updates" <?= (int) $notificationSettings['event_updates'] === 1 ? 'checked' : '' ?>> Event updates</label><label><input type="checkbox" name="cancellation_alerts" <?= (int) $notificationSettings['cancellation_alerts'] === 1 ? 'checked' : '' ?>> Cancellation alerts</label><button type="submit" class="button">Save Settings</button></form></div>
<div class="dashboard-card table-card"><h3>Inbox</h3><p style="margin-top:0;color:var(--text-secondary);font-size:0.92rem;">Account alerts and organizer updates for events you are booked for.</p><div class="table-scroll"><table class="list-table"><thead><tr><th>Type</th><th>Title</th><th>Message</th><th>Date</th><th class="table-actions-col">Actions</th></tr></thead><tbody><?php foreach ($notificationFeed as $item): ?><tr><td><span class="badge"><?= $item['kind'] === 'update' ? 'Event update' : 'Account' ?></span></td><td><?= sanitize($item['title']) ?><?= $item['kind'] === 'system' && (int) $item['is_read'] === 0 ? ' •' : '' ?></td><td><?php if ($item['kind'] === 'update' && !empty($item['event_title'])): ?><p class="notif-event-tag" style="margin:0 0 6px;font-size:0.85rem;color:var(--muted);"><?= sanitize($item['event_title']) ?></p><?php endif; ?><?= nl2br(sanitize($item['body'])) ?></td><td><?= date('M d, Y H:i', strtotime($item['created_at'])) ?></td><td class="table-actions-col"><div class="table-actions"><?php if ($item['kind'] === 'system'): ?><?php if ((int) $item['is_read'] === 0): ?><form method="post" class="inline-form"><input type="hidden" name="action" value="mark_notification_read"><input type="hidden" name="notification_id" value="<?= (int) $item['id'] ?>"><button class="button button-alt" type="submit">Mark read</button></form><?php endif; ?><form method="post" class="inline-form" onsubmit="return confirm('Remove this notification?');"><input type="hidden" name="action" value="delete_notification"><input type="hidden" name="notification_id" value="<?= (int) $item['id'] ?>"><button class="button button-alt" type="submit">Delete</button></form><?php else: ?><form method="post" class="inline-form" onsubmit="return confirm('Remove this update from your list?');"><input type="hidden" name="action" value="dismiss_announcement"><input type="hidden" name="announcement_id" value="<?= (int) $item['id'] ?>"><button class="button button-alt" type="submit">Dismiss</button></form><?php endif; ?></div></td></tr><?php endforeach; ?></tbody></table></div><?php if (empty($notificationFeed)): ?><p style="color:var(--text-secondary);">No notifications yet.</p><?php endif; ?></div>
<?php else: ?><div class="dashboard-card"><p>Select an event from the Events tab.</p></div><?php endif; ?>
</section></div>
<style>.panel-list{list-style:none;padding:0;margin:0;display:grid;gap:10px}.panel-list li a,.panel-list li{color:var(--text-secondary)}.table-card{overflow:auto}</style>
<?php include 'includes/footer.php'; ?>
