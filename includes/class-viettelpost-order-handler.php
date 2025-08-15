<?php

/**
 * ViettelPost Order Handler Class
 *
 * @package EchBay_ViettelPost_WooCommerce
 *
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ViettelPost Order Handler
 */
class EchBay_ViettelPost_Order_Handler
{

    /**
     * Constructor
     */
    public function __construct()
    {
        // Order status change hooks
        add_action('woocommerce_order_status_processing', array($this, 'maybe_create_viettelpost_order'));
        add_action('woocommerce_order_status_completed', array($this, 'maybe_create_viettelpost_order'));

        // Admin order actions
        add_action('woocommerce_order_actions', array($this, 'add_order_actions'));
        add_action('woocommerce_order_action_create_viettelpost_order', array($this, 'create_viettelpost_order_action'));
        add_action('woocommerce_order_action_print_viettelpost_label', array($this, 'print_viettelpost_label_action'));
        add_action('woocommerce_order_action_track_viettelpost_order', array($this, 'track_viettelpost_order_action'));

        // Add meta boxes
        add_action('add_meta_boxes', array($this, 'add_order_meta_boxes'));

        // Add order details
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'display_viettelpost_info'));
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_tracking_info_frontend'));

        // AJAX handlers
        add_action('wp_ajax_viettelpost_create_order', array($this, 'ajax_create_order'));
        add_action('wp_ajax_viettelpost_track_order', array($this, 'ajax_track_order'));
        add_action('wp_ajax_viettelpost_print_label', array($this, 'ajax_print_label'));

        // Scheduled task for tracking updates
        add_action('echbay_viettelpost_update_tracking', array($this, 'update_tracking_info'));
    }

    /**
     * Maybe create ViettelPost order automatically
     */
    public function maybe_create_viettelpost_order($order_id)
    {
        // Check if auto-create is enabled
        $auto_create = get_option('echbay_viettelpost_auto_create_order', 'no');
        if ($auto_create == 'no') {
            // die(__CLASS__ . ':' . __LINE__);
            return;
        }

        // Get order object
        $order = wc_get_order($order_id);
        if (!$order) {
            // die(__CLASS__ . ':' . __LINE__);
            return;
        }

        // Check if order already has ViettelPost order number
        if ($order->get_meta('_viettelpost_order_number', true)) {
            // die(__CLASS__ . ':' . __LINE__);
            return;
        }

        // Debugging output
        if (get_option('echbay_viettelpost_auto_create_status') != 'wc-' . $order->get_status()) {
            // echo 'echbay_viettelpost_auto_create_status: ' . get_option('echbay_viettelpost_auto_create_status') . '<br>' . PHP_EOL;
            // echo 'order get_status: ' . $order->get_status() . '<br>' . PHP_EOL;
            // die(__CLASS__ . ':' . __LINE__);
            return;
        }

        // Check if order uses ViettelPost shipping method
        if (1 > 2) {
            $shipping_methods = $order->get_shipping_methods();
            // print_r($shipping_methods, true);
            $has_viettelpost = false;

            foreach ($shipping_methods as $shipping_method) {
                if ($shipping_method->get_method_id() === 'viettelpost') {
                    $has_viettelpost = true;
                    break;
                }
            }
            // var_dump($has_viettelpost);

            if (!$has_viettelpost) {
                // die(__CLASS__ . ':' . __LINE__);
                return;
            }
            // die(__CLASS__ . ':' . __LINE__);
        }

        // Queue the order for creation
        if ($auto_create == 'queue') {
            $this->add_order_to_queue($order_id);
            return;
        }

        // Create ViettelPost order
        $this->create_viettelpost_order($order_id);
    }

    /**
     * Create ViettelPost order
     */
    public function create_viettelpost_order($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('invalid_order', 'Đơn hàng không hợp lệ');
        }
        // echo '<pre>' . print_r($order, true) . '</pre>';
        // die(__FILE__ . ':' . __LINE__);

        // Lấy số đơn hàng ViettelPost
        $order_number = $order->get_meta('_viettelpost_order_number', true);
        // nếu có rồi thì báo lỗi vận đơn đã tồn tại
        if ($order_number) {
            return new WP_Error('order_exists', 'Vận đơn ViettelPost đã tồn tại #' . $order_number);
        }

        // Prepare order data
        $order_data = $this->prepare_order_data($order);
        if (is_wp_error($order_data)) {
            return $order_data;
        }
        // put data vào file để test
        // file_put_contents(__DIR__ . '/' . basename(__FILE__, '.php') . '.log', print_r($order_data, true));
        // in ra dưới dạng mảng để xem
        // echo '<pre>' . print_r($order_data, true) . '</pre>';
        // die(__FILE__ . ':' . __LINE__);

        // Call API to create order
        $api = new EchBay_ViettelPost_API();
        $order_data = $api->get_order_service($order_data);
        // echo '<pre>' . print_r($order_data, true) . '</pre>';
        // die(__FILE__ . ':' . __LINE__);
        $result = $api->create_order($order_data);
        // var_dump($result);
        // echo '<pre>' . print_r($result, true) . '</pre>';
        // die(__FILE__ . ':' . __LINE__);

        if (is_wp_error($result)) {
            file_put_contents(__DIR__ . '/' . basename(__FILE__, '.php') . '.error_log', print_r($result, true));
            $order->add_order_note(
                sprintf('Lỗi tạo vận đơn ViettelPost: %s', $result->get_error_message())
            );
            return $result;
        }
        // die(__FILE__ . ':' . __LINE__);

        if (isset($result['status']) && $result['status'] == 200 && isset($result['data']['ORDER_NUMBER'])) {
            $order_number = $result['data']['ORDER_NUMBER'];

            // chuyển trạng thái đơn sau khi tạo vận đơn
            $update_status = get_option('echbay_viettelpost_update_status', '');
            if (!empty($update_status)) {
                $order->update_status($update_status);
            }

            // Save order number using HPOS compatible methods
            $order->update_meta_data('_viettelpost_order_number', $order_number);
            $order->update_meta_data('_viettelpost_created_date', current_time('mysql'));
            $order->update_meta_data('_viettelpost_service_type', $order_data['ORDER_SERVICE']);
            $order->save();

            // Add order note
            $order->add_order_note(
                sprintf('Đã tạo vận đơn ViettelPost: %s', $order_number)
            );

            return $order_number;
        }

        $error_message = isset($result['message']) ? $result['message'] : 'Không thể tạo vận đơn';
        $error_message = isset($result['message']) ? $result['message'] : 'Không thể tạo vận đơn';
        $order->add_order_note(
            sprintf('Lỗi tạo vận đơn ViettelPost: %s', $error_message)
        );

        return new WP_Error('create_order_failed', $error_message);
    }

    /**
     * Prepare order data for ViettelPost API
     */
    private function prepare_order_data($order)
    {
        // Get shipping address
        $shipping_address = array(
            'name' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
            'address' => $order->get_shipping_address_1(),
            'address_2' => $order->get_shipping_address_2(),
            'phone' => $order->get_billing_phone(),
            'email' => $order->get_billing_email(),
            'province' => $order->get_shipping_state(),
            'district' => $order->get_shipping_city(),
            // 'ward' => $order->get_meta('_shipping_ward', true), // Get ward from custom field
            // 'postcode' => $order->get_shipping_postcode()
        );

        // Fallback to billing address if shipping is empty
        if (empty($shipping_address['name'])) {
            $shipping_address['name'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        }
        if (empty($shipping_address['address'])) {
            $shipping_address['address'] = $order->get_billing_address_1() . ' ' . $order->get_billing_address_2();
        }
        if (empty($shipping_address['province'])) {
            $shipping_address['province'] = $order->get_billing_state();
        }
        if (empty($shipping_address['district'])) {
            $shipping_address['district'] = $order->get_billing_city();
        }
        // if (empty($shipping_address['ward'])) {
        // $shipping_address['ward'] = $order->get_meta('_billing_ward', true);
        // }
        if (empty($shipping_address['address_2'])) {
            $shipping_address['address_2'] = $order->get_billing_address_2();
        }

        // Validate required fields
        if (empty($shipping_address['name']) || empty($shipping_address['address']) || empty($shipping_address['phone'])) {
            return new WP_Error('incomplete_address', 'Thông tin địa chỉ nhận hàng không đầy đủ');
        }

        // Calculate total weight and prepare products
        $total_weight = 0;
        $product_description = '';
        $product_names = array();

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) continue;

            $weight = $product->get_weight();
            if (empty($weight)) {
                $weight = get_option('echbay_viettelpost_default_weight', 500) / 1000; // Convert to kg
            }

            $total_weight += $weight * $item->get_quantity();
            $product_names[] = $product->get_name() . ' x' . $item->get_quantity();
        }

        $product_description = implode(', ', $product_names);
        $total_weight_grams = max($total_weight * 1000, 100); // Minimum 100g

        // Get service type from shipping method
        $service_type = get_option('echbay_viettelpost_default_service', 'STK');
        foreach ($order->get_shipping_methods() as $shipping_method) {
            if ($shipping_method->get_method_id() === 'viettelpost') {
                $meta_data = $shipping_method->get_meta_data();
                foreach ($meta_data as $meta) {
                    if ($meta->key === 'service_type') {
                        $service_type = $meta->value;
                        break;
                    }
                }
                break;
            }
        }

        // Format address data
        $api = new EchBay_ViettelPost_API();
        $address_data = $api->format_address($shipping_address);

        // Get payment method
        $payment_method = $order->get_payment_method();
        $total = $order->get_total();

        // Prepare final order data
        $order_data = array_merge($address_data, array(
            'PRODUCT_NAME' => $product_description,
            'PRODUCT_DESCRIPTION' => $product_description,
            'PRODUCT_QUANTITY' => 1,
            'PRODUCT_PRICE' => $total,
            'PRODUCT_WEIGHT' => $total_weight_grams,
            'PRODUCT_TYPE' => 'HH',
            /**
             * ORDER_PAYMENT
             * Order type
             * 1: Uncollect money
             * 2: Collect express fee and price of goods.
             * 3: Collect price of goods
             * 4: Collect express fee
             */
            'ORDER_PAYMENT' => $payment_method === 'cod' ? 3 : 1,
            'ORDER_SERVICE' => $service_type,
            'ORDER_SERVICE_ADD' => '',
            'ORDER_VOUCHER' => '',
            'ORDER_NOTE' => $order->get_customer_note(),
            // collection money (that customers want VTP to collect from receivers)
            // 'MONEY_COLLECTION' => $payment_method === 'cod' ? $total : $order->get_shipping_total(),
            'MONEY_COLLECTION' => $payment_method === 'cod' ? $total : 0,
            // 'MONEY_COLLECTION' => $order->get_shipping_total(),
            'MONEY_TOTALFEE' => 0, // Will be calculated by API
            // 'MONEY_FEECOD' => $payment_method === 'cod' ? 0 : 0,
            'MONEY_FEECOD' => 0,
            'MONEY_FEEVAS' => 0,
            'MONEY_FEEINSURRANCE' => 0,
            'LIST_ITEM' => array(
                array(
                    'PRODUCT_NAME' => $product_description,
                    'PRODUCT_PRICE' => $total,
                    'PRODUCT_WEIGHT' => $total_weight_grams,
                    'PRODUCT_QUANTITY' => 1
                )
            )
        ));

        return $order_data;
    }

    /**
     * Add order actions
     */
    public function add_order_actions($actions)
    {
        $actions['create_viettelpost_order'] = 'Tạo vận đơn ViettelPost';
        $actions['print_viettelpost_label'] = 'In nhãn ViettelPost';
        $actions['track_viettelpost_order'] = 'Tra cứu vận đơn ViettelPost';

        return $actions;
    }

    /**
     * Create ViettelPost order action
     */
    public function create_viettelpost_order_action($order)
    {
        $result = $this->create_viettelpost_order($order->get_id());

        if (is_wp_error($result)) {
            WC_Admin_Settings::add_error($result->get_error_message());
        } else {
            WC_Admin_Settings::add_message('Đã tạo vận đơn ViettelPost thành công');
        }
    }

    /**
     * Print ViettelPost label action
     */
    public function print_viettelpost_label_action($order)
    {
        $order_number = $order->get_meta('_viettelpost_order_number', true);

        if (empty($order_number)) {
            WC_Admin_Settings::add_error('Đơn hàng chưa có vận đơn ViettelPost');
            return;
        }

        $api = new EchBay_ViettelPost_API();
        $result = $api->print_order($order_number);

        if (is_wp_error($result)) {
            WC_Admin_Settings::add_error($result->get_error_message());
        } else {
            WC_Admin_Settings::add_message('Đã gửi yêu cầu in nhãn');
        }
    }

    /**
     * Track ViettelPost order action
     */
    public function track_viettelpost_order_action($order)
    {
        $this->update_tracking_info($order->get_id());
        WC_Admin_Settings::add_message('Đã cập nhật thông tin theo dõi');
    }

    /**
     * Add order meta boxes
     */
    public function add_order_meta_boxes()
    {
        $screen = wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';

        add_meta_box(
            'viettelpost-order-info',
            'Thông tin ViettelPost',
            array($this, 'order_meta_box_content'),
            $screen,
            'side',
            'default'
        );
    }

    /**
     * Order meta box content
     */
    public function order_meta_box_content($post_or_order_object)
    {
        $order = ($post_or_order_object instanceof WP_Post) ? wc_get_order($post_or_order_object->ID) : $post_or_order_object;

        if (!$order) {
            return;
        }

        $order_number = $order->get_meta('_viettelpost_order_number', true);
        $created_date = $order->get_meta('_viettelpost_created_date', true);
        $service_type = $order->get_meta('_viettelpost_service_type', true);
        $tracking_info = $order->get_meta('_viettelpost_tracking_info', true);

        echo '<div class="viettelpost-order-info">';

        if ($order_number) {
            echo '<p><strong>' . 'Mã vận đơn:' . '</strong> ' . esc_html($order_number) . '</p>';
            echo '<p><strong>' . 'Ngày tạo:' . '</strong> ' . esc_html($created_date) . '</p>';
            echo '<p><strong>' . 'Dịch vụ:' . '</strong> ' . esc_html($service_type) . '</p>';

            if ($tracking_info) {
                echo '<h4>' . 'Trạng thái vận chuyển:' . '</h4>';
                echo '<div class="tracking-info">';
                foreach ($tracking_info as $track) {
                    echo '<p><strong>' . esc_html($track['date']) . ':</strong> ' . esc_html($track['status']) . '</p>';
                }
                echo '</div>';
            }

            echo '<p>';
            echo '<button type="button" class="button viettelpost-track-order" data-order-id="' . $order->get_id() . '">' . 'Cập nhật tracking' . '</button> ';
            echo '<button type="button" class="button viettelpost-print-label" data-order-id="' . $order->get_id() . '">' . 'In nhãn' . '</button>';
            echo '</p>';
        } else {
            echo '<p>' . 'Chưa tạo vận đơn ViettelPost' . '</p>';
            echo '<button type="button" class="button button-primary viettelpost-create-order" data-order-id="' . $order->get_id() . '">' . 'Tạo vận đơn' . '</button>';
        }

        echo '</div>';

        // Add JavaScript
        wp_enqueue_script('viettelpost-admin-order', ECHBAY_VIETTELPOST_PLUGIN_URL . 'assets/admin-order.js', array('jquery'), ECHBAY_VIETTELPOST_DEBUG, true);
        wp_localize_script('viettelpost-admin-order', 'viettelpost_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('viettelpost_order_actions')
        ));
    }

    /**
     * Display ViettelPost info in admin order details
     */
    public function display_viettelpost_info($order)
    {
        $order_number = $order->get_meta('_viettelpost_order_number', true);

        if ($order_number) {
            echo '<div class="viettelpost-info">';
            echo '<h3>' . 'Thông tin ViettelPost' . '</h3>';
            echo '<p><strong>' . 'Mã vận đơn:' . '</strong> ' . esc_html($order_number) . '</p>';
            echo '</div>';
        }
    }

    /**
     * Display tracking info on frontend
     */
    public function display_tracking_info_frontend($order)
    {
        $order_number = $order->get_meta('_viettelpost_order_number', true);
        $tracking_info = $order->get_meta('_viettelpost_tracking_info', true);

        if ($order_number && $tracking_info) {
            echo '<h2>' . 'Theo dõi vận chuyển' . '</h2>';
            echo '<p><strong>' . 'Mã vận đơn:' . '</strong> ' . esc_html($order_number) . '</p>';
            echo '<div class="tracking-timeline">';
            foreach ($tracking_info as $track) {
                echo '<div class="tracking-item">';
                echo '<span class="tracking-date">' . esc_html($track['date']) . '</span>';
                echo '<span class="tracking-status">' . esc_html($track['status']) . '</span>';
                echo '</div>';
            }
            echo '</div>';
        }
    }

    /**
     * Update tracking info
     */
    public function update_tracking_info($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $order_number = $order->get_meta('_viettelpost_order_number', true);

        if (empty($order_number)) {
            return;
        }

        $api = new EchBay_ViettelPost_API();
        $result = $api->get_order_status($order_number);

        if (!is_wp_error($result) && isset($result['data'])) {
            $order->update_meta_data('_viettelpost_tracking_info', $result['data']);
            $order->update_meta_data('_viettelpost_last_tracking_update', current_time('mysql'));
            $order->save();
        }
    }

    /**
     * AJAX create order
     */
    public function ajax_create_order()
    {
        check_ajax_referer('viettelpost_order_actions', 'nonce');

        if (!current_user_can('edit_shop_orders')) {
            wp_die('Bạn không có quyền thực hiện thao tác này');
        }

        $order_id = intval($_POST['order_id']);
        $result = $this->create_viettelpost_order($order_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success('Đã tạo vận đơn thành công');
        }
    }

    /**
     * AJAX track order
     */
    public function ajax_track_order()
    {
        check_ajax_referer('viettelpost_order_actions', 'nonce');

        if (!current_user_can('edit_shop_orders')) {
            wp_die('Bạn không có quyền thực hiện thao tác này');
        }

        $order_id = intval($_POST['order_id']);
        $this->update_tracking_info($order_id);

        wp_send_json_success('Đã cập nhật thông tin tracking');
    }

    /**
     * AJAX print label
     */
    public function ajax_print_label()
    {
        check_ajax_referer('viettelpost_order_actions', 'nonce');

        if (!current_user_can('edit_shop_orders')) {
            wp_die('Bạn không có quyền thực hiện thao tác này');
        }

        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error('Đơn hàng không hợp lệ');
        }

        $order_number = $order->get_meta('_viettelpost_order_number', true);

        if (empty($order_number)) {
            wp_send_json_error('Đơn hàng chưa có vận đơn ViettelPost');
        }

        $api = new EchBay_ViettelPost_API();
        $result = $api->print_order($order_number);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success('Đã gửi yêu cầu in nhãn');
        }
    }

    /**
     * Add order to queue
     */
    public function add_order_to_queue($order_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'echbay_viettelpost_queue';

        // Check if order already exists in queue
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE order_id = %d AND status IN ('pending', 'processing')",
            $order_id
        ));

        if ($existing) {
            return false; // Already in queue
        }

        // Insert order into queue
        $result = $wpdb->insert(
            $table_name,
            array(
                'order_id' => $order_id,
                'attempts' => 0,
                'max_attempts' => 3,
                'created_at' => current_time('mysql'),
                'status' => 'pending'
            ),
            array('%d', '%d', '%d', '%s', '%s')
        );

        return $result !== false;
    }

    /**
     * Process queue - called by cron
     */
    public function process_queue()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'echbay_viettelpost_queue';

        // Get pending orders from queue
        $orders = $wpdb->get_results(
            "SELECT * FROM $table_name 
             WHERE status = 'pending' 
             AND attempts < max_attempts 
             ORDER BY created_at ASC 
             LIMIT 10"
        );

        foreach ($orders as $queue_item) {
            // Update status to processing
            $wpdb->update(
                $table_name,
                array('status' => 'processing'),
                array('id' => $queue_item->id),
                array('%s'),
                array('%d')
            );

            // Try to create ViettelPost order
            $result = $this->create_viettelpost_order($queue_item->order_id);

            if (is_wp_error($result)) {
                // Failed - increment attempts
                $wpdb->update(
                    $table_name,
                    array(
                        'attempts' => $queue_item->attempts + 1,
                        'status' => $queue_item->attempts + 1 >= $queue_item->max_attempts ? 'failed' : 'pending',
                        'error_message' => $result->get_error_message()
                    ),
                    array('id' => $queue_item->id),
                    array('%d', '%s', '%s'),
                    array('%d')
                );
            } else {
                // Success - mark as completed
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
        }
    }

    /**
     * Get queue statistics
     */
    public function get_queue_stats()
    {
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

        return $stats;
    }
}
