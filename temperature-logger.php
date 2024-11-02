<?php
/*
Plugin Name: Temperature Logger
Description: Logs water temperature readings from a secure endpoint, displays them using shortcode.
Version: 1.4
Author: Ingolf Becker
*/

/*
OLD, use seperate subdomain for URL hosting.

// Activation hook to create the custom table
register_activation_hook(__FILE__, 'temperature_logger_activate');

function temperature_logger_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'temperature_logs';
    
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        temperature float NOT NULL,
        timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Add rewrite rule for our custom endpoint
add_action('init', 'temperature_logger_add_rewrite_rule');

function temperature_logger_add_rewrite_rule() {
    add_rewrite_rule('^temperatureupdate$', 'index.php?temperature_update=1', 'top');
}

// Add query var for our custom endpoint
add_filter('query_vars', 'temperature_logger_add_query_vars');

function temperature_logger_add_query_vars($vars) {
    $vars[] = 'temperature_update';
    return $vars;
}

// Handle the temperature update request
add_action('template_redirect', 'temperature_logger_handle_request');

function temperature_logger_handle_request() {
    if (get_query_var('temperature_update') == 1) {
        $secret_key = 'SECRET'; // Replace with your actual secret key
        
        if ($_GET['key'] !== $secret_key) {
            status_header(403);
            die('Invalid key');
        }
        
        if (!isset($_GET['temp']) || $_GET['temp'] === '') {
            status_header(400);
            die('Temperature value is missing');
        }
        
        $temperature = floatval($_GET['temp']);
        
        // Check if temperature is within the valid range
        if ($temperature < -10 || $temperature > 50) {
            status_header(400);
            die('Invalid temperature value. Must be between -10 and 50 degrees.');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'temperature_logs';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'temperature' => $temperature,
                'timestamp' => current_time('mysql', 1)
            ),
            array('%f', '%s')
        );
        
        if ($result === false) {
            status_header(500);
            die('Failed to log temperature');
        }
        
        status_header(200);
        die('Temperature logged successfully');
    }
}

// Flush rewrite rules on plugin activation and deactivation
register_activation_hook(__FILE__, 'temperature_logger_flush_rewrites');
register_deactivation_hook(__FILE__, 'temperature_logger_flush_rewrites');

function temperature_logger_flush_rewrites() {
    temperature_logger_add_rewrite_rule();
    flush_rewrite_rules();
}

*/

// DISPLAYING TEMPERATURES

// Function to get the most recent temperature
function get_most_recent_temperature($column) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'temperature_logs';
    
    $result = $wpdb->get_row($wpdb->prepare(
        "SELECT $column as temperature, timestamp FROM $table_name 
        WHERE $column IS NOT NULL 
        ORDER BY timestamp DESC 
        LIMIT 1"
    ));
    
    if ($result) {
        $london_time = new DateTime($result->timestamp, new DateTimeZone('UTC'));
        $london_time->setTimezone(new DateTimeZone('Europe/London'));
        $result->timestamp = $london_time->format('d/m/Y H:i');
    }
    
    return $result;
}

function get_most_recent_update_timestamp() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'temperature_logs';
    
    $result = $wpdb->get_row($wpdb->prepare(
        "SELECT timestamp FROM $table_name 
        ORDER BY timestamp DESC 
        LIMIT 1"
    ));
    
    if ($result) {
        $london_time = new DateTime($result->timestamp, new DateTimeZone('UTC'));
        $london_time->setTimezone(new DateTimeZone('Europe/London'));
		return  $london_time;
    } else {
		return new DateTime("1970-01-01", new DateTimeZone('Europe/London'));
	}
}

// Function to get min and max temperatures for each of the last 7 days including today
function get_daily_min_max_temperatures($column) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'temperature_logs';
    
    $query = $wpdb->prepare("
        SELECT 
            DATE(CONVERT_TZ(timestamp, '+00:00', 'Europe/London')) as date,
            MIN($column) as min_temp,
            MAX($column) as max_temp,
            (SELECT TIME(CONVERT_TZ(timestamp, '+00:00', 'Europe/London'))
             FROM $table_name t2
             WHERE DATE(CONVERT_TZ(t2.timestamp, '+00:00', 'Europe/London')) = DATE(CONVERT_TZ(t1.timestamp, '+00:00', 'Europe/London'))
             AND t2.$column = MIN(t1.$column)
             AND t2.$column IS NOT NULL
             LIMIT 1) as min_temp_time,
            (SELECT TIME(CONVERT_TZ(timestamp, '+00:00', 'Europe/London'))
             FROM $table_name t2
             WHERE DATE(CONVERT_TZ(t2.timestamp, '+00:00', 'Europe/London')) = DATE(CONVERT_TZ(t1.timestamp, '+00:00', 'Europe/London'))
             AND t2.$column = MAX(t1.$column)
             AND t2.$column IS NOT NULL
             LIMIT 1) as max_temp_time
        FROM $table_name t1
        WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(CONVERT_TZ(timestamp, '+00:00', 'Europe/London'))
        ORDER BY date DESC
        LIMIT 7
    ");
    
    return $wpdb->get_results($query);
}

