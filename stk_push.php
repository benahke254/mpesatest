<?php
// Allow any origin to access this resource
header("Access-Control-Allow-Origin: *");  // This allows all origins; you can replace '*' with specific domains like 'https://example.com' for more security

// Allow specific methods (e.g., GET, POST)
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

// Allow specific headers (e.g., Content-Type, Authorization)
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// If it's a preflight request (OPTIONS request), return 200 status without any processing
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Stop further processing
    http_response_code(200);
    exit();
}

// Read raw POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($data['phone']) || !isset($data['amount'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$phone = $data['phone'];
$amount = $data['amount'];

// M-Pesa credentials (from your provided details)
$shortcode = '174379';
$consumerKey = 'FcrA6bZbGZfm7XGOsuQGMGQQlnNpYUSVuohKN4cbUBOhr7ml';
$consumerSecret = 'p30cG1LMM8AzGptCtk8MdtZrSY9R7KQ17r7ibaU6Q2X7n1XG4ijoWsFH7e8J9BkJ';
$passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
$callbackUrl = 'https://9fdf-102-135-174-108.ngrok-free.app/mpesatest/callback.php'; // Your callback.php URL

// Generate timestamp
$timestamp = date('YmdHis');

// Generate password
$password = base64_encode($shortcode . $passkey . $timestamp);

// Request access token
$credentials = base64_encode("$consumerKey:$consumerSecret");
$token_url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
if (!isset($result['access_token'])) {
    echo json_encode(['success' => false, 'message' => 'Failed to generate access token']);
    exit;
}

$access_token = $result['access_token'];

// Prepare STK Push request
$stkPushUrl = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
$payload = [
    'BusinessShortCode' => $shortcode,
    'Password' => $password,
    'Timestamp' => $timestamp,
    'TransactionType' => 'CustomerPayBillOnline',
    'Amount' => $amount,
    'PartyA' => $phone,
    'PartyB' => $shortcode,
    'PhoneNumber' => $phone,
    'CallBackURL' => $callbackUrl,
    'AccountReference' => 'QuickCoin',
    'TransactionDesc' => 'Hotspot Payment'
];

$ch = curl_init($stkPushUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $access_token
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if (isset($result['ResponseCode']) && $result['ResponseCode'] == '0') {
    echo json_encode(['success' => true, 'message' => 'STK Push Sent']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to initiate STK Push']);
}
?>
