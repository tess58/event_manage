<?php
require_once 'includes/config.php';
require_login();
if (!is_role('organizer')) { header('Location: login.php'); exit; }
$pageTitle = 'Organizer Settings | Event Ethiopia';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $stmt = $mysqli->prepare("SELECT password FROM users WHERE id=?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $valid = $row && (password_verify($current, $row['password']) || $current === $row['password']);
    if (!$valid) {
        $error = 'Current password is incorrect.';
    } elseif ($new === '' || strlen($new) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($new !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $update = $mysqli->prepare("UPDATE users SET password=? WHERE id=?");
        $update->bind_param('si', $hash, $_SESSION['user_id']);
        $update->execute();
        $update->close();
        $message = 'Password changed successfully.';
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= sanitize($pageTitle) ?></title><link rel="stylesheet" href="css/style.css"></head><body>
<main class="page-content wrapper"><section class="heading-bar"><h2>Organizer Settings</h2><a href="organizer-dashboard.php" class="button button-alt">Back</a></section>
<?php if ($message): ?><div class="alert alert-success"><?= sanitize($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= sanitize($error) ?></div><?php endif; ?>
<div class="form-card" style="max-width:640px;"><form method="post" class="form-grid"><input type="hidden" name="change_password" value="1"><input type="password" name="current_password" placeholder="Current password" required><input type="password" name="new_password" placeholder="New password" required><input type="password" name="confirm_password" placeholder="Confirm new password" required><button class="button">Change Password</button></form></div>
</main></body></html>
