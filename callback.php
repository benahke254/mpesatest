<?php
$conn = new mysqli("sql5.freesqldatabase.com", "sql5777359", "YQ8SA8yu2p", "sql5777359");

$callbackJSON = file_get_contents('php://input');
$callbackData = json_decode($callbackJSON, true);
file_put_contents('callback_log.txt', $callbackJSON . PHP_EOL, FILE_APPEND);

$checkoutRequestID = $callbackData['Body']['stkCallback']['CheckoutRequestID'] ?? '';
$meta = $callbackData['Body']['stkCallback']['CallbackMetadata']['Item'] ?? [];

$mpesaReceipt = '';
foreach ($meta as $item) {
    if ($item['Name'] == 'MpesaReceiptNumber') {
        $mpesaReceipt = $item['Value'];
        break;
    }
}

if (empty($checkoutRequestID) || empty($mpesaReceipt)) {
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Missing data']);
    exit();
}

// Find matching pending payment
$stmt = $conn->prepare("SELECT * FROM pendingpayments WHERE checkout_id = ? AND status = 'pending' LIMIT 1");
$stmt->bind_param("s", $checkoutRequestID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $phone = $row['phone'];
    $package = $row['package'];
    $purchase_time = date('Y-m-d H:i:s');
    $name = "Client";

    // Insert into clients
    $insert = $conn->prepare("INSERT INTO clients (name, phone, package, purchase_time, checkout_id, mpesa_receipt) VALUES (?, ?, ?, ?, ?, ?)");
    $insert->bind_param("ssssss", $name, $phone, $package, $purchase_time, $checkoutRequestID, $mpesaReceipt);
    $insert->execute();

    // Update pendingpayments
    $update = $conn->prepare("UPDATE pendingpayments SET status = 'completed' WHERE checkout_id = ?");
    $update->bind_param("s", $checkoutRequestID);
    $update->execute();

    echo json_encode(["ResultDesc" => "Success", "ResultCode" => 0]);
} else {
    file_put_contents('callback_log.txt', "❌ CheckoutID not found in pendingpayments: $checkoutRequestID" . PHP_EOL, FILE_APPEND);
    echo json_encode(["ResultDesc" => "Not Found", "ResultCode" => 1]);
}
?>
