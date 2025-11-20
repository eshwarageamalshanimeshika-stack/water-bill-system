<?php
/**
 * Water Billing System - Complete Functions Library
 * Based on Sri Lanka NWSDB Water Tariff 2024 (Effective August 21, 2024)
 * Gazette Notification No. 2398/19
 * 
 * FIXED: Proper slab-based/tiered billing calculation
 * Each slab is calculated separately and added together
 * 
 * Tariff Tables Used:
 * - Table 02: Domestic (Categories: 10, 11, 13, 16, 18, 19)
 * - Table 05: Government Institutions & Industries (Categories: 60, 61, 62, 64, 73, 75)
 * - Table 08: Commercial Institutions (Categories: 65, 70, 71, 74, 77, 79, 80, 82, 98)
 */

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header("Location: ../index.php");
    exit();
}

// =============================================================================
// FORMATTING FUNCTIONS
// =============================================================================

function formatCurrency($amount) {
    return "Rs. " . number_format($amount, 2);
}

function formatDate($date, $format = 'M d, Y') {
    if (empty($date) || $date == '0000-00-00') {
        return 'N/A';
    }
    return date($format, strtotime($date));
}

function formatPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) == 10) {
        return substr($phone, 0, 4) . '-' . substr($phone, 4, 3) . '-' . substr($phone, 7);
    }
    return $phone;
}

// =============================================================================
// INPUT SANITIZATION & VALIDATION
// =============================================================================

function cleanInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function sanitize($data) {
    return cleanInput($data);
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return strlen($phone) >= 9 && strlen($phone) <= 15;
}

function validateNumber($value, $min = null, $max = null) {
    if (!is_numeric($value)) {
        return false;
    }
    
    $value = floatval($value);
    
    if ($min !== null && $value < $min) {
        return false;
    }
    
    if ($max !== null && $value > $max) {
        return false;
    }
    
    return true;
}

// =============================================================================
// BILL CALCULATION FUNCTIONS - NWSDB TARIFF 2024
// Based on Gazette No. 2398/19 dated August 21, 2024
// FIXED: Proper slab-based calculation
// =============================================================================

/**
 * Calculate bill for Domestic consumers (Tariff Table 02)
 * Categories: 10, 11, 13, 16, 18, 19
 * Effective from August 21, 2024
 * 
 * @param float $units Number of units consumed
 * @return array Array with usage_charge, service_charge, and total
 */
function calculateDomesticBill($units) {
    $units = floatval($units);
    $usage_charge = 0;
    $service_charge = 0;
    
    // Define slabs: [max_units, rate_per_unit, service_charge]
    $slabs = [
        [5, 50.00, 300.00],
        [10, 70.00, 300.00],
        [15, 90.00, 300.00],
        [20, 100.00, 400.00],
        [25, 120.00, 500.00],
        [30, 150.00, 600.00],
        [40, 170.00, 1500.00],
        [50, 195.00, 3000.00],
        [75, 225.00, 3500.00],
        [100, 250.00, 4000.00],
        [PHP_INT_MAX, 280.00, 4500.00]  // Over 100
    ];
    
    $remaining_units = $units;
    $previous_limit = 0;
    
    foreach ($slabs as $slab) {
        list($limit, $rate, $svc_charge) = $slab;
        
        if ($remaining_units <= 0) break;
        
        $slab_size = $limit - $previous_limit;
        $units_in_slab = min($remaining_units, $slab_size);
        
        $usage_charge += $units_in_slab * $rate;
        $service_charge = $svc_charge;  // Service charge for this tier
        
        $remaining_units -= $units_in_slab;
        $previous_limit = $limit;
    }
    
    return [
        'usage_charge' => $usage_charge,
        'service_charge' => $service_charge,
        'total' => $usage_charge + $service_charge
    ];
}

/**
 * Calculate bill for Government Institutions & Industries (Tariff Table 05)
 * Categories: 60, 61, 62, 64, 73, 75
 * Effective from August 21, 2024
 * 
 * @param float $units Number of units consumed
 * @return array Array with usage_charge, service_charge, and total
 */
