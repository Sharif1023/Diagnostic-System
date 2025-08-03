<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit();
}

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "You do not have permission to delete invoices";
    header('Location: invoice_list.php');
    exit();
}

// Check if invoice ID is provided
if (!isset($_GET['id'])) {
    header('Location: invoice_list.php');
    exit();
}

$invoice_id = (int)$_GET['id'];

// Include database configuration
require_once '../../config/database.php';

// Get database connection
$pdo = getDBConnection();

try {
    // Start transaction
    $pdo->beginTransaction();

    // Delete invoice tests
    $stmt = $pdo->prepare("DELETE FROM invoice_tests WHERE invoice_id = ?");
    $stmt->execute([$invoice_id]);

    // Delete invoice consumables
    $stmt = $pdo->prepare("DELETE FROM invoice_consumables WHERE invoice_id = ?");
    $stmt->execute([$invoice_id]);

    // Delete invoice
    $stmt = $pdo->prepare("DELETE FROM invoices WHERE id = ?");
    $stmt->execute([$invoice_id]);

    // Commit transaction
    $pdo->commit();

    $_SESSION['success'] = "Invoice deleted successfully";
} catch(PDOException $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error'] = "Error deleting invoice: " . $e->getMessage();
}

// Redirect back to invoice list
header('Location: invoice_list.php');
exit(); 