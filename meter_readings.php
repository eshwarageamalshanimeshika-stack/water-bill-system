<?php
require_once("../includes/auth.php");
require_once("../includes/header.php");
require_once("../includes/functions.php");
require_once("../config.php");

$error = "";
$success = "";

// Handle new meter reading submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $customer_id = intval($_POST['customer_id']);
    $previous_reading = floatval($_POST['previous_reading']);
    $current_reading  = floatval($_POST['current_reading']);
    
    // Get fixed_units field value - properly handle empty strings
    $fixed_units_input = isset($_POST['fixed_units']) ? trim($_POST['fixed_units']) : '';
    
    // Initialize variables
    $units_consumed = 0;
    $fixed_units_to_store = null;
    
    // Determine calculation method
    if ($fixed_units_input !== '' && is_numeric($fixed_units_input)) {
        // User entered a fixed units value - use it directly
        $fixed_units_value = floatval($fixed_units_input);
        $units_consumed = $fixed_units_value;
        $fixed_units_to_store = $fixed_units_value;
    } else {
        // Calculate from meter readings: current - previous
        $units_consumed = $current_reading - $previous_reading;
        // Ensure units consumed is not negative
        if ($units_consumed < 0) {
            $units_consumed = 0;
        }
        $fixed_units_to_store = null;
    }

    // Validation  
    if ($customer_id <= 0) {  
        $error = "⚠ Please select a customer.";  
    } elseif ($current_reading < 0 || $previous_reading < 0) {
        $error = "⚠ Readings cannot be negative.";
    } elseif ($fixed_units_input === '' && $current_reading < $previous_reading) {  
        $error = "⚠ Current reading must be >= previous reading (or use Fixed Units).";  
    } elseif ($fixed_units_input !== '' && !is_numeric($fixed_units_input)) {
        $error = "⚠ Fixed units must be a valid number.";
    } elseif ($fixed_units_input !== '' && floatval($fixed_units_input) < 0) {  
        $error = "⚠ Fixed units must be ≥ 0.";  
    } else {  
        $reading_date = date('Y-m-d');

        // Insert the meter reading with calculated units_consumed
        if ($fixed_units_to_store !== null) {
            $stmt = $conn->prepare("  
                INSERT INTO meter_reading (customer_id, previous_reading, current_reading, units_consumed, fixed_units, date)  
                VALUES (?, ?, ?, ?, ?, ?)  
            ");  
            $stmt->bind_param("idddds", $customer_id, $previous_reading, $current_reading, $units_consumed, $fixed_units_to_store, $reading_date);
        } else {
            $stmt = $conn->prepare("  
                INSERT INTO meter_reading (customer_id, previous_reading, current_reading, units_consumed, date)  
                VALUES (?, ?, ?, ?, ?)  
            ");  
            $stmt->bind_param("iddds", $customer_id, $previous_reading, $current_reading, $units_consumed, $reading_date);
        }

        if ($stmt->execute()) {  
            $success = "✅ Meter reading added successfully! Units Consumed: " . number_format($units_consumed, 2);
            $stmt->close();
            // Redirect to prevent form resubmission
            header("Location: meter_readings.php?success=" . urlencode(number_format($units_consumed, 2)));
            exit;
        } else {  
            $error = "❌ Failed to add meter reading: " . $stmt->error;  
            $stmt->close();
        }  
    }
}

// Check for success message from redirect
if (isset($_GET['success'])) {
    $success = "✅ Meter reading added successfully! Units Consumed: " . htmlspecialchars($_GET['success']);
}

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$countResult = $conn->query("SELECT COUNT(*) AS total FROM meter_reading");
$totalRows = $countResult->fetch_assoc()['total'] ?? 0;
$totalPages = ceil($totalRows / $limit);

// Fetch all meter readings with calculated units
$sql = "
    SELECT m.reading_id, c.account_no, c.house, c.name AS customer_name, c.tariff,
           m.previous_reading, m.current_reading, m.units_consumed, m.fixed_units, m.date,
           -- Calculate units: if fixed_units exists use it, otherwise current - previous
           CASE 
               WHEN m.fixed_units IS NOT NULL THEN m.fixed_units
               WHEN m.units_consumed IS NOT NULL AND m.units_consumed > 0 THEN m.units_consumed
               ELSE (m.current_reading - m.previous_reading)
           END AS calculated_units
    FROM meter_reading m
    JOIN customer c ON m.customer_id = c.customer_id
    WHERE c.tariff IN ('domestic', 'industrial', 'commercial')
    ORDER BY m.date DESC, m.reading_id DESC
    LIMIT $limit OFFSET $offset
";
$result = $conn->query($sql);

// Fetch customers for autocomplete/search
$customerResult = $conn->query("
    SELECT customer_id, account_no, house, name, tariff 
    FROM customer 
    WHERE tariff IN ('domestic', 'industrial', 'commercial')
    ORDER BY house ASC
");
$customers = [];
while ($row = $customerResult->fetch_assoc()) {
    $customers[] = $row;
}
?>

<style>  
/* Dashboard Wrapper */  
.dashboard-wrapper {  
    display: flex;  
    min-height: 80vh;  
    background: #121826;  
    color: #f1f1f1;  
}  
  
/* Sidebar */  
.sidebar {  
    width: 220px;  
    background-color: #1f2a38;  
    color: #fff;  
    padding: 20px;  
    flex-shrink: 0;  
    min-height: 100vh;  
}  
.sidebar h3 {  
    margin-bottom: 15px;  
    font-size: 18px;  
    border-bottom: 1px solid #444;  
    padding-bottom: 5px;  
}  
.sidebar a {  
    display: block;  
    color: #fff;  
    text-decoration: none;  
    margin: 10px 0;  
    padding: 8px 12px;  
    border-radius: 6px;  
    transition: background 0.3s;  
}  
.sidebar a:hover { background-color: #2c3e50; }  
.sidebar a.active { background-color: #2c3e50; font-weight: bold; }  
.logout-btn { color: #ff6b6b !important; font-weight: bold; }  
  
/* Main Content */  
.main-content {  
    flex-grow: 1;  
    padding: 20px;  
    overflow-x: auto;
}  
.main-content h2 {  
    margin-bottom: 20px;  
    font-size: 26px;  
    font-weight: bold;  
    color: #4dabf7;  
}  
  
/* Form */  
form label { display:block; margin:10px 0 5px; font-weight: bold; color: #f1f5f9; }  
form input, form select { 
    width: 50%; 
    padding: 10px; 
    margin-bottom: 10px; 
    background: #0f172a; 
    border: 1px solid #444; 
    color: #f1f5f9; 
    border-radius: 6px;
    font-size: 14px;
}
form input:focus, form select:focus {
    outline: none;
    border-color: #4dabf7;
}
form input[readonly] {
    background: #1e293b;
    color: #94a3b8;
}

/* Custom autocomplete search box */
.customer-search-wrapper {
    position: relative;
    width: 50%;
}

#customer_search {
    width: 100%;
    padding: 10px;
    background: #0f172a;
    border: 1px solid #444;
    color: #f1f5f9;
    border-radius: 6px;
    font-size: 14px;
}

#customer_search:focus {
    outline: none;
    border-color: #4dabf7;
}

.customer-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    max-height: 300px;
    overflow-y: auto;
    background: #1e293b;
    border: 1px solid #4dabf7;
    border-top: none;
    border-radius: 0 0 6px 6px;
    display: none;
    z-index: 1000;
}

.customer-dropdown.show {
    display: block;
}

.customer-item {
    padding: 10px;
    cursor: pointer;
    border-bottom: 1px solid #374151;
    transition: background 0.2s;
}

.customer-item:hover {
    background: #2c3e50;
}

.customer-item:last-child {
    border-bottom: none;
}

.customer-house {
    font-weight: bold;
    color: #4dabf7;
}

.customer-name {
    color: #f1f5f9;
    margin-left: 5px;
}

.customer-account {
    color: #94a3b8;
    font-size: 12px;
    margin-left: 5px;
}

.customer-tariff {
    display: inline-block;
    margin-left: 8px;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 10px;
    font-weight: bold;
}

form button { 
    margin-top: 15px; 
    padding: 12px 24px; 
    background: #4dabf7; 
    color: #fff; 
    border: none; 
    border-radius: 6px; 
    cursor: pointer; 
    font-weight: bold;
    font-size: 15px;
    transition: background 0.3s;
}
form button:hover { background: #1e90ff; }
  
/* Table */  
table { 
    width: 100%; 
    border-collapse: collapse; 
    margin-top: 20px; 
    background: #1e293b; 
    border-radius: 8px; 
    overflow: hidden;
    min-width: 1100px;
}  
table th, table td { 
    border: 1px solid #374151; 
    padding: 12px 10px; 
    text-align: center; 
}  
table th { 
    background-color: #0f172a; 
    color: #fff; 
    font-weight: bold;
    text-transform: uppercase;
    font-size: 12px;
    letter-spacing: 0.5px;
    white-space: nowrap;
}
table tbody tr { transition: background 0.2s; }
table tbody tr:hover { background-color: #2c3e50; }
table tbody tr:nth-child(even) { background-color: #1e293b; }
table tbody tr:nth-child(odd) { background-color: #182535; }
  
/* Error / Success Messages */  
.error { 
    color: #ff6b6b; 
    font-weight: bold; 
    margin-bottom: 15px; 
    background: #450a0a; 
    padding: 12px 15px; 
    border-radius: 6px;
    border-left: 4px solid #ef4444;
}  
.success { 
    color: #22c55e; 
    font-weight: bold; 
    margin-bottom: 15px; 
    background: #052e16; 
    padding: 12px 15px; 
    border-radius: 6px;
    border-left: 4px solid #22c55e;
}  

/* Highlight columns */
.units-highlight {
    font-weight: bold;
    color: #22c55e;
    font-size: 15px;
}

.fixed-units-col {
    color: #f59e0b;
    font-weight: bold;
}

/* Fixed units badge */
.fixed-badge {
    display: inline-block;
    background: #f59e0b;
    color: #000;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: bold;
}

.na-text {
    color: #6b7280;
    font-style: italic;
    font-size: 13px;
}

/* Tariff badges */
.tariff-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.tariff-domestic { background: #3b82f6; color: #fff; }
.tariff-industrial { background: #8b5cf6; color: #fff; }
.tariff-commercial { background: #f59e0b; color: #000; }
  
/* Responsive */  
@media (max-width: 768px) {  
    .dashboard-wrapper { flex-direction: column; }  
    .sidebar { width: 100%; display:flex; flex-wrap: wrap; gap:10px; }  
    .sidebar a { flex:1 1 45%; text-align:center; }  
    form input, form select, .customer-search-wrapper { width: 100%; }  
    table { font-size: 11px; }
    table th, table td { padding: 8px 5px; }
}  
</style>

<div class="dashboard-wrapper">  
    <!-- Sidebar -->  
    <div class="sidebar">  
        <h3>Sidebar</h3>  
        <a href="dashboard.php">🏠 Dashboard</a>  
        <a href="customers.php">👥 Customers</a>  
        <a href="meter_readings.php" class="active">💧 Meter Readings</a>  
        <a href="bills.php">🧾 Bills</a>  
        <a href="payments.php">💰 Payments</a>  
        <a href="reports.php">📊 Reports</a>  
        <a href="profile.php">👤 Profile</a>  
        <a href="../auth/logout.php" class="logout-btn">🚪 Logout</a>  
    </div>

    <!-- Main Content -->  
    <div class="main-content">  
        <h2>💧 Manage Meter Readings</h2>  

        <?php if ($error) echo "<p class='error'>$error</p>"; ?>  
        <?php if ($success) echo "<p class='success'>$success</p>"; ?>  

        <!-- Meter Reading Form -->  
        <form method="POST" id="readingForm">  
            <input type="hidden" name="customer_id" id="customer_id" value="">
            
            <label for="customer_search">Select Customer:</label>  
            <div class="customer-search-wrapper">
                <input type="text" id="customer_search" placeholder="Type to search customer..." autocomplete="off">
                <div class="customer-dropdown" id="customer_dropdown"></div>
            </div>

            <label for="previous_reading">Previous Reading:</label>  
            <input type="number" step="0.01" name="previous_reading" id="previous_reading" readonly placeholder="Auto-filled from last reading">  

            <label for="current_reading">Current Reading: <span style="color: #ef4444;">*</span></label>  
            <input type="number" step="0.01" name="current_reading" id="current_reading" required placeholder="Enter current meter reading">  

            <label for="fixed_units">Fixed Units (optional):</label>  
            <input type="number" step="0.01" name="fixed_units" id="fixed_units" placeholder="Leave empty to auto-calculate: Current - Previous">  
            <small style="color: #94a3b8; display: block; margin-top: -5px; margin-bottom: 10px;">
                💡 Use this field to manually override the calculated consumption
            </small>

            <button type="submit">💾 Add Reading</button>  
        </form>  

        <!-- Meter Readings Table -->  
        <h3 style="color: #4dabf7; margin-top: 30px; margin-bottom: 15px;">📋 Recent Meter Readings</h3>
        <table>  
            <thead>  
                <tr>  
                    <th>ID</th>  
                    <th>Account No</th>  
                    <th>House</th>  
                    <th>Customer</th>  
                    <th>Tariff</th>  
                    <th>Previous</th>  
                    <th>Current</th>  
                    <th>Fixed Units</th>  
                    <th>Units Used</th>  
                    <th>Date</th>  
                </tr>  
            </thead>  
            <tbody>  
                <?php if ($result && $result->num_rows > 0): ?>  
                    <?php while ($row = $result->fetch_assoc()): 
                        // Calculate units to display
                        $units_to_display = floatval($row['calculated_units']);
                        $is_fixed = ($row['fixed_units'] !== null && $row['fixed_units'] > 0);
                        $fixed_units_value = $row['fixed_units'] !== null ? floatval($row['fixed_units']) : null;
                        
                        // Get tariff badge class
                        $tariffClass = 'tariff-' . strtolower($row['tariff']);
                    ?>  
                        <tr>  
                            <td><?= $row['reading_id']; ?></td>  
                            <td><?= htmlspecialchars($row['account_no']); ?></td>  
                            <td><?= htmlspecialchars($row['house']); ?></td>  
                            <td><?= htmlspecialchars($row['customer_name']); ?></td>  
                            <td>
                                <span class="tariff-badge <?= $tariffClass; ?>">
                                    <?= strtoupper(htmlspecialchars($row['tariff'])); ?>
                                </span>
                            </td>  
                            <td><?= number_format($row['previous_reading'], 2); ?></td>  
                            <td><?= number_format($row['current_reading'], 2); ?></td>  
                            <td class="fixed-units-col">
                                <?php if ($fixed_units_value !== null): ?>
                                    <span class="fixed-badge"><?= number_format($fixed_units_value, 2); ?></span>
                                <?php else: ?>
                                    <span class="na-text">—</span>
                                <?php endif; ?>
                            </td>  
                            <td class="units-highlight">
                                <?= number_format($units_to_display, 2); ?>
                            </td>  
                            <td><?= htmlspecialchars($row['date']); ?></td>  
                        </tr>  
                    <?php endwhile; ?>  
                <?php else: ?>  
                    <tr><td colspan="10" style="text-align: center; padding: 20px; color: #94a3b8;">⚠ No meter readings found.</td></tr>  
                <?php endif; ?>  
            </tbody>  
        </table>  

        <?php if ($totalPages > 1): ?>
            <div style="margin-top: 20px; text-align: center;">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i; ?>" style="padding: 8px 12px; margin: 0 2px; background: <?= ($i == $page) ? '#4dabf7' : '#1e293b'; ?>; color: #fff; text-decoration: none; border-radius: 4px; display: inline-block;">
                        <?= $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Store customers data
const customers = <?= json_encode($customers); ?>;

const searchInput = document.getElementById('customer_search');
const dropdown = document.getElementById('customer_dropdown');
const customerIdField = document.getElementById('customer_id');

// Function to get tariff display name
function getTariffDisplayName(tariff) {
    const tariffMap = {
        'domestic': 'Domestic',
        'industrial': 'Industrial',
        'commercial': 'Commercial'
    };
    return tariffMap[tariff] || tariff;
}

// Function to get tariff class
function getTariffClass(tariff) {
    return 'tariff-' + tariff.toLowerCase();
}

// Function to render dropdown
function renderDropdown(filteredCustomers) {
    if (filteredCustomers.length === 0) {
        dropdown.innerHTML = '<div class="customer-item" style="color: #94a3b8;">No customers found</div>';
        dropdown.classList.add('show');
        return;
    }
    
    dropdown.innerHTML = filteredCustomers.map(customer => `
        <div class="customer-item" data-id="${customer.customer_id}" data-house="${customer.house}" data-name="${customer.name}" data-account="${customer.account_no}">
            <span class="customer-house">${customer.house}</span>
            <span class="customer-name">${customer.name}</span>
            <span class="customer-account">(${customer.account_no})</span>
            <span class="customer-tariff ${getTariffClass(customer.tariff)}">${getTariffDisplayName(customer.tariff)}</span>
        </div>
    `).join('');
    
    dropdown.classList.add('show');
    
    // Add click listeners to items
    document.querySelectorAll('.customer-item').forEach(item => {
        item.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const house = this.getAttribute('data-house');
            const name = this.getAttribute('data-name');
            const account = this.getAttribute('data-account');
            
            if (id) {
                customerIdField.value = id;
                searchInput.value = `${house} - ${name} (${account})`;
                dropdown.classList.remove('show');
                
                // Fetch previous reading
                fetchPreviousReading(id);
            }
        });
    });
}

// Search functionality
searchInput.addEventListener('input', function() {
    const query = this.value.toLowerCase().trim();
    
    if (query === '') {
        dropdown.classList.remove('show');
        customerIdField.value = '';
        document.getElementById('previous_reading').value = '';
        return;
    }
    
    const filtered = customers.filter(customer => {
        return customer.house.toLowerCase().includes(query) ||
               customer.name.toLowerCase().includes(query) ||
               customer.account_no.toLowerCase().includes(query);
    });
    
    renderDropdown(filtered);
});

// Show all customers on focus
searchInput.addEventListener('focus', function() {
    if (this.value.trim() === '') {
        renderDropdown(customers);
    }
});

// Hide dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.classList.remove('show');
    }
});

// Fetch previous reading
function fetchPreviousReading(customerId) {
    fetch("get_previous_reading.php?customer_id=" + customerId)
        .then(response => response.json())
        .then(data => {
            document.getElementById("previous_reading").value = data.previous_reading ?? 0;
        })
        .catch(err => {
            console.error("Error fetching previous reading:", err);
            document.getElementById("previous_reading").value = 0;
        });
}

// Calculate and show expected units in real-time
document.getElementById("current_reading").addEventListener("input", function() {
    const previous = parseFloat(document.getElementById("previous_reading").value) || 0;
    const current = parseFloat(this.value) || 0;
    const fixedField = document.getElementById("fixed_units");
    
    // Only show calculation hint if fixed units is empty
    if (!fixedField.value && current >= previous) {
        const calculated = current - previous;
        fixedField.placeholder = "Auto-calculated: " + calculated.toFixed(2) + " units";
    } else {
        fixedField.placeholder = "Leave empty to auto-calculate: Current - Previous";
    }
});

// Form validation
document.getElementById('readingForm').addEventListener('submit', function(e) {
    if (!customerIdField.value) {
        e.preventDefault();
        alert('⚠ Please select a customer from the dropdown');
    }
});
</script>

<?php 
// Close database connection
$conn->close();
require_once("../includes/footer.php"); 
?>