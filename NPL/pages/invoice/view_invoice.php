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

try {
    // Fetch invoice details
    $stmt = $pdo->prepare("
        SELECT i.*
        FROM invoices i
        WHERE i.id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        header('Location: view_invoice_list.php');
        exit();
    }

    // Fetch invoice tests
    $stmt = $pdo->prepare("
        SELECT it.*, t.test_name, t.category, t.price
        FROM invoice_tests it
        JOIN tests_info t ON it.test_code = t.test_code
        WHERE it.invoice_id = ?
        ORDER BY t.category, t.test_name
    ");
    $stmt->execute([$invoice_id]);
    $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch invoice consumables
    $stmt = $pdo->prepare("
        SELECT ic.*, c.name, c.price
        FROM invoice_consumables ic
        JOIN consumable_info c ON ic.consumable_code = c.consumable_code
        WHERE ic.invoice_id = ?
        ORDER BY c.name
    ");
    $stmt->execute([$invoice_id]);
    $consumables = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error fetching invoice details: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Invoice - Laboratory Management System</title>
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
        .invoice-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .invoice-section h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            font-size: 18px;
        }
        .invoice-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
            font-size: 14px;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        .total-section {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
        }
        .total-group {
            text-align: right;
        }
        .total-label {
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
            margin-bottom: 5px;
        }
        .total-value {
            font-size: 16px;
            font-weight: 600;
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
        .status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-pending {
            background: #f1c40f;
            color: #fff;
        }
        .status-paid {
            background: #2ecc71;
            color: #fff;
        }
        .status-partial {
            background: #e67e22;
            color: #fff;
        }
        @media print {
            body {
                background: white;
                padding: 0;
                font-size: 9px;
            }
            .container {
                max-width: 100%;
                padding: 0;
            }
            .invoice-section {
                box-shadow: none;
                padding: 8px;
                margin-bottom: 8px;
            }
            .invoice-section h3 {
                font-size: 10px;
                margin-bottom: 6px;
            }
            .detail-label {
                font-size: 9px;
            }
            .detail-value {
                font-size: 9px;
            }
            table {
                margin-bottom: 8px;
            }
            th, td {
                padding: 4px;
                font-size: 9px;
            }
            .total-label {
                font-size: 9px;
            }
            .total-value {
                font-size: 10px;
            }
            .btn {
                display: none;
            }
            .invoice-header {
                margin-bottom: 8px;
            }
            .invoice-details {
                gap: 6px;
                margin-bottom: 8px;
            }
            .detail-group {
                margin-bottom: 4px;
            }
            .status {
                font-size: 8px;
                padding: 2px 4px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h2>Invoice Details</h2>
            </div>
            <a href="view_invoice_list.php" class="btn btn-back">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Back to Invoice List
            </a>
        </div>

        <div class="invoice-section">
            <div class="invoice-header">
                <div>
                    <h3>Invoice #<?php echo htmlspecialchars($invoice['invoice_no']); ?></h3>
                    <div class="detail-group">
                        <div class="detail-label">Date</div>
                        <div class="detail-value"><?php echo date('d M Y', strtotime($invoice['created_at'])); ?></div>
                    </div>
                </div>
                <div>
                    <div class="detail-group">
                        <div class="detail-label">Status</div>
                        <div class="detail-value">
                            <?php if ($invoice['due'] == 0): ?>
                                <span class="status status-paid">Paid</span>
                            <?php elseif ($invoice['amount_paid'] == 0): ?>
                                <span class="status status-pending">Pending</span>
                            <?php else: ?>
                                <span class="status status-partial">Partial</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="invoice-details">
                <div>
                    <div class="detail-group">
                        <div class="detail-label">Patient Name</div>
                        <div class="detail-value"><?php echo htmlspecialchars($invoice['patient_name']); ?></div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Contact Number</div>
                        <div class="detail-value"><?php echo htmlspecialchars($invoice['patient_contact_no']); ?></div>
                    </div>
                </div>
            </div>

            <?php if (!empty($tests)): ?>
            <div class="invoice-section">
                <h3>Tests</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Test Name</th>
                            <th>Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tests as $test): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($test['test_name']); ?></td>
                            <td>৳<?php echo number_format($test['price'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if (!empty($consumables)): ?>
            <div class="invoice-section">
                <h3>Consumables</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Total Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($consumables as $consumable): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($consumable['name']); ?></td>
                            <td><?php echo $consumable['quantity']; ?></td>
                            <td>৳<?php echo number_format($consumable['unit_price'], 2); ?></td>
                            <td>৳<?php echo number_format($consumable['unit_price'] * $consumable['quantity'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <div class="total-section">
                <div class="total-group">
                    <div class="total-label">Total Amount</div>
                    <div class="total-value">৳<?php echo number_format($invoice['total_amount'], 2); ?></div>
                </div>
            </div>

            <div class="total-section">
                <div class="total-group">
                    <div class="total-label">Amount Paid</div>
                    <div class="total-value">৳<?php echo number_format($invoice['amount_paid'], 2); ?></div>
                </div>
            </div>

            <div class="total-section">
                <div class="total-group">
                    <div class="total-label">Due Amount</div>
                    <div class="total-value">৳<?php echo number_format($invoice['due'], 2); ?></div>
                </div>
            </div>
        </div>

        <div class="invoice-section">
            <?php if ($invoice['due'] > 0): ?>
                <a href="edit_invoice.php?id=<?php echo $invoice_id; ?>" class="btn btn-primary">Update Payment</a>
            <?php endif; ?>
            <a href="print_invoice.php?id=<?php echo $invoice_id; ?>" class="btn btn-primary" target="_blank">Print Invoice</a>
            <a href="view_invoice_list.php" class="btn btn-back">Back to List</a>
        </div>
    </div>
</body>
</html>
