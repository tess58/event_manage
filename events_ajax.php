<?php
require_once 'includes/config.php';
header('Content-Type: application/json; charset=utf-8');

$category = isset($_GET['category']) ? intval($_GET['category']) : 0;
$location = isset($_GET['location']) ? sanitize($_GET['location']) : '';
$date = isset($_GET['date']) ? sanitize($_GET['date']) : '';
$query = isset($_GET['query']) ? sanitize($_GET['query']) : '';
$sort = in_array($_GET['sort'] ?? 'date', ['date', 'newest', 'popularity', 'price'], true) ? $_GET['sort'] : 'date';
$priceType = in_array($_GET['price_type'] ?? '', ['free', 'paid'], true) ? $_GET['price_type'] : '';
$priceExpr = db_has_column($mysqli, 'events', 'price') ? 'e.price' : '0';

$sql = "SELECT e.id, e.title, e.date, e.location, $priceExpr AS price, c.name AS category_name, e.image_url, COUNT(b.id) AS bookings_count
        FROM events e
        JOIN categories c ON e.category_id = c.id
        LEFT JOIN bookings b ON b.event_id = e.id
        WHERE e.status = 'published'";
$params = [];
$types = '';

if ($category) {
    $sql .= " AND e.category_id = ?";
    $types .= 'i';
    $params[] = $category;
}
if ($location) {
    $sql .= " AND e.location LIKE ?";
    $types .= 's';
    $params[] = "%{$location}%";
}
if ($date) {
    $sql .= " AND e.date = ?";
    $types .= 's';
    $params[] = $date;
}
if ($query) {
    $sql .= " AND (e.title LIKE ? OR e.description LIKE ? OR e.location LIKE ? OR c.name LIKE ?)";
    $types .= 'ssss';
    $term = "%{$query}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}
if ($priceType === 'free') {
    $sql .= " AND $priceExpr <= 0";
} elseif ($priceType === 'paid') {
    $sql .= " AND $priceExpr > 0";
}
$sql .= " GROUP BY e.id";
if ($sort === 'newest') {
    $sql .= " ORDER BY e.created_at DESC";
} elseif ($sort === 'popularity') {
    $sql .= " ORDER BY bookings_count DESC, e.date ASC";
} elseif ($sort === 'price') {
    $sql .= " ORDER BY price ASC, e.date ASC";
} else {
    $sql .= " ORDER BY e.date ASC";
}

$stmt = $mysqli->prepare($sql);
if ($stmt === false) {
    echo json_encode([]);
    exit;
}
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$events = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode($events);
