<?php
/**
 * get_previous_reading.php
 * AJAX endpoint to fetch the last meter reading for a customer
 * Returns JSON with previous reading value
 * 
 * Updated to support NWSDB Tariff 2024 (Effective August 21, 2024)
 * Tariff Tables: 02 (Domestic), 05 (Government/Industries), 08 (Commercial)
 */

require_once("../includes/auth.php");
require_once("../config.php");

// Set JSON header
header('Content-Type: application/json');

// Ensure customer_id is provided and valid
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

// Default response structure
$response = [
    'success' => false,
    'previous_reading' => 0,
    'account_no' => '',
    'customer_name' => '',
    'tariff' => '',
    'tariff_name' => '',
    'last_reading_date' => '',
    'last_units_consumed' => 0,
    'error' => ''
];

// Validate customer_id
if ($customer_id <= 0) {
    $response['error'] = 'Invalid customer ID';
    echo json_encode($response);
    exit;
}

try {
    // Fetch customer information including tariff type
    $custStmt = $conn->prepare("SELECT account_no, name, tariff FROM customer WHERE customer_id = ?");
    $custStmt->bind_param("i", $customer_id);
    $custStmt->execute();
    $custResult = $custStmt->get_result();
    
    if ($custRow = $custResult->fetch_assoc()) {
        $response['account_no'] = $custRow['account_no'];
        $response['customer_name'] = $custRow['name'];
        $response['tariff'] = $custRow['tariff'];
        
        // Map tariff types to display names based on 2024 gazette
        $tariffNames = [
            'domestic' => 'Domestic (Table 02)',
            'industrial' => 'Government/Industries (Table 05)',
            'commercial' => 'Commercial (Table 08)'
        ];
        $response['tariff_name'] = $tariffNames[$custRow['tariff']] ?? ucfirst($custRow['tariff']);
    } else {
        $response['error'] = 'Customer not found';
        echo json_encode($response);
        exit;
    }
    $custStmt->close();

    // Fetch the last meter reading for this customer
    // We get current_reading from the most recent entry to use as previous_reading for new entry
    $stmt = $conn->prepare("
        SELECT current_reading, date, units_consumed, fixed_units
        FROM meter_reading
        WHERE customer_id = ?
        ORDER BY date DESC, reading_id DESC
        LIMIT 1
    ");
    
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Use the current_reading from last entry as previous_reading for new entry
        $response['previous_reading'] = floatval($row['current_reading']);
        $response['last_reading_date'] = $row['date'];
        
        // Calculate units consumed based on fixed_units or calculated value
        if ($row['fixed_units'] !== null && $row['fixed_units'] > 0) {
            $response['last_units_consumed'] = floatval($row['fixed_units']);
        } else {
            $response['last_units_consumed'] = floatval($row['units_consumed']);
        }
        
        $response['success'] = true;
    } else {
        // No previous reading found - this is the first reading for this customer
        $response['previous_reading'] = 0.00;
        $response['last_units_consumed'] = 0.00;
        $response['success'] = true;
        $response['message'] = 'No previous readings found. Starting from 0.';
    }
    
    $stmt->close();

} catch (Exception $e) {
    $response['error'] = 'Database error occurred';
    $response['success'] = false;
    // Log error for debugging (remove in production)
    error_log("get_previous_reading.php Error: " . $e->getMessage());
}

// Return JSON response
echo json_encode($response);
?>