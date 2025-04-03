<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$database = "rfid_system";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bhavan = trim($_POST['bhavan']);
    $pswd = trim($_POST['password']);

    // ✅ Master Login from Database
    if ($bhavan === "master") {
        $query = "SELECT * FROM wardens WHERE bhavan = ? AND password = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $bhavan, $pswd);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $_SESSION['master'] = true;
            header("Location: master_dashboard.php");
            exit();
        } else {
            $error = "Invalid Master Credentials!";
        }
        $stmt->close();
    }

    // ✅ Warden Login (including vidya)
    else {
        $query = "SELECT * FROM wardens WHERE bhavan = ? AND password = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $bhavan, $pswd);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $_SESSION['bhavan'] = $bhavan;
            header("Location: dashboard.php");
            exit();
        } 
        // ✅ Principal Login
        if ($bhavan === "principal" && $pswd === "123") {
            $_SESSION['principal'] = true;
            header("Location: principal_dashboard.php");
            exit();
        }
        
        else {
            $error = "Invalid User ID or Password!";
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background: linear-gradient(135deg, #74b9ff, #0984e3);
            padding: 20px;
            position: relative; /* Added for positioning the home button */
        }

        .login-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        h2 {
            margin-bottom: 20px;
            color: #333;
        }

        .error {
            color: red;
            margin-bottom: 10px;
        }

        .input-group {
            margin-bottom: 15px;
            text-align: left;
        }

        label {
            font-size: 14px;
            font-weight: bold;
            color: #444;
        }

        input {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }

        button {
            width: 100%;
            padding: 12px;
            border: none;
            background: #0984e3;
            color: white;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            transition: 0.3s;
        }

        button:hover {
            background: #0659a7;
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

        /* Responsive */
        @media (max-width: 480px) {
            .login-container {
                padding: 20px;
            }
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
    <div class="login-container">
        <h2>🔒 Login</h2>
        <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
        
        <form method="POST">
            <div class="input-group">
                <label for="bhavan">User ID:</label>
                <input type="text" name="bhavan" id="bhavan" required>
            </div>
            
            <div class="input-group">
                <label for="password">Password:</label>
                <input type="password" name="password" id="password" required>
            </div>
            
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>