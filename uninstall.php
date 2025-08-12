<?php

/**
 * Uninstall script
 *
 * @package EchBay_ViettelPost_WooCommerce
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('echbay_viettelpost_username');
delete_option('echbay_viettelpost_password');
delete_option('echbay_viettelpost_customer_code');
delete_option('echbay_viettelpost_sender_name');
delete_option('echbay_viettelpost_sender_address');
delete_option('echbay_viettelpost_sender_phone');
delete_option('echbay_viettelpost_sender_email');
delete_option('echbay_viettelpost_sender_province');
delete_option('echbay_viettelpost_sender_district');
delete_option('echbay_viettelpost_sender_ward');
delete_option('echbay_viettelpost_auto_create_order');
delete_option('echbay_viettelpost_auto_create_status');
delete_option('echbay_viettelpost_default_weight');
delete_option('echbay_viettelpost_default_service');
delete_option('echbay_viettelpost_provinces');
delete_option('echbay_viettelpost_districts');
delete_option('echbay_viettelpost_wards');

// Delete transients
delete_transient('echbay_viettelpost_token');

// Clear scheduled events
wp_clear_scheduled_hook('echbay_viettelpost_sync_locations');
wp_clear_scheduled_hook('echbay_viettelpost_update_tracking');

// Delete post meta for all orders (optional - only if user chooses to remove all data)
global $wpdb;

// Uncomment the following lines if you want to remove all ViettelPost data from orders
/*
$wpdb->delete(
    $wpdb->postmeta,
    array(
        'meta_key' => '_viettelpost_order_number'
    )
);

$wpdb->delete(
    $wpdb->postmeta,
    array(
        'meta_key' => '_viettelpost_created_date'
    )
);

$wpdb->delete(
    $wpdb->postmeta,
    array(
        'meta_key' => '_viettelpost_service_type'
    )
);

$wpdb->delete(
    $wpdb->postmeta,
    array(
        'meta_key' => '_viettelpost_tracking_info'
    )
);

$wpdb->delete(
    $wpdb->postmeta,
    array(
        'meta_key' => '_viettelpost_last_tracking_update'
    )
);
*/
