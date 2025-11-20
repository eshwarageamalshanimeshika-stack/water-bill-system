<?php
session_start();

// If user already logged in, redirect to dashboard
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/dashboard.php");
        exit();
    } elseif ($_SESSION['role'] === 'customer') {
        header("Location: customer/dashboard.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Water Bill Management System</title>
<link rel="stylesheet" href="assets/css/style.css">

<style>
    /* Reset & dark theme */
    html, body {
        margin: 0;
        padding: 0;
        height: 100%;
        font-family: Arial, sans-serif;
        background-color: #121212;
        color: #e0e0e0;
        display: flex;
        flex-direction: column;
    }

    header {
        background: #0D47A1;
        padding: 20px;
        text-align: center;
        color: white;
        box-shadow: 0 4px 8px rgba(0,0,0,0.4);
    }

    header h1 { margin: 0; font-size: 28px; }
    header p { margin: 5px 0 0; font-size: 16px; color: #BBDEFB; }

    main {
        flex: 1; /* Takes remaining space */
        text-align: center;
        padding: 60px 20px;
    }

    main h2 { font-size: 24px; color: #90CAF9; }

    .btn {
        display: inline-block;
        padding: 14px 30px;
        margin: 15px;
        border-radius: 8px;
        font-size: 16px;
        font-weight: bold;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .btn-primary {
        background-color: #0D47A1;
        color: #fff;
    }

    .btn-primary:hover {
        background-color: #1565C0;
        transform: scale(1.05);
    }

    .btn-success {
        background-color: #4CAF50;
        color: #fff;
    }

    .btn-success:hover {
        background-color: #2E7D32;
        transform: scale(1.05);
    }

    .alert {
        padding: 12px;
        margin: 15px auto;
        border-radius: 6px;
        max-width: 400px;
        text-align: center;
        font-weight: bold;
    }

    .alert-success {
        background-color: #2E7D32;
        color: #E0E0E0;
    }

    footer {
        background: #1E1E1E;
        color: #bbb;
        text-align: center;
        padding: 10px 0;
        font-size: 14px;
        border-top: 1px solid #333;
    }

    @media (max-width: 480px) {
        main h2 { font-size: 20px; }
        .btn { padding: 12px 20px; font-size: 14px; }
        footer { font-size: 12px; padding: 8px 0; }
    }
</style>
</head>
<body>

<header>
    <h1>💧 Water Bill Management System</h1>
    <p>Automated Billing & Payment Tracking</p>
</header>

<main>
    <?php if (isset($_GET['logout']) && $_GET['logout'] === 'success'): ?>
        <div class="alert alert-success">
            You have been logged out successfully.
        </div>
    <?php endif; ?>

    <h2>Welcome!</h2>
    <p>Please login to continue</p>

    <div>
        <a href="auth/login.php" class="btn btn-primary">Login</a>
        <a href="auth/register.php" class="btn btn-success">Register</a>
    </div>
</main>

<footer>
    &copy; <?php echo date("Y"); ?> Water Supply Department Kothmale | Sri Lanka Mahawali Authority
</footer>

</body>
</html>
