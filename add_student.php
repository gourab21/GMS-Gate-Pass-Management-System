<?php
session_start();

if (!isset($_SESSION['bhavan'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rfid_system";

// Connect to Database
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

// Get input data
$name = $_POST['name'];
$roll_no = $_POST['roll_no'];
$year_ = $_POST['year_'];
$dept = $_POST['dept'];
$bhavan = $_SESSION['bhavan']; // Get bhavan from session

// Default values
$green_led = "23:59";
$out_status = 0;
$rfid = "0";

// Insert into database
$stmt = $conn->prepare("INSERT INTO vidyamandira (rfid, bhavan, roll, name, year_, dept, green_led, `Out`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sssssssi", $rfid, $bhavan, $roll_no, $name, $year_, $dept, $green_led, $out_status);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "✅ Student added successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "❌ Failed to add student"]);
}

$stmt->close();
$conn->close();
?>
