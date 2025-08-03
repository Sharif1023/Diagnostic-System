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

// Include database connection
require_once '../../config/database.php';

try {
    $pdo = getDBConnection();
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $pdo->beginTransaction();
                    try {
                        // Insert test info
                        $stmt = $pdo->prepare("
                            INSERT INTO tests_info (test_code, test_name, price, category, description)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $_POST['test_code'],
                            $_POST['test_name'],
                            $_POST['price'],
                            $_POST['category'],
                            $_POST['description'] ?? ''
                        ]);

                        // Insert test parameters
                        if (isset($_POST['parameters']) && is_array($_POST['parameters'])) {
                            $stmt = $pdo->prepare("
                                INSERT INTO test_parameters (test_code, parameter_name, unit, normal_range, sort_order)
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            foreach ($_POST['parameters'] as $index => $param) {
                                $stmt->execute([
                                    $_POST['test_code'],
                                    $param['name'],
                                    $param['unit'],
                                    $param['range'],
                                    $index + 1
                                ]);
                            }
                        }
                        $pdo->commit();
                        $success = "Test added successfully";
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        throw $e;
                    }
                    break;
                    
                case 'edit':
                    $pdo->beginTransaction();
                    try {
                        // Update test info
                        $stmt = $pdo->prepare("
                            UPDATE tests_info 
                            SET test_name = ?, price = ?, category = ?, description = ?
                            WHERE test_code = ?
                        ");
                        $stmt->execute([
                            $_POST['test_name'],
                            $_POST['price'],
                            $_POST['category'],
                            $_POST['description'] ?? '',
                            $_POST['test_code']
                        ]);

                        // Delete all existing parameters for this test
                        $stmt = $pdo->prepare("DELETE FROM test_parameters WHERE test_code = ?");
                        $stmt->execute([$_POST['test_code']]);

                        // Insert new parameters
                        if (isset($_POST['parameters']) && is_array($_POST['parameters'])) {
                            $stmt = $pdo->prepare("
                                INSERT INTO test_parameters (test_code, parameter_name, unit, normal_range, sort_order)
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            foreach ($_POST['parameters'] as $index => $param) {
                                // Ensure empty strings for unit and range if not set
                                $unit = isset($param['unit']) ? trim($param['unit']) : '';
                                $range = isset($param['range']) ? trim($param['range']) : '';
                                
                                $stmt->execute([
                                    $_POST['test_code'],
                                    trim($param['name']),
                                    $unit,
                                    $range,
                                    $index + 1
                                ]);
                            }
                        }
                        $pdo->commit();
                        $success = "Test updated successfully";
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        throw $e;
                    }
                    break;
                    
                case 'delete':
                    $pdo->beginTransaction();
                    try {
                        // Delete parameters first (due to foreign key constraint)
                        $stmt = $pdo->prepare("DELETE FROM test_parameters WHERE test_code = ?");
                        $stmt->execute([$_POST['test_code']]);
                        
                        // Delete test
                        $stmt = $pdo->prepare("DELETE FROM tests_info WHERE test_code = ?");
                        $stmt->execute([$_POST['test_code']]);
                        
                        $pdo->commit();
                        $success = "Test deleted successfully";
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        throw $e;
                    }
                    break;
            }
        }
    }
    
    // Get all tests with their parameters
    $stmt = $pdo->query("
        SELECT ti.*, 
               GROUP_CONCAT(
                   CONCAT_WS('|', tp.parameter_name, tp.unit, tp.normal_range)
                   ORDER BY tp.sort_order
               ) as parameters
        FROM tests_info ti
        LEFT JOIN test_parameters tp ON ti.test_code = tp.test_code
        GROUP BY ti.test_code
        ORDER BY ti.test_name
    ");
    $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error = "An error occurred while managing tests.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Tests - Niruponi Pathology Laboratory System</title>
    <link rel="stylesheet" href="../../assets/css/common.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        .modal-content {
            background: #fff;
            width: 90%;
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }
        .modal-content::-webkit-scrollbar {
            width: 8px;
        }
        .modal-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        .modal-content::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        .modal-content::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        .modal-header {
            position: sticky;
            top: 0;
            background: #fff;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            margin-bottom: 15px;
        }
        .modal-footer {
            position: sticky;
            bottom: 0;
            background: #fff;
            padding: 15px 0;
            border-top: 1px solid #eee;
            margin-top: 15px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
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
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        .btn-danger:hover {
            background: #c0392b;
        }
        /* New styles for parameter rows */
        .parameter-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 100px;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .parameter-field {
            width: 100%;
        }
        .parameter-field input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .parameter-actions {
            display: flex;
            justify-content: center;
        }
        .parameters-container {
            margin-bottom: 20px;
        }
        .parameters-header {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 100px;
            gap: 10px;
            margin-bottom: 10px;
            padding: 0 10px;
        }
        .parameters-header div {
            font-weight: 600;
            color: #666;
            font-size: 0.9em;
        }
        .parameters-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .parameters-section-header h3 {
            margin: 0;
        }
        /* Add search box styles */
        .search-box {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        .search-box input {
            flex: 1;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .search-box select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: white;
        }
        .description-cell {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .description-cell:hover {
            white-space: normal;
            overflow: visible;
            position: relative;
            z-index: 1;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 4px;
            padding: 8px;
        }
        .empty-description {
            color: #999;
            font-style: italic;
        }
        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .form-header h2 {
            margin: 0;
        }
        .parameter-actions {
            display: flex;
            gap: 10px;
        }
        .parameter-actions button {
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h2>Manage Tests</h2>
            </div>
            <div>
                <a href="/NPL/pages/user/admin_dashboard.php" class="btn btn-back">
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

        <div class="form-section">
            <div class="form-header">
                <h2>Add New Test</h2>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                <div class="form-header">
                    <div></div>
                    <button type="submit" class="btn btn-primary">Add Test</button>
                </div>
                <div class="form-group">
                    <label for="test_code">Test Code</label>
                    <input type="text" id="test_code" name="test_code" required>
                </div>
                <div class="form-group">
                    <label for="test_name">Test Name</label>
                    <input type="text" id="test_name" name="test_name" required>
                </div>
                <div class="form-group">
                    <label for="description">Description (Optional)</label>
                    <textarea id="description" name="description" placeholder="Enter test description"></textarea>
                </div>
                <div class="form-group">
                    <label for="category">Category</label>
                    <select id="category" name="category" required>
                        <option value="">Select Category</option>
                        <option value="ICT">ICT</option>
                        <option value="Blood">Blood</option>
                        <option value="Urine">Urine</option>
                        <option value="Stool">Stool</option>
                        <option value="Microbiology">Microbiology</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="price">Price</label>
                    <input type="number" id="price" name="price" step="0.01" required>
                </div>
                <div class="form-group">
                    <div class="parameters-section-header">
                        <h3>Test Parameters</h3>
                    </div>
                    <div class="parameters-header">
                        <div>Parameter Name</div>
                        <div>Unit</div>
                        <div>Normal Range</div>
                        <div class="action-header">Actions</div>
                    </div>
                    <div id="parameters-container">
                        <div class="parameter-row">
                            <div class="parameter-field">
                                <input type="text" name="parameters[0][name]" placeholder="Enter parameter name" required>
                            </div>
                            <div class="parameter-field">
                                <input type="text" name="parameters[0][unit]" placeholder="Enter unit (optional)">
                            </div>
                            <div class="parameter-field">
                                <input type="text" name="parameters[0][range]" placeholder="Enter normal range (optional)">
                            </div>
                            <div class="parameter-actions">
                                <button type="button" class="btn btn-danger remove-param" onclick="removeParameter(this)">Remove</button>
                            </div>
                        </div>
                    </div>
                    <div class="parameter-actions" style="margin-top: 15px; justify-content: flex-start;">
                        <button type="button" class="btn btn-primary" onclick="addParameter()">Add Parameter</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="recent-items">
            <h2>Existing Tests</h2>
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search tests..." onkeyup="searchTests()">
                <select id="categoryFilter" onchange="searchTests()">
                    <option value="">All Categories</option>
                    <option value="ICT">ICT</option>
                    <option value="Blood">Blood</option>
                    <option value="Urine">Urine</option>
                    <option value="Stool">Stool</option>
                    <option value="Microbiology">Microbiology</option>
                </select>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Description</th>
                        <th>Parameters</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="testsTableBody">
                    <?php foreach ($tests as $test): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($test['test_code']); ?></td>
                        <td><?php echo htmlspecialchars($test['test_name']); ?></td>
                        <td><?php echo htmlspecialchars($test['category']); ?></td>
                        <td>৳<?php echo number_format($test['price'], 2); ?></td>
                        <td class="description-cell">
                            <?php if (empty($test['description'])): ?>
                                <span class="empty-description">No description</span>
                            <?php else: ?>
                                <?php echo htmlspecialchars($test['description']); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($test['parameters'])): ?>
                                <ul>
                                    <?php 
                                    $params = explode(',', $test['parameters']);
                                    foreach ($params as $param) {
                                        list($name, $unit, $range) = explode('|', $param);
                                        echo "<li>" . htmlspecialchars($name);
                                        if (!empty($unit)) {
                                            echo " (" . htmlspecialchars($unit) . ")";
                                        }
                                        if (!empty($range)) {
                                            echo ": " . htmlspecialchars($range);
                                        }
                                        echo "</li>";
                                    }
                                    ?>
                                </ul>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button onclick="openEditModal('<?php echo htmlspecialchars(json_encode($test)); ?>')" 
                                        class="btn btn-primary">Edit</button>
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="test_code" value="<?php echo htmlspecialchars($test['test_code']); ?>">
                                    <button type="submit" class="btn btn-danger" 
                                            onclick="return confirm('Are you sure you want to delete this test?')">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit Test Modal -->
    <div id="editTestModal" class="modal" role="dialog" aria-labelledby="editTestTitle" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="editTestTitle">Edit Test</h3>
            </div>
            <form method="POST" action="" id="editTestForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="test_code" id="editTestCode">
                <div class="form-group">
                    <label for="editTestName">Test Name</label>
                    <input type="text" id="editTestName" name="test_name" required>
                </div>
                <div class="form-group">
                    <label for="editDescription">Description (Optional)</label>
                    <textarea id="editDescription" name="description" placeholder="Enter test description"></textarea>
                </div>
                <div class="form-group">
                    <label for="editCategory">Category</label>
                    <select id="editCategory" name="category" required>
                        <option value="ICT">ICT</option>
                        <option value="Blood">Blood</option>
                        <option value="Urine">Urine</option>
                        <option value="Stool">Stool</option>
                        <option value="Microbiology">Microbiology</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="editPrice">Price</label>
                    <input type="number" id="editPrice" name="price" step="0.01" required>
                </div>
                <div class="form-group">
                    <div class="parameters-section-header">
                        <h3>Test Parameters</h3>
                        <button type="button" class="btn btn-primary" onclick="addEditParameter()">Add Parameter</button>
                    </div>
                    <div class="parameters-header">
                        <div>Parameter Name</div>
                        <div>Unit</div>
                        <div>Normal Range</div>
                        <div class="action-header">Actions</div>
                    </div>
                    <div id="edit-parameters-container">
                        <!-- Parameters will be added here dynamically -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Update Test</button>
                    <button type="button" class="btn btn-danger" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let parameterCount = 1;
        
        function addParameter() {
            const container = document.getElementById('parameters-container');
            const row = document.createElement('div');
            row.className = 'parameter-row';
            row.innerHTML = `
                <div class="parameter-field">
                    <input type="text" name="parameters[${parameterCount}][name]" placeholder="Enter parameter name" required>
                </div>
                <div class="parameter-field">
                    <input type="text" name="parameters[${parameterCount}][unit]" placeholder="Enter unit (optional)">
                </div>
                <div class="parameter-field">
                    <input type="text" name="parameters[${parameterCount}][range]" placeholder="Enter normal range (optional)">
                </div>
                <div class="parameter-actions">
                    <button type="button" class="btn btn-danger remove-param" onclick="removeParameter(this)">Remove</button>
                </div>
            `;
            container.appendChild(row);
            parameterCount++;
        }
        
        function addEditParameter() {
            const container = document.getElementById('edit-parameters-container');
            const row = document.createElement('div');
            row.className = 'parameter-row';
            row.innerHTML = `
                <div class="parameter-field">
                    <input type="text" name="parameters[${parameterCount}][name]" placeholder="Enter parameter name" required>
                </div>
                <div class="parameter-field">
                    <input type="text" name="parameters[${parameterCount}][unit]" placeholder="Enter unit (optional)">
                </div>
                <div class="parameter-field">
                    <input type="text" name="parameters[${parameterCount}][range]" placeholder="Enter normal range (optional)">
                </div>
                <div class="parameter-actions">
                    <button type="button" class="btn btn-danger remove-param" onclick="removeParameter(this)">Remove</button>
                </div>
            `;
            container.appendChild(row);
            parameterCount++;
        }
        
        function removeParameter(button) {
            const row = button.closest('.parameter-row');
            row.remove();
            
            // Reindex remaining parameters
            const container = row.parentElement;
            const rows = container.getElementsByClassName('parameter-row');
            Array.from(rows).forEach((row, index) => {
                const inputs = row.getElementsByTagName('input');
                inputs[0].name = `parameters[${index}][name]`;
                inputs[1].name = `parameters[${index}][unit]`;
                inputs[2].name = `parameters[${index}][range]`;
            });
        }
        
        function openEditModal(testData) {
            const test = JSON.parse(testData);
            document.getElementById('editTestCode').value = test.test_code;
            document.getElementById('editTestName').value = test.test_name;
            document.getElementById('editPrice').value = test.price;
            document.getElementById('editCategory').value = test.category;
            document.getElementById('editDescription').value = test.description || '';
            
            // Clear existing parameters
            const container = document.getElementById('edit-parameters-container');
            container.innerHTML = '';
            parameterCount = 0;
            
            // Add parameters if they exist
            if (test.parameters) {
                const params = test.parameters.split(',');
                params.forEach((param, index) => {
                    const [name, unit, range] = param.split('|');
                    const row = document.createElement('div');
                    row.className = 'parameter-row';
                    row.innerHTML = `
                        <div class="parameter-field">
                            <input type="text" name="parameters[${index}][name]" value="${name}" required>
                        </div>
                        <div class="parameter-field">
                            <input type="text" name="parameters[${index}][unit]" value="${unit || ''}" placeholder="Enter unit (optional)">
                        </div>
                        <div class="parameter-field">
                            <input type="text" name="parameters[${index}][range]" value="${range || ''}" placeholder="Enter normal range (optional)">
                        </div>
                        <div class="parameter-actions">
                            <button type="button" class="btn btn-danger remove-param" onclick="removeParameter(this)">Remove</button>
                        </div>
                    `;
                    container.appendChild(row);
                    parameterCount = index + 1;
                });
            }
            
            document.getElementById('editTestModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editTestModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editTestModal');
            if (event.target === modal) {
                closeEditModal();
            }
        }

        // Add keyboard navigation for modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEditModal();
            }
        });

        // Focus trap for modal
        const modal = document.getElementById('editTestModal');
        const focusableElements = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        const firstFocusableElement = focusableElements[0];
        const lastFocusableElement = focusableElements[focusableElements.length - 1];

        modal.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
                if (e.shiftKey) {
                    if (document.activeElement === firstFocusableElement) {
                        e.preventDefault();
                        lastFocusableElement.focus();
                    }
                } else {
                    if (document.activeElement === lastFocusableElement) {
                        e.preventDefault();
                        firstFocusableElement.focus();
                    }
                }
            }
        });

        // Add search functionality
        function searchTests() {
            const searchText = document.getElementById('searchInput').value.toLowerCase();
            const categoryFilter = document.getElementById('categoryFilter').value;
            const rows = document.querySelectorAll('#testsTableBody tr');
            
            rows.forEach(row => {
                const code = row.cells[0].textContent.toLowerCase();
                const name = row.cells[1].textContent.toLowerCase();
                const category = row.cells[2].textContent;
                
                const matchesSearch = code.includes(searchText) || name.includes(searchText);
                const matchesCategory = !categoryFilter || category === categoryFilter;
                
                row.style.display = matchesSearch && matchesCategory ? '' : 'none';
            });
        }
    </script>
</body>
</html> 