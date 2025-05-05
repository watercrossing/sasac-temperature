<?php
// Simple JSON API for the latest readings. Returns {"water":"14.9","air":"13.9"}, or "-" for values in case readings in the database are older than 20min or in case of errors.

// Database configuration
$db_host = 'localhost';
$db_name = '';
$db_user = '';
$db_pass = '';

header("Cache-Control: no-cache");
header("Content-Type: application/json");

// Check if the request method is GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Connect to the database
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }

    // Get the current UTC time
    $current_time = new DateTime('now', new DateTimeZone('UTC'));
    
    // Prepare and execute SQL query to get the latest temperature readings
    try {
        $stmt = $pdo->query("SELECT water, air, timestamp FROM wpmi_temperature_logs ORDER BY timestamp DESC LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Calculate time difference
            $timestamp = new DateTime($result['timestamp'], new DateTimeZone('UTC'));
            $time_diff = $current_time->getTimestamp() - $timestamp->getTimestamp();
            
            // Format response
            $response = [];
            
            // Check if water reading is not null and not older than 10 minutes
            if ($result['water'] !== null && $time_diff <= 600) {
                $response['water'] = number_format((float)$result['water'], 1, '.', '');
            } else {
                $response['water'] = "-";
            }
            
            // Check if air reading is not null and not older than 10 minutes
            if ($result['air'] !== null && $time_diff <= 600) {
                $response['air'] = number_format((float)$result['air'], 1, '.', '');
            } else {
                $response['air'] = "-";
            }
            
            echo json_encode($response);
        } else {
            // No readings found
            echo json_encode(['water' => '-', 'air' => '-']);
        }
    } catch (PDOException $e) {
        echo json_encode(['water' => '-', 'air' => '-']);
    }
} else {
    echo json_encode(['water' => '-', 'air' => '-']);
}
?>
