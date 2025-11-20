<?php
// start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent direct access to this file
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header("Location: ../index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Water Bill Management System</title>
    <link rel="stylesheet" href="../assets/css/style.css" />
    <style>
        /* Navbar styling */
        .site-header {
            background-color: #1e1e2f; /* modern dark background */
            padding: 15px 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.4);
        }
        .navbar {
            display: flex;
            align-items: center;
            justify-content: center; /* center content horizontally */
        }
        .logo a {
            font-size: 24px; /* slightly bigger */
            font-weight: bold;
            color: #f1f1f1; /* modern light text */
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .logo a:hover {
            color: #4dabf7; /* light blue hover */
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="navbar">
            <div class="logo">
                <a href="../index.php">💧 Water Bill Management System</a>
            </div>
        </div>
    </header>
