<?php
session_start();

// ✅ Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ Set JSON response header
header("Content-Type: application/json");

// ✅ Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rfid_system";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

// ✅ Ensure Warden is logged in
if (!isset($_SESSION['bhavan'])) {
    echo json_encode(["error" => "Unauthorized access."]);
    exit();
}

$warden_bhavan = $_SESSION['bhavan']; // Warden's Bhavan name

// ✅ FIX: Use the correct column name 'request_time' instead of 'gatepass_time'
$sql = "SELECT g.roll, g.request_time, g.reason, v.name 
        FROM gatepass_requests g
        JOIN vidyamandira v ON g.roll = v.roll 
        WHERE v.bhavan = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["error" => "SQL Prepare Error: " . $conn->error]);
    exit();
}

$stmt->bind_param("s", $warden_bhavan);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    echo json_encode(["error" => "SQL Execution Error: " . $stmt->error]);
    exit();
}

// ✅ Fetch results
$requests = [];
while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}

// ✅ Close connection
$stmt->close();
$conn->close();

// ✅ Return JSON response
if (empty($requests)) {
    echo json_encode(["message" => "No gatepass requests found."]);
} else {
    echo json_encode($requests);
}
?>
