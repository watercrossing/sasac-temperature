<?php
/*
Plugin Name: Temperature Logger
Description: Logs water temperature readings from a secure endpoint, displays them using shortcode.
Version: 1.5
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

function get_temperature_data($period, $window_size = 3) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'temperature_logs';
    
    // Determine interval and query format based on period
    switch($period) {
        case '24h':
            $interval = 'INTERVAL 25 HOUR';
            $time_format = '%Y-%m-%d %H:%i:00';
            break;
        case '7d':
            $interval = 'INTERVAL 8 DAY';
            $time_format = '%Y-%m-%d %H:%i:00';
            break;
        case '30d':
            $interval = 'INTERVAL 31 DAY';
            $time_format = '%Y-%m-%d %H:00:00';
            break;
        default:
            error_log("Invalid period specified: $period");
            return false;
    }
    
    // For periods longer than 24h, only get hourly data
    $query = $wpdb->prepare("
        SELECT 
            UNIX_TIMESTAMP(CONVERT_TZ(
                DATE_FORMAT(timestamp, %s),
                '+00:00', 'Europe/London'
            )) as timestamp,
            AVG(water) as water_temp,
            AVG(air) as air_temp
        FROM $table_name
        WHERE timestamp >= DATE_SUB(NOW(), $interval)
        GROUP BY DATE_FORMAT(timestamp, %s)
        ORDER BY timestamp ASC
    ", $time_format, $time_format);
    
    $results = $wpdb->get_results($query);
    $total_points = count($results);
    
    // Calculate moving averages with configurable window
    $processed_data = [];
    
    // Helper function to calculate average of available points
    $calculate_average = function($array) {
        return !empty($array) ? array_sum($array) / count($array) : null;
    };
    
    for ($i = 0; $i < $total_points; $i++) {
        $current_row = $results[$i];
        
        // Calculate cutoff time based on period
        switch($period) {
            case '24h':
                $cutoff = strtotime('-24 hours');
                break;
            case '7d':
                $cutoff = strtotime('-7 days');
                break;
            case '30d':
                $cutoff = strtotime('-30 days');
                break;
            default:
                $cutoff = strtotime('-24 hours');
        }
        
        if ($current_row->timestamp >= $cutoff) {
            $water_window = [];
            $air_window = [];
            
            // Calculate start and end indices for the window
            $start_idx = max(0, $i - $window_size);
            $end_idx = min($total_points - 1, $i + $window_size);
            
            // Collect points within the window
            for ($j = $start_idx; $j <= $end_idx; $j++) {
                // For 24h: 5 minutes * window_size
                // For 7d/30d: 1 hour * window_size
                $max_time_diff = ($period === '24h') ? $window_size * 300 : $window_size * 3600;
                $time_diff = abs($results[$j]->timestamp - $current_row->timestamp);
                
                if ($time_diff <= $max_time_diff) {
                    $water_window[] = $results[$j]->water_temp;
                    $air_window[] = $results[$j]->air_temp;
                }
            }
            
            $processed_row = (object)[
                'timestamp' => $current_row->timestamp,
                'water_temp' => $current_row->water_temp,
                'air_temp' => $current_row->air_temp,
                'water_temp_sma' => $calculate_average($water_window),
                'air_temp_sma' => $calculate_average($air_window),
                'points_in_average' => count($water_window)
            ];
            
            $processed_data[] = $processed_row;
        }
    }
    
    return $processed_data;
}


// Function to create temperature graph
function create_temperature_graph($data, $period, $show_raw_data = true, $show_sma = true) {
    // Check if data is valid
    if (empty($data) || !is_array($data) || !isset($data[0]->water_temp) || !isset($data[0]->air_temp)) {
        error_log("Invalid data structure in create_temperature_graph");
        return false;
    }
    
    // Set up image
    $width = 1000;
    $height = 500;
    $margin = 30;
    $graph_width = $width - 2 * $margin;
    $graph_height = $height - 2 * $margin;
    
    $image = imagecreatetruecolor($width, $height);
    if (!$image) {
        error_log("Failed to create image resource");
        return false;
    }
    
    // Define colors - use standard colors for raw data if it's the only thing shown
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    $blue = imagecolorallocate($image, 0, 0, 255);
    $red = imagecolorallocate($image, 255, 0, 0);
    $light_blue = imagecolorallocate($image, 180, 180, 255);
    $light_red = imagecolorallocate($image, 255, 180, 180);
    $gray = imagecolorallocate($image, 200, 200, 200);
    $light_gray = imagecolorallocate($image, 230, 230, 230);
    
    imagefill($image, 0, 0, $white);
    
    // Calculate min and max temperatures
    $all_temps = [];
    foreach ($data as $point) {
        if ($show_raw_data) {
            $all_temps[] = floatval($point->water_temp);
            $all_temps[] = floatval($point->air_temp);
        }
        if ($show_sma && isset($point->water_temp_sma) && isset($point->air_temp_sma)) {
            $all_temps[] = floatval($point->water_temp_sma);
            $all_temps[] = floatval($point->air_temp_sma);
        }
    }
    
    $min_temp = min($all_temps);
    $max_temp = max($all_temps);
    $temp_range = $max_temp - $min_temp;
    
    if ($temp_range == 0) {
        $temp_range = 1; // Avoid division by zero
    }
    
    // Draw axes
    imageline($image, $margin, $margin, $margin, $height - $margin, $black);
    imageline($image, $margin, $height - $margin, $width - $margin, $height - $margin, $black);
    
    // Draw horizontal grid lines
    for ($i = 0; $i <= 5; $i++) {
        $y = $margin + $i * $graph_height / 5;
		if ($i < 5) {
			imageline($image, $margin, $y, $width - $margin, $y, $gray);
		}
        $temp = $max_temp - $i * $temp_range / 5;
        imagestring($image, 2, 5, $y - 7, number_format($temp, 1), $black);
    }
    
    // Get actual start and end timestamps from data
    $data_start_time = $data[0]->timestamp;
    $data_end_time = end($data)->timestamp;
    
    // Draw vertical grid lines and x-axis labels
    if ($period === '24h') {
        // Find the first and last full hour
        $first_hour = strtotime(date('Y-m-d H:00:00', $data_start_time));
        if ($first_hour < $data_start_time) {
            $first_hour += 3600;
        }
        $last_hour = strtotime(date('Y-m-d H:00:00', $data_end_time));
        
        // Draw lines every 3 hours
        for ($time = $first_hour; $time <= $last_hour; $time += 3600 * 3) {
            $x_pos = $margin + ($time - $data_start_time) * $graph_width / ($data_end_time - $data_start_time);
            
            if ($x_pos >= $margin && $x_pos <= ($width - $margin)) {
                imageline($image, $x_pos, $margin, $x_pos, $height - $margin, $gray);
                imagestring($image, 2, $x_pos - 15, $height - $margin + 10, date('H:i', $time), $black);
            }
        }
    } elseif ($period === '7d') {
        // Find the first and last midnight
        $first_midnight = strtotime(date('Y-m-d 00:00:00', $data_start_time));
        if ($first_midnight < $data_start_time) {
            $first_midnight += 86400;
        }
        $last_midnight = strtotime(date('Y-m-d 00:00:00', $data_end_time));
        
        // Draw daily lines at midnight
        for ($time = $first_midnight; $time <= $last_midnight; $time += 86400) {
            $x_pos = $margin + ($time - $data_start_time) * $graph_width / ($data_end_time - $data_start_time);
            
            if ($x_pos >= $margin && $x_pos <= ($width - $margin)) {
                imageline($image, $x_pos, $margin, $x_pos, $height - $margin, $gray);
                imagestring($image, 2, $x_pos - 15, $height - $margin + 10, date('d/m', $time), $black);
            }
        }
    } else { // 30d
        // Find the first and last midnight
        $first_midnight = strtotime(date('Y-m-d 00:00:00', $data_start_time));
        if ($first_midnight < $data_start_time) {
            $first_midnight += 86400;
        }
        $last_midnight = strtotime(date('Y-m-d 00:00:00', $data_end_time));
        
        // Draw thin lines at every midnight
        for ($time = $first_midnight; $time <= $last_midnight; $time += 86400) {
            $x_pos = $margin + ($time - $data_start_time) * $graph_width / ($data_end_time - $data_start_time);
            
            if ($x_pos >= $margin && $x_pos <= ($width - $margin)) {
                // Draw thinner line in light gray for regular days
                if (($time - $first_midnight) % (86400 * 3) !== 0) {
                    imageline($image, $x_pos, $margin, $x_pos, $height - $margin, $light_gray);
                } else {
                    // Draw normal grid line and label every 3 days
                    imageline($image, $x_pos, $margin, $x_pos, $height - $margin, $gray);
                    imagestring($image, 2, $x_pos - 15, $height - $margin + 10, date('d/m', $time), $black);
                }
            }
        }
    }
    
    // Determine which colors to use for raw data
    $water_color = ($show_sma) ? $light_blue : $blue;
    $air_color = ($show_sma) ? $light_red : $red;
    
    // Plot raw data if enabled
    if ($show_raw_data) {
        $prev_x_water = $prev_y_water = $prev_x_air = $prev_y_air = null;
        foreach ($data as $index => $point) {
            $x = $margin + $index * $graph_width / (count($data) - 1);
            $y_water = $height - $margin - (floatval($point->water_temp) - $min_temp) * $graph_height / $temp_range;
            $y_air = $height - $margin - (floatval($point->air_temp) - $min_temp) * $graph_height / $temp_range;
            
            if ($prev_x_water !== null) {
                imageline($image, $prev_x_water, $prev_y_water, $x, $y_water, $water_color);
                imageline($image, $prev_x_air, $prev_y_air, $x, $y_air, $air_color);
            }
            
            $prev_x_water = $x;
            $prev_y_water = $y_water;
            $prev_x_air = $x;
            $prev_y_air = $y_air;
        }
    }
    
    // Plot SMA data if enabled
    if ($show_sma) {
        $prev_x_water = $prev_y_water = $prev_x_air = $prev_y_air = null;
        foreach ($data as $index => $point) {
            if (!isset($point->water_temp_sma) || !isset($point->air_temp_sma)) {
                continue;
            }
            
            $x = $margin + $index * $graph_width / (count($data) - 1);
            
            if ($point->water_temp_sma !== null) {
                $y_water = $height - $margin - (floatval($point->water_temp_sma) - $min_temp) * $graph_height / $temp_range;
                if ($prev_x_water !== null) {
                    imageline($image, $prev_x_water, $prev_y_water, $x, $y_water, $blue);
                    if ($show_raw_data) {
                        imageline($image, $prev_x_water, $prev_y_water - 1, $x, $y_water - 1, $blue);
                    }
                }
                $prev_x_water = $x;
                $prev_y_water = $y_water;
            }
            
            if ($point->air_temp_sma !== null) {
                $y_air = $height - $margin - (floatval($point->air_temp_sma) - $min_temp) * $graph_height / $temp_range;
                if ($prev_x_air !== null) {
                    imageline($image, $prev_x_air, $prev_y_air, $x, $y_air, $red);
                    if ($show_raw_data) {
                        imageline($image, $prev_x_air, $prev_y_air - 1, $x, $y_air - 1, $red);
                    }
                }
                $prev_x_air = $x;
                $prev_y_air = $y_air;
            }
        }
    }
    
    // Add labels
    switch($period) {
        case '24h':
            $title = 'Last 24 hours Temperature / C';
            break;
        case '7d':
            $title = 'Last 7 days Temperature / C';
            break;
        case '30d':
            $title = 'Last 30 days Temperature / C';
            break;
        default:
            $title = 'Temperature / C';
    }
    imagestring($image, 5, $width / 2 - 100, 10, $title, $black);
    
    // Update legend based on what's shown
    if ($show_raw_data && $show_sma) {
        imagestring($image, 3, $width - 150, 10, 'Water (raw)', $light_blue);
        imagestring($image, 3, $width - 150, 25, 'Water (SMA)', $blue);
        imagestring($image, 3, $width - 150, 40, 'Air (raw)', $light_red);
        imagestring($image, 3, $width - 150, 55, 'Air (SMA)', $red);
    } else if ($show_raw_data) {
        imagestring($image, 3, $width - 150, 30, 'Water', $blue);
        imagestring($image, 3, $width - 150, 50, 'Air', $red);
    } else if ($show_sma) {
        imagestring($image, 3, $width - 150, 30, 'Water (SMA)', $blue);
        imagestring($image, 3, $width - 150, 50, 'Air (SMA)', $red);
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
    $data_24h = get_temperature_data('24h', 3);
    $data_7d = get_temperature_data('7d', 5);
    $data_30d = get_temperature_data('30d', 5);
    
	$output = ""; //"""Data: " . json_encode($data_24h); //var_dump($data_24h);
    $graph_24h = create_temperature_graph($data_24h, '24h', false, true);
    $graph_7d = create_temperature_graph($data_7d, '7d', false, true);
    $graph_30d = create_temperature_graph($data_30d, '30d', true, false);
    
    $output .= '<h2>Temperature Graphs</h2>';
    $output .= '<h3>Last 24 Hours</h3>';
    $output .= '<img src="data:image/png;base64,' . $graph_24h . '" alt="24 Hour Temperature Graph">';
    $output .= '<h3>Last 7 Days</h3>';
    $output .= '<img src="data:image/png;base64,' . $graph_7d . '" alt="7 Day Temperature Graph">';
    $output .= '<h3>Last 30 Days</h3>';
    $output .= '<img src="data:image/png;base64,' . $graph_30d . '" alt="30 Day Temperature Graph">';
    
	
    return $output;
}

// Register shortcode
add_shortcode('temperature_figures', 'temperature_figures_shortcode');
