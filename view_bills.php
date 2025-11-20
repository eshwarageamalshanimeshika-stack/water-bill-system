<?php
require_once("../includes/auth.php");  // Customer-only access
require_once("../includes/header.php");
require_once("../includes/functions.php");
require_once("../config.php");

// Get customer ID from session
$customer_id = $_SESSION['user_id'];

// Fetch customer name
$stmtName = $conn->prepare("SELECT name FROM customer WHERE customer_id=?");
$stmtName->bind_param("i", $customer_id);
$stmtName->execute();
$stmtName->bind_result($customerName);
$stmtName->fetch();
$stmtName->close();
$customerName = $customerName ?? "Customer";

// Fetch all bills for this customer
$sql = "SELECT * FROM bills WHERE customer_id=? ORDER BY bill_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();
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

/* Table Styles */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

thead th {
    background-color: #1e1e1e;
    padding: 12px;
    text-align: left;
    color: #90caf9;
}

tbody td {
    padding: 12px;
    border-bottom: 1px solid #2c2c2c;
}

.btn {
    padding: 6px 12px;
    background-color: #004080;
    color: #fff;
    border-radius: 6px;
    text-decoration: none;
    margin-right: 5px;
    transition: background 0.3s;
}

.btn:hover {
    background-color: #002f5e;
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
    table, thead, tbody, th, td, tr {
        display: block;
    }
    thead tr {
        display: none;
    }
    tbody td {
        padding-left: 50%;
        position: relative;
    }
    tbody td::before {
        position: absolute;
        top: 12px;
        left: 15px;
        width: 45%;
        white-space: nowrap;
        font-weight: bold;
        content: attr(data-label);
        color: #90caf9;
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
        <a href="view_bills.php" class="active">🧾 View Bills</a>
        <a href="pay_bill.php">💳 Pay Bill</a>
        <a href="payment_history.php">💰 Payment History</a>

        <div class="section-title">Account</div>
        <a href="profile.php">👤 Profile</a>
        <a href="../auth/logout.php">🚪 Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h2>📄 My Bills</h2>
        <h3 class="welcome">Welcome, <?php echo htmlspecialchars($customerName); ?>!</h3>

        <?php if ($result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Bill ID</th>
                        <th>Units Consumed</th>
                        <th>Amount</th>
                        <th>Bill Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($bill = $result->fetch_assoc()): ?>
                        <tr>
                            <td data-label="Bill ID"><?php echo $bill['id']; ?></td>
                            <td data-label="Units Consumed"><?php echo $bill['units_consumed']; ?></td>
                            <td data-label="Amount">Rs. <?php echo number_format($bill['amount'], 2); ?></td>
                            <td data-label="Bill Date"><?php echo $bill['bill_date']; ?></td>
                            <td data-label="Status">
                                <?php if ($bill['status'] == "paid"): ?>
                                    ✅ Paid
                                <?php elseif ($bill['status'] == "overdue"): ?>
                                    ⚠ Overdue
                                <?php else: ?>
                                    ⏳ Unpaid
                                <?php endif; ?>
                            </td>
                            <td data-label="Action">
                                <?php if ($bill['status'] != "paid"): ?>
                                    <a href="pay_bill.php?bill_id=<?php echo $bill['id']; ?>" class="btn">Pay Now</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>⚠ You have no bills yet.</p>
        <?php endif; ?>
    </div>
</div>

<?php
$stmt->close();
require_once("../includes/footer.php");
?>