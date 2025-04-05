<?php
session_start();
if (!isset($_SESSION['roll'])) {
    echo json_encode(["status" => "error", "message" => "User not logged in."]);
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rfid_system";

// Database connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed."]);
    exit();
}

$roll = $_SESSION['roll'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $request_time = $_POST['request_time'];
    $reason = $_POST['reason'];

    // Check if a request already exists for the student
    $check_sql = "SELECT * FROM gatepass_requests WHERE roll = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $roll);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "You already have a gate pass request."]);
    } else {
        // Insert new gate pass request
        $sql = "INSERT INTO gatepass_requests (roll, request_time, reason, status) VALUES (?, ?, ?, 'Pending')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $roll, $request_time, $reason);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Gate Pass Requested!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Request Failed!"]);
        }

        $stmt->close();
    }
    $check_stmt->close();
}
$conn->close();
?>
