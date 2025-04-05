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

// Handle record updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    if (isset($_POST['roll'], $_POST['table'], $_POST['column'], $_POST['value'])) {
        $table = $_POST['table'];
        $roll = $_POST['roll'];
        $column = $_POST['column'];
        $value = $_POST['value'];
        $query = "UPDATE `$table` SET `$column` = ? WHERE roll = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $value, $roll);
        $stmt->execute();
        $stmt->close();
        $message = "Record updated successfully.";
    }
}

// Handle record deletion
// Handle record deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    if (isset($_POST['roll'], $_POST['table'])) {
        $table = $_POST['table'];
        $roll = $_POST['roll'];

        $conn->begin_transaction();

        try {
            if ($table === 'vidyamandira') {
                // Get the RFID before deletion for Firebase
                $stmt_rfid = $conn->prepare("SELECT rfid FROM vidyamandira WHERE roll = ?");
                $stmt_rfid->bind_param("s", $roll);
                $stmt_rfid->execute();
                $result = $stmt_rfid->get_result();
                $rfid_row = $result->fetch_assoc();
                $rfid = $rfid_row ? $rfid_row['rfid'] : null;
                $stmt_rfid->close();

                if (!$rfid) {
                    throw new Exception("RFID not found for roll: $roll");
                }

                // Delete from vidyamandira
                $query1 = "DELETE FROM vidyamandira WHERE roll = ?";
                $stmt1 = $conn->prepare($query1);
                $stmt1->bind_param("s", $roll);
                $stmt1->execute();
                $stmt1->close();

                // Delete from student_users
                $query2 = "DELETE FROM student_users WHERE roll = ?";
                $stmt2 = $conn->prepare($query2);
                $stmt2->bind_param("s", $roll);
                $stmt2->execute();
                $stmt2->close();

                // Delete from Firebase
                $firebase_url = "https://gatepass-3e72f-default-rtdb.asia-southeast1.firebasedatabase.app/students/$rfid.json"; // Replace with your Firebase URL
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $firebase_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");

                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                if ($http_code === 200) {
                    $message = "Record deleted successfully from all relevant tables and Firebase.";
                } else {
                    throw new Exception("Error deleting from Firebase: " . $response);
                }

                curl_close($ch);
            } else {
                $query = "DELETE FROM `$table` WHERE roll = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("s", $roll);
                $stmt->execute();
                $stmt->close();
                $message = "Record deleted successfully from $table.";
            }

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error deleting record: " . $e->getMessage();
        }
    }
}

