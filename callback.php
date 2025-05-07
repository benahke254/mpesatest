<?php
// callback.php

require_once 'db.php';

// Get callback JSON from Safaricom
$callbackJSON = file_get_contents('php://input');
$callbackData = json_decode($callbackJSON, true);

// Log the full callback for debugging
file_put_contents('callback_log.txt', $callbackJSON . PHP_EOL, FILE_APPEND);

// Extract CheckoutRequestID
$checkoutRequestID = $callbackData['Body']['stkCallback']['CheckoutRequestID'] ?? null;
$metadataItems = $callbackData['Body']['stkCallback']['CallbackMetadata']['Item'] ?? [];

$mpesaReceipt = '';
foreach ($metadataItems as $item) {
    if ($item['Name'] === 'MpesaReceiptNumber') {
        $mpesaReceipt = $item['Value'];
        break;
    }
}

// Validate necessary data
if (!$checkoutRequestID || !$mpesaReceipt) {
    file_put_contents('callback_log.txt', "❌ Missing CheckoutRequestID or MpesaReceiptNumber" . PHP_EOL, FILE_APPEND);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Missing data']);
    exit();
}

// Check if payment exists and is still pending
$stmt = $conn->prepare("SELECT * FROM pendingpayments WHERE checkout_id = ? AND status = 'pending' LIMIT 1");
$stmt->bind_param("s", $checkoutRequestID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $phone = $row['phone'];
    $package = $row['package'];
    $purchase_time = date('Y-m-d H:i:s');
    $name = 'Client';

    // Insert into clients table
    $insert = $conn->prepare("INSERT INTO clients (name, phone, package, purchase_time, checkout_id, mpesa_receipt) VALUES (?, ?, ?, ?, ?, ?)");
    $insert->bind_param("ssssss", $name, $phone, $package, $purchase_time, $checkoutRequestID, $mpesaReceipt);
    $insert->execute();

    // Mark pending payment as completed
    $update = $conn->prepare("UPDATE pendingpayments SET status = 'completed' WHERE checkout_id = ?");
    $update->bind_param("s", $checkoutRequestID);
    $update->execute();

    echo json_encode(["ResultDesc" => "Success", "ResultCode" => 0]);
} else {
    file_put_contents('callback_log.txt', "❌ CheckoutID not found or already processed: $checkoutRequestID" . PHP_EOL, FILE_APPEND);
    echo json_encode(["ResultDesc" => "Not Found", "ResultCode" => 1]);
}
?>
