<?php
if (!isset($pageTitle)) {
    $pageTitle = 'Ethiopian Event Management';
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
    <script src="js/dark-mode.js" defer></script>
</head>

<body>
<?php
$isDashboard = strpos($_SERVER['PHP_SELF'], 'user-dashboard.php') !== false || strpos($_SERVER['PHP_SELF'], 'organizer-dashboard.php') !== false || strpos($_SERVER['PHP_SELF'], 'admin-') !== false;
$dashUrl = 'user-dashboard.php';
if (function_exists('is_role')) {
    if (is_role('admin')) $dashUrl = 'admin-dashboard.php';
    elseif (is_role('organizer')) $dashUrl = 'organizer-dashboard.php';
}
?>
    <header class="site-header" id="site-header">
        <?php if ($isDashboard): ?>
        <div class="wrapper header-inner" style="gap: 12px; flex-wrap: wrap;">
            <a href="index.php" class="brand-logo" style="margin-right: auto;">Event Ethiopia</a>
            <div class="dashboard-header-actions">
                <button type="button" class="dashboard-drawer-toggle button">Menu</button>
            </div>
        </div>
        <?php else: ?>
        <div class="wrapper header-inner">
            <a href="index.php" class="brand-logo">✦ Event Ethiopia</a>
            <button type="button" class="site-nav-toggle button button-alt" id="siteNavToggle" aria-expanded="false" aria-controls="site-main-nav">Menu</button>
            <nav class="main-nav" id="site-main-nav">
                <a href="index.php">Home</a>
                <a href="events.php">Events</a>
                <?php if (is_logged_in()): ?>
                    <?php if (is_role('admin')): ?>
                        <a href="admin-dashboard.php">Admin Panel</a>
                    <?php elseif (is_role('organizer')): ?>
                        <a href="organizer-dashboard.php">My Dashboard</a>
                    <?php else: ?>
                        <a href="user-dashboard.php">My Dashboard</a>
                    <?php endif; ?>
                    <span style="color: var(--text-secondary); font-size: 0.9rem;">Welcome, <?= sanitize(substr($_SESSION['user_name'], 0, 20)) ?></span>
                    <a href="logout.php" class="button button-alt">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="button">Login</a>
                <?php endif; ?>
                <button id="themeToggle" class="button button-alt theme-toggle" type="button" aria-label="Toggle dark mode">🌙</button>
            </nav>
        </div>
        <?php endif; ?>
    </header>
    <main class="page-content wrapper">
