<?php
require_once 'includes/config.php';
require_login();
$pageTitle = 'Dashboard | Event Ethiopia';
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];
$message = '';
$error = '';
$counts = [
    'total_users' => 0,
    'total_organizers' => 0,
    'total_events' => 0,
    'total_bookings' => 0,
    'total_confirmed' => 0,
];
$pendingOrganizers = [];
$allEvents = [];
$recentBookings = [];
$categories = [];
$ownEvents = [];
$eventBookings = [];
$myBookings = [];
$myReviews = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($userRole === 'admin') {
        if (!empty($_POST['approve_organizer'])) {
            $approveId = intval($_POST['approve_organizer']);
            $approveStmt = $mysqli->prepare("UPDATE users SET status = 'approved' WHERE id = ? AND role = 'organizer'");
            $approveStmt->bind_param('i', $approveId);
            $approveStmt->execute();
            $message = 'Organizer account approved.';
            $approveStmt->close();
        }
        if (!empty($_POST['reject_organizer'])) {
            $rejectId = intval($_POST['reject_organizer']);
            $rejectStmt = $mysqli->prepare("UPDATE users SET status = 'rejected' WHERE id = ? AND role = 'organizer'");
            $rejectStmt->bind_param('i', $rejectId);
            $rejectStmt->execute();
            $message = 'Organizer account rejected.';
            $rejectStmt->close();
        }
    }
    if ($userRole === 'organizer') {
        if (!empty($_POST['create_event'])) {
            $title = sanitize($_POST['title']);
            $description = sanitize($_POST['description']);
            $date = sanitize($_POST['date']);
            $location = sanitize($_POST['location']);
            $categoryId = intval($_POST['category_id']);
            $imageUrl = filter_var($_POST['image_url'], FILTER_SANITIZE_URL);
            $status = 'published';
            if (!$title || !$description || !$date || !$location) {
                $error = 'All event fields are required.';
            } else {
                $insertEvent = $mysqli->prepare("INSERT INTO events (title, description, date, location, organizer_id, category_id, image_url, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $insertEvent->bind_param('sssisiss', $title, $description, $date, $location, $userId, $categoryId, $imageUrl, $status);
                if ($insertEvent->execute()) {
                    $message = 'Event created successfully.';
                } else {
                    $error = 'Unable to create event.';
                }
                $insertEvent->close();
            }
        }
        if (!empty($_POST['delete_event'])) {
            $removeId = intval($_POST['delete_event']);
            $deleteStmt = $mysqli->prepare("DELETE FROM events WHERE id = ? AND organizer_id = ?");
            $deleteStmt->bind_param('ii', $removeId, $userId);
            if ($deleteStmt->execute()) {
                $message = 'Event deleted successfully.';
            } else {
                $error = 'Unable to delete event.';
            }
            $deleteStmt->close();
        }
        if (!empty($_POST['update_event'])) {
            $eventId = intval($_POST['event_id']);
            $title = sanitize($_POST['title']);
            $description = sanitize($_POST['description']);
            $date = sanitize($_POST['date']);
            $location = sanitize($_POST['location']);
            $categoryId = intval($_POST['category_id']);
            $imageUrl = filter_var($_POST['image_url'], FILTER_SANITIZE_URL);
            if (!$title || !$description || !$date || !$location) {
                $error = 'All event fields are required.';
            } else {
                $updateStmt = $mysqli->prepare("UPDATE events SET title = ?, description = ?, date = ?, location = ?, category_id = ?, image_url = ? WHERE id = ? AND organizer_id = ?");
                $updateStmt->bind_param('sssisisi', $title, $description, $date, $location, $categoryId, $imageUrl, $eventId, $userId);
                if ($updateStmt->execute()) {
                    $message = 'Event updated successfully.';
                } else {
                    $error = 'Unable to update event.';
                }
                $updateStmt->close();
            }
        }
    }
}

if ($userRole === 'admin') {
    $countsStmt = $mysqli->prepare(
        "SELECT
            (SELECT COUNT(*) FROM users WHERE role = 'user') AS total_users,
            (SELECT COUNT(*) FROM users WHERE role = 'organizer') AS total_organizers,
            (SELECT COUNT(*) FROM events) AS total_events,
            (SELECT COUNT(*) FROM bookings) AS total_bookings,
            (SELECT IFNULL(SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END),0) FROM bookings) AS total_confirmed"
    );
    $countsStmt->execute();
    $counts = $countsStmt->get_result()->fetch_assoc();
    $countsStmt->close();

    $pendingStmt = $mysqli->prepare("SELECT id, name, email FROM users WHERE role = 'organizer' AND status = 'pending' ORDER BY id DESC");
    $pendingStmt->execute();
    $pendingOrganizers = $pendingStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $pendingStmt->close();

    $eventsStmt = $mysqli->prepare(
        "SELECT e.id, e.title, e.date, e.location, u.name AS organizer_name, c.name AS category_name
         FROM events e
         JOIN users u ON u.id = e.organizer_id
         JOIN categories c ON c.id = e.category_id
         ORDER BY e.date DESC"
    );
    $eventsStmt->execute();
    $allEvents = $eventsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $eventsStmt->close();

    $bookingsStmt = $mysqli->prepare(
        "SELECT b.id, u.name AS attendee, e.title AS event_title, b.status, b.qr_code
         FROM bookings b
         JOIN users u ON u.id = b.user_id
         JOIN events e ON e.id = b.event_id
         ORDER BY b.id DESC LIMIT 10"
    );
    $bookingsStmt->execute();
    $recentBookings = $bookingsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $bookingsStmt->close();
}

