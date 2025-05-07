<?php
// Database connection
$conn = new mysqli("sql5.freesqldatabase.com", "sql5777359", "YQ8SA8yu2p", "sql5777359");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Inputs
$phone = $_POST['phone'];
$package = $_POST['package'];

// Generate unique checkout ID
$checkout_id = uniqid('cc_', true);

// Insert into pendingpayments
$stmt = $conn->prepare("INSERT INTO pendingpayments (phone, package, checkout_id) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $phone, $package, $checkout_id);
$stmt->execute();

// Format phone
$phone = preg_replace('/^0/', '254', $phone);

// M-Pesa STK Push API credentials
$shortcode = "174379";
$passkey = "bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919";
$consumerKey = "FcrA6bZbGZfm7XGOsuQGMGQQlnNpYUSVuohKN4cbUBOhr7ml";
$consumerSecret = "p30cG1LMM8AzGptCtk8MdtZrSY9R7KQ17r7ibaU6Q2X7n1XG4ijoWsFH7e8J9BkJ";

// Generate access token
$credentials = base64_encode($consumerKey . ':' . $consumerSecret);
$url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);
$access_token = json_decode($response)->access_token;

// Prepare STK Push request
$timestamp = date("YmdHis");
$password = base64_encode($shortcode . $passkey . $timestamp);
$stk_url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

$curl_post_data = [
    'BusinessShortCode' => $shortcode,
    'Password' => $password,
    'Timestamp' => $timestamp,
    'TransactionType' => 'CustomerPayBillOnline',
    'Amount' => 1,
    'PartyA' => $phone,
    'PartyB' => $shortcode,
    'PhoneNumber' => $phone,
    'CallBackURL' => 'https://mpesatest-mk71.onrender.com/callback.php',
    'AccountReference' => $checkout_id,
    'TransactionDesc' => $package
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $stk_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $access_token]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($curl_post_data));
$response = curl_exec($ch);
curl_close($ch);

// Return checkout_id for polling
echo json_encode(['status' => 'initiated', 'checkout_id' => $checkout_id]);
?>
