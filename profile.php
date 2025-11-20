<?php
require_once("../includes/auth.php");  // Customer-only access
require_once("../includes/header.php");
require_once("../includes/functions.php");
require_once("../config.php");

$error = "";
$success = "";

// Get customer ID from session
$customer_id = $_SESSION['user_id'];

// Fetch customer name & email
$stmtName = $conn->prepare("SELECT name, email, password FROM customer WHERE customer_id=?");
$stmtName->bind_param("i", $customer_id);
$stmtName->execute();
$stmtName->bind_result($customerName, $customerEmail, $current_password_in_db);
$stmtName->fetch();
$stmtName->close();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = cleanInput($_POST['name']);
    $email = cleanInput($_POST['email']);
    $current_password = cleanInput($_POST['current_password']);
    $new_password = cleanInput($_POST['new_password']);
    $confirm_password = cleanInput($_POST['confirm_password']);

    // ----------------------
    // Update Name/Email
    // ----------------------
    if (!empty($name) && !empty($email)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "⚠ Invalid email format.";
        } else {
            // Check if email is used by another customer
            $stmt = $conn->prepare("SELECT customer_id FROM customer WHERE email=? AND customer_id!=?");
            $stmt->bind_param("si", $email, $customer_id);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $error = "⚠ Email is already in use by another account.";
            } else {
                $stmt->close();
                $stmt = $conn->prepare("UPDATE customer SET name=?, email=? WHERE customer_id=?");
                $stmt->bind_param("ssi", $name, $email, $customer_id);
                if ($stmt->execute()) {
                    $success = "✅ Profile updated successfully!";
                    $customerName = $name;
                    $customerEmail = $email;
                } else {
                    $error = "❌ Failed to update profile.";
                }
                $stmt->close();
            }
        }
    }

    // ----------------------
    // Update Password
    // ----------------------
    if (!empty($current_password) && !empty($new_password) && !empty($confirm_password)) {
        if ($current_password !== $current_password_in_db) {
            $error = "⚠ Current password is incorrect.";
        } elseif ($new_password !== $confirm_password) {
            $error = "⚠ New passwords do not match.";
        } else {
            $stmt = $conn->prepare("UPDATE customer SET password=? WHERE customer_id=?");
            $stmt->bind_param("si", $new_password, $customer_id);
            if ($stmt->execute()) {
                $success .= " ✅ Password changed successfully!";
                $current_password_in_db = $new_password;
            } else {
                $error = "❌ Failed to change password.";
            }
            $stmt->close();
        }
    }
}
?>

<style>
body {
    margin: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #121212;
    color: #e0e0e0;
}

.dashboard-wrapper {
    display: flex;
    min-height: 100vh;
}

/* Sidebar */
.sidebar {
    width: 250px;
    background-color: #1f1f1f;
    display: flex;
    flex-direction: column;
    padding: 20px;
    flex-shrink: 0;
    height: 100vh;
    position: sticky;
    top: 0;
    overflow-y: auto;
}

.sidebar h2 {
    color: #90caf9;
    margin-bottom: 20px;
    font-size: 1.5em;
    text-align: center;
}

.sidebar .section-title {
    margin: 15px 0 10px;
    font-size: 0.9em;
    color: #90caf9;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.sidebar a {
    color: #cfd8dc;
    text-decoration: none;
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: background 0.3s, color 0.3s;
}

.sidebar a:hover, .sidebar a.active {
    background-color: #37474f;
    color: #fff;
}

/* Main content */
.main-content {
    flex: 1;
    padding: 30px;
    background-color: #181818;
    max-width: 900px;
    margin: 0 auto;
}

h2 {
    color: #ffffff;
    margin-bottom: 10px;
    text-align: center;
    font-size: 32px;
}

h3.welcome {
    color: #90caf9;
    margin-bottom: 30px;
    font-weight: 400;
    text-align: center;
}

.profile-container {
    background: linear-gradient(145deg, #1e1e1e, #0f0f0f);
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.4);
    max-width: 600px;
    margin: 0 auto;
}

.info-section {
    background: #1a1a1a;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    border-left: 4px solid #90caf9;
}

.info-section h4 {
    color: #90caf9;
    margin: 0 0 15px 0;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Form */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #9ca3af;
    font-size: 14px;
}

