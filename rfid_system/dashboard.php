<?php
$timeout = 600; // 10 minutes

ini_set('session.gc_maxlifetime', $timeout);
ini_set('session.cookie_lifetime', $timeout);

session_start();

// Check session timeout but don't redirect immediately
$session_expired = false;
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout) {
    session_unset();
    session_destroy();
    $session_expired = true; // Flag to indicate session has expired
} else if (isset($_SESSION['LAST_ACTIVITY'])) {
    $_SESSION['LAST_ACTIVITY'] = time(); // Update last activity timestamp if session is still valid
}

// If 'bhavan' session doesn't exist, consider it expired too
if (!isset($_SESSION['bhavan'])) {
    $session_expired = true;
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rfid_system";

// Connect to MySQL
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed.");
}

// Firebase Realtime Database URL
$firebaseUrl = "https://gatepass-3e72f-default-rtdb.asia-southeast1.firebasedatabase.app/students.json";

// Fetch data from Firebase and update MySQL
function syncFirebaseData($conn, $firebaseUrl) {
    $ch = curl_init($firebaseUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $firebase_response = curl_exec($ch);
    curl_close($ch);

    if ($firebase_response === false) {
        return "Error fetching Firebase data.";
    }

    $students = json_decode($firebase_response, true);
    if (!is_array($students)) {
        return "Invalid Firebase data format.";
    }

    $updatedCount = 0;
    foreach ($students as $rfid => $data) {
        if (isset($data['green_led'], $data['Out'])) {
            $green_led = $data['green_led'];
            $out_status = (int) $data['Out'];

            $stmt = $conn->prepare("UPDATE vidyamandira SET green_led = ?, `Out` = ? WHERE rfid = ?");
            $stmt->bind_param("sis", $green_led, $out_status, $rfid);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $updatedCount++;
            }

            $stmt->close();
        }
    }
    
    return "✅ $updatedCount records updated from Firebase.";
}

