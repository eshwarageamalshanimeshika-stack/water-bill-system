<?php
require_once("../includes/auth.php");
require_once("../config.php");

// -------------------------
// Handle Download Request - MUST BE BEFORE ANY HTML OUTPUT
// -------------------------
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    $selectedCustomer = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
    $selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    $selectedMonth = isset($_GET['month']) ? intval($_GET['month']) : date('m');
    
    // Get revenue
    $revenueSql = "SELECT SUM(amount) AS total_revenue FROM payments WHERE YEAR(payment_date) = ? AND MONTH(payment_date) = ?";
    $params = [$selectedYear, $selectedMonth];
    $types = "ii";
    
    if ($selectedCustomer) {
        $revenueSql .= " AND customer_id = ?";
        $params[] = $selectedCustomer;
        $types .= "i";
    }
    
    $stmt = $conn->prepare($revenueSql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $revenueResult = $stmt->get_result();
    $totalRevenue = $revenueResult->fetch_assoc()['total_revenue'] ?? 0;
    $stmt->close();
    
    // Get unpaid bills
    $unpaidSql = "
        SELECT COUNT(*) AS unpaid_count, SUM(b.amount) AS unpaid_amount
        FROM bills b
        WHERE b.status IN ('unpaid', 'overdue')
        AND YEAR(b.bill_date) = ? AND MONTH(b.bill_date) = ?
    ";
    $params = [$selectedYear, $selectedMonth];
    $types = "ii";
    
    if ($selectedCustomer) {
        $unpaidSql .= " AND b.customer_id = ?";
        $params[] = $selectedCustomer;
        $types .= "i";
    }
    
    $stmt = $conn->prepare($unpaidSql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $unpaidResult = $stmt->get_result();
    $unpaidData = $unpaidResult->fetch_assoc();
    $unpaidCount = $unpaidData['unpaid_count'] ?? 0;
    $unpaidAmount = $unpaidData['unpaid_amount'] ?? 0;
    $stmt->close();
    
    // Get customer details with consumption
    $detailsSql = "
        SELECT 
            c.customer_id,
            c.name,
            c.account_no,
            c.email,
            c.phone,
            c.house,
            c.house_no,
            c.tariff,
            SUM(b.units_consumed) AS total_units,
            SUM(b.amount) AS total_billed,
            SUM(CASE WHEN b.status = 'paid' THEN b.amount ELSE 0 END) AS total_paid,
            SUM(CASE WHEN b.status IN ('unpaid', 'overdue') THEN b.amount ELSE 0 END) AS total_unpaid
        FROM customer c
        LEFT JOIN bills b ON c.customer_id = b.customer_id 
            AND YEAR(b.bill_date) = ? AND MONTH(b.bill_date) = ?
        WHERE 1=1
    ";
    $params = [$selectedYear, $selectedMonth];
    $types = "ii";
    
    if ($selectedCustomer) {
        $detailsSql .= " AND c.customer_id = ?";
        $params[] = $selectedCustomer;
        $types .= "i";
    }
    $detailsSql .= " GROUP BY c.customer_id ORDER BY total_units DESC";
    
    $stmt = $conn->prepare($detailsSql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $detailsResult = $stmt->get_result();
    
    // Generate CSV
    $filename = "water_report_" . date('M_Y', mktime(0,0,0,$selectedMonth,1,$selectedYear)) . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 support
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Report header
    fputcsv($output, ['Water Billing System - Monthly Report']);
    fputcsv($output, ['Period', date('M-Y', mktime(0,0,0,$selectedMonth,1,$selectedYear))]);
    fputcsv($output, ['Generated On', date('m/d/Y')]);
    fputcsv($output, []);
    
    // Summary section
    fputcsv($output, ['SUMMARY']);
    fputcsv($output, ['Total Revenue Collected', number_format($totalRevenue, 2, '.', '')]);
    fputcsv($output, ['Unpaid Bills Count', $unpaidCount]);
    fputcsv($output, ['Unpaid Bills Amount', number_format($unpaidAmount, 2, '.', '')]);
    fputcsv($output, []);
    
    // Customer details section
    fputcsv($output, ['CUSTOMER DETAILS']);
    fputcsv($output, ['Customer ID', 'Account No', 'Full Name', 'Email', 'Phone', 'House', 'House No', 'Tariff', 'Units Consumed', 'Total Billed', 'Total Paid', 'Total Unpaid']);
    
    while ($row = $detailsResult->fetch_assoc()) {
        fputcsv($output, [
            $row['customer_id'],
            $row['account_no'],
            $row['name'],
            $row['email'],
            $row['phone'],
            $row['house'],
            $row['house_no'],
            $row['tariff'],
            $row['total_units'] ?? 0,
            number_format($row['total_billed'] ?? 0, 2, '.', ''),
            number_format($row['total_paid'] ?? 0, 2, '.', ''),
            number_format($row['total_unpaid'] ?? 0, 2, '.', '')
        ]);
    }
    
    fclose($output);
    $stmt->close();
    $conn->close();
    exit();
}

// NOW include header and other files (after CSV check)
require_once("../includes/header.php");
require_once("../includes/functions.php");

// -------------------------
// Get selected filters
// -------------------------
$selectedCustomer = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$selectedMonth = isset($_GET['month']) ? intval($_GET['month']) : date('m');

// -------------------------
// Fetch all customers for dropdown
// -------------------------
$customers = [];
$customerQuery = $conn->query("SELECT customer_id, name, account_no FROM customer ORDER BY name ASC");
if ($customerQuery) {
    while ($row = $customerQuery->fetch_assoc()) {
        $customers[] = $row;
    }
}

// -------------------------
// Total Revenue Collected
// -------------------------
$revenueSql = "SELECT SUM(amount) AS total_revenue FROM payments WHERE YEAR(payment_date) = ? AND MONTH(payment_date) = ?";
$params = [$selectedYear, $selectedMonth];
$types = "ii";

if ($selectedCustomer) {
    $revenueSql .= " AND customer_id = ?";
    $params[] = $selectedCustomer;
    $types .= "i";
}

$stmt = $conn->prepare($revenueSql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$revenueResult = $stmt->get_result();
$totalRevenue = $revenueResult->fetch_assoc()['total_revenue'] ?? 0;
$stmt->close();

// -------------------------
// Total Unpaid Bills
// -------------------------
$unpaidSql = "
    SELECT COUNT(*) AS unpaid_count, SUM(b.amount) AS unpaid_amount
    FROM bills b
    WHERE b.status IN ('unpaid', 'overdue')
    AND YEAR(b.bill_date) = ? AND MONTH(b.bill_date) = ?
";
$params = [$selectedYear, $selectedMonth];
$types = "ii";

if ($selectedCustomer) {
    $unpaidSql .= " AND b.customer_id = ?";
    $params[] = $selectedCustomer;
    $types .= "i";
}

$stmt = $conn->prepare($unpaidSql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$unpaidResult = $stmt->get_result();
$unpaidData = $unpaidResult->fetch_assoc();
$unpaidCount = $unpaidData['unpaid_count'] ?? 0;
$unpaidAmount = $unpaidData['unpaid_amount'] ?? 0;
$stmt->close();

// -------------------------
// Customer Details with Consumption
// -------------------------
$detailsSql = "
    SELECT 
        c.customer_id,
        c.name,
        c.account_no,
        c.email,
        c.phone,
        c.house,
        c.house_no,
        c.tariff,
        SUM(b.units_consumed) AS total_units,
        SUM(b.amount) AS total_billed,
        SUM(CASE WHEN b.status = 'paid' THEN b.amount ELSE 0 END) AS total_paid,
        SUM(CASE WHEN b.status IN ('unpaid', 'overdue') THEN b.amount ELSE 0 END) AS total_unpaid
    FROM customer c
    LEFT JOIN bills b ON c.customer_id = b.customer_id 
        AND YEAR(b.bill_date) = ? AND MONTH(b.bill_date) = ?
    WHERE 1=1
";
$params = [$selectedYear, $selectedMonth];
$types = "ii";

if ($selectedCustomer) {
    $detailsSql .= " AND c.customer_id = ?";
    $params[] = $selectedCustomer;
    $types .= "i";
}
$detailsSql .= " GROUP BY c.customer_id ORDER BY total_units DESC";

$stmt = $conn->prepare($detailsSql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$detailsResult = $stmt->get_result();

// -------------------------
// Generate year and month options
// -------------------------
$currentYear = date('Y');
$years = range($currentYear, $currentYear - 5);
$months = range(1, 12);
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
.report-container { 
    flex-grow: 1;
    max-width: 1600px; 
    margin: 0 auto; 
    padding: 30px; 
    color: #f1f1f1; 
}

/* Report Header */
.report-header { 
    text-align: center; 
    margin-bottom: 20px; 
    border-bottom: 2px solid #4dabf7; 
    padding-bottom: 20px; 
}
.report-header h1 { 
    font-size: 32px; 
    color: #4dabf7; 
    margin: 0 0 10px 0; 
}
.report-header .period { 
    font-size: 20px; 
    color: #9ca3af; 
    margin: 5px 0; 
}
.report-header .generated { 
    font-size: 14px; 
    color: #6b7280; 
}

/* Filter Section */
.filter-section { 
    background: linear-gradient(145deg, #1e293b, #0f172a); 
    padding: 25px; 
    border-radius: 12px; 
    margin-bottom: 30px; 
    box-shadow: 0 4px 12px rgba(0,0,0,0.4); 
}
.filter-section h3 { 
    color: #4dabf7; 
    margin-top: 0; 
    margin-bottom: 20px;
    font-size: 18px;
}
.filter-form { 
    display: flex; 
    flex-wrap: wrap; 
    gap: 15px; 
    align-items: flex-end; 
}
.filter-group { 
    display: flex; 
    flex-direction: column; 
    flex: 1;
    min-width: 180px;
}
.filter-group label { 
    color: #9ca3af; 
    margin-bottom: 8px; 
    font-size: 14px; 
    font-weight: 600; 
}
.filter-group select { 
    padding: 12px; 
    border-radius: 8px; 
    border: 1px solid #374151; 
    background: #0f172a; 
    color: #f1f1f1; 
    font-size: 14px;
    cursor: pointer;
    transition: border 0.3s;
}
.filter-group select:hover { 
    border-color: #4dabf7; 
}
.filter-group select:focus { 
    outline: none; 
    border-color: #4dabf7; 
}
.filter-actions { 
    display: flex; 
    gap: 10px; 
    align-items: flex-end; 
}
.btn { 
    padding: 12px 20px; 
    border-radius: 8px; 
    border: none; 
    cursor: pointer; 
    font-weight: 600; 
    text-decoration: none; 
    display: inline-flex; 
    align-items: center; 
    gap: 8px; 
    transition: all 0.3s;
    font-size: 14px;
}
.btn-filter { 
    background: #4dabf7; 
    color: #fff; 
}
.btn-filter:hover { 
    background: #3b9ae1; 
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(77, 171, 247, 0.4);
}
.btn-download { 
    background: #10b981; 
    color: #fff; 
}
.btn-download:hover { 
    background: #059669; 
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(16, 185, 129, 0.4);
}

/* Summary Section */
.summary-section { 
    margin-bottom: 30px; 
}
.summary-cards { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
    gap: 20px; 
}
.summary-card { 
    background: linear-gradient(145deg, #1e293b, #0f172a); 
    padding: 25px; 
    border-radius: 12px; 
    box-shadow: 0 4px 12px rgba(0,0,0,0.4);
    transition: transform 0.3s;
}
.summary-card:hover {
    transform: translateY(-5px);
}
.summary-card h3 { 
    font-size: 14px; 
    color: #9ca3af; 
    margin: 0 0 15px 0; 
    text-transform: uppercase; 
    letter-spacing: 1px; 
}
.summary-card .value { 
    font-size: 32px; 
    font-weight: bold; 
    color: #4dabf7; 
}
.summary-card .sub-value { 
    font-size: 16px; 
    color: #9ca3af; 
    margin-top: 10px; 
}

/* Details Section */
.details-section { 
    background: linear-gradient(145deg, #1e293b, #0f172a); 
    padding: 25px; 
    border-radius: 12px; 
    box-shadow: 0 4px 12px rgba(0,0,0,0.4); 
    overflow-x: auto;
}
.details-section h3 { 
    color: #4dabf7; 
    margin-top: 0; 
    font-size: 20px; 
    margin-bottom: 20px;
}
.report-table { 
    width: 100%; 
    border-collapse: collapse; 
    margin-top: 15px; 
    min-width: 1200px;
}
.report-table thead { 
    background: #0f172a; 
}
.report-table th { 
    padding: 15px 10px; 
    text-align: left; 
    color: #9ca3af; 
    font-weight: 600; 
    text-transform: uppercase; 
    font-size: 11px; 
    letter-spacing: 0.5px; 
    border-bottom: 2px solid #4dabf7; 
    white-space: nowrap;
}
.report-table td { 
    padding: 15px 10px; 
    border-bottom: 1px solid #374151; 
    color: #f1f1f1; 
    font-size: 13px;
}
.report-table tbody tr { 
    transition: background 0.3s; 
}
.report-table tbody tr:hover { 
    background: rgba(77, 171, 247, 0.1); 
}
.no-data { 
    text-align: center; 
    padding: 40px; 
    color: #9ca3af; 
    font-style: italic; 
    font-size: 16px;
}

/* Status badges */
.badge {
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    display: inline-block;
}
.badge-domestic { background: #3b82f6; color: #fff; }
.badge-commercial { background: #8b5cf6; color: #fff; }
.badge-industrial { background: #f59e0b; color: #fff; }

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
    .filter-form {
        flex-direction: column;
    }
    .filter-group {
        width: 100%;
    }
    .filter-actions {
        width: 100%;
        flex-direction: column;
    }
    .btn {
        width: 100%;
        justify-content: center;
    }
    .report-table {
        font-size: 11px;
    }
    .report-table th,
    .report-table td {
        padding: 10px 5px;
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
        <a href="payments.php">💰 Payments</a>
        <a href="reports.php" class="active">📊 Reports</a>
        <a href="profile.php">👤 Profile</a>
        <a href="../auth/logout.php" class="logout-btn">🚪 Logout</a>
    </div>

    <!-- Main Content -->
    <div class="report-container">
        <!-- Report Header -->
        <div class="report-header">
            <h1>💧 Water Billing System - Report</h1>
            <div class="period">Period: <?= date('F Y', mktime(0,0,0,$selectedMonth,1,$selectedYear)); ?></div>
            <div class="generated">Generated on: <?= date('F d, Y'); ?></div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <h3>📋 Filter Options</h3>
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label>Customer:</label>
                    <select name="customer_id">
                        <option value="">-- All Customers --</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['customer_id']; ?>" <?= $selectedCustomer == $c['customer_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) . " (Acc: " . htmlspecialchars($c['account_no']) . ")"; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Year:</label>
                    <select name="year">
                        <?php foreach ($years as $y): ?>
                            <option value="<?= $y; ?>" <?= $selectedYear == $y ? 'selected' : '' ?>><?= $y; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Month:</label>
                    <select name="month">
                        <?php foreach ($months as $m): ?>
                            <option value="<?= $m; ?>" <?= $selectedMonth == $m ? 'selected' : '' ?>>
                                <?= date('F', mktime(0,0,0,$m,1)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-filter">🔍 Apply Filter</button>
                    <?php
                    $downloadUrl = "?download=csv&customer_id=$selectedCustomer&year=$selectedYear&month=$selectedMonth";
                    ?>
                    <a href="<?= $downloadUrl; ?>" class="btn btn-download">📥 Download CSV</a>
                </div>
            </form>
        </div>

        <!-- Summary Section -->
        <div class="summary-section">
            <div class="summary-cards">
                <div class="summary-card">
                    <h3>Total Revenue Collected</h3>
                    <div class="value"><?= formatCurrency($totalRevenue); ?></div>
                </div>
                <div class="summary-card">
                    <h3>Unpaid Bills</h3>
                    <div class="value"><?= $unpaidCount; ?></div>
                    <div class="sub-value">Amount: <?= formatCurrency($unpaidAmount); ?></div>
                </div>
            </div>
        </div>

        <!-- Details Section -->
        <div class="details-section">
            <h3>👥 Customer Details & Consumption Report</h3>
            <?php if ($detailsResult && $detailsResult->num_rows > 0): ?>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Account No</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>House</th>
                            <th>House No</th>
                            <th>Tariff</th>
                            <th>Units Consumed</th>
                            <th>Total Billed</th>
                            <th>Total Paid</th>
                            <th>Total Unpaid</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $detailsResult->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['customer_id']); ?></td>
                                <td><?= htmlspecialchars($row['account_no']); ?></td>
                                <td><?= htmlspecialchars($row['name']); ?></td>
                                <td><?= htmlspecialchars($row['email']); ?></td>
                                <td><?= htmlspecialchars($row['phone']); ?></td>
                                <td><?= htmlspecialchars($row['house']); ?></td>
                                <td><?= htmlspecialchars($row['house_no']); ?></td>
                                <td>
                                    <span class="badge badge-<?= strtolower($row['tariff']); ?>">
                                        <?= htmlspecialchars($row['tariff']); ?>
                                    </span>
                                </td>
                                <td><?= number_format($row['total_units'] ?? 0); ?></td>
                                <td><?= formatCurrency($row['total_billed'] ?? 0); ?></td>
                                <td style="color: #10b981;"><?= formatCurrency($row['total_paid'] ?? 0); ?></td>
                                <td style="color: #ef4444;"><?= formatCurrency($row['total_unpaid'] ?? 0); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">⚠ No customer data available for the selected period.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
$stmt->close();
$conn->close();
require_once("../includes/footer.php"); 
?>