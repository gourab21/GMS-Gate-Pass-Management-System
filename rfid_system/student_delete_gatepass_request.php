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

$sql = "DELETE FROM gatepass_requests WHERE roll = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $roll);

if ($stmt->execute()) {
    echo "success";
} else {
    echo "error";
}

$stmt->close();
$conn->close();
?>
