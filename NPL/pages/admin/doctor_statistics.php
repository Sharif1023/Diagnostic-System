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

// Include required files
require_once '../../config/database.php';
require_once '../../includes/doctor_functions.php';

// Get database connection
$pdo = getDBConnection();

// Get doctor ID from URL
$doctorId = $_GET['id'] ?? null;

if (!$doctorId) {
    header('Location: manage_doctors.php');
    exit();
}

try {
    // Get doctor details
    $doctor = getDoctorById($doctorId);
    if (!$doctor) {
        throw new Exception('Doctor not found');
    }

    // Get doctor statistics
    $statistics = getDoctorStatistics($doctorId);

    // Get doctor's recent reports
    $reports = getDoctorReports($doctorId, 10, 0);

} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Statistics - Laboratory Management System</title>
    <link rel="stylesheet" href="../../assets/css/common.css">
    <style>
        .statistics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin: 10px 0;
        }
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        .doctor-info {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .doctor-info h3 {
            margin-top: 0;
            color: #333;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        .info-item {
            margin-bottom: 10px;
        }
        .info-label {
            font-weight: bold;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h2>Doctor Statistics</h2>
            </div>
            <div>
                <a href="../../pages/user/admin_dashboard.php" class="btn btn-back">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Back to Dashboard
                </a>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php else: ?>
            <div class="doctor-info">
                <h3>Dr. <?php echo htmlspecialchars($doctor['name']); ?></h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Qualifications</div>
                        <div><?php echo htmlspecialchars($doctor['qualifications']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Designation</div>
                        <div><?php echo htmlspecialchars($doctor['designation']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Workplace</div>
                        <div><?php echo htmlspecialchars($doctor['workplace']); ?></div>
                    </div>
                </div>
            </div>

            <div class="statistics-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $statistics['total_reports']; ?></div>
                    <div class="stat-label">Total Reports</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $statistics['completed_reports']; ?></div>
                    <div class="stat-label">Completed Reports</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $statistics['pending_reports']; ?></div>
                    <div class="stat-label">Pending Reports</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo date('d/m/Y', strtotime($statistics['first_report_date'])); ?></div>
                    <div class="stat-label">First Report Date</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo date('d/m/Y', strtotime($statistics['last_report_date'])); ?></div>
                    <div class="stat-label">Last Report Date</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Recent Reports</h3>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Report ID</th>
                                <th>Patient Name</th>
                                <th>Test Name</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $report): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($report['id']); ?></td>
                                <td><?php echo htmlspecialchars($report['patient_name']); ?></td>
                                <td><?php echo htmlspecialchars($report['test_name']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($report['report_date'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $report['report_status']; ?>">
                                        <?php echo ucfirst($report['report_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="../report/view_report.php?id=<?php echo $report['id']; ?>" 
                                       class="btn btn-view">View</a>
                                    <a href="../report/print_report.php?id=<?php echo $report['id']; ?>" 
                                       class="btn btn-primary" target="_blank">Print</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 