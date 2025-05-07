<?php
// check_payment.php

// Database connection details
$host = 'sql5.freesqldatabase.com';
$user = 'sql5777359';
$password = 'YQ8SA8yu2p';
$dbname = 'sql5777359';

// Connect to MySQL database
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Database Connection Failed"]));
}

// Get phone and mpesa_receipt from AJAX request
$phone = isset($_GET['phone']) ? $_GET['phone'] : '';
$mpesa_receipt = isset($_GET['receipt']) ? $_GET['receipt'] : '';

if (empty($phone) || empty($mpesa_receipt)) {
    echo json_encode(["status" => "error", "message" => "Phone or receipt number missing"]);
    exit();
}

// Search for payment by phone number and receipt
$stmt = $conn->prepare("SELECT * FROM clients WHERE phone = ? AND mpesa_receipt = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("ss", $phone, $mpesa_receipt);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Payment found
    $row = $result->fetch_assoc();

    echo json_encode([
        "status" => "success",
        "package" => $row['package'],
        "purchase_time" => $row['purchase_time'],
        "mpesa_receipt" => $row['mpesa_receipt'],
        "name" => $row['name']
    ]);
} else {
    // No matching payment found
    echo json_encode(["status" => "pending"]);
}

$stmt->close();
$conn->close();
?>
