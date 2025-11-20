<?php
require_once("../includes/auth.php");  // Customer-only access
require_once("../config.php");

// Get customer ID from session
$customer_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bill_id = $_POST['bill_id'];
    $amount = $_POST['amount'];
    $method = $_POST['method'];
    
    // Validate input
    if (empty($bill_id) || empty($amount) || empty($method)) {
        $error = "All fields are required!";
    } else {
        // Verify the bill belongs to this customer
        $checkSql = "SELECT id, amount, status FROM bills WHERE id=? AND customer_id=?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("ii", $bill_id, $customer_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows === 0) {
            $error = "Invalid bill or unauthorized access!";
        } else {
            $bill = $checkResult->fetch_assoc();
            
            if ($bill['status'] === 'paid') {
                $error = "This bill has already been paid!";
            } elseif ($amount > $bill['amount']) {
                $error = "Payment amount cannot exceed bill amount!";
            } else {
                // Insert payment record
                $insertSql = "INSERT INTO payments (customer_id, bill_id, amount, method, payment_date) 
                              VALUES (?, ?, ?, ?, NOW())";
                $insertStmt = $conn->prepare($insertSql);
                $insertStmt->bind_param("iids", $customer_id, $bill_id, $amount, $method);
                
                if ($insertStmt->execute()) {
                    // Check if bill is fully paid
                    $paidSql = "SELECT SUM(amount) as total_paid FROM payments WHERE bill_id=?";
                    $paidStmt = $conn->prepare($paidSql);
                    $paidStmt->bind_param("i", $bill_id);
                    $paidStmt->execute();
                    $paidResult = $paidStmt->get_result();
                    $paidRow = $paidResult->fetch_assoc();
                    $totalPaid = $paidRow['total_paid'];
                    
                    // Update bill status if fully paid
                    if ($totalPaid >= $bill['amount']) {
                        $updateSql = "UPDATE bills SET status='paid' WHERE id=?";
                        $updateStmt = $conn->prepare($updateSql);
                        $updateStmt->bind_param("i", $bill_id);
                        $updateStmt->execute();
                        $updateStmt->close();
                    }
                    
                    $paidStmt->close();
                    $insertStmt->close();
                    
                    $success = "Payment successful! Redirecting...";
                    header("refresh:2;url=payment_history.php");
                } else {
                    $error = "Payment failed. Please try again.";
                }
            }
        }
        $checkStmt->close();
    }
}

// NOW include header and other files (after form processing)
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

// Get bill_id from URL if provided
$selected_bill_id = isset($_GET['bill_id']) ? intval($_GET['bill_id']) : 0;

// Fetch unpaid bills for this customer
$billsSql = "SELECT b.id, b.bill_date, b.amount, b.units_consumed,
                    COALESCE(SUM(p.amount), 0) as paid_amount
             FROM bills b
             LEFT JOIN payments p ON b.id = p.bill_id
             WHERE b.customer_id=? AND b.status != 'paid'
             GROUP BY b.id
             ORDER BY b.bill_date DESC";
$billsStmt = $conn->prepare($billsSql);
$billsStmt->bind_param("i", $customer_id);
$billsStmt->execute();
$billsResult = $billsStmt->get_result();

// Fetch selected bill details if bill_id provided
$selectedBill = null;
if ($selected_bill_id > 0) {
    $selectedSql = "SELECT b.id, b.bill_date, b.amount, b.units_consumed,
                           COALESCE(SUM(p.amount), 0) as paid_amount
                    FROM bills b
                    LEFT JOIN payments p ON b.id = p.bill_id
                    WHERE b.id=? AND b.customer_id=? AND b.status != 'paid'
                    GROUP BY b.id";
    $selectedStmt = $conn->prepare($selectedSql);
    $selectedStmt->bind_param("ii", $selected_bill_id, $customer_id);
    $selectedStmt->execute();
    $selectedResult = $selectedStmt->get_result();
    if ($selectedResult->num_rows > 0) {
        $selectedBill = $selectedResult->fetch_assoc();
    }
    $selectedStmt->close();
}
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

