<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include role checking
require_once '../../config/role_check.php';

// Check if user is admin
checkRole('admin');

// Include database configuration
require_once '../../config/database.php';

// Get database connection
$pdo = getDBConnection();

$error = null;
$success = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_user'])) {
        // Handle user deletion
        $user_id = $_POST['user_id'];
        try {
            // Prevent deleting the last admin
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
            $stmt->execute();
            $adminCount = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $userRole = $stmt->fetchColumn();
            
            if ($userRole === 'admin' && $adminCount <= 1) {
                $error = "Cannot delete the last admin user";
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $success = "User deleted successfully";
            }
        } catch(PDOException $e) {
            $error = "Error deleting user: " . $e->getMessage();
        }
    } elseif (isset($_POST['update_password'])) {
        // Handle password update
        $user_id = $_POST['user_id'];
        $new_password = $_POST['new_password'];
        
        if (empty($new_password)) {
            $error = "New password cannot be empty";
        } else {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                $success = "Password updated successfully";
            } catch(PDOException $e) {
                $error = "Error updating password: " . $e->getMessage();
            }
        }
    } else {
        // Existing user creation code
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'staff';

        // Validate input
        if (empty($username) || empty($password)) {
            $error = "Please fill in all fields";
        } else {
            try {
                // Check if username already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $error = "Username already exists";
                } else {
                    // Hash the password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // Insert new user
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                    $stmt->execute([$username, $hashed_password, $role]);
                    $success = "User created successfully";
                }
            } catch(PDOException $e) {
                $error = "Error creating user: " . $e->getMessage();
            }
        }
    }
}

// Fetch all users
try {
    $stmt = $pdo->query("SELECT id, username, role FROM users ORDER BY username");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Error fetching users: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Niruponi Pathology Laboratory System</title>
    <link rel="stylesheet" href="../../assets/css/common.css">
    <style>
        .user-form {
            max-width: 500px;
            margin: 0 auto 2rem;
        }
        .user-list {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .user-list table {
            width: 100%;
            border-collapse: collapse;
        }
        .user-list th, .user-list td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .user-list th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .user-list tr:hover {
            background: #f8f9fa;
        }
        .role-admin {
            color: #e74c3c;
            font-weight: bold;
        }
        .role-staff {
            color: #3498db;
            font-weight: bold;
        }
        .delete-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .delete-btn:hover {
            background-color: #c0392b;
        }
        .update-btn {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-right: 0.5rem;
        }
        .update-btn:hover {
            background-color: #2980b9;
        }
        .action-cell {
            text-align: center;
        }
        .password-input {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h2>Manage Users</h2>
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

        <?php if ($success): ?>
            <div class="alert-message alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3>Create New User</h3>
            </div>
            <form method="POST" class="user-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" required>
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Create User</button>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Existing Users</h3>
            </div>
            <div class="user-list">
                <table>
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Role</th>
                            <th class="action-cell">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td>
                                    <span class="role-<?php echo htmlspecialchars($user['role']); ?>">
                                        <?php echo htmlspecialchars(ucfirst($user['role'])); ?>
                                    </span>
                                </td>
                                <td class="action-cell">
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="delete_user" value="1">
                                        <button type="submit" class="delete-btn">Delete</button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="update_password" value="1">
                                        <input type="password" name="new_password" class="password-input" placeholder="New password" required>
                                        <button type="submit" class="update-btn">Update Password</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html> 