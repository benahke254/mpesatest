<?php
// Database credentials
$host = 'sql5.freesqldatabase.com';
$user = 'sql5777359';
$password = 'YQ8SA8yu2p';
$dbname = 'sql5777359';

// Create connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

// Fetch the most recent transaction
$sql = "SELECT phone, package, checkout_id 
        FROM clients 
        ORDER BY time DESC 
        LIMIT 1";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    echo "<h3>✅ Payment Successful!</h3>";
    echo "<strong>Phone:</strong> " . htmlspecialchars($row['phone']) . "<br>";
    echo "<strong>Package:</strong> " . htmlspecialchars($row['package']) . "<br>";
    echo "<strong>Checkout ID:</strong> " . htmlspecialchars($row['checkout_id']) . "<br>";
    echo "<strong>Time:</strong> " . htmlspecialchars($row['time']) . "<br>";
} else {
    echo "❌ No recent payment found.";
}

$conn->close();
?>