// Function to generate a temperature table
function generate_temperature_table($temps, $type) {
    $output = '<table border="1" cellpadding="5" style="border-collapse: collapse; width: 100%;">';
    $output .= "<tr><th colspan='5' style='text-align: center;'>{$type} Temperature</th></tr>";
    $output .= '<tr><th style="text-align: center;">Date</th><th style="text-align: center;">Min</th><th style="text-align: center;">Time</th><th style="text-align: center;">Max</th><th style="text-align: center;">Time</th></tr>';
    
    foreach ($temps as $day) {
        $output .= '<tr>';
        $output .= '<td style="text-align: center;">' . date('d/m/Y', strtotime($day->date)) . '</td>';
        $output .= '<td style="text-align: center;">' . (is_null($day->min_temp) ? '-' : number_format($day->min_temp, 1) . '°C') . '</td>';
        $output .= '<td style="text-align: center;">' . (is_null($day->min_temp_time) ? '-' : substr($day->min_temp_time, 0, 5)) . '</td>';
        $output .= '<td style="text-align: center;">' . (is_null($day->max_temp) ? '-' : number_format($day->max_temp, 1) . '°C') . '</td>';
        $output .= '<td style="text-align: center;">' . (is_null($day->max_temp_time) ? '-' : substr($day->max_temp_time, 0, 5)) . '</td>';
        $output .= '</tr>';
    }
    
    $output .= '</table>';
    return $output;
}


function add_custom_headers() {
    // Get the current page URL
    $current_url = home_url($_SERVER['REQUEST_URI']);
    
    // Check if the current page is the one we want to modify
    if ($current_url === 'https://sasac.co.uk/water-temperature/') {
		
		$latest_data_time = get_most_recent_update_timestamp();
		$current_time = new DateTime('now', new DateTimeZone('Europe/London'));
		$time_difference = $current_time->getTimestamp() - $latest_data_time->getTimestamp();
		
		if ($time_difference <= 300) { // 5 minutes
			// Data is recent, refresh 5 minutes after the latest data
        	header("Cache-Control: max-age=". max(301 - $time_difference, 5));
		} else {
			// Data is more than 5 minutes old, refresh every 15 minutes
			header("Cache-Control: max-age=30");
		}
		header("Refresh: 60");
    }
}

// Use the 'send_headers' action to ensure headers are sent at the right time
add_action('send_headers', 'add_custom_headers');


// Shortcode to display temperature data
function temperature_display_shortcode() {
    $recent_water_temp = get_most_recent_temperature('water');
    $recent_air_temp = get_most_recent_temperature('air');
    $daily_water_temps = get_daily_min_max_temperatures('water');
    $daily_air_temps = get_daily_min_max_temperatures('air');
    
    $output = '';
    if ($recent_water_temp) {
        $output .= '<h3>Water: ' . number_format($recent_water_temp->temperature, 1) . '°C</h3>';
        $output .= '(Recorded at: ' . $recent_water_temp->timestamp . ')<br>';
    } else {
        $output .= 'No recent water temperature data available.<br>';
    }
    if ($recent_air_temp) {
        $output .= '<h3>Air: ' . number_format($recent_air_temp->temperature, 1) . '°C</h3>';
        $output .= '(Recorded at: ' . $recent_air_temp->timestamp . ')';
    } else {
        $output .= 'No recent air temperature data available.';
    }
    $output .= '</p>';
    
    $output .= '<h3>Last 7 Days Temperature Range (including today)</h3>';
    $output .= '<div style="display: flex; flex-wrap: wrap; gap: 20px;">';
    $output .= '<div style="flex: 1 1 300px;">' . generate_temperature_table($daily_air_temps, 'Air') . '</div>';
    $output .= '<div style="flex: 1 1 300px;">' . generate_temperature_table($daily_water_temps, 'Water') . '</div>';
    $output .= '</div>';
    
    return $output;
}

// Register shortcode
add_shortcode('temperature_display', 'temperature_display_shortcode');


// Figures!

function get_temperature_data($period) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'temperature_logs';
    
    $interval = ($period === '24h') ? 'INTERVAL 24 HOUR' : 'INTERVAL 7 DAY';
    
    $query = $wpdb->prepare("
        SELECT 
            UNIX_TIMESTAMP(CONVERT_TZ(timestamp, '+00:00', 'Europe/London')) as timestamp,
            water as water_temp,
            air as air_temp
        FROM $table_name
        WHERE timestamp >= DATE_SUB(NOW(), $interval)
        ORDER BY timestamp ASC
    ");
    
    return $wpdb->get_results($query);
}

