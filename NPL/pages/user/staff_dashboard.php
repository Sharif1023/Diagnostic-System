<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: /NPL/index.php');
    exit();
}

// Include database configuration
require_once '../../config/database.php';

// Get database connection
$pdo = getDBConnection();

// Fetch today's statistics
try {
    $today = date('Y-m-d');
    
    // Today's invoices
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count, 
               SUM(total_amount) as total_amount,
               SUM(amount_paid) as amount_paid,
               SUM(total_amount - amount_paid - discount_amount) as total_due
        FROM invoices 
        WHERE DATE(created_at) = ?
    ");
    $stmt->execute([$today]);
    $today_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Today's reports
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM test_reports 
        WHERE DATE(created_at) = ?
    ");
    $stmt->execute([$today]);
    $today_reports = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Pending reports
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM invoices i
        JOIN invoice_tests it ON i.id = it.invoice_id
        LEFT JOIN test_reports tr ON i.id = tr.invoice_id AND it.test_code = tr.test_code
        WHERE tr.id IS NULL
    ");
    $pending_reports = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Completed reports
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM test_reports 
        WHERE report_status = 'completed'
    ");
    $completed_reports = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Error fetching statistics: " . $e->getMessage();
    $today_stats = ['count' => 0, 'total_amount' => 0, 'amount_paid' => 0, 'total_due' => 0];
    $today_reports = ['count' => 0];
    $pending_reports = ['count' => 0];
    $completed_reports = ['count' => 0];
}