// Run Firebase Sync when requested, but check session first
if (isset($_GET['sync'])) {
    if ($session_expired) {
        echo "Session token expired";
    } else {
        echo syncFirebaseData($conn, $firebaseUrl);
    }
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warden Dashboard</title>
    <style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; }
    body { 
        display: flex; 
        justify-content: center; 
        align-items: center; 
        min-height: 100vh; 
        background: linear-gradient(135deg, #74b9ff, #0984e3); 
        padding: 20px; 
        text-align: center; 
    }
    .dashboard-container { 
        background: rgba(255, 255, 255, 0.95); 
        padding: 30px; 
        border-radius: 12px; 
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15); 
        width: 100%; 
        max-width: 600px; 
        border: 1px solid rgba(255, 255, 255, 0.3); 
    }
    h2 { 
        margin-bottom: 20px; 
        color: #333; 
        font-size: 28px; 
        font-weight: 600; 
        letter-spacing: 1px; 
    }
    h3 { 
        color: #444; 
        font-size: 20px; 
        margin: 15px 0; 
        font-weight: 500; 
    }
    .btn-group { 
        display: flex; 
        justify-content: space-between; 
        margin: 15px 0; 
        gap: 10px; 
    }
    button { 
        flex: 1; 
        padding: 12px; 
        border: none; 
        background: linear-gradient(to right, #0984e3, #0659a7); 
        color: white; 
        font-size: 16px; 
        border-radius: 6px; 
        cursor: pointer; 
        transition: background 0.3s, transform 0.2s; 
        margin: 5px; 
    }
    button:hover { 
        background: linear-gradient(to right, #0659a7, #043f7a); 
        transform: translateY(-2px); 
    }
    button.active { 
        background: linear-gradient(to right, #0659a7, #043f7a); 
        font-weight: bold; 
        box-shadow: inset 0 0 5px rgba(0, 0, 0, 0.3); 
    }
    .hidden { display: none; }
    .logout { 
        position: absolute; 
        top: 15px; 
        right: 20px; 
        background: linear-gradient(to right, #ff4757, #e84118); 
        padding: 8px 15px; 
        color: white; 
        text-decoration: none; 
        border-radius: 6px; 
        transition: background 0.3s, transform 0.2s; 
    }
    .logout:hover { 
        background: linear-gradient(to right, #e84118, #c0392b); 
        transform: translateY(-2px); 
    }
    .container { margin-top: 15px; }
    input { 
        width: 80%; 
        padding: 12px; 
        margin-top: 5px; 
        border: 1px solid #ccc; 
        border-radius: 6px; 
        font-size: 16px; 
        background: #f9f9f9; 
        transition: border-color 0.3s; 
    }
    input:focus { 
        border-color: #0984e3; 
        outline: none; 
        box-shadow: 0 0 5px rgba(9, 132, 227, 0.3); 
    }
    .error { 
        color: #e84118; 
        font-size: 14px; 
        margin-top: 10px; 
        font-weight: 500; 
    }
    table { 
        width: 100%; 
        margin-top: 10px; 
        border-collapse: collapse; 
        background: #fff; 
        border-radius: 6px; 
        overflow: hidden; 
    }
    th, td { 
        border: 1px solid #ddd; 
        padding: 12px; 
        text-align: center; 
    }
    th { 
        background-color: #f2f2f2; 
        color: #333; 
        font-weight: 600; 
    }
    td { 
        color: #555; 
    }
    .reject-btn { 
        background: linear-gradient(to right, #ff4757, #e84118); 
        padding: 5px 10px; 
        color: white; 
        border: none; 
        border-radius: 4px; 
        cursor: pointer; 
        transition: background 0.3s; 
    }
    .reject-btn:hover { 
        background: linear-gradient(to right, #e84118, #c0392b); 
    }

    /* Mobile view adjustments */
    @media (max-width: 600px) {
        body { padding: 10px; }
        .dashboard-container { padding: 20px; max-width: 100%; }
        h2 { font-size: 24px; }
        h3 { font-size: 18px; }
        .btn-group { flex-direction: column; gap: 8px; }
        button { padding: 10px; font-size: 14px; }
        .logout { top: 10px; right: 10px; padding: 6px 12px; font-size: 14px; }
        input { width: 100%; padding: 10px; font-size: 14px; }
        .error { font-size: 12px; }
        th, td { padding: 8px; font-size: 12px; }
        .reject-btn { padding: 4px 8px; font-size: 12px; }
    }
</style>
    <script>
        function showSection(sectionId) {
            document.getElementById("studentList").classList.add("hidden");
            document.getElementById("requestedGatepass").classList.add("hidden");
            document.getElementById("issuedGatepass").classList.add("hidden");

            document.querySelectorAll(".btn-group button").forEach(btn => btn.classList.remove("active"));
            document.querySelector(`button[onclick="showSection('${sectionId}')"]`).classList.add("active");

            if (sectionId === "studentList") {
                syncStudentData();
            } else if (sectionId === "requestedGatepass") {
                loadGatepassRequests();
            } else if (sectionId === "issuedGatepass") {
                loadIssuedGatepasses();
            }

            document.getElementById(sectionId).classList.remove("hidden");
        }

        function syncStudentData() {
            fetch("dashboard.php?sync=true")
                .then(response => response.text())
                .then(message => {
                    if (message === "Session token expired") {
                        document.getElementById("studentListContent").innerHTML = "<p>Session token expired. Please log in again.</p>";
                    } else {
                        loadStudentList(); // Default to 'all' filter
                    }
                })
                .catch(error => {
                    console.error("Error syncing Firebase data:", error);
                    loadStudentList();
                });
        }

        function loadStudentList(filter = 'all') {
            fetch("fetch_student_list.php?filter=" + filter)
                .then(response => response.text())
                .then(data => {
                    if (data === "Session token expired") {
                        document.getElementById("studentListContent").innerHTML = "<p>Session token expired. Please log in again.</p>";
                    } else {
                        document.getElementById("studentListContent").innerHTML = data;
                        updateCounts();

                        document.querySelectorAll("#studentList .btn-group button").forEach(btn => btn.classList.remove("active"));
                        if (filter === 'in') {
                            document.getElementById("inButton").classList.add("active");
                        } else if (filter === 'out') {
                            document.getElementById("outButton").classList.add("active");
                        } else {
                            document.getElementById("allButton").classList.add("active");
                        }
                    }
                });
        }

        function updateCounts() {
            fetch("fetch_student_list.php?count=true")
                .then(response => response.json())
                .then(data => {
                    if (data === "Session token expired") {
                        // Do nothing or handle differently if needed
                    } else {
                        document.getElementById("inCount").innerText = data.in;
                        document.getElementById("outCount").innerText = data.out;
                        document.getElementById("allCount").innerText = data.all;
                        document.getElementById("issuedCount").innerText = data.issued;
                    }
                });
        }

        function loadIssuedGatepasses() {
            fetch("fetch_student_list.php?filter=issued")
                .then(response => response.text())
                .then(data => {
                    if (data === "Session token expired") {
                        document.getElementById("issuedGatepassContent").innerHTML = "<p>Session token expired. Please log in again.</p>";
                    } else {
                        // Add Reject button to each row with proper roll number
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(data, 'text/html');
                        const rows = doc.querySelectorAll('tr');
                        let tableContent = '<table><tr><th>Roll</th><th>Name</th><th>Year</th><th>Department</th><th>Gate Pass Time</th><th>Out Status</th><th>Action</th></tr>';
                        rows.forEach(row => {
                            const cells = row.querySelectorAll('td');
                            if (cells.length > 0) {
                                const roll = cells[0].textContent; // Roll number is in the first column
                                tableContent += `<tr>${row.innerHTML}<td><button class="reject-btn" onclick="rejectGatepass('${roll}')">❌ Reject</button></td></tr>`;
                            }
                        });
                        tableContent += '</table>';
                        document.getElementById("issuedGatepassContent").innerHTML = tableContent;
                    }
                })
                .catch(error => console.error("Error loading issued gatepasses:", error));
        }

        function rejectGatepass(roll) {
            if (confirm("Are you sure you want to reject this gatepass?")) {
                fetch("reject_gatepass.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: `roll=${encodeURIComponent(roll)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === "success") {
                        alert(data.message);
                        loadIssuedGatepasses(); // Refresh the list
                    } else {
                        alert("❌ Error: " + data.message);
                    }
                })
                .catch(error => console.error("Error rejecting gatepass:", error));
            }
        }

        document.addEventListener("DOMContentLoaded", function () {
            document.querySelector(`button[onclick="showSection('studentList')"]`).classList.add("active");
            loadStudentList("all");
            loadGatepassRequests();
        });

        function loadGatepassRequests() {
            const timestamp = new Date().getTime();
            fetch("fetch_gatepass_requests.php?t=" + timestamp)
                .then(response => response.text())
                .then(text => {
                    if (text === "Session token expired") {
                        document.getElementById("gatepassRequests").innerHTML = "<tr><td colspan='5'>Session token expired. Please log in again.</td></tr>";
                        return;
                    }
                    try {
                        return JSON.parse(text);
                    } catch (error) {
                        console.error("Server response is not valid JSON:", text);
                        throw new Error("Invalid server response.");
                    }
                })
                .then(data => {
                    if (!data) return;
                    let tableContent = "";
                    if (data.message) {
                        tableContent = `<tr><td colspan='5'>${data.message}</td></tr>`;
                    } else {
                        data.forEach(request => {
                            tableContent += `
                                <tr>
                                    <td>${request.name}</td>
                                    <td>${request.roll}</td>
                                    <td>${request.request_time}</td>
                                    <td>${request.reason}</td>
                                    <td>
                                        <button onclick="handleGatepass('approve', '${request.roll}', '${request.request_time}')" style="background:green; color:white; padding:5px 10px; border:none; border-radius:4px; cursor:pointer;">✅ Approve</button>
                                        <button onclick="handleGatepass('reject', '${request.roll}', '${request.request_time}')" style="background:red; color:white; padding:5px 10px; border:none; border-radius:4px; cursor:pointer;">❌ Reject</button>
                                    </td>
                                </tr>`;
                        });
                    }
                    document.getElementById("gatepassRequests").innerHTML = tableContent;
                })
                .catch(error => console.error("Error loading gatepass requests:", error));
        }

        function handleGatepass(action, roll, time) {
            fetch("handle_gatepass.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: `action=${action}&roll=${roll}&time=${time}`
            })
            .then(response => response.text())
                .then(text => {
                    if (text === "Session token expired") {
                        document.getElementById("gatepassRequests").innerHTML = "<tr><td colspan='5'>Session token expired. Please log in again.</td></tr>";
                        return;
                    }
                    try {
                        return JSON.parse(text);
                    } catch (error) {
                        console.error("JSON Parse Error:", error);
                        throw new Error("Invalid server response format.");
                    }
                })
                .then(data => {
                    if (!data) return;
                    if (data.status === "success") {
                        alert(data.message);
                        loadGatepassRequests();
                    } else {
                        alert("❌ Error: " + data.message);
                    }
                })
                .catch(error => console.error("Error processing request:", error));
        }

        function updateLEDTime(roll) {
            var time = document.getElementById("led_time").value;
            if (!time) {
                alert("⚠️ Please select a time.");
                return;
            }

            var xhr = new XMLHttpRequest();
            xhr.open("POST", "update_led_time.php", true);
            xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

            xhr.onreadystatechange = function () {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    var response = JSON.parse(xhr.responseText);
                    if (response.status === "success") {
                        alert("Student gate pass updated successfully.");
                    } else {
                        alert("❌ Error: " + response.message);
                    }
                    searchStudent();
                }
            };

            xhr.send("roll=" + roll + "&led_time=" + time);
        }

        function searchStudent() {
            var roll = document.getElementById("search_roll").value;
            if (roll.trim() === "") {
                document.getElementById("searchResult").innerHTML = "<p class='error'>⚠️ Please enter a roll number!</p>";
                return;
            }

            var xhr = new XMLHttpRequest();
            xhr.open("POST", "search_student.php", true);
            xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

            xhr.onreadystatechange = function () {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    document.getElementById("searchResult").innerHTML = xhr.responseText;
                }
            };

            xhr.send("search_roll=" + roll);
        }

        function addStudent() {
            const name = document.getElementById("student_name").value;
            const roll_no = document.getElementById("roll_no").value;
            const year_ = document.getElementById("year_").value;
            const dept = document.getElementById("dept").value;
            
            if (!name || !roll_no || !year_ || !dept) {
                document.getElementById("addStudentMessage").innerHTML = "⚠️ All fields are required!";
                return;
            }

            fetch("add_student.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: `name=${name}&roll_no=${roll_no}&year_=${year_}&dept=${dept}`
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById("addStudentMessage").innerHTML = data.message;
                if (data.status === "success") {
                    document.getElementById("addStudentForm").reset();
                }
            })
            .catch(error => {
                document.getElementById("addStudentMessage").innerHTML = "❌ Error adding student.";
            });
        }
    </script>
</head>
<body>
    <a href="logout.php" class="logout">🚪 Logout</a>

    <div class="dashboard-container">
        <h2>Welcome, Warden of <?= htmlspecialchars($_SESSION['bhavan'] ?? 'Unknown') ?> 🏢</h2>

        <div class="btn-group">
            <button onclick="showSection('studentList')">📋 Student List</button>
            <button onclick="showSection('requestedGatepass')">📜 Requested Gatepass</button>
            <button onclick="showSection('issuedGatepass')">✅ Issued Gatepass</button>
        </div>

        <!-- Student List Section -->
        <div id="studentList" class="hidden">
            <h3>📌 Student List (Bhavan: <?= htmlspecialchars($_SESSION['bhavan'] ?? 'Unknown') ?>)</h3>
            
            <div class="btn-group">
                <button id="inButton" onclick="loadStudentList('in')">🟢 In (<span id="inCount">0</span>)</button>
                <button id="outButton" onclick="loadStudentList('out')">🔴 Out (<span id="outCount">0</span>)</button>
                <button id="allButton" onclick="loadStudentList('all')">📋 All (<span id="allCount">0</span>)</button>
            </div>

            <div id="studentListContent"></div>
        </div>

        <!-- Requested Gatepass Section -->
        <div id="requestedGatepass" class="hidden">
            <h3>📌 Requested Gatepass</h3>
            <div id="requestedGatepassContent">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Roll No</th>
                            <th>Gatepass Time</th>
                            <th>Reason</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="gatepassRequests">
                        <tr><td colspan="5">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Issued Gatepass Section -->
        <div id="issuedGatepass" class="hidden">
            <h3>✅ Issued Gatepass (Bhavan: <?= htmlspecialchars($_SESSION['bhavan'] ?? 'Unknown') ?>)</h3>
            <div class="btn-group">
                <button onclick="loadIssuedGatepasses()">🔄 Refresh (<span id="issuedCount">0</span>)</button>
            </div>
            <div id="issuedGatepassContent"></div>
        </div>
    </div>
</body>
</html>