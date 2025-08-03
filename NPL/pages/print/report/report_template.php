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
require_once '../../../config/database.php';

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
               t.description,
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
        SELECT p.*, r.result_value
        FROM test_parameters p
        LEFT JOIN test_results r ON p.id = r.parameter_id AND r.report_id = ?
        WHERE p.test_code = ?
        ORDER BY p.sort_order
    ");
    $stmt->execute([$report_id, $report['test_code']]);
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
    <title>Print Report - Laboratory Management System</title>
    <style>
        @media print {
            @page {
                size: A4;
                margin: 0;
            }
            body {
                margin: 0;
                padding: 0;
                font-size: 9pt;
                line-height: 1.2;
            }
            .a4-page {
                width: 210mm;
                height: 297mm;
                margin: 0 auto;
                padding: 45mm 15mm;
                box-sizing: border-box;
            }
            .center {
                text-align: center;
            }
            .right {
                text-align: right;
            }
            .left {
                text-align: left;
            }
            .bold {
                font-weight: bold;
            }
            .title {
                font-size: 14pt;
                margin-bottom: 5px;
            }
            .divider {
                border-top: 1px solid #000;
                margin: 2px 0;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 2px 0;
                font-size: 9pt;
            }
            th, td {
                padding: 2px;
                text-align: left;
                border: none;
                font-size: 9pt;
            }
            .test-name {
                width: 40%;
            }
            .test-range {
                width: 25%;
            }
            .test-value {
                width: 20%;
                text-align: center;
            }
            .test-unit {
                width: 15%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="a4-page">
        <div class="center bold title">Report Copy</div>
        <div class="center bold">Date: <?php echo date('d/m/Y', strtotime($report['report_date'])); ?></div>
        <div style="height: 5px;"></div>
        
        <div>
            <span>Report No: <?php echo htmlspecialchars($report['id']); ?></span>
            <span style="float: right;">Invoice No: <?php echo htmlspecialchars($report['invoice_no']); ?></span>
        </div>
        <div>
            <span>Patient Name: <?php echo htmlspecialchars($report['patient_name']); ?></span>
            <span style="float: right;">Age: <?php echo htmlspecialchars($report['patient_age']); ?></span>
        </div>
        <div>
            <span>Gender: <?php echo htmlspecialchars($report['patient_gender']); ?></span>
            <span style="float: right;">Contact No: <?php echo htmlspecialchars($report['patient_contact_no']); ?></span>
        </div>
        <div>
            <span>Ref By: 
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
            </span>
        </div>
        <div>
            <span>Test Name: <?php echo htmlspecialchars($report['test_name']); ?></span>
        </div>
        <div style="height: 5px;"></div>

        <div class="divider"></div>
        <table>
            <tr>
                <th class="test-name">Test Name</th>
                <th class="test-range">Normal Range</th>
                <th class="test-value">Test Value</th>
                <th class="test-unit">Unit</th>
            </tr>
            <tr><td colspan="4" class="divider"></td></tr>
            <?php foreach ($parameters as $param): ?>
            <tr>
                <td><?php echo htmlspecialchars($param['parameter_name']); ?></td>
                <td><?php echo htmlspecialchars($param['normal_range']); ?></td>
                <td class="test-value"><?php echo htmlspecialchars($param['result_value'] ?? 'Not entered'); ?></td>
                <td class="test-unit"><?php echo htmlspecialchars($param['unit']); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>

        <div style="height: 5px;"></div>
        <div>
            <span>Verified By: ___________________</span>
        </div>
        <div>
            <span>Signature: ___________________</span>
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>
