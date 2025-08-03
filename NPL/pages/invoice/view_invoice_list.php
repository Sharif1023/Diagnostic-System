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

// Include database configuration
require_once '../../config/database.php';

// Get database connection
$pdo = getDBConnection();

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Get search parameters
$search_invoice = $_GET['search_invoice'] ?? '';
$search_patient = $_GET['search_patient'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Build the query with search conditions
$query = "
    SELECT i.*, 
           COUNT(DISTINCT it.test_code) as test_count,
           COUNT(DISTINCT ic.consumable_code) as consumable_count
    FROM invoices i
    LEFT JOIN invoice_tests it ON i.id = it.invoice_id
    LEFT JOIN invoice_consumables ic ON i.id = ic.invoice_id
    WHERE 1=1
";

$params = [];

if (!empty($search_invoice)) {
    $query .= " AND i.invoice_no LIKE ?";
    $params[] = "%$search_invoice%";
}

if (!empty($search_patient)) {
    $query .= " AND i.patient_name LIKE ?";
    $params[] = "%$search_patient%";
}

if (!empty($start_date)) {
    $query .= " AND DATE(i.created_at) >= ?";
    $params[] = $start_date;
}

if (!empty($end_date)) {
    $query .= " AND DATE(i.created_at) <= ?";
    $params[] = $end_date;
}

// Get total records count (improved version)
$count_query = "SELECT COUNT(DISTINCT i.id) FROM invoices i WHERE 1=1";
$count_params = [];

if (!empty($search_invoice)) {
    $count_query .= " AND i.invoice_no LIKE ?";
    $count_params[] = "%$search_invoice%";
}

if (!empty($search_patient)) {
    $count_query .= " AND i.patient_name LIKE ?";
    $count_params[] = "%$search_patient%";
}

if (!empty($start_date)) {
    $count_query .= " AND DATE(i.created_at) >= ?";
    $count_params[] = $start_date;
}

if (!empty($end_date)) {
    $count_query .= " AND DATE(i.created_at) <= ?";
    $count_params[] = $end_date;
}

$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($count_params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);

// Add pagination to main query (fixed part)
$query .= " GROUP BY i.id ORDER BY i.created_at DESC LIMIT $records_per_page OFFSET $offset";

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
    <title>Invoice List - Laboratory Management System</title>
    <style>
        body { 
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f4f4f4;
            margin: 0;
            padding: 15px;
            color: #333;
        }
        .container {
            max-width: 90%;
            margin: 0 auto;
            padding: 0 15px;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .page-title {
            flex: 1;
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
        }
        .btn-primary {
            background: #3498db;
            color: white;
            border: none;
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
        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        table {
            width: 100%;
            border-collapse: collapse;
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
        tr:hover {
            background: #f8f9fa;
        }
        .price-column {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: #2c3e50;
        }
        .search-container {
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
        @media (max-width: 768px) {
            .search-form {
                flex-direction: column;
                align-items: stretch;
            }
            .search-input, .search-date {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h2>Invoice List</h2>
            </div>
            <div>
                <a href="<?php echo $dashboard_url; ?>" class="btn btn-back">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Go Back to Dashboard
                </a>
                <a href="new_invoice.php" class="btn btn-primary">Create New Invoice</a>
            </div>
        </div>

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

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Invoice Number</th>
                        <th>Patient Name</th>
                        <th>Date</th>
                        <th>Total Amount</th>
                        <th>Amount Paid</th>
                        <th>Due Amount</th>
                        <th>Tests</th>
                        <th>Consumables</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center;">No invoices found</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($invoices as $invoice): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($invoice['invoice_no']); ?></td>
                        <td><?php echo htmlspecialchars($invoice['patient_name']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($invoice['created_at'])); ?></td>
                        <td class="price-column">৳<?php echo number_format($invoice['total_amount'], 2); ?></td>
                        <td class="price-column">৳<?php echo number_format($invoice['amount_paid'], 2); ?></td>
                        <td class="price-column">৳<?php echo number_format($invoice['total_amount'] - $invoice['amount_paid'], 2); ?></td>
                        <td><?php echo $invoice['test_count']; ?></td>
                        <td><?php echo $invoice['consumable_count']; ?></td>
                        <td>
                            <a href="view_invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-view">View</a>
                            <a href="print_invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-primary" target="_blank">Print</a>
                            <?php if ($invoice['total_amount'] - $invoice['amount_paid'] > 0): ?>
                                <a href="edit_invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-primary">Pay</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
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

