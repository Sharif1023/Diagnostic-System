<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include config file for BASE_URL
require_once __DIR__ . '/../../config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Niruponi Pathology Laboratory System</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/common.css">
</head>
<body>
    <header>
        <div class="header-container">
            <h1>Niruponi Pathology Laboratory System</h1>
            <div class="user-info">
                <?php if (isset($_SESSION['username'])): ?>
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a href="../../logout.php" class="logout-btn">Logout</a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    <div class="main-container">
        <?php
        // Include the appropriate sidebar based on user role
        if (isset($_SESSION['role'])) {
            if ($_SESSION['role'] === 'admin') {
                include 'admin_sidebar.php';
            } else {
                include 'staff_sidebar.php';
            }
        }
        ?>
        <div class="main-content"> 