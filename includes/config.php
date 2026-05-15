<?php
session_start();

$host = '127.0.0.1';
$db   = 'ethiopian_events';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) {
    die('Database connection failed: ' . $mysqli->connect_error);
}
$mysqli->set_charset($charset);

function sanitize($value)
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function is_logged_in()
{
    return isset($_SESSION['user_id']);
}

function require_login()
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function is_role($role)
{
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    return normalize_role($_SESSION['user_role']) === normalize_role($role);
}

function normalize_role($role)
{
    $value = strtolower(trim((string) $role));
    if ($value === 'attendee' || $value === 'normal_user' || $value === 'customer') {
        return 'user';
    }
    return $value;
}

function db_has_column($mysqli, $table, $column)
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($safeTable === '' || $safeColumn === '') {
        return false;
    }

    $query = "SHOW COLUMNS FROM `$safeTable` LIKE '$safeColumn'";
    $result = $mysqli->query($query);
    return $result && $result->num_rows > 0;
}

function db_ensure_column($mysqli, $table, $column, $definition)
{
    if (!db_has_column($mysqli, $table, $column)) {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($safeTable !== '' && $safeColumn !== '') {
            $mysqli->query("ALTER TABLE `$safeTable` ADD COLUMN `$safeColumn` $definition");
        }
    }
}

function get_booking_code_column($mysqli)
{
    return db_has_column($mysqli, 'bookings', 'qr_code') ? 'qr_code' : 'ticket_code';
}

function create_notification($mysqli, $userId, $title, $message, $type = 'info')
{
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
    $stmt = $mysqli->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('isss', $userId, $title, $message, $type);
        $stmt->execute();
        $stmt->close();
    }
}

function ensure_booking_qr_schema($mysqli)
{
    db_ensure_column($mysqli, 'bookings', 'ticket_number', "VARCHAR(40) NULL");
    db_ensure_column($mysqli, 'bookings', 'qr_code_data', "TEXT NULL");
    db_ensure_column($mysqli, 'bookings', 'validation_token', "VARCHAR(128) NULL");
    db_ensure_column($mysqli, 'bookings', 'payment_status', "VARCHAR(30) NOT NULL DEFAULT 'paid'");
    db_ensure_column($mysqli, 'bookings', 'check_in_status', "VARCHAR(30) NOT NULL DEFAULT 'pending'");
    db_ensure_column($mysqli, 'bookings', 'checked_in_at', "DATETIME NULL");
    db_ensure_column($mysqli, 'bookings', 'ticket_type', "VARCHAR(50) NOT NULL DEFAULT 'Regular'");
    db_ensure_column($mysqli, 'events', 'attendance_count', "INT NOT NULL DEFAULT 0");
}

function booking_qr_secret()
{
    return 'event_ethiopia_qr_secret_v1';
}

function generate_ticket_number($bookingId, $eventId, $userId)
{
    return sprintf('ET-%d-%d-%d-%s', $eventId, $userId, $bookingId, strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)));
}

function build_booking_qr_payload($bookingId, $userId, $eventId, $ticketNumber)
{
    $base = [
        'booking_id' => (int) $bookingId,
        'user_id' => (int) $userId,
        'event_id' => (int) $eventId,
        'ticket_number' => $ticketNumber,
    ];
    $token = hash_hmac('sha256', implode('|', $base), booking_qr_secret());
    $base['token'] = $token;
    return json_encode($base);
}