// Fetch recent invoices
try {
    $stmt = $pdo->prepare("
        SELECT i.*, 
               COUNT(DISTINCT it.test_code) as test_count,
               COUNT(DISTINCT ic.consumable_code) as consumable_count,
               CASE 
                   WHEN i.amount_paid >= i.total_amount THEN 'paid'
                   WHEN i.amount_paid > 0 THEN 'partial'
                   ELSE 'unpaid'
               END as status
        FROM invoices i
        LEFT JOIN invoice_tests it ON i.id = it.invoice_id
        LEFT JOIN invoice_consumables ic ON i.id = ic.invoice_id
        GROUP BY i.id
        ORDER BY i.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Error fetching recent invoices: " . $e->getMessage();
    $recent_invoices = [];
}

// Fetch recent reports
try {
    $stmt = $pdo->prepare("
        SELECT r.*, 
               COUNT(DISTINCT tr.id) as test_count,
               i.patient_name,
               t.test_name,
               r.report_status
        FROM test_reports r
        LEFT JOIN test_results tr ON r.id = tr.report_id
        LEFT JOIN invoices i ON r.invoice_id = i.id
        LEFT JOIN tests_info t ON r.test_code = t.test_code
        GROUP BY r.id
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Error fetching recent reports: " . $e->getMessage();
    $recent_reports = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Laboratory Management System</title>
    <link rel="stylesheet" href="../../assets/css/common.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        body {
            background: #f4f6f8;
        }
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 250px;
            background: #2c3e50;
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        .sidebar-header h2 {
            margin: 0;
            font-size: 18px;
            color: white;
        }
        .nav-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .nav-item {
            margin-bottom: 5px;
        }
        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
        }
        .nav-link:hover,
        .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .nav-link svg {
            margin-right: 10px;
            width: 18px;
            height: 18px;
        }
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 40px 32px 32px 32px;
            min-height: 100vh;
        }
        .dashboard-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 32px;
            flex-wrap: wrap;
            gap: 20px;
        }
        .dashboard-header .welcome {
            font-size: 1.7rem;
            font-weight: 600;
            color: #2c3e50;
        }
        /* Quick Actions - two similar cards side by side */
        .quick-actions-row {
            display: flex;
            gap: 24px;
            margin-bottom: 36px;
            flex-wrap: wrap;
        }
        .quick-actions-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            padding: 18px 20px 16px 20px;
            min-width: 260px;
            flex: 1 1 260px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        .quick-actions-card h3 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .quick-actions-card .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .quick-actions-card .btn {
            padding: 7px 14px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .stats-row {
            display: flex;
            gap: 24px;
            margin-bottom: 36px;
            flex-wrap: wrap;
        }
        .stat-card {
            background: white;
            padding: 22px 20px 18px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            min-width: 220px;
            flex: 1 1 220px;
        }
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            font-size: 15px;
            font-weight: 500;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #3498db;
            margin: 0 0 5px 0;
        }
        .stat-label {
            font-size: 13px;
            color: #7f8c8d;
            margin: 2px 0 0 0;
        }
        .data-row {
            display: flex;
            gap: 28px;
            margin-top: 0;
            flex-wrap: wrap;
        }
        .recent-invoices,
        .recent-reports {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            flex: 1 1 350px;
            min-width: 320px;
        }
        .recent-invoices h3,
        .recent-reports h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #2c3e50;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-badge.pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-badge.completed {
            background-color: #d4edda;
            color: #155724;
        }
        .status-badge.paid {
            background-color: #d4edda;
            color: #155724;
        }
        .status-badge.unpaid {
            background-color: #f8d7da;
            color: #721c24;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
            border: none;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        @media (max-width: 1100px) {
            .dashboard-header, .stats-row, .data-row, .quick-actions-row {
                flex-direction: column;
                gap: 18px;
            }
            .quick-actions-card, .recent-invoices, .recent-reports {
                min-width: 0;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Niruponi Pathology</h2>
            </div>
            <nav>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="staff_dashboard.php" class="nav-link active">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="7" height="7"></rect>
                                <rect x="14" y="3" width="7" height="7"></rect>
                                <rect x="14" y="14" width="7" height="7"></rect>
                                <rect x="3" y="14" width="7" height="7"></rect>
                            </svg>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../report/new_report.php" class="nav-link">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 5v14M5 12h14"/>
                            </svg>
                            New Report
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../report/view_report_list.php" class="nav-link">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                            </svg>
                            View Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../invoice/new_invoice.php" class="nav-link">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                                <polyline points="10 9 9 9 8 9"/>
                            </svg>
                            New Invoice
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../invoice/view_invoice_list.php" class="nav-link">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                            </svg>
                            View Invoices
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../../logout.php" class="nav-link">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                <polyline points="16 17 21 12 16 7"/>
                                <line x1="21" y1="12" x2="9" y2="12"/>
                            </svg>
                            Logout
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="dashboard-header">
                <div class="welcome">
                    Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>
                </div>
            </div>
            <div class="quick-actions-row">
                <div class="quick-actions-card">
                    <h3>Invoice Actions</h3>
                    <div class="action-buttons">
                        <a href="../invoice/new_invoice.php" class="btn btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                                <polyline points="10 9 9 9 8 9"/>
                            </svg>
                            New Invoice
                        </a>
                        <a href="../invoice/view_invoice_list.php" class="btn btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                            </svg>
                            View Invoices
                        </a>
                    </div>
                </div>
                <div class="quick-actions-card">
                    <h3>Report Actions</h3>
                    <div class="action-buttons">
                        <a href="../report/new_report.php" class="btn btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 5v14M5 12h14"/>
                            </svg>
                            New Report
                        </a>
                        <a href="../report/view_report_list.php" class="btn btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                            </svg>
                            View Reports
                        </a>
                    </div>
                </div>
            </div>
            <?php if (isset($error)): ?>
                <div class="message error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="stats-row">
                <div class="stat-card">
                    <h3>Today's Invoices</h3>
                    <p class="stat-number"><?php echo htmlspecialchars($today_stats['count']); ?></p>
                    <p class="stat-label">Total Amount: <?php echo number_format($today_stats['total_amount'], 2); ?></p>
                    <p class="stat-label">Amount Paid: <?php echo number_format($today_stats['amount_paid'], 2); ?></p>
                    <p class="stat-label">Total Due: <?php echo number_format($today_stats['total_due'], 2); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Today's Reports</h3>
                    <p class="stat-number"><?php echo htmlspecialchars($today_reports['count']); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Pending Reports</h3>
                    <p class="stat-number"><?php echo htmlspecialchars($pending_reports['count']); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Completed Reports</h3>
                    <p class="stat-number"><?php echo htmlspecialchars($completed_reports['count']); ?></p>
                </div>
            </div>

            <div class="data-row">
                <div class="recent-invoices">
                    <h3>Recent Invoices</h3>
                    <?php if (!empty($recent_invoices)): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Invoice No</th>
                                    <th>Patient Name</th>
                                    <th>Total Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_invoices as $invoice): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($invoice['invoice_no']); ?></td>
                                        <td><?php echo htmlspecialchars($invoice['patient_name']); ?></td>
                                        <td><?php echo number_format($invoice['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower($invoice['status'] ?? 'unpaid'); ?>">
                                                <?php echo ucfirst($invoice['status'] ?? 'Unpaid'); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No recent invoices found.</p>
                    <?php endif; ?>
                </div>
                <div class="recent-reports">
                    <h3>Recent Reports</h3>
                    <?php if (!empty($recent_reports)): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Report ID</th>
                                    <th>Patient Name</th>
                                    <th>Test Name</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_reports as $report): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($report['id']); ?></td>
                                        <td><?php echo htmlspecialchars($report['patient_name']); ?></td>
                                        <td><?php echo htmlspecialchars($report['test_name']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower($report['report_status'] ?? 'pending'); ?>">
                                                <?php echo ucfirst($report['report_status'] ?? 'Pending'); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No recent reports found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

<?php
// Include footer
include '../includes/footer.php';
?>
