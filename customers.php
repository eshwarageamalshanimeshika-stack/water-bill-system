<?php
require_once("../includes/auth.php");  // Admin-only access
require_once("../includes/header.php");
require_once("../includes/functions.php");
require_once("../config.php");

// Handle deletion
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM customer WHERE customer_id=?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $success = "✅ Customer deleted successfully.";
    } else {
        $error = "❌ Failed to delete customer.";
    }
    $stmt->close();
}

// Fetch all customers
$sql = "SELECT * FROM customer ORDER BY customer_id DESC";
$result = $conn->query($sql);
?>

<style>
/* Layout */
.page-wrapper { display: flex; min-height: 80vh; background: #121826; }
.sidebar { width: 220px; background-color: #1f2a38; color: #fff; padding: 20px; flex-shrink: 0; }
.sidebar h3 { margin-bottom: 15px; font-size: 18px; border-bottom: 1px solid #444; padding-bottom: 5px; }
.sidebar a { display: block; color: #fff; text-decoration: none; margin: 10px 0; padding: 8px 12px; border-radius: 6px; transition: background 0.3s; }
.sidebar a:hover { background-color: #2c3e50; }
.logout-btn { color: #ff6b6b !important; font-weight: bold; }
.main-content { flex-grow: 1; padding: 20px; color: #f1f1f1; }
.main-content h2 { margin-bottom: 20px; font-size: 26px; font-weight: bold; color: #4dabf7; }
.btn { display: inline-block; padding: 6px 12px; margin: 4px 2px; border-radius: 6px; text-decoration: none; background: #4dabf7; color: #fff; transition: background 0.3s; }
.btn:hover { background: #1e90ff; }
.success { color: #22c55e; margin-bottom: 10px; }
.error { color: #ef4444; margin-bottom: 10px; }
table { width: 100%; border-collapse: collapse; background: #1e293b; color: #f1f5f9; border-radius: 8px; overflow: hidden; }
th, td { padding: 12px 15px; border-bottom: 1px solid #374151; }
th { background: #0f172a; text-align: left; }
tr:hover { background: #2c3e50; }
@media (max-width: 768px) {
    .page-wrapper { flex-direction: column; }
    .sidebar { width: 100%; display: flex; flex-wrap: wrap; gap: 10px; }
    .sidebar a { flex: 1 1 45%; text-align: center; }
}
</style>

<div class="page-wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
        <h3>Sidebar</h3>
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="customers.php">👥 Customers</a>
        <a href="meter_readings.php">💧 Meter Readings</a>
        <a href="bills.php">🧾 Bills</a>
        <a href="payments.php">💰 Payments</a>
        <a href="reports.php">📊 Reports</a>
        <a href="profile.php">👤 Profile</a>
        <a href="../auth/logout.php" class="logout-btn">🚪 Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h2>👥 Manage Customers</h2>

        <?php if (!empty($error)) { echo "<p class='error'>$error</p>"; } ?>
        <?php if (!empty($success)) { echo "<p class='success'>$success</p>"; } ?>

        <a href="add_customer.php" class="btn">➕ Add New Customer</a>

        <?php if ($result && $result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Account No</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Username</th>
                        <th>House</th>
                        <th>House No</th>
                        <th>Phone</th>
                        <th>Tariff</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($customer = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $customer['customer_id']; ?></td>
                            <td><?php echo htmlspecialchars($customer['account_no']); ?></td>
                            <td><?php echo htmlspecialchars($customer['name']); ?></td>
                            <td><?php echo htmlspecialchars($customer['email']); ?></td>
                            <td><?php echo htmlspecialchars($customer['username']); ?></td>
                            <td><?php echo htmlspecialchars($customer['house']); ?></td>
                            <td><?php echo htmlspecialchars($customer['house_no']); ?></td>
                            <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                            <td><?php echo ucfirst($customer['tariff']); ?></td>
                            <td>
                                <a href="edit_customer.php?id=<?php echo $customer['customer_id']; ?>" class="btn">✏ Edit</a>
                                <a href="customers.php?delete_id=<?php echo $customer['customer_id']; ?>" class="btn" onclick="return confirm('Are you sure you want to delete this customer?');">🗑 Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>⚠ No customers found.</p>
        <?php endif; ?>
    </div>
</div>

<?php
require_once("../includes/footer.php");
?>