function create_booking_with_qr($mysqli, $userId, $eventId, $status = 'confirmed')
{
    ensure_booking_qr_schema($mysqli);
    $qrColumn = get_booking_code_column($mysqli);
    $seedCode = 'QR-' . strtoupper(bin2hex(random_bytes(6)));
    $stmt = $mysqli->prepare("INSERT INTO bookings (user_id, event_id, $qrColumn, status, payment_status, check_in_status) VALUES (?, ?, ?, ?, 'paid', 'pending')");
    if (!$stmt) {
        return [false, 'Unable to prepare booking query.', null];
    }
    $stmt->bind_param('iiss', $userId, $eventId, $seedCode, $status);
    $ok = $stmt->execute();
    $bookingId = (int) $stmt->insert_id;
    $stmt->close();
    if (!$ok || $bookingId <= 0) {
        return [false, 'Unable to create booking.', null];
    }

    $ticketNumber = generate_ticket_number($bookingId, $eventId, $userId);
    $qrData = build_booking_qr_payload($bookingId, $userId, $eventId, $ticketNumber);
    $validationToken = hash('sha256', $qrData);

    $update = $mysqli->prepare("UPDATE bookings SET ticket_number = ?, qr_code_data = ?, validation_token = ?, $qrColumn = ? WHERE id = ?");
    $update->bind_param('ssssi', $ticketNumber, $qrData, $validationToken, $ticketNumber, $bookingId);
    $update->execute();
    $update->close();

    return [true, '', ['booking_id' => $bookingId, 'ticket_number' => $ticketNumber, 'qr_code_data' => $qrData, 'validation_token' => $validationToken]];
}

function validate_qr_payload($payload)
{
    $decoded = json_decode($payload, true);
    if (!is_array($decoded)) {
        return [false, 'Invalid QR data format.', null];
    }
    $required = ['booking_id', 'user_id', 'event_id', 'ticket_number', 'token'];
    foreach ($required as $key) {
        if (!array_key_exists($key, $decoded) || $decoded[$key] === '') {
            return [false, 'Missing booking data.', null];
        }
    }
    $expected = hash_hmac(
        'sha256',
        ((int) $decoded['booking_id']) . '|' . ((int) $decoded['user_id']) . '|' . ((int) $decoded['event_id']) . '|' . $decoded['ticket_number'],
        booking_qr_secret()
    );
    if (!hash_equals($expected, (string) $decoded['token'])) {
        return [false, 'Invalid ticket token.', null];
    }
    return [true, '', $decoded];
}

function ensure_event_announcement_schema($mysqli)
{
    $mysqli->query(
        "CREATE TABLE IF NOT EXISTS event_announcements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            organizer_id INT NOT NULL,
            title VARCHAR(180) NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ea_event (event_id),
            INDEX idx_ea_org (organizer_id)
        )"
    );
    $mysqli->query(
        "CREATE TABLE IF NOT EXISTS event_announcement_dismissals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            announcement_id INT NOT NULL,
            user_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_ann_user (announcement_id, user_id),
            INDEX idx_ead_user (user_id)
        )"
    );
}

/**
 * @return array{0:bool,1:string,2:array{id:int,recipient_count:int}|null}
 */
function create_event_announcement($mysqli, $organizerId, $eventId, $title, $message)
{
    ensure_event_announcement_schema($mysqli);
    $organizerId = (int) $organizerId;
    $eventId = (int) $eventId;
    $title = trim($title);
    $message = trim($message);
    if ($title === '' || $message === '') {
        return [false, 'Title and message are required.', null];
    }
    $chk = $mysqli->prepare("SELECT id FROM events WHERE id=? AND organizer_id=? LIMIT 1");
    $chk->bind_param('ii', $eventId, $organizerId);
    $chk->execute();
    $okEvent = $chk->get_result()->num_rows > 0;
    $chk->close();
    if (!$okEvent) {
        return [false, 'Event not found or not owned by you.', null];
    }
    $stmt = $mysqli->prepare("INSERT INTO event_announcements (event_id, organizer_id, title, message) VALUES (?,?,?,?)");
    if (!$stmt) {
        return [false, 'Unable to save announcement.', null];
    }
    $stmt->bind_param('iiss', $eventId, $organizerId, $title, $message);
    $ok = $stmt->execute();
    $newId = (int) $stmt->insert_id;
    $stmt->close();
    if (!$ok || $newId <= 0) {
        return [false, 'Unable to save announcement.', null];
    }
    $attStmt = $mysqli->prepare("SELECT COUNT(*) AS c FROM bookings WHERE event_id=? AND status='confirmed'");
    $attStmt->bind_param('i', $eventId);
    $attStmt->execute();
    $count = (int) ($attStmt->get_result()->fetch_assoc()['c'] ?? 0);
    $attStmt->close();
    return [true, '', ['id' => $newId, 'recipient_count' => $count]];
}
