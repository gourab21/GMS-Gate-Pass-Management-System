<?php
session_start();
if (!isset($_SESSION['roll'])) {
    header("Location: student_login.php");
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

$roll = $_SESSION['roll'];
$sql = "SELECT v.roll, v.name, v.year_, v.dept, v.green_led, g.request_time, g.status 
        FROM vidyamandira v 
        LEFT JOIN gatepass_requests g ON v.roll = g.roll 
        WHERE v.roll = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $roll);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $hasActiveRequest = ($row['request_time'] !== NULL && $row['status'] !== 'Rejected');

    // Check green_led value and convert to 12-hour format
    if ($row['green_led'] !== '23:59' && !empty($row['green_led'])) {
        $gatePassTime = date("h:i A", strtotime($row['green_led'])); // Convert to 12-hour format
        $gatePassStatus = "✅ Gatepass Issued at " . $gatePassTime;
    } else {
        $gatePassStatus = $hasActiveRequest 
            ? "🕒 Requested at " . $row['request_time'] . " (Status: " . $row['status'] . ")" 
            : "❌ Not Issued";
    }
} else {
    echo "Student not found.";
    exit();
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Student Dashboard</title>
    <!-- Flatpickr CSS and JS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
* { font-family: Arial, sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
body { 
    display: flex; 
    justify-content: center; 
    align-items: center; 
    min-height: 100vh; 
    background: linear-gradient(135deg, #74b9ff, #0984e3); 
    padding: 20px; 
}
.container { 
    background: rgba(255, 255, 255, 0.95); 
    padding: 30px; 
    border-radius: 12px; 
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15); 
    max-width: 500px; /* Default for desktop */
    width: 100%; 
    text-align: center; 
    border: 1px solid rgba(255, 255, 255, 0.3); 
}
h2 { 
    color: #333; 
    font-size: 28px; 
    margin-bottom: 20px; 
    font-weight: 600; 
    letter-spacing: 1px; 
}
h3 { 
    color: #444; 
    font-size: 20px; 
    margin: 15px 0; 
    font-weight: 500; 
}
p { 
    color: #555; 
    font-size: 16px; 
    margin: 8px 0; 
}
button { 
    padding: 10px 20px; 
    background: linear-gradient(to right, #0984e3, #0659a7); 
    color: white; 
    border: none; 
    border-radius: 6px; 
    cursor: pointer; 
    margin: 5px; 
    font-size: 16px; 
    transition: background 0.3s, transform 0.2s; 
}
button:hover { 
    background: linear-gradient(to right, #0659a7, #043f7a); 
    transform: translateY(-2px); 
}
.logout { 
    background: linear-gradient(to right, #ff4757, #e84118); 
}
.logout:hover { 
    background: linear-gradient(to right, #e84118, #c0392b); 
}
.modal-overlay {
    display: none; 
    position: fixed; 
    top: 0; 
    left: 0; 
    width: 100%; 
    height: 100%; 
    background: rgba(0, 0, 0, 0.6); 
    justify-content: center; 
    align-items: center; 
    z-index: 1000; 
}
.modal-content {
    background: rgba(255, 255, 255, 0.95); 
    padding: 25px; 
    border-radius: 12px; 
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3); 
    text-align: center; 
    width: 350px; /* Default for desktop */
    position: relative; 
    animation: fadeIn 0.3s ease-in-out; 
    border: 1px solid rgba(255, 255, 255, 0.3); 
}
.close-btn {
    position: absolute; 
    top: 10px; 
    right: 15px; 
    font-size: 20px; 
    cursor: pointer; 
    color: #ff4757; 
    transition: color 0.3s; 
}
.close-btn:hover { 
    color: #e84118; 
}
.modal-content input, 
.modal-content textarea {
    width: 100%; 
    padding: 12px; 
    margin: 10px 0; 
    border: 1px solid #ccc; 
    border-radius: 6px; 
    font-size: 16px; 
    background: #f9f9f9; 
    transition: border-color 0.3s; 
}
.modal-content input:focus, 
.modal-content textarea:focus { 
    border-color: #0984e3; 
    outline: none; 
    box-shadow: 0 0 5px rgba(9, 132, 227, 0.3); 
}
.modal-content button { 
    width: 100%; 
    padding: 12px; 
}
@keyframes fadeIn { 
    from { opacity: 0; transform: scale(0.9); } 
    to { opacity: 1; transform: scale(1); } 
}
/* Optional Flatpickr styling */
.flatpickr-time input {
    font-size: 16px; 
    padding: 12px; 
    border: 1px solid #ccc; 
    border-radius: 6px; 
    background: #f9f9f9; 
}

/* Mobile view adjustments - Full Screen Card */
@media (max-width: 600px) {
    body { 
        padding: 0; /* Remove padding to use full screen */
        margin: 0; /* Ensure no margin interferes */
        min-height: 100vh; /* Full height */
        overflow-x: hidden; /* Prevent horizontal scroll */
    }
    .container { 
        padding: 40px; /* Large internal padding */
        max-width: 100%; /* Full width */
        width: 100vw; /* Full viewport width */
        height: 100vh; /* Full viewport height */
        border-radius: 0; /* Remove rounded corners for full-screen effect */
        box-shadow: none; /* Remove shadow for seamless look */
        border: none; /* Remove border */
        margin: 0; /* No margins */
        display: flex; /* Ensure content is centered */
        flex-direction: column; /* Stack content vertically */
        justify-content: center; /* Center vertically */
        align-items: center; /* Center horizontally */
    }
    h2 { 
        font-size: 36px; /* Large title */
        margin-bottom: 25px; 
    }
    h3 { 
        font-size: 28px; /* Large subtitle */
        margin: 20px 0; 
    }
    p { 
        font-size: 20px; /* Large text */
        margin: 12px 0; 
    }
    button { 
        padding: 15px 30px; /* Large buttons */
        font-size: 20px; /* Large button text */
        border-radius: 8px; 
        margin: 10px; 
    }
    .modal-content { 
        padding: 40px; /* Large padding */
        width: 90%; /* Slightly less than full width for modals */
        max-width: 500px; /* Constrain modal size */
        border-radius: 12px; /* Keep rounded corners for modals */
    }
    .modal-content input, 
    .modal-content textarea { 
        padding: 15px; 
        font-size: 20px; 
        margin: 15px 0; 
        border-radius: 8px; 
    }
    .modal-content button { 
        padding: 15px; 
        font-size: 20px; 
    }
    .close-btn { 
        font-size: 30px; 
        top: 15px; 
        right: 20px; 
    }
    .flatpickr-time input { 
        padding: 15px; 
        font-size: 20px; 
        border-radius: 8px; 
    }
}
</style>
</head>
<body>

<div class="container">
    <h2>📌 Student Dashboard</h2>
    <button onclick="location.reload()">🏠 Home (Refresh)</button>
    <button onclick="openModal('changePassModal')">🔑 Change Password</button>
    <a href="student_logout.php"><button class="logout">🚪 Logout</button></a>

    <h3>👤 Student Details</h3>
    <p><strong>Roll:</strong> <?= htmlspecialchars($row['roll']) ?></p>
    <p><strong>Name:</strong> <?= htmlspecialchars($row['name']) ?></p>
    <p><strong>Year:</strong> <?= htmlspecialchars($row['year_']) ?></p>
    <p><strong>Department:</strong> <?= htmlspecialchars($row['dept']) ?></p>
    <p><strong>Gate Pass Status:</strong> <?= $gatePassStatus ?></p>

    <?php if (!$hasActiveRequest && ($row['green_led'] === '23:59' || empty($row['green_led']))) { ?>
        <button onclick="openModal('requestGatePassModal')">📝 Request Gate Pass</button>
    <?php } elseif ($hasActiveRequest && ($row['green_led'] === '23:59' || empty($row['green_led']))) { ?>
        <button onclick="openModal('modifyGatePassModal')">✏ Modify Request</button>
        <button onclick="openModal('deleteRequestModal')">❌ Delete Request</button>
    <?php } ?>
</div>

<!-- 🚀 Request Gate Pass Modal -->
<div id="requestGatePassModal" class="modal-overlay">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal('requestGatePassModal', true)">✖</span>
        <h3>📝 Request Gate Pass</h3>
        <form id="gatePassForm">
            <input type="text" id="request_time" class="timepicker" required placeholder="Select Time">
            <textarea id="reason" placeholder="Enter Reason" required></textarea>
            <button type="submit">Submit Request</button>
        </form>
    </div>
</div>

<!-- 🔑 Change Password Modal -->
<div id="changePassModal" class="modal-overlay">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal('changePassModal', true)">✖</span>
        <h3>🔐 Change Password</h3>
        <form id="changePasswordForm">
            <input type="password" id="new_password" placeholder="New Password" required>
            <input type="password" id="confirm_password" placeholder="Confirm Password" required>
            <button type="submit">Update Password</button>
        </form>
    </div>
</div>

<!-- ✏ Modify Gate Pass Modal -->
<div id="modifyGatePassModal" class="modal-overlay">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal('modifyGatePassModal', true)">✖</span>
        <h3>✏ Modify Gate Pass</h3>
        <form id="modifyGatePassForm">
            <input type="text" id="modify_request_time" class="timepicker" required placeholder="Select Time">
            <textarea id="modify_reason" placeholder="Enter New Reason" required></textarea>
            <button type="submit">Update Request</button>
        </form>
    </div>
</div>

<!-- ❌ Delete Request Modal -->
<div id="deleteRequestModal" class="modal-overlay">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal('deleteRequestModal', true)">✖</span>
        <h3>⚠️ Confirm Deletion</h3>
        <p>Are you sure you want to delete this request?</p>
        <button id="confirmDeleteBtn">✅ Yes, Delete</button>
        <button onclick="closeModal('deleteRequestModal', true)">❌ Cancel</button>
    </div>
</div>

<script>
// Initialize Flatpickr for time inputs with AM/PM format
document.addEventListener("DOMContentLoaded", function() {
    flatpickr(".timepicker", {
        enableTime: true,
        noCalendar: true,
        dateFormat: "h:i K", // 12-hour format with AM/PM (e.g., "2:30 PM")
        time_24hr: false,    // Switch to 12-hour clock
        minuteIncrement: 1   // Minute steps
    });
});

// Open Modal
function openModal(id) {
    document.getElementById(id).style.display = "flex";
}

// Close Modal & Refresh Page
function closeModal(id, refresh = false) {
    document.getElementById(id).style.display = "none";
    if (refresh) location.reload();
}

// Change Password Form Submission
document.getElementById("changePasswordForm").onsubmit = function(event) {
    event.preventDefault();

    let newPassword = document.getElementById("new_password").value;
    let confirmPassword = document.getElementById("confirm_password").value;

    if (newPassword !== confirmPassword) {
        alert("⚠ Passwords do not match!");
        return;
    }

    let xhr = new XMLHttpRequest();
    xhr.open("POST", "student_update_password.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    xhr.onload = function() {
        let response = JSON.parse(xhr.responseText);
        alert(response.message);

        if (response.status === "success") {
            closeModal("changePassModal", true);
        }
    };

    xhr.send("new_password=" + encodeURIComponent(newPassword));
};

// Request Gate Pass Form Submission
document.getElementById("gatePassForm").onsubmit = function(event) {
    event.preventDefault();
    let requestTime = document.getElementById("request_time").value;
    let reason = document.getElementById("reason").value;

    let xhr = new XMLHttpRequest();
    xhr.open("POST", "student_request_gatepass.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    xhr.onload = function() {
        let response = JSON.parse(xhr.responseText);
        if (response.status === "success") {
            alert(response.message);
            location.reload();
        } else {
            alert(response.message);
        }
    };

    xhr.send("request_time=" + encodeURIComponent(requestTime) + "&reason=" + encodeURIComponent(reason));
};

// Modify Gate Pass Form Submission
document.getElementById("modifyGatePassForm").onsubmit = function(event) {
    event.preventDefault();
    let requestTime = document.getElementById("modify_request_time").value;
    let reason = document.getElementById("modify_reason").value;

    let xhr = new XMLHttpRequest();
    xhr.open("POST", "student_modify_gatepass_request.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    xhr.onload = function() {
        if (xhr.responseText.trim() === "success") {
            closeModal("modifyGatePassModal", true);
        } else {
            alert("Failed to update request.");
        }
    };

    xhr.send("request_time=" + encodeURIComponent(requestTime) + "&reason=" + encodeURIComponent(reason));
};

// Delete Request Confirmation
document.getElementById("confirmDeleteBtn").onclick = function() {
    let xhr = new XMLHttpRequest();
    xhr.open("POST", "student_delete_gatepass_request.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    xhr.onload = function() {
        if (xhr.responseText.trim() === "success") {
            closeModal("deleteRequestModal", true);
        } else {
            alert("Failed to delete request.");
        }
    };

    xhr.send();
};
</script>

</body>
</html>