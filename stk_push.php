<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

// Database connection
$conn = new mysqli("sql5.freesqldatabase.com", "sql5777359", "YQ8SA8yu2p", "sql5777359");
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// Get POST inputs
$phone = $_POST['phone'] ?? '';
$package = $_POST['package'] ?? '';

if (empty($phone) || empty($package)) {
    echo json_encode(['status' => 'error', 'message' => 'Phone or package missing']);
    exit;
}

// Generate unique checkout ID
$checkout_id = uniqid('cc_', true);

// Insert into pendingpayments table
$stmt = $conn->prepare("INSERT INTO pendingpayments (phone, package, checkout_id) VALUES (?, ?, ?)");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Database prepare failed']);
    exit;
}
$stmt->bind_param("sss", $phone, $package, $checkout_id);
$stmt->execute();
$stmt->close();

// Format phone (replace leading 0 with 254)
$phone = preg_replace('/^0/', '254', $phone);

// M-Pesa STK Push credentials
$shortcode = "174379";
$passkey = "bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919";
$consumerKey = "FcrA6bZbGZfm7XGOsuQGMGQQlnNpYUSVuohKN4cbUBOhr7ml";
$consumerSecret = "p30cG1LMM8AzGptCtk8MdtZrSY9R7KQ17r7ibaU6Q2X7n1XG4ijoWsFH7e8J9BkJ";

// Generate access token
$credentials = base64_encode($consumerKey . ':' . $consumerSecret);
$token_url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$token_response = curl_exec($ch);
curl_close($ch);

$token_data = json_decode($token_response);
if (!isset($token_data->access_token)) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to obtain access token']);
    exit;
}
$access_token = $token_data->access_token;

// Prepare STK Push request
$timestamp = date("YmdHis");
$password = base64_encode($shortcode . $passkey . $timestamp);
$stk_url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

$stk_payload = [
    'BusinessShortCode' => $shortcode,
    'Password' => $password,
    'Timestamp' => $timestamp,
    'TransactionType' => 'CustomerPayBillOnline',
    'Amount' => 1, // For testing, use 1
    'PartyA' => $phone,
    'PartyB' => $shortcode,
    'PhoneNumber' => $phone,
    'CallBackURL' => 'https://mpesatest-mk71.onrender.com/callback.php',
    'AccountReference' => $checkout_id,
    'TransactionDesc' => $package
];

$ch = curl_init($stk_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $access_token
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($stk_payload));
$stk_response = curl_exec($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_status != 200) {
    echo json_encode(['status' => 'error', 'message' => 'STK push request failed']);
    exit;
}

// Return final JSON response with checkout ID
echo json_encode([
    'status' => 'success',
    'message' => 'STK push initiated',
    'checkout_id' => $checkout_id
]);
?>
