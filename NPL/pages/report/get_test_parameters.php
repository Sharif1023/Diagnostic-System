<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Include database configuration
require_once '../../config/database.php';

// Get database connection
$pdo = getDBConnection();

// Get test code from POST request
$test_code = $_POST['test_code'] ?? '';

if (empty($test_code)) {
    http_response_code(400);
    echo json_encode(['error' => 'Test code is required']);
    exit();
}

try {
    // Get test parameters
    $stmt = $pdo->prepare("
        SELECT id, parameter_name, unit, reference_range
        FROM test_parameters
        WHERE test_code = ?
        ORDER BY display_order
    ");
    $stmt->execute([$test_code]);
    $parameters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return parameters as JSON
    header('Content-Type: application/json');
    echo json_encode($parameters);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} 