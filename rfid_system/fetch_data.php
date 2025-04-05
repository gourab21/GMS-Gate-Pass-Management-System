<?php
$servername = "localhost";
$username = "root"; // Default XAMPP username
$password = ""; // Default XAMPP password is empty
$dbname = "rfid_system"; // Your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$data = null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['roll'])) {
    $roll = intval($_POST['roll']);
    
    $sql = "SELECT * FROM vidyamandira WHERE roll = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $roll);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
    } else {
        $data = "No data found for Roll Number: " . $roll;
    }

    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fetch RFID Data</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            margin: 50px;
        }
        table {
            margin: 20px auto;
            border-collapse: collapse;
            width: 60%;
        }
        table, th, td {
            border: 1px solid black;
            padding: 10px;
        }
        th {
            background-color: #f2f2f2;
        }
        .container {
            max-width: 600px;
            margin: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Search Student by Roll Number</h2>
        <form method="POST">
            <label for="roll">Enter Roll Number:</label>
            <input type="number" id="roll" name="roll" required>
            <button type="submit">Fetch Data</button>
        </form>

        <?php if ($data): ?>
            <?php if (is_array($data)): ?>
                <table>
                    <tr>
                        <th>RFID</th>
                        <th>Bhavan</th>
                        <th>Roll</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Red LED</th>
                        <th>Green LED</th>
                    </tr>
                    <tr>
                        <td><?= htmlspecialchars($data['rfid']) ?></td>
                        <td><?= htmlspecialchars($data['bhavan']) ?></td>
                        <td><?= htmlspecialchars($data['roll']) ?></td>
                        <td><?= htmlspecialchars($data['name']) ?></td>
                        <td><?= htmlspecialchars($data['dept']) ?></td>
                        <td><?= htmlspecialchars($data['red_led']) ?></td>
                        <td><?= htmlspecialchars($data['green_led']) ?></td>
                    </tr>
                </table>
            <?php else: ?>
                <p><?= htmlspecialchars($data) ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
