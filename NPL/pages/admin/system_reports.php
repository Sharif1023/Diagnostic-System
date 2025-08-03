<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Reports - Niruponi Pathology Laboratory System</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h2>System Reports</h2>
            </div>
            <div>
                <a href="/pages/user/admin_dashboard.php" class="btn btn-back">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Back to Dashboard
                </a>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="reports-section">
            <h2>Available Reports</h2>
            <div class="grid grid-3">
                <a href="reports/daily_sales.php" class="btn btn-primary">Daily Sales Report</a>
                <a href="reports/monthly_sales.php" class="btn btn-primary">Monthly Sales Report</a>
                <a href="reports/test_wise.php" class="btn btn-primary">Test-wise Report</a>
                <a href="reports/patient_wise.php" class="btn btn-primary">Patient-wise Report</a>
                <a href="reports/doctor_wise.php" class="btn btn-primary">Doctor-wise Report</a>
                <a href="reports/collection_report.php" class="btn btn-primary">Collection Report</a>
            </div>
        </div>
    </div>
</body>
</html> 