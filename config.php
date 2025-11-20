<?php
// Database configuration
$host = "localhost";      // Server host (XAMPP default: localhost)
$user = "root";           // MySQL username (XAMPP default: root)
$password = "";           // MySQL password (XAMPP default: empty)
$database = "water_bill_db"; // Your database name

// Create connection
$conn = new mysqli($host, $user, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// Optional: Set character encoding to UTF-8
//$conn->set_charset("utf8");
$conn->set_charset("utf8");
?>