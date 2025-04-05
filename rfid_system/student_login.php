<?php
session_start();
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rfid_system";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed.");
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $roll = trim($_POST['roll']);
    $password = trim($_POST['password']);

    $sql = "SELECT password FROM student_users WHERE roll = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $roll);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['roll'] = $roll;
            header("Location: student_dashboard.php");
            exit();
        } else {
            $error = "❌ Invalid Credentials!";
        }
    } else {
        $error = "❌ Roll number not found!";
    }
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Student Login</title>
    <style>
        * { font-family: Arial, sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            background: linear-gradient(135deg, #74b9ff, #0984e3); 
            padding: 20px; 
            position: relative; /* Added for positioning the home button */
        }
        .container { 
            background: rgba(255, 255, 255, 0.95); 
            padding: 30px; 
            border-radius: 12px; 
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15); 
            max-width: 400px; 
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
        input { 
            width: 90%; 
            padding: 12px; 
            margin: 10px 0; 
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
        button { 
            width: 100%; 
            padding: 12px; 
            background: linear-gradient(to right, #0984e3, #0659a7); 
            color: white; 
            border: none; 
            border-radius: 6px; 
            font-size: 16px; 
            cursor: pointer; 
            transition: background 0.3s, transform 0.2s; 
        }
        button:hover { 
            background: linear-gradient(to right, #0659a7, #043f7a); 
            transform: translateY(-2px); 
        }
        .error { 
            color: #e84118; 
            margin-top: 10px; 
            font-size: 14px; 
            font-weight: 500; 
        }
        .home-btn {
            position: absolute;
            top: 15px;
            left: 15px;
            background: #ffffff;
            padding: 8px 15px;
            color: #0984e3;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: 0.3s;
        }
        .home-btn:hover {
            background: #f0f0f0;
            color: #0659a7;
        }

        /* Mobile view adjustments */
        @media (max-width: 600px) {
            body { padding: 10px; }
            .container { padding: 20px; max-width: 100%; }
            h2 { font-size: 24px; }
            input { width: 100%; padding: 10px; font-size: 14px; }
            button { padding: 12px; font-size: 14px; }
            .error { font-size: 12px; }
            .home-btn {
                top: 10px;
                left: 10px;
                padding: 6px 12px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <a href="../index2.html" class="home-btn">🏠 Home</a>
    <div class="container">
        <h2>📌 Student Login</h2>
        <form method="POST">
            <input type="text" name="roll" placeholder="Roll No" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
        <p class="error"><?= $error ?></p>
    </div>
</body>
</html>