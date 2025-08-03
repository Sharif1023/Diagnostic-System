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

// Include database configuration
require_once '../../config/database.php';

// Get database connection
$pdo = getDBConnection();

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

$error = null;
$success = null;

// Get search parameters
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

// Fetch pending tests from invoices with search conditions
try {
    $query = "
        SELECT i.id as invoice_id, 
               i.invoice_no,
               i.patient_name,
               i.patient_age,
               i.patient_gender,
               it.test_code,
               t.test_name,
               t.category,
               t.price
        FROM invoices i
        JOIN invoice_tests it ON i.id = it.invoice_id
        JOIN tests_info t ON it.test_code = t.test_code
        LEFT JOIN test_reports tr ON i.id = tr.invoice_id AND it.test_code = tr.test_code
        WHERE tr.id IS NULL
    ";

    $params = [];

    if (!empty($search)) {
        $query .= " AND (i.patient_name LIKE ? OR t.test_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if (!empty($category)) {
        $query .= " AND t.category = ?";
        $params[] = $category;
    }

    // Get total records count
    $count_query = "
        SELECT COUNT(DISTINCT CONCAT(i.id, it.test_code)) as total
        FROM invoices i
        JOIN invoice_tests it ON i.id = it.invoice_id
        JOIN tests_info t ON it.test_code = t.test_code
        LEFT JOIN test_reports tr ON i.id = tr.invoice_id AND it.test_code = tr.test_code
        WHERE tr.id IS NULL
    ";

    if (!empty($search)) {
        $count_query .= " AND (i.patient_name LIKE ? OR t.test_name LIKE ?)";
    }

    if (!empty($category)) {
        $count_query .= " AND t.category = ?";
    }

    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);

    // Add pagination to main query
    $query .= " ORDER BY i.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $records_per_page;
    $params[] = $offset;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $pendingTests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch unique categories for filter dropdown
    $stmt = $pdo->query("SELECT DISTINCT category FROM tests_info ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {
    die("Error fetching pending tests: " . $e->getMessage());
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $pdo->beginTransaction();

        $invoice_id = $_POST['invoice_id'];
        $test_code = $_POST['test_code'];
        $doctor_id = $_POST['doctor_id'] ?? null; // Get doctor_id from form
        $results = $_POST['results'];

        // Get test information
        $stmt = $pdo->prepare("SELECT test_name, category FROM tests_info WHERE test_code = ?");
        $stmt->execute([$test_code]);
        $test_info = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$test_info) {
            throw new Exception("Test information not found");
        }

        // Insert test report
        $stmt = $pdo->prepare("
            INSERT INTO test_reports (
                invoice_id,
                test_code,
                test_name,
                category,
                doctor_id,
                status,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, 'pending', NOW(), NOW())
        ");
        
        $stmt->execute([
            $invoice_id,
            $test_code,
            $test_info['test_name'],
            $test_info['category'],
            $doctor_id
        ]);
        $report_id = $pdo->lastInsertId();

        // Insert test results
        $stmt = $pdo->prepare("
            INSERT INTO test_results (
                report_id,
                parameter_id,
                result_value,
                normal_range,
                unit,
                remark,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $allParametersFilled = true;
        foreach ($results as $parameter_id => $result) {
            // Get parameter details
            $paramStmt = $pdo->prepare("SELECT normal_range, unit FROM test_parameters WHERE id = ?");
            $paramStmt->execute([$parameter_id]);
            $param_info = $paramStmt->fetch(PDO::FETCH_ASSOC);

            if (!empty($result)) {
                $stmt->execute([
                    $report_id,
                    $parameter_id,
                    $result,
                    $param_info['normal_range'],
                    $param_info['unit'],
                    $_POST['remarks'][$parameter_id] ?? null
                ]);
            } else {
                $allParametersFilled = false;
            }
        }

        // Update report status based on whether all parameters are filled
        $status = $allParametersFilled ? 'completed' : 'pending';
        $stmt = $pdo->prepare("UPDATE test_reports SET status = ? WHERE id = ?");
        $stmt->execute([$status, $report_id]);

        // Commit transaction
        $pdo->commit();

        $_SESSION['success'] = "Test report created successfully";
        header("Location: view_report.php?id=" . $report_id);
        exit();
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Report - Laboratory Management System</title>
    <link rel="stylesheet" href="../../assets/css/common.css">
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h2>Create New Report</h2>
            </div>
            <?php
            $dashboard_url = '/NPL/pages/user/staff_dashboard.php';
            if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                $dashboard_url = '/NPL/pages/user/admin_dashboard.php';
            }
            ?>

        
            <a href="<?php echo $dashboard_url; ?>" class="btn btn-back">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Back to Dashboard
            </a>
        </div>

        <?php if ($error): ?>
            <div class="message error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="message success-message"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Search Form -->
        <div class="search-container">
            <form class="search-form" method="GET" action="">
                <input type="text" 
                       name="search" 
                       class="search-input" 
                       placeholder="Search by patient name or test name..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <select name="category" class="search-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" 
                                <?php echo $category === $cat ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="search-button">Search</button>
                <?php if (!empty($search) || !empty($category)): ?>
                    <a href="new_report.php" class="clear-search">Clear Search</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Invoice Number</th>
                        <th>Patient Name</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Test Name</th>
                        <th>Category</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingTests as $test): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($test['invoice_no']); ?></td>
                        <td><?php echo htmlspecialchars($test['patient_name']); ?></td>
                        <td><?php echo htmlspecialchars($test['patient_age']); ?></td>
                        <td><?php echo htmlspecialchars($test['patient_gender']); ?></td>
                        <td><?php echo htmlspecialchars($test['test_name']); ?></td>
                        <td><?php echo htmlspecialchars($test['category']); ?></td>
                        <td>
                            <button type="button" class="btn btn-primary" 
                                    onclick="showTestForm(<?php echo htmlspecialchars(json_encode($test)); ?>)">
                                Enter Results
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?><?php 
                    echo !empty($search) ? '&search=' . urlencode($search) : ''; 
                    echo !empty($category) ? '&category=' . urlencode($category) : ''; 
                ?>">&laquo; Previous</a>
            <?php else: ?>
                <a class="disabled">&laquo; Previous</a>
            <?php endif; ?>

            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);

            if ($start_page > 1) {
                echo '<a href="?page=1' . 
                    (!empty($search) ? '&search=' . urlencode($search) : '') . 
                    (!empty($category) ? '&category=' . urlencode($category) : '') . 
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
                        (!empty($search) ? '&search=' . urlencode($search) : '') . 
                        (!empty($category) ? '&category=' . urlencode($category) : '') . 
                        '">' . $i . '</a>';
                }
            }

            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) {
                    echo '<span>...</span>';
                }
                echo '<a href="?page=' . $total_pages . 
                    (!empty($search) ? '&search=' . urlencode($search) : '') . 
                    (!empty($category) ? '&category=' . urlencode($category) : '') . 
                    '">' . $total_pages . '</a>';
            }
            ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?><?php 
                    echo !empty($search) ? '&search=' . urlencode($search) : ''; 
                    echo !empty($category) ? '&category=' . urlencode($category) : ''; 
                ?>">Next &raquo;</a>
            <?php else: ?>
                <a class="disabled">Next &raquo;</a>
            <?php endif; ?>
        </div>

        <!-- Test Result Form (Hidden by default) -->
        <div id="testForm" class="form-section" style="display: none;">
            <h3>Enter Test Results</h3>
            <form method="POST" action="">
                <input type="hidden" name="invoice_id" id="invoice_id">
                <input type="hidden" name="test_code" id="test_code">
                
                <div id="testParameters"></div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Save Results</button>
                    <button type="button" class="btn btn-back" onclick="hideTestForm()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showTestForm(test) {
            // Create a new form
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'enter_results.php';
            
            // Add hidden fields
            const invoiceId = document.createElement('input');
            invoiceId.type = 'hidden';
            invoiceId.name = 'invoice_id';
            invoiceId.value = test.invoice_id;
            form.appendChild(invoiceId);
            
            const testCode = document.createElement('input');
            testCode.type = 'hidden';
            testCode.name = 'test_code';
            testCode.value = test.test_code;
            form.appendChild(testCode);
            
            // Add form to body and submit
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
        
        function hideTestForm() {
            document.getElementById('testForm').style.display = 'none';
        }
    </script>

    <style>
        /* ... existing styles ... */
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
            min-width: 150px;
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
            .search-input, .search-select {
                width: 100%;
            }
        }
    </style>
</body>
</html>

