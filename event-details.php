<?php
require_once 'includes/config.php';
$pageTitle = 'Event Details | Event Ethiopia';
$error = '';
$message = '';
$eventId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$qrColumn = get_booking_code_column($mysqli);
ensure_booking_qr_schema($mysqli);

$eventStmt = $mysqli->prepare(
    "SELECT e.*, c.name AS category_name, u.name AS organizer_name
     FROM events e
     JOIN categories c ON c.id = e.category_id
     JOIN users u ON u.id = e.organizer_id
     WHERE e.id = ? AND e.status = 'published' LIMIT 1"
);
$eventStmt->bind_param('i', $eventId);
$eventStmt->execute();
$event = $eventStmt->get_result()->fetch_assoc();
$eventStmt->close();
if (!$event) {
    header('Location: events.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    if (isset($_POST['book_ticket'])) {
        $userId = $_SESSION['user_id'];
        $existingStmt = $mysqli->prepare("SELECT id FROM bookings WHERE user_id = ? AND event_id = ? LIMIT 1");
        $existingStmt->bind_param('ii', $userId, $eventId);
        $existingStmt->execute();
        $existingStmt->store_result();
        if ($existingStmt->num_rows > 0) {
            $error = 'You have already booked this event.';
        } else {
            [$ok, $bookingError, $bookingData] = create_booking_with_qr($mysqli, $userId, $eventId, 'confirmed');
            if ($ok) {
                $message = 'Booking confirmed! Your QR ticket is ready.';
                create_notification($mysqli, $userId, 'Booking Confirmed', 'Your QR ticket is generated and ready to use.', 'success');
            } else {
                $error = $bookingError ?: 'Unable to complete booking. Please try again.';
            }
        }
        $existingStmt->close();
    }
    if (isset($_POST['submit_review'])) {
        $rating = intval($_POST['rating'] ?? 0);
        $comment = sanitize($_POST['comment'] ?? '');
        if ($rating < 1 || $rating > 5 || !$comment) {
            $error = 'Please add a rating and comment.';
        } else {
            $reviewStmt = $mysqli->prepare("INSERT INTO reviews (user_id, event_id, rating, comment) VALUES (?, ?, ?, ?)");
            $reviewStmt->bind_param('iiis', $_SESSION['user_id'], $eventId, $rating, $comment);
            if ($reviewStmt->execute()) {
                $message = 'Thank you for your review.';
            } else {
                $error = 'Unable to save your review.';
            }
            $reviewStmt->close();
        }
    }
}

db_ensure_column($mysqli, 'reviews', 'organizer_reply', "TEXT NULL");
$reviewListStmt = $mysqli->prepare(
    "SELECT r.rating, r.comment, r.organizer_reply, u.name AS reviewer_name, r.created_at
     FROM reviews r
     JOIN users u ON u.id = r.user_id
     WHERE r.event_id = ?
     ORDER BY r.created_at DESC"
);
$reviewListStmt->bind_param('i', $eventId);
$reviewListStmt->execute();
$reviews = $reviewListStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$reviewListStmt->close();

$averageStmt = $mysqli->prepare("SELECT ROUND(AVG(rating),1) AS avg_rating, COUNT(*) AS total_reviews FROM reviews WHERE event_id = ?");
$averageStmt->bind_param('i', $eventId);
$averageStmt->execute();
$avgResult = $averageStmt->get_result()->fetch_assoc();
$averageStmt->close();

$hasBooking = false;
if (isset($_SESSION['user_id'])) {
    $checkBookingStmt = $mysqli->prepare("SELECT id FROM bookings WHERE user_id = ? AND event_id = ? LIMIT 1");
    $checkBookingStmt->bind_param('ii', $_SESSION['user_id'], $eventId);
    $checkBookingStmt->execute();
    $checkBookingStmt->store_result();
    $hasBooking = $checkBookingStmt->num_rows > 0;
    $checkBookingStmt->close();
}

