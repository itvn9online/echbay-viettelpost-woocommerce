<?php

/**
 * ViettelPost Shipping Method Class
 *
 * @package EchBay_ViettelPost_WooCommerce
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ViettelPost Shipping Method
 */
class EchBay_ViettelPost_Shipping_Method extends WC_Shipping_Method
{
    /**
     * Thuộc tính cấu hình shipping method
     */
    public $service_type;
    public $free_shipping_threshold;
    public $fallback_price;
    public $title;
    public $enabled;

    /**
     * Constructor
     */
    public function __construct($instance_id = 0)
    {
        $this->id = 'viettelpost';
        $this->instance_id = absint($instance_id);
        $this->method_title = 'ViettelPost';
        $this->method_description = 'Vận chuyển qua ViettelPost với tính phí tự động';

        $this->supports = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );

        $this->init();
    }

    /**
     * Initialize the method
     */
    public function init()
    {
        // Load the settings API
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title = $this->get_option('title');
        $this->enabled = $this->get_option('enabled');
        $this->service_type = $this->get_option('service_type');
        $this->free_shipping_threshold = $this->get_option('free_shipping_threshold');
        $this->fallback_price = $this->get_option('fallback_price');

        // Save settings
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    /**
     * Initialize form fields
     */
    public function init_form_fields()
    {
        $this->instance_form_fields = array(
            'enabled' => array(
                'title' => 'Kích hoạt',
                'type' => 'checkbox',
                'description' => 'Kích hoạt phương thức vận chuyển ViettelPost',
                'default' => 'yes'
            ),

            'title' => array(
                'title' => 'Tiêu đề',
                'type' => 'text',
                'description' => 'Tiêu đề hiển thị cho khách hàng khi chọn phương thức vận chuyển',
                'default' => 'Giao hàng ViettelPost',
                'desc_tip' => true
            ),

            'service_type' => array(
                'title' => 'Loại dịch vụ',
                'type' => 'select',
                'description' => 'Chọn loại dịch vụ vận chuyển ViettelPost',
                'default' => 'STK',
                'options' => ECHBAY_VIETTELPOST_ORDER_SERVICE,
                'desc_tip' => true
            ),

            'free_shipping_threshold' => array(
                'title' => 'Miễn phí vận chuyển từ',
                'type' => 'price',
                'description' => 'Đơn hàng từ giá trị này sẽ được miễn phí vận chuyển. Để trống để tắt tính năng.',
                'default' => '',
                'desc_tip' => true
            ),

            'fallback_price' => array(
                'title' => 'Phí dự phòng',
                'type' => 'price',
                'description' => 'Phí vận chuyển sẽ áp dụng khi không thể tính toán từ API ViettelPost',
                'default' => '30000',
                'desc_tip' => true
            )
        );
    }

    /**
     * Calculate shipping rates
     */
    public function calculate_shipping($package = array())
    {
        // Check if method is enabled
        if (!$this->enabled || $this->enabled === 'no') {
            return;
        }

        // Check if free shipping threshold is met
        if (!empty($this->free_shipping_threshold) && $package['contents_cost'] >= $this->free_shipping_threshold) {
            $rate = array(
                'id' => $this->get_rate_id(),
                'label' => $this->title . ' (Miễn phí)',
                'cost' => 0,
                'meta_data' => array(
                    'service_type' => $this->service_type
                )
            );

            $this->add_rate($rate);
            return;
        }

        // Calculate shipping cost via API
        $shipping_cost = $this->calculate_api_shipping_cost($package);

        // Use fallback price if API calculation fails
        if ($shipping_cost === false) {
            $shipping_cost = $this->fallback_price;
        }

        $rate = array(
            'id' => $this->get_rate_id(),
            'label' => $this->title,
            'cost' => $shipping_cost,
            'meta_data' => array(
                'service_type' => $this->service_type
            )
        );

        $this->add_rate($rate);
    }

    /**
     * Calculate shipping cost via ViettelPost API
     */
    private function calculate_api_shipping_cost($package)
    {
        // Get destination address
        $destination = $package['destination'];

        // Validate required fields
        if (empty($destination['state']) || empty($destination['city'])) {
            return false;
        }

        // Calculate total weight
        $total_weight = 0;
        $total_value = 0;

        foreach ($package['contents'] as $item_id => $values) {
            $product = $values['data'];
            $weight = $product->get_weight();

            // Use default weight if product weight is not set
            if (empty($weight)) {
                $weight = get_option('echbay_viettelpost_default_weight', 500) / 1000; // Convert to kg
            }

            $total_weight += $weight * $values['quantity'];
            $total_value += $values['line_total'];
        }

        // Convert weight to grams
        $total_weight_grams = $total_weight * 1000;

        // Prepare API data
        $api_data = array(
            'SENDER_PROVINCE' => get_option('echbay_viettelpost_sender_province'),
            'SENDER_DISTRICT' => get_option('echbay_viettelpost_sender_district'),
            'RECEIVER_PROVINCE' => $this->get_province_id_by_name($destination['state']),
            'RECEIVER_DISTRICT' => $this->get_district_id_by_name($destination['city']),
            'PRODUCT_TYPE' => 'HH',
            'PRODUCT_WEIGHT' => max($total_weight_grams, 100), // Minimum 100g
            'PRODUCT_PRICE' => $total_value,
            'ORDER_SERVICE' => $this->service_type,
            // 'ORDER_SERVICE' => 'SHT',
            'NATIONAL_TYPE' => 1
        );

        // Call API
        $api = new EchBay_ViettelPost_API();
        $result = $api->calculate_price($api_data);

        if (is_wp_error($result)) {
            return false;
        }

        if (isset($result['status']) && $result['status'] == 200 && isset($result['data'][0]['MONEY_TOTAL'])) {
            return $result['data'][0]['MONEY_TOTAL'];
        }

        return false;
    }

    /**
     * Get province ID by name
     */
    private function get_province_id_by_name($province_name)
    {
        $provinces = get_option('echbay_viettelpost_provinces', array());

        foreach ($provinces as $province) {
            if (strcasecmp($province['PROVINCE_NAME'], $province_name) === 0) {
                return $province['PROVINCE_ID'];
            }
        }

        return null;
    }

    /**
     * Get district ID by name
     */
    private function get_district_id_by_name($district_name)
    {
        $districts = get_option('echbay_viettelpost_districts', array());

        foreach ($districts as $district) {
            if (strcasecmp($district['DISTRICT_NAME'], $district_name) === 0) {
                return $district['DISTRICT_ID'];
            }
        }

        return null;
    }

    /**
     * Check if this shipping method is available
     */
    public function is_available($package)
    {
        // Check if ViettelPost is configured
        $username = get_option('echbay_viettelpost_username');
        $password = get_option('echbay_viettelpost_password');

        if (empty($username) || empty($password)) {
            return false;
        }

        // Check if sender address is configured
        $sender_province = get_option('echbay_viettelpost_sender_province');
        $sender_district = get_option('echbay_viettelpost_sender_district');

        if (empty($sender_province) || empty($sender_district)) {
            return false;
        }

        return parent::is_available($package);
    }
}
