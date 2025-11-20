<?php
require_once("../includes/auth.php");
require_once("../includes/header.php");
require_once("../includes/functions.php");
require_once("../config.php");

$error = "";
$success = "";

// Fetch customers for dropdown - Only customers with valid tariffs
$customerSql = "SELECT customer_id, name, account_no, house, tariff 
                FROM customer 
                WHERE tariff IN ('domestic', 'industrial', 'commercial')
                ORDER BY account_no ASC";
$customerResult = $conn->query($customerSql);

// Handle bill generation
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $customer_id = intval($_POST['customer_id']);
    $reading_id = isset($_POST['reading_id']) ? intval($_POST['reading_id']) : 0;

    if ($reading_id > 0) {
        // Get meter reading details
        $readingSql = "SELECT reading_id, units_consumed, fixed_units,
                       CASE 
                           WHEN fixed_units IS NOT NULL THEN fixed_units
                           WHEN units_consumed IS NOT NULL AND units_consumed > 0 THEN units_consumed
                           ELSE 0
                       END AS actual_units
                       FROM meter_reading 
                       WHERE reading_id=? AND customer_id=?";
        $stmt = $conn->prepare($readingSql);
        $stmt->bind_param("ii", $reading_id, $customer_id);
        $stmt->execute();
        $stmt->bind_result($reading_id, $unitsConsumed, $fixedUnits, $actualUnits);
        $stmt->fetch();
        $stmt->close();

        if (!$reading_id) {
            $error = "⚠ Invalid meter reading selected.";
        } else {
            // Get customer tariff
            $typeSql = "SELECT tariff FROM customer WHERE customer_id=?";
            $stmt = $conn->prepare($typeSql);
            $stmt->bind_param("i", $customer_id);
            $stmt->execute();
            $stmt->bind_result($customerType);
            $stmt->fetch();
            $stmt->close();

            // Validate tariff type
            if (!in_array($customerType, ['domestic', 'industrial', 'commercial'])) {
                $error = "⚠ Invalid customer tariff: $customerType. Only Domestic, Industrial, and Commercial tariffs are supported.";
            } else {
                // Calculate total bill using the 2024 tariff
                $billCalculation = calculateBill($customerType, $actualUnits);
                
                if ($billCalculation === null) {
                    $error = "⚠ Error calculating bill for tariff: $customerType";
                } else {
                    $totalBill = $billCalculation['total'];
                    $usageCharge = $billCalculation['usage_charge'];
                    $serviceCharge = $billCalculation['service_charge'];

                    // Check if bill already exists for this reading
                    $checkSql = "SELECT COUNT(*) as count FROM bills WHERE meter_reading_id = ?";
                    $stmt = $conn->prepare($checkSql);
                    $stmt->bind_param("i", $reading_id);
                    $stmt->execute();
                    $stmt->bind_result($billExists);
                    $stmt->fetch();
                    $stmt->close();

                    if ($billExists > 0) {
                        $error = "⚠ A bill has already been generated for this meter reading.";
                    } else {
                        // Insert bill into table
                        $insertSql = "INSERT INTO bills 
                            (customer_id, meter_reading_id, bill_date, due_date, amount, status, units_consumed) 
                            VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 14 DAY), ?, 'unpaid', ?)";
                        $stmt = $conn->prepare($insertSql);
                        $stmt->bind_param("iidd", $customer_id, $reading_id, $totalBill, $actualUnits);

                        if ($stmt->execute()) {
                            $success = "✅ Bill generated successfully! Total: Rs. " . number_format($totalBill, 2) . 
                                      " (Usage: Rs. " . number_format($usageCharge, 2) . 
                                      " + Service: Rs. " . number_format($serviceCharge, 2) . ")";
                        } else {
                            $error = "❌ Failed to generate bill. Error: " . $stmt->error;
                        }
                        $stmt->close();
                    }
                }
            }
        }
    } else {
        $error = "⚠ Please select a meter reading.";
    }
}

