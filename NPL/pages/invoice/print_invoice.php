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
    $stmt = $pdo->prepare("
        SELECT i.*, d.name as doctor_name
        FROM invoices i
        LEFT JOIN doctors d ON i.referred_by = d.id
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
    <title>Print Invoice - Laboratory Management System</title>
    <link rel="stylesheet" href="/assets/css/common.css">
    <style>
        @media print {
            @page {
                size: A5;
                margin: 0;
            }
            body {
                margin: 0;
                padding: 0;
                font-size: 8pt;
                line-height: 1.1;
            }
            .a5-page {
                width: 148mm;
                height: 210mm;
                margin: 0 auto;
                padding: 0;
                box-sizing: border-box;
                page-break-after: avoid;
            }
            .top-margin {
                height: 35mm;
                width: 100%;
            }
            .content-area {
                width: calc(100% - 20mm); /* 100% - (10mm left + 10mm right) */
                height: calc(210mm - 60mm); /* 210mm - (35mm top + 25mm bottom) */
                margin: 0 auto;
                padding: 0;
                overflow: hidden;
            }
            .content-wrapper {
                width: 100%;
                max-width: 128mm; /* A5 width (148mm) - 20mm padding */
                margin: 0 auto;
            }
            .bottom-margin {
                height: 25mm;
                width: 100%;
            }
            .center {
                text-align: center;
            }
            .right {
                text-align: right;
            }
            .left {
                text-align: left;
            }
            .bold {
                font-weight: bold;
            }
            .title {
                font-size: 10pt;
                margin-bottom: 2px;
            }
            .divider {
                border-top: 1px solid #000;
                margin: 1px 0;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 1px 0;
                font-size: 8pt;
            }
            th, td {
                padding: 1px;
                text-align: left;
                border: none;
                font-size: 8pt;
            }
            .test-code {
                width: 12%;
                font-family: 'Courier New', monospace;
                padding-right: 15px;
            }
            .test-name {
                width: 45%;
                padding-left: 5px;
            }
            .test-qty {
                width: 8%;
                text-align: center;
            }
            .test-price {
                width: 15%;
                text-align: right;
                font-family: 'Courier New', monospace;
            }
            .patient-info {
                margin-bottom: 2px;
            }
            .total-section {
                margin-top: 2px;
            }
            .total-section div {
                margin-bottom: 1px;
            }
        }
    </style>
</head>
<body>
    <?php 
    $copies = ['Customer Copy', 'Lab Copy'];
    foreach ($copies as $copy_title): 
    ?>
    <div class="a5-page">
        <div class="top-margin"></div>
        <div class="content-area">
            <div class="content-wrapper">
                <div class="center bold title"><?php echo $copy_title; ?></div>
                <div class="center bold">Date: <?php echo date('d/m/Y', strtotime($invoice['created_at'])); ?></div>
                <div style="height: 5px;"></div>

                <div>
                    <span>Invoice No: <?php echo htmlspecialchars($invoice['invoice_no']); ?></span>
                </div>
                <div>
                    <span>Name: <?php echo htmlspecialchars($invoice['patient_name']); ?></span>
                    <span style="float: right;">Age: <?php echo htmlspecialchars($invoice['patient_age']); ?></span>
                </div>
                <div>
                    <span>Sex: <?php echo htmlspecialchars($invoice['patient_gender']); ?></span>
                    <span style="float: right;">Contact No: <?php echo htmlspecialchars($invoice['patient_contact_no']); ?></span>
                </div>
                <div>
                    <span>Ref By: <?php echo htmlspecialchars($invoice['doctor_name'] ?? $invoice['referred_by'] ?? 'N/A'); ?></span>
                </div>
                <div class="divider"></div>

                <table>
                    <tr>
                        <th class="test-code">Code</th>
                        <th class="test-name">Test Name</th>
                        <th class="test-qty">Quantity</th>
                        <th class="test-price">Price (Tk.)</th>
                    </tr>
                    <tr><td colspan="4" class="divider"></td></tr>
                    <?php foreach ($tests as $test): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($test['test_code']); ?></td>
                        <td><?php echo htmlspecialchars($test['test_name']); ?></td>
                        <td class="test-qty"><?php echo $test['quantity']; ?></td>
                        <td class="test-price"><?php echo number_format($test['unit_price'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php foreach ($consumables as $consumable): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($consumable['consumable_code']); ?></td>
                        <td><?php echo htmlspecialchars($consumable['name']); ?></td>
                        <td class="test-qty"><?php echo $consumable['quantity']; ?></td>
                        <td class="test-price"><?php echo number_format($consumable['unit_price'] * $consumable['quantity'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr><td colspan="4" class="divider"></td></tr>
                </table>

                <div class="left">
                    <div>Delivery Date: <?php echo date('d/m/Y', strtotime($invoice['delivery_date'])); ?></div>
                    <div style="height: 4px;"></div>
                    <div style="font-size: 12px; font-weight: bold;">রিপোর্ট ডেলিভারি সন্ধ্যা ৬টা</div>
                </div>
                <div style="height: 4px;"></div>

                <div class="right">
                    <div>Net Total Tk <?php echo number_format($invoice['total_amount'], 2); ?></div>
                    <div>(-) Discount Tk <?php echo number_format($invoice['discount_amount'], 2); ?></div>
                    <div>Net Payable Tk <?php echo number_format($invoice['total_amount'] - $invoice['discount_amount'], 2); ?></div>
                    <div>Total Payment Tk <?php echo number_format($invoice['amount_paid'], 2); ?></div>
                    <div class="divider"></div>
                    <div>Due Tk <?php echo number_format($invoice['due'], 2); ?></div>
                </div>
            </div>
        </div>
        <div class="bottom-margin"></div>
    </div>
    <?php endforeach; ?>

    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html> 