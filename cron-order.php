<?php
/**
 * ViettelPost Order Queue Cron Job
 * 
 * This file should be called by server cron job every 5 minutes.
 * 
 * Setup examples:
 * 
 * 1. Via server cron (recommended):
 * */5 * * * * /usr/bin/php /path/to/your/wordpress/wp-content/plugins/echbay-viettelpost-woocommerce/cron-order.php
 * 
 * 2. Via wget/curl (if PHP CLI not available):
 * */5 * * * * wget -q -O - "https://yoursite.com/wp-content/plugins/echbay-viettelpost-woocommerce/cron-order.php?key=your_secret_key" >/dev/null 2>&1
 * */5 * * * * curl -s "https://yoursite.com/wp-content/plugins/echbay-viettelpost-woocommerce/cron-order.php?key=your_secret_key" >/dev/null 2>&1
 * 
 * Security: 
 * - Change the secret key below to a secure random string
 * - Optionally restrict access by IP address
 * - Make sure this file is not accessible from public web without the key
 *
 * @package EchBay_ViettelPost_WooCommerce
 */

// Security check
$secret_key = 'viettelpost_cron_2024'; // Change this to a secure key
if (isset($_GET['key']) && $_GET['key'] !== $secret_key) {
    http_response_code(403);
    die('Access denied');
}

// Load WordPress
$wp_load_paths = [
    __DIR__ . '/../../../../wp-load.php',
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../wp-load.php',
    __DIR__ . '/../wp-load.php'
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die('Could not load WordPress');
}

// Check if running from command line or web
$is_cli = php_sapi_name() === 'cli';

// Log function
function vtp_log($message) {
    global $is_cli;
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message";
    
    if ($is_cli) {
        echo $log_message . "\n";
    } else {
        echo $log_message . "<br>\n";
    }
    
    // Also log to file
    error_log($log_message, 3, __DIR__ . '/cron-order.log');
}

/**
 * Process ViettelPost order queue
 */
function process_viettelpost_queue() {
    global $wpdb;
    
    vtp_log('Starting ViettelPost queue processing...');
    
    // Check if ViettelPost plugin is active
    if (!class_exists('EchBay_ViettelPost_Order_Handler')) {
        vtp_log('ViettelPost plugin not found or not active');
        return;
    }
    
    $table_name = $wpdb->prefix . 'echbay_viettelpost_queue';
    
    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        vtp_log('Queue table does not exist');
        return;
    }
    
    // Get pending orders from queue
    $orders = $wpdb->get_results(
        "SELECT * FROM $table_name 
         WHERE status = 'pending' 
         AND attempts < max_attempts 
         ORDER BY created_at ASC 
         LIMIT 10"
    );
    
    if (empty($orders)) {
        vtp_log('No pending orders in queue');
        return;
    }
    
    vtp_log('Found ' . count($orders) . ' orders to process');
    
    $order_handler = new EchBay_ViettelPost_Order_Handler();
    
    foreach ($orders as $queue_item) {
        vtp_log("Processing order ID: {$queue_item->order_id}");
        
        // Update status to processing
        $wpdb->update(
            $table_name,
            array('status' => 'processing'),
            array('id' => $queue_item->id),
            array('%s'),
            array('%d')
        );
        
        // Try to create ViettelPost order
        $result = $order_handler->create_viettelpost_order($queue_item->order_id);
        
        if (is_wp_error($result)) {
            // Failed - increment attempts
            $error_message = $result->get_error_message();
            vtp_log("Failed to create order {$queue_item->order_id}: $error_message");
            
            $new_attempts = $queue_item->attempts + 1;
            $new_status = $new_attempts >= $queue_item->max_attempts ? 'failed' : 'pending';
            
            $wpdb->update(
                $table_name,
                array(
                    'attempts' => $new_attempts,
                    'status' => $new_status,
                    'error_message' => $error_message
                ),
                array('id' => $queue_item->id),
                array('%d', '%s', '%s'),
                array('%d')
            );
            
            vtp_log("Order {$queue_item->order_id} status updated to: $new_status (attempts: $new_attempts)");
            
        } else {
            // Success - mark as completed
            vtp_log("Successfully created ViettelPost order for order ID: {$queue_item->order_id}, VTP ID: $result");
            
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'completed',
                    'sent_at' => current_time('mysql'),
                    'vtp_id' => $result
                ),
                array('id' => $queue_item->id),
                array('%s', '%s', '%s'),
                array('%d')
            );
        }
        
        // Add small delay to avoid overwhelming the API
        sleep(1);
    }
    
    vtp_log('Queue processing completed');
}

/**
 * Clean up old queue entries
 */
function cleanup_old_queue_entries() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'echbay_viettelpost_queue';
    
    // Delete completed entries older than 30 days
    $deleted = $wpdb->query(
        "DELETE FROM $table_name 
         WHERE status = 'completed' 
         AND sent_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    
    if ($deleted > 0) {
        vtp_log("Cleaned up $deleted old completed queue entries");
    }
    
    // Delete failed entries older than 7 days
    $deleted = $wpdb->query(
        "DELETE FROM $table_name 
         WHERE status = 'failed' 
         AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
    );
    
    if ($deleted > 0) {
        vtp_log("Cleaned up $deleted old failed queue entries");
    }
}

/**
 * Get and display queue statistics
 */
function display_queue_stats() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'echbay_viettelpost_queue';
    
    $stats = $wpdb->get_row(
        "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
         FROM $table_name"
    );
    
    if ($stats) {
        vtp_log("Queue Stats - Total: {$stats->total}, Pending: {$stats->pending}, Processing: {$stats->processing}, Completed: {$stats->completed}, Failed: {$stats->failed}");
    }
}

// Main execution
try {
    // Display current stats
    display_queue_stats();
    
    // Process the queue
    process_viettelpost_queue();
    
    // Cleanup old entries (only run occasionally)
    if (rand(1, 10) == 1) { // 10% chance
        cleanup_old_queue_entries();
    }
    
    vtp_log('Cron job completed successfully');
    
} catch (Exception $e) {
    vtp_log('Error during cron execution: ' . $e->getMessage());
    exit(1);
}

// Output success for web requests
if (!$is_cli) {
    echo '<hr>Cron job completed at ' . date('Y-m-d H:i:s');
}
