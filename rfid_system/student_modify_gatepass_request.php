<?php
session_start();
if (!isset($_SESSION['roll'])) {
    echo "Unauthorized access!";
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rfid_system";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed.");
}

$roll = $_SESSION['roll'];
$request_time = $_POST['request_time'];
$reason = $_POST['reason'];

$sql = "UPDATE gatepass_requests SET request_time = ?, reason = ?, status = 'Pending' WHERE roll = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $request_time, $reason, $roll);

if ($stmt->execute()) {
    echo "success";
} else {
    echo "error";
}

$stmt->close();
$conn->close();
?>
