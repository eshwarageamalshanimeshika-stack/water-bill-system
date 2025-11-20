<?php
require_once("../includes/auth.php");
require_once("../includes/header.php");
require_once("../includes/functions.php");
require_once("../config.php");

$error = "";
$success = "";

// Handle connection status updates
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $customer_id = intval($_POST['status_customer_id']);
    $new_status = trim($_POST['new_status']);
    $action_reason = trim($_POST['action_reason']);
    
    $valid_statuses = ['active', 'disconnected', 'hold', 'read_bill'];
    
    if ($customer_id && in_array($new_status, $valid_statuses)) {
        // Get current status
        $currentStatusQuery = $conn->prepare("SELECT connection_status FROM customer WHERE customer_id = ?");
        $currentStatusQuery->bind_param("i", $customer_id);
        $currentStatusQuery->execute();
        $currentStatusQuery->bind_result($currentStatus);
        $currentStatusQuery->fetch();
        $currentStatusQuery->close();
        
        // Update customer status
        $updateStmt = $conn->prepare("UPDATE customer SET connection_status = ? WHERE customer_id = ?");
        $updateStmt->bind_param("si", $new_status, $customer_id);
        
        if ($updateStmt->execute()) {
            // Log the action
            $logStmt = $conn->prepare("INSERT INTO connection_logs (customer_id, previous_status, new_status, action_reason, action_date, performed_by) VALUES (?, ?, ?, ?, NOW(), ?)");
            $userId = $_SESSION['user_id'] ?? 'admin';
            $logStmt->bind_param("issss", $customer_id, $currentStatus, $new_status, $action_reason, $userId);
            $logStmt->execute();
            $logStmt->close();
            
            $statusLabels = [
                'active' => '🟢 Active/Reconnected',
                'disconnected' => '🔴 Disconnected',
                'hold' => '🟡 On Hold',
                'read_bill' => '📖 Read Bill Only'
            ];
            
            $success = "✅ Connection status updated to: " . $statusLabels[$new_status];
        } else {
            $error = "❌ Error updating status: " . $conn->error;
        }
        $updateStmt->close();
    } else {
        $error = "⚠ Invalid status or customer selection.";
    }
}

