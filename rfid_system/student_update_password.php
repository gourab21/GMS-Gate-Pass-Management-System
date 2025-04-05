<?php
session_start();
header("Content-Type: application/json");

if (!isset($_SESSION['roll'])) {
    echo json_encode(["status" => "error", "message" => "Session expired. Please log in again."]);
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rfid_system";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed."]);
    exit();
}

if (!isset($_POST['new_password']) || empty($_POST['new_password'])) {
    echo json_encode(["status" => "error", "message" => "Password cannot be empty."]);
    exit();
}

$roll = $_SESSION['roll'];
$newPassword = password_hash($_POST['new_password'], PASSWORD_DEFAULT);

$sql = "UPDATE student_users SET password = ? WHERE roll = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $newPassword, $roll);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(["status" => "success", "message" => "✅ Password updated successfully!"]);
} else {
    echo json_encode(["status" => "error", "message" => "⚠ No changes made."]);
}

$stmt->close();
$conn->close();
?>
