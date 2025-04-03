<?php
session_start();
header('Content-Type: application/json'); // Return response in JSON format

if (!isset($_SESSION['bhavan'])) {
    echo json_encode(["status" => "error", "message" => "Session expired. Please log in again."]);
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rfid_system";

// ✅ Connect to MySQL
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed."]);
    exit();
}

// ✅ Firebase Realtime Database URL
$firebaseUrl = "https://gatepass-3e72f-default-rtdb.asia-southeast1.firebasedatabase.app/students";

// ✅ Validate Input
if (empty($_POST['roll']) || empty($_POST['led_time'])) {
    echo json_encode(["status" => "error", "message" => "Invalid request. Missing required parameters."]);
    exit();
}

$roll = trim($_POST['roll']);
$led_time = trim($_POST['led_time']);
$bhavan = $_SESSION['bhavan'];

// ✅ Validate Roll Number
if (!preg_match('/^\d+$/', $roll)) {
    echo json_encode(["status" => "error", "message" => "Invalid Roll Number format."]);
    exit();
}

// ✅ Validate Time Format (24-hour HH:MM)
if (!preg_match("/^([01][0-9]|2[0-3]):[0-5][0-9]$/", $led_time)) {
    echo json_encode(["status" => "error", "message" => "Invalid time format. Use HH:MM (24-hour format)."]);
    exit();
}

// ✅ Get RFID based on Roll No.
$sql = "SELECT rfid FROM vidyamandira WHERE roll = ? AND bhavan = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $roll, $bhavan);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $rfid = $row['rfid'];

    // ✅ Update MySQL
    $update_sql = "UPDATE vidyamandira SET green_led = ?, `Out` = 0 WHERE roll = ? AND bhavan = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sis", $led_time, $roll, $bhavan);
    $update_stmt->execute();

    if ($update_stmt->affected_rows > 0) {
        // ✅ Update Firebase
        $firebase_url = "$firebaseUrl/$rfid.json";
        $firebase_data = json_encode(["green_led" => $led_time, "Out" => 0]);

        $ch = curl_init($firebase_url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $firebase_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $firebase_response = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_status == 200) {
            echo json_encode(["status" => "success", "message" => "Student gate pass updated successfully."]);
        } else {
            echo json_encode(["status" => "warning", "message" => "MySQL updated, but Firebase update failed."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "No changes made. Check Roll Number or Bhavan."]);
    }

    $update_stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "No student found with Roll No: $roll in Bhavan: $bhavan."]);
}

$stmt->close();
$conn->close();
?>