if ($userRole === 'organizer') {
    $catStmt = $mysqli->prepare("SELECT id, name FROM categories ORDER BY name");
    $catStmt->execute();
    $categories = $catStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $catStmt->close();

    $ownStmt = $mysqli->prepare(
        "SELECT e.id, e.title, e.date, e.location, c.name AS category_name, e.status
         FROM events e
         JOIN categories c ON c.id = e.category_id
         WHERE e.organizer_id = ?
         ORDER BY e.date DESC"
    );
    $ownStmt->bind_param('i', $userId);
    $ownStmt->execute();
    $ownEvents = $ownStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $ownStmt->close();

    $bookingStmt = $mysqli->prepare(
        "SELECT b.id, b.status, b.qr_code, u.name AS attendee, e.title AS event_title
         FROM bookings b
         JOIN users u ON u.id = b.user_id
         JOIN events e ON e.id = b.event_id
         WHERE e.organizer_id = ?
         ORDER BY b.id DESC"
    );
    $bookingStmt->bind_param('i', $userId);
    $bookingStmt->execute();
    $eventBookings = $bookingStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $bookingStmt->close();
}

if ($userRole === 'user') {
    $bookingsStmt = $mysqli->prepare(
        "SELECT b.id, b.status, b.qr_code, e.title AS event_title, e.date, e.location, c.name AS category_name
         FROM bookings b
         JOIN events e ON e.id = b.event_id
         JOIN categories c ON c.id = e.category_id
         WHERE b.user_id = ?
         ORDER BY b.id DESC"
    );
    $bookingsStmt->bind_param('i', $userId);
    $bookingsStmt->execute();
    $myBookings = $bookingsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $bookingsStmt->close();

    $reviewStmt = $mysqli->prepare(
        "SELECT r.rating, r.comment, e.title AS event_title
         FROM reviews r
         JOIN events e ON e.id = r.event_id
         WHERE r.user_id = ?
         ORDER BY r.id DESC"
    );
    $reviewStmt->bind_param('i', $userId);
    $reviewStmt->execute();
    $myReviews = $reviewStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $reviewStmt->close();
}
?>
<?php include 'includes/header.php'; ?>
<section class="heading-bar">
    <div>
        <h2>Welcome back, <?= sanitize($_SESSION['user_name']) ?></h2>
        <p>Manage your profile and explore your dashboard tools.</p>
    </div>
