<?php
// Database setup script - Run this once to initialize the database with sample data

$host = '127.0.0.1';
$db   = 'ethiopian_events';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

// Create database connection without selecting a database first
$mysqli = new mysqli($host, $user, $pass);

if ($mysqli->connect_error) {
    die('Connection Error: ' . $mysqli->connect_error);
}

echo "[v0] Database connection successful<br>";

// Create database
$createDb = "CREATE DATABASE IF NOT EXISTS $db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($mysqli->query($createDb)) {
    echo "[v0] Database created/verified<br>";
} else {
    die("[v0] Error creating database: " . $mysqli->error);
}

// Select the database
$mysqli->select_db($db);

// Create users table
$usersTable = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','organizer','user') NOT NULL DEFAULT 'user',
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($mysqli->query($usersTable)) {
    echo "[v0] Users table created/verified<br>";
} else {
    die("[v0] Error creating users table: " . $mysqli->error);
}

// Create categories table
$categoriesTable = "CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE
)";

if ($mysqli->query($categoriesTable)) {
    echo "[v0] Categories table created/verified<br>";
} else {
    die("[v0] Error creating categories table: " . $mysqli->error);
}

// Create events table
$eventsTable = "CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description LONGTEXT NOT NULL,
    date DATETIME NOT NULL,
    location VARCHAR(200) NOT NULL,
    price DECIMAL(10, 2),
    image_url VARCHAR(500),
    category_id INT,
    organizer_id INT,
    status ENUM('draft','published','cancelled') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (organizer_id) REFERENCES users(id)
)";

if ($mysqli->query($eventsTable)) {
    echo "[v0] Events table created/verified<br>";
} else {
    die("[v0] Error creating events table: " . $mysqli->error);
}

// Create bookings table
$bookingsTable = "CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    ticket_code VARCHAR(50) UNIQUE NOT NULL,
    quantity INT DEFAULT 1,
    status ENUM('confirmed','cancelled') DEFAULT 'confirmed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (event_id) REFERENCES events(id)
)";

if ($mysqli->query($bookingsTable)) {
    echo "[v0] Bookings table created/verified<br>";
} else {
    die("[v0] Error creating bookings table: " . $mysqli->error);
}

// Create reviews table
$reviewsTable = "CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    comment LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
)";

if ($mysqli->query($reviewsTable)) {
    echo "[v0] Reviews table created/verified<br>";
} else {
    die("[v0] Error creating reviews table: " . $mysqli->error);
}

// Insert sample data
echo "<br><strong>Inserting sample data...</strong><br>";

// Ensure sample users always exist with working credentials
$samplePassword = password_hash('password', PASSWORD_DEFAULT);
$sampleUsers = [
    ['Admin User', 'admin@example.com', $samplePassword, 'admin', 'approved'],
    ['Event Organizer', 'organizer@example.com', $samplePassword, 'organizer', 'approved'],
    ['John Doe', 'user@example.com', $samplePassword, 'user', 'approved'],
];

$upsertUser = $mysqli->prepare(
    "INSERT INTO users (name, email, password, role, status)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        password = VALUES(password),
        role = VALUES(role),
        status = VALUES(status)"
);

if ($upsertUser) {
    foreach ($sampleUsers as $sampleUser) {
        $upsertUser->bind_param('sssss', $sampleUser[0], $sampleUser[1], $sampleUser[2], $sampleUser[3], $sampleUser[4]);
        $upsertUser->execute();
    }
    $upsertUser->close();
    echo "[v0] Sample users created/updated successfully<br>";
    echo "Admin: admin@example.com / password<br>";
    echo "Organizer: organizer@example.com / password<br>";
    echo "User: user@example.com / password<br>";
} else {
    echo "[v0] Error preparing sample user update: " . $mysqli->error . "<br>";
}

// Insert sample categories if they don't exist
$checkCategories = "SELECT COUNT(*) as count FROM categories";
$result = $mysqli->query($checkCategories);
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    $insertCategories = "INSERT INTO categories (name) VALUES 
    ('Music'),
    ('Business'),
    ('Food'),
    ('Sports'),
    ('Art'),
    ('Technology')";

    if ($mysqli->query($insertCategories)) {
        echo "[v0] Sample categories inserted<br>";
    } else {
        echo "[v0] Error inserting categories: " . $mysqli->error . "<br>";
    }
} else {
    echo "[v0] Categories already exist<br>";
}

echo "<br><strong style='color: green;'>✓ Database setup complete!</strong><br>";
echo "You can now delete this file (setup-database.php) from your project.<br>";
echo "<a href='login.php'>Go to Login</a>";

$mysqli->close();
?>
