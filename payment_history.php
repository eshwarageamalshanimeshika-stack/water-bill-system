<?php
require_once("../includes/auth.php");  // Customer-only access
require_once("../config.php");

// Get customer ID from session
$customer_id = $_SESSION['user_id'];

// Handle Delete Payment
if (isset($_GET['delete']) && isset($_GET['payment_id'])) {
    $payment_id = intval($_GET['payment_id']);
    
    // Verify payment belongs to this customer
    $checkSql = "SELECT p.payment_id, p.bill_id FROM payments p WHERE p.payment_id=? AND p.customer_id=?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("ii", $payment_id, $customer_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $payment = $checkResult->fetch_assoc();
        $bill_id = $payment['bill_id'];
        
        // Delete payment
        $deleteSql = "DELETE FROM payments WHERE payment_id=?";
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->bind_param("i", $payment_id);
        
        if ($deleteStmt->execute()) {
            // Update bill status back to unpaid if necessary
            $updateSql = "UPDATE bills SET status='unpaid' WHERE id=?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("i", $bill_id);
            $updateStmt->execute();
            $updateStmt->close();
            
            $_SESSION['success_msg'] = "Payment deleted successfully!";
        } else {
            $_SESSION['error_msg'] = "Failed to delete payment!";
        }
        $deleteStmt->close();
    } else {
        $_SESSION['error_msg'] = "Unauthorized access!";
    }
    $checkStmt->close();
    
    header("Location: payment_history.php");
    exit;
}

// Handle CSV Download - MUST BE BEFORE ANY HTML OUTPUT
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    // Fetch customer name for the file
    $nameSql = "SELECT name FROM customer WHERE customer_id=?";
    $stmtName = $conn->prepare($nameSql);
    $stmtName->bind_param("i", $customer_id);
    $stmtName->execute();
    $stmtName->bind_result($customerName);
    $stmtName->fetch();
    $stmtName->close();
    
    // Fetch payments
    $sql = "SELECT p.payment_id, p.bill_id, p.amount, p.method, 
                   DATE_FORMAT(p.payment_date, '%Y-%m-%d') as payment_date,
                   b.units_consumed, b.amount AS bill_amount 
            FROM payments p 
            JOIN bills b ON p.bill_id = b.id 
            WHERE p.customer_id=? 
            ORDER BY p.payment_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Calculate correct totals from ALL bills
    $totalSql = "SELECT COALESCE(SUM(amount), 0) as total_bills FROM bills WHERE customer_id=?";
    $totalStmt = $conn->prepare($totalSql);
    $totalStmt->bind_param("i", $customer_id);
    $totalStmt->execute();
    $totalStmt->bind_result($totalBills);
    $totalStmt->fetch();
    $totalStmt->close();
    
    $paidSql = "SELECT COALESCE(SUM(amount), 0) as total_paid FROM payments WHERE customer_id=?";
    $paidStmt = $conn->prepare($paidSql);
    $paidStmt->bind_param("i", $customer_id);
    $paidStmt->execute();
    $paidStmt->bind_result($totalPaid);
    $paidStmt->fetch();
    $paidStmt->close();
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="payment_history_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 support
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add header row
    fputcsv($output, ['Payment ID', 'Bill ID', 'Units Consumed', 'Bill Amount', 'Amount Paid', 'Payment Method', 'Payment Date']);
    
    // Add data rows
    while ($payment = $result->fetch_assoc()) {
        fputcsv($output, [
            $payment['payment_id'],
            $payment['bill_id'],
            $payment['units_consumed'],
            number_format($payment['bill_amount'], 2, '.', ''),
            number_format($payment['amount'], 2, '.', ''),
            ucfirst($payment['method']),
            $payment['payment_date']
        ]);
    }
    
    // Add summary rows
    fputcsv($output, []);
    fputcsv($output, ['', '', '', 'Total Bill Amount:', number_format($totalBills, 2, '.', '')]);
    fputcsv($output, ['', '', '', 'Total Paid:', number_format($totalPaid, 2, '.', '')]);
    fputcsv($output, ['', '', '', 'Amount Due:', number_format(($totalBills - $totalPaid), 2, '.', '')]);
    
    fclose($output);
    $stmt->close();
    $conn->close();
    exit;
}

// NOW include header and other files (after CSV check)
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

// Fetch all payments for this customer
$sql = "SELECT p.*, b.units_consumed, b.amount AS bill_amount 
        FROM payments p 
        JOIN bills b ON p.bill_id = b.id 
        WHERE p.customer_id=? 
        ORDER BY p.payment_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();

