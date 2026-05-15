<?php
require_once 'includes/config.php';
$pageTitle = 'Events | Event Ethiopia';
$hasEventPrice = db_has_column($mysqli, 'events', 'price');
$priceSelect = $hasEventPrice ? 'e.price' : '0';
$sortBy = in_array($_GET['sort'] ?? 'date', ['date', 'newest', 'popularity', 'price'], true) ? $_GET['sort'] : 'date';
$priceType = in_array($_GET['price_type'] ?? '', ['free', 'paid'], true) ? $_GET['price_type'] : '';

$categoryStmt = $mysqli->prepare("SELECT id, name FROM categories ORDER BY name");
$categoryStmt->execute();
$categories = $categoryStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$categoryStmt->close();

$locationStmt = $mysqli->prepare("SELECT DISTINCT location FROM events WHERE status = 'published' ORDER BY location");
$locationStmt->execute();
$locations = $locationStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$locationStmt->close();

$orderBy = "e.date ASC";
if ($sortBy === 'newest') {
    $orderBy = "e.created_at DESC";
} elseif ($sortBy === 'popularity') {
    $orderBy = "bookings_count DESC, e.date ASC";
} elseif ($sortBy === 'price') {
    $orderBy = "price ASC, e.date ASC";
}
$priceFilterSql = '';
if ($priceType === 'free') {
    $priceFilterSql = " AND $priceSelect <= 0";
} elseif ($priceType === 'paid') {
    $priceFilterSql = " AND $priceSelect > 0";
}

$eventsStmt = $mysqli->prepare(
    "SELECT e.id, e.title, e.date, e.location, $priceSelect AS price, c.name AS category_name, e.image_url, COUNT(b.id) AS bookings_count
    FROM events e
    JOIN categories c ON e.category_id = c.id
    LEFT JOIN bookings b ON b.event_id = e.id
    WHERE e.status = 'published' $priceFilterSql
    GROUP BY e.id
    ORDER BY $orderBy"
);
$eventsStmt->execute();
$events = $eventsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$eventsStmt->close();
?>
<?php include 'includes/header.php'; ?>
<section class="events-hero">
    <h1>Discover Amazing Events</h1>
    <p>Find and book the best events in Ethiopia</p>
</section>

<form class="filter-panel events-filters" id="eventFilters" onsubmit="return false;">
    <input type="text" id="searchQuery" placeholder="Search events, artists, venues...">
    <select id="filterCategory">
        <option value="">All Categories</option>
        <?php foreach ($categories as $category): ?>
            <option value="<?= $category['id'] ?>"><?= sanitize($category['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select id="filterLocation">
        <option value="">All Locations</option>
        <?php foreach ($locations as $location): ?>
            <option value="<?= sanitize($location['location']) ?>"><?= sanitize($location['location']) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="date" id="filterDate">
    <select id="filterPriceType">
        <option value="">All Prices</option>
        <option value="free" <?= $priceType === 'free' ? 'selected' : '' ?>>Free</option>
        <option value="paid" <?= $priceType === 'paid' ? 'selected' : '' ?>>Paid</option>
    </select>
    <select id="sortBy">
        <option value="date" <?= $sortBy === 'date' ? 'selected' : '' ?>>Sort: Upcoming</option>
        <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Sort: Newest</option>
        <option value="popularity" <?= $sortBy === 'popularity' ? 'selected' : '' ?>>Sort: Popularity</option>
        <option value="price" <?= $sortBy === 'price' ? 'selected' : '' ?>>Sort: Price</option>
    </select>
    <button class="button" type="button">Search</button>
</form>

<section class="heading-bar">
    <h2>Popular Events</h2>
    <a href="events.php" class="button button-alt">View All</a>
</section>

<div class="cards-grid" id="eventsCards">
    <?php foreach ($events as $event): ?>
        <article class="card card--clickable">
            <a class="card-stretch-link" href="event-details.php?id=<?= (int) $event['id'] ?>" aria-label="Open event: <?= sanitize($event['title']) ?>"><span class="visually-hidden">Open event details</span></a>
            <div class="event-card-media">
                <img src="<?= sanitize($event['image_url']) ?>" alt="<?= sanitize($event['title']) ?>">
                <span class="category-badge"><?= sanitize($event['category_name']) ?></span>
            </div>
            <div class="card-body">
                <h3><?= sanitize($event['title']) ?></h3>
                <div class="meta-row">
                    <span>📍 <?= sanitize($event['location']) ?></span>
                </div>
                <div class="meta-row">
                    <span>📅 <?= date('M d, Y', strtotime($event['date'])) ?></span>
                </div>
                <?php if (!empty($event['price'])): ?>
                    <div class="event-price">
                        ETB <?= number_format($event['price']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</div>

<?php include 'includes/footer.php'; ?>
