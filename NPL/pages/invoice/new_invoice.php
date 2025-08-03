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

// Include database configuration
require_once '../../config/database.php';

// Get database connection
$pdo = getDBConnection();

// Determine dashboard URL based on user role
$dashboard_url = '/NPL/pages/user/staff_dashboard.php'; // Default to staff dashboard
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $dashboard_url = '/NPL/pages/user/admin_dashboard.php';
}

// Fetch available tests and consumables
try {
    $tests = $pdo->query("SELECT * FROM tests_info ORDER BY test_name")->fetchAll(PDO::FETCH_ASSOC);
    $consumables = $pdo->query("SELECT * FROM consumable_info ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Invoice - Laboratory Management System</title>
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
        .page-header h2 {
            color: #2c3e50;
            font-size: 24px;
            margin: 0;
            padding: 15px 0;
        }
        .form-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .form-section h3 {
            color: #2c3e50;
            font-size: 18px;
            margin: 0 0 15px 0;
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
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding: 0 0 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .search-box {
            width: 250px;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: all 0.3s;
        }
        .search-box:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        .table-container {
            margin-top: 10px;
            max-height: 350px;
            overflow-y: auto;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            background: white;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        th {
            position: sticky;
            top: 0;
            background: #f8f9fa;
            z-index: 1;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
            color: #2c3e50;
            font-weight: 600;
            font-size: 14px;
        }
        td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
            font-size: 14px;
            vertical-align: middle;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .test-checkbox, .consumable-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
            vertical-align: middle;
            accent-color: #3498db;
        }
        .consumable-quantity {
            width: 80px;
            padding: 6px 8px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .consumable-quantity:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        .form-actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            border: none;
            font-size: 14px;
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
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        .btn-secondary:hover {
            background: #7f8c8d;
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
        /* Custom scrollbar for better appearance */
        .table-container::-webkit-scrollbar {
            width: 8px;
        }
        .table-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        .table-container::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }
        .table-container::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        /* Price column styling */
        .price-column {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h2>Create New Invoice</h2>
            </div>
            <a href="<?php echo $dashboard_url; ?>" class="btn btn-back">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Go Back to Dashboard
            </a>
        </div>

        <form id="invoiceForm" method="POST" action="add_invoice.php" onsubmit="return validateForm()">
            
            <div class="form-section">
                <h3>Patient Information</h3>
                <div class="form-group">
                    <label for="patient_name">Patient Name</label>
                    <input type="text" id="patient_name" name="patient_name" required>
                </div>
                <div class="form-group">
                    <label for="patient_contact">Contact Number</label>
                    <input type="tel" id="patient_contact" name="patient_contact_no" required>
                </div>
                <div class="form-group">
                    <label for="patient_age">Age</label>
                    <input type="text" id="patient_age" name="patient_age"  required>
                </div>
                <div class="form-group">
                    <label for="patient_gender">Gender</label>
                    <select id="patient_gender" name="patient_gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="referred_by">Referred By</label>
                    <input type="text" id="referred_by" name="referred_by" placeholder="Doctor's name or clinic" required>
                </div>
                <div class="form-group">
                    <label for="delivery_date">Delivery Date</label>
                    <input type="date" id="delivery_date" name="delivery_date" required 
                           min="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>

            <div class="form-section">
                <div class="section-header">
                    <h3>Tests</h3>
                    <input type="text" id="testSearch" class="search-box" placeholder="🔍 Search tests...">
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 50%">Test Name</th>
                                <th style="width: 30%">Price</th>
                                <th style="width: 20%">Select</th>
                            </tr>
                        </thead>
                        <tbody id="testTableBody">
                            <?php foreach ($tests as $test): ?>
                            <tr class="test-row">
                                <td><?php echo htmlspecialchars($test['test_name']); ?></td>
                                <td class="price-column">৳<?php echo number_format($test['price'], 2); ?></td>
                                <td>
                                    <input type="checkbox" class="test-checkbox" name="tests[]" value="<?php echo $test['test_code']; ?>" 
                                           data-price="<?php echo $test['price']; ?>">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="form-section">
                <div class="section-header">
                    <h3>Consumables</h3>
                    <input type="text" id="consumableSearch" class="search-box" placeholder="🔍 Search consumables...">
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 40%">Item Name</th>
                                <th style="width: 20%">Price</th>
                                <th style="width: 20%">Quantity</th>
                                <th style="width: 20%">Select</th>
                            </tr>
                        </thead>
                        <tbody id="consumableTableBody">
                            <?php foreach ($consumables as $consumable): ?>
                            <tr class="consumable-row">
                                <td><?php echo htmlspecialchars($consumable['name']); ?></td>
                                <td class="price-column">৳<?php echo number_format($consumable['price'], 2); ?></td>
                                <td>
                                    <input type="number" class="consumable-quantity" name="quantities[<?php echo $consumable['consumable_code']; ?>]" 
                                           min="0" value="0" disabled>
                                </td>
                                <td>
                                    <input type="checkbox" class="consumable-checkbox" name="consumables[]" 
                                           value="<?php echo $consumable['consumable_code']; ?>" data-price="<?php echo $consumable['price']; ?>">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="form-section">
                <h3>Payment Information</h3>
                <div class="form-group">
                    <label for="total_amount">Total Amount</label>
                    <input type="number" id="total_amount" name="total_amount" readonly>
                </div>
                <div class="form-group">
                    <label for="discount_amount">Discount Amount</label>
                    <input type="number" id="discount_amount" name="discount_amount" value="0" min="0" step="0.01">
                </div>
                <div class="form-group">
                    <label for="amount_paid">Amount Paid</label>
                    <input type="number" id="amount_paid" name="amount_paid" required min="0" step="0.01">
                </div>
                <div class="form-group">
                    <label for="due_amount">Due Amount</label>
                    <input type="number" id="due_amount" readonly>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" name="action" value="save" class="btn btn-primary">Create Invoice</button>
                <button type="reset" class="btn btn-secondary">Reset</button>
            </div>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const testCheckboxes = document.querySelectorAll('.test-checkbox');
        const consumableCheckboxes = document.querySelectorAll('.consumable-checkbox');
        const consumableQuantities = document.querySelectorAll('.consumable-quantity');
        const totalAmountInput = document.getElementById('total_amount');
        const discountAmountInput = document.getElementById('discount_amount');
        const amountPaidInput = document.getElementById('amount_paid');
        const dueAmountInput = document.getElementById('due_amount');

        function updateCheckboxStates() {
            const selectedTests = document.querySelectorAll('.test-checkbox:checked');
            const selectedConsumables = document.querySelectorAll('.consumable-checkbox:checked');
            const totalSelected = selectedTests.length + selectedConsumables.length;
            const isAtLimit = totalSelected >= 15;

            // Disable unchecked checkboxes if at limit
            testCheckboxes.forEach(checkbox => {
                if (!checkbox.checked) {
                    checkbox.disabled = isAtLimit;
                }
            });

            consumableCheckboxes.forEach(checkbox => {
                if (!checkbox.checked) {
                    checkbox.disabled = isAtLimit;
                }
            });

            // Show warning if at limit
            if (isAtLimit) {
                alert('Maximum 15 items (tests + consumables) can be selected. Please deselect some items to add more.');
            }
        }

        function updateAmounts() {
            let total = 0;

            // Calculate test costs
            testCheckboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    const price = parseFloat(checkbox.dataset.price);
                    total += price;
                }
            });

            // Calculate consumable costs
            consumableCheckboxes.forEach((checkbox, index) => {
                if (checkbox.checked) {
                    const quantity = parseInt(consumableQuantities[index].value) || 1;
                    const price = parseFloat(checkbox.dataset.price);
                    total += price * quantity;
                }
            });

            const discount = parseFloat(discountAmountInput.value) || 0;
            const amountPaid = parseFloat(amountPaidInput.value) || 0;
            const netTotal = total - discount;
            const due = Math.max(0, netTotal - amountPaid);

            totalAmountInput.value = total.toFixed(2);
            dueAmountInput.value = due.toFixed(2);

            // Validate amount paid
            if (amountPaid > netTotal) {
                amountPaidInput.value = netTotal.toFixed(2);
                dueAmountInput.value = '0.00';
            }
        }

        // Add event listeners
        testCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateAmounts();
                updateCheckboxStates();
            });
        });

        consumableCheckboxes.forEach((checkbox, index) => {
            checkbox.addEventListener('change', function() {
                consumableQuantities[index].disabled = !this.checked;
                if (!this.checked) {
                    consumableQuantities[index].value = '0';
                } else {
                    consumableQuantities[index].value = '1';
                }
                updateAmounts();
                updateCheckboxStates();
            });
        });

        consumableQuantities.forEach(quantity => {
            quantity.addEventListener('input', function() {
                if (parseInt(this.value) < 1) {
                    this.value = '1';
                }
                updateAmounts();
            });
        });

        discountAmountInput.addEventListener('input', updateAmounts);
        amountPaidInput.addEventListener('input', function() {
            const total = parseFloat(totalAmountInput.value) || 0;
            const discount = parseFloat(discountAmountInput.value) || 0;
            const netTotal = total - discount;
            
            if (parseFloat(this.value) > netTotal) {
                alert('Amount paid cannot exceed the total amount after discount');
                this.value = netTotal.toFixed(2);
            }
            updateAmounts();
        });

        // Search functionality for tests
        const testSearch = document.getElementById('testSearch');
        const testRows = document.querySelectorAll('.test-row');

        testSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            testRows.forEach(row => {
                const testName = row.querySelector('td').textContent.toLowerCase();
                row.style.display = testName.includes(searchTerm) ? '' : 'none';
            });
        });

        // Search functionality for consumables
        const consumableSearch = document.getElementById('consumableSearch');
        const consumableRows = document.querySelectorAll('.consumable-row');

        consumableSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            consumableRows.forEach(row => {
                const consumableName = row.querySelector('td').textContent.toLowerCase();
                row.style.display = consumableName.includes(searchTerm) ? '' : 'none';
            });
        });

        function validateForm() {
            // Check patient information
            const patientName = document.getElementById('patient_name').value.trim();
            const patientContact = document.getElementById('patient_contact').value.trim();
            const patientAge = document.getElementById('patient_age').value.trim();
            const patientGender = document.getElementById('patient_gender').value;
            const referredBy = document.getElementById('referred_by').value.trim();
            const deliveryDate = document.getElementById('delivery_date').value;

            if (!patientName) {
                alert('Please enter patient name');
                return false;
            }
            if (!patientContact) {
                alert('Please enter contact number');
                return false;
            }
            if (!patientAge) {
                alert('Please enter patient age');
                return false;
            }
            if (!patientGender) {
                alert('Please select gender');
                return false;
            }
            if (!referredBy) {
                alert('Please enter who referred the patient');
                return false;
            }
            if (!deliveryDate) {
                alert('Please select delivery date');
                return false;
            }

            // Check if at least one test or consumable is selected
            const selectedTests = document.querySelectorAll('.test-checkbox:checked');
            const selectedConsumables = document.querySelectorAll('.consumable-checkbox:checked');
            
            if (selectedTests.length === 0 && selectedConsumables.length === 0) {
                alert('Please select at least one test or consumable');
                return false;
            }

            // Check total number of items (tests + consumables)
            const totalItems = selectedTests.length + selectedConsumables.length;
            if (totalItems > 15) {
                alert('Maximum 15 items (tests + consumables) can be selected. Please reduce the number of items.');
                return false;
            }

            // Validate consumable quantities
            consumableCheckboxes.forEach((checkbox, index) => {
                if (checkbox.checked) {
                    const quantity = parseInt(consumableQuantities[index].value);
                    if (isNaN(quantity) || quantity < 1) {
                        alert('Please enter a valid quantity for selected consumables');
                        return false;
                    }
                }
            });

            // Check payment information
            const amountPaid = document.getElementById('amount_paid').value.trim();
            const discountAmount = document.getElementById('discount_amount').value.trim();
            
            if (!amountPaid) {
                alert('Please enter amount paid');
                return false;
            }
            if (!discountAmount) {
                alert('Please enter discount amount');
                return false;
            }

            return true;
        }
    });
    </script>
</body>
</html>
