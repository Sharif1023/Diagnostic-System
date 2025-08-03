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

$error = null;
$success = null;

// Fetch invoice details
try {
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        header('Location: invoice_list.php');
        exit();
    }

    if ($invoice['due'] == 0) {
        header('Location: view_invoice.php?id=' . $invoice_id);
        exit();
    }
} catch(PDOException $e) {
    die("Error fetching invoice details: " . $e->getMessage());
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        $amount_paid = (float)$_POST['amount_paid'];

        if ($amount_paid <= 0) {
            throw new Exception("Amount paid must be greater than 0");
        }

        if ($amount_paid > $invoice['due']) {
            throw new Exception("Amount paid cannot be greater than due amount");
        }

        // Update invoice
        $stmt = $pdo->prepare("
            UPDATE invoices 
            SET amount_paid = amount_paid + ?,
                due = due - ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $amount_paid,
            $amount_paid,
            $invoice_id
        ]);

        $_SESSION['success'] = "Payment updated successfully";
        header('Location: view_invoice.php?id=' . $invoice_id);
        exit();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Payment - Laboratory Management System</title>
    <style>
        body { 
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f4f4f4;
            margin: 0;
            padding: 15px;
            color: #333;
        }
        .container {
            max-width: 800px;
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
        .form-group input {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        .form-group input:focus {
            border-color: #3498db;
            outline: none;
        }
        .form-group input[readonly] {
            background: #f8f9fa;
            cursor: not-allowed;
        }
        .error-message {
            color: #e74c3c;
            font-size: 14px;
            margin-top: 5px;
        }
        .success-message {
            color: #27ae60;
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
        .invoice-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .detail-group {
            margin-bottom: 10px;
        }
        .detail-label {
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
            margin-bottom: 5px;
        }
        .detail-value {
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h2>Update Payment</h2>
            </div>
            <a href="view_invoice.php?id=<?php echo $invoice_id; ?>" class="btn btn-back">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Back to Invoice
            </a>
        </div>

        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="form-section">
            <h3>Invoice Information</h3>
            <div class="invoice-details">
                <div>
                    <div class="detail-group">
                        <div class="detail-label">Invoice Number</div>
                        <div class="detail-value"><?php echo htmlspecialchars($invoice['invoice_no']); ?></div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Patient Name</div>
                        <div class="detail-value"><?php echo htmlspecialchars($invoice['patient_name']); ?></div>
                    </div>
                </div>
                <div>
                    <div class="detail-group">
                        <div class="detail-label">Total Amount</div>
                        <div class="detail-value">৳<?php echo number_format($invoice['total_amount'], 2); ?></div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Discount Amount</div>
                        <div class="detail-value">৳<?php echo number_format($invoice['discount_amount'], 2); ?></div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Amount Paid</div>
                        <div class="detail-value">৳<?php echo number_format($invoice['amount_paid'], 2); ?></div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Due Amount</div>
                        <div class="detail-value">৳<?php echo number_format($invoice['due'], 2); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="">
            <div class="form-section">
                <h3>Payment Details</h3>
                <div class="form-group">
                    <label for="amount_paid">Amount to Pay (৳)</label>
                    <input type="number" id="amount_paid" name="amount_paid" required min="0.01" step="0.01"
                           max="<?php echo $invoice['due']; ?>"
                           placeholder="Enter amount to pay">
                </div>
            </div>

            <div class="form-section">
                <button type="submit" class="btn btn-primary">Update Payment</button>
                <a href="view_invoice.php?id=<?php echo $invoice_id; ?>" class="btn btn-back">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html> 