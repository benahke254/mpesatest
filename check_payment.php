<?php
// check_payment.php

require_once 'db.php';

// Get the checkout_id from the GET parameter
$checkout_id = $_GET['checkout_id'] ?? '';

if (empty($checkout_id)) {
    echo json_encode(["status" => "error", "message" => "Missing checkout_id"]);
    exit();
}

// Check if the payment has been confirmed
$stmt = $conn->prepare("SELECT * FROM clients WHERE checkout_id = ? LIMIT 1");
$stmt->bind_param("s", $checkout_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();

    // If the payment is confirmed, return client details
    echo json_encode([
        "status" => "success",
        "name" => $row['name'],
        "phone" => $row['phone'],
        "package" => $row['package'],
        "purchase_time" => $row['purchase_time'],
        "mpesa_receipt" => $row['mpesa_receipt']
    ]);
} else {
    // If no entry is found in clients, check pending payments
    $stmt_pending = $conn->prepare("SELECT * FROM pendingpayments WHERE checkout_id = ? AND status = 'pending' LIMIT 1");
    $stmt_pending->bind_param("s", $checkout_id);
    $stmt_pending->execute();
    $pending_result = $stmt_pending->get_result();

    if ($pending_result->num_rows > 0) {
        // Payment is still pending
        echo json_encode(["status" => "pending"]);
    } else {
        // If no pending payment is found
        echo json_encode(["status" => "error", "message" => "Payment not found or already processed"]);
    }
}
?>
