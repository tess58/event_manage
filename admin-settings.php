<?php
require_once 'includes/config.php';
require_login();
if (!is_role('admin')) { header('Location: login.php'); exit; }
$pageTitle = 'Admin Settings | Event Ethiopia';
$message = '';
$error = '';

// lightweight key-value system settings table
$mysqli->query(
    "CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT NOT NULL
    )"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $name = sanitize($_POST['name'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    if (!$name || !$email) {
        $error = 'Name and email are required.';
    } else {
        $stmt = $mysqli->prepare("UPDATE users SET name=?, email=? WHERE id=?");
        $stmt->bind_param('ssi', $name, $email, $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $message = 'Profile settings updated.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_system'])) {
    $settings = [
        'system_name' => sanitize($_POST['system_name'] ?? 'Event Ethiopia'),
        'max_event_capacity' => (string) max(1, (int) ($_POST['max_event_capacity'] ?? 1000)),
        'booking_limit_per_user' => (string) max(1, (int) ($_POST['booking_limit_per_user'] ?? 10)),
        'feature_reviews' => isset($_POST['feature_reviews']) ? '1' : '0',
        'feature_bookings' => isset($_POST['feature_bookings']) ? '1' : '0',
        'email_notifications' => isset($_POST['email_notifications']) ? '1' : '0',
    ];
    $stmt = $mysqli->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    foreach ($settings as $key => $value) {
        $stmt->bind_param('ss', $key, $value);
        $stmt->execute();
    }
    $stmt->close();
    $message = 'System settings updated.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $checkStmt = $mysqli->prepare("SELECT password FROM users WHERE id=?");
    $checkStmt->bind_param('i', $_SESSION['user_id']);
    $checkStmt->execute();
    $row = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();
    $valid = $row && (password_verify($current, $row['password']) || $current === $row['password']);
    if (!$valid) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New password and confirm password do not match.';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $updateStmt = $mysqli->prepare("UPDATE users SET password = ? WHERE id = ?");
        $updateStmt->bind_param('si', $hash, $_SESSION['user_id']);
        $updateStmt->execute();
        $updateStmt->close();
        $message = 'Password changed.';
    }
}

$settings = [];
$settingsResult = $mysqli->query("SELECT setting_key, setting_value FROM system_settings");
if ($settingsResult) {
    while ($row = $settingsResult->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= sanitize($pageTitle) ?></title><link rel="stylesheet" href="css/style.css"></head><body>
<main class="page-content wrapper">
<section class="heading-bar"><h2>Admin Settings</h2><a href="admin-dashboard.php" class="button button-alt">Back</a></section>
<?php if ($message): ?><div class="alert alert-success"><?= sanitize($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= sanitize($error) ?></div><?php endif; ?>
<div class="grid-2">
<div class="form-card">
<h3>Admin Profile</h3>
<form method="post" class="form-grid">
<input type="hidden" name="save_profile" value="1">
<div><label>Name</label><input type="text" name="name" value="<?= sanitize($_SESSION['user_name']) ?>" required></div>
<div><label>Email</label><input type="email" name="email" value="<?= sanitize($_SESSION['user_email']) ?>" required></div>
<button class="button" type="submit">Save Changes</button>
</form>
</div>
<div class="form-card">
<h3>Security</h3>
<form method="post" class="form-grid">
<input type="hidden" name="change_password" value="1">
<div><label>Current Password</label><input type="password" name="current_password" required></div>
<div><label>New Password</label><input type="password" name="new_password" required></div>
<div><label>Confirm New Password</label><input type="password" name="confirm_password" required></div>
<button class="button" type="submit">Change Password</button>
</form>
</div>
</div>
<div class="form-card table-card">
<h3>System Configuration</h3>
<form method="post" class="form-grid">
<input type="hidden" name="save_system" value="1">
<div><label>System Name</label><input type="text" name="system_name" value="<?= sanitize($settings['system_name'] ?? 'Event Ethiopia') ?>"></div>
<div><label>Max Event Capacity</label><input type="number" name="max_event_capacity" min="1" value="<?= sanitize($settings['max_event_capacity'] ?? '1000') ?>"></div>
<div><label>Booking Limit Per User</label><input type="number" name="booking_limit_per_user" min="1" value="<?= sanitize($settings['booking_limit_per_user'] ?? '10') ?>"></div>
<div><label><input type="checkbox" name="feature_reviews" <?= ($settings['feature_reviews'] ?? '1') === '1' ? 'checked' : '' ?>> Enable Reviews</label></div>
<div><label><input type="checkbox" name="feature_bookings" <?= ($settings['feature_bookings'] ?? '1') === '1' ? 'checked' : '' ?>> Enable Bookings</label></div>
<div><label><input type="checkbox" name="email_notifications" <?= ($settings['email_notifications'] ?? '0') === '1' ? 'checked' : '' ?>> Email Notifications</label></div>
<button class="button" type="submit">Save System Settings</button>
</form>
</div>
</main></body></html>
