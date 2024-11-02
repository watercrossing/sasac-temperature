<?php
// Database configuration
$db_host = 'localhost';
$db_name = '';
$db_user = '';
$db_pass = '';

// Secret key for validation
$secret_key = 'SECRET';

// Function to validate temperature
function validateTemperature($temp, $type) {
    if ($type === 'water') {
        return $temp >= -10 && $temp <= 50;
    } elseif ($type === 'air') {
        return $temp >= -30 && $temp <= 60;
    }
    return false;
}

header("Cache-Control: no-cache");

// Check if the request method is GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Validate the secret key
    if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) {
        echo "Error: Incorrect key";
        exit;
    }

    // Check if at least one temperature is provided
    if (!isset($_GET['water']) && !isset($_GET['air'])) {
        echo "Error: At least one temperature (water or air) is required";
        exit;
    }

    $water_temp = isset($_GET['water']) && filter_var($_GET['water'], FILTER_VALIDATE_FLOAT) ? floatval($_GET['water']) : null;
    $air_temp = isset($_GET['air']) && filter_var($_GET['air'], FILTER_VALIDATE_FLOAT) ? floatval($_GET['air']) : null;

    // Validate temperatures
    if ($water_temp !== null && !validateTemperature($water_temp, 'water')) {
        echo "Error: Invalid water temperature";
        exit;
    }
    if ($air_temp !== null && !validateTemperature($air_temp, 'air')) {
        echo "Error: Invalid air temperature";
        exit;
    }

    if ($water_temp === null && $air_temp === null) {
        echo "Need to supply at least one valid temperature";
        exit;
    }

    // Connect to the database
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        echo "Error: Database connection failed";
        exit;
    }

    // Prepare and execute the SQL query
    try {
        $stmt = $pdo->prepare("INSERT INTO wpmi_temperature_logs (water, air, timestamp) VALUES (:water, :air, UTC_TIMESTAMP())");
        $stmt->bindParam(':water', $water_temp, PDO::PARAM_STR);
        $stmt->bindParam(':air', $air_temp, PDO::PARAM_STR);
        $stmt->execute();

        echo "Update OK";
    } catch (PDOException $e) {
        echo "Error: Failed to insert data into the database<br>".$e->getMessage();
    }
} else {
    echo "Error: Invalid request method";
}
?>