// Fetch customers for dropdown - Only valid tariffs
$customers = [];
$customerQuery = $conn->query("
    SELECT customer_id, name, account_no, tariff, connection_status 
    FROM customer 
    WHERE tariff IN ('domestic', 'industrial', 'commercial')
    ORDER BY name ASC
");
if ($customerQuery) {
    while ($row = $customerQuery->fetch_assoc()) {
        $customers[] = $row;
    }
}

// Handle new payment submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_payment'])) {
    $bill_id      = intval($_POST['bill_id']);
    $customer_id  = intval($_POST['customer_id']);
    $amount_paid  = floatval($_POST['amount_paid']);
    $method       = trim($_POST['method']);
    $date         = date("Y-m-d");

    if ($bill_id && $customer_id && $amount_paid > 0) {
        // Get bill details to validate payment amount
        $billCheckSql = "SELECT amount, status FROM bills WHERE id = ? AND customer_id = ?";
        $stmt = $conn->prepare($billCheckSql);
        $stmt->bind_param("ii", $bill_id, $customer_id);
        $stmt->execute();
        $stmt->bind_result($billAmount, $billStatus);
        $stmt->fetch();
        $stmt->close();

        if (!$billAmount) {
            $error = "⚠ Invalid bill selected.";
        } elseif ($billStatus == 'paid') {
            $error = "⚠ This bill has already been fully paid.";
        } else {
            // Get total paid so far for this bill
            $paidSql = "SELECT COALESCE(SUM(amount), 0) as total_paid FROM payments WHERE bill_id = ?";
            $stmt = $conn->prepare($paidSql);
            $stmt->bind_param("i", $bill_id);
            $stmt->execute();
            $stmt->bind_result($totalPaid);
            $stmt->fetch();
            $stmt->close();

            $outstanding = $billAmount - $totalPaid;

            if ($amount_paid > $outstanding) {
                $error = "⚠ Payment amount (Rs. " . number_format($amount_paid, 2) . ") exceeds outstanding balance of Rs. " . number_format($outstanding, 2);
            } else {
                // Insert payment
                $stmt = $conn->prepare("INSERT INTO payments (bill_id, customer_id, amount, method, payment_date) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iidss", $bill_id, $customer_id, $amount_paid, $method, $date);

                if ($stmt->execute()) {
                    $newTotalPaid = $totalPaid + $amount_paid;
                    $newRemaining = $billAmount - $newTotalPaid;
                    
                    // Update bill status if fully paid
                    if ($newTotalPaid >= $billAmount) {
                        $updateBillStmt = $conn->prepare("UPDATE bills SET status = 'paid' WHERE id = ?");
                        $updateBillStmt->bind_param("i", $bill_id);
                        $updateBillStmt->execute();
                        $updateBillStmt->close();
                        $success = "✅ Payment recorded successfully! Bill #" . $bill_id . " is now FULLY PAID.";
                        
                        // Check if all bills are paid and suggest reconnection
                        $allBillsQuery = $conn->query("
                            SELECT COUNT(*) as unpaid_count
                            FROM bills b
                            LEFT JOIN (SELECT bill_id, SUM(amount) as total_paid FROM payments GROUP BY bill_id) paid 
                            ON b.id = paid.bill_id
                            WHERE b.customer_id = $customer_id
                            AND (b.amount - COALESCE(paid.total_paid, 0)) > 0.01
                        ");
                        $unpaidCount = $allBillsQuery->fetch_assoc()['unpaid_count'] ?? 0;
                        
                        if ($unpaidCount == 0) {
                            $success .= " 🎉 All bills are now paid! Consider updating connection status to ACTIVE if disconnected.";
                        }
                    } else {
                        $success = "✅ Partial payment recorded successfully! Amount: Rs. " . number_format($amount_paid, 2) . 
                                  " | Remaining balance: Rs. " . number_format($newRemaining, 2);
                    }
                    
                    // Check if customer has other outstanding bills
                    $otherBillsQuery = $conn->query("
                        SELECT COUNT(*) as other_unpaid
                        FROM bills b
                        LEFT JOIN (
                            SELECT bill_id, SUM(amount) as total_paid 
                            FROM payments 
                            GROUP BY bill_id
                        ) paid ON b.id = paid.bill_id
                        WHERE b.customer_id = $customer_id
                        AND b.id != $bill_id
                        AND b.status = 'unpaid'
                        AND (b.amount - COALESCE(paid.total_paid, 0)) > 0.01
                    ");
                    $otherUnpaid = $otherBillsQuery->fetch_assoc()['other_unpaid'] ?? 0;
                    
                    if ($otherUnpaid > 0) {
                        $success .= " ⚠️ Note: Customer still has $otherUnpaid other unpaid bill(s).";
                    }
                } else {
                    $error = "❌ Error: " . $conn->error;
                }
                $stmt->close();
            }
        }
    } else {
        $error = "⚠ Please fill all required fields with valid values.";
    }
}

// Fetch all bills with outstanding amounts - including partial payments
$billsData = [];
$billsQuery = $conn->query("
    SELECT b.id AS bill_id, c.customer_id, c.name, c.account_no, c.tariff, c.connection_status,
           b.units_consumed, b.amount AS bill_amount,
           b.bill_date, b.due_date, b.status,
           COALESCE(SUM(p.amount), 0) AS paid_amount,
           (b.amount - COALESCE(SUM(p.amount), 0)) AS remaining_amount,
           COUNT(p.payment_id) as payment_count
    FROM bills b
    JOIN customer c ON b.customer_id = c.customer_id
    LEFT JOIN payments p ON b.id = p.bill_id
    WHERE c.tariff IN ('domestic', 'industrial', 'commercial')
    GROUP BY b.id
    HAVING remaining_amount > 0.01 OR b.status = 'unpaid'
    ORDER BY c.name ASC, b.bill_date DESC
");
if ($billsQuery) {
    while ($row = $billsQuery->fetch_assoc()) {
        $billsData[] = $row;
    }
}

// Pagination for payment history
$limit = 15;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Count total payments
$countResult = $conn->query("
    SELECT COUNT(*) AS total 
    FROM payments p
    JOIN bills b ON p.bill_id = b.id
    JOIN customer c ON p.customer_id = c.customer_id
    WHERE c.tariff IN ('domestic', 'industrial', 'commercial')
");
$totalRows = $countResult->fetch_assoc()['total'] ?? 0;
$totalPages = ceil($totalRows / $limit);

// Fetch payment history with remaining amounts
$payments = $conn->query("
   SELECT p.payment_id, p.amount AS amount_paid, p.method, p.payment_date AS date,
          b.id as bill_id, b.units_consumed, b.amount AS bill_amount,
          b.bill_date, b.status as bill_status,
          (SELECT COALESCE(SUM(p2.amount), 0) FROM payments p2 WHERE p2.bill_id = b.id) as total_paid_on_bill,
          (b.amount - (SELECT COALESCE(SUM(p2.amount), 0) FROM payments p2 WHERE p2.bill_id = b.id)) as remaining_on_bill,
          c.name, c.account_no, c.tariff, c.connection_status
   FROM payments p
   JOIN bills b ON p.bill_id = b.id
   JOIN customer c ON p.customer_id = c.customer_id
   WHERE c.tariff IN ('domestic', 'industrial', 'commercial')
   ORDER BY p.payment_date DESC, p.payment_id DESC
   LIMIT $limit OFFSET $offset
");

// Calculate summary statistics
$summaryQuery = $conn->query("
    SELECT 
        COUNT(DISTINCT b.id) as total_unpaid_bills,
        COUNT(DISTINCT b.customer_id) as customers_with_unpaid,
        COALESCE(SUM(b.amount - COALESCE(paid.total_paid, 0)), 0) as total_outstanding,
        SUM(CASE WHEN c.connection_status = 'disconnected' THEN 1 ELSE 0 END) as disconnected_customers,
        SUM(CASE WHEN c.connection_status = 'hold' THEN 1 ELSE 0 END) as hold_customers
    FROM bills b
    JOIN customer c ON b.customer_id = c.customer_id
    LEFT JOIN (
        SELECT bill_id, SUM(amount) as total_paid 
        FROM payments 
        GROUP BY bill_id
    ) paid ON b.id = paid.bill_id
    WHERE c.tariff IN ('domestic', 'industrial', 'commercial')
    AND (b.amount - COALESCE(paid.total_paid, 0)) > 0.01
");
$summary = $summaryQuery->fetch_assoc();

// Fetch customers by connection status
$customersByStatus = $conn->query("
    SELECT c.customer_id, c.name, c.account_no, c.house, c.tariff, c.connection_status,
           COUNT(DISTINCT b.id) as unpaid_bills,
           COALESCE(SUM(b.amount - COALESCE(paid.total_paid, 0)), 0) as total_outstanding
    FROM customer c
    LEFT JOIN bills b ON c.customer_id = b.customer_id
    LEFT JOIN (
        SELECT bill_id, SUM(amount) as total_paid 
        FROM payments 
        GROUP BY bill_id
    ) paid ON b.id = paid.bill_id
    WHERE c.tariff IN ('domestic', 'industrial', 'commercial')
    AND c.connection_status IN ('disconnected', 'hold', 'read_bill')
    GROUP BY c.customer_id
    ORDER BY c.connection_status, total_outstanding DESC
");

// Fetch outstanding bills table data
$outstandingBillsTable = $conn->query("
    SELECT b.id AS bill_id, c.customer_id, c.name, c.account_no, c.house, c.tariff, c.connection_status,
           b.units_consumed, b.amount AS bill_amount,
           b.bill_date, b.due_date, b.status,
           COALESCE(SUM(p.amount), 0) AS paid_amount,
           (b.amount - COALESCE(SUM(p.amount), 0)) AS outstanding_amount,
           COUNT(p.payment_id) as payment_count
    FROM bills b
    JOIN customer c ON b.customer_id = c.customer_id
    LEFT JOIN payments p ON b.id = p.bill_id
    WHERE c.tariff IN ('domestic', 'industrial', 'commercial')
    GROUP BY b.id
    HAVING outstanding_amount > 0.01 OR b.status = 'unpaid'
    ORDER BY c.connection_status DESC, outstanding_amount DESC, b.bill_date DESC
    LIMIT 20
");
?>

<style>
body { background: #121826; margin: 0; padding: 0; }
.dashboard-wrapper { display: flex; min-height: 100vh; }

/* Sidebar Styles */
.sidebar { 
    width: 240px; 
    background-color: #1f2a38; 
    color: #fff; 
    padding: 20px; 
    flex-shrink: 0;
    height: 100vh;
    position: sticky;
    top: 0;
    overflow-y: auto;
}
.sidebar h3 { 
    margin-bottom: 20px; 
    font-size: 18px; 
    border-bottom: 2px solid #4dabf7; 
    padding-bottom: 10px; 
    color: #e8edf1ff;
}
.sidebar a { 
    display: flex;
    align-items: center;
    gap: 10px;
    color: #fff; 
    text-decoration: none; 
    margin: 8px 0; 
    padding: 12px 15px; 
    border-radius: 8px; 
    transition: all 0.3s;
    font-size: 15px;
}
.sidebar a:hover { 
    background-color: #2c3e50; 
    transform: translateX(5px);
}
.sidebar a.active { 
    background-color: #2c3e50;
    color: #fff;
    font-weight: bold;
}
.logout-btn { 
    color: #ff6b6b !important; 
    font-weight: bold;
    margin-top: 20px;
    border-top: 1px solid #374151;
    padding-top: 20px !important;
}
.logout-btn:hover {
    background-color: #ff6b6b !important;
    color: #fff !important;
}

/* Main Content */
.main-content { 
    flex-grow: 1; 
    padding: 30px; 
    color: #f1f5f9;
    max-width: 1800px;
    margin: 0 auto;
}

h2 { 
    text-align: center; 
    margin-bottom: 30px; 
    color: #4dabf7;
    font-size: 32px;
}

h3 {
    color: #4dabf7;
    margin-top: 40px;
    margin-bottom: 20px;
    font-size: 24px;
}

/* Summary Cards */
.summary-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.summary-card {
    background: linear-gradient(145deg, #1e293b, #0f172a);
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.4);
    border-left: 4px solid #4dabf7;
    transition: transform 0.3s;
}

.summary-card:hover {
    transform: translateY(-5px);
}

.summary-card h4 {
    color: #9ca3af;
    font-size: 14px;
    margin: 0 0 10px 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.summary-card .value {
    color: #4dabf7;
    font-size: 28px;
    font-weight: bold;
}

.summary-card.warning {
    border-left-color: #f59e0b;
}

.summary-card.warning .value {
    color: #f59e0b;
}

.summary-card.danger {
    border-left-color: #ef4444;
}

.summary-card.danger .value {
    color: #ef4444;
}

/* Connection Status Badge */
.connection-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.conn-active { background: #22c55e; color: #000; }
.conn-disconnected { background: #ef4444; color: #fff; }
.conn-hold { background: #f59e0b; color: #000; }
.conn-read_bill { background: #3b82f6; color: #fff; }

 select,
.status-form textarea {
    width: 100%;
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #374151;
    background: #0f172a;
    color: #f1f1f1;
    font-size: 13px;
}

.status-form textarea {
    resize: vertical;
    min-height: 42px;
}

.status-form button {
    padding: 10px 20px;
    background: #ef4444;
    color: #fff;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: bold;
    font-size: 14px;
    transition: all 0.3s;
}

.status-form button:hover {
    background: #dc2626;
    transform: translateY(-2px);
}

/* Info Box */
.info-box {
    background: linear-gradient(145deg, #1e293b, #0f172a);
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    border-left: 4px solid #4dabf7;
    box-shadow: 0 4px 12px rgba(0,0,0,0.4);
}
.info-box h4 {
    color: #4dabf7;
    margin: 0 0 12px 0;
    font-size: 16px;
}
.info-box ul {
    margin: 0;
    padding-left: 20px;
    color: #9ca3af;
    font-size: 14px;
    line-height: 1.8;
}
.info-box ul li {
    margin: 8px 0;
}

/* Form Styles */
form { 
    background: linear-gradient(145deg, #1e293b, #0f172a); 
    padding: 30px; 
    border-radius: 12px; 
    margin-bottom: 30px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.4);
}

form label { 
    display: block; 
    margin-top: 15px;
    margin-bottom: 8px;
    color: #9ca3af;
    font-weight: 600;
    font-size: 14px;
}

form input, form select { 
    width: 100%; 
    padding: 12px; 
    margin-top: 5px; 
    border-radius: 8px; 
    border: 1px solid #374151;
    background: #0f172a;
    color: #f1f1f1;
    font-size: 14px;
    transition: border 0.3s;
}

form input:focus, form select:focus {
    outline: none;
    border-color: #4dabf7;
}

form input[readonly] {
    background: #1e293b;
    cursor: not-allowed;
    color: #9ca3af;
}

form button { 
    margin-top: 20px; 
    padding: 14px; 
    width: 100%; 
    background: #4dabf7; 
    color: #fff; 
    border: none; 
    border-radius: 8px; 
    cursor: pointer; 
    font-weight: bold;
    font-size: 16px;
    transition: all 0.3s;
}

form button:hover { 
    background: #3b9ae1;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(77, 171, 247, 0.4);
}

/* Table Styles */
table { 
    width: 100%; 
    border-collapse: collapse; 
    background: linear-gradient(145deg, #1e293b, #0f172a); 
    color: #f1f5f9; 
    border-radius: 12px; 
    overflow: hidden; 
    margin-top: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.4);
}

th { 
    background: #0f172a; 
    text-align: left;
    padding: 15px 10px;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 10px;
    letter-spacing: 0.5px;
    color: #9ca3af;
    border-bottom: 2px solid #4dabf7;
    white-space: nowrap;
}

td { 
    padding: 12px 10px; 
    border-bottom: 1px solid #374151;
    font-size: 12px;
}

tbody tr { 
    transition: background 0.3s; 
}

tbody tr:hover { 
    background: rgba(77, 171, 247, 0.1);
}

tbody tr.disconnected-row {
    background: rgba(239, 68, 68, 0.1);
}

tbody tr.hold-row {
    background: rgba(245, 158, 11, 0.1);
}

/* Alert Messages */
.error { 
    background: linear-gradient(145deg, #dc2626, #991b1b); 
    padding: 15px 20px; 
    border-radius: 8px; 
    margin-bottom: 20px; 
    color: #fff;
    font-weight: 500;
    box-shadow: 0 4px 8px rgba(220, 38, 38, 0.3);
}

.success { 
    background: linear-gradient(145deg, #10b981, #059669); 
    padding: 15px 20px; 
    border-radius: 8px; 
    margin-bottom: 20px; 
    color: #fff;
    font-weight: 500;
    box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
}

/* Tariff Badges */
.tariff-badge {
    display: inline-block;
    padding: 3px 6px;
    border-radius: 4px;
    font-size: 9px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.tariff-domestic { background: #3b82f6; color: #fff; }
.tariff-industrial { background: #8b5cf6; color: #fff; }
.tariff-commercial { background: #f59e0b; color: #000; }

/* Status Badges */
.status-badge {
    display: inline-block;
    padding: 3px 6px;
    border-radius: 4px;
    font-size: 9px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-paid { background: #22c55e; color: #000; }
.status-unpaid { background: #ef4444; color: #fff; }
.status-partial { background: #3b82f6; color: #fff; }

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #9ca3af;
    font-size: 16px;
}

.empty-state::before {
    content: "✅";
    display: block;
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 30px;
    flex-wrap: wrap;
}

.pagination a {
    padding: 10px 16px;
    background: #1e293b;
    color: #fff;
    text-decoration: none;
    border-radius: 6px;
    transition: all 0.3s;
    font-weight: 500;
}

.pagination a:hover {
    background: #2c3e50;
    transform: translateY(-2px);
}

.pagination a.active {
    background: #4dabf7;
    font-weight: bold;
}

hr {
    border: none;
    border-top: 1px solid #374151;
    margin: 40px 0;
}

/* Responsive Design */
@media (max-width: 768px) {
    .dashboard-wrapper {
        flex-direction: column;
    }
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
    }
    .status-form {
        grid-template-columns: 1fr;
    }
    table {
        font-size: 10px;
    }
    th, td {
        padding: 8px 5px;
    }
}
</style>

<div class="dashboard-wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
        <h3>Sidebar</h3>
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="customers.php">👥 Customers</a>
        <a href="meter_readings.php">💧 Meter Readings</a>
        <a href="bills.php">🧾 Bills</a>
        <a href="payments.php" class="active">💰 Payments</a>
        <a href="reports.php">📊 Reports</a>
        <a href="profile.php">👤 Profile</a>
        <a href="../auth/logout.php" class="logout-btn">🚪 Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h2>💳 Manage Payments & Connections</h2>

        <!-- Summary Cards -->
        <div class="summary-section">
            <div class="summary-card warning">
                <h4>📋 Unpaid Bills</h4>
                <div class="value"><?php echo $summary['total_unpaid_bills'] ?? 0; ?></div>
            </div>
            <div class="summary-card warning">
                <h4>👥 Customers with Unpaid Bills</h4>
                <div class="value"><?php echo $summary['customers_with_unpaid'] ?? 0; ?></div>
            </div>
            <div class="summary-card warning">
                <h4>💰 Total Outstanding</h4>
                <div class="value"><?php echo formatCurrency($summary['total_outstanding'] ?? 0); ?></div>
            </div>
            <div class="summary-card danger">
                <h4>🔴 Disconnected</h4>
                <div class="value"><?php echo $summary['disconnected_customers'] ?? 0; ?></div>
            </div>
            <div class="summary-card" style="border-left-color: #f59e0b;">
                <h4>🟡 On Hold</h4>
                <div class="value" style="color: #f59e0b;"><?php echo $summary['hold_customers'] ?? 0; ?></div>
            </div>
        </div>

        <!-- Connection Status Management -->
        <div class="connection-management">
            <h4>🔌 Connection Status Management</h4>
            <form method="POST" action="" class="status-form">
                <div>
                    <label for="status_customer_id">Select Customer:</label>
                    <select name="status_customer_id" id="status_customer_id" required>
                        <option value="">-- Select Customer --</option>
                        <?php foreach ($customers as $c): 
                            $statusIcon = [
                                'active' => '🟢',
                                'disconnected' => '🔴',
                                'hold' => '🟡',
                                'read_bill' => '📖'
                            ];
                            $icon = $statusIcon[$c['connection_status']] ?? '⚪';
                        ?>
                            <option value="<?php echo $c['customer_id']; ?>" data-status="<?php echo $c['connection_status']; ?>">
                                <?php echo $icon . " " . htmlspecialchars($c['name']) . " (" . htmlspecialchars($c['account_no']) . ") - " . strtoupper($c['connection_status']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="new_status">New Status:</label>
                    <select name="new_status" id="new_status" required>
                        <option value="">-- Select --</option>
                        <option value="active">🟢 Active/Reconnect</option>
                        <option value="disconnected">🔴 Disconnect</option>
                        <option value="hold">🟡 Hold</option>
                        <option value="read_bill">📖 Read Bill Only</option>
                    </select>
                </div>
                <div>
                    <label for="action_reason">Reason:</label>
                    <textarea name="action_reason" id="action_reason" required placeholder="Enter reason for status change"></textarea>
                </div>
                <div>
                    <button type="submit" name="update_status">Update Status</button>
                </div>
            </form>
        </div>

        <!-- Customers with Special Status -->
        <?php if ($customersByStatus && $customersByStatus->num_rows > 0): ?>
        <div class="info-box" style="border-left-color: #ef4444;">
            <h4>⚠️ Customers Requiring Attention (<?php echo $customersByStatus->num_rows; ?>)</h4>
            <table style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th>Name</th>
                        <th>House</th>
                        <th>Status</th>
                        <th>Unpaid Bills</th>
                        <th>Outstanding</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($cs = $customersByStatus->fetch_assoc()): 
                        $connClass = 'conn-' . strtolower($cs['connection_status']);
                        $rowClass = '';
                        if ($cs['connection_status'] == 'disconnected') $rowClass = 'disconnected-row';
                        elseif ($cs['connection_status'] == 'hold') $rowClass = 'hold-row';
                    ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td><?php echo htmlspecialchars($cs['account_no']); ?></td>
                            <td><?php echo htmlspecialchars($cs['name']); ?></td>
                            <td><?php echo htmlspecialchars($cs['house']); ?></td>
                            <td>
                                <span class="connection-badge <?php echo $connClass; ?>">
                                    <?php echo strtoupper(str_replace('_', ' ', $cs['connection_status'])); ?>
                                </span>
                            </td>
                            <td><?php echo $cs['unpaid_bills']; ?></td>
                            <td style="color: #f59e0b; font-weight: bold;"><?php echo formatCurrency($cs['total_outstanding']); ?></td>
                            <td>
                                <?php if ($cs['total_outstanding'] == 0): ?>
                                    <span style="color: #22c55e; font-size: 11px;">✓ Can Reconnect</span>
                                <?php else: ?>
                                    <span style="color: #ef4444; font-size: 11px;">⚠ Payment Needed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

       

        <!-- Add Payment Form -->
        <form method="POST" action="" id="paymentForm">
            <h3 style="margin-top: 0; color: #4dabf7;">💰 Record New Payment</h3>
            
            <label for="customer_id">Customer:</label>
            <select name="customer_id" id="customer_id" required>
                <option value="">-- Select Customer --</option>
                <?php foreach ($customers as $c): 
                    $tariffDisplay = ucfirst($c['tariff']);
                    $statusIcon = [
                        'active' => '🟢',
                        'disconnected' => '🔴',
                        'hold' => '🟡',
                        'read_bill' => '📖'
                    ];
                    $icon = $statusIcon[$c['connection_status']] ?? '⚪';
                ?>
                    <option value="<?php echo $c['customer_id']; ?>">
                        <?php echo $icon . " " . htmlspecialchars($c['name']) . " (Acc: " . htmlspecialchars($c['account_no']) . ") - " . $tariffDisplay; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="bill_id">Select Bill to Pay (Unpaid/Partially Paid Only):</label>
            <select name="bill_id" id="bill_id" required>
                <option value="">-- Select Bill --</option>
                <?php foreach ($billsData as $b): 
                    $isPartial = $b['paid_amount'] > 0;
                    $statusLabel = $isPartial ? '[PARTIAL PAID]' : '[UNPAID]';
                    
                    // Calculate customer's total outstanding across all bills
                    $customerOutstandingQuery = $conn->query("
                        SELECT SUM(b.amount - COALESCE(paid.total_paid, 0)) as customer_total_outstanding
                        FROM bills b
                        LEFT JOIN (
                            SELECT bill_id, SUM(amount) as total_paid 
                            FROM payments 
                            GROUP BY bill_id
                        ) paid ON b.id = paid.bill_id
                        WHERE b.customer_id = " . $b['customer_id'] . "
                        AND b.status = 'unpaid'
                    ");
                    $customerOutstanding = $customerOutstandingQuery->fetch_assoc()['customer_total_outstanding'] ?? 0;
                    
                    // Calculate previous bills outstanding (excluding current bill)
                    $previousOutstandingQuery = $conn->query("
                        SELECT SUM(b.amount - COALESCE(paid.total_paid, 0)) as previous_outstanding
                        FROM bills b
                        LEFT JOIN (
                            SELECT bill_id, SUM(amount) as total_paid 
                            FROM payments 
                            GROUP BY bill_id
                        ) paid ON b.id = paid.bill_id
                        WHERE b.customer_id = " . $b['customer_id'] . "
                        AND b.id != " . $b['bill_id'] . "
                        AND b.status = 'unpaid'
                    ");
                    $previousOutstanding = $previousOutstandingQuery->fetch_assoc()['previous_outstanding'] ?? 0;
                    
                    $statusIcon = [
                        'active' => '🟢',
                        'disconnected' => '🔴',
                        'hold' => '🟡',
                        'read_bill' => '📖'
                    ];
                    $icon = $statusIcon[$b['connection_status']] ?? '⚪';
                ?>
                    <option value="<?php echo $b['bill_id']; ?>" 
                        data-customer="<?php echo $b['customer_id']; ?>"
                        data-units="<?php echo $b['units_consumed']; ?>"
                        data-bill="<?php echo $b['bill_amount']; ?>"
                        data-paid="<?php echo $b['paid_amount']; ?>"
                        data-remaining="<?php echo $b['remaining_amount']; ?>"
                        data-date="<?php echo $b['bill_date']; ?>"
                        data-payments="<?php echo $b['payment_count']; ?>"
                        data-previous-outstanding="<?php echo $previousOutstanding; ?>"
                        data-customer-total-outstanding="<?php echo $customerOutstanding; ?>"
                        data-connection-status="<?php echo $b['connection_status']; ?>">
                        <?php echo $icon . " Bill #" . $b['bill_id'] . " | " . htmlspecialchars($b['name']) . " - This Bill: " . 
                        formatCurrency($b['remaining_amount']) . " " . $statusLabel; 
                        if ($previousOutstanding > 0) {
                            echo " | Previous Bills: " . formatCurrency($previousOutstanding);
                        }
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div id="connection_status_alert" style="display: none; background: #450a0a; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #ef4444;">
                <label style="color: #ef4444; margin: 0 0 8px 0;">⚠️ Connection Status:</label>
                <input type="text" id="connection_status_display" readonly 
                       style="font-weight: bold; color: #ef4444; background: #1e293b; border-color: #ef4444;">
                <small style="color: #fca5a5; display: block; margin-top: 5px;" id="connection_status_message">
                </small>
            </div>

            <label>Units Consumed:</label>
            <input type="text" id="units_consumed" readonly placeholder="Select a bill">

            <label>Current Bill Amount:</label>
            <input type="text" id="bill_amount" readonly placeholder="Select a bill">

            <label>Bill Date:</label>
            <input type="text" id="bill_date" readonly placeholder="Select a bill">

            <label>Already Paid on This Bill:</label>
            <input type="text" id="paid_amount" readonly placeholder="Select a bill">

            <label>Remaining on This Bill:</label>
            <input type="text" id="remaining_amount" readonly placeholder="Select a bill" 
                   style="font-weight: bold; color: #f59e0b;">

            <div id="previous_outstanding_section" style="display: none; background: #450a0a; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #ef4444;">
                <label style="color: #ef4444; margin: 0 0 8px 0;">⚠️ Previous Outstanding Balance (Other Bills):</label>
                <input type="text" id="previous_outstanding" readonly 
                       style="font-weight: bold; color: #ef4444; background: #1e293b; border-color: #ef4444;">
                <small style="color: #fca5a5; display: block; margin-top: 5px;">
                    💡 This customer has unpaid balance from previous bills. Each bill is tracked separately.
                </small>
            </div>

            <label style="color: #4dabf7; font-size: 16px;">📊 Total Amount Due (All Bills Combined):</label>
            <input type="text" id="total_customer_outstanding" readonly placeholder="Select a bill" 
                   style="font-weight: bold; color: #4dabf7; font-size: 16px; background: #0f172a; border: 2px solid #4dabf7;">
            <small style="color: #9ca3af; display: block; margin-top: -8px; margin-bottom: 10px;">
                ℹ️ This shows the customer's complete outstanding balance across all unpaid bills
            </small>

            <label for="amount_paid">Amount to Pay Now: <span style="color: #ef4444;">*</span></label>
            <input type="number" step="0.01" name="amount_paid" id="amount_paid" required 
                   placeholder="Enter amount to pay on THIS bill" min="0.01">
            <small style="color: #9ca3af; display: block; margin-top: -8px; margin-bottom: 10px;">
                💡 Payment will be applied to the selected bill. Maximum: remaining balance of this bill.
            </small>

            <label>Balance After This Payment:</label>
            <input type="text" id="balance_after" readonly placeholder="Enter payment amount">
            <small style="color: #9ca3af; display: block; margin-top: -8px; margin-bottom: 10px;">
                📌 This shows remaining balance on the selected bill only
            </small>

            <label for="method">Payment Method: <span style="color: #ef4444;">*</span></label>
            <select name="method" required>
                <option value="cash">💵 Cash</option>
                <option value="card">💳 Card</option>
                <option value="online">🏦 Bank Transfer</option>
            </select>

            <button type="submit" name="add_payment">💾 Record Payment</button>
        </form>

        <hr>

        <!-- Outstanding Bills Table -->
        <h3>⚠️ Outstanding Bills (Complete List)</h3>
        <p style="color: #9ca3af; margin-bottom: 20px;">Each bill is tracked separately with its own payment history. This table shows all unpaid or partially paid bills.</p>
        <?php if ($outstandingBillsTable && $outstandingBillsTable->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Bill ID</th>
                        <th>Account</th>
                        <th>Customer</th>
                        <th>House</th>
                        <th>Tariff</th>
                        <th>Units</th>
                        <th>Bill Total</th>
                        <th>Paid</th>
                        <th>Outstanding</th>
                        <th>Bill Date</th>
                        <th>Due Date</th>
                        <th>Bill Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($ob = $outstandingBillsTable->fetch_assoc()): 
                        $tariffClass = 'tariff-' . strtolower($ob['tariff']);
                        $isPartial = $ob['paid_amount'] > 0;
                        $statusBadge = $isPartial ? 'status-partial' : 'status-unpaid';
                        $statusText = $isPartial ? 'PARTIAL' : 'UNPAID';
                        $connClass = 'conn-' . strtolower($ob['connection_status']);
                        $rowClass = '';
                        if ($ob['connection_status'] == 'disconnected') $rowClass = 'disconnected-row';
                        elseif ($ob['connection_status'] == 'hold') $rowClass = 'hold-row';
                    ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td>
                                <span class="connection-badge <?php echo $connClass; ?>">
                                    <?php echo strtoupper(str_replace('_', ' ', $ob['connection_status'])); ?>
                                </span>
                            </td>
                            <td><strong><?php echo $ob['bill_id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($ob['account_no']); ?></td>
                            <td><?php echo htmlspecialchars($ob['name']); ?></td>
                            <td><?php echo htmlspecialchars($ob['house']); ?></td>
                            <td><span class="tariff-badge <?php echo $tariffClass; ?>"><?php echo strtoupper($ob['tariff']); ?></span></td>
                            <td><?php echo number_format($ob['units_consumed'], 2); ?></td>
                            <td><?php echo formatCurrency($ob['bill_amount']); ?></td>
                            <td style="color: #22c55e; font-weight: bold;"><?php echo formatCurrency($ob['paid_amount']); ?></td>
                            <td style="color: #f59e0b; font-weight: bold; font-size: 13px;"><?php echo formatCurrency($ob['outstanding_amount']); ?></td>
                            <td><?php echo $ob['bill_date']; ?></td>
                            <td><?php echo $ob['due_date']; ?></td>
                            <td><span class="status-badge <?php echo $statusBadge; ?>"><?php echo $statusText; ?></span></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <p>No outstanding bills. All bills are paid!</p>
            </div>
        <?php endif; ?>

        <hr>

        <!-- Payment History -->
        <h3>📜 Payment History</h3>
        <p style="color: #9ca3af; margin-bottom: 20px;">Complete payment history showing which payments were applied to which bills. Each payment is tracked separately.</p>
        <?php if ($payments && $payments->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Payment ID</th>
                        <th>Customer</th>
                        <th>Account</th>
                        <th>Tariff</th>
                        <th>Connection</th>
                        <th>Bill ID</th>
                        <th>Bill Total</th>
                        <th>This Payment</th>
                        <th>Total Paid</th>
                        <th>Remaining</th>
                        <th>Method</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($p = $payments->fetch_assoc()): 
                        $tariffClass = 'tariff-' . strtolower($p['tariff']);
                        $statusClass = 'status-' . strtolower($p['bill_status']);
                        $isPartial = $p['remaining_on_bill'] > 0.01 && $p['bill_status'] == 'unpaid';
                        $connClass = 'conn-' . strtolower($p['connection_status']);
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($p['payment_id']); ?></td>
                            <td><?php echo htmlspecialchars($p['name']); ?></td>
                            <td><?php echo htmlspecialchars($p['account_no']); ?></td>
                            <td><span class="tariff-badge <?php echo $tariffClass; ?>"><?php echo strtoupper($p['tariff']); ?></span></td>
                            <td>
                                <span class="connection-badge <?php echo $connClass; ?>" style="font-size: 8px;">
                                    <?php echo strtoupper(str_replace('_', ' ', $p['connection_status'])); ?>
                                </span>
                            </td>
                            <td><strong><?php echo $p['bill_id']; ?></strong></td>
                            <td><?php echo formatCurrency($p['bill_amount']); ?></td>
                            <td style="color: #22c55e; font-weight: bold;"><?php echo formatCurrency($p['amount_paid']); ?></td>
                            <td style="color: #10b981; font-weight: bold;"><?php echo formatCurrency($p['total_paid_on_bill']); ?></td>
                            <td style="color: <?php echo $p['remaining_on_bill'] > 0.01 ? '#f59e0b' : '#22c55e'; ?>; font-weight: bold;">
                                <?php echo formatCurrency($p['remaining_on_bill']); ?>
                            </td>
                            <td><?php echo ucfirst($p['method']); ?></td>
                            <td><?php echo $p['date']; ?></td>
                            <td>
                                <?php if ($isPartial): ?>
                                    <span class="status-badge status-partial">PARTIAL</span>
                                <?php else: ?>
                                    <span class="status-badge <?php echo $statusClass; ?>"><?php echo strtoupper($p['bill_status']); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?= $i; ?>" class="<?= ($i == $page) ? 'active' : ''; ?>">
                            <?= $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <p>No payments recorded yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Filter bills by customer
document.getElementById('customer_id').addEventListener('change', function() {
    var selectedCustomer = this.value;
    var billSelect = document.getElementById('bill_id');
    for (var i=0; i<billSelect.options.length; i++) {
        var opt = billSelect.options[i];
        if(opt.value=="") { opt.style.display="block"; continue; }
        if(opt.getAttribute('data-customer') === selectedCustomer) { opt.style.display="block"; } 
        else { opt.style.display="none"; }
    }
    billSelect.value = "";
    clearForm();
});

// Autofill bill details
document.getElementById('bill_id').addEventListener('change', function() {
    var option = this.options[this.selectedIndex];
    if (!option.value) {
        clearForm();
        return;
    }
    
    var units = option.getAttribute('data-units');
    var billAmount = parseFloat(option.getAttribute('data-bill')) || 0;
    var paidAmount = parseFloat(option.getAttribute('data-paid')) || 0;
    var remaining = parseFloat(option.getAttribute('data-remaining')) || 0;
    var billDate = option.getAttribute('data-date') || '';
    var previousOutstanding = parseFloat(option.getAttribute('data-previous-outstanding')) || 0;
    var customerTotalOutstanding = parseFloat(option.getAttribute('data-customer-total-outstanding')) || 0;
    var connectionStatus = option.getAttribute('data-connection-status') || 'active';

    document.getElementById('units_consumed').value = parseFloat(units).toFixed(2) + ' units';
    document.getElementById('bill_amount').value = 'Rs. ' + billAmount.toFixed(2);
    document.getElementById('bill_date').value = billDate;
    document.getElementById('paid_amount').value = 'Rs. ' + paidAmount.toFixed(2);
    document.getElementById('remaining_amount').value = 'Rs. ' + remaining.toFixed(2);
    
    // Show/hide previous outstanding section
    var previousOutstandingSection = document.getElementById('previous_outstanding_section');
    if (previousOutstanding > 0) {
        previousOutstandingSection.style.display = 'block';
        document.getElementById('previous_outstanding').value = 'Rs. ' + previousOutstanding.toFixed(2);
    } else {
        previousOutstandingSection.style.display = 'none';
    }
    
    // Show total customer outstanding
    document.getElementById('total_customer_outstanding').value = 'Rs. ' + customerTotalOutstanding.toFixed(2);
    
    // Show connection status alert
    var connectionStatusAlert = document.getElementById('connection_status_alert');
    var connectionStatusDisplay = document.getElementById('connection_status_display');
    var connectionStatusMessage = document.getElementById('connection_status_message');
    
    var statusLabels = {
        'active': '🟢 ACTIVE',
        'disconnected': '🔴 DISCONNECTED',
        'hold': '🟡 ON HOLD',
        'read_bill': '📖 READ BILL ONLY'
    };
    
    var statusMessages = {
        'active': '✓ Customer has active connection',
        'disconnected': '⚠ Customer is disconnected. Full payment required for reconnection.',
        'hold': '⚠ Customer connection is on hold. Check reason before processing.',
        'read_bill': '⚠ Customer is in grace period. Only meter reading allowed.'
    };
    
    if (connectionStatus !== 'active') {
        connectionStatusAlert.style.display = 'block';
        connectionStatusDisplay.value = statusLabels[connectionStatus] || connectionStatus;
        connectionStatusMessage.textContent = statusMessages[connectionStatus] || '';
    } else {
        connectionStatusAlert.style.display = 'none';
    }
    
    var paid = parseFloat(document.getElementById('amount_paid').value) || 0;
    document.getElementById('balance_after').value = 'Rs. ' + (remaining - paid).toFixed(2);
});

// Update balance after payment when typing amount paid
document.getElementById('amount_paid').addEventListener('input', function() {
    var remaining = parseFloat(document.getElementById('remaining_amount').value.replace('Rs. ', '')) || 0;
    var paid = parseFloat(this.value) || 0;
    var balanceAfter = remaining - paid;
    document.getElementById('balance_after').value = 'Rs. ' + balanceAfter.toFixed(2);
    
    // Warning if overpayment on this bill
    if (paid > remaining) {
        this.style.borderColor = '#ef4444';
        document.getElementById('balance_after').style.color = '#ef4444';
    } else {
        this.style.borderColor = '#4dabf7';
        document.getElementById('balance_after').style.color = balanceAfter > 0 ? '#f59e0b' : '#22c55e';
    }
});

function clearForm() {
    document.getElementById('units_consumed').value = "";
    document.getElementById('bill_amount').value = "";
    document.getElementById('bill_date').value = "";
    document.getElementById('paid_amount').value = "";
    document.getElementById('remaining_amount').value = "";
    document.getElementById('amount_paid').value = "";
    document.getElementById('balance_after').value = "";
    document.getElementById('previous_outstanding_section').style.display = 'none';
    document.getElementById('total_customer_outstanding').value = "";
    document.getElementById('connection_status_alert').style.display = 'none';
}
</script>

<?php 
// Close database connection
$conn->close();
require_once("../includes/footer.php"); 
?>