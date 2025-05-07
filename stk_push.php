<?php
header('Content-Type: application/json');

// Include DB connection
require_once 'db.php';

// Safaricom credentials
$consumerKey = 'FcrA6bZbGZfm7XGOsuQGMGQQlnNpYUSVuohKN4cbUBOhr7ml';
$consumerSecret = 'p30cG1LMM8AzGptCtk8MdtZrSY9R7KQ17r7ibaU6Q2X7n1XG4ijoWsFH7e8J9BkJ';
$shortCode = '174379';
$passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
$callbackUrl = 'https://mpesatest-mk71.onrender.com/callback.php';

// Get user input
$data = json_decode(file_get_contents('php://input'), true);
$phone = $data['phone'];
$package = $data['package'];

// Validate input
if (!$phone || !$package) {
    echo json_encode(['status' => 'error', 'message' => 'Phone number and package are required']);
    exit;
}

// Format phone number
if (substr($phone, 0, 1) === '0') {
    $phone = '254' . substr($phone, 1);
} elseif (substr($phone, 0, 3) === '254') {
    $phone = $phone;
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid phone number format']);
    exit;
}

// Get access token
$credentials = base64_encode("$consumerKey:$consumerSecret");
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Basic $credentials"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$accessToken = json_decode($response)->access_token ?? null;
if (!$accessToken) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to generate access token']);
    exit;
}

// STK push
$timestamp = date('YmdHis');
$password = base64_encode($shortCode . $passkey . $timestamp);

// Map package to amount
$amount = 0;
switch ($package) {
    case 'Daily - Ksh10':
        $amount = 10;
        break;
    case 'Weekly - Ksh50':
        $amount = 50;
        break;
    case 'Monthly - Ksh200':
        $amount = 200;
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid package selected']);
        exit;
}

// Generate unique checkout_id
$checkout_id = uniqid('tx_', true);

// Save to pendingpayments
$stmt = $conn->prepare("INSERT INTO pendingpayments (phone, package, checkout_id) VALUES (?, ?, ?)");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Database prepare failed: ' . $conn->error]);
    exit;
}
$stmt->bind_param("sss", $phone, $package, $checkout_id);
if (!$stmt->execute()) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to save pending payment: ' . $stmt->error]);
    exit;
}
$stmt->close();

// Initiate STK Push
$ch = curl_init('https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $accessToken",
    'Content-Type: application/json'
]);

$payload = json_encode([
    'BusinessShortCode' => $shortCode,
    'Password' => $password,
    'Timestamp' => $timestamp,
    'TransactionType' => 'CustomerPayBillOnline',
    'Amount' => $amount,
    'PartyA' => $phone,
    'PartyB' => $shortCode,
    'PhoneNumber' => $phone,
    'CallBackURL' => $callbackUrl,
    'AccountReference' => $package,
    'TransactionDesc' => "Hotspot payment for $package"
]);

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$result = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(['status' => 'error', 'message' => 'cURL error: ' . $err]);
} else {
    echo json_encode(['status' => 'success', 'message' => 'STK push initiated', 'checkout_id' => $checkout_id]);
}
?>
