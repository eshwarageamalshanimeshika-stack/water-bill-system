<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If not logged in → redirect to login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Detect current file path
$currentFile = $_SERVER['PHP_SELF'];

// Restrict access for Admin pages
if (strpos($currentFile, '/admin/') !== false && $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php?error=unauthorized");
    exit();
}

// Restrict access for Customer pages
if (strpos($currentFile, '/customer/') !== false && $_SESSION['role'] !== 'customer') {
    header("Location: ../auth/login.php?error=unauthorized");
    exit();
}