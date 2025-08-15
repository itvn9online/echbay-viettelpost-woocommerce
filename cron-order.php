<?php

/**
 * ViettelPost Order Queue Cron Job
 * 
 */

// 
header('Content-Type: application/json');

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

// Initialize order handler
$order_handler = new EchBay_ViettelPost_Order_Handler();

// Process the queue
echo json_encode(array(
    'success' => true,
    'message' => 'Queue processed successfully',
    'result' => $order_handler->process_queue()
));
