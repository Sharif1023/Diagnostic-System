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
               i.patient_contact_no,
               i.referred_by,
               t.test_name,
               t.category,
               d.name as doctor_name,
               d.qualifications as doctor_qualifications,
               d.designation as doctor_designation,
               d.workplace as doctor_workplace
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
            tp.unit
        FROM test_results tr
        JOIN test_parameters tp ON tr.parameter_id = tp.id
        WHERE tr.report_id = ?
        ORDER BY tp.sort_order
    ");
    $stmt->execute([$report_id]);
    $parameters = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error fetching report details: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Report - Laboratory Management System</title>
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
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .page-title {
            flex: 1;
        }
        .report-header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .report-header h2 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            font-size: 24px;
        }
        .report-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .report-info p {
            margin: 8px 0;
            font-size: 14px;
        }
        .test-results {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .test-results h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            font-size: 18px;
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
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-pending {
            background: #f1c40f;
            color: #fff;
        }
        .status-completed {
            background: #2ecc71;
            color: #fff;
        }
        .status-cancelled {
            background: #e74c3c;
            color: #fff;
        }
        .status-verified {
            background: #3498db;
            color: #fff;
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
                <h2>Test Report</h2>
            </div>
            <a href="view_report_list.php" class="btn btn-back">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Back to Report List
            </a>
        </div>

        <div class="report-header">
            <div class="report-info">
                <div>
                    <p><strong>Report ID:</strong> <?php echo htmlspecialchars($report['id']); ?></p>
                    <p><strong>Patient Name:</strong> <?php echo htmlspecialchars($report['patient_name']); ?></p>
                    <p><strong>Age:</strong> <?php echo htmlspecialchars($report['patient_age']); ?></p>
                    <p><strong>Gender:</strong> <?php echo htmlspecialchars($report['patient_gender']); ?></p>
                </div>
                <div>
                    <p><strong>Test Name:</strong> <?php echo htmlspecialchars($report['test_name']); ?></p>
                    <p><strong>Category:</strong> <?php echo htmlspecialchars($report['category']); ?></p>
                    <p><strong>Doctor:</strong> 
                        <?php if ($report['doctor_name']): ?>
                            Dr. <?php echo htmlspecialchars($report['doctor_name']); ?>
                            (<?php echo htmlspecialchars($report['doctor_qualifications']); ?>)
                            <br>
                            <?php echo htmlspecialchars($report['doctor_designation']); ?>
                            <br>
                            <?php echo htmlspecialchars($report['doctor_workplace']); ?>
                        <?php else: ?>
                            Not specified
                        <?php endif; ?>
                    </p>
                    <p><strong>Report Date:</strong> <?php echo date('d/m/Y', strtotime($report['report_date'])); ?></p>
                </div>
                <div>
                    <p><strong>Status:</strong> 
                        <span class="status-badge status-<?php echo $report['report_status']; ?>">
                            <?php echo ucfirst($report['report_status']); ?>
                        </span>
                    </p>
                    <?php if ($report['remarks']): ?>
                        <p><strong>Remarks:</strong> <?php echo htmlspecialchars($report['remarks']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Doctor Information Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h3>Doctor Information</h3>
            </div>
            <div class="card-body">
                <?php if ($report['doctor_id']): ?>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Name:</strong> Dr. <?php echo htmlspecialchars($report['doctor_name']); ?></p>
                            <p><strong>Qualifications:</strong> <?php echo htmlspecialchars($report['doctor_qualifications']); ?></p>
                            <p><strong>Designation:</strong> <?php echo htmlspecialchars($report['doctor_designation']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Workplace:</strong> <?php echo htmlspecialchars($report['doctor_workplace']); ?></p>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No doctor information available</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Test Results Section -->
        <div class="card">
            <div class="card-header">
                <h3>Test Results</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Parameter</th>
                                <th>Result</th>
                                <th>Normal Range</th>
                                <th>Unit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($parameters as $param): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($param['parameter_name']); ?></td>
                                    <td><?php echo htmlspecialchars($param['result_value']); ?></td>
                                    <td><?php echo htmlspecialchars($param['normal_range']); ?></td>
                                    <td><?php echo htmlspecialchars($param['unit']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
