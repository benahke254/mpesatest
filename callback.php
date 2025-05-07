<?php
file_put_contents('callback_log.txt', json_encode($_POST), FILE_APPEND);

// callback.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Database credentials
$host = 'sql5.freesqldatabase.com';
$user = 'sql5777359';
$password = 'YQ8SA8yu2p';
$dbname = 'sql5777359';

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    file_put_contents('callback_log.txt', "DB ERROR: " . $conn->connect_error . PHP_EOL, FILE_APPEND);
    die("Database Connection Failed: " . $conn->connect_error);
}

// Confirm POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    file_put_contents('callback_log.txt', "Invalid method: " . $_SERVER['REQUEST_METHOD'] . PHP_EOL, FILE_APPEND);
    echo json_encode(["ResultDesc" => "Invalid method", "ResultCode" => 1]);
    exit;
}

// Get raw POST data
$callbackJSON = file_get_contents('php://input');
file_put_contents('callback_log.txt', "Raw JSON: $callbackJSON" . PHP_EOL, FILE_APPEND);

// Decode JSON
$callbackData = json_decode($callbackJSON, true);
if ($callbackData === null) {
    file_put_contents('callback_log.txt', "ERROR: Invalid JSON received." . PHP_EOL, FILE_APPEND);
    echo json_encode(["ResultDesc" => "Invalid JSON", "ResultCode" => 1]);
    exit;
}

// Check for ResultCode
if (isset($callbackData['Body']['stkCallback']['ResultCode'])) {
    $resultCode = $callbackData['Body']['stkCallback']['ResultCode'];
    if ($resultCode == 0) {
        // Extract values safely
        $meta = $callbackData['Body']['stkCallback']['CallbackMetadata']['Item'];
        $checkoutRequestID = $callbackData['Body']['stkCallback']['CheckoutRequestID'] ?? '';
        $amount = $meta[0]['Value'] ?? '';
        $mpesaReceipt = $meta[1]['Value'] ?? '';
        $phone = $meta[4]['Value'] ?? '';

        if ($amount && $mpesaReceipt && $phone && $checkoutRequestID) {
            $name = "Client";
            $package = $amount . "MB";
            $purchase_time = date('Y-m-d H:i:s');

            $stmt = $conn->prepare("INSERT INTO clients (name, phone, package, purchase_time, checkout_id, mpesa_receipt) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $name, $phone, $package, $purchase_time, $checkoutRequestID, $mpesaReceipt);

            if ($stmt->execute()) {
                file_put_contents('callback_log.txt', "✅ Payment inserted: $mpesaReceipt" . PHP_EOL, FILE_APPEND);
                echo json_encode(["ResultDesc" => "Success", "ResultCode" => 0]);
            } else {
                file_put_contents('callback_log.txt', "❌ DB Insert Error: " . $stmt->error . PHP_EOL, FILE_APPEND);
                echo json_encode(["ResultDesc" => "Insert failed", "ResultCode" => 1]);
            }
            $stmt->close();
        } else {
            file_put_contents('callback_log.txt', "❌ Missing values in metadata." . PHP_EOL, FILE_APPEND);
            echo json_encode(["ResultDesc" => "Missing metadata", "ResultCode" => 1]);
        }
    } else {
        file_put_contents('callback_log.txt', "⚠️ Payment not successful. ResultCode: $resultCode" . PHP_EOL, FILE_APPEND);
        echo json_encode(["ResultDesc" => "Payment failed", "ResultCode" => 0]);
    }
} else {
    file_put_contents('callback_log.txt', "❌ Missing ResultCode in callback." . PHP_EOL, FILE_APPEND);
    echo json_encode(["ResultDesc" => "Invalid callback", "ResultCode" => 1]);
}

$conn->close();
?>
