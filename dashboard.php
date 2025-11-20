<?php
require_once("../includes/auth.php");  // Customer-only access
require_once("../includes/header.php");
require_once("../includes/functions.php");
require_once("../config.php");

$customer_id = $_SESSION['user_id'];

// Fetch customer name
$nameSql = "SELECT name FROM customer WHERE customer_id=?";
$stmt = $conn->prepare($nameSql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$stmt->bind_result($customerName);
$stmt->fetch();
$stmt->close();
$customerName = $customerName ?? "Customer";

// ----------------------
// Total bills count and total bill amount
// ----------------------
$billsSql = "SELECT COUNT(*) AS total_bills, COALESCE(SUM(amount), 0) AS total_bill_amount 
             FROM bills WHERE customer_id=?";
$stmt = $conn->prepare($billsSql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$stmt->bind_result($totalBills, $totalBillAmount);
$stmt->fetch();
$stmt->close();

// ----------------------
// Total payments
// ----------------------
$paymentsSql = "SELECT COALESCE(SUM(amount), 0) AS total_paid FROM payments WHERE customer_id=?";
$stmt = $conn->prepare($paymentsSql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$stmt->bind_result($totalPaid);
$stmt->fetch();
$stmt->close();

// ----------------------
// Calculate Total Amount Due
// ----------------------
$totalDue = $totalBillAmount - $totalPaid;

// ----------------------
// Outstanding (unpaid) bills count
// ----------------------
$unpaidSql = "SELECT COUNT(*) AS unpaid_count 
              FROM bills 
              WHERE customer_id=? AND status IN ('unpaid', 'overdue')";
$stmt = $conn->prepare($unpaidSql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$stmt->bind_result($unpaidCount);
$stmt->fetch();
$stmt->close();

// ----------------------
// Recent Bills (Last 5)
// ----------------------
$recentBillsSql = "SELECT id, bill_date, amount, status 
                   FROM bills 
                   WHERE customer_id=? 
                   ORDER BY bill_date DESC 
                   LIMIT 5";
$stmt = $conn->prepare($recentBillsSql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$recentBillsResult = $stmt->get_result();
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

/* Cards */
.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.card {
    background: linear-gradient(135deg, #1e1e1e 0%, #2c2c2c 100%);
    padding: 25px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    transition: transform 0.2s, box-shadow 0.3s;
    border: 1px solid #2c2c2c;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(144, 202, 249, 0.2);
}

.card-icon {
    font-size: 2.5em;
    margin-bottom: 10px;
}

.card h3 {
    color: #90caf9;
    margin-bottom: 10px;
    font-size: 1em;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.card p {
    font-size: 1.8em;
    font-weight: bold;
    color: #e0e0e0;
    margin: 5px 0;
}

.card.due-card p {
    color: #ff6f61;
}

.card.paid-card p {
    color: #81c784;
}

/* Recent Bills Section */
.recent-bills {
    background-color: #1f1f1f;
    padding: 25px;
    border-radius: 12px;
    margin-top: 30px;
}

.recent-bills h3 {
    color: #90caf9;
    margin-bottom: 20px;
    font-size: 1.3em;
}

.bills-table {
    width: 100%;
    border-collapse: collapse;
}

.bills-table th,
.bills-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #2c2c2c;
}

.bills-table th {
    color: #90caf9;
    font-weight: 600;
}

.bills-table tr:hover {
    background-color: #2c2c2c;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 600;
}

.status-paid {
    background-color: #1b5e20;
    color: #81c784;
}

.status-unpaid {
    background-color: #5f2120;
    color: #ff6f61;
}

.status-overdue {
    background-color: #8f6000;
    color: #ffb74d;
}

.no-bills {
    text-align: center;
    color: #9e9e9e;
    padding: 20px;
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
    .dashboard-cards {
        grid-template-columns: 1fr;
    }
    .bills-table {
        font-size: 0.9em;
    }
}
</style>

<div class="dashboard-wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
        <h2>Sidebar</h2>

        <div class="section-title">Main</div>
        <a href="dashboard.php" class="active">🏠 Dashboard</a>

        <div class="section-title">Billing</div>
        <a href="view_bills.php">🧾 View Bills</a>
        <a href="pay_bill.php">💳 Pay Bill</a>
        <a href="payment_history.php">💰 Payment History</a>

        <div class="section-title">Account</div>
        <a href="profile.php">👤 Profile</a>
        <a href="../auth/logout.php">🚪 Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h2>🏠 My Dashboard</h2>
        <h3 class="welcome">Welcome, <?php echo htmlspecialchars($customerName); ?>!</h3>

        <div class="dashboard-cards">
            <div class="card">
                <div class="card-icon">📋</div>
                <h3>Total Bills</h3>
                <p><?php echo $totalBills; ?></p>
            </div>

            <div class="card paid-card">
                <div class="card-icon">✅</div>
                <h3>Total Paid</h3>
                <p><?php echo formatCurrency($totalPaid); ?></p>
            </div>

            <div class="card">
                <div class="card-icon">⏳</div>
                <h3>Unpaid Bills</h3>
                <p><?php echo $unpaidCount; ?></p>
            </div>

            <div class="card due-card">
                <div class="card-icon">💰</div>
                <h3>Total Amount Due</h3>
                <p><?php echo formatCurrency($totalDue); ?></p>
            </div>
        </div>

        <!-- Recent Bills Section -->
        <div class="recent-bills">
            <h3>📊 Recent Bills</h3>
            <?php if ($recentBillsResult->num_rows > 0): ?>
                <table class="bills-table">
                    <thead>
                        <tr>
                            <th>Bill ID</th>
                            <th>Bill Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($bill = $recentBillsResult->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $bill['id']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($bill['bill_date'])); ?></td>
                                <td><?php echo formatCurrency($bill['amount']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $bill['status']; ?>">
                                        <?php 
                                        if ($bill['status'] == 'paid') echo '✅ Paid';
                                        elseif ($bill['status'] == 'overdue') echo '⚠️ Overdue';
                                        else echo '⏳ Unpaid';
                                        ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="no-bills">📭 No bills available yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$stmt->close();
require_once("../includes/footer.php");
?>