// Calculate correct totals from ALL bills (not just paid ones)
$totalSql = "SELECT COALESCE(SUM(amount), 0) as total_bills FROM bills WHERE customer_id=?";
$totalStmt = $conn->prepare($totalSql);
$totalStmt->bind_param("i", $customer_id);
$totalStmt->execute();
$totalStmt->bind_result($totalBills);
$totalStmt->fetch();
$totalStmt->close();

$paidSql = "SELECT COALESCE(SUM(amount), 0) as total_paid FROM payments WHERE customer_id=?";
$paidStmt = $conn->prepare($paidSql);
$paidStmt->bind_param("i", $customer_id);
$paidStmt->execute();
$paidStmt->bind_result($totalPaid);
$paidStmt->fetch();
$paidStmt->close();
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

/* Header with download button */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    flex-wrap: wrap;
    gap: 15px;
}

.download-btn {
    background-color: #90caf9;
    color: #121212;
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 1em;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: background 0.3s, transform 0.2s;
}

.download-btn:hover {
    background-color: #64b5f6;
    transform: translateY(-2px);
}

.download-btn:active {
    transform: translateY(0);
}

/* Alert Messages */
.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
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

/* Table */
.container {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

th, td {
    padding: 12px 15px;
    border: 1px solid #2c2c2c;
    text-align: left;
}

th {
    background-color: #1e1e1e;
    color: #90caf9;
}

tr:nth-child(even) {
    background-color: #1a1a1a;
}

tr:hover {
    background-color: #2c2c2c;
}

tfoot th {
    background-color: #222;
    font-weight: bold;
}

/* Action Buttons */
.action-btns {
    display: flex;
    gap: 5px;
}

.btn-action {
    padding: 6px 12px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 0.85em;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.3s;
}

.btn-edit {
    background-color: #1976d2;
    color: #fff;
}

.btn-edit:hover {
    background-color: #1565c0;
}

.btn-delete {
    background-color: #d32f2f;
    color: #fff;
}

.btn-delete:hover {
    background-color: #c62828;
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
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .download-btn {
        width: 100%;
        justify-content: center;
    }
    .action-btns {
        flex-direction: column;
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
        <div class="page-header">
            <h2>💰 Payment History</h2>
            <?php if ($result->num_rows > 0): ?>
                <a href="?download=csv" class="download-btn">
                    📥 Download CSV
                </a>
            <?php endif; ?>
        </div>
        <h3 class="welcome">Welcome, <?php echo htmlspecialchars($customerName); ?>!</h3>

        <?php if (isset($_SESSION['success_msg'])): ?>
            <div class="alert alert-success">
                ✅ <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_msg'])): ?>
            <div class="alert alert-error">
                ❌ <?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?>
            </div>
        <?php endif; ?>

        <div class="container">
            <?php if ($result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Bill ID</th>
                            <th>Units Consumed</th>
                            <th>Bill Amount</th>
                            <th>Amount Paid</th>
                            <th>Payment Method</th>
                            <th>Payment Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($payment = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $payment['payment_id']; ?></td>
                                <td><?php echo $payment['bill_id']; ?></td>
                                <td><?php echo $payment['units_consumed']; ?></td>
                                <td><?php echo formatCurrency($payment['bill_amount']); ?></td>
                                <td><?php echo formatCurrency($payment['amount']); ?></td>
                                <td><?php echo ucfirst($payment['method']); ?></td>
                                <td><?php echo $payment['payment_date']; ?></td>
                                <td>
                                    <div class="action-btns">
                                        <a href="edit_payment.php?payment_id=<?php echo $payment['payment_id']; ?>" 
                                           class="btn-action btn-edit">
                                            ✏️ Edit
                                        </a>
                                        <a href="?delete=1&payment_id=<?php echo $payment['payment_id']; ?>" 
                                           class="btn-action btn-delete"
                                           onclick="return confirm('Are you sure you want to delete this payment?');">
                                            🗑️ Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3" style="text-align:right;">Total Bill Amount:</th>
                            <th><?php echo formatCurrency($totalBills); ?></th>
                            <th colspan="4"></th>
                        </tr>
                        <tr>
                            <th colspan="3" style="text-align:right;">Total Paid:</th>
                            <th><?php echo formatCurrency($totalPaid); ?></th>
                            <th colspan="4"></th>
                        </tr>
                        <tr>
                            <th colspan="3" style="text-align:right; color:#ff6f61;">Amount Due:</th>
                            <th style="color:#ff6f61;">
                                <?php 
                                $due = $totalBills - $totalPaid; 
                                echo formatCurrency($due); 
                                ?>
                            </th>
                            <th colspan="4"></th>
                        </tr>
                    </tfoot>
                </table>
            <?php else: ?>
                <p>⚠ You have not made any payments yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$stmt->close();
require_once("../includes/footer.php");
?>