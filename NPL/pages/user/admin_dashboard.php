<?php
require_once '../../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /index.php');
    exit();
}

// Include header
include '../../includes/header.php';

// Get dashboard statistics
try {
    $pdo = getDBConnection();
    
    // Get counts
    $stats = [
        'users' => $pdo->query("SELECT COUNT(*) as total FROM users")->fetch()['total'],
        'invoices' => $pdo->query("SELECT COUNT(*) as total FROM invoices")->fetch()['total'],
        'reports' => $pdo->query("SELECT COUNT(*) as total FROM test_reports")->fetch()['total'],
        'tests' => $pdo->query("SELECT COUNT(*) as total FROM tests_info")->fetch()['total']
    ];
    
    // Get recent invoices
    $recentInvoices = $pdo->query("
        SELECT i.*, 
               COUNT(DISTINCT it.test_code) as test_count,
               COUNT(DISTINCT ic.consumable_code) as consumable_count
        FROM invoices i
        LEFT JOIN invoice_tests it ON i.id = it.invoice_id
        LEFT JOIN invoice_consumables ic ON i.id = ic.invoice_id
        GROUP BY i.id
        ORDER BY i.created_at DESC
        LIMIT 5
    ")->fetchAll();
} catch(PDOException $e) {
    $error = "Error loading dashboard data";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Niruponi Pathology Laboratory System</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .dashboard-layout {
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
        .sidebar h2 {
            padding: 0 20px;
            margin-bottom: 20px;
            font-size: 1.5rem;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            padding: 10px 20px;
            background: #34495e;
            font-weight: 600;
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar ul li {
            padding: 0;
        }
        .sidebar ul li a {
            display: block;
            padding: 10px 20px;
            color: #ecf0f1;
            text-decoration: none;
            transition: background 0.3s;
        }
        .sidebar ul li a:hover {
            background: #34495e;
        }
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <div class="sidebar">
           
            <div class="section">
                <div class="section-title">Dashboard</div>
                <ul>
                    <li><a href="../user/admin_dashboard.php">Dashboard</a></li>
                </ul>
            </div>
            <div class="section">
                <div class="section-title">Invoice Section</div>
                <ul>
                    <li><a href="../invoice/new_invoice.php">Create New Invoice</a></li>
                    <li><a href="../invoice/invoice_list.php">View Invoice List</a></li>
                    <li><a href="../invoice/pay_bill.php">Pay Bill</a></li>
                </ul>
            </div>
            <div class="section">
                <div class="section-title">Report Section</div>
                <ul>
                    <li><a href="../report/new_report.php">Create New Report</a></li>
                    <li><a href="../report/view_report_list.php">View Report List</a></li>
                </ul>
            </div>
            <div class="section">
                <div class="section-title">Administration</div>
                <ul>
                    <li><a href="../admin/manage_users.php">Manage Users</a></li>
                    <li><a href="../admin/manage_tests.php">Manage Tests</a></li>
                    <li><a href="../admin/manage_doctors.php">Manage Doctors</a></li>
                    <li><a href="../admin/manage_consumables.php">Manage Consumables</a></li>
                    <li><a href="../admin/reports.php">System Reports</a></li>
                </ul>
            </div>
            <div class="section">
                <div class="section-title">Account</div>
                <ul>
                    <li><a href="../../logout.php">Logout</a></li>
                </ul>
            </div>
        </div>

        <div class="main-content">
            <div class="container">
                <h1>Admin Dashboard</h1>

                <?php if (isset($error)): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="dashboard-stats">
                    <div class="stat-card">
                        <h3>Total Users</h3>
                        <div class="number"><?php echo htmlspecialchars($stats['users']); ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Total Invoices</h3>
                        <div class="number"><?php echo htmlspecialchars($stats['invoices']); ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Total Reports</h3>
                        <div class="number"><?php echo htmlspecialchars($stats['reports']); ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Total Tests</h3>
                        <div class="number"><?php echo htmlspecialchars($stats['tests']); ?></div>
                    </div>
                </div>

                <div class="recent-items">
                    <h2>Recent Invoices</h2>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Invoice Number</th>
                                <th>Patient Name</th>
                                <th>Date</th>
                                <th>Total Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentInvoices as $invoice): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($invoice['invoice_no']); ?></td>
                                <td><?php echo htmlspecialchars($invoice['patient_name']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($invoice['created_at'])); ?></td>
                                <td>৳<?php echo number_format($invoice['total_amount'], 2); ?></td>
                                <td>
                                    <?php if ($invoice['total_amount'] - $invoice['amount_paid'] <= 0): ?>
                                        <span class="status-badge status-paid">Paid</span>
                                    <?php else: ?>
                                        <span class="status-badge status-pending">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="../invoice/view_invoice.php?id=<?php echo $invoice['id']; ?>" class="action-btn">View</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>Quick Actions</h3>
                    </div>
                    <div class="grid grid-3">
                        <a href="../admin/manage_users.php" class="btn btn-primary">Manage Users</a>
                        <a href="../admin/manage_tests.php" class="btn btn-primary">Manage Tests</a>
                        <a href="../admin/manage_doctors.php" class="btn btn-primary">Manage Doctors</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

<?php
// Include footer
include '../includes/footer.php';
?>
