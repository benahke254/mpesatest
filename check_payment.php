<?php
$conn = new mysqli("sql5.freesqldatabase.com", "sql5777359", "YQ8SA8yu2p", "sql5777359");

$checkout_id = $_GET['checkout_id'] ?? '';

if (empty($checkout_id)) {
    echo json_encode(["status" => "error", "message" => "Missing checkout_id"]);
    exit();
}

// Check if payment confirmed
$stmt = $conn->prepare("SELECT * FROM clients WHERE checkout_id = ? LIMIT 1");
$stmt->bind_param("s", $checkout_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode([
        "status" => "success",
        "name" => $row['name'],
        "package" => $row['package'],
        "purchase_time" => $row['purchase_time'],
        "mpesa_receipt" => $row['mpesa_receipt']
    ]);
} else {
    echo json_encode(["status" => "pending"]);
}
?>
