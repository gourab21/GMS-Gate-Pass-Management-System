<?php
session_start();
if (!isset($_SESSION['principal'])) {
    header("Location: login.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rfid_system";

// Connect to MySQL
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Fetch Unique Bhavans for Sorting
$bhavanQuery = "SELECT DISTINCT bhavan FROM vidyamandira";
$bhavanResult = $conn->query($bhavanQuery);

// Firebase Realtime Database URL
$firebaseUrl = "https://gatepass-3e72f-default-rtdb.asia-southeast1.firebasedatabase.app/students.json";

// Function to sync Firebase data to MySQL
function syncFirebaseData($conn, $firebaseUrl) {
    $ch = curl_init($firebaseUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $firebase_response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($firebase_response === false) {
        return "Error fetching Firebase data: " . $curl_error;
    }

    $students = json_decode($firebase_response, true);
    if (!is_array($students)) {
        return "Invalid Firebase data format: " . $firebase_response;
    }

    $updatedCount = 0;
    foreach ($students as $rfid => $data) {
        if (isset($data['green_led'], $data['Out'])) {
            $green_led = $data['green_led'];
            $out_status = (int) $data['Out'];

            $stmt = $conn->prepare("UPDATE vidyamandira SET green_led = ?, `Out` = ? WHERE rfid = ?");
            if (!$stmt) {
                return "Prepare failed: " . $conn->error;
            }
            $stmt->bind_param("sis", $green_led, $out_status, $rfid);
            $execute_result = $stmt->execute();
            if ($execute_result === false) {
                return "Execute failed for RFID $rfid: " . $stmt->error;
            }

            if ($stmt->affected_rows > 0) {
                $updatedCount++;
            }
            $stmt->close();
        }
    }
    
    // return "✅ $updatedCount records updated from Firebase.";
}

// Handle sync request
if (isset($_GET['sync'])) {
    if (!isset($_SESSION['principal'])) {
        echo "Session expired.";
    } else {
        $result = syncFirebaseData($conn, $firebaseUrl);
        echo $result;
    }
    $conn->close();
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Principal Dashboard</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; }
    body { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; background: linear-gradient(135deg, #74b9ff, #0984e3); padding: 20px; text-align: center; }
    .dashboard-container { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2); width: 100%; max-width: 800px; }
    h2 { margin-bottom: 15px; color: #333; }
    .filter-group { display: flex; justify-content: space-between; margin-bottom: 15px; }
    select, input { padding: 10px; font-size: 16px; border: 1px solid #ccc; border-radius: 5px; }
    button { padding: 10px; border: none; background: #0984e3; color: white; font-size: 16px; border-radius: 5px; cursor: pointer; margin: 5px; }
    button:hover { background: #0659a7; }
    .logout { position: absolute; top: 15px; right: 20px; background: #ff4757; padding: 8px 15px; color: white; text-decoration: none; border-radius: 5px; }
    .logout:hover { background: #e84118; }

    /* Table styling for PC */
    #studentsList { width: 100%; margin-top: 10px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid black; padding: 10px; text-align: center; }
    th { background-color: #f2f2f2; }
    .sync-message { margin-top: 10px; color: #333; }

    /* Mobile view adjustments */
    @media (max-width: 717px) {
        body { padding: 10px; }
        .dashboard-container { padding: 15px; max-width: 100%; }
        .filter-group { flex-direction: column; gap: 10px; }
        select, input { width: 100%; margin-bottom: 10px; font-size: 14px; padding: 8px; }
        button { width: 100%; padding: 12px; font-size: 14px; }
        .logout { top: 10px; right: 10px; padding: 6px 12px; font-size: 14px; }
        
        /* Mobile table fix: enable horizontal scrolling */
        #studentsList { overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%; }
        table { font-size: 12px; min-width: 600px; width: 100%; } /* Prevent table from breaking layout */
        th, td { 
            padding: 6px; /* Reduce padding for better spacing */
            white-space: nowrap; /* Prevent text from wrapping */
        }
    }
</style>


    <script>
        let firebaseData = {};

        function formatTime(value) {
            if (!value || value === '23:59' || value === '-') return 'N/A';
            if (!isNaN(value) && value.toString().length > 5) {
                const date = new Date(parseInt(value) * 1000);
                return date.toLocaleTimeString('en-US', { 
                    hour: '2-digit', 
                    minute: '2-digit', 
                    hour12: true 
                });
            }
            if (typeof value === 'string' && /^\d{2}:\d{2}$/.test(value)) {
                const [hours, minutes] = value.split(':');
                const date = new Date();
                date.setHours(parseInt(hours));
                date.setMinutes(parseInt(minutes));
                return date.toLocaleTimeString('en-US', { 
                    hour: '2-digit', 
                    minute: '2-digit', 
                    hour12: true 
                });
            }
            return value;
        }

        function loadPrincipalStudents() {
            var bhavan = document.getElementById("bhavanFilter").value;
            var status = document.getElementById("statusFilter").value;
            var name = document.getElementById("nameSearch").value;
            var dept = document.getElementById("deptSearch").value;

            fetch("fetch_principal_students.php?bhavan=" + encodeURIComponent(bhavan) + 
                  "&status=" + encodeURIComponent(status) + 
                  "&name=" + encodeURIComponent(name) + 
                  "&dept=" + encodeURIComponent(dept))
                .then(response => response.text())
                .then(data => {
                    fetch('<?php echo $firebaseUrl; ?>')
                        .then(response => response.json())
                        .then(firebase => {
                            firebaseData = firebase || {};
                            console.log("Firebase data:", firebase); // Debug Firebase data
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(data, 'text/html');
                            let table = doc.querySelector('table');

                            if (table) {
                                const headerRow = table.querySelector('tr');
                                let headers = headerRow.querySelectorAll('th');
                                headers[4].textContent = 'Gate Pass Time (Firebase)';
                                headers[5].textContent = 'Status (Firebase)';

                                const rows = table.querySelectorAll('tr');
                                rows.forEach((row, index) => {
                                    if (index > 0) {
                                        const rfidCell = row.cells[6];
                                        const rfid = rfidCell ? rfidCell.textContent.trim() : '';
                                        const studentData = firebaseData[rfid] || {};
                                        const greenLed = studentData.green_led || '';
                                        const outStatus = studentData.Out !== undefined ? studentData.Out : '';

                                        const timeCell = row.cells[4];
                                        timeCell.textContent = formatTime(greenLed);

                                        const statusCell = row.cells[5];
                                        statusCell.textContent = outStatus === 1 ? '🔴 Out' : '🟢 In';
                                    }
                                });
                            } else {
                                table = document.createElement('table');
                                table.innerHTML = '<tr><th>Roll</th><th>Name</th><th>Bhavan</th><th>Department</th><th>Gate Pass Time (Firebase)</th><th>Status (Firebase)</th></tr>';
                                document.getElementById("studentsList").appendChild(table);
                            }
                            document.getElementById("studentsList").innerHTML = table.outerHTML;
                        })
                        .catch(error => {
                            console.error('Error fetching Firebase data:', error);
                            document.getElementById("studentsList").innerHTML = data;
                        });
                })
                .catch(error => console.error('Error fetching students:', error));
        }

        function syncData() {
            fetch("principal_dashboard.php?sync=true")
                .then(response => response.text())
                .then(message => {
                    console.log("Sync response:", message); // Debug sync response
                    if (message === "Session expired.") {
                        document.getElementById("syncMessage").innerHTML = "<p>Session expired. Please log in again.</p>";
                    } else {
                        document.getElementById("syncMessage").innerHTML = "<p>" + message + "</p>";
                        loadPrincipalStudents(); // Refresh table
                    }
                })
                .catch(error => {
                    console.error("Error syncing data:", error);
                    document.getElementById("syncMessage").innerHTML = "<p>Error syncing data: " + error.message + "</p>";
                });
        }

        document.addEventListener("DOMContentLoaded", function () {
            loadPrincipalStudents();
        });
    </script>
</head>
<body>
    <a href="logout.php" class="logout">🚪 Logout</a>

    <div class="dashboard-container">
        <h2>📌 Principal Dashboard</h2>

        <div class="filter-group">
            <select id="bhavanFilter" onchange="loadPrincipalStudents()">
                <option value="">All Bhavans</option>
                <?php while ($row = $bhavanResult->fetch_assoc()) { ?>
                    <option value="<?= htmlspecialchars($row['bhavan']) ?>"><?= htmlspecialchars($row['bhavan']) ?></option>
                <?php } ?>
            </select>

            <select id="statusFilter" onchange="loadPrincipalStudents()">
                <option value="">All Students</option>
                <option value="0">🟢 In</option>
                <option value="1">🔴 Out</option>
            </select>

            <input type="text" id="nameSearch" placeholder="Search by Name" onkeyup="loadPrincipalStudents()">
            <input type="text" id="deptSearch" placeholder="Search by Department" onkeyup="loadPrincipalStudents()">
        </div>

        <button onclick="syncData()">🔄 Refresh </button>
        <div id="syncMessage" class="sync-message"></div>

        <div id="studentsList"></div>
    </div>
</body>
</html>
<?php $conn->close(); ?>