// Function to create temperature graph
function create_temperature_graph($data, $period) {
    // Check if data is valid
    if (empty($data) || !is_array($data) || !isset($data[0]->water_temp) || !isset($data[0]->air_temp)) {
        error_log("Invalid data structure in create_temperature_graph");
        return false;
    }

    // Set up image
    $width = 800;
    $height = 400;
    $margin = 50;
    $graph_width = $width - 2 * $margin;
    $graph_height = $height - 2 * $margin;
    
    $image = imagecreatetruecolor($width, $height);
    if (!$image) {
        error_log("Failed to create image resource");
        return false;
    }

    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    $blue = imagecolorallocate($image, 0, 0, 255);
    $red = imagecolorallocate($image, 255, 0, 0);
    $gray = imagecolorallocate($image, 200, 200, 200);
    
    imagefill($image, 0, 0, $white);
    
    // Calculate min and max temperatures
    $water_temps = array_map(function($item) { return floatval($item->water_temp); }, $data);
    $air_temps = array_map(function($item) { return floatval($item->air_temp); }, $data);
    $min_temp = min(min($water_temps), min($air_temps));
    $max_temp = max(max($water_temps), max($air_temps));
    $temp_range = $max_temp - $min_temp;
    
    if ($temp_range == 0) {
        $temp_range = 1; // Avoid division by zero
    }

    // Draw axes
    imageline($image, $margin, $margin, $margin, $height - $margin, $black);
    imageline($image, $margin, $height - $margin, $width - $margin, $height - $margin, $black);
    
    // Draw grid lines
    for ($i = 0; $i <= 5; $i++) {
        $y = $margin + $i * $graph_height / 5;
        imageline($image, $margin, $y, $width - $margin, $y, $gray);
        $temp = $max_temp - $i * $temp_range / 5;
        imagestring($image, 2, 5, $y - 7, number_format($temp, 1), $black);
    }
    
    // Plot data
    $prev_x_water = $prev_y_water = $prev_x_air = $prev_y_air = null;
    foreach ($data as $index => $point) {
        $x = $margin + $index * $graph_width / (count($data) - 1);
        $y_water = $height - $margin - (floatval($point->water_temp) - $min_temp) * $graph_height / $temp_range;
        $y_air = $height - $margin - (floatval($point->air_temp) - $min_temp) * $graph_height / $temp_range;
        
        if ($prev_x_water !== null) {
            imageline($image, $prev_x_water, $prev_y_water, $x, $y_water, $blue);
            imageline($image, $prev_x_air, $prev_y_air, $x, $y_air, $red);
        }
        
        $prev_x_water = $x;
        $prev_y_water = $y_water;
        $prev_x_air = $x;
        $prev_y_air = $y_air;
    }
    
    // Add labels
    $title = ($period === '24h') ? 'Last 24 hours Temperature / C' : 'Last 7 days Temperature / C';
    imagestring($image, 5, $width / 2 - 100, 10, $title, $black);
    imagestring($image, 3, $width - 100, 30, 'Water', $blue);
    imagestring($image, 3, $width - 100, 50, 'Air', $red);
    
    // X-axis labels
    $label_count = ($period === '24h') ? 6 : 7;
    for ($i = 0; $i < $label_count; $i++) {
        $x = $margin + $i * $graph_width / ($label_count - 1);
        $index = floor($i * (count($data) - 1) / ($label_count - 1));
        $time = date(($period === '24h') ? 'H:i' : 'd/m', $data[$index]->timestamp);
        imagestring($image, 2, $x - 15, $height - $margin + 10, $time, $black);
    }
    
    // Output image
    ob_start();
    $result = imagepng($image);
    if (!$result) {
        error_log("Failed to create PNG image");
        ob_end_clean();
        return false;
    }
    $image_data = ob_get_clean();
    imagedestroy($image);
    
    return base64_encode($image_data);
}

// Shortcode to display temperature figures
function temperature_figures_shortcode() {
    $data_24h = get_temperature_data('24h');
    $data_7d = get_temperature_data('7d');
    
	$output = ""; //"""Data: " . json_encode($data_24h); //var_dump($data_24h);
    $graph_24h = create_temperature_graph($data_24h, '24h');
    $graph_7d = create_temperature_graph($data_7d, '7d');
    
    $output .= '<h2>Temperature Graphs</h2>';
    $output .= '<h3>Last 24 Hours</h3>';
    $output .= '<img src="data:image/png;base64,' . $graph_24h . '" alt="24 Hour Temperature Graph">';
    $output .= '<h3>Last 7 Days</h3>';
    $output .= '<img src="data:image/png;base64,' . $graph_7d . '" alt="7 Day Temperature Graph">';
    
	
    return $output;
}

// Register shortcode
add_shortcode('temperature_figures', 'temperature_figures_shortcode');
