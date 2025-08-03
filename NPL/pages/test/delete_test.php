<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /index.php');
    exit();
}

// Check if test code is provided
if (!isset($_GET['code'])) {
    header('Location: test_list.php');
    exit();
}

$test_code = $_GET['code'];

// Include database configuration
require_once '../../config/database.php';

// Get database connection
$pdo = getDBConnection();

try {
    // Check if test exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tests_info WHERE test_code = ?");
    $stmt->execute([$test_code]);
    if ($stmt->fetchColumn() === 0) {
        header('Location: test_list.php');
        exit();
    }

    // Check if test is used in any reports
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM report_tests WHERE test_code = ?");
    $stmt->execute([$test_code]);
    if ($stmt->fetchColumn() > 0) {
        $_SESSION['error'] = "Cannot delete test: It is being used in one or more reports";
        header('Location: test_list.php');
        exit();
    }

    // Delete test
    $stmt = $pdo->prepare("DELETE FROM tests_info WHERE test_code = ?");
    $stmt->execute([$test_code]);

    $_SESSION['success'] = "Test deleted successfully";
} catch(PDOException $e) {
    $_SESSION['error'] = "Error deleting test: " . $e->getMessage();
}

header('Location: test_list.php');
exit(); 