// Handle password reset for student_users
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    if (isset($_POST['roll'], $_POST['table']) && $_POST['table'] === 'student_users') {
        $roll = $_POST['roll'];
        $new_password = $roll . "rkmv"; // Default password: roll + "rkmv"
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $query = "UPDATE student_users SET password = ? WHERE roll = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $hashed_password, $roll);
        if ($stmt->execute()) {
            $message = "Password reset successfully for roll: $roll.";
        } else {
            $message = "Error resetting password: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle password reset for wardens
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    if (isset($_POST['bhavan'], $_POST['table']) && $_POST['table'] === 'wardens') {
        $bhavan = $_POST['bhavan'];
        $new_password = $bhavan . "rkmv"; // Default password: bhavan + "rkmv" (unencrypted)

        $query = "UPDATE wardens SET password = ? WHERE bhavan = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $new_password, $bhavan);
        if ($stmt->execute()) {
            $message = "Password reset successfully for bhavan: $bhavan.";
        } else {
            $message = "Error resetting password: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle adding a new student
// Handle adding a new student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    if (isset($_POST['rfid'], $_POST['name'], $_POST['roll'], $_POST['bhavan'], $_POST['year_'], $_POST['dept'])) {
        $rfid = $_POST['rfid'];
        $name = $_POST['name'];
        $roll = $_POST['roll'];
        $bhavan = $_POST['bhavan'];
        $year_ = $_POST['year_'];
        $dept = $_POST['dept'];
        $green_led = "23:59";
        $out = 0;

        $stmt_check = $conn->prepare("SELECT * FROM vidyamandira WHERE roll = ?");
        $stmt_check->bind_param("s", $roll);
        $stmt_check->execute();
        $stmt_check->store_result();
        
        if ($stmt_check->num_rows > 0) {
            echo "<script>alert('Student already exists');</script>";
        } else {
            // Insert into MySQL (vidyamandira table)
            $stmt = $conn->prepare("INSERT INTO vidyamandira (rfid, name, roll, bhavan, year_, dept, green_led, `Out`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssi", $rfid, $name, $roll, $bhavan, $year_, $dept, $green_led, $out);
            $mysql_success = $stmt->execute();
            if ($mysql_success) {
                echo "<script>alert('Student added successfully to MySQL!');</script>";

                // Add to Firebase
                $firebase_url = "https://gatepass-3e72f-default-rtdb.asia-southeast1.firebasedatabase.app/students/$rfid.json"; // Replace with your Firebase URL
                $firebase_data = [
                    "Out" => 0,
                    "green_led" => "23:59"
                ];
                $firebase_json = json_encode($firebase_data);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $firebase_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT"); // Use PUT to set the data
                curl_setopt($ch, CURLOPT_POSTFIELDS, $firebase_json);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Content-Type: application/json",
                    "Content-Length: " . strlen($firebase_json)
                ]);

                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                if ($http_code === 200) {
                    echo "<script>alert('Student added successfully to Firebase!');</script>";
                } else {
                    echo "<script>alert('Error adding student to Firebase: " . addslashes($response) . "');</script>";
                }

                curl_close($ch);
            } else {
                echo "<script>alert('Error adding student to MySQL: " . addslashes($stmt->error) . "');</script>";
            }
            $stmt->close();

            // Insert into student_users table
            $hashed_password = password_hash($roll, PASSWORD_DEFAULT);
            $stmt2 = $conn->prepare("INSERT INTO student_users (roll, password) VALUES (?, ?)");
            $stmt2->bind_param("ss", $roll, $hashed_password);
            if ($stmt2->execute()) {
                $Ble = 1; // Fixed the typo here (removed space)
            } else {
                echo "<script>alert('Error creating student user: " . addslashes($stmt2->error) . "');</script>";
            }
            $stmt2->close();
        }
        
        $stmt_check->close();
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
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        margin: 0;
        padding: 20px;
        background-color: #f5f6fa;
        color: #333;
    }
    table {
        width: 90%;
        margin: 20px auto;
        border-collapse: separate;
        border-spacing: 0;
        background-color: white;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        border-radius: 8px;
        overflow: hidden;
    }
    th, td {
        padding: 15px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }
    th {
        background-color: #2c3e50;
        color: white;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 14px;
    }
    td {
        font-size: 14px;
    }
    tr:hover {
        background-color: #f8f9fd;
    }
    a {
        text-decoration: none;
        color: #3498db;
        transition: color 0.3s ease;
    }
    a:hover {
        color: #2980b9;
        text-decoration: underline;
    }
    .logout {
        position: fixed;
        top: 20px;
        right: 20px;
        background: #e74c3c;
        color: white;
        padding: 10px 20px;
        border-radius: 25px;
        font-weight: 500;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        transition: transform 0.2s ease;
    }
    .logout:hover {
        transform: translateY(-2px);
    }
    input, button, select {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        margin: 5px;
    }
    button {
        background-color: #3498db;
        color: white;
        border: none;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }
    button:hover {
        background-color: #2980b9;
    }
    h2, h3 {
        color: #2c3e50;
        margin: 20px 0;
    }
    #addStudentForm {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        width: 50%;
        margin: 20px auto;
    }
    label {
        display: block;
        margin: 10px 0;
        color: #666;
    }
    .filter-select {
        width: 100%;
        background-color: #34495e;
        color: white;
        border: none;
        padding: 5px;
    }
</style>
    <script>
        function confirmDelete(roll, name) {
            return confirm("Are you sure you want to delete the student: " + name + " (Roll: " + roll + ")? This will remove their records from all relevant tables.");
        }
    </script>
