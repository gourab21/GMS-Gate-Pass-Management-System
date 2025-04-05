<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rfid_system";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $roll = trim($_POST['roll']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash password

    $sql = "INSERT INTO student_users (roll, password) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $roll, $password);

    if ($stmt->execute()) {
        echo "Student Registered Successfully!";
    } else {
        echo "Error: " . $conn->error;
    }

    $stmt->close();
    $conn->close();
}
?>
<form method="POST">
    <input type="text" name="roll" placeholder="Roll No" required>
    <input type="password" name="password" placeholder="Password" required>
    <button type="submit">Register Student</button>
</form>
