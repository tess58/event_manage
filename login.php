<?php
require_once 'includes/config.php';
$pageTitle = 'Login | Event Ethiopia';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    db_ensure_column($mysqli, 'users', 'is_blocked', "TINYINT(1) NOT NULL DEFAULT 0");
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = trim($_POST['password'] ?? '');
    if (!$email || !$password) {
        $error = 'Email and password are required.';
    } else {
        // Check if users table exists
        $checkTable = $mysqli->query("SHOW TABLES LIKE 'users'");
        if ($checkTable->num_rows === 0) {
            $error = 'Database not initialized. <a href="setup-database.php" style="color: inherit; font-weight: 600; text-decoration: underline;">Click here to set up the database</a>';
        } else {
            $stmt = $mysqli->prepare("SELECT id, name, email, password, role, status, is_blocked FROM users WHERE email = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($user = $result->fetch_assoc()) {
                    $isPasswordValid = password_verify($password, $user['password']);
                    if (!$isPasswordValid && $password === $user['password']) {
                        $isPasswordValid = true;
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $updateStmt = $mysqli->prepare("UPDATE users SET password = ? WHERE id = ?");
                        if ($updateStmt) {
                            $updateStmt->bind_param('si', $hash, $user['id']);
                            $updateStmt->execute();
                            $updateStmt->close();
                        }
                    }

                    if (!$isPasswordValid) {
                        $error = 'Invalid email or password.';
                    } elseif (db_has_column($mysqli, 'users', 'is_blocked') && (int) ($user['is_blocked'] ?? 0) === 1) {
                        $error = 'Your account is blocked. Contact admin support.';
                    } elseif (normalize_role($user['role']) === 'organizer' && $user['status'] !== 'approved') {
                        $error = 'Your organizer account is pending approval. Please wait for admin to approve it.';
                    } else {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_email'] = $user['email'];
                        $normalizedRole = normalize_role($user['role']);
                        $_SESSION['user_role'] = $normalizedRole;
                        session_regenerate_id(true);

                        // Redirect based on role
                        if ($normalizedRole === 'admin') {
                            header('Location: admin-dashboard.php');
                        } elseif ($normalizedRole === 'organizer') {
                            header('Location: organizer-dashboard.php');
                        } else {
                            header('Location: user-dashboard.php');
                        }
                        exit;
                    }
                } else {
                    $error = 'Invalid email or password.';
                }
                $stmt->close();
            } else {
                $error = 'Database query error. <a href="setup-database.php" style="color: inherit; font-weight: 600; text-decoration: underline;">Click here to initialize</a>';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="login-page">
    <header class="site-header" id="site-header">
        <div class="wrapper header-inner">
            <a href="index.php" class="brand-logo">✦ Event Ethiopia</a>
            <button type="button" class="site-nav-toggle button button-alt" id="siteNavToggle" aria-expanded="false" aria-controls="site-main-nav">Menu</button>
            <nav class="main-nav" id="site-main-nav">
                <a href="index.php">Home</a>
                <a href="events.php">Events</a>
                <button id="themeToggle" class="button button-alt theme-toggle" type="button" aria-label="Toggle dark mode">🌙</button>
                <a href="register.php" class="button">Register</a>
            </nav>
        </div>
    </header>
    <main class="page-content wrapper">
        <div class="login-hero">
            <h1>Welcome Back!</h1>
            <p>Sign in to continue to your account</p>
            <p style="margin-top: 16px; opacity: 0.85;">Discover, Book, and Experience the best events in Ethiopia.</p>

            <div class="login-features">
                <div class="login-feature">
                    <div class="login-feature-icon">📍</div>
                    <div class="login-feature-text">
                        <h3>Find Amazing Events</h3>
                        <p>Discover thousands of events happening near you</p>
                    </div>
                </div>
                <div class="login-feature">
                    <div class="login-feature-icon">🎫</div>
                    <div class="login-feature-text">
                        <h3>Book Tickets Online</h3>
                        <p>Quick and easy ticket booking with secure payments</p>
                    </div>
                </div>
                <div class="login-feature">
                    <div class="login-feature-icon">🎁</div>
                    <div class="login-feature-text">
                        <h3>Get QR Code & Enjoy</h3>
                        <p>Get instant QR code tickets and enjoy events hassle-free</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="login-form-container">
            <h2>Login</h2>
            <p>Enter your credentials to access your account</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= strip_tags($error, '<a>') ?></div>
            <?php endif; ?>

            <form id="loginForm" method="post" action="login.php" class="form-grid">
                <div>
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="you@example.com" value="<?= sanitize($_POST['email'] ?? '') ?>" autocomplete="username" required>
                </div>
                <div>
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                    <div class="forgot-password">
                        <a href="#">Forgot Password?</a>
                    </div>
                </div>
                <button type="submit" class="button" style="width: 100%; margin-top: 8px;">Login</button>
            </form>

            <div class="demo-credentials">
                <p><strong>Demo Accounts</strong></p>
                <p>Admin: admin@example.com / password</p>
                <p>Organizer: organizer@example.com / password</p>
                <p>User: user@example.com / password</p>
            </div>

            <div class="form-register">
                Don't have an account? <a href="register.php">Register here</a>
            </div>
        </div>
    </main>
    <script src="js/script.js"></script>
    <script src="js/dark-mode.js"></script>
</body>

</html>