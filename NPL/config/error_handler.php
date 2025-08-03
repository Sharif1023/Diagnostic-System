<?php
// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Custom error handler
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $error = date('Y-m-d H:i:s') . " - Error [$errno]: $errstr in $errfile on line $errline\n";
    error_log($error, 3, __DIR__ . '/../logs/error.log');
    
    if (ini_get('display_errors')) {
        echo "<div class='error-message'>An error occurred. Please try again later.</div>";
    }
    
    return true;
}

// Custom exception handler
function customExceptionHandler($exception) {
    $error = date('Y-m-d H:i:s') . " - Exception: " . $exception->getMessage() . 
             " in " . $exception->getFile() . " on line " . $exception->getLine() . "\n";
    error_log($error, 3, __DIR__ . '/../logs/error.log');
    
    if (ini_get('display_errors')) {
        echo "<div class='error-message'>An error occurred. Please try again later.</div>";
    }
}

// Set error handlers
set_error_handler('customErrorHandler');
set_exception_handler('customExceptionHandler');

// Function to log specific actions
function logAction($action, $details = '') {
    $log = date('Y-m-d H:i:s') . " - Action: $action";
    if ($details) {
        $log .= " - Details: $details";
    }
    $log .= "\n";
    error_log($log, 3, __DIR__ . '/../logs/action.log');
}

// Function to log database errors
function logDatabaseError($error, $query = '') {
    $log = date('Y-m-d H:i:s') . " - Database Error: $error";
    if ($query) {
        $log .= " - Query: $query";
    }
    $log .= "\n";
    error_log($log, 3, __DIR__ . '/../logs/database.log');
}

// Function to log security events
function logSecurityEvent($event, $details = '') {
    $log = date('Y-m-d H:i:s') . " - Security Event: $event";
    if ($details) {
        $log .= " - Details: $details";
    }
    $log .= "\n";
    error_log($log, 3, __DIR__ . '/../logs/security.log');
} 