.form-group input {
    width: 100%;
    padding: 12px 15px;
    border-radius: 8px;
    border: 1px solid #2c2c2c;
    background-color: #0f0f0f;
    color: #e0e0e0;
    font-size: 14px;
    transition: border 0.3s, background 0.3s;
}

.form-group input:focus {
    outline: none;
    border-color: #90caf9;
    background-color: #1a1a1a;
}

.form-group input::placeholder {
    color: #666;
}

button.btn {
    width: 100%;
    padding: 14px 20px;
    background: linear-gradient(145deg, #90caf9, #64b5f6);
    color: #121212;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
    font-size: 16px;
    transition: all 0.3s;
    margin-top: 10px;
}

button.btn:hover {
    background: linear-gradient(145deg, #64b5f6, #42a5f5);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(144, 202, 249, 0.4);
}

button.btn:active {
    transform: translateY(0);
}

/* Alert Messages */
.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 500;
}

.alert-error {
    background: linear-gradient(145deg, #dc2626, #991b1b);
    color: #fff;
    border-left: 4px solid #ef4444;
}

.alert-success {
    background: linear-gradient(145deg, #10b981, #059669);
    color: #fff;
    border-left: 4px solid #22c55e;
}

hr {
    border: none;
    border-top: 1px solid #2c2c2c;
    margin: 30px 0;
}

.password-section {
    margin-top: 30px;
    padding-top: 30px;
    border-top: 2px solid #2c2c2c;
}

.password-section h4 {
    color: #f59e0b;
    margin: 0 0 20px 0;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.password-info {
    background: rgba(245, 158, 11, 0.1);
    padding: 12px 15px;
    border-radius: 6px;
    border-left: 3px solid #f59e0b;
    margin-bottom: 20px;
    font-size: 13px;
    color: #d1d5db;
}

/* Responsive */
@media (max-width: 768px) {
    .dashboard-wrapper {
        flex-direction: column;
    }
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
        flex-direction: row;
        flex-wrap: wrap;
        padding: 10px;
    }
    .sidebar a {
        flex: 1 1 45%;
        justify-content: center;
        margin-bottom: 5px;
        text-align: center;
    }
    .main-content {
        padding: 20px 15px;
    }
    .profile-container {
        padding: 20px;
    }
}
</style>

<div class="dashboard-wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
        <h2>Sidebar</h2>

        <div class="section-title">Main</div>
        <a href="dashboard.php">🏠 Dashboard</a>

        <div class="section-title">Billing</div>
        <a href="view_bills.php">🧾 View Bills</a>
        <a href="pay_bill.php">💳 Pay Bill</a>
        <a href="payment_history.php">💰 Payment History</a>

        <div class="section-title">Account</div>
        <a href="profile.php" class="active">👤 Profile</a>
        <a href="../auth/logout.php">🚪 Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h2>👤 My Profile</h2>
        <h3 class="welcome">Welcome, <?php echo htmlspecialchars($customerName); ?>!</h3>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                ❌ <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                ✅ <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <div class="profile-container">
            <form method="POST" action="">
                <div class="info-section">
                    <h4>📋 Personal Information</h4>
                    
                    <div class="form-group">
                        <label>Full Name:</label>
                        <input type="text" name="name" required value="<?php echo htmlspecialchars($customerName); ?>" placeholder="Enter your full name">
                    </div>

                    <div class="form-group">
                        <label>Email Address:</label>
                        <input type="email" name="email" required value="<?php echo htmlspecialchars($customerEmail); ?>" placeholder="Enter your email">
                    </div>
                </div>

                <div class="password-section">
                    <h4>🔐 Change Password</h4>
                    
                    <div class="password-info">
                        💡 <strong>Optional:</strong> Leave password fields empty if you don't want to change your password.
                    </div>

                    <div class="form-group">
                        <label>Current Password:</label>
                        <input type="password" name="current_password" placeholder="Enter current password">
                    </div>

                    <div class="form-group">
                        <label>New Password:</label>
                        <input type="password" name="new_password" placeholder="Enter new password">
                    </div>

                    <div class="form-group">
                        <label>Confirm New Password:</label>
                        <input type="password" name="confirm_password" placeholder="Confirm new password">
                    </div>
                </div>

                <button type="submit" class="btn">💾 Update Profile</button>
            </form>
        </div>
    </div>
</div>

<?php
require_once("../includes/footer.php");
?>