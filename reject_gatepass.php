<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['bhavan'])) {
    echo json_encode(["status" => "error", "message" => "Session token expired"]);
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rfid_system";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

$roll = $_POST['roll'] ?? '';
$bhavan = $_SESSION['bhavan'];

if (empty($roll)) {
    echo json_encode(["status" => "error", "message" => "Roll number is required"]);
    exit();
}

// Get the RFID for the student to update Firebase
$stmt = $conn->prepare("SELECT rfid, `Out` FROM vidyamandira WHERE roll = ? AND bhavan = ?");
$stmt->bind_param("ss", $roll, $bhavan);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $rfid = $row['rfid'];
    $out_status = $row['Out'];
} else {
    echo json_encode(["status" => "error", "message" => "Student not found"]);
    $stmt->close();
    $conn->close();
    exit();
}
$stmt->close();

// Update green_led to '23:59' in MySQL
$stmt = $conn->prepare("UPDATE vidyamandira SET green_led = '23:59' WHERE roll = ? AND bhavan = ?");
$stmt->bind_param("ss", $roll, $bhavan);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    // Update Firebase
    $firebaseUrl = "https://gatepass-3e72f-default-rtdb.asia-southeast1.firebasedatabase.app/students/{$rfid}.json";
    $data = [
        "green_led" => "23:59",
        "Out" => $out_status // Preserve the current Out status
    ];

    $ch = curl_init($firebaseUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $firebase_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($firebase_response === false || $http_code != 200) {
        echo json_encode(["status" => "error", "message" => "Failed to update Firebase"]);
    } else {
        echo json_encode(["status" => "success", "message" => "Gatepass rejected successfully"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Failed to reject gatepass in MySQL"]);
}

$stmt->close();
$conn->close();
?>