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

// Determine dashboard URL based on user role
$dashboard_url = '/NPL/pages/user/staff_dashboard.php'; // Default to staff dashboard
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $dashboard_url = '/NPL/pages/user/admin_dashboard.php';
}

// Check if invoice ID is provided
if (!isset($_GET['id'])) {
    header('Location: view_invoice_list.php');
    exit();
}

$invoice_id = $_GET['id'];

// Include database configuration
require_once '../../config/database.php';

// Get database connection
$pdo = getDBConnection();

// Fetch invoice details
try {
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        header('Location: view_invoice_list.php');
        exit();
    }
    
    // Process payment if form is submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $amount_paid = floatval($_POST['amount_paid']);
        
        if ($amount_paid > 0 && $amount_paid <= ($invoice['total_amount'] - $invoice['amount_paid'])) {
            $new_amount_paid = $invoice['amount_paid'] + $amount_paid;
            
            $stmt = $pdo->prepare("UPDATE invoices SET amount_paid = ? WHERE id = ?");
            $stmt->execute([$new_amount_paid, $invoice_id]);
            
            header("Location: view_invoice.php?id=" . $invoice_id);
            exit();
        } else {
            $error = "Invalid payment amount";
        }
    }
} catch(PDOException $e) {
    die("Error processing payment: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Bill - Laboratory Management System</title>
    <style>
        body { 
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f4f4f4;
            margin: 0;
            padding: 15px;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 0 15px;
        }
        .page-header {
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .page-title {
            flex: 1;
        }
        .form-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .form-section h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            font-size: 18px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        .form-group input:focus,
        .form-group select:focus {
            border-color: #3498db;
            outline: none;
        }
        .error-message {
            color: #e74c3c;
            font-size: 14px;
            margin-top: 5px;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
        }
        .btn-primary {
            background: #3498db;
            color: white;
        }
        .btn-primary:hover {
            background: #2980b9;
        }
        .btn-back {
            background: #7f8c8d;
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-back:hover {
            background: #6c7a7d;
        }
        .price-column {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: #2c3e50;
        }
        .payment-summary {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .payment-summary p {
            margin: 8px 0;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h2>Pay Bill</h2>
            </div>
            <a href="<?php echo $dashboard_url; ?>" class="btn btn-back">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Go Back to Dashboard
            </a>
        </div>

        <div class="form-section">
            <div class="payment-summary">
                <p><strong>Invoice Number:</strong> <?php echo htmlspecialchars($invoice['invoice_number']); ?></p>
                <p><strong>Total Amount:</strong> ৳<?php echo number_format($invoice['total_amount'], 2); ?></p>
                <p><strong>Amount Paid:</strong> ৳<?php echo number_format($invoice['amount_paid'], 2); ?></p>
                <p><strong>Due Amount:</strong> ৳<?php echo number_format($invoice['total_amount'] - $invoice['amount_paid'], 2); ?></p>
            </div>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="amount_paid">Amount to Pay</label>
                    <input type="number" id="amount_paid" name="amount_paid" step="0.01" min="0" max="<?php echo $invoice['total_amount'] - $invoice['amount_paid']; ?>" required>
                    <?php if (isset($error)): ?>
                        <div class="error-message"><?php echo $error; ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Process Payment</button>
                    <a href="view_invoice.php?id=<?php echo $invoice_id; ?>" class="btn btn-back">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

