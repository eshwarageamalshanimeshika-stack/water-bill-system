<?php
session_start();
require_once("../config.php");
require_once("../includes/functions.php");

$success = "";
$error   = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $confirm  = trim($_POST['confirm_password']);

    if (empty($name) || empty($email) || empty($username) || empty($password) || empty($confirm)) {
        $error = "⚠ All fields are required.";
    } elseif ($password !== $confirm) {
        $error = "⚠ Passwords do not match.";
    } else {
        // check if admin exists
        $check = $conn->prepare("SELECT admin_id FROM admin WHERE username=? OR email=?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "⚠ Admin already exists.";
        } else {
            // insert new admin (currently plain text password - recommend hashing later)
            $stmt = $conn->prepare("INSERT INTO admin (name, email, username, password, role) VALUES (?, ?, ?, ?, 'admin')");
            $stmt->bind_param("ssss", $name, $email, $username, $password);

            if ($stmt->execute()) {
                $success = "✅ Admin registered successfully! <a href='login.php'>Login</a>";
            } else {
                $error = "❌ Something went wrong: " . $stmt->error;
            }
            $stmt->close();
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Register | Water Bill Management</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .success, .error {
            text-align: center;
            font-weight: bold;
            margin: 15px 0;
        }
        .success { color: #22c55e; }
        .error { color: #ef4444; }
    </style>
</head>
<body>
<header>
    <h1>💧 Water Bill Management System</h1>
    <p>Admin Registration</p>
</header>

<main>
    <div class="register-container">
        <h2 style="text-align:center;">🛠 Admin Registration</h2>
        <p style="text-align:center;">Fill in your details</p>

        <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
        <?php if (!empty($success)) echo "<p class='success'>$success</p>"; ?>

        <form method="POST" action="">
            <label>Full Name:</label>
            <input type="text" name="name" required>

            <label>Email:</label>
            <input type="email" name="email" required>

            <label>Username:</label>
            <input type="text" name="username" required>

            <label>Password:</label>
            <input type="password" name="password" required>

            <label>Confirm Password:</label>
            <input type="password" name="confirm_password" required>

            <button type="submit" class="btn btn-primary">Register</button>
        </form>

        <p style="text-align:center; margin-top:15px;">
            Already have an account? <a href="login.php">Login here</a>
        </p>
    </div>
</main>

<footer>
    <p>&copy; <?php echo date("Y"); ?> Water Supply Department Kothmale | Sri Lanka Mahawali Authority</p>
</footer>
</body>
</html>