</head>
<body>
    <a class="logout" href="?logout=true">Logout</a>
    <h2>Master Dashboard</h2>
    
    <?php if (isset($message)) echo "<p><strong>$message</strong></p>"; ?>
    
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

        if ($selected_table === 'vidyamandira') {
            echo '<button onclick="document.getElementById(\'addStudentForm\').style.display=\'block\'">Add Student</button>';

            $bhavan_options = [];
            $result = $conn->query("SELECT bhavan FROM wardens WHERE bhavan NOT IN ('master', 'principal')");
            while ($row = $result->fetch_assoc()) {
                $bhavan_options[] = $row['bhavan'];
            }
            
            echo '<div id="addStudentForm" style="display:none;">
                    <h3>Add New Student</h3>
                    <form method="POST">
                        <input type="hidden" name="table" value="vidyamandira">
                        <label>RFID: <input type="text" name="rfid" required></label><br>
                        <label>Name: <input type="text" name="name" required></label><br>
                        <label>Roll: <input type="text" name="roll" required></label><br>
                        <label>Bhavan:
                            <select name="bhavan" required>
                                <option value="">Select Bhavan</option>';
                                foreach ($bhavan_options as $bhavan) { 
                                    echo '<option value="' . htmlspecialchars($bhavan) . '">' . htmlspecialchars($bhavan) . '</option>';
                                }
            echo '      </select>
                        </label><br>
                        <label>Year: <input type="text" name="year_" required></label><br>
                        <label>Dept: <input type="text" name="dept" required></label><br>
                        <button type="submit" name="add_student">Add Student</button>
                    </form>
                </div>';

            // Get filter values from GET parameters
            $bhavan_filter = isset($_GET['bhavan']) ? $_GET['bhavan'] : '';
            $dept_filter = isset($_GET['dept']) ? $_GET['dept'] : '';
            $year_filter = isset($_GET['year_']) ? $_GET['year_'] : '';
            $out_filter = isset($_GET['Out']) ? $_GET['Out'] : '';

            // Build the filtered query
            $query = "SELECT * FROM `$selected_table` WHERE 1=1";
            if ($bhavan_filter) {
                $query .= " AND bhavan = '" . $conn->real_escape_string($bhavan_filter) . "'";
            }
            if ($dept_filter) {
                $query .= " AND dept = '" . $conn->real_escape_string($dept_filter) . "'";
            }
            if ($year_filter) {
                $query .= " AND year_ = '" . $conn->real_escape_string($year_filter) . "'";
            }
            if ($out_filter !== '') { // Only apply if not "All"
                $query .= " AND `Out` = '" . $conn->real_escape_string($out_filter) . "'";
            }
            $query .= " LIMIT 50";
            $data_result = $conn->query($query);

            // Get unique values for filters
            $bhavans = $conn->query("SELECT DISTINCT bhavan FROM vidyamandira ORDER BY bhavan");
            $depts = $conn->query("SELECT DISTINCT dept FROM vidyamandira ORDER BY dept");
            $years = $conn->query("SELECT DISTINCT year_ FROM vidyamandira ORDER BY year_");
            $outs = $conn->query("SELECT DISTINCT `Out` FROM vidyamandira ORDER BY `Out`");
        } else {
            $data_result = $conn->query("SELECT * FROM `$selected_table` LIMIT 50");
        }
        
        if ($data_result && $data_result->num_rows > 0) {
            echo "<table border='1'><tr>";
            $columns = [];
            while ($field = $data_result->fetch_field()) {
                $column_name = $field->name;
                $columns[] = $column_name;

                // Add filters to specific columns for vidyamandira
                if ($selected_table === 'vidyamandira' && in_array($column_name, ['bhavan', 'dept', 'year_', 'Out'])) {
                    echo "<th>";
                    echo htmlspecialchars($column_name) . "<br>";
                    echo "<form method='GET' style='margin: 0;'>";
                    echo "<input type='hidden' name='table' value='vidyamandira'>";
                    
                    // Preserve other filters
                    if ($column_name !== 'bhavan') echo "<input type='hidden' name='bhavan' value='$bhavan_filter'>";
                    if ($column_name !== 'dept') echo "<input type='hidden' name='dept' value='$dept_filter'>";
                    if ($column_name !== 'year_') echo "<input type='hidden' name='year_' value='$year_filter'>";
                    if ($column_name !== 'Out') echo "<input type='hidden' name='Out' value='$out_filter'>";

                    echo "<select name='$column_name' class='filter-select' onchange='this.form.submit()'>";
                    echo "<option value=''>All</option>";

                    if ($column_name === 'bhavan') {
                        while ($row = $bhavans->fetch_assoc()) {
                            $selected = ($bhavan_filter === $row['bhavan']) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($row['bhavan']) . "' $selected>" . htmlspecialchars($row['bhavan']) . "</option>";
                        }
                    } elseif ($column_name === 'dept') {
                        while ($row = $depts->fetch_assoc()) {
                            $selected = ($dept_filter === $row['dept']) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($row['dept']) . "' $selected>" . htmlspecialchars($row['dept']) . "</option>";
                        }
                    } elseif ($column_name === 'year_') {
                        while ($row = $years->fetch_assoc()) {
                            $selected = ($year_filter === $row['year_']) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($row['year_']) . "' $selected>" . htmlspecialchars($row['year_']) . "</option>";
                        }
                    } elseif ($column_name === 'Out') {
                        while ($row = $outs->fetch_assoc()) {
                            $selected = ($out_filter === (string)$row['Out']) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($row['Out']) . "' $selected>" . htmlspecialchars($row['Out']) . "</option>";
                        }
                    }
                    echo "</select>";
                    echo "</form>";
                    echo "</th>";
                } else {
                    echo "<th>" . htmlspecialchars($column_name) . "</th>";
                }
            }
            echo "<th>Actions</th></tr>";
            
            while ($row = $data_result->fetch_assoc()) {
                echo "<tr>";
                foreach ($row as $key => $value) {
                    echo "<td>" . htmlspecialchars($value) . "</td>";
                }
                echo "<td>";

                if ($selected_table === 'student_users' && isset($row['roll'])) {
                    // Only Reset Password for student_users
                    echo "<form method='POST' style='display:inline;'>
                            <input type='hidden' name='table' value='$selected_table'>
                            <input type='hidden' name='roll' value='" . $row['roll'] . "'>
                            <button type='submit' name='reset_password'>Reset Password</button>
                        </form>";
                } elseif ($selected_table === 'wardens' && isset($row['bhavan'])) {
                    // Only Reset Password for wardens
                    echo "<form method='POST' style='display:inline;'>
                            <input type='hidden' name='table' value='$selected_table'>
                            <input type='hidden' name='bhavan' value='" . $row['bhavan'] . "'>
                            <button type='submit' name='reset_password'>Reset Password</button>
                        </form>";
                } elseif ($selected_table === 'vidyamandira' && isset($row['roll'])) {
                    // Update and Delete for vidyamandira
                    $name = isset($row['name']) ? htmlspecialchars($row['name']) : 'Unknown';
                    echo "<form method='POST' style='display:inline;'>
                            <input type='hidden' name='table' value='$selected_table'>
                            <input type='hidden' name='roll' value='" . $row['roll'] . "'>
                            <select name='column'>";
                                foreach ($columns as $col) {
                                    echo "<option value='$col'>$col</option>";
                                }
                    echo "</select>
                            <input type='text' name='value' placeholder='New Value'>
                            <button type='submit' name='update'>Update</button>
                        </form>
                        <form method='POST' style='display:inline;' onsubmit='return confirmDelete(\"" . $row['roll'] . "\", \"" . $name . "\");'>
                            <input type='hidden' name='table' value='$selected_table'>
                            <input type='hidden' name='roll' value='" . $row['roll'] . "'>
                            <button type='submit' name='delete'>Delete</button>
                        </form>";
                } elseif (isset($row['roll'])) {
                    // Update for other tables with roll
                    echo "<form method='POST' style='display:inline;'>
                            <input type='hidden' name='table' value='$selected_table'>
                            <input type='hidden' name='roll' value='" . $row['roll'] . "'>
                            <select name='column'>";
                                foreach ($columns as $col) {
                                    echo "<option value='$col'>$col</option>";
                                }
                    echo "</select>
                            <input type='text' name='value' placeholder='New Value'>
                            <button type='submit' name='update'>Update</button>
                        </form>";
                } else {
                    echo "No actions available";
                }

                echo "</td>";
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