function calculateIndustrialBill($units) {
    $units = floatval($units);
    $rate = 110.00;  // Flat rate for all units
    
    // Service charge based on consumption tier
    if ($units <= 25) {
        $service_charge = 500.00;
    } elseif ($units <= 50) {
        $service_charge = 750.00;
    } elseif ($units <= 75) {
        $service_charge = 1500.00;
    } elseif ($units <= 100) {
        $service_charge = 1750.00;
    } elseif ($units <= 200) {
        $service_charge = 2000.00;
    } elseif ($units <= 500) {
        $service_charge = 3000.00;
    } elseif ($units <= 1000) {
        $service_charge = 5000.00;
    } elseif ($units <= 2000) {
        $service_charge = 10000.00;
    } elseif ($units <= 4000) {
        $service_charge = 15000.00;
    } elseif ($units <= 10000) {
        $service_charge = 30000.00;
    } elseif ($units <= 20000) {
        $service_charge = 60000.00;
    } else {
        $service_charge = 130000.00;
    }
    
    $usage_charge = $units * $rate;
    
    return [
        'usage_charge' => $usage_charge,
        'service_charge' => $service_charge,
        'total' => $usage_charge + $service_charge
    ];
}

/**
 * Calculate bill for Commercial Institutions (Tariff Table 08)
 * Categories: 65, 70, 71, 74, 77, 79, 80, 82, 98
 * Effective from August 21, 2024
 * 
 * @param float $units Number of units consumed
 * @return array Array with usage_charge, service_charge, and total
 */
function calculateCommercialBill($units) {
    $units = floatval($units);
    $rate = 150.00;  // Flat rate for all units
    
    // Service charge based on consumption tier
    if ($units <= 25) {
        $service_charge = 500.00;
    } elseif ($units <= 50) {
        $service_charge = 750.00;
    } elseif ($units <= 75) {
        $service_charge = 1500.00;
    } elseif ($units <= 100) {
        $service_charge = 1750.00;
    } elseif ($units <= 200) {
        $service_charge = 2000.00;
    } elseif ($units <= 500) {
        $service_charge = 3000.00;
    } elseif ($units <= 1000) {
        $service_charge = 5000.00;
    } elseif ($units <= 2000) {
        $service_charge = 10000.00;
    } elseif ($units <= 4000) {
        $service_charge = 15000.00;
    } elseif ($units <= 10000) {
        $service_charge = 30000.00;
    } elseif ($units <= 20000) {
        $service_charge = 60000.00;
    } else {
        $service_charge = 130000.00;
    }
    
    $usage_charge = $units * $rate;
    
    return [
        'usage_charge' => $usage_charge,
        'service_charge' => $service_charge,
        'total' => $usage_charge + $service_charge
    ];
}

/**
 * Main bill calculation function
 * Routes to appropriate calculation based on tariff type
 * 
 * @param string $tariff Tariff type/category
 * @param float $units Number of units consumed
 * @return array|null Calculation result array or null if invalid tariff
 */
function calculateBill($tariff, $units) {
    // Normalize tariff name
    $tariff = strtolower(trim($tariff));
    
    // Map tariff names to calculation functions (2024 Tariff Tables)
    switch ($tariff) {
        case 'domestic':
        case 'residential':
        case 'domestic_other':
            return calculateDomesticBill($units);
            
        case 'industrial':
        case 'govt_industries':
        case 'government':
        case 'industry':
            return calculateIndustrialBill($units);
            
        case 'commercial':
        case 'business':
        case 'boi':
        case 'soe':
            return calculateCommercialBill($units);
            
        default:
            // Default to domestic if unknown
            return calculateDomesticBill($units);
    }
}

/**
 * Get tariff rate information
 * 
 * @param string $tariff Tariff type
 * @param float $units Number of units
 * @return array Rate information
 */
function getTariffInfo($tariff, $units) {
    $result = calculateBill($tariff, $units);
    
    if ($result) {
        $result['tariff_type'] = ucfirst($tariff);
        $result['units'] = $units;
        $result['rate_per_unit'] = $units > 0 ? round($result['usage_charge'] / $units, 2) : 0;
    }
    
    return $result;
}

