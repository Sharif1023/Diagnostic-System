<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit();
}

if (empty($_GET['id'])) {
    header('Location: view_report_list.php');
    exit();
}

$report_id = $_GET['id'];

require_once '../../config/database.php';
$pdo = getDBConnection();

try {
    $stmt = $pdo->prepare("
        SELECT r.*, 
               i.patient_name,
               i.patient_age,
               i.patient_gender,
               i.patient_contact_no,
               i.referred_by,
               i.invoice_no,
               t.test_name,
               t.description,
               d.name as doctor_name,
               d.qualifications as doctor_qualifications,
               d.designation as doctor_designation,
               d.workplace as doctor_workplace
        FROM test_reports r
        LEFT JOIN invoices i ON r.invoice_id = i.id
        LEFT JOIN tests_info t ON r.test_code = t.test_code
        LEFT JOIN doctors d ON r.doctor_id = d.id
        WHERE r.id = ?
    ");
    $stmt->execute([$report_id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        header('Location: view_report_list.php');
        exit();
    }

    $stmt = $pdo->prepare("
        SELECT 
            tr.result_value,
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
    <title>Print Report - Niruponi Pathology Laboratory System</title>
    <link rel="stylesheet" href="../../assets/css/common.css">
    <style>
        @media print {
            @page { size: A4; margin: 0; }
            body { margin: 0; padding: 0; font-size: 9pt; line-height: 1.2; }
            .a4-page { width: 210mm; height: 297mm; margin: 0 auto; padding: 45mm 15mm; box-sizing: border-box; }
            .center { text-align: center; }
            .right { text-align: right; }
            .left { text-align: left; }
            .bold { font-weight: bold; }
            .title { font-size: 14pt; margin-bottom: 5px; }
            .divider { border-bottom: 1px solid #000; }
            .report-title { 
                text-align: center; 
                font-size: 14pt; 
                font-weight: bold; 
                margin: 0px 0; 
            }
            .divider-double { border-top: 2px solid #000; margin: 5px 0; }
            .divider-dashed { border-top: 1px dashed #000; margin: 5px 0; }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin: 2px 0; 
                font-size: 11pt;
            }
            th, td { 
                padding: 4px 8px; 
                text-align: left; 
                border: none; 
                font-size: 11pt;
            }
            th { 
                border-bottom: 2px dashed #000;
                font-weight: bold;
            }
            .test-name { width: 30%; text-align: left; }
            .test-range { width: 40%; }
            .test-value { width: 20%; text-align: left; }
            .test-unit { width: 10%; text-align: left; }
            .section-divider { border-top: 0px solid #000; margin: 15px 0; width: 100%; }
            .section-title { font-weight: bold; font-size: 12pt; margin: 10px 0; color: #2c3e50; }
            .dashed-divider {
                border-bottom: 1px dashed #000;
                height: 1px;
                margin: 0;
                padding: 0;
            }
            .table-row { border-bottom: 1px dashed #000; }
            tr:last-child td {
                border-bottom: none;
            }
        }
        .doctor-info {
            position: absolute;
            bottom: 35mm;
            right: 15mm;
            text-align: right;
            font-size: 9pt;
            line-height: 1.1;
            max-width: 60mm;
            word-wrap: break-word;
        }
        .doctor-info p { margin: 2px 0; }
        .patient-info-box {
            border: 1px solid #000;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            background: #f8f9fa;
        }
        .info-columns { display: flex; gap: 20px; }
        .info-column { flex: 1; }
        .info-row {
            display: flex;
            justify-content: flex-start;
            align-items: center;
            margin-bottom: 5px;
            padding: 3px 0;
        }
        .info-label { font-weight: bold; min-width: 90px; margin-right: 8px; }
        .info-value { flex: 1; text-align: left; }
    </style>
</head>
<body>
    <div class="a4-page">        
        <div class="patient-info-box">
            <div class="info-columns">
                <div class="info-column">
                    <div class="info-row">
                        <span class="info-label">Invoice No:</span>
                        <span class="info-value"><strong><?php echo htmlspecialchars($report['invoice_no'] ?? ''); ?></strong></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Patient Name:</span>
                        <span class="info-value"><strong><?php echo htmlspecialchars($report['patient_name'] ?? ''); ?></strong></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Test Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars($report['test_name'] ?? ''); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Ref By:</span>
                        <span class="info-value"><strong><?php echo htmlspecialchars($report['referred_by'] ?? ''); ?></strong></span>
                    </div>
                </div>
                <div class="info-column">
                    <div class="info-row">
                        <span class="info-label">Report No:</span>
                        <span class="info-value"><?php echo htmlspecialchars($report['id'] ?? ''); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Age:</span>
                        <span class="info-value"><?php echo htmlspecialchars($report['patient_age'] ?? ''); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Sex:</span>
                        <span class="info-value"><?php echo htmlspecialchars($report['patient_gender'] ?? ''); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Date:</span>
                        <span class="info-value"><?php echo !empty($report['report_date']) ? date('d/m/Y', strtotime($report['report_date'])) : ''; ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php if (!empty($report['description'])): ?>
            <div class="report-title">
                <?php echo htmlspecialchars($report['description']); ?>
            <div style="height: 8px;"></div>
            </div>
        <?php endif; ?>
        <div>
        <table>
            <thead>
                <tr style="border: 1px solid #000; border-radius: 2.5px; background: #f8f9fa; box-sizing: border-box; overflow: hidden;">
                    <th class="test-name" style="border: none; background: transparent;">Test Name</th>
                    <th class="test-value" style="border: none; background: transparent;">Test Value</th>
                    <th class="test-range" style="border: none; background: transparent;">Normal Range</th>
                    <th class="test-unit" style="border: none; background: transparent;">Unit</th>
                </tr>
                <!-- Removed dashed divider under table header -->
            </thead>
            <tbody>
                <?php if (empty($parameters)): ?>
                    <tr>
                        <td colspan="4" style="color: red; text-align: center;">
                            No test data found for this report.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($parameters as $param): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($param['parameter_name'] ?? ''); ?></td>
                            <td class="test-value"><strong><?php echo htmlspecialchars($param['result_value'] ?? ''); ?></strong></td>
                            <td><?php echo htmlspecialchars($param['normal_range'] ?? ''); ?></td>
                            <td class="test-unit"><?php echo htmlspecialchars($param['unit'] ?? ''); ?></td>
                        </tr>
                        <tr><td colspan="4" class="dashed-divider"></td></tr>
                    <?php endforeach; ?>
                    <tr><td colspan="4" class="dashed-divider"></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
        <div class="doctor-info">
            <?php if (!empty($report['doctor_id'])): ?>                
                <p><?php echo htmlspecialchars($report['doctor_name'] ?? ''); ?></p>
                <p><?php echo htmlspecialchars($report['doctor_qualifications'] ?? ''); ?></p>
                <p><?php echo htmlspecialchars($report['doctor_designation'] ?? ''); ?></p>
                <p><?php echo htmlspecialchars($report['doctor_workplace'] ?? ''); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>