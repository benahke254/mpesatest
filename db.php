<?php
// db.php

$conn = new mysqli("sql5.freesqldatabase.com", "sql5777359", "YQ8SA8yu2p", "sql5777359");

if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Connection failed: ' . $conn->connect_error]));
}
?>
