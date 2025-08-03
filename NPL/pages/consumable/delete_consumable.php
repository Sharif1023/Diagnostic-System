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

// Check if consumable code is provided
if (!isset($_GET['code'])) {
    header('Location: consumable_list.php');
    exit();
}

$consumable_code = $_GET['code'];

// Include database configuration
require_once '../../config/database.php';

// Get database connection
$pdo = getDBConnection();

try {
    // Check if consumable is used in any invoices
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice_consumables WHERE consumable_code = ?");
    $stmt->execute([$consumable_code]);
    if ($stmt->fetchColumn() > 0) {
        $_SESSION['error'] = "Cannot delete consumable: It is being used in one or more invoices";
        header('Location: consumable_list.php');
        exit();
    }

    // Delete the consumable
    $stmt = $pdo->prepare("DELETE FROM consumable_info WHERE consumable_code = ?");
    $stmt->execute([$consumable_code]);

    $_SESSION['success'] = "Consumable deleted successfully";
} catch(PDOException $e) {
    $_SESSION['error'] = "Error deleting consumable: " . $e->getMessage();
}

// Redirect back to consumable list
header('Location: consumable_list.php');
exit(); 