<?php
// Allow any origin to access this resource
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Read raw POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['phone']) || !isset($data['amount'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$phone = $data['phone'];
$amount = $data['amount'];

// M-Pesa credentials
$shortcode = '174379';
$consumerKey = 'FcrA6bZbGZfm7XGOsuQGMGQQlnNpYUSVuohKN4cbUBOhr7ml';
$consumerSecret = 'p30cG1LMM8AzGptCtk8MdtZrSY9R7KQ17r7ibaU6Q2X7n1XG4ijoWsFH7e8J9BkJ';
$passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
$callbackUrl = 'https://mpesatest-mk71.onrender.com/callback.php';

$timestamp = date('YmdHis');
$password = base64_encode($shortcode . $passkey . $timestamp);

// Generate access token
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

// Send STK Push
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

// Final response to frontend
if (isset($result['ResponseCode']) && $result['ResponseCode'] == '0') {
    $checkoutRequestID = $result['CheckoutRequestID'];
    echo json_encode([
        'success' => true,
        'receipt' => $checkoutRequestID
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => $result['errorMessage'] ?? 'Failed to initiate STK Push'
    ]);
}
?>
