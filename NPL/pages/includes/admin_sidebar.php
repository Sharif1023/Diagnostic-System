<?php
// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /index.php');
    exit();
}
?>
<div class="sidebar">
    <h2>Menu</h2>
    <div class="section">
        <div class="section-title">Dashboard</div>
        <ul>
            <li><a href="../user/admin_dashboard.php">Dashboard</a></li>
        </ul>
    </div>
    <div class="section">
        <div class="section-title">Invoice Section</div>
        <ul>
            <li><a href="../invoice/new_invoice.php">Create New Invoice</a></li>
            <li><a href="../invoice/view_invoice_list.php">View Invoice List</a></li>
            <li><a href="../invoice/view_invoice_list.php">Pay Bill</a></li>
        </ul>
    </div>
    <div class="section">
        <div class="section-title">Report Section</div>
        <ul>
            <li><a href="../report/new_report.php">Create New Report</a></li>
            <li><a href="../report/view_report_list.php">View Report List</a></li>
        </ul>
    </div>
    <div class="section">
        <div class="section-title">Administration</div>
        <ul>
            <li><a href="../admin/manage_users.php">Manage Users</a></li>
            <li><a href="../admin/manage_tests.php">Manage Tests</a></li>
            <li><a href="../admin/manage_consumables.php">Manage Consumables</a></li>
            <li><a href="../admin/manage_doctors.php">Manage Doctors</a></li>
            <li><a href="../admin/reports.php">System Reports</a></li>
        </ul>
    </div>
    <div class="section">
        <div class="section-title">Account</div>
        <ul>
            <li><a href="../../logout.php">Logout</a></li>
        </ul>
    </div>
</div> 