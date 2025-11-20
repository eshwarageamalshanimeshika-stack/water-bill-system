<?php
require_once("../includes/auth.php");  // Admin-only access
require_once("../includes/header.php");
require_once("../includes/functions.php");
require_once("../config.php");

$error = "";
$success = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $account_no = cleanInput($_POST['account_no']);
    $name       = cleanInput($_POST['name']);
    $email      = cleanInput($_POST['email']);
    $username   = cleanInput($_POST['username']);
    $house      = cleanInput($_POST['house']);
    $house_no   = cleanInput($_POST['house_no']);
    $phone      = cleanInput($_POST['phone']);
    $tariff     = cleanInput($_POST['tariff']);
    $password   = cleanInput($_POST['password']);
    $confirm    = cleanInput($_POST['confirm_password']);

    // Validation
    if (empty($account_no) || empty($name) || empty($email) || empty($username) || empty($house) || empty($house_no) || empty($phone) || empty($tariff) || empty($password) || empty($confirm)) {
        $error = "⚠ Please fill in all required fields.";
    } elseif (strpos($email, '@') === false) {
        $error = "⚠ Invalid email format. Must contain '@'.";
    } elseif ($password !== $confirm) {
        $error = "⚠ Passwords do not match.";
    } else {
        // Check if account number already exists
        $stmt = $conn->prepare("SELECT customer_id FROM customer WHERE account_no = ?");
        $stmt->bind_param("s", $account_no);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "⚠ Account Number already exists. Please use another.";
        } else {
            // Check if email already exists
            $stmt2 = $conn->prepare("SELECT customer_id FROM customer WHERE email = ?");
            $stmt2->bind_param("s", $email);
            $stmt2->execute();
            $stmt2->store_result();

            if ($stmt2->num_rows > 0) {
                $error = "⚠ Email already exists. Please use another email.";
            } else {
                // Insert customer into customer table
                $insertStmt = $conn->prepare("INSERT INTO customer (account_no, name, email, username, house, house_no, phone, tariff, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insertStmt->bind_param("sssssssss", $account_no, $name, $email, $username, $house, $house_no, $phone, $tariff, $password);

                if ($insertStmt->execute()) {
                    $success = "✅ Customer added successfully!";
                } else {
                    $error = "❌ Something went wrong. Please try again.";
                }

                $insertStmt->close();
            }

            $stmt2->close();
        }

        $stmt->close();
    }
}
?>

<style>
.container {
    max-width: 600px;
    margin: 30px auto;
    background: #1e293b;
    padding: 20px;
    border-radius: 12px;
    color: #f1f5f9;
}
.container h2 {
    text-align: center;
    margin-bottom: 20px;
    font-size: 24px;
    color: #4dabf7;
}
.form-group {
    margin-bottom: 15px;
    position: relative;
}
label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}
input, select {
    width: 100%;
    padding: 8px;
    border-radius: 6px;
    border: 1px solid #444;
    background: #0f172a;
    color: #f1f5f9;
}
.toggle-password {
    position: absolute;
    top: 38px;
    right: 10px;
    cursor: pointer;
    color: #f1f5f9;
    user-select: none;
}
.btn {
    background: #4dabf7;
    color: white;
    padding: 10px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    width: 100%;
    font-size: 16px;
    margin-bottom: 10px;
}
.btn:hover {
    background: #1e90ff;
}
.back-btn {
    background: #ef4444;
    width: 200px;
    display: inline-block;
    text-align: center;
}
.back-btn:hover {
    background: #dc2626;
}
.error { color: #ef4444; margin-bottom: 10px; text-align: center; }
.success { color: #22c55e; margin-bottom: 10px; text-align: center; }
.back-container {
    text-align: center;
    margin-top: 10px;
}
</style>

<div class="container">
    <h2>➕ Add New Customer</h2>

    <?php if (!empty($error)) { echo "<p class='error'>$error</p>"; } ?>
    <?php if (!empty($success)) { echo "<p class='success'>$success</p>"; } ?>

    <form method="POST" action="">
        <div class="form-group">
            <label>Account No:</label>
            <input type="text" name="account_no" required placeholder="Enter unique account number">
        </div>

        <div class="form-group">
            <label>Full Name:</label>
            <input type="text" name="name" required placeholder="Enter full name">
        </div>

        <div class="form-group">
            <label>Email:</label>
            <input type="text" name="email" required placeholder="Enter email">
        </div>

        <div class="form-group">
            <label>Username:</label>
            <input type="text" name="username" required placeholder="Enter username">
        </div>

        <div class="form-group">
            <label>House:</label>
            <input type="text" name="house" required placeholder="Enter house name">
        </div>

        <div class="form-group">
            <label>House Number:</label>
            <input type="text" name="house_no" required placeholder="Enter house number">
        </div>

        <div class="form-group">
            <label>Phone:</label>
            <input type="text" name="phone" required placeholder="Enter phone number">
        </div>

        <div class="form-group">
            <label>Tariff:</label>
            <select name="tariff" required>
                <option value="">-- Select Type --</option>
                <option value="domestic">Domestic</option>
                <option value="commercial">Commercial</option>
                <option value="industrial">Industrial</option>
            </select>
        </div>

        <div class="form-group">
            <label>Password:</label>
            <input type="password" name="password" id="password" required placeholder="Enter password">
            <span class="toggle-password" onclick="togglePassword('password')"></span>
        </div>

        <div class="form-group">
            <label>Confirm Password:</label>
            <input type="password" name="confirm_password" id="confirm_password" required placeholder="Confirm password">
            <span class="toggle-password" onclick="togglePassword('confirm_password')"></span>
        </div>

        <button type="submit" class="btn">Add Customer</button>
    </form>

    <!-- Back Button -->
    <div class="back-container">
        <a href="customers.php" class="btn back-btn">⬅ Back to Customers</a>
    </div>
</div>

<script>
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    field.type = field.type === "password" ? "text" : "password";
}
</script>

<?php
require_once("../includes/footer.php");
?>
