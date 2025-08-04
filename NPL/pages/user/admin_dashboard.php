<?php
require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /index.php');
    exit();
}
include '../../includes/header.php';

try {
    $pdo = getDBConnection();
    $stats = [
        'users' => $pdo->query("SELECT COUNT(*) as total FROM users")->fetch()['total'],
        'invoices' => $pdo->query("SELECT COUNT(*) as total FROM invoices")->fetch()['total'],
        'reports' => $pdo->query("SELECT COUNT(*) as total FROM test_reports")->fetch()['total'],
        'tests' => $pdo->query("SELECT COUNT(*) as total FROM tests_info")->fetch()['total']
    ];
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
    <title>Admin Dashboard - Niruponi Pathology Lab</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f4f6f8;
            margin: 0;
        }
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
            font-size: 1.5rem;
        }
        .section-title {
            padding: 10px 20px;
            background: #34495e;
            font-weight: bold;
        }
        .sidebar ul {
            list-style: none;
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
            background: #3c5d7d;
        }
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 30px;
        }

        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
        }
        .stat-card h3 {
            margin: 0;
            font-size: 1.2rem;
            color: #34495e;
        }
        .stat-card .number {
            font-size: 2rem;
            font-weight: bold;
            color: #2980b9;
            margin-top: 10px;
        }

        .recent-items h2 {
            margin-top: 40px;
            font-size: 1.5rem;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            margin-top: 15px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07);
        }
        .data-table th, .data-table td {
            padding: 12px 15px;
            text-align: left;
        }
        .data-table th {
            background: #2c3e50;
            color: white;
        }
        .data-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            color: white;
        }
        .status-paid {
            background-color: #27ae60;
        }
        .status-pending {
            background-color: #e67e22;
        }
        .action-btn {
            padding: 6px 12px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .card {
            margin-top: 40px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .card-header h3 {
            margin: 0 0 15px;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .btn-primary {
            display: block;
            text-align: center;
            padding: 12px;
            background: #2980b9;
            color: white;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: background 0.3s;
        }
        .btn-primary:hover {
            background: #1f6391;
        }

        .error {
            background-color: #e74c3c;
            color: white;
            padding: 10px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <div class="sidebar">
            <div class="section">
                <div class="section-title">Dashboard</div>
                <ul><li><a href="../user/admin_dashboard.php">Overview</a></li></ul>
            </div>
            <div class="section">
                <div class="section-title">Invoice Section</div>
                <ul>
                    <li><a href="../invoice/new_invoice.php">Create Invoice</a></li>
                    <li><a href="../invoice/invoice_list.php">Invoice List</a></li>
                    <li><a href="../invoice/pay_bill.php">Pay Bill</a></li>
                </ul>
            </div>
            <div class="section">
                <div class="section-title">Reports</div>
                <ul>
                    <li><a href="../report/new_report.php">New Report</a></li>
                    <li><a href="../report/view_report_list.php">Report List</a></li>
                </ul>
            </div>
            <div class="section">
                <div class="section-title">Admin Settings</div>
                <ul>
                    <li><a href="../admin/manage_users.php">Users</a></li>
                    <li><a href="../admin/manage_tests.php">Tests</a></li>
                    <li><a href="../admin/manage_doctors.php">Doctors</a></li>
                    <li><a href="../admin/manage_consumables.php">Consumables</a></li>
                    <li><a href="../admin/reports.php">System Reports</a></li>
                </ul>
            </div>
            <div class="section">
                <div class="section-title">Account</div>
                <ul><li><a href="../../logout.php">Logout</a></li></ul>
            </div>
        </div>

        <div class="main-content">
            <h1>Admin Dashboard</h1>

            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="dashboard-stats">
                <div class="stat-card"><h3>Total Users</h3><div class="number"><?php echo $stats['users']; ?></div></div>
                <div class="stat-card"><h3>Total Invoices</h3><div class="number"><?php echo $stats['invoices']; ?></div></div>
                <div class="stat-card"><h3>Total Reports</h3><div class="number"><?php echo $stats['reports']; ?></div></div>
                <div class="stat-card"><h3>Total Tests</h3><div class="number"><?php echo $stats['tests']; ?></div></div>
            </div>

            <div class="recent-items">
                <h2>Recent Invoices</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Patient</th>
                            <th>Date</th>
                            <th>Amount</th>
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
                            <td><a class="action-btn" href="../invoice/view_invoice.php?id=<?php echo $invoice['id']; ?>">View</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <div class="card-header"><h3>Quick Actions</h3></div>
                <div class="grid-3">
                    <a href="../admin/manage_users.php" class="btn btn-primary">Users</a>
                    <a href="../admin/manage_tests.php" class="btn btn-primary">Tests</a>
                    <a href="../admin/manage_doctors.php" class="btn btn-primary">Doctors</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php include '../includes/footer.php'; ?>
