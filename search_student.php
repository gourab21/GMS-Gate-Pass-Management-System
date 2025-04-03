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

if (!isset($_POST['search_roll'])) {
    echo "Invalid request.";
    exit();
}

$roll = $_POST['search_roll'];
$bhavan = $_SESSION['bhavan'];

$sql = "SELECT roll, name, dept, green_led FROM vidyamandira WHERE roll = ? AND bhavan = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $roll, $bhavan);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $timeValue = htmlspecialchars($row['green_led']);
    echo "<table border='1'>
            <tr><th>Roll</th><td>{$row['roll']}</td></tr>
            <tr><th>Name</th><td>{$row['name']}</td></tr>
            <tr><th>Department</th><td>{$row['dept']}</td></tr>
            <tr><th>Gate Pass Time</th>
                <td>
                    <input type='time' id='led_time' value='{$timeValue}'>
                    <button onclick='updateLEDTime({$row['roll']})'>Update</button>
                </td>
            </tr>
          </table>";
} else {
    echo "No student found.";
}

$stmt->close();
$conn->close();
?>
