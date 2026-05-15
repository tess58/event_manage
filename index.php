<?php
require_once 'includes/config.php';
$pageTitle = 'Home | Event Ethiopia';

$eventsQuery = $mysqli->prepare(
    "SELECT e.id, e.title, e.date, e.location, c.name AS category_name, e.image_url
    FROM events e
    JOIN categories c ON e.category_id = c.id
    WHERE e.status = 'published'
    ORDER BY e.date ASC
    LIMIT 6"
);
$eventsQuery->execute();
$result = $eventsQuery->get_result();
$featured = $result->fetch_all(MYSQLI_ASSOC);
$eventsQuery->close();

$topQuery = $mysqli->prepare(
    "SELECT e.id, e.title, e.date, e.location, c.name AS category_name, e.image_url, COUNT(b.id) AS bookings
    FROM events e
    LEFT JOIN bookings b ON b.event_id = e.id AND b.status = 'confirmed'
    JOIN categories c ON c.id = e.category_id
    WHERE e.status = 'published'
    GROUP BY e.id
    ORDER BY bookings DESC
    LIMIT 3"
);
$topQuery->execute();
$topResult = $topQuery->get_result();
$topEvents = $topResult->fetch_all(MYSQLI_ASSOC);
$topQuery->close();
?>
<?php include 'includes/header.php'; ?>
<section class="hero">
    <div class="hero-content">
        <span class="badge">Ethiopian Event Management</span>
        <h1>Discover Amazing Events Across Ethiopia</h1>
        <p>Find concerts, food festivals, marathons, conferences and more. Browse, book, and manage your tickets with a modern event platform.</p>
        <div class="hero-actions">
            <a href="events.php" class="button">Browse Events</a>
            <a href="register.php" class="button button-alt">Create Account</a>
        </div>
    </div>
    <div class="hero-visual">
        <div class="hero-visual-text">
            <h2>Secure checkout • Local organizers • Fast ticketing</h2>
        </div>
    </div>
</section>
<section class="heading-bar">
    <h2>Popular Events</h2>
    <p>Book the best local experiences and find top venues in Ethiopia.</p>
</section>
<div class="popular-events-grid">
    <?php foreach ($topEvents as $event): ?>
        <article class="card card--clickable">
            <a class="card-stretch-link" href="event-details.php?id=<?= (int) $event['id'] ?>" aria-label="Open event: <?= sanitize($event['title']) ?>"><span class="visually-hidden">Open event details</span></a>
            <img src="<?= sanitize($event['image_url']) ?>" alt="<?= sanitize($event['title']) ?>">
            <div class="card-body">
                <div class="meta-row">
                    <span><?= date('M d, Y', strtotime($event['date'])) ?></span>
                    <span><?= sanitize($event['location']) ?></span>
                </div>
                <h3><?= sanitize($event['title']) ?></h3>
                <span class="meta-pill"><?= sanitize($event['category_name']) ?></span>
            </div>
        </article>
    <?php endforeach; ?>
</div>
<section class="heading-bar" style="margin-top:48px;">
    <h2>Latest Events</h2>
    <a href="events.php" class="button button-alt">View All Events</a>
</section>
<div class="cards-grid" id="eventsCards">
    <?php foreach ($featured as $event): ?>
        <article class="card card--clickable">
            <a class="card-stretch-link" href="event-details.php?id=<?= (int) $event['id'] ?>" aria-label="Open event: <?= sanitize($event['title']) ?>"><span class="visually-hidden">Open event details</span></a>
            <img src="<?= sanitize($event['image_url']) ?>" alt="<?= sanitize($event['title']) ?>">
            <div class="card-body">
                <div class="meta-row">
                    <span><?= date('M d, Y', strtotime($event['date'])) ?></span>
                    <span><?= sanitize($event['location']) ?></span>
                </div>
                <h3><?= sanitize($event['title']) ?></h3>
                <span class="meta-pill"><?= sanitize($event['category_name']) ?></span>
            </div>
        </article>
    <?php endforeach; ?>
</div>
<?php include 'includes/footer.php'; ?>