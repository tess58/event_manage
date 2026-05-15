<?php
require_once 'includes/config.php';
$pageTitle = 'Register | Event Ethiopia';
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $role = in_array($_POST['role'] ?? 'user', ['user', 'organizer']) ? $_POST['role'] : 'user';

    if (!$name || !$email || !$password || !$confirm) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = 'Email is already registered.';
        } else {
            $status = $role === 'organizer' ? 'pending' : 'approved';
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $insert = $mysqli->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
            $insert->bind_param('sssss', $name, $email, $hash, $role, $status);
            if ($insert->execute()) {
                $success = $role === 'organizer' ? 'Registration successful. Await admin approval for your organizer account.' : 'Account created. You can now log in.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
            $insert->close();
        }
        $stmt->close();
    }
}
?>
<?php include 'includes/header.php'; ?>
<section class="heading-bar">
    <h2>Create an account</h2>
    <p>Register as an attendee or event organizer in Ethiopia.</p>
</section>
<div class="form-card" style="max-width: 620px; margin: 0 auto;">
    <?php if ($error): ?>
        <div class="alert alert-error"><?= sanitize($error) ?></div>
    <?php elseif ($success): ?>
        <div class="alert alert-success"><?= sanitize($success) ?></div>
    <?php endif; ?>
    <form id="registerForm" method="post" action="register.php" class="form-grid">
        <div>
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" required>
        </div>
        <div>
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div>
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div>
            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </div>
        <div>
            <label for="role">Account type</label>
            <select id="role" name="role" required>
                <option value="user">Attendee</option>
                <option value="organizer">Event Organizer</option>
            </select>
        </div>
        <button type="submit" class="button">Register</button>
    </form>
    <p class="text-center" style="margin-top:16px;">Already registered? <a href="login.php">Login here</a></p>
</div>
<?php include 'includes/footer.php'; ?>