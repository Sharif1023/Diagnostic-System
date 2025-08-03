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

// Search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_type = isset($_GET['search_type']) ? $_GET['search_type'] : 'all';

// Get total number of records with search
try {
    $count_sql = "SELECT COUNT(DISTINCT r.id) 
                  FROM test_reports r
                  LEFT JOIN doctors d ON r.doctor_id = d.id
                  LEFT JOIN invoices i ON r.invoice_id = i.id
                  LEFT JOIN tests_info t ON r.test_code = t.test_code";
    
    if (!empty($search)) {
        $count_sql .= " WHERE ";
        switch($search_type) {
            case 'invoice':
                $count_sql .= "i.invoice_no LIKE :search1";
                break;
            case 'patient':
                $count_sql .= "i.patient_name LIKE :search1";
                break;
            case 'doctor':
                $count_sql .= "d.name LIKE :search1";
                break;
            case 'test':
                $count_sql .= "t.test_name LIKE :search1";
                break;
            default:
                $count_sql .= "(i.invoice_no LIKE :search1 
                           OR i.patient_name LIKE :search2 
                           OR d.name LIKE :search3 
                           OR t.test_name LIKE :search4)";
        }
    }
    
    $count_stmt = $pdo->prepare($count_sql);
    if (!empty($search)) {
        if ($search_type === 'all') {
            $count_stmt->bindValue(':search1', '%' . $search . '%', PDO::PARAM_STR);
            $count_stmt->bindValue(':search2', '%' . $search . '%', PDO::PARAM_STR);
            $count_stmt->bindValue(':search3', '%' . $search . '%', PDO::PARAM_STR);
            $count_stmt->bindValue(':search4', '%' . $search . '%', PDO::PARAM_STR);
        } else {
            $count_stmt->bindValue(':search1', '%' . $search . '%', PDO::PARAM_STR);
        }
    }
    $count_stmt->execute();
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch(PDOException $e) {
    die("Error counting records: " . $e->getMessage());
}

