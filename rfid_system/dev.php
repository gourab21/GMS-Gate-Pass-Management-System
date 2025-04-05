<?php
session_start();
if (!isset($_SESSION['master'])) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$database = "rfid_system";
$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['query'])) {
    $query = $_POST['query'];
    if ($conn->query($query) === TRUE) {
        $message = "Query executed successfully.";
    } else {
        $message = "Error executing query: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Dashboard</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; margin: 20px; }
                table { width: 80%; margin: auto; border-collapse: collapse; }
                th, td { padding: 10px; border: 1px solid #ddd; }
                th { background-color: #007BFF; color: white; }
                a { text-decoration: none; color: #007BFF; }
                a:hover { text-decoration: underline; }
                .logout { position: absolute; top: 10px; right: 10px; background: red; color: white; padding: 10px; border-radius: 5px; }
                textarea { width: 80%; height: 100px; }
                button { margin-top: 10px; padding: 10px; background: #007BFF; color: white; border: none; cursor: pointer; }
            </style>
</head>
<body>
    <a class="logout" href="?logout=true">Logout</a>
    <h2>Master Dashboard</h2>
    
    <?php if (isset($message)) echo "<p><strong>$message</strong></p>"; ?>
    
    <h3>Execute SQL Query</h3>
    <form method="POST">
        <textarea name="query" placeholder="Enter your SQL query here..."></textarea><br>
        <button type="submit">Execute</button>
    </form>

    <h3>Tables in RFID System</h3>
    
    <?php
    $tables_result = $conn->query("SHOW TABLES");
    ?>
    <table>
        <tr>
            <th>Table Name</th>
        </tr>
        <?php while ($table = $tables_result->fetch_array()) { ?>
            <tr>
                <td><a href="?table=<?php echo urlencode($table[0]); ?>"> <?php echo htmlspecialchars($table[0]); ?> </a></td>
            </tr>
        <?php } ?>
    </table>

    <?php 
    if (isset($_GET['table'])) {
        $selected_table = $_GET['table'];
        echo "<h3>Contents of Table: " . htmlspecialchars($selected_table) . "</h3>";
        $data_result = $conn->query("SELECT * FROM `$selected_table` LIMIT 50");
        
        if ($data_result && $data_result->num_rows > 0) {
            echo "<table border='1'><tr>";
            while ($field = $data_result->fetch_field()) {
                echo "<th>" . htmlspecialchars($field->name) . "</th>";
            }
            echo "</tr>";
            
            while ($row = $data_result->fetch_assoc()) {
                echo "<tr>";
                foreach ($row as $value) {
                    echo "<td>" . htmlspecialchars($value) . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No data found in this table.</p>";
        }
    }
    ?>
</body>
</html>
<?php $conn->close(); ?>