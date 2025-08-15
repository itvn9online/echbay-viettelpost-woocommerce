<?php

/**
 * ViettelPost API Class
 *
 * @package EchBay_ViettelPost_WooCommerce
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ViettelPost API handler
 */
class EchBay_ViettelPost_API
{

    /**
     * API Base URLs
     */
    const API_BASE_URL_PRODUCTION = 'https://partner.viettelpost.vn/v2';
    const API_BASE_URL_DEVELOPMENT = 'https://partnerdev.viettelpost.vn/v2';

    /**
     * API Token
     */
    private $token;

    /**
     * Username
     */
    private $username;

    /**
     * Password
     */
    private $password;

    /**
     * Environment
     */
    private $environment;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->username = get_option('echbay_viettelpost_username', '');
        $this->password = get_option('echbay_viettelpost_password', '');
        $this->environment = get_option('echbay_viettelpost_environment', 'development');
        $this->token = get_transient('echbay_viettelpost_token');
    }

    /**
     * Get API base URL based on environment
     */
    private function get_api_base_url()
    {
        return $this->environment === 'production'
            ? self::API_BASE_URL_PRODUCTION
            : self::API_BASE_URL_DEVELOPMENT;
    }

    /**
     * Login and get token
     */
    public function login()
    {
        if (empty($this->username) || empty($this->password)) {
            return new WP_Error('missing_credentials', 'Thiếu thông tin đăng nhập API');
        }

        $response = wp_remote_post($this->get_api_base_url() . '/user/login', array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'USERNAME' => $this->username,
                'PASSWORD' => $this->password
            )),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['status']) && $data['status'] == 200 && isset($data['data']['token'])) {
            $this->token = $data['data']['token'];
            // Cache token for 23 hours
            set_transient('echbay_viettelpost_token', $this->token, 23 * HOUR_IN_SECONDS);
            return $this->token;
        }

        return new WP_Error('login_failed', isset($data['message']) ? $data['message'] : 'Đăng nhập thất bại');
    }

    /**
     * Make API request
     */
    private function make_request($endpoint, $method = 'GET', $data = array())
    {
        // Ensure we have a valid token
        if (empty($this->token)) {
            $login_result = $this->login();
            if (is_wp_error($login_result)) {
                return $login_result;
            }
        }
        // echo $this->token . '<br>' . PHP_EOL;

        $args = array(
            'method' => $method,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Token' => $this->token
            ),
            'timeout' => 30
        );

        if (!empty($data)) {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($this->get_api_base_url() . $endpoint, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        // If token expired, try to login again
        if (isset($decoded['status']) && $decoded['status'] == 401) {
            delete_transient('echbay_viettelpost_token');
            $this->token = null;
            $login_result = $this->login();
            if (is_wp_error($login_result)) {
                return $login_result;
            }

            // Retry the request with new token
            $args['headers']['Token'] = $this->token;
            $response = wp_remote_request($this->get_api_base_url() . $endpoint, $args);

            if (is_wp_error($response)) {
                return $response;
            }

            $body = wp_remote_retrieve_body($response);
            $decoded = json_decode($body, true);
        }

        return $decoded;
    }

    /**
     * Make API public request
     * Các request này không cần token
     */
    private function make_public_request($endpoint, $method = 'GET', $data = array())
    {
        $args = array(
            'method' => $method,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30
        );

        if (!empty($data)) {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($this->get_api_base_url() . $endpoint, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    /**
     * Get provinces
     */
    public function get_provinces($provinceId = '-1')
    {
        return $this->make_public_request('/categories/listProvinceById?provinceId=' . $provinceId);
    }

    /**
     * Get districts by province ID
     */
    public function get_districts($province_id)
    {
        return $this->make_public_request('/categories/listDistrict?provinceId=' . $province_id);
    }

    /**
     * Get wards by district ID
     */
    public function get_wards($district_id)
    {
        return $this->make_public_request('/categories/listWards?districtId=' . $district_id);
    }

    /**
     * Get inventory
     */
    public function get_inventorys()
    {
        return $this->make_request('/user/listInventory');
    }

    /**
     * Calculate shipping price
     */
    public function calculate_price($data)
    {
        $default_data = array(
            'PRODUCT_WEIGHT' => 1000, // gram
            'PRODUCT_PRICE' => 0,
            'MONEY_COLLECTION' => 0,
            'ORDER_SERVICE_ADD' => '',
            'ORDER_SERVICE' => 'STK', // Default service
            'NATIONAL_TYPE' => 1, // Domestic
        );

        $data = wp_parse_args($data, $default_data);

        return $this->make_request('/order/getPriceAll', 'POST', $data);
    }

    /**
     * Lấy danh sách dịch vụ theo ID địa danh
     */
    public function get_services_id_district($data)
    {
        $default_data = array(
            'PRODUCT_WEIGHT' => 1000, // gram
            'PRODUCT_PRICE' => 0,
            'MONEY_COLLECTION' => 0,
            /**
             * Loại bảng giá
             * 0: Bảng giá quốc tế
             * 1: Bảng giá trong nước
             */
            'TYPE' => 1,
        );

        $data = wp_parse_args($data, $default_data);

        return $this->make_request('/order/getPriceAll', 'POST', $data);
    }

    /**
     * xác định loại dịch vụ vận chuyển trước khi tạo đơn
     **/
    public function get_order_service($data)
    {
        $result = $this->get_services_id_district($data);
        // echo '<pre>' . print_r($result, true) . '</pre>';
        if (!empty($result)) {
            $has_service = false;

            // ưu tiện dịch vụ theo config trước
            if ($data['ORDER_SERVICE'] != '') {
                foreach ($result as $service) {
                    if (isset($service['MA_DV_CHINH']) && $service['MA_DV_CHINH'] == $data['ORDER_SERVICE']) {
                        $has_service = true;
                        break;
                    }
                }
            }

            // nếu không tìm được -> thử dịch vụ VCN
            if (!$has_service) {
                foreach ($result as $service) {
                    if (isset($service['MA_DV_CHINH']) && $service['MA_DV_CHINH'] == 'VCN') {
                        $data['ORDER_SERVICE'] = 'VCN';
                        $has_service = true;
                        break;
                    }
                }

                // vẫn không tìm được -> lấy dịch vụ đầu tiên
                if (!$has_service) {
                    $data['ORDER_SERVICE'] = $result[0]['MA_DV_CHINH'];
                }
            }
        }
        // echo '<pre>' . print_r($data, true) . '</pre>';
        // die(__FILE__ . ':' . __LINE__);

        // 
        return $data;
    }

    /**
     * Create order
     */
    public function create_order($data)
    {
        return $this->make_request('/order/createOrder', 'POST', $data);
    }

    /**
     * Get order detail
     */
    public function get_order_detail($order_number)
    {
        return $this->make_request('/order/getOrderInfoByOrderNumber?orderNumber=' . $order_number);
    }

    /**
     * Get order status
     */
    public function get_order_status($order_number)
    {
        return $this->make_request('/order/getOrderStatusByOrderNumber?orderNumber=' . $order_number);
    }

    /**
     * Print order
     */
    public function print_order($order_numbers)
    {
        if (is_array($order_numbers)) {
            $order_numbers = implode(',', $order_numbers);
        }

        return $this->make_request('/order/print?orderNumber=' . $order_numbers);
    }

    /**
     * Cancel order
     */
    public function cancel_order($order_number, $note = '')
    {
        $data = array(
            'ORDER_NUMBER' => $order_number,
            'NOTE' => $note
        );

        return $this->make_request('/order/cancelOrder', 'POST', $data);
    }

    /**
     * Get services
     */
    public function get_services()
    {
        return $this->make_request('/categories/listService');
    }

    /**
     * Format address for API
     */
    public function format_address($address_data)
    {
        if (1 > 2) {
            // global $tinh_thanhpho;
            // global $quan_huyen;

            // lấy thư mục plugins của wordpress
            $plugins_dir = WP_PLUGIN_DIR;
            // echo $plugins_dir;

            // nạp dữ liệu quận huyện
            $qh_path = $plugins_dir . '/woo-vietnam-checkout/cities/quan_huyen.php';
            if (is_file($qh_path)) {
                include_once $qh_path;

                // 
                if (!empty($quan_huyen)) {
                    // echo '<pre>' . print_r($quan_huyen, true) . '</pre>';

                    // Format district
                    foreach ($quan_huyen as $v) {
                        if ($v['matp'] == $address_data['province'] && $v['maqh'] == $address_data['district']) {
                            $address_data['district'] = $v['name'];
                            break;
                        }
                    }
                }

                // Tỉnh thành phố
                $ttp_path = $plugins_dir . '/woo-vietnam-checkout/cities/tinh_thanhpho.php';
                if (is_file($ttp_path)) {
                    include_once $ttp_path;

                    // Tỉnh thành phố
                    if (!empty($tinh_thanhpho)) {
                        // echo '<pre>' . print_r($tinh_thanhpho, true) . '</pre>';

                        // Format province
                        if (isset($tinh_thanhpho[$address_data['province']])) {
                            $address_data['province'] = $tinh_thanhpho[$address_data['province']];
                        }
                    }
                }
            }
        }
        // echo '<pre>' . print_r($address_data, true) . '</pre>';

        // Create an instance of the checkout class
        // $eb_checkout = new EchBay_ViettelPost_Checkout();

        // Format address data
        return array(
            'SENDER_FULLNAME' => get_option('echbay_viettelpost_sender_name', get_bloginfo('name')),
            'SENDER_ADDRESS' => get_option('echbay_viettelpost_sender_address', ''),
            'SENDER_PHONE' => get_option('echbay_viettelpost_sender_phone', ''),
            'SENDER_EMAIL' => get_option('echbay_viettelpost_sender_email', get_option('admin_email')),
            'SENDER_WARD' => get_option('echbay_viettelpost_sender_ward', ''),
            'SENDER_DISTRICT' => get_option('echbay_viettelpost_sender_district', ''),
            'SENDER_PROVINCE' => get_option('echbay_viettelpost_sender_province', ''),

            'RECEIVER_FULLNAME' => $address_data['name'],
            'RECEIVER_ADDRESS' => implode(', ', [
                $address_data['address'],
                // $eb_checkout->get_ward_name($address_data['address_2'], $address_data['district']),
                // $eb_checkout->get_district_name($address_data['district'], $address_data['province']),
                // $eb_checkout->get_province_name($address_data['province']),
            ]),
            // 'RECEIVER_ADDRESS' => 'Số 11 Duy Tân, phường Dịch Vọng Hậu, quận Cầu Giấy, thành phố Hà Nội',
            'RECEIVER_PHONE' => $address_data['phone'],
            'RECEIVER_EMAIL' => isset($address_data['email']) ? $address_data['email'] : '',
            // 'RECEIVER_WARD' => $address_data['ward'] ? $address_data['ward'] : '0',
            'RECEIVER_WARD' => $address_data['address_2'] ? $address_data['address_2'] : '0',
            'RECEIVER_DISTRICT' => $address_data['district'],
            'RECEIVER_PROVINCE' => $address_data['province'],
            // 'RECEIVER_DISTRICT' => '32',
            // 'RECEIVER_PROVINCE' => '2',
        );
    }
}
