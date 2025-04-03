<?php
session_start();
if (!isset($_SESSION['bhavan'])) {
    echo "Session token expired";
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

$bhavan = $_SESSION['bhavan'];
$filter = isset($_GET['filter']) ? $_GET['filter'] : "all";

// ✅ Fetch student counts if requested
if (isset($_GET['count'])) {
    $countData = [
        "all" => 0,
        "in" => 0,
        "out" => 0,
        "issued" => 0 // New count for issued gatepasses
    ];

    // Count students in each category
    $countQuery = "SELECT 
                    COUNT(*) as total, 
                    SUM(CASE WHEN `Out` = 0 THEN 1 ELSE 0 END) as inCount, 
                    SUM(CASE WHEN `Out` = 1 THEN 1 ELSE 0 END) as outCount,
                    SUM(CASE WHEN green_led != '23:59' AND green_led IS NOT NULL THEN 1 ELSE 0 END) as issuedCount 
                   FROM vidyamandira WHERE bhavan = ?";
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param("s", $bhavan);
    $stmt->execute();
    $stmt->bind_result($total, $inCount, $outCount, $issuedCount);
    $stmt->fetch();
    $stmt->close();

    $countData["all"] = $total;
    $countData["in"] = $inCount;
    $countData["out"] = $outCount;
    $countData["issued"] = $issuedCount;

    echo json_encode($countData);
    $conn->close();
    exit();
}

// ✅ Filter students based on "in", "out", "all", or "issued"
$sql = "SELECT roll, name, year_, dept, green_led, `Out` FROM vidyamandira WHERE bhavan = ?";
if ($filter == "in") {
    $sql .= " AND `Out` = 0";
} elseif ($filter == "out") {
    $sql .= " AND `Out` = 1";
} elseif ($filter == "issued") {
    $sql .= " AND green_led != '23:59' AND green_led IS NOT NULL";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $bhavan);
$stmt->execute();
$result = $stmt->get_result();

// ✅ Display the student table
echo "<table>
        <tr>
            <th>Roll</th>
            <th>Name</th>
            <th>Year</th>
            <th>Department</th>
            <th>Gate Pass Time</th>
            <th>Out Status</th>
        </tr>";

while ($row = $result->fetch_assoc()) {
    $outStatus = ($row['Out'] == 1) 
        ? "<span style='color: red; font-weight: bold;'>Outside</span>" 
        : "<span style='color: green; font-weight: bold;'>Inside</span>";

    // Convert 24-hour format to 12-hour format with AM/PM
    if ($row['green_led'] === '23:59') {
        $gatePassTime = "-";
    } else {
        $gatePassTime = date("h:i A", strtotime($row['green_led']));
    }

    echo "<tr>
            <td>{$row['roll']}</td>
            <td>{$row['name']}</td>
            <td>{$row['year_']}</td>
            <td>{$row['dept']}</td>
            <td>{$gatePassTime}</td>
            <td>{$outStatus}</td>
          </tr>";
}

echo "</table>";

$stmt->close();
$conn->close();
?>