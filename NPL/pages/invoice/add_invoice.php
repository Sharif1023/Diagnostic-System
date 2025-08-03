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

// Include database configuration
require_once '../../config/database.php';

// Get database connection
$pdo = getDBConnection();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        $patient_name = trim($_POST['patient_name']);
        $patient_age = trim($_POST['patient_age']);
        $patient_gender = trim($_POST['patient_gender']);
        $patient_contact_no = trim($_POST['patient_contact_no']);
        $referred_by = trim($_POST['referred_by'] ?? '');
        $delivery_date = trim($_POST['delivery_date']);
        $amount_paid = (float)$_POST['amount_paid'];
        $discount_amount = (float)$_POST['discount_amount'];
        $selected_tests = isset($_POST['tests']) ? $_POST['tests'] : [];
        $selected_consumables = isset($_POST['consumables']) ? $_POST['consumables'] : [];

        if (empty($patient_name)) {
            throw new Exception("Patient name is required");
        }

        if ($amount_paid < 0) {
            throw new Exception("Amount paid cannot be negative");
        }

        if ($discount_amount < 0) {
            throw new Exception("Discount amount cannot be negative");
        }

        if (empty($selected_tests) && empty($selected_consumables)) {
            throw new Exception("Please select at least one test or consumable");
        }

        if (empty($delivery_date)) {
            throw new Exception("Delivery date is required");
        }

        // Calculate total amount
        $total_amount = 0;

        // Calculate test amounts
        foreach ($selected_tests as $test_code) {
            $stmt = $pdo->prepare("SELECT price FROM tests_info WHERE test_code = ?");
            $stmt->execute([$test_code]);
            $test = $stmt->fetch(PDO::FETCH_ASSOC);
            $total_amount += $test['price'];
        }

        // Calculate consumable amounts
        foreach ($selected_consumables as $consumable_code) {
            $stmt = $pdo->prepare("SELECT price FROM consumable_info WHERE consumable_code = ?");
            $stmt->execute([$consumable_code]);
            $consumable = $stmt->fetch(PDO::FETCH_ASSOC);
            $quantity = isset($_POST['quantities'][$consumable_code]) ? (int)$_POST['quantities'][$consumable_code] : 1;
            $total_amount += ($consumable['price'] * $quantity);
        }

        // Generate invoice number
        $stmt = $pdo->query("SELECT invoice_no FROM invoices ORDER BY id DESC LIMIT 1");
        $last_invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($last_invoice) {
            // Extract the numeric part and increment
            preg_match('/NPL(\d+)/', $last_invoice['invoice_no'], $matches);
            $next_number = (int)$matches[1] + 1;
        } else {
            // If no invoices exist, start with 1
            $next_number = 1;
        }
        
        // Format with leading zeros (e.g., NPL00000001)
        $invoice_no = 'NPL' . str_pad($next_number, 8, '0', STR_PAD_LEFT);

        // Start transaction
        $pdo->beginTransaction();

        // Insert invoice
        $stmt = $pdo->prepare("
            INSERT INTO invoices (
                invoice_no,
                patient_name,
                patient_age,
                patient_gender,
                patient_contact_no,
                referred_by,
                delivery_date,
                total_amount,
                discount_amount,
                amount_paid,
                due
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $net_total = $total_amount - $discount_amount;
        $due = max(0, $net_total - $amount_paid);
        
        $stmt->execute([
            $invoice_no,
            $patient_name,
            $patient_age,
            $patient_gender,
            $patient_contact_no,
            $referred_by,
            $delivery_date,
            $total_amount,
            $discount_amount,
            $amount_paid,
            $due
        ]);

        $invoice_id = $pdo->lastInsertId();

        // Insert invoice tests
        if (!empty($selected_tests)) {
            $stmt = $pdo->prepare("
                INSERT INTO invoice_tests (
                    invoice_id,
                    test_code,
                    quantity,
                    unit_price
                ) VALUES (?, ?, 1, ?)
            ");
            
            foreach ($selected_tests as $test_code) {
                $stmt = $pdo->prepare("SELECT price FROM tests_info WHERE test_code = ?");
                $stmt->execute([$test_code]);
                $test = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $pdo->prepare("
                    INSERT INTO invoice_tests (
                        invoice_id,
                        test_code,
                        quantity,
                        unit_price
                    ) VALUES (?, ?, 1, ?)
                ");
                $stmt->execute([$invoice_id, $test_code, $test['price']]);
            }
        }

        // Insert invoice consumables
        if (!empty($selected_consumables)) {
            foreach ($selected_consumables as $consumable_code) {
                // Get consumable price
                $stmt = $pdo->prepare("SELECT price FROM consumable_info WHERE consumable_code = ?");
                $stmt->execute([$consumable_code]);
                $consumable = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Get quantity from form
                $quantity = isset($_POST['quantities'][$consumable_code]) ? (int)$_POST['quantities'][$consumable_code] : 1;
                
                // Insert with quantity and unit price
                $stmt = $pdo->prepare("
                    INSERT INTO invoice_consumables (
                        invoice_id,
                        consumable_code,
                        quantity,
                        unit_price
                    ) VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$invoice_id, $consumable_code, $quantity, $consumable['price']]);
            }
        }

        // Commit transaction
        $pdo->commit();

        // Set success message and redirect
        $_SESSION['success'] = "Invoice created successfully";
        
        // Check if this is a print action
        if (isset($_POST['action']) && $_POST['action'] === 'print') {
            // Redirect to print page in the same window
            header("Location: print_invoice.php?id=" . $invoice_id);
            exit();
        } else {
            // Redirect to invoice list with success message
            header("Location: view_invoice_list.php");
            exit();
        }
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = $e->getMessage();
        header('Location: new_invoice.php');
        exit();
    }
} else {
    // If not a POST request, redirect to new invoice form
    header('Location: new_invoice.php');
    exit();
} 