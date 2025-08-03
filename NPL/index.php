<?php
// Start session with basic security
session_start([
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Include configuration
require_once 'config/database.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: pages/user/" . ($_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : 'staff_dashboard.php'));
    exit();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password";
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                header("Location: pages/user/" . ($user['role'] === 'admin' ? 'admin_dashboard.php' : 'staff_dashboard.php'));
                exit();
            } else {
                $error = "Invalid username or password";
            }
        } catch(PDOException $e) {
            $error = "A system error occurred. Please try again later.";
        }
    }
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Niruponi Pathology Laboratory System - Login</title>
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Segoe UI', sans-serif;
      background: url('assets/image/dia-bg.avif') no-repeat center center/cover;
      height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .header {
      text-align: center;
      padding: 30px 20px 20px;
      color: #f9e8e8ff;
      text-shadow: 0 2px 5px rgba(0,0,0,0.6);
      font-size: 35px;
      font-weight: bold;
    }

    .main-container {
  flex: 1;
  display: flex;
  justify-content: flex-end;
  align-items: center;
  padding: 40px;
  padding-right: 15%;
}


    .login-panel {
      background: rgba(239, 230, 230, 0.96);
      padding: 40px;
      border-radius: 24px;
      box-shadow: 0 12px 30px rgba(0,0,0,0.2);
      max-width: 400px;
      width: 100%;
    }

    .login-panel h2 {
      color: #2c3e50;
      margin-bottom: 25px;
      font-size: 22px;
      text-align: center;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      margin-bottom: 6px;
      font-weight: 600;
      color: #34495e;
    }

    .form-group input {
      width: 100%;
      padding: 10px 14px;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 16px;
      transition: border-color 0.3s;
    }

    .form-group input:focus {
      border-color: #3498db;
      outline: none;
    }

    .error {
      background-color: #ffe6e6;
      border: 1px solid #e74c3c;
      color: #e74c3c;
      padding: 10px;
      border-radius: 6px;
      margin-bottom: 20px;
      text-align: center;
    }

    button[type="submit"] {
      width: 100%;
      background: #3498db;
      color: #fff;
      padding: 12px;
      border: none;
      border-radius: 6px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.3s ease-in-out;
    }

    button[type="submit"]:hover {
      background: #2980b9;
    }

    @media (max-width: 768px) {
      .main-container {
        justify-content: center;
        padding: 20px;
      }

      .header {
        font-size: 22px;
        padding-top: 20px;
      }

      .login-panel {
        padding: 30px 20px;
      }
    }
  </style>
</head>
<body>

  <div class="header">
    Niruponi Pathology Laboratory System
  </div>

  <div class="main-container">
    <div class="login-panel">
      <h2>Login to Continue</h2>
      <?php if (isset($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        <div class="form-group">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" required />
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required />
        </div>
        <button type="submit">Login</button>
      </form>
    </div>
  </div>

</body>
</html>



