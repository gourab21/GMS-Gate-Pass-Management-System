<?php
$servername = "localhost";
$username = "root"; 
$password = ""; 
$dbname = "rfid_system"; 

$conn = new mysqli($servername, $username, $password, $dbname);

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
            margin: 20px;
        }
        table {
            margin: 20px auto;
            border-collapse: collapse;
            width: 60%;
        }
        table, th, td {
            border: 1px solid black;
            padding: 10px;
            text-align: center;
        }
        th {
            background-color: #f2f2f2;
        }
        .container {
            max-width: 600px;
            margin: auto;
        }
        .buttons {
            margin-top: 10px;
        }
        .buttons button {
            padding: 8px 15px;
            margin: 5px;
            font-size: 16px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Search Student by Roll Number</h2>
        <form method="POST" id="searchForm">
            <label for="roll">Enter Roll Number:</label>
            <input type="number" id="roll" name="roll" required placeholder="Enter Roll Number" title="Enter Roll Number">
            <div class="buttons">
                <button type="submit">Fetch Data</button>
                <button type="button" onclick="resetForm()">Reset</button>
            </div>
        </form>

        <?php if ($data): ?>
            <?php if (is_array($data)): ?>
                <h3>Details for Roll Number: <?= htmlspecialchars($data['roll']) ?></h3>
                <table>
                    <tr>
                        <th>RFID</th>
                        <th>Bhavan</th>
                        <th>Roll</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Green LED</th>
                    </tr>
                    <tr>
                        <td><?= htmlspecialchars($data['rfid']) ?></td>
                        <td><?= htmlspecialchars($data['bhavan']) ?></td>
                        <td><?= htmlspecialchars($data['roll']) ?></td>
                        <td><?= htmlspecialchars($data['name']) ?></td>
                        <td><?= htmlspecialchars($data['dept']) ?></td>
                        <td><?= htmlspecialchars($data['green_led']) ?></td>
                    </tr>
                </table>
            <?php else: ?>
                <p><?= htmlspecialchars($data) ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        function resetForm() {
            document.getElementById("searchForm").reset();
            window.location.href = window.location.pathname;
        }
    </script>
</body>
</html>
