<?php
session_start();
require_once("../config.php");
require_once("../includes/functions.php");

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm  = trim($_POST['confirm_password']);

    if (empty($email) || empty($password) || empty($confirm)) {
        $error = "⚠ All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "⚠ Invalid email format.";
    } elseif ($password !== $confirm) {
        $error = "⚠ Passwords do not match.";
    } else {
        // Check if email exists
        $sql = "SELECT id FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            // Update password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $updateSql = "UPDATE users SET password = ? WHERE email = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("ss", $hashedPassword, $email);

            if ($updateStmt->execute()) {
                $success = "✅ Password reset successful! You can now <a href='login.php'>login</a>.";
            } else {
                $error = "❌ Something went wrong. Please try again.";
            }

            $updateStmt->close();
        } else {
            $error = "⚠ No account found with that email.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password | Water Bill System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="reset-container">
        <h2>🔄 Reset Password</h2>

        <?php if (!empty($error)) { echo "<p class='error'>$error</p>"; } ?>
        <?php if (!empty($success)) { echo "<p class='success'>$success</p>"; } ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Email Address:</label>
                <input type="email" name="email" required placeholder="Enter your registered email">
            </div>

            <div class="form-group">
                <label>New Password:</label>
                <input type="password" name="password" required placeholder="Enter new password">
            </div>

            <div class="form-group">
                <label>Confirm Password:</label>
                <input type="password" name="confirm_password" required placeholder="Confirm new password">
            </div>

            <button type="submit" class="btn">Reset Password</button>
        </form>

        <p>Remembered your password? <a href="login.php">Login</a></p>
    </div>
</body>
</html>