/* Payment Form */
.payment-form {
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

.form-group select,
.form-group input {
    width: 100%;
    padding: 12px;
    background-color: #2c2c2c;
    border: 1px solid #404040;
    border-radius: 8px;
    color: #e0e0e0;
    font-size: 1em;
}

.form-group select:focus,
.form-group input:focus {
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

.btn-submit {
    background-color: #90caf9;
    color: #121212;
    padding: 12px 30px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 1em;
    font-weight: 600;
    transition: background 0.3s, transform 0.2s;
}

.btn-submit:hover {
    background-color: #64b5f6;
    transform: translateY(-2px);
}

.btn-submit:active {
    transform: translateY(0);
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success {
    background-color: #1b5e20;
    color: #81c784;
    border: 1px solid #2e7d32;
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
        <a href="pay_bill.php" class="active">💳 Pay Bill</a>
        <a href="payment_history.php">💰 Payment History</a>

        <div class="section-title">Account</div>
        <a href="profile.php">👤 Profile</a>
        <a href="../auth/logout.php">🚪 Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h2>💳 Pay Bill</h2>
        <h3 class="welcome">Welcome, <?php echo htmlspecialchars($customerName); ?>!</h3>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                ✅ <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                ❌ <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($billsResult->num_rows > 0): ?>
            <div class="payment-form">
                <form method="POST" action="" id="paymentForm">
                    <div class="form-group">
                        <label for="bill_id">Select Bill: *</label>
                        <select name="bill_id" id="bill_id" required onchange="updateBillDetails()">
                            <option value="">-- Select Bill --</option>
                            <?php while ($bill = $billsResult->fetch_assoc()): ?>
                                <option value="<?php echo $bill['id']; ?>" 
                                        data-amount="<?php echo $bill['amount']; ?>"
                                        data-paid="<?php echo $bill['paid_amount']; ?>"
                                        data-units="<?php echo $bill['units_consumed']; ?>"
                                        data-date="<?php echo $bill['bill_date']; ?>"
                                        <?php echo ($selected_bill_id == $bill['id']) ? 'selected' : ''; ?>>
                                    Bill #<?php echo $bill['id']; ?> - <?php echo $bill['bill_date']; ?> 
                                    (<?php echo formatCurrency($bill['amount'] - $bill['paid_amount']); ?> due)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div id="billDetails" style="display: <?php echo $selectedBill ? 'block' : 'none'; ?>;">
                        <div class="bill-info">
                            <p><strong>Units Consumed:</strong> <span id="units"><?php echo $selectedBill['units_consumed'] ?? ''; ?></span></p>
                            <p><strong>Bill Amount:</strong> <span id="billAmount"><?php echo isset($selectedBill) ? formatCurrency($selectedBill['amount']) : ''; ?></span></p>
                            <p><strong>Already Paid:</strong> <span id="paidAmount"><?php echo isset($selectedBill) ? formatCurrency($selectedBill['paid_amount']) : ''; ?></span></p>
                            <p><strong>Outstanding:</strong> <span id="outstanding"><?php echo isset($selectedBill) ? formatCurrency($selectedBill['amount'] - $selectedBill['paid_amount']) : ''; ?></span></p>
                        </div>

                        <div class="form-group">
                            <label for="amount">Payment Amount: *</label>
                            <input type="number" name="amount" id="amount" 
                                   step="0.01" min="0.01" 
                                   max="<?php echo isset($selectedBill) ? ($selectedBill['amount'] - $selectedBill['paid_amount']) : ''; ?>"
                                   value="<?php echo isset($selectedBill) ? ($selectedBill['amount'] - $selectedBill['paid_amount']) : ''; ?>"
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="method">Payment Method: *</label>
                            <select name="method" id="method" required>
                                <option value="">-- Select Method --</option>
                                <option value="cash">Cash</option>
                                <option value="card">Credit/Debit Card</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="online">Online Payment</option>
                            </select>
                        </div>

                        <button type="submit" class="btn-submit">💰 Submit Payment</button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="alert alert-success">
                ✅ You have no unpaid bills at the moment!
            </div>
            <a href="view_bills.php" style="color: #90caf9;">← Back to Bills</a>
        <?php endif; ?>
    </div>
</div>

<script>
function updateBillDetails() {
    const select = document.getElementById('bill_id');
    const selectedOption = select.options[select.selectedIndex];
    const details = document.getElementById('billDetails');
    
    if (selectedOption.value) {
        const amount = parseFloat(selectedOption.dataset.amount);
        const paid = parseFloat(selectedOption.dataset.paid);
        const outstanding = amount - paid;
        const units = selectedOption.dataset.units;
        const date = selectedOption.dataset.date;
        
        document.getElementById('units').textContent = units;
        document.getElementById('billAmount').textContent = 'Rs. ' + amount.toFixed(2);
        document.getElementById('paidAmount').textContent = 'Rs. ' + paid.toFixed(2);
        document.getElementById('outstanding').textContent = 'Rs. ' + outstanding.toFixed(2);
        
        document.getElementById('amount').max = outstanding;
        document.getElementById('amount').value = outstanding;
        
        details.style.display = 'block';
    } else {
        details.style.display = 'none';
    }
}

// Initialize on page load if bill is pre-selected
window.onload = function() {
    const billSelect = document.getElementById('bill_id');
    if (billSelect.value) {
        updateBillDetails();
    }
};
</script>

<?php
$billsStmt->close();
require_once("../includes/footer.php");
?>