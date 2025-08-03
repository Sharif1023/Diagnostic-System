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

// Include database configuration
require_once '../../config/database.php';

// Get database connection
$pdo = getDBConnection();

$error = null;
$success = null;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        $test_code = trim($_POST['test_code']);
        $test_name = trim($_POST['test_name']);
        $description = trim($_POST['description'] ?? '');
        $unit = trim($_POST['unit']);
        $reference_range = trim($_POST['reference_range']);

        if (empty($test_code) || empty($test_name) || empty($unit) || empty($reference_range)) {
            throw new Exception("All fields are required");
        }

        // Check if test code already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tests_info WHERE test_code = ?");
        $stmt->execute([$test_code]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Test code already exists");
        }

        // Insert new test
        $stmt = $pdo->prepare("
            INSERT INTO tests_info (
                test_code,
                test_name,
                description,
                unit,
                reference_range
            ) VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $test_code,
            $test_name,
            $description,
            $unit,
            $reference_range
        ]);

        $success = "Test added successfully";
        
        // Clear form
        $_POST = array();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Test - Laboratory Management System</title>
    <style>
        body { 
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f4f4f4;
            margin: 0;
            padding: 15px;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 15px;
        }
        .page-header {
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .page-title {
            flex: 1;
        }
        .form-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .form-section h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            font-size: 18px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        .form-group input:focus, .form-group textarea:focus {
            border-color: #3498db;
            outline: none;
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        .error-message {
            color: #e74c3c;
            font-size: 14px;
            margin-top: 5px;
        }
        .success-message {
            color: #27ae60;
            font-size: 14px;
            margin-top: 5px;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h2>Add New Test</h2>
            </div>
            <a href="test_list.php" class="btn btn-back">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Back to Test List
            </a>
        </div>

        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-section">
                <h3>Test Information</h3>
                <div class="form-group">
                    <label for="test_code">Test Code</label>
                    <input type="text" id="test_code" name="test_code" required
                           value="<?php echo isset($_POST['test_code']) ? htmlspecialchars($_POST['test_code']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="test_name">Test Name</label>
                    <input type="text" id="test_name" name="test_name" required
                           value="<?php echo isset($_POST['test_name']) ? htmlspecialchars($_POST['test_name']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="description">Description (Optional)</label>
                    <textarea id="description" name="description" 
                              placeholder="Enter test description"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                </div>
                <div class="form-group">
                    <label for="unit">Unit</label>
                    <input type="text" id="unit" name="unit" required
                           value="<?php echo isset($_POST['unit']) ? htmlspecialchars($_POST['unit']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="reference_range">Reference Range</label>
                    <input type="text" id="reference_range" name="reference_range" required
                           value="<?php echo isset($_POST['reference_range']) ? htmlspecialchars($_POST['reference_range']) : ''; ?>"
                           placeholder="e.g., 0-100">
                </div>
            </div>

            <div class="form-section">
                <button type="submit" class="btn btn-primary">Add Test</button>
                <a href="test_list.php" class="btn btn-back">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html> 