<?php
require_once("../includes/auth.php");  // Customer-only access
require_once("../config.php");

// Get customer ID from session
$customer_id = $_SESSION['user_id'];

// Get payment ID from URL
$payment_id = isset($_GET['payment_id']) ? intval($_GET['payment_id']) : 0;

if ($payment_id === 0) {
    $_SESSION['error_msg'] = "Invalid payment ID!";
    header("Location: payment_history.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = $_POST['amount'];
    $method = $_POST['method'];
    $payment_date = $_POST['payment_date'];
    
    // Validate input
    if (empty($amount) || empty($method) || empty($payment_date)) {
        $error = "All fields are required!";
    } else {
        // Get bill amount to validate payment
        $billSql = "SELECT b.amount, b.id 
                    FROM payments p 
                    JOIN bills b ON p.bill_id = b.id 
                    WHERE p.payment_id=? AND p.customer_id=?";
        $billStmt = $conn->prepare($billSql);
        $billStmt->bind_param("ii", $payment_id, $customer_id);
        $billStmt->execute();
        $billResult = $billStmt->get_result();
        
        if ($billResult->num_rows === 0) {
            $error = "Unauthorized access!";
        } else {
            $bill = $billResult->fetch_assoc();
            
            if ($amount > $bill['amount']) {
                $error = "Payment amount cannot exceed bill amount!";
            } else {
                // Update payment
                $updateSql = "UPDATE payments 
                              SET amount=?, method=?, payment_date=? 
                              WHERE payment_id=? AND customer_id=?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("dssii", $amount, $method, $payment_date, $payment_id, $customer_id);
                
                if ($updateStmt->execute()) {
                    $_SESSION['success_msg'] = "Payment updated successfully!";
                    header("Location: payment_history.php");
                    exit;
                } else {
                    $error = "Failed to update payment!";
                }
                $updateStmt->close();
            }
        }
        $billStmt->close();
    }
}

// Fetch payment details
$sql = "SELECT p.*, b.amount AS bill_amount, b.units_consumed 
        FROM payments p 
        JOIN bills b ON p.bill_id = b.id 
        WHERE p.payment_id=? AND p.customer_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $payment_id, $customer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_msg'] = "Payment not found or unauthorized access!";
    header("Location: payment_history.php");
    exit;
}

$payment = $result->fetch_assoc();

// NOW include header
require_once("../includes/header.php");
require_once("../includes/functions.php");

// Fetch customer name
$nameSql = "SELECT name FROM customer WHERE customer_id=?";
$stmtName = $conn->prepare($nameSql);
$stmtName->bind_param("i", $customer_id);
$stmtName->execute();
$stmtName->bind_result($customerName);
$stmtName->fetch();
$stmtName->close();
$customerName = $customerName ?? "Customer";
?>

<!-- Page Styles -->
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
}

h2 {
    color: #ffffff;
    margin-bottom: 10px;
}

h3.welcome {
    color: #90caf9;
    margin-bottom: 30px;
    font-weight: 400;
}

/* Edit Form */
.edit-form {
    background-color: #1f1f1f;
    padding: 30px;
    border-radius: 12px;
    max-width: 600px;
    margin: 20px 0;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #90caf9;
    font-weight: 500;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 12px;
    background-color: #2c2c2c;
    border: 1px solid #404040;
    border-radius: 8px;
    color: #e0e0e0;
    font-size: 1em;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #90caf9;
}

.form-group input[readonly] {
    background-color: #1a1a1a;
    cursor: not-allowed;
}

.bill-info {
    background-color: #263238;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.bill-info p {
    margin: 8px 0;
    display: flex;
    justify-content: space-between;
}

.bill-info strong {
    color: #90caf9;
}

.btn-group {
    display: flex;
    gap: 10px;
}

.btn {
    padding: 12px 30px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 1em;
    font-weight: 600;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.btn-primary {
    background-color: #90caf9;
    color: #121212;
}

.btn-primary:hover {
    background-color: #64b5f6;
    transform: translateY(-2px);
}

.btn-secondary {
    background-color: #424242;
    color: #e0e0e0;
}

.btn-secondary:hover {
    background-color: #616161;
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-error {
    background-color: #5f2120;
    color: #ff6f61;
    border: 1px solid #c62828;
}

/* Responsive */
@media (max-width: 768px) {
    .dashboard-wrapper {
        flex-direction: column;
    }
    .sidebar {
        width: 100%;
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
    .btn-group {
        flex-direction: column;
    }
    .btn {
        width: 100%;
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
        <a href="payment_history.php" class="active">💰 Payment History</a>

        <div class="section-title">Account</div>
        <a href="profile.php">👤 Profile</a>
        <a href="../auth/logout.php">🚪 Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h2>✏️ Edit Payment</h2>
        <h3 class="welcome">Welcome, <?php echo htmlspecialchars($customerName); ?>!</h3>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                ❌ <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="edit-form">
            <div class="bill-info">
                <p><strong>Payment ID:</strong> <span><?php echo $payment['payment_id']; ?></span></p>
                <p><strong>Bill ID:</strong> <span><?php echo $payment['bill_id']; ?></span></p>
                <p><strong>Units Consumed:</strong> <span><?php echo $payment['units_consumed']; ?></span></p>
                <p><strong>Bill Amount:</strong> <span><?php echo formatCurrency($payment['bill_amount']); ?></span></p>
            </div>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="amount">Payment Amount: *</label>
                    <input type="number" name="amount" id="amount" 
                           step="0.01" min="0.01" 
                           max="<?php echo $payment['bill_amount']; ?>"
                           value="<?php echo $payment['amount']; ?>" required>
                    <small style="color: #9e9e9e;">Maximum: <?php echo formatCurrency($payment['bill_amount']); ?></small>
                </div>

                <div class="form-group">
                    <label for="method">Payment Method: *</label>
                    <select name="method" id="method" required>
                        <option value="">-- Select Method --</option>
                        <option value="cash" <?php echo ($payment['method'] === 'cash') ? 'selected' : ''; ?>>Cash</option>
                        <option value="card" <?php echo ($payment['method'] === 'card') ? 'selected' : ''; ?>>Credit/Debit Card</option>
                        <option value="bank_transfer" <?php echo ($payment['method'] === 'bank_transfer') ? 'selected' : ''; ?>>Bank Transfer</option>
                        <option value="online" <?php echo ($payment['method'] === 'online') ? 'selected' : ''; ?>>Online Payment</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="payment_date">Payment Date: *</label>
                    <input type="date" name="payment_date" id="payment_date" 
                           value="<?php echo date('Y-m-d', strtotime($payment['payment_date'])); ?>" required>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">💾 Update Payment</button>
                    <a href="payment_history.php" class="btn btn-secondary">❌ Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$stmt->close();
require_once("../includes/footer.php");
?>