$ticketCode = null;
$ticketQrData = null;
if (isset($_SESSION['user_id']) && $hasBooking) {
    $ticketStmt = $mysqli->prepare("SELECT $qrColumn AS code, qr_code_data FROM bookings WHERE user_id = ? AND event_id = ? LIMIT 1");
    $ticketStmt->bind_param('ii', $_SESSION['user_id'], $eventId);
    $ticketStmt->execute();
    $ticket = $ticketStmt->get_result()->fetch_assoc();
    $ticketCode = $ticket['code'] ?? null;
    $ticketQrData = $ticket['qr_code_data'] ?? null;
    $ticketStmt->close();
}
?>
<?php include 'includes/header.php'; ?>

<?php if ($message): ?>
    <div class="alert alert-success"><?= sanitize($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?= sanitize($error) ?></div>
<?php endif; ?>

<div class="event-details-hero">
    <img src="<?= sanitize($event['image_url']) ?>" alt="<?= sanitize($event['title']) ?>" class="event-hero-img">
    <div class="event-hero-overlay">
        <span class="category-badge"><?= sanitize($event['category_name']) ?></span>
    </div>
</div>

<div class="event-details-layout">
    <div>
        <h1 style="margin: 0 0 12px; font-size: 2rem;"><?= sanitize($event['title']) ?></h1>
        <div class="meta-row" style="margin-bottom: 24px;">
            <span>📅 <?= date('F j, Y', strtotime($event['date'])) ?></span>
            <span>📍 <?= sanitize($event['location']) ?></span>
        </div>
        
        <div class="event-section">
            <h3>About This Event</h3>
            <p><?= nl2br(sanitize($event['description'])) ?></p>
        </div>

        <div class="event-section">
            <h3>Event Details</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="detail-item">
                    <strong>Organizer</strong>
                    <p><?= sanitize($event['organizer_name']) ?></p>
                </div>
                <div class="detail-item">
                    <strong>Category</strong>
                    <p><?= sanitize($event['category_name']) ?></p>
                </div>
            </div>
        </div>

        <div class="event-section">
            <h3>Reviews & Ratings</h3>
            <div style="margin-bottom: 24px;">
                <div style="font-size: 1.4rem; font-weight: 700; margin-bottom: 8px;">
                    ⭐ <?= $avgResult['avg_rating'] ? sanitize($avgResult['avg_rating']) : 'No ratings yet' ?>
                </div>
                <p style="color: var(--text-secondary); margin: 0;">Based on <?= intval($avgResult['total_reviews']) ?> review(s)</p>
            </div>

            <?php if (isset($_SESSION['user_id'])): ?>
                <form method="post" class="form-grid" style="background: var(--primary-soft); padding: 20px; border-radius: 12px; margin-bottom: 24px;">
                    <input type="hidden" name="submit_review" value="1">
                    <div>
                        <label for="rating">Your Rating</label>
                        <select id="rating" name="rating" required>
                            <option value="">Select rating</option>
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <option value="<?= $i ?>"><?= $i ?> star<?= $i > 1 ? 's' : '' ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div style="grid-column:1 / -1;">
                        <label for="comment">Your Review</label>
                        <textarea id="comment" name="comment" class="form-textarea" rows="4" placeholder="Share your experience..." required></textarea>
                    </div>
                    <button class="button" type="submit">Submit Review</button>
                </form>
            <?php endif; ?>

            <div class="reviews-list">
                <?php if (empty($reviews)): ?>
                    <p style="color: var(--text-secondary);">No reviews yet. Be the first to review this event!</p>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="review-item">
                            <div class="review-item-header">
                                <strong><?= sanitize($review['reviewer_name']) ?></strong>
                                <span class="review-stars">⭐ <?= intval($review['rating']) ?>/5</span>
                            </div>
                            <p class="review-comment"><?= nl2br(sanitize($review['comment'])) ?></p>
                            <?php if (!empty($review['organizer_reply'])): ?>
                                <div class="review-reply-bubble">
                                    <strong>Organizer reply</strong>
                                    <p class="review-reply-text"><?= nl2br(sanitize($review['organizer_reply'])) ?></p>
                                </div>
                            <?php endif; ?>
                            <p class="review-date"><?= date('M d, Y', strtotime($review['created_at'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div>
        <div class="card" style="position: sticky; top: 100px;">
            <div class="card-body">
                <?php if (!empty($event['price'] ?? null)): ?>
                    <div style="margin-bottom: 20px;">
                        <p style="margin: 0 0 8px; color: var(--text-secondary); font-size: 0.9rem;">PRICE</p>
                        <div style="font-size: 2rem; font-weight: 700; color: var(--primary);">ETB <?= number_format($event['price'] ?? 0) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($hasBooking): ?>
                        <div style="background: #e6f4ea; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                            <h3 style="margin: 0 0 12px; color: #17783a;">✓ Booking Confirmed</h3>
                            <p style="margin: 0 0 12px; color: #17783a; font-size: 0.9rem;">Your QR ticket is ready. Show it at the venue.</p>
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($ticketQrData ?: $ticketCode) ?>" alt="QR ticket" style="width: 100%; border-radius: 8px;">
                            <p style="margin: 12px 0 0; text-align: center; color: var(--text-secondary); font-size: 0.85rem;">Code: <strong><?= sanitize($ticketCode) ?></strong></p>
                            <a href="user-dashboard.php?tab=bookings" class="button button-alt" style="width:100%;margin-top:10px;display:flex;justify-content:center;">Go to My Bookings</a>
                        </div>
                    <?php else: ?>
                        <form method="post">
                            <input type="hidden" name="book_ticket" value="1">
                            <button type="submit" class="button" style="width: 100%; padding: 14px;">Book Ticket</button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="login.php" class="button" style="width: 100%; display: flex; align-items: center; justify-content: center; padding: 14px;">Login to Book</a>
                <?php endif; ?>

                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border);">
                    <h4 style="margin: 0 0 12px; font-size: 0.9rem;">Event Features</h4>
                    <ul style="margin: 0; padding-left: 20px; color: var(--text-secondary); font-size: 0.9rem;">
                        <li>Easy Booking</li>
                        <li>Secure Payment</li>
                        <li>QR Code Ticket</li>
                        <li>24/7 Support</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.event-details-hero {
    position: relative;
    width: 100%;
    max-height: 400px;
    overflow: hidden;
    border-radius: 12px;
}

.event-hero-img {
    width: 100%;
    height: 400px;
    object-fit: cover;
    display: block;
}

.event-hero-overlay {
    position: absolute;
    top: 20px;
    left: 20px;
}

.event-section {
    margin-bottom: 32px;
    padding-bottom: 32px;
    border-bottom: 1px solid var(--border);
}

.event-section h3 {
    margin: 0 0 16px;
    font-size: 1.3rem;
    color: var(--text);
}

.event-section p {
    margin: 0;
    color: var(--text-secondary);
    line-height: 1.7;
}

.detail-item {
    padding: 12px;
    background: var(--primary-soft);
    border-radius: 8px;
}

.detail-item strong {
    display: block;
    margin-bottom: 6px;
    color: var(--text);
    font-size: 0.85rem;
    text-transform: uppercase;
}

.detail-item p {
    margin: 0;
    color: var(--text);
}

.review-item {
    padding: 16px;
    border: 1px solid var(--border);
    border-radius: 8px;
    margin-bottom: 12px;
}

.review-item:last-child {
    margin-bottom: 0;
}

@media (max-width: 940px) {
    .event-details-hero {
        max-height: 300px;
    }
    .event-hero-img {
        height: 300px;
    }
    .card {
        position: relative !important;
        top: auto !important;
    }
}
</style>
<?php include 'includes/footer.php'; ?>
