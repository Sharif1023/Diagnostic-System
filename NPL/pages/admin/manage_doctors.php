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

// Include database configuration
require_once '../../config/database.php';
require_once '../../includes/doctor_functions.php';

// Get database connection
$pdo = getDBConnection();

// Add CSRF token generation at the top
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Function to validate doctor name
function validateDoctorName($name) {
    $name = trim($name);
    if (empty($name)) {
        return false;
    }
    // Remove "Dr." prefix if present and validate
    $name = preg_replace('/^Dr\.\s*/i', '', $name);
    return preg_match('/^[a-zA-Z\s\.]+$/', $name);
}

// Function to validate qualifications
function validateQualifications($qualifications) {
    return !empty(trim($qualifications));
}

// Function to validate designation
function validateDesignation($designation) {
    return !empty(trim($designation));
}

// Function to validate workplace
function validateWorkplace($workplace) {
    return !empty(trim($workplace));
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid request');
        }

        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    addDoctor(
                        $_POST['name'],
                        $_POST['qualifications'],
                        $_POST['designation'],
                        $_POST['workplace']
                    );
                    break;
                
                case 'edit':
                    updateDoctor(
                        $_POST['id'],
                        $_POST['name'],
                        $_POST['qualifications'],
                        $_POST['designation'],
                        $_POST['workplace']
                    );
                    break;
                
                case 'delete':
                    deleteDoctor($_POST['id']);
                    break;
            }
            header('Location: manage_doctors.php');
            exit();
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Fetch all doctors
try {
    $doctors = getAllDoctors();
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Doctors - Laboratory Management System</title>
    <link rel="stylesheet" href="../../assets/css/common.css">
    <style>
        .doctor-card {
            background: #fff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .doctor-card h3 {
            margin: 0 0 10px 0;
            color: #333;
        }
        .doctor-info {
            margin-bottom: 10px;
        }
        .doctor-actions {
            display: flex;
            gap: 10px;
        }
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
        .btn-info {
            background: #2ecc71;
            color: white;
        }
        .btn-info:hover {
            background: #27ae60;
        }
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        .btn-danger:hover {
            background: #c0392b;
        }
        .btn:focus {
            outline: 2px solid #4a90e2;
            outline-offset: 2px;
        }
        .form-group input:focus {
            outline: 2px solid #4a90e2;
            outline-offset: 2px;
        }
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h2>Manage Doctors</h2>
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
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3>Add New Doctor</h3>
            </div>
            <form method="POST" action="" id="addDoctorForm" aria-labelledby="addDoctorTitle">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="add">
                <div class="grid grid-2">
                    <div class="form-group">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" required 
                               placeholder="Enter doctor's name (without Dr. prefix)"
                               aria-required="true">
                    </div>
                    <div class="form-group">
                        <label for="qualifications">Qualifications</label>
                        <input type="text" id="qualifications" name="qualifications" required 
                               placeholder="e.g., MBBS, MD"
                               aria-required="true">
                    </div>
                    <div class="form-group">
                        <label for="designation">Designation</label>
                        <input type="text" id="designation" name="designation" required 
                               placeholder="e.g., Senior Consultant"
                               aria-required="true">
                    </div>
                    <div class="form-group">
                        <label for="workplace">Workplace</label>
                        <input type="text" id="workplace" name="workplace" required 
                               placeholder="e.g., City Hospital"
                               aria-required="true">
                    </div>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" aria-label="Add new doctor">Add Doctor</button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Existing Doctors</h3>
            </div>
            <div class="search-box">
                <label for="searchDoctors" class="sr-only">Search doctors</label>
                <input type="text" id="searchDoctors" placeholder="Search doctors..."
                       aria-label="Search doctors">
            </div>
            <div id="doctorsList" role="list">
                <?php foreach ($doctors as $doctor): ?>
                <div class="doctor-card" data-id="<?php echo $doctor['id']; ?>" role="listitem">
                    <h3><?php echo htmlspecialchars($doctor['name']); ?></h3>
                    <div class="doctor-info">
                        <p><strong>Qualifications:</strong> <?php echo htmlspecialchars($doctor['qualifications']); ?></p>
                        <p><strong>Designation:</strong> <?php echo htmlspecialchars($doctor['designation']); ?></p>
                        <p><strong>Workplace:</strong> <?php echo htmlspecialchars($doctor['workplace']); ?></p>
                    </div>
                    <div class="doctor-actions">
                        <button class="btn btn-primary edit-doctor" 
                                data-doctor='<?php echo htmlspecialchars(json_encode($doctor)); ?>'
                                aria-label="Edit doctor <?php echo htmlspecialchars($doctor['name']); ?>"
                                onclick="openEditModal('<?php echo htmlspecialchars(json_encode($doctor)); ?>')">
                            Edit
                        </button>
                        <a href="doctor_statistics.php?id=<?php echo $doctor['id']; ?>" 
                           class="btn btn-info"
                           aria-label="View statistics for doctor <?php echo htmlspecialchars($doctor['name']); ?>">
                            Statistics
                        </a>
                        <form method="POST" action="" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $doctor['id']; ?>">
                            <button type="submit" class="btn btn-danger delete-doctor" 
                                    onclick="return confirm('Are you sure you want to delete Dr. <?php echo htmlspecialchars($doctor['name']); ?>?')"
                                    aria-label="Delete doctor <?php echo htmlspecialchars($doctor['name']); ?>">
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Edit Doctor Modal -->
    <div id="editDoctorModal" class="modal" role="dialog" aria-labelledby="editDoctorTitle" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="editDoctorTitle">Edit Doctor</h3>
            </div>
            <form method="POST" action="" id="editDoctorForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editDoctorId">
                <div class="form-group">
                    <label for="editName">Name</label>
                    <input type="text" id="editName" name="name" required aria-required="true">
                </div>
                <div class="form-group">
                    <label for="editQualifications">Qualifications</label>
                    <input type="text" id="editQualifications" name="qualifications" required aria-required="true">
                </div>
                <div class="form-group">
                    <label for="editDesignation">Designation</label>
                    <input type="text" id="editDesignation" name="designation" required aria-required="true">
                </div>
                <div class="form-group">
                    <label for="editWorkplace">Workplace</label>
                    <input type="text" id="editWorkplace" name="workplace" required aria-required="true">
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Update</button>
                    <button type="button" class="btn btn-danger" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchDoctors').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const doctors = document.querySelectorAll('.doctor-card');
            
            doctors.forEach(doctor => {
                const text = doctor.textContent.toLowerCase();
                doctor.style.display = text.includes(searchTerm) ? 'block' : 'none';
            });
        });

        function openEditModal(doctorData) {
            const doctor = JSON.parse(doctorData);
            document.getElementById('editDoctorId').value = doctor.id;
            document.getElementById('editName').value = doctor.name;
            document.getElementById('editQualifications').value = doctor.qualifications;
            document.getElementById('editDesignation').value = doctor.designation;
            document.getElementById('editWorkplace').value = doctor.workplace;
            document.getElementById('editDoctorModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editDoctorModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editDoctorModal');
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
        const modal = document.getElementById('editDoctorModal');
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