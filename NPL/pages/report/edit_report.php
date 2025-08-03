<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: /index.php');
    exit();
}

// Check if report ID is provided
if (!isset($_GET['id'])) {
    header('Location: view_report_list.php');
    exit();
}

$report_id = $_GET['id'];

// Include database configuration
require_once '../../config/database.php';

// Get database connection
$pdo = getDBConnection();

// Fetch report details
try {
    $stmt = $pdo->prepare("
        SELECT r.*, 
               i.patient_name,
               i.patient_age,
               i.patient_gender,
               t.test_name,
               t.category,
               t.price,
               d.id as doctor_id
        FROM test_reports r
        JOIN invoices i ON r.invoice_id = i.id
        JOIN tests_info t ON r.test_code = t.test_code
        LEFT JOIN doctors d ON r.doctor_id = d.id
        WHERE r.id = ?
    ");
    $stmt->execute([$report_id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$report) {
        header('Location: view_report_list.php');
        exit();
    }
    
    // Fetch test parameters and results
    $stmt = $pdo->prepare("
        SELECT 
            tr.*,
            tp.parameter_name,
            tp.normal_range,
            tp.unit,
            tp.id as parameter_id
        FROM test_parameters tp
        LEFT JOIN test_results tr ON tp.id = tr.parameter_id AND tr.report_id = ?
        WHERE tp.test_code = ?
        ORDER BY tp.sort_order
    ");
    $stmt->execute([$report_id, $report['test_code']]);
    $parameters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all doctors for the dropdown
    $stmt = $pdo->query("
        SELECT id, name, qualifications, designation, workplace 
        FROM doctors 
        ORDER BY name
    ");
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error fetching report details: " . $e->getMessage());
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['results'])) {
    try {
        // Start transaction
        $pdo->beginTransaction();

        $doctor_id = $_POST['doctor_id'] ?? null;
        $results = $_POST['results'];

        // Update test report
        $stmt = $pdo->prepare("
            UPDATE test_reports 
            SET doctor_id = ?,
                report_date = CURDATE()
            WHERE id = ?
        ");
        
        $stmt->execute([$doctor_id, $report_id]);

        // Delete existing results
        $stmt = $pdo->prepare("DELETE FROM test_results WHERE report_id = ?");
        $stmt->execute([$report_id]);

        // Insert updated test results
        $stmt = $pdo->prepare("
            INSERT INTO test_results (
                report_id,
                parameter_id,
                result_value,
                normal_range,
                unit,
                created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
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
                    $param_info['unit']
                ]);
            } else {
                $allParametersFilled = false;
            }
        }

        // Update report status based on whether all parameters are filled
        $status = $allParametersFilled ? 'completed' : 'pending';
        $stmt = $pdo->prepare("UPDATE test_reports SET report_status = ? WHERE id = ?");
        $stmt->execute([$status, $report_id]);

        // Commit transaction
        $pdo->commit();

        $_SESSION['success'] = "Test results updated successfully";
        header("Location: view_report_list.php");
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
    <title>Edit Test Results - Laboratory Management System</title>
    <link rel="stylesheet" href="../../assets/css/common.css">
    <style>
        .card {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .card-header {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }
        .card-header h3 {
            margin: 0;
            color: #333;
        }
        .grid {
            display: grid;
            gap: 20px;
        }
        .grid-2 {
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #666;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-group input[readonly] {
            background: #f8f9fa;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #3498db;
            color: white;
        }
        .btn-primary:hover {
            background: #2980b9;
        }
        .btn-back {
            background: #f8f9fa;
            color: #333;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-back:hover {
            background: #e9ecef;
        }
        .error-message {
            background: #fee;
            color: #c00;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .test-price {
            font-weight: bold;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h2>Edit Test Results</h2>
            </div>
            <div>
                <a href="view_report_list.php" class="btn btn-back">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Back to Report List
                </a>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3>Patient & Test Details</h3>
            </div>
            <div class="grid grid-2">
                <div class="form-group">
                    <label>Patient Name</label>
                    <input type="text" value="<?php echo htmlspecialchars($report['patient_name']); ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Age</label>
                    <input type="text" value="<?php echo htmlspecialchars($report['patient_age']); ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Gender</label>
                    <input type="text" value="<?php echo htmlspecialchars($report['patient_gender']); ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Test Name</label>
                    <input type="text" value="<?php echo htmlspecialchars($report['test_name']); ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" value="<?php echo htmlspecialchars($report['category']); ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Test Price</label>
                    <input type="text" value="৳<?php echo number_format($report['price'], 2); ?>" readonly class="test-price">
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Test Parameters</h3>
            </div>
            <form method="POST" action="">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="doctor_id" style="font-weight: 600;">Select Doctor</label>
                    <select name="doctor_id" id="doctor_id" class="form-control" required>
                        <option value="">-- Select Doctor --</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['id']; ?>" 
                                <?php echo ($report['doctor_id'] == $doctor['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($doctor['name']); ?> 
                                (<?php echo htmlspecialchars($doctor['qualifications']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <h3>Test Parameters</h3>
                    <?php foreach ($parameters as $param): ?>
                        <div class="parameter-group" style="margin-bottom: 15px; display: flex; align-items: center; gap: 20px;">
                            <div style="flex: 1;">
                                <label for="result_<?php echo $param['parameter_id']; ?>" style="font-weight: 600;">
                                    <?php echo htmlspecialchars($param['parameter_name']); ?>
                                    <?php if ($param['unit']): ?>
                                        (<?php echo htmlspecialchars($param['unit']); ?>)
                                    <?php endif; ?>
                                </label>
                                <input type="text" 
                                       id="result_<?php echo $param['parameter_id']; ?>" 
                                       name="results[<?php echo $param['parameter_id']; ?>]" 
                                       value="<?php echo htmlspecialchars($param['result_value'] ?? ''); ?>"
                                       class="form-control"
                                       required>
                            </div>
                            <div style="min-width: 200px; color: #666;">
                                Normal Range: <?php echo htmlspecialchars($param['normal_range']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Update Results</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
