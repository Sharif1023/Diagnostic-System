<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include role checking
require_once '../../config/role_check.php';

// Check if user is logged in (staff or admin)
checkLogin();

// Include database configuration
require_once '../../config/database.php';

// Get database connection
$pdo = getDBConnection();

// Fetch all tests
try {
    $stmt = $pdo->query("SELECT * FROM tests_info ORDER BY test_name");
    $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error fetching tests: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Management - Laboratory Management System</title>
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
            border: none;
        }
        .btn-primary {
            background: #3498db;
            color: white;
        }
        .btn-primary:hover {
            background: #2980b9;
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
        }
        .description-cell {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .description-cell:hover {
            white-space: normal;
            overflow: visible;
            position: relative;
            z-index: 1;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 4px;
            padding: 8px;
        }
        .empty-description {
            color: #999;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h2>Test Management</h2>
            </div>
            <div>
                <a href="/pages/user/admin_dashboard.php" class="btn btn-back">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Go Back to Dashboard
                </a>
                <a href="add_test.php" class="btn btn-primary">Add New Test</a>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Test Code</th>
                        <th>Test Name</th>
                        <th>Description</th>
                        <th>Unit</th>
                        <th>Reference Range</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tests as $test): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($test['test_code']); ?></td>
                        <td><?php echo htmlspecialchars($test['test_name']); ?></td>
                        <td class="description-cell">
                            <?php if (empty($test['description'])): ?>
                                <span class="empty-description">No description</span>
                            <?php else: ?>
                                <?php echo htmlspecialchars($test['description']); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($test['unit']); ?></td>
                        <td><?php echo htmlspecialchars($test['reference_range']); ?></td>
                        <td>
                            <div class="action-buttons">
                                <a href="edit_test.php?code=<?php echo $test['test_code']; ?>" class="btn btn-edit">Edit</a>
                                <a href="delete_test.php?code=<?php echo $test['test_code']; ?>" 
                                   class="btn btn-delete"
                                   onclick="return confirm('Are you sure you want to delete this test? This action cannot be undone.');">
                                    Delete
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html> 