<?php
session_start();
header("Content-Type: application/json");

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rfid_system";

// Firebase URL
$firebaseUrl = "https://gatepass-3e72f-default-rtdb.asia-southeast1.firebasedatabase.app/students";

// Connect to MySQL
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Database connection failed: " . $conn->connect_error]));
}

// Check if required POST parameters exist
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['action'], $_POST['roll'], $_POST['time'])) {
    die(json_encode(["status" => "error", "message" => "Invalid request parameters."]));
}

$action = $_POST['action'];
$roll = $_POST['roll'];
$time = date("H:i", strtotime($_POST['time'])); // Converts to HH:MM format


// Validate Inputs
if (empty($roll) || empty($time) || !in_array($action, ['approve', 'reject'])) {
    die(json_encode(["status" => "error", "message" => "Invalid input data."]));
}

// Retrieve the RFID corresponding to the roll number
$stmt = $conn->prepare("SELECT rfid FROM vidyamandira WHERE roll = ?");
$stmt->bind_param("s", $roll);
$stmt->execute();
$result = $stmt->get_result();
$rfidData = $result->fetch_assoc();
$stmt->close();

if (!$rfidData || empty($rfidData['rfid'])) {
    die(json_encode(["status" => "error", "message" => "RFID not found for Roll No: $roll"]));
}

$rfid = $rfidData['rfid']; // RFID Value from Database

if ($action === "approve") {
    // ✅ Update MySQL: Set green_led
    $stmt = $conn->prepare("UPDATE vidyamandira SET green_led = ? WHERE roll = ?");
    if ($stmt) {
        $stmt->bind_param("ss", $time, $roll);
        $stmt->execute();
        $stmt->close();
    } else {
        die(json_encode(["status" => "error", "message" => "Error updating green_led: " . $conn->error]));
    }

    // ✅ Update `gatepass_requests` table status
    $status = "Permission Granted at $time";
    $stmt = $conn->prepare("UPDATE gatepass_requests SET status = ? WHERE roll = ?");
    if ($stmt) {
        $stmt->bind_param("ss", $status, $roll);
        $stmt->execute();
        $stmt->close();
    } else {
        die(json_encode(["status" => "error", "message" => "Error updating gatepass_requests: " . $conn->error]));
    }

    // ✅ Update Firebase green_led field for the corresponding RFID
    $firebaseData = json_encode(["green_led" => $time]); // Setting Out=1 when approving
    $firebasePath = "$firebaseUrl/$rfid.json"; // Update specific RFID entry

    $ch = curl_init($firebasePath);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH"); // Use PATCH to update specific fields
    curl_setopt($ch, CURLOPT_POSTFIELDS, $firebaseData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local development, disable SSL verification

    $firebaseResponse = curl_exec($ch);
    curl_close($ch);

    if ($firebaseResponse === false) {
        die(json_encode(["status" => "error", "message" => "Failed to update Firebase"]));
    }
} else {
    // ❌ If rejected, update `gatepass_requests` status as "Not Issued"
    $status = "Not Issued";
    $stmt = $conn->prepare("UPDATE gatepass_requests SET status = ? WHERE roll = ?");
    if ($stmt) {
        $stmt->bind_param("ss", $status, $roll);
        $stmt->execute();
        $stmt->close();
    } else {
        die(json_encode(["status" => "error", "message" => "Error updating gatepass_requests: " . $conn->error]));
    }
}

// 🚨 Delete request from `gatepass_requests` after processing
$stmt = $conn->prepare("DELETE FROM gatepass_requests WHERE roll = ?");
if ($stmt) {
    $stmt->bind_param("s", $roll);
    $stmt->execute();
    $stmt->close();
} else {
    die(json_encode(["status" => "error", "message" => "Error deleting gatepass request: " . $conn->error]));
}

// ✅ Return success response
echo json_encode(["status" => "success", "message" => "Gatepass request processed successfully."]);
$conn->close();
?>