// Get selected customer's meter readings (for AJAX or page load)
$selectedCustomerId = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
$meterReadings = [];
if ($selectedCustomerId > 0) {
    $meterSql = "SELECT m.reading_id, m.date, m.previous_reading, m.current_reading, 
                 m.units_consumed, m.fixed_units,
                 CASE 
                     WHEN m.fixed_units IS NOT NULL THEN m.fixed_units
                     WHEN m.units_consumed IS NOT NULL AND m.units_consumed > 0 THEN m.units_consumed
                     ELSE (m.current_reading - m.previous_reading)
                 END AS actual_units,
                 CASE WHEN b.id IS NOT NULL THEN 'Yes' ELSE 'No' END as has_bill
                 FROM meter_reading m
                 LEFT JOIN bills b ON m.reading_id = b.meter_reading_id
                 WHERE m.customer_id = ?
                 ORDER BY m.date DESC";
    $stmt = $conn->prepare($meterSql);
    $stmt->bind_param("i", $selectedCustomerId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $meterReadings[] = $row;
    }
    $stmt->close();
}

// Pagination for bills table
$limit = 15;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Count total bills
$countResult = $conn->query("
    SELECT COUNT(*) AS total 
    FROM bills b 
    JOIN customer c ON b.customer_id = c.customer_id 
    WHERE c.tariff IN ('domestic', 'industrial', 'commercial')
");
$totalRows = $countResult->fetch_assoc()['total'] ?? 0;
$totalPages = ceil($totalRows / $limit);
?>

<style>
body { background: #121826; margin: 0; padding: 0; }
.dashboard-wrapper { display: flex; min-height: 100vh; }

/* Sidebar */
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
    max-width: 1400px;
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

.form-group { 
    margin-bottom: 20px; 
}

form label { 
    display: block; 
    margin-bottom: 8px;
    color: #9ca3af;
    font-weight: 600;
    font-size: 14px;
}

form select, form input { 
    width: 100%; 
    padding: 12px; 
    border-radius: 8px; 
    border: 1px solid #374151;
    background: #0f172a;
    color: #f1f5f9;
    font-size: 14px;
    transition: border 0.3s;
}

form select:focus, form input:focus {
    outline: none;
    border-color: #4dabf7;
}

form input[readonly] {
    background: #1e293b;
    cursor: not-allowed;
    color: #9ca3af;
}

.btn { 
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

.btn:hover { 
    background: #3b9ae1;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(77, 171, 247, 0.4);
}

/* Reading Details Card */
#readingDetails {
    background: #0f172a; 
    padding: 20px; 
    border-radius: 12px; 
    margin: 20px 0;
    border: 1px solid #374151;
}

#readingDetails h4 {
    color: #4dabf7; 
    margin-bottom: 15px;
    font-size: 18px;
}

#readingDetails > div:first-of-type {
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
    gap: 15px;
}

#readingDetails label {
    font-size: 12px; 
    color: #94a3b8;
    margin-bottom: 5px;
}

#readingDetails > div > div > div {
    font-size: 16px; 
    font-weight: bold; 
    color: #f1f5f9;
}

#detail_units {
    font-size: 18px !important; 
    color: #22c55e !important;
}

#fixedUnitsInfo {
    margin-top: 15px; 
    padding: 10px; 
    background: rgba(245, 158, 11, 0.1); 
    border-left: 3px solid #f59e0b; 
    border-radius: 4px;
}

#fixedUnitsInfo small {
    color: #f59e0b; 
    font-weight: bold;
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
    padding: 15px 12px;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 12px;
    letter-spacing: 0.5px;
    color: #9ca3af;
    border-bottom: 2px solid #4dabf7;
}

td { 
    padding: 15px 12px; 
    border-bottom: 1px solid #374151;
    font-size: 14px;
}

tbody tr { 
    transition: background 0.3s; 
}

