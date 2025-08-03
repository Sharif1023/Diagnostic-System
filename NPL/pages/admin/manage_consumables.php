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
                    $stmt = $pdo->prepare("
                        INSERT INTO consumable_info (consumable_code, name, price, unit)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['consumable_code'],
                        $_POST['name'],
                        $_POST['price'],
                        $_POST['unit']
                    ]);
                    $success = "Consumable added successfully";
                    break;
                    
                case 'edit':
                    $stmt = $pdo->prepare("
                        UPDATE consumable_info 
                        SET name = ?, price = ?, unit = ?
                        WHERE consumable_code = ?
                    ");
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['price'],
                        $_POST['unit'],
                        $_POST['consumable_code']
                    ]);
                    $success = "Consumable updated successfully";
                    break;
                    
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM consumable_info WHERE consumable_code = ?");
                    $stmt->execute([$_POST['consumable_code']]);
                    $success = "Consumable deleted successfully";
                    break;
            }
        }
    }
    
    // Get all consumables
    $stmt = $pdo->query("SELECT * FROM consumable_info ORDER BY name");
    $consumables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error = "An error occurred while managing consumables.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Consumables - Niruponi Pathology Laboratory System</title>
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
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
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
        .btn-back {
            background: #95a5a6;
            color: white;
        }
        .btn-back:hover {
            background: #7f8c8d;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .data-table tr:hover {
            background: #f5f5f5;
        }
        .search-box {
            margin-bottom: 20px;
        }
        .search-box input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h2>Manage Consumables</h2>
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
            <h2>Add New Consumable</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label for="consumable_code">Consumable Code</label>
                    <input type="text" id="consumable_code" name="consumable_code" required>
                </div>
                <div class="form-group">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="price">Price</label>
                    <input type="number" id="price" name="price" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="unit">Unit</label>
                    <input type="text" id="unit" name="unit" required>
                </div>
                <button type="submit" class="btn btn-primary">Add Consumable</button>
            </form>
        </div>

        <div class="recent-items">
            <h2>Existing Consumables</h2>
            <div class="search-box">
                <input type="text" id="searchConsumables" placeholder="Search consumables..." onkeyup="searchConsumables()">
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Unit</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="consumablesTableBody">
                    <?php foreach ($consumables as $consumable): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($consumable['consumable_code']); ?></td>
                        <td><?php echo htmlspecialchars($consumable['name']); ?></td>
                        <td>৳<?php echo number_format($consumable['price'], 2); ?></td>
                        <td><?php echo htmlspecialchars($consumable['unit']); ?></td>
                        <td>
                            <div class="action-buttons">
                                <button onclick="openEditModal('<?php echo htmlspecialchars(json_encode($consumable)); ?>')" 
                                        class="btn btn-primary">Edit</button>
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="consumable_code" value="<?php echo htmlspecialchars($consumable['consumable_code']); ?>">
                                    <button type="submit" class="btn btn-danger" 
                                            onclick="return confirm('Are you sure you want to delete this consumable?')">
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

    <!-- Edit Consumable Modal -->
    <div id="editConsumableModal" class="modal" role="dialog" aria-labelledby="editConsumableTitle" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="editConsumableTitle">Edit Consumable</h3>
            </div>
            <form method="POST" action="" id="editConsumableForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="consumable_code" id="editConsumableCode">
                <div class="form-group">
                    <label for="editName">Name</label>
                    <input type="text" id="editName" name="name" required>
                </div>
                <div class="form-group">
                    <label for="editPrice">Price</label>
                    <input type="number" id="editPrice" name="price" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="editUnit">Unit</label>
                    <input type="text" id="editUnit" name="unit" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Update</button>
                    <button type="button" class="btn btn-danger" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(consumableData) {
            const consumable = JSON.parse(consumableData);
            document.getElementById('editConsumableCode').value = consumable.consumable_code;
            document.getElementById('editName').value = consumable.name;
            document.getElementById('editPrice').value = consumable.price;
            document.getElementById('editUnit').value = consumable.unit;
            document.getElementById('editConsumableModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editConsumableModal').style.display = 'none';
        }

        function searchConsumables() {
            const input = document.getElementById('searchConsumables');
            const filter = input.value.toUpperCase();
            const table = document.getElementById('consumablesTableBody');
            const tr = table.getElementsByTagName('tr');

            for (let i = 0; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName('td');
                let found = false;
                for (let j = 0; j < td.length - 1; j++) {
                    if (td[j]) {
                        const txtValue = td[j].textContent || td[j].innerText;
                        if (txtValue.toUpperCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                tr[i].style.display = found ? '' : 'none';
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editConsumableModal');
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
        const modal = document.getElementById('editConsumableModal');
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
    </script>
</body>
</html> 