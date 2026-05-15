<?php
require_once 'includes/config.php';
require_login();
if (!is_role('organizer')) { header('Location: login.php'); exit; }
$pageTitle = 'Organizer Profile | Event Ethiopia';
$message = '';
$error = '';
db_ensure_column($mysqli, 'users', 'organization_name', "VARCHAR(150) DEFAULT NULL");
db_ensure_column($mysqli, 'users', 'profile_image', "VARCHAR(255) DEFAULT NULL");
db_ensure_column($mysqli, 'users', 'contact_info', "VARCHAR(150) DEFAULT NULL");
db_ensure_column($mysqli, 'users', 'social_links', "TEXT NULL");

$profileStmt = $mysqli->prepare("SELECT name,email,organization_name,profile_image,contact_info,social_links FROM users WHERE id=?");
$profileStmt->bind_param('i', $_SESSION['user_id']);
$profileStmt->execute();
$profile = $profileStmt->get_result()->fetch_assoc();
$profileStmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $organizationName = sanitize($_POST['organization_name'] ?? '');
    $profileImage = filter_var($_POST['profile_image'] ?? '', FILTER_SANITIZE_URL);
    $contactInfo = sanitize($_POST['contact_info'] ?? '');
    $socialLinks = sanitize($_POST['social_links'] ?? '');
    if (!$name || !$email) {
        $error = 'Name and email are required.';
    } else {
        $stmt = $mysqli->prepare("UPDATE users SET name=?, email=?, organization_name=?, profile_image=?, contact_info=?, social_links=? WHERE id=?");
        $stmt->bind_param('ssssssi', $name, $email, $organizationName, $profileImage, $contactInfo, $socialLinks, $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $message = 'Profile updated.';
        $profile = [
            'name' => $name,
            'email' => $email,
            'organization_name' => $organizationName,
            'profile_image' => $profileImage,
            'contact_info' => $contactInfo,
            'social_links' => $socialLinks
        ];
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= sanitize($pageTitle) ?></title><link rel="stylesheet" href="css/style.css"></head><body>
<main class="page-content wrapper"><section class="heading-bar"><h2>Organizer Profile</h2><a href="organizer-dashboard.php" class="button button-alt">Back</a></section>
<?php if ($message): ?><div class="alert alert-success"><?= sanitize($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= sanitize($error) ?></div><?php endif; ?>
<div class="form-card" style="max-width:760px;"><form method="post" class="form-grid"><div><label>Name</label><input type="text" name="name" value="<?= sanitize($profile['name'] ?? $_SESSION['user_name']) ?>" required></div><div><label>Email</label><input type="email" name="email" value="<?= sanitize($profile['email'] ?? $_SESSION['user_email']) ?>" required></div><div><label>Organization Name</label><input type="text" name="organization_name" value="<?= sanitize($profile['organization_name'] ?? '') ?>"></div><div><label>Logo / Profile Image URL</label><input type="url" name="profile_image" value="<?= sanitize($profile['profile_image'] ?? '') ?>"></div><div><label>Contact Information</label><input type="text" name="contact_info" value="<?= sanitize($profile['contact_info'] ?? '') ?>"></div><div><label>Social Links</label><textarea class="form-textarea" rows="3" name="social_links" placeholder="Instagram: ..., LinkedIn: ..."><?= sanitize($profile['social_links'] ?? '') ?></textarea></div><button class="button">Save</button></form></div>
</main></body></html>