tbody tr:hover { 
    background: rgba(77, 171, 247, 0.1);
}

/* Tariff Badges */
.tariff-badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.tariff-domestic { 
    background: #3b82f6; 
    color: #fff; 
}

.tariff-industrial { 
    background: #8b5cf6; 
    color: #fff; 
}

.tariff-commercial { 
    background: #f59e0b; 
    color: #000; 
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

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #9ca3af;
    font-size: 16px;
}

.empty-state::before {
    content: "⚠";
    display: block;
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
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
    #readingDetails > div:first-of-type {
        grid-template-columns: 1fr;
    }
    table {
        font-size: 12px;
    }
    th, td {
        padding: 10px 8px;
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
        <a href="bills.php" class="active">🧾 Bills</a>
        <a href="payments.php">💰 Payments</a>
        <a href="reports.php">📊 Reports</a>
        <a href="profile.php">👤 Profile</a>
        <a href="../auth/logout.php" class="logout-btn">🚪 Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h2>🧾 Generate Bills</h2>

        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Bill Generation Form -->
        <form method="POST" action="" id="billForm">
            <div class="form-group">
                <label>Select Customer:</label>
                <select name="customer_id" id="customer_id" required>
                    <option value="">-- Select Customer --</option>
                    <?php while($row = $customerResult->fetch_assoc()): 
                        $tariffDisplay = ucfirst($row['tariff']);
                    ?>
                        <option value="<?php echo $row['customer_id']; ?>" <?php echo ($selectedCustomerId == $row['customer_id']) ? 'selected' : ''; ?>>
                            <?php echo $row['account_no'] . " - " . $row['name'] . " | " . $row['house'] . " (" . $tariffDisplay . ")"; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div id="meterReadingsSection" style="display: <?php echo $selectedCustomerId > 0 ? 'block' : 'none'; ?>;">
                <div class="form-group">
                    <label>Select Meter Reading:</label>
                    <select name="reading_id" id="reading_id" required>
                        <option value="">-- Select Reading --</option>
                    </select>
                </div>

                <div id="readingDetails" style="display:none;">
                    <h4>📊 Reading Details</h4>
                    <div>
                        <div>
                            <label>Previous Reading:</label>
                            <div id="detail_previous">-</div>
                        </div>
                        <div>
                            <label>Current Reading:</label>
                            <div id="detail_current">-</div>
                        </div>
                        <div>
                            <label>Units Consumed:</label>
                            <div id="detail_units">-</div>
                        </div>
                        <div>
                            <label>Reading Date:</label>
                            <div id="detail_date">-</div>
                        </div>
                    </div>
                    <div id="fixedUnitsInfo" style="display: none;">
                        <small>⚠ This reading uses fixed units override</small>
                    </div>
                </div>

                <button type="submit" class="btn">💾 Generate Bill</button>
            </div>
        </form>

        <hr>

        <!-- Recent Bills Table -->
        <h3>📜 Recent Bills</h3>
        <table>
            <thead>
                <tr>
                    <th>Bill ID</th>
                    <th>Account No</th>
                    <th>Customer</th>
                    <th>House</th>
                    <th>Tariff</th>
                    <th>Units</th>
                    <th>Amount</th>
                    <th>Bill Date</th>
                    <th>Due Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $billsSql = "SELECT b.id, b.units_consumed, b.amount, b.bill_date, b.due_date, b.status,
                             c.name, c.account_no, c.house, c.tariff
                             FROM bills b 
                             JOIN customer c ON b.customer_id = c.customer_id 
                             WHERE c.tariff IN ('domestic', 'industrial', 'commercial')
                             ORDER BY b.bill_date DESC, b.id DESC 
                             LIMIT $limit OFFSET $offset";
                $billsResult = $conn->query($billsSql);
                if ($billsResult && $billsResult->num_rows > 0) {
                    while ($bill = $billsResult->fetch_assoc()) {
                        $statusColor = $bill['status'] == 'paid' ? '#22c55e' : '#ef4444';
                        $tariffClass = 'tariff-' . strtolower($bill['tariff']);
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($bill['id']) . "</td>";
                        echo "<td>" . htmlspecialchars($bill['account_no']) . "</td>";
                        echo "<td>" . htmlspecialchars($bill['name']) . "</td>";
                        echo "<td>" . htmlspecialchars($bill['house']) . "</td>";
                        echo "<td><span class='tariff-badge " . $tariffClass . "'>" . strtoupper($bill['tariff']) . "</span></td>";
                        echo "<td><strong>" . number_format($bill['units_consumed'], 2) . "</strong></td>";
                        echo "<td><strong>" . formatCurrency($bill['amount']) . "</strong></td>";
                        echo "<td>" . htmlspecialchars($bill['bill_date']) . "</td>";
                        echo "<td>" . htmlspecialchars($bill['due_date']) . "</td>";
                        echo "<td><span style='color: $statusColor; font-weight: bold;'>" . strtoupper($bill['status']) . "</span></td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='10' class='empty-state'>No bills found.</td></tr>";
                }
                ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i; ?><?= $selectedCustomerId ? '&customer_id='.$selectedCustomerId : ''; ?>" 
                       class="<?= ($i == $page) ? 'active' : ''; ?>">
                        <?= $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Store meter readings data
let meterReadingsData = <?php echo json_encode($meterReadings); ?>;

// Customer selection change
document.getElementById('customer_id').addEventListener('change', function() {
    const customerId = this.value;
    if (customerId) {
        // Redirect to same page with customer_id to fetch readings
        window.location.href = 'bills.php?customer_id=' + customerId;
    } else {
        document.getElementById('meterReadingsSection').style.display = 'none';
    }
});

// Populate meter readings dropdown on page load
if (meterReadingsData.length > 0) {
    const readingSelect = document.getElementById('reading_id');
    readingSelect.innerHTML = '<option value="">-- Select Reading --</option>';
    
    meterReadingsData.forEach(reading => {
        const option = document.createElement('option');
        option.value = reading.reading_id;
        const unitsText = parseFloat(reading.actual_units).toFixed(2);
        const fixedIndicator = reading.fixed_units ? ' [FIXED]' : '';
        option.textContent = `Reading #${reading.reading_id} - ${reading.date} (${unitsText} units${fixedIndicator}) ${reading.has_bill === 'Yes' ? '✅ Billed' : ''}`;
        option.dataset.previous = reading.previous_reading;
        option.dataset.current = reading.current_reading;
        option.dataset.units = reading.actual_units;
        option.dataset.date = reading.date;
        option.dataset.hasBill = reading.has_bill;
        option.dataset.isFixed = reading.fixed_units ? '1' : '0';
        
        // Disable if already billed
        if (reading.has_bill === 'Yes') {
            option.disabled = true;
            option.style.color = '#64748b';
        }
        
        readingSelect.appendChild(option);
    });
}

// Reading selection change
document.getElementById('reading_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    
    if (selectedOption.value) {
        document.getElementById('readingDetails').style.display = 'block';
        document.getElementById('detail_previous').textContent = parseFloat(selectedOption.dataset.previous).toFixed(2);
        document.getElementById('detail_current').textContent = parseFloat(selectedOption.dataset.current).toFixed(2);
        document.getElementById('detail_units').textContent = parseFloat(selectedOption.dataset.units).toFixed(2);
        document.getElementById('detail_date').textContent = selectedOption.dataset.date;
        
        // Show fixed units indicator if applicable
        const fixedInfo = document.getElementById('fixedUnitsInfo');
        if (selectedOption.dataset.isFixed === '1') {
            fixedInfo.style.display = 'block';
        } else {
            fixedInfo.style.display = 'none';
        }
    } else {
        document.getElementById('readingDetails').style.display = 'none';
    }
});
</script>

<?php
$conn->close();
require_once("../includes/footer.php");
?>