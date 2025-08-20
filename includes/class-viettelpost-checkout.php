<?php

/**
 * ViettelPost Checkout Customization Class
 *
 * @package EchBay_ViettelPost_WooCommerce
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ViettelPost Checkout handler
 */
class EchBay_ViettelPost_Checkout
{

    /**
     * Constructor
     */
    public function __construct()
    {
        // Only customize checkout if enabled in settings
        if (get_option('echbay_viettelpost_customize_checkout', 'yes') !== 'yes') {
            return;
        }

        // Customize checkout fields
        add_filter('woocommerce_checkout_fields', array($this, 'customize_checkout_fields'));
        // add_filter('woocommerce_billing_fields', array($this, 'customize_billing_fields'));
        // add_filter('woocommerce_shipping_fields', array($this, 'customize_shipping_fields'));

        // AJAX handlers for dynamic dropdowns
        add_action('wp_ajax_viettelpost_get_checkout_districts', array($this, 'get_checkout_districts_ajax'));
        add_action('wp_ajax_nopriv_viettelpost_get_checkout_districts', array($this, 'get_checkout_districts_ajax'));
        add_action('wp_ajax_viettelpost_get_checkout_wards', array($this, 'get_checkout_wards_ajax'));
        add_action('wp_ajax_nopriv_viettelpost_get_checkout_wards', array($this, 'get_checkout_wards_ajax'));

        // Enqueue checkout scripts
        add_action('wp_enqueue_scripts', array($this, 'checkout_scripts'));

        // Force show state fields for Vietnam
        // add_filter('woocommerce_country_locale', array($this, 'force_show_state_field'));

        // Validate checkout fields
        // add_action('woocommerce_checkout_process', array($this, 'validate_checkout_fields'));

        // Save custom fields
        // add_action('woocommerce_checkout_update_order_meta', array($this, 'save_checkout_fields'));

        // Display custom fields in admin
        // add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_custom_fields_admin'));
        // add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'display_custom_fields_admin_shipping'));

        // Display on order details
        // add_action('woocommerce_order_details_after_customer_details', array($this, 'display_custom_fields_frontend'));

        add_filter('woocommerce_get_order_address', array($this, 'render_woocommerce_get_order_address'), 99, 2);  //API V1
    }

    /**
     * Customize checkout fields
     */
    public function customize_checkout_fields($fields)
    {
        WC()->customer->set_shipping_country('VN');

        // Customize billing fields
        $fields['billing'] = $this->customize_billing_fields($fields['billing']);

        // Customize shipping fields
        $fields['shipping'] = $this->customize_shipping_fields($fields['shipping']);

        return $fields;
    }

    /**
     * Customize billing fields
     */
    public function customize_billing_fields($fields, $prefix = 'billing_')
    {
        // xóa trường không cần thiết
        unset($fields[$prefix . 'first_name']);
        // unset($fields[$prefix . 'address_2']);
        unset($fields[$prefix . 'company']);
        unset($fields[$prefix . 'postcode']);
        unset($fields[$prefix . 'country']);

        // Last name field
        $fields[$prefix . 'last_name']['label'] = 'Họ và tên';
        $fields[$prefix . 'last_name']['class'] = array('form-row-wide');
        // Address 1 field
        $fields[$prefix . 'address_1']['class'] = array('form-row-wide');
        $fields[$prefix . 'address_1']['priority'] = 90;
        // Phone field
        $fields[$prefix . 'phone']['class'] = array('form-row-first');
        // Email field
        $fields[$prefix . 'email']['class'] = array('form-row-last');
        $fields[$prefix . 'email']['required'] = false;

        // Get provinces data
        $provinces = get_option('echbay_viettelpost_provinces', array());
        $province_options = array('' => 'Chọn tỉnh/thành phố');

        foreach ($provinces as $province) {
            $province_options[$province['PROVINCE_ID']] = $province['PROVINCE_NAME'];
        }

        // Update state field with provinces dropdown
        $fields[$prefix . 'state'] = array(
            'label' => 'Tỉnh/Thành phố',
            'required' => true,
            'type' => 'select',
            'options' => $province_options,
            'class' => array('form-row-wide', 'address-field', 'update_totals_on_change'),
            'priority' => 80,
            'custom_attributes' => array(
                'data-field-type' => 'province',
            )
        );

        // Add district field (replace city if exists or add new)
        $fields[$prefix . 'city'] = array(
            'label' => 'Quận/Huyện',
            'required' => true,
            'type' => 'select',
            'options' => array('' => 'Chọn quận/huyện'),
            'class' => array('form-row-first', 'address-field'),
            'priority' => 85,
            'custom_attributes' => array(
                'data-field-type' => 'district',
                'data-depends-on' => $prefix . 'state'
            )
        );

        // Add ward field (using address_2)
        $fields[$prefix . 'address_2'] = array(
            'label' => 'Phường/Xã',
            'required' => true,
            'type' => 'select',
            'options' => array('' => 'Chọn phường/xã'),
            'class' => array('form-row-last', 'address-field'),
            'priority' => 87,
            'custom_attributes' => array(
                'data-field-type' => 'ward',
                'data-depends-on' => $prefix . 'city'
            )
        );

        return $fields;
    }

    /**
     * Customize shipping fields
     */
    public function customize_shipping_fields($fields)
    {
        return $this->customize_billing_fields($fields, 'shipping_');
    }

    /**
     * Get districts via AJAX for checkout
     */
    public function get_checkout_districts_ajax()
    {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'viettelpost_checkout_ajax')) {
            wp_send_json_error('Nonce verification failed');
        }

        $province_id = intval($_POST['province_id']);

        if (!$province_id) {
            wp_send_json_error('ID tỉnh không hợp lệ');
        }

        // Try to get from cache first
        $districts = get_option('echbay_viettelpost_districts_' . $province_id, array());

        if (empty($districts)) {
            // If not cached, get from API
            $api = new EchBay_ViettelPost_API();
            $result = $api->get_districts($province_id);

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            } elseif (isset($result['data'])) {
                $districts = $result['data'];
                // Cache for future use
                update_option('echbay_viettelpost_districts_' . $province_id, $districts);
            } else {
                wp_send_json_error('Không thể lấy danh sách quận/huyện');
            }
        }

        wp_send_json_success($districts);
    }

    /**
     * Get wards via AJAX for checkout
     */
    public function get_checkout_wards_ajax()
    {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'viettelpost_checkout_ajax')) {
            wp_send_json_error('Nonce verification failed');
        }

        $district_id = intval($_POST['district_id']);

        if (!$district_id) {
            wp_send_json_error('ID quận/huyện không hợp lệ');
        }

        // Try to get from cache first
        $wards = get_option('echbay_viettelpost_wards_' . $district_id, array());

        if (empty($wards)) {
            // If not cached, get from API
            $api = new EchBay_ViettelPost_API();
            $result = $api->get_wards($district_id);

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            } elseif (isset($result['data'])) {
                $wards = $result['data'];
                // Cache for future use
                update_option('echbay_viettelpost_wards_' . $district_id, $wards);
            } else {
                wp_send_json_error('Không thể lấy danh sách phường/xã');
            }
        }

        wp_send_json_success($wards);
    }

    /**
     * Enqueue checkout scripts
     */
    public function checkout_scripts()
    {
        if (is_checkout()) {
            // Enqueue CSS for checkout styling
            wp_enqueue_style(
                'echbay-viettelpost-checkout',
                ECHBAY_VIETTELPOST_PLUGIN_URL . 'assets/checkout.css',
                array(),
                ECHBAY_VIETTELPOST_DEBUG
            );

            // Enqueue JavaScript for checkout functionality
            wp_enqueue_script(
                'echbay-viettelpost-checkout',
                ECHBAY_VIETTELPOST_PLUGIN_URL . 'assets/checkout.js',
                array('jquery'),
                ECHBAY_VIETTELPOST_DEBUG,
                true
            );

            wp_localize_script('echbay-viettelpost-checkout', 'viettelpost_checkout_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('viettelpost_checkout_ajax'),
                'messages' => array(
                    'loading' => 'Đang tải...',
                    'select_province' => 'Chọn tỉnh/thành phố',
                    'select_district' => 'Chọn quận/huyện',
                    'select_ward' => 'Chọn phường/xã'
                )
            ));
        }
    }

    /**
     * Force show state field for Vietnam
     */
    public function force_show_state_field($country_locale)
    {
        // Force show state field for Vietnam
        if (isset($country_locale['VN'])) {
            if (isset($country_locale['VN']['state'])) {
                unset($country_locale['VN']['state']);
            }
        }

        // Ensure state field is visible for VN
        $country_locale['VN']['state'] = array(
            'required' => true,
            'hidden' => false
        );

        return $country_locale;
    }

    /**
     * Validate checkout fields
     */
    public function validate_checkout_fields()
    {
        // Validate billing address_2 (ward)
        if (empty($_POST['billing_address_2'])) {
            wc_add_notice('Vui lòng chọn Phường/Xã cho địa chỉ thanh toán.', 'error');
        }

        // Validate shipping address_2 (ward) if shipping to different address
        if (!empty($_POST['ship_to_different_address']) && empty($_POST['shipping_address_2'])) {
            wc_add_notice('Vui lòng chọn Phường/Xã cho địa chỉ giao hàng.', 'error');
        }
    }

    /**
     * Save checkout fields
     */
    public function save_checkout_fields($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Save billing address_2 (ward) - address_2 is automatically saved by WooCommerce
        // But we still save the ward name for display purposes
        if (!empty($_POST['billing_address_2'])) {
            $ward_id = intval($_POST['billing_address_2']);
            $district_id = intval($_POST['billing_city']);
            $wards = get_option('echbay_viettelpost_wards_' . $district_id, array());

            foreach ($wards as $ward) {
                if ($ward['WARDS_ID'] == $ward_id) {
                    $order->update_meta_data('_billing_ward_name', $ward['WARDS_NAME']);
                    break;
                }
            }
        }

        // Save shipping address_2 (ward) - address_2 is automatically saved by WooCommerce  
        // But we still save the ward name for display purposes
        if (!empty($_POST['shipping_address_2'])) {
            $ward_id = intval($_POST['shipping_address_2']);
            $district_id = intval($_POST['shipping_city']);
            $wards = get_option('echbay_viettelpost_wards_' . $district_id, array());

            foreach ($wards as $ward) {
                if ($ward['WARDS_ID'] == $ward_id) {
                    $order->update_meta_data('_shipping_ward_name', $ward['WARDS_NAME']);
                    break;
                }
            }
        }

        $order->save();
    }

    /**
     * Display custom fields in admin billing
     */
    public function display_custom_fields_admin($order)
    {
        $billing_ward_name = $order->get_meta('_billing_ward_name', true);

        if ($billing_ward_name) {
            echo '<p><strong>' . 'Phường/Xã:' . '</strong> ' . esc_html($billing_ward_name) . '</p>';
        }
    }

    /**
     * Display custom fields in admin shipping
     */
    public function display_custom_fields_admin_shipping($order)
    {
        $shipping_ward_name = $order->get_meta('_shipping_ward_name', true);

        if ($shipping_ward_name) {
            echo '<p><strong>' . 'Phường/Xã:' . '</strong> ' . esc_html($shipping_ward_name) . '</p>';
        }
    }

    /**
     * Display custom fields on frontend
     */
    public function display_custom_fields_frontend($order)
    {
        $billing_ward_name = $order->get_meta('_billing_ward_name', true);
        $shipping_ward_name = $order->get_meta('_shipping_ward_name', true);

        if ($billing_ward_name || $shipping_ward_name) {
            echo '<h2>' . 'Thông tin địa chỉ chi tiết' . '</h2>';
            echo '<table class="woocommerce-table woocommerce-table--customer-details shop_table customer_details">';

            if ($billing_ward_name) {
                echo '<tr><th>' . 'Phường/Xã (Thanh toán):' . '</th><td>' . esc_html($billing_ward_name) . '</td></tr>';
            }

            if ($shipping_ward_name) {
                echo '<tr><th>' . 'Phường/Xã (Giao hàng):' . '</th><td>' . esc_html($shipping_ward_name) . '</td></tr>';
            }

            echo '</table>';
        }
    }

    /**
     * Get province name by ID
     */
    public function get_province_name($province_id)
    {
        if (!$province_id || !is_numeric($province_id)) {
            return $province_id;
        }

        $provinces = get_option('echbay_viettelpost_provinces', array());
        // print_r($provinces);

        foreach ($provinces as $province) {
            if ($province['PROVINCE_ID'] == $province_id) {
                return $province['PROVINCE_NAME'];
            }
        }

        return '#' . $province_id;
    }

    /**
     * Get district name by ID
     */
    public function get_district_name($district_id, $province_id)
    {
        if (!$district_id || !is_numeric($district_id) || !$province_id || !is_numeric($province_id)) {
            return $district_id;
        }

        $districts = get_option('echbay_viettelpost_districts_' . $province_id, array());

        if (empty($districts)) {
            // If not cached, get from API
            $api = new EchBay_ViettelPost_API();
            $result = $api->get_districts($province_id);
            if (is_wp_error($result)) {
                // lưu error log
                error_log($result->get_error_message());
            } elseif (isset($result['data'])) {
                $districts = $result['data'];
                // Cache for future use
                update_option('echbay_viettelpost_districts_' . $province_id, $districts);
            }
        }

        foreach ($districts as $district) {
            if ($district['DISTRICT_ID'] == $district_id) {
                return $district['DISTRICT_NAME'];
            }
        }

        return '#' . $district_id;
    }

    /**
     * Get ward name by ID
     */
    public function get_ward_name($ward_id, $district_id)
    {
        if (!$ward_id || !is_numeric($ward_id) || !$district_id || !is_numeric($district_id)) {
            return $ward_id;
        }

        $wards = get_option('echbay_viettelpost_wards_' . $district_id, array());

        if (empty($wards)) {
            // If not cached, get from API
            $api = new EchBay_ViettelPost_API();
            $result = $api->get_wards($district_id);
            if (is_wp_error($result)) {
                // lưu error log
                error_log($result->get_error_message());
            } elseif (isset($result['data'])) {
                $wards = $result['data'];
                // Cache for future use
                update_option('echbay_viettelpost_wards_' . $district_id, $wards);
            }
        }

        foreach ($wards as $ward) {
            if ($ward['WARDS_ID'] == $ward_id) {
                return $ward['WARDS_NAME'];
            }
        }

        return '#' . $ward_id;
    }

    function render_woocommerce_get_order_address($value, $type)
    {
        if ($type == 'billing' || $type == 'shipping') {
            // echo $type . '<br>' . PHP_EOL;
            if (
                1 > 2
                && isset($_GET['page']) && $_GET['page'] == 'wc-orders'
                && isset($_GET['action']) && $_GET['action'] == 'edit'
                && isset($_GET['id']) && is_numeric($_GET['id'])
            ) {
                echo '<pre>' . print_r($value, true) . '</pre>';
            }
            // die(__FILE__ . ':' . __LINE__);
            if (isset($value['state'])) {
                // Get city name
                if (isset($value['city'])) {
                    // Get ward name
                    if (isset($value['address_2'])) {
                        // Get ward name
                        $value['address_2'] = $this->get_ward_name($value['address_2'], $value['city']);
                    }

                    $value['city'] = $this->get_district_name($value['city'], $value['state']);
                }

                $value['state'] = $this->get_province_name($value['state']);
                // echo $value['state'] . '<br>' . PHP_EOL;
                // die(__FUNCTION__ . ':' . __LINE__);
                // đặt country = '' thì mới tính toán được tỉnh thành VN
                $value['country'] = '';
            }
        }
        return $value;
    }
}
