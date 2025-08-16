<?php

/**
 * ViettelPost Order Queue Cron Job
 * 
 */

// 
header('Content-Type: application/json');

// Simple rate limiting to prevent abuse
$last_run_option = __DIR__ . '/ebvtp_last_cron_run.txt';
$current_time = time();
$last_run = is_file($last_run_option) ? (int) file_get_contents($last_run_option) : 0;
$min_interval = 55; // Minimum 55 seconds between runs

if (($current_time - $last_run) < $min_interval) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Rate limited',
        // 'last_run' => $current_time - $last_run,
    ));
    exit();
}

// Update last run time
file_put_contents($last_run_option, $current_time, LOCK_EX);

// Load WordPress
$wp_loaded = false;
$wp_load_php = '../wp-load.php';
for ($i = 0; $i < 10; $i++) {
    // echo $wp_load_php . '<br>' . PHP_EOL;
    if (is_file($wp_load_php)) {
        require_once $wp_load_php;
        $wp_loaded = true;
        break;
    }
    $wp_load_php = '../' . $wp_load_php; // Try parent directories
}

if (!$wp_loaded) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'WordPress not found'
    ));
    exit();
}
// die(__FILE__ . ':' . __LINE__);

// Check if ViettelPost plugin is active
if (!class_exists('EchBay_ViettelPost_Order_Handler')) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'ViettelPost plugin not found or not active'
    ));
    exit();
}

// 
try {
    // Initialize order handler
    $order_handler = new EchBay_ViettelPost_Order_Handler();
    $processed = $order_handler->process_queue();

    // Clean up last run file
    unlink($last_run_option);

    // Process the queue
    echo json_encode(array(
        'success' => true,
        'message' => 'Queue processed successfully',
        'result' => $processed
    ));
} catch (Exception $e) {
    http_response_code(500);

    echo json_encode(array(
        'success' => false,
        'message' => 'Error processing queue',
        'error' => $e->getMessage()
    ));
}
