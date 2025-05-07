<?php
// check_payment.php

// Database connection details
$host = 'localhost';
$user = 'root';
$password = '';
$dbname = 'mpesatest';

// Connect to MySQL database
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Database Connection Failed"]));
}

// Get phone number from AJAX request
$phone = isset($_GET['phone']) ? $_GET['phone'] : '';

if (empty($phone)) {
    echo json_encode(["status" => "error", "message" => "Phone number missing"]);
    exit();
}

// Search for payment by phone number
$stmt = $conn->prepare("SELECT * FROM clients WHERE phone = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("s", $phone);
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
    // No payment found
    echo json_encode(["status" => "pending"]);
}

$stmt->close();
$conn->close();
?>
