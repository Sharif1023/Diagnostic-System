<?php
// Include config file
require_once __DIR__ . '/config.php';

// Function to check if user is logged in
function checkLogin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . 'index.php');
        exit();
    }
}

// Function to check if user has required role
function checkRole($required_role) {
    checkLogin();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
        header('Location: ' . BASE_URL . 'pages/user/dashboard.php');
        exit();
    }
}

// Function to check if user has one of the allowed roles
function checkRoles($allowed_roles) {
    checkLogin();
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
        header('Location: ' . BASE_URL . 'pages/user/dashboard.php');
        exit();
    }
}
?> 