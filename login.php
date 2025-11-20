<?php
session_start();
require_once "../config.php";   // DB connection
require_once "../includes/functions.php"; // helper functions

// If already logged in, redirect
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: ../admin/dashboard.php");
        exit();
    } elseif ($_SESSION['role'] === 'customer') {
        header("Location: ../customer/dashboard.php");
        exit();
    }
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = cleanInput($_POST['username']);
    $password = cleanInput($_POST['password']);
    $role     = cleanInput($_POST['role']); // selected role

    if (!empty($username) && !empty($password) && !empty($role)) {

        if ($role === "admin") {
            // Check Admin login
            $stmt = $conn->prepare("SELECT * FROM admin WHERE username = ? LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $admin = $result->fetch_assoc();
                
                // ⚠ Plain text password check (switch to password_verify later)
                if ($password === $admin['password']) {
                    $_SESSION['user_id']  = $admin['admin_id'];
                    $_SESSION['username'] = $admin['username'];
                    $_SESSION['role']     = 'admin';
                    header("Location: ../admin/dashboard.php");
                    exit();
                } else {
                    $error = "Invalid Admin Password.";
                }
            } else {
                $error = "Admin not found.";
            }
        } 
        
        elseif ($role === "customer") {
            // Check Customer login (allow username OR email)
            $stmt = $conn->prepare("SELECT * FROM customer WHERE email = ? OR username = ? LIMIT 1");
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $customer = $result->fetch_assoc();
                
                // ⚠ Plain text password check (switch to password_verify later)
                if ($password === $customer['password']) {
                    $_SESSION['user_id']  = $customer['customer_id'];
                    $_SESSION['username'] = $customer['username'];
                    $_SESSION['role']     = 'customer';
                    header("Location: ../customer/dashboard.php");
                    exit();
                } else {
                    $error = "Invalid Customer Password.";
                }
            } else {
                $error = "Customer not found.";
            }
        }

    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Water Bill Management System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Full height page styling */
        html, body {
            height: 100%;
            margin: 0;
            display: flex;
            flex-direction: column;
            background: #121212;
            color: #E0E0E0;
            font-family: Arial, sans-serif;
        }

        header {
            background: #0D47A1;
            color: white;
            text-align: center;
            padding: 20px;
        }

        main {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }

        form {
            background: #1E1E1E;
            padding: 25px;
            border-radius: 10px;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 4px 10px rgba(0,0,0,0.5);
        }

        label {
            display: block;
            margin: 10px 0 5px;
        }

        input, select {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
        }

        input[type="submit"] {
            background: #0D47A1;
            color: #fff;
            cursor: pointer;
        }

        input[type="submit"]:hover {
            background: #1565C0;
        }

        .alert-error {
            background: #ff4d4d;
            color: #fff;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            text-align: center;
        }

        footer {
            text-align: center;
            padding: 15px;
            background: #1E1E1E;
            color: #ccc;
        }
    </style>
</head>
<body>

<header>
    <h1>💧 Water Bill Management System</h1>
    <p>Login to your account</p>
</header>

<main>
    <form method="POST" id="loginForm">
        <h2 style="text-align:center;">Login</h2>
        <p style="text-align:center;">Select role and enter your credentials</p>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <label for="role">Role</label>
        <select name="role" id="role" required>
            <option value="">-- Select Role --</option>
            <option value="admin">Admin</option>
            <option value="customer">Customer</option>
        </select>

        <label for="username">Username / Email</label>
        <input type="text" name="username" id="username" required placeholder="Enter username or email">

        <label for="password">Password</label>
        <input type="password" name="password" id="password" required placeholder="Enter password">

        <input type="submit" value="Login">
    </form>

    <!-- Register link below form -->
    <p style="text-align:center; margin-top:15px;">
        New User? <a href="register.php">Register here</a>
    </p>
</main>

<footer>
    <p>&copy; <?php echo date("Y"); ?> Water Supply Department Kothmale | Sri Lanka Mahawali Authority</p>
</footer>

<script src="../assets/js/main.js"></script>
</body>
</html>