// =============================================================================
// SESSION & AUTHENTICATION FUNCTIONS
// =============================================================================

function isLoggedIn() {
    return isset($_SESSION['admin_id']) || isset($_SESSION['customer_id']);
}

function isAdmin() {
    return isset($_SESSION['admin_id']);
}

function isCustomer() {
    return isset($_SESSION['customer_id']);
}

function getUserId() {
    if (isset($_SESSION['admin_id'])) {
        return $_SESSION['admin_id'];
    } elseif (isset($_SESSION['customer_id'])) {
        return $_SESSION['customer_id'];
    }
    return null;
}

function getUserType() {
    if (isset($_SESSION['admin_id'])) {
        return 'admin';
    } elseif (isset($_SESSION['customer_id'])) {
        return 'customer';
    }
    return null;
}

function getUserName() {
    if (isset($_SESSION['admin_name'])) {
        return $_SESSION['admin_name'];
    } elseif (isset($_SESSION['customer_name'])) {
        return $_SESSION['customer_name'];
    }
    return null;
}

// =============================================================================
// NAVIGATION & REDIRECT FUNCTIONS
// =============================================================================

function redirect($url, $msg = "") {
    if (!empty($msg)) {
        header("Location: $url?msg=" . urlencode($msg));
    } else {
        header("Location: $url");
    }
    exit();
}

function redirectWithSuccess($url, $message) {
    header("Location: $url?success=" . urlencode($message));
    exit();
}

function redirectWithError($url, $message) {
    header("Location: $url?error=" . urlencode($message));
    exit();
}

// =============================================================================
// DATABASE HELPER FUNCTIONS
// =============================================================================

