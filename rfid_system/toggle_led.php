<?php
session_start();
if (!isset($_SESSION['bhavan'])) {
    echo "Session expired.";
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rfid_system";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_POST['roll']) || !isset($_POST['gate_pass_time'])) {
    echo "Invalid request.";
    exit();
}

$roll = $_POST['roll'];
$bhavan = $_SESSION['bhavan'];
$time = $_POST['gate_pass_time']; // Time entered by the user

// Validate time format (24-hour format HH:MM)
if (!preg_match("/^([01][0-9]|2[0-3]):[0-5][0-9]$/", $time)) {
    echo "Invalid time format. Use HH:MM (24-hour format).";
    exit();
}

// Update green_led with just the time
$sql = "UPDATE vidyamandira SET green_led = ? WHERE roll = ? AND bhavan = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sis", $time, $roll, $bhavan);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo "Gate pass time updated to " . $time;
} else {
    echo "No changes made. Check roll number or bhavan.";
}

$stmt->close();
$conn->close();
?>