// Fetch reports with pagination, sorting and search
try {
    $sql = "SELECT r.*, 
                   COUNT(rt.id) as test_count,
                   d.name as doctor_name,
                   i.patient_name,
                   i.patient_age,
                   i.patient_gender,
                   i.invoice_no,
                   GROUP_CONCAT(DISTINCT t.test_name) as test_names,
                   i.created_at as invoice_date
            FROM test_reports r
            LEFT JOIN test_results rt ON r.id = rt.report_id
            LEFT JOIN doctors d ON r.doctor_id = d.id
            LEFT JOIN invoices i ON r.invoice_id = i.id
            LEFT JOIN tests_info t ON r.test_code = t.test_code";
    
    if (!empty($search)) {
        $sql .= " WHERE ";
        switch($search_type) {
            case 'invoice':
                $sql .= "i.invoice_no LIKE :search1";
                break;
            case 'patient':
                $sql .= "i.patient_name LIKE :search1";
                break;
            case 'doctor':
                $sql .= "d.name LIKE :search1";
                break;
            case 'test':
                $sql .= "t.test_name LIKE :search1";
                break;
            default:
                $sql .= "(i.invoice_no LIKE :search1 
                      OR i.patient_name LIKE :search2 
                      OR d.name LIKE :search3 
                      OR t.test_name LIKE :search4)";
        }
    }
    
    $sql .= " GROUP BY r.id
              ORDER BY r.id DESC
              LIMIT :offset, :records_per_page";
    
    $stmt = $pdo->prepare($sql);
    if (!empty($search)) {
        if ($search_type === 'all') {
            $stmt->bindValue(':search1', '%' . $search . '%', PDO::PARAM_STR);
            $stmt->bindValue(':search2', '%' . $search . '%', PDO::PARAM_STR);
            $stmt->bindValue(':search3', '%' . $search . '%', PDO::PARAM_STR);
            $stmt->bindValue(':search4', '%' . $search . '%', PDO::PARAM_STR);
        } else {
            $stmt->bindValue(':search1', '%' . $search . '%', PDO::PARAM_STR);
        }
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error fetching reports: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report List - Laboratory Management System</title>
    <style>
        body { 
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f4f4f4;
            margin: 0;
            padding: 15px;
            color: #333;
        }
        .container {
            max-width: 1200px;
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
        .action-buttons {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        .action-buttons .btn {
            white-space: nowrap;
            padding: 6px 12px;
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
        .search-select {
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
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h2>Report List</h2>
            </div>
            <div>
                <a href="<?php echo $dashboard_url; ?>" class="btn btn-back">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Go Back to Dashboard
                </a>
                <a href="new_report.php" class="btn btn-primary">Create New Report</a>
            </div>
        </div>

        <!-- Search Form -->
        <div class="search-container">
            <form class="search-form" method="GET" action="">
                <input type="text" 
                       name="search" 
                       class="search-input" 
                       placeholder="Search..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <select name="search_type" class="search-select">
                    <option value="all" <?php echo $search_type === 'all' ? 'selected' : ''; ?>>All Fields</option>
                    <option value="invoice" <?php echo $search_type === 'invoice' ? 'selected' : ''; ?>>Invoice No</option>
                    <option value="patient" <?php echo $search_type === 'patient' ? 'selected' : ''; ?>>Patient Name</option>
                    <option value="doctor" <?php echo $search_type === 'doctor' ? 'selected' : ''; ?>>Doctor Name</option>
                    <option value="test" <?php echo $search_type === 'test' ? 'selected' : ''; ?>>Test Name</option>
                </select>
                <button type="submit" class="search-button">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="view_report_list.php" class="clear-search">Clear Search</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Report ID</th>
                        <th>Invoice No</th>
                        <th>Patient Name</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Doctor's Name</th>
                        <th>Test Names</th>
                        <th>Invoice Date</th>
                        <th>Report Date</th>
                        <th>Tests</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $report): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($report['id']); ?></td>
                        <td><?php echo htmlspecialchars($report['invoice_no']); ?></td>
                        <td><?php echo htmlspecialchars($report['patient_name']); ?></td>
                        <td><?php echo htmlspecialchars($report['patient_age']); ?></td>
                        <td><?php echo ucfirst(htmlspecialchars($report['patient_gender'])); ?></td>
                        <td><?php echo htmlspecialchars($report['doctor_name']); ?></td>
                        <td><?php echo htmlspecialchars($report['test_names']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($report['invoice_date'])); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($report['report_date'])); ?></td>
                        <td><?php echo $report['test_count']; ?></td>
                        <td>
                            <div class="action-buttons">
                                <a href="view_report.php?id=<?php echo $report['id']; ?>" class="btn btn-view">View</a>
                                <a href="print_report.php?id=<?php echo $report['id']; ?>" class="btn btn-primary" target="_blank">Print</a>
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                <a href="edit_report.php?id=<?php echo $report['id']; ?>" class="btn btn-primary">Edit</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) . '&search_type=' . urlencode($search_type) : ''; ?>">&laquo; Previous</a>
            <?php else: ?>
                <a class="disabled">&laquo; Previous</a>
            <?php endif; ?>

            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);

            if ($start_page > 1) {
                echo '<a href="?page=1' . (!empty($search) ? '&search=' . urlencode($search) . '&search_type=' . urlencode($search_type) : '') . '">1</a>';
                if ($start_page > 2) {
                    echo '<span>...</span>';
                }
            }

            for ($i = $start_page; $i <= $end_page; $i++) {
                if ($i == $page) {
                    echo '<a class="active">' . $i . '</a>';
                } else {
                    echo '<a href="?page=' . $i . (!empty($search) ? '&search=' . urlencode($search) . '&search_type=' . urlencode($search_type) : '') . '">' . $i . '</a>';
                }
            }

            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) {
                    echo '<span>...</span>';
                }
                echo '<a href="?page=' . $total_pages . (!empty($search) ? '&search=' . urlencode($search) . '&search_type=' . urlencode($search_type) : '') . '">' . $total_pages . '</a>';
            }
            ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) . '&search_type=' . urlencode($search_type) : ''; ?>">Next &raquo;</a>
            <?php else: ?>
                <a class="disabled">Next &raquo;</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