function getCustomerById($conn, $customer_id) {
    $stmt = $conn->prepare("SELECT * FROM customer WHERE customer_id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();
    $stmt->close();
    return $customer;
}

function getAdminById($conn, $admin_id) {
    $stmt = $conn->prepare("SELECT * FROM admin WHERE admin_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    $stmt->close();
    return $admin;
}

function getLastMeterReading($conn, $customer_id) {
    $stmt = $conn->prepare("
        SELECT current_reading 
        FROM meter_reading 
        WHERE customer_id = ? 
        ORDER BY date DESC, reading_id DESC 
        LIMIT 1
    ");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? floatval($row['current_reading']) : 0;
}

function billExistsForReading($conn, $reading_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM bills WHERE meter_reading_id = ?");
    $stmt->bind_param("i", $reading_id);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count > 0;
}

function getTotalCustomers($conn) {
    $result = $conn->query("SELECT COUNT(*) as total FROM customer");
    $row = $result->fetch_assoc();
    return $row['total'] ?? 0;
}

function getTotalRevenue($conn) {
    $result = $conn->query("SELECT SUM(amount) as total FROM payments");
    $row = $result->fetch_assoc();
    return $row['total'] ?? 0;
}

function getPendingBillsCount($conn) {
    $result = $conn->query("SELECT COUNT(*) as total FROM bills WHERE status = 'unpaid'");
    $row = $result->fetch_assoc();
    return $row['total'] ?? 0;
}

function getPendingBillsAmount($conn) {
    $result = $conn->query("
        SELECT SUM(b.amount - COALESCE(paid.total_paid, 0)) as total 
        FROM bills b
        LEFT JOIN (
            SELECT bill_id, SUM(amount) as total_paid 
            FROM payments 
            GROUP BY bill_id
        ) paid ON b.id = paid.bill_id
        WHERE b.status = 'unpaid'
    ");
    $row = $result->fetch_assoc();
    return $row['total'] ?? 0;
}

function getCustomerOutstanding($conn, $customer_id) {
    $stmt = $conn->prepare("
        SELECT SUM(b.amount - COALESCE(paid.total_paid, 0)) as outstanding
        FROM bills b
        LEFT JOIN (
            SELECT bill_id, SUM(amount) as total_paid 
            FROM payments 
            GROUP BY bill_id
        ) paid ON b.id = paid.bill_id
        WHERE b.customer_id = ? AND b.status = 'unpaid'
    ");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['outstanding'] ?? 0;
}

// =============================================================================
// ACCOUNT GENERATION FUNCTIONS
// =============================================================================

function generateAccountNumber($conn) {
    do {
        $account_no = date('Y') . rand(1000, 9999);
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM customer WHERE account_no = ?");
        $stmt->bind_param("s", $account_no);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
    } while ($count > 0);
    
    return $account_no;
}

function generatePassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $password;
}

// =============================================================================
// ALERT & MESSAGE FUNCTIONS
// =============================================================================

function showAlert($message, $type = 'info') {
    $alertClass = '';
    $icon = '';
    
    switch ($type) {
        case 'success':
            $alertClass = 'alert-success';
            $icon = '✅';
            break;
        case 'error':
            $alertClass = 'alert-error';
            $icon = '❌';
            break;
        case 'warning':
            $alertClass = 'alert-warning';
            $icon = '⚠';
            break;
        default:
            $alertClass = 'alert-info';
            $icon = 'ℹ';
    }
    
    return "<div class='alert {$alertClass}'>{$icon} " . htmlspecialchars($message) . "</div>";
}

// =============================================================================
// UTILITY FUNCTIONS
// =============================================================================

function getCurrentTimestamp() {
    return date('Y-m-d H:i:s');
}

function getCurrentDate() {
    return date('Y-m-d');
}

function daysBetween($date1, $date2) {
    $datetime1 = new DateTime($date1);
    $datetime2 = new DateTime($date2);
    $interval = $datetime1->diff($datetime2);
    return $interval->days;
}

function isOverdue($due_date) {
    return strtotime($due_date) < strtotime(date('Y-m-d'));
}

function calculatePromptPaymentDiscount($amount, $bill_date, $payment_date = null) {
    if ($payment_date === null) {
        $payment_date = date('Y-m-d');
    }
    
    $days = daysBetween($bill_date, $payment_date);
    
    // 1.5% discount if paid within 14 days (as per gazette)
    if ($days <= 14) {
        return $amount * 0.015;
    }
    
    return 0;
}

function calculateLateSurcharge($amount, $bill_date, $current_date = null) {
    if ($current_date === null) {
        $current_date = date('Y-m-d');
    }
    
    $days = daysBetween($bill_date, $current_date);
    
    // 2.5% surcharge per month if not paid within 30 days (as per gazette)
    if ($days > 30) {
        $months = ceil($days / 30);
        return $amount * 0.025 * $months;
    }
    
    return 0;
}

function getTariffDisplayName($tariff) {
    $names = [
        'domestic' => 'Domestic (Table 02)',
        'industrial' => 'Government/Industries (Table 05)',
        'commercial' => 'Commercial (Table 08)',
    ];
    
    return $names[strtolower($tariff)] ?? ucfirst($tariff);
}

function getCustomersByTariff($conn, $tariff) {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM customer WHERE tariff = ?");
    $stmt->bind_param("s", $tariff);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['total'] ?? 0;
}

function getDashboardStats($conn) {
    $stats = [];
    
    $stats['total_customers'] = getTotalCustomers($conn);
    $stats['total_revenue'] = getTotalRevenue($conn);
    $stats['pending_bills'] = getPendingBillsCount($conn);
    $stats['pending_amount'] = getPendingBillsAmount($conn);
    
    // Revenue this month
    $result = $conn->query("
        SELECT COALESCE(SUM(amount), 0) as monthly_revenue 
        FROM payments 
        WHERE MONTH(payment_date) = MONTH(CURRENT_DATE()) 
        AND YEAR(payment_date) = YEAR(CURRENT_DATE())
    ");
    $row = $result->fetch_assoc();
    $stats['monthly_revenue'] = $row['monthly_revenue'] ?? 0;
    
    // Bills this month
    $result = $conn->query("
        SELECT COUNT(*) as monthly_bills 
        FROM bills 
        WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ");
    $row = $result->fetch_assoc();
    $stats['monthly_bills'] = $row['monthly_bills'] ?? 0;
    
    return $stats;
}

// =============================================================================
// END OF FUNCTIONS FILE
// =============================================================================
?>