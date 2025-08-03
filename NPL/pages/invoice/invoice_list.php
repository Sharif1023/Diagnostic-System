<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit();
}

$dashboard_url = '/NPL/pages/user/staff_dashboard.php';
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $dashboard_url = '/NPL/pages/user/admin_dashboard.php';
}

require_once '../../config/database.php';
$pdo = getDBConnection();

$search_invoice = $_GET['search_invoice'] ?? '';
$search_patient = $_GET['search_patient'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

$query = "
    SELECT i.*, 
           COUNT(DISTINCT it.test_code) as test_count,
           COUNT(DISTINCT ic.consumable_code) as consumable_count
    FROM invoices i
    LEFT JOIN invoice_tests it ON i.id = it.invoice_id
    LEFT JOIN invoice_consumables ic ON i.id = ic.invoice_id
    WHERE 1=1
";

$count_query = "SELECT COUNT(DISTINCT i.id) as total FROM invoices i WHERE 1=1";
$params = [];
$count_params = [];

if (!empty($search_invoice)) {
    $query .= " AND i.invoice_no LIKE :search_invoice";
    $count_query .= " AND i.invoice_no LIKE :search_invoice";
    $params[':search_invoice'] = "%$search_invoice%";
    $count_params[':search_invoice'] = "%$search_invoice%";
}

if (!empty($search_patient)) {
    $query .= " AND i.patient_name LIKE :search_patient";
    $count_query .= " AND i.patient_name LIKE :search_patient";
    $params[':search_patient'] = "%$search_patient%";
    $count_params[':search_patient'] = "%$search_patient%";
}

if (!empty($start_date)) {
    $query .= " AND DATE(i.created_at) >= :start_date";
    $count_query .= " AND DATE(i.created_at) >= :start_date";
    $params[':start_date'] = $start_date;
    $count_params[':start_date'] = $start_date;
}

if (!empty($end_date)) {
    $query .= " AND DATE(i.created_at) <= :end_date";
    $count_query .= " AND DATE(i.created_at) <= :end_date";
    $params[':end_date'] = $end_date;
    $count_params[':end_date'] = $end_date;
}

// Get total records count
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($count_params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);

// Add grouping, ordering and pagination to main query
$query .= " GROUP BY i.id ORDER BY i.created_at DESC LIMIT :limit OFFSET :offset";
$params[':limit'] = $records_per_page;
$params[':offset'] = $offset;

// Fetch invoices with search conditions and pagination
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error fetching invoices: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Management - Laboratory Management System</title>
    <style>
        body { 
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f4f4f4;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .page-title h2 {
            margin: 0;
            color: #2c3e50;
            font-size: 28px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            white-space: nowrap;
            min-width: 100px;
        }
        .btn-primary {
            background: #3498db;
            color: white;
        }
        .btn-primary:hover {
            background: #2980b9;
        }
        .btn-view {
            background: #2ecc71;
            color: white;
        }
        .btn-view:hover {
            background: #27ae60;
        }
        .btn-edit {
            background: #f39c12;
            color: white;
        }
        .btn-edit:hover {
            background: #d35400;
        }
        .btn-delete {
            background: #e74c3c;
            color: white;
        }
        .btn-delete:hover {
            background: #c0392b;
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
        }.search-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .search-form {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .search-input {
            flex: 1;
            min-width: 200px;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .search-date {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            background: white;
        }
        .search-button {
            padding: 8px 16px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }
        .search-button:hover {
            background: #2980b9;
        }
        .clear-search {
            padding: 8px 16px;
            background: #95a5a6;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
        }
        .clear-search:hover {
            background: #7f8c8d;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .form-group label {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
        }
        .form-group input {
            padding: 12px 15px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        .search-btn, .reset-btn {
            padding: 12px 20px;
            font-size: 16px;
            min-width: 120px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .search-btn {
            background: #3498db;
            color: white;
        }
        .search-btn:hover {
            background: #2980b9;
        }
        .reset-btn {
            background: #95a5a6;
            color: white;
            text-decoration: none;
            text-align: center;
        }
        .reset-btn:hover {
            background: #7f8c8d;
        }
        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-top: 25px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
            font-size: 14px;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
            position: sticky;
            top: 0;
            z-index: 1;
            font-size: 15px;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .invoice-no {
            width: 120px;
            text-align: left;
        }
        .patient-name {
            width: 200px;
            text-align: left;
        }
        .date {
            width: 100px;
            text-align: center;
        }
        .tests {
            width: 80px;
            text-align: center;
        }
        .consumables {
            width: 100px;
            text-align: center;
        }
        .amount {
            width: 120px;
            text-align: right;
        }
        .status {
            width: 100px;
            text-align: center;
        }
        .actions {
            width: 200px;
            text-align: center;
        }
        .price-column {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: #2c3e50;
            text-align: right;
            padding-right: 15px;
            font-size: 14px;
        }
        .btn-group {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: nowrap;
        }
        .alert-message {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 16px;
            text-align: center;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .table-responsive {
            overflow-x: auto;
            margin: 0 -15px;
        }
        .status-paid, .status-pending, .status-partial {
            font-size: 13px;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 4px;
            display: inline-block;
            text-align: center;
            min-width: 80px;
        }
        .status-paid {
            background: #d4edda;
            color: #155724;
        }
        .status-pending {
            background: #f8d7da;
            color: #721c24;
        }
        .status-partial {
            background: #fff3cd;
            color: #856404;
        }
        .action-buttons {
            display: flex;
            gap: 5px;
            justify-content: center;
            align-items: center;
            flex-wrap: nowrap;
        }
        .action-buttons .btn {
            padding: 4px 8px;
            font-size: 12px;
            min-width: 50px;
        }
        .actions {
            width: 160px;
            text-align: center;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
            padding: 10px;
        }
        .pagination a {
            padding: 8px 12px;
            text-decoration: none;
            background: #3498db;
            color: white;
            border-radius: 4px;
            transition: background 0.3s;
        }
        .pagination a:hover {
            background: #2980b9;
        }
        .pagination .active {
            background: #2c3e50;
        }
        .pagination .disabled {
            background: #95a5a6;
            cursor: not-allowed;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
            position: sticky;
            top: 0;
            z-index: 1;
            font-size: 14px;
            white-space: nowrap;
        }
        td {
            font-size: 13px;
            white-space: nowrap;
        }
        @media (max-width: 1200px) {
            .container {
                padding: 0 15px;
            }
            .action-buttons {
                flex-direction: column;
                gap: 5px;
            }
            .action-buttons .btn {
                width: 100%;
            }
        }
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            .page-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            .btn-group {
                width: 100%;
                justify-content: center;
            }
            .search-form {
                flex-direction: column;
                align-items: stretch;
            }
            .search-input, .search-date {
                width: 100%;
            }
            .form-group input {
                width: 100%;
            }
            .search-btn, .reset-btn {
                width: 100%;
            }
            .table-responsive {
                margin: 0 -15px;
            }
            table {
                font-size: 13px;
            }
            th, td {
                padding: 8px 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h2>Admin Invoice Management</h2>
            </div>
            <div class="btn-group">
                <a href="<?php echo $dashboard_url; ?>" class="btn btn-back">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Go Back to Dashboard
                </a>
                <a href="add_invoice.php" class="btn btn-primary">New Invoice</a>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert-message alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert-message alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Search Form -->
        <div class="search-container">
            <form class="search-form" method="GET" action="">
                <input type="text" 
                       name="search_invoice" 
                       class="search-input" 
                       placeholder="Search by invoice number..." 
                       value="<?php echo htmlspecialchars($search_invoice); ?>">
                <input type="text" 
                       name="search_patient" 
                       class="search-input" 
                       placeholder="Search by patient name..." 
                       value="<?php echo htmlspecialchars($search_patient); ?>">
                <input type="date" 
                       name="start_date" 
                       class="search-date" 
                       value="<?php echo htmlspecialchars($start_date); ?>"
                       placeholder="Start Date">
                <input type="date" 
                       name="end_date" 
                       class="search-date" 
                       value="<?php echo htmlspecialchars($end_date); ?>"
                       placeholder="End Date">
                <button type="submit" class="search-button">Search</button>
                <?php if (!empty($search_invoice) || !empty($search_patient) || !empty($start_date) || !empty($end_date)): ?>
                    <a href="view_invoice_list.php" class="clear-search">Clear Search</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-responsive">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th class="invoice-no">Invoice No</th>
                            <th class="patient-name">Patient Name</th>
                            <th class="date">Date</th>
                            <th class="tests">Tests</th>
                            <th class="consumables">Consumables</th>
                            <th class="amount">Total Amount</th>
                            <th class="amount">Amount Paid</th>
                            <th class="amount">Due</th>
                            <th class="status">Status</th>
                            <th class="actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td class="invoice-no"><?php echo htmlspecialchars($invoice['invoice_no']); ?></td>
                            <td class="patient-name"><?php echo htmlspecialchars($invoice['patient_name']); ?></td>
                            <td class="date"><?php echo date('d M Y', strtotime($invoice['created_at'])); ?></td>
                            <td class="tests"><?php echo $invoice['test_count']; ?></td>
                            <td class="consumables"><?php echo $invoice['consumable_count']; ?></td>
                            <td class="amount price-column">৳<?php echo number_format($invoice['total_amount'], 2); ?></td>
                            <td class="amount price-column">৳<?php echo number_format($invoice['amount_paid'], 2); ?></td>
                            <td class="amount price-column">৳<?php echo number_format($invoice['due'], 2); ?></td>
                            <td class="status">
                                <?php if ($invoice['due'] == 0): ?>
                                    <span class="status status-paid">Paid</span>
                                <?php elseif ($invoice['amount_paid'] == 0): ?>
                                    <span class="status status-pending">Pending</span>
                                <?php else: ?>
                                    <span class="status status-partial">Partial</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <div class="action-buttons">
                                    <a href="view_invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-view">View</a>
                                    <a href="print_invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-primary" target="_blank">Print</a>
                                    <?php if ($invoice['due'] > 0): ?>
                                        <a href="edit_invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-edit">Edit</a>
                                    <?php endif; ?>
                                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                        <a href="delete_invoice.php?id=<?php echo $invoice['id']; ?>" 
                                           class="btn btn-delete"
                                           onclick="return confirm('Are you sure you want to delete this invoice? This action cannot be undone.');">
                                            Delete
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?><?php 
                    echo !empty($search_invoice) ? '&search_invoice=' . urlencode($search_invoice) : ''; 
                    echo !empty($search_patient) ? '&search_patient=' . urlencode($search_patient) : ''; 
                    echo !empty($start_date) ? '&start_date=' . urlencode($start_date) : ''; 
                    echo !empty($end_date) ? '&end_date=' . urlencode($end_date) : ''; 
                ?>">&laquo; Previous</a>
            <?php else: ?>
                <a class="disabled">&laquo; Previous</a>
            <?php endif; ?>

            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);

            if ($start_page > 1) {
                echo '<a href="?page=1' . 
                    (!empty($search_invoice) ? '&search_invoice=' . urlencode($search_invoice) : '') . 
                    (!empty($search_patient) ? '&search_patient=' . urlencode($search_patient) : '') . 
                    (!empty($start_date) ? '&start_date=' . urlencode($start_date) : '') . 
                    (!empty($end_date) ? '&end_date=' . urlencode($end_date) : '') . 
                    '">1</a>';
                if ($start_page > 2) {
                    echo '<span>...</span>';
                }
            }

            for ($i = $start_page; $i <= $end_page; $i++) {
                if ($i == $page) {
                    echo '<a class="active">' . $i . '</a>';
                } else {
                    echo '<a href="?page=' . $i . 
                        (!empty($search_invoice) ? '&search_invoice=' . urlencode($search_invoice) : '') . 
                        (!empty($search_patient) ? '&search_patient=' . urlencode($search_patient) : '') . 
                        (!empty($start_date) ? '&start_date=' . urlencode($start_date) : '') . 
                        (!empty($end_date) ? '&end_date=' . urlencode($end_date) : '') . 
                        '">' . $i . '</a>';
                }
            }

            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) {
                    echo '<span>...</span>';
                }
                echo '<a href="?page=' . $total_pages . 
                    (!empty($search_invoice) ? '&search_invoice=' . urlencode($search_invoice) : '') . 
                    (!empty($search_patient) ? '&search_patient=' . urlencode($search_patient) : '') . 
                    (!empty($start_date) ? '&start_date=' . urlencode($start_date) : '') . 
                    (!empty($end_date) ? '&end_date=' . urlencode($end_date) : '') . 
                    '">' . $total_pages . '</a>';
            }
            ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?><?php 
                    echo !empty($search_invoice) ? '&search_invoice=' . urlencode($search_invoice) : ''; 
                    echo !empty($search_patient) ? '&search_patient=' . urlencode($search_patient) : ''; 
                    echo !empty($start_date) ? '&start_date=' . urlencode($start_date) : ''; 
                    echo !empty($end_date) ? '&end_date=' . urlencode($end_date) : ''; 
                ?>">Next &raquo;</a>
            <?php else: ?>
                <a class="disabled">Next &raquo;</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
