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

// Check if consumable code is provided
if (!isset($_GET['code'])) {
    header('Location: consumable_list.php');
    exit();
}

$consumable_code = $_GET['code'];

// Include database configuration
require_once '../../config/database.php';

// Get database connection
$pdo = getDBConnection();

$error = null;
$success = null;

// Fetch consumable details
try {
    $stmt = $pdo->prepare("SELECT * FROM consumable_info WHERE consumable_code = ?");
    $stmt->execute([$consumable_code]);
    $consumable = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$consumable) {
        header('Location: consumable_list.php');
        exit();
    }
} catch(PDOException $e) {
    die("Error fetching consumable: " . $e->getMessage());
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        $name = trim($_POST['name']);
        $price = (float)$_POST['price'];
        $unit = trim($_POST['unit']);

        if (empty($name) || empty($unit)) {
            throw new Exception("Name and unit are required");
        }

        if ($price < 0) {
            throw new Exception("Price cannot be negative");
        }

        // Update consumable
        $stmt = $pdo->prepare("
            UPDATE consumable_info 
            SET name = ?,
                price = ?,
                unit = ?
            WHERE consumable_code = ?
        ");
        
        $stmt->execute([
            $name,
            $price,
            $unit,
            $consumable_code
        ]);

        $success = "Consumable updated successfully";
        
        // Refresh consumable data
        $stmt = $pdo->prepare("SELECT * FROM consumable_info WHERE consumable_code = ?");
        $stmt->execute([$consumable_code]);
        $consumable = $stmt->fetch(PDO::FETCH_ASSOC);
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
    <title>Edit Consumable - Laboratory Management System</title>
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
        .form-group input {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        .form-group input:focus {
            border-color: #3498db;
            outline: none;
        }
        .form-group input[readonly] {
            background: #f8f9fa;
            cursor: not-allowed;
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
                <h2>Edit Consumable</h2>
            </div>
            <a href="consumable_list.php" class="btn btn-back">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Back to Consumable List
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
                <h3>Consumable Information</h3>
                <div class="form-group">
                    <label for="consumable_code">Consumable Code</label>
                    <input type="text" id="consumable_code" name="consumable_code" readonly
                           value="<?php echo htmlspecialchars($consumable['consumable_code']); ?>">
                </div>
                <div class="form-group">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" required
                           value="<?php echo htmlspecialchars($consumable['name']); ?>">
                </div>
                <div class="form-group">
                    <label for="price">Price (৳)</label>
                    <input type="number" id="price" name="price" required min="0" step="0.01"
                           value="<?php echo htmlspecialchars($consumable['price']); ?>">
                </div>
                <div class="form-group">
                    <label for="unit">Unit</label>
                    <input type="text" id="unit" name="unit" required
                           value="<?php echo htmlspecialchars($consumable['unit']); ?>"
                           placeholder="e.g., piece, box, etc.">
                </div>
            </div>

            <div class="form-section">
                <button type="submit" class="btn btn-primary">Update Consumable</button>
                <a href="consumable_list.php" class="btn btn-back">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html> 