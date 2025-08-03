<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include role checking
require_once '../../config/role_check.php';

// Check if user is logged in (staff or admin)
checkLogin();

// Include database configuration
require_once '../../config/database.php';

// ... rest of the existing code ... 