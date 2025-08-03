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

// Include database connection
require_once '../../config/database.php';

try {
    $pdo = getDBConnection();
    
    // Get date range from query parameters
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    
    // Debug: Print the date range being used
    error_log("Report date range: $start_date to $end_date");
    
    // Get financial summary (updated to include discount_amount)
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_invoices,
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COALESCE(SUM(amount_paid), 0) as total_paid,
            COALESCE(SUM(due), 0) as total_due,
            COALESCE(SUM(discount_amount), 0) as total_discount
        FROM invoices 
        WHERE DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Debug: Print the summary results
    error_log("Financial summary: " . print_r($summary, true));
    
    // Get top tests
    $stmt = $pdo->prepare("
        SELECT 
            t.test_name,
            COUNT(*) as test_count,
            COALESCE(SUM(t.price), 0) as total_revenue
        FROM invoice_tests it
        JOIN tests_info t ON it.test_code = t.test_code
        JOIN invoices i ON it.invoice_id = i.id
        WHERE DATE(i.created_at) BETWEEN ? AND ?
        GROUP BY t.test_code, t.test_name
        ORDER BY test_count DESC
        LIMIT 5
    ");
    $stmt->execute([$start_date, $end_date]);
    $top_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Print the top tests results
    error_log("Top tests: " . print_r($top_tests, true));
    
    // Get top consumables
    $stmt = $pdo->prepare("
        SELECT 
            c.name,
            COUNT(*) as usage_count,
            COALESCE(SUM(c.price), 0) as total_revenue
        FROM invoice_consumables ic
        JOIN consumable_info c ON ic.consumable_code = c.consumable_code
        JOIN invoices i ON ic.invoice_id = i.id
        WHERE DATE(i.created_at) BETWEEN ? AND ?
        GROUP BY c.consumable_code, c.name
        ORDER BY usage_count DESC
        LIMIT 5
    ");
    $stmt->execute([$start_date, $end_date]);
    $top_consumables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Print the top consumables results
    error_log("Top consumables: " . print_r($top_consumables, true));
    
} catch (PDOException $e) {
    error_log("Database error in reports.php: " . $e->getMessage());
    $error = "An error occurred while generating reports. Please try again later.";
}

// Add a test query to check if there are any invoices at all
try {
    $testStmt = $pdo->query("SELECT COUNT(*) as total FROM invoices");
    $totalInvoices = $testStmt->fetch(PDO::FETCH_ASSOC)['total'];
    error_log("Total invoices in database: " . $totalInvoices);
} catch (PDOException $e) {
    error_log("Test query error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Niruponi Pathology Laboratory System</title>
    <link rel="stylesheet" href="../../assets/css/common.css">
    <style>
        .report-section {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .report-section h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-card h4 {
            margin: 0 0 10px 0;
            color: #666;
        }
        .stat-card .value {
            font-size: 24px;
            font-weight: bold;
            color: #3498db;
        }
        .stat-card .discount-value {
            font-size: 24px;
            font-weight: bold;
            color: #e74c3c; /* Red color for discount */
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .date-range-form {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 20px;
        }
        .date-range-form .form-group {
            margin: 0;
        }
        .date-range-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .date-range-form input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #3498db;
            color: white;
        }
        .btn-primary:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h2>Reports</h2>
            </div>
            <div>
                <a href="/NPL/pages/user/admin_dashboard.php" class="btn btn-back">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Back to Dashboard
                </a>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="report-section">
            <h3>Date Range</h3>
            <form method="GET" action="" class="date-range-form">
                <div class="form-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" required>
                </div>
                <div class="form-group">
                    <label for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" required>
                </div>
                <div class="form-group">
                    <label for="quick_range">Quick Range</label>
                    <select id="quick_range" class="form-control" onchange="setQuickRange(this.value)">
                        <option value="">Select Range</option>
                        <option value="1">Last 1 Day</option>
                        <option value="7">Last 7 Days</option>
                        <option value="30">Last 30 Days</option>
                        <option value="all">All Time</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Generate Report</button>
            </form>
        </div>

        <div class="report-section">
            <h3>Financial Summary</h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <h4>Total Invoices</h4>
                    <div class="value"><?php echo number_format($summary['total_invoices']); ?></div>
                </div>
                <div class="stat-card">
                    <h4>Total Revenue</h4>
                    <div class="value">৳<?php echo number_format($summary['total_revenue'], 2); ?></div>
                </div>
                <div class="stat-card">
                    <h4>Total Paid</h4>
                    <div class="value">৳<?php echo number_format($summary['total_paid'], 2); ?></div>
                </div>
                <div class="stat-card">
                    <h4>Total Due</h4>
                    <div class="value">৳<?php echo number_format($summary['total_due'], 2); ?></div>
                </div>
                <div class="stat-card">
                    <h4>Total Discount</h4>
                    <div class="discount-value">৳<?php echo number_format($summary['total_discount'], 2); ?></div>
                </div>
            </div>
        </div>

        <div class="report-section">
            <h3>Top Tests</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Test Name</th>
                        <th>Number of Tests</th>
                        <th>Total Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_tests as $test): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($test['test_name']); ?></td>
                        <td><?php echo number_format($test['test_count']); ?></td>
                        <td>৳<?php echo number_format($test['total_revenue'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="report-section">
            <h3>Top Consumables</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Consumable Name</th>
                        <th>Usage Count</th>
                        <th>Total Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_consumables as $consumable): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($consumable['name']); ?></td>
                        <td><?php echo number_format($consumable['usage_count']); ?></td>
                        <td>৳<?php echo number_format($consumable['total_revenue'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    function setQuickRange(range) {
        const endDate = new Date();
        const startDate = new Date();
        
        switch(range) {
            case '1':
                startDate.setDate(endDate.getDate() - 1);
                break;
            case '7':
                startDate.setDate(endDate.getDate() - 7);
                break;
            case '30':
                startDate.setDate(endDate.getDate() - 30);
                break;
            case 'all':
                startDate.setFullYear(2000); // Set to a very early date
                break;
            default:
                return;
        }
        
        document.getElementById('start_date').value = startDate.toISOString().split('T')[0];
        document.getElementById('end_date').value = endDate.toISOString().split('T')[0];
    }
    </script>
</body>
</html>