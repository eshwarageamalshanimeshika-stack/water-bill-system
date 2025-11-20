<?php
require_once("../includes/auth.php");   // Admin-only access
require_once("../config.php");

$payment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($payment_id <= 0) {
    die("❌ Invalid payment ID.");
}

// Delete payment
$stmt = $conn->prepare("DELETE FROM payments WHERE payment_id=?");
$stmt->bind_param("i", $payment_id);
if ($stmt->execute()) {
    header("Location: payments.php?msg=deleted");
    exit;
} else {
    die("❌ Error deleting payment: " . $conn->error);
}