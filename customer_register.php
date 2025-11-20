<?php
session_start();
require_once("../config.php");
require_once("../includes/functions.php");

$success = "";
$error   = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $account_no = trim($_POST['account_no']);
    $name       = trim($_POST['name']);
    $email      = trim($_POST['email']);
    $username   = trim($_POST['username']);
    $house      = trim($_POST['house']);
    $house_no   = trim($_POST['house_no']);
    $tariff     = trim($_POST['tariff']);
    $phone      = trim($_POST['phone']);
    $password   = trim($_POST['password']);
    $confirm    = trim($_POST['confirm_password']);

    // Validation
    if (empty($account_no) || empty($name) || empty($email) || empty($username) || empty($house) || empty($house_no) || empty($tariff) || empty($phone) || empty($password) || empty($confirm)) {
        $error = "⚠ All fields are required.";
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $error = "⚠ Invalid phone number. Must be 10 digits.";
    } elseif ($password !== $confirm) {
        $error = "⚠ Passwords do not match.";
    } else {
        // Check if account_no already exists
        $checkAcc = $conn->prepare("SELECT customer_id FROM customer WHERE account_no=?");
        $checkAcc->bind_param("s", $account_no);
        $checkAcc->execute();
        $checkAcc->store_result();

        if ($checkAcc->num_rows > 0) {
            $error = "⚠ Account Number already exists. Please use another.";
        } else {
            // Check if email already exists
            $check = $conn->prepare("SELECT customer_id FROM customer WHERE email=?");
            $check->bind_param("s", $email);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = "⚠ Customer with this email already exists.";
            } else {
                // Insert new customer (plain password stored - recommend hashing later)
                $stmt = $conn->prepare("INSERT INTO customer 
                    (account_no, name, email, username, house, house_no, phone, tariff, password) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssssss", $account_no, $name, $email, $username, $house, $house_no, $phone, $tariff, $password);

                if ($stmt->execute()) {
                    $success = "✅ Customer registered successfully! <a href='login.php'>Login</a>";
                } else {
                    $error = "❌ Something went wrong: " . $stmt->error;
                }
                $stmt->close();
            }
            $check->close();
        }
        $checkAcc->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Register | Water Bill Management</title>
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
    <p>Customer Registration</p>
</header>

<main>
    <div class="register-container">
        <h2 style="text-align:center;">🏠 Customer Registration</h2>
        <p style="text-align:center;">Fill in your details</p>

        <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
        <?php if (!empty($success)) echo "<p class='success'>$success</p>"; ?>

        <form method="POST" action="">
            <label>Account No:</label>
            <input type="text" name="account_no" required placeholder="Enter your account number">

            <label>Full Name:</label>
            <input type="text" name="name" required>

            <label>Email:</label>
            <input type="email" name="email" required>

            <label>Username:</label>
            <input type="text" name="username" required>

            <label>House:</label>
            <input type="text" name="house" required>

            <label>House Number:</label>
            <input type="text" name="house_no" required>

            <label>Phone:</label>
            <input type="text" name="phone" required placeholder="07XXXXXXXX">

            <label>Tariff:</label>
            <select name="tariff" required>
                <option value="domestic">Domestic</option>
                <option value="commercial">Commercial</option>
                <option value="industrial">Industrial</option>
            </select>

            <label>Password:</label>
            <input type="password" name="password" required>

            <label>Confirm Password:</label>
            <input type="password" name="confirm_password" required>

            <button type="submit" class="btn btn-success">Register</button>
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