</section>
<?php if ($message): ?>
    <div class="alert alert-success"><?= sanitize($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?= sanitize($error) ?></div>
<?php endif; ?>
<?php if ($userRole === 'admin'): ?>
    <div class="dashboard-grid">
        <div class="dashboard-card">
            <h3>Total Attendees</h3>
            <strong><?= intval($counts['total_users']) ?></strong>
        </div>
        <div class="dashboard-card">
            <h3>Total Organizers</h3>
            <strong><?= intval($counts['total_organizers']) ?></strong>
        </div>
        <div class="dashboard-card">
            <h3>Total Events</h3>
            <strong><?= intval($counts['total_events']) ?></strong>
        </div>
        <div class="dashboard-card">
            <h3>Total Bookings</h3>
            <strong><?= intval($counts['total_bookings']) ?></strong>
        </div>
        <div class="dashboard-card">
            <h3>Confirmed Bookings</h3>
            <strong><?= intval($counts['total_confirmed']) ?></strong>
        </div>
    </div>
    <section class="form-card">
        <h3>Pending Organizer Approvals</h3>
        <?php if (empty($pendingOrganizers)): ?>
            <p>No pending organizer accounts.</p>
        <?php else: ?>
            <table class="list-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingOrganizers as $organizer): ?>
                        <tr>
                            <td><?= sanitize($organizer['name']) ?></td>
                            <td><?= sanitize($organizer['email']) ?></td>
                            <td>
                                <form method="post" style="display:inline-block; margin-right:8px;">
                                    <button name="approve_organizer" value="<?= $organizer['id'] ?>" class="button button-alt">Approve</button>
                                </form>
                                <form method="post" style="display:inline-block;">
                                    <button name="reject_organizer" value="<?= $organizer['id'] ?>" class="button">Reject</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
    <section class="form-card" style="margin-top:24px;">
        <h3>Recent Bookings</h3>
        <table class="list-table">
            <thead>
                <tr>
                    <th>Attendee</th>
                    <th>Event</th>
                    <th>Status</th>
                    <th>QR Code</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentBookings as $booking): ?>
                    <tr>
                        <td><?= sanitize($booking['attendee']) ?></td>
                        <td><?= sanitize($booking['event_title']) ?></td>
                        <td><?= sanitize($booking['status']) ?></td>
                        <td><?= sanitize($booking['qr_code']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php elseif ($userRole === 'organizer'): ?>
    <div class="dashboard-grid">
        <div class="dashboard-card">
            <h3>My Events</h3>
            <strong><?= count($ownEvents) ?></strong>
        </div>
        <div class="dashboard-card">
            <h3>Event Bookings</h3>
            <strong><?= count($eventBookings) ?></strong>
        </div>
    </div>
    <section class="form-card">
        <h3>Create New Event</h3>
        <form id="eventForm" method="post" class="form-grid">
            <input type="hidden" name="create_event" value="1">
            <div>
                <label for="title">Event title</label>
                <input type="text" id="title" name="title" required>
            </div>
            <div>
                <label for="date">Date</label>
                <input type="date" id="date" name="date" required>
            </div>
            <div>
                <label for="location">Location</label>
                <input type="text" id="location" name="location" required>
            </div>
            <div>
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id">
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>"><?= sanitize($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="grid-column:1 / -1;">
                <label for="image_url">Image URL</label>
                <input type="url" id="image_url" name="image_url" placeholder="https://...">
            </div>
            <div style="grid-column:1 / -1;">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-textarea" rows="5" required></textarea>
            </div>
            <button class="button" type="submit">Create Event</button>
        </form>
    </section>
    <section class="form-card" style="margin-top:24px;">
        <h3>Manage Your Events</h3>
        <table class="list-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Date</th>
                    <th>Location</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ownEvents as $event): ?>
                    <tr>
                        <td><a href="event-details.php?id=<?= (int) $event['id'] ?>"><?= sanitize($event['title']) ?></a></td>
                        <td><?= date('M d, Y', strtotime($event['date'])) ?></td>
                        <td><?= sanitize($event['location']) ?></td>
                        <td><?= sanitize($event['category_name']) ?></td>
                        <td><span class="badge"><?= sanitize($event['status']) ?></span></td>
                        <td>
                            <form method="post" style="display:inline-block; margin-right:8px;">
                                <button class="button button-alt" name="delete_event" value="<?= $event['id'] ?>">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <section class="form-card" style="margin-top:24px;">
        <h3>Recent Bookings for Your Events</h3>
        <table class="list-table">
            <thead>
                <tr>
                    <th>Attendee</th>
                    <th>Event</th>
                    <th>Status</th>
                    <th>QR</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($eventBookings as $booking): ?>
                    <tr>
                        <td><?= sanitize($booking['attendee']) ?></td>
                        <td><?= sanitize($booking['event_title']) ?></td>
                        <td><?= sanitize($booking['status']) ?></td>
                        <td><?= sanitize($booking['qr_code']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php elseif ($userRole === 'user'): ?>
    <div class="dashboard-grid">
        <div class="dashboard-card">
            <h3>My Bookings</h3>
            <strong><?= count($myBookings) ?></strong>
        </div>
        <div class="dashboard-card">
            <h3>My Reviews</h3>
            <strong><?= count($myReviews) ?></strong>
        </div>
    </div>
    <section class="form-card">
        <h3>Booking History</h3>
        <?php if (empty($myBookings)): ?>
            <p>You have not booked any events yet. Browse events to find your next ticket.</p>
        <?php else: ?>
            <table class="list-table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Date</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>QR</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($myBookings as $booking): ?>
                        <tr>
                            <td><?= sanitize($booking['event_title']) ?></td>
                            <td><?= date('M d, Y', strtotime($booking['date'])) ?></td>
                            <td><?= sanitize($booking['location']) ?></td>
                            <td><?= sanitize($booking['status']) ?></td>
                            <td><?= sanitize($booking['qr_code']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
    <section class="form-card" style="margin-top:24px;">
        <h3>My Reviews</h3>
        <?php if (empty($myReviews)): ?>
            <p>You have not left any reviews yet.</p>
        <?php else: ?>
            <table class="list-table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Rating</th>
                        <th>Comment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($myReviews as $review): ?>
                        <tr>
                            <td><?= sanitize($review['event_title']) ?></td>
                            <td><?= intval($review['rating']) ?>/5</td>
                            <td><?= sanitize($review['comment']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>