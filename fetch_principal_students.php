<?php
session_start();
if (!isset($_SESSION['principal'])) {
    echo "Session expired.";
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

$bhavan = $_GET['bhavan'] ?? "";
$status = $_GET['status'] ?? "";
$name = $_GET['name'] ?? "";
$dept = $_GET['dept'] ?? "";

$sql = "SELECT roll, name, bhavan, dept, green_led, `Out`, rfid FROM vidyamandira WHERE 1=1";
$params = [];
$types = "";

if ($bhavan) {
    $sql .= " AND bhavan = ?";
    $params[] = $bhavan;
    $types .= "s";
}
if ($status !== "") {
    $sql .= " AND `Out` = ?";
    $params[] = $status;
    $types .= "i";
}
if ($name) {
    $sql .= " AND name LIKE ?";
    $params[] = "%$name%";
    $types .= "s";
}
if ($dept) {
    $sql .= " AND dept LIKE ?";
    $params[] = "%$dept%";
    $types .= "s";
}

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

echo "<table>
        <tr><th>Roll</th><th>Name</th><th>Bhavan</th><th>Department</th><th>Gate Pass Time</th><th>Status</th><th>RFID</th></tr>";

while ($row = $result->fetch_assoc()) {
    // We'll replace these with Firebase data in principal_dashboard.php
    $gatePassTime = ($row['green_led'] === '23:59') ? "-" : $row['green_led'];
    $statusText = $row['Out'] ? "🔴 Out" : "🟢 In";

    echo "<tr><td>{$row['roll']}</td><td>{$row['name']}</td><td>{$row['bhavan']}</td><td>{$row['dept']}</td>
          <td>{$gatePassTime}</td><td>{$statusText}</td><td>{$row['rfid']}</td></tr>";
}
echo "</table>";

$stmt->close();
$conn->close();
?>