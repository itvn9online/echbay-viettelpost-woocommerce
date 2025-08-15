<?php

/**
 * ViettelPost Settings Class
 *
 * @package EchBay_ViettelPost_WooCommerce
 * 
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ViettelPost Settings handler
 */
class EchBay_ViettelPost_Settings
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_filter('woocommerce_settings_tabs_array', array($this, 'add_settings_tab'), 50);
        add_action('woocommerce_settings_tabs_viettelpost', array($this, 'settings_tab'));
        add_action('woocommerce_update_options_viettelpost', array($this, 'update_settings'));
        add_action('woocommerce_settings_save_viettelpost', array($this, 'save_settings'));

        // AJAX handlers
        add_action('wp_ajax_viettelpost_test_connection', array($this, 'test_connection'));
        add_action('wp_ajax_viettelpost_sync_locations', array($this, 'sync_locations'));
        add_action('wp_ajax_viettelpost_get_districts', array($this, 'get_districts_ajax'));
        add_action('wp_ajax_viettelpost_get_wards', array($this, 'get_wards_ajax'));

        // Add admin scripts
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));

        // Add custom field types for WooCommerce settings
        add_action('woocommerce_admin_field_viettelpost_test_connection', array($this, 'output_test_connection_field'));
        add_action('woocommerce_admin_field_viettelpost_sync_locations', array($this, 'output_sync_locations_field'));
    }

    /**
     * Add settings tab to WooCommerce
     */
    public function add_settings_tab($settings_tabs)
    {
        $settings_tabs['viettelpost'] = 'ViettelPost';
        return $settings_tabs;
    }

    /**
     * Display settings tab content
     */
    public function settings_tab()
    {
        $current_section = isset($_GET['section']) ? sanitize_text_field($_GET['section']) : '';

        // Display section navigation
        $this->output_sections();

        if ($current_section === 'documentation') {
            $this->documentation_section();
        } else {
            woocommerce_admin_fields($this->get_settings());
        }
    }

    /**
     * Output section navigation
     */
    public function output_sections()
    {
        global $current_section;
        $current_section = isset($_GET['section']) ? sanitize_text_field($_GET['section']) : '';

        $sections = array(
            '' => 'Cài đặt',
            'documentation' => 'Tài liệu hướng dẫn'
        );

        if (count($sections) > 1) {
            echo '<ul class="subsubsub">';
            $array_keys = array_keys($sections);
            foreach ($sections as $id => $label) {
                echo '<li><a href="' . admin_url('admin.php?page=wc-settings&tab=viettelpost&section=' . sanitize_title($id)) . '" class="' . ($current_section == $id ? 'current' : '') . '">' . $label . '</a> ' . (end($array_keys) == $id ? '' : '|') . ' </li>';
            }
            echo '</ul><br class="clear" />';
        }
    }

    /**
     * Display documentation section
     */
    public function documentation_section()
    {
?>
        <div class="viettelpost-documentation">
            <h2>Tài liệu hướng dẫn ViettelPost WooCommerce</h2>

            <div class="viettelpost-docs-container">
                <!-- Getting Started -->
                <div class="viettelpost-docs-section">
                    <h3>1. Bắt đầu</h3>
                    <div class="viettelpost-docs-content">
                        <h4>Yêu cầu hệ thống</h4>
                        <ul>
                            <li>WordPress 5.8+</li>
                            <li>WooCommerce 5.0+</li>
                            <li>PHP 7.4+</li>
                            <li>Tài khoản ViettelPost Partner</li>
                        </ul>

                        <h4>Đăng ký tài khoản ViettelPost Partner</h4>
                        <ol>
                            <li>Tài liệu tham khảo: <a href="https://docs.google.com/document/d/1iIGeBMzf1bUtCd0sUC1CO-yohzA7o_gW/edit?tab=t.0" target="_blank">https://docs.google.com/document/d/1iIGeBMzf1bUtCd0sUC1CO-yohzA7o_gW/edit?tab=t.0</a></li>
                            <li>Development <a href="https://partnerdev.viettelpost.vn" target="_blank">https://partnerdev.viettelpost.vn</a></li>
                            <li>Production <a href="https://partner.viettelpost.vn" target="_blank">https://partner.viettelpost.vn</a></li>
                            <li>Đăng ký tài khoản mới hoặc đăng nhập nếu đã có</li>
                            <li>Hoàn thành thủ tục xác thực doanh nghiệp</li>
                            <li>Lấy thông tin đăng nhập API (Username/Password)</li>
                        </ol>
                    </div>
                </div>

                <!-- Installation -->
                <div class="viettelpost-docs-section">
                    <h3>2. Cài đặt và cấu hình</h3>
                    <div class="viettelpost-docs-content">
                        <h4>Bước 1: Cấu hình API</h4>
                        <ol>
                            <li>Vào <strong>WooCommerce > Settings > ViettelPost</strong></li>
                            <li>Nhập <strong>Username</strong> và <strong>Password</strong> ViettelPost Partner</li>
                            <li>Nhập <strong>Mã khách hàng</strong> nếu có</li>
                            <li>Nhấn <strong>"Kiểm tra kết nối"</strong> để test API</li>
                        </ol>

                        <h4>Bước 2: Cấu hình thông tin người gửi</h4>
                        <ol>
                            <li>Điền đầy đủ thông tin người gửi (tên, địa chỉ, điện thoại)</li>
                            <li>Chọn tỉnh/huyện/xã của kho hàng</li>
                            <li>Nhấn <strong>"Đồng bộ địa chỉ"</strong> để cập nhật danh sách</li>
                        </ol>

                        <h4>Bước 3: Thiết lập vận chuyển</h4>
                        <ol>
                            <li>Vào <strong>WooCommerce > Settings > Shipping</strong></li>
                            <li>Chọn <strong>Shipping Zone</strong> cần thiết lập</li>
                            <li>Thêm <strong>"ViettelPost"</strong> shipping method</li>
                            <li>Cấu hình title, service type, và phí dự phòng</li>
                        </ol>
                    </div>
                </div>

                <!-- Features -->
                <div class="viettelpost-docs-section">
                    <h3>3. Tính năng chính</h3>
                    <div class="viettelpost-docs-content">
                        <h4>Tính phí vận chuyển tự động</h4>
                        <p>Plugin sẽ tự động tính phí vận chuyển dựa trên:</p>
                        <ul>
                            <li>Địa chỉ người nhận</li>
                            <li>Cân nặng sản phẩm</li>
                            <li>Dịch vụ vận chuyển được chọn (VCN, VBS, VTK)</li>
                            <li>Giá trị đơn hàng (nếu có COD)</li>
                        </ul>

                        <h4>Tạo vận đơn tự động</h4>
                        <p>Có 2 cách tạo vận đơn:</p>
                        <ul>
                            <li><strong>Tự động:</strong> Khi đơn hàng chuyển sang trạng thái đã cấu hình</li>
                            <li><strong>Thủ công:</strong> Từ trang chi tiết đơn hàng hoặc bulk actions</li>
                        </ul>

                        <h4>Theo dõi vận chuyển</h4>
                        <ul>
                            <li>Cập nhật trạng thái tự động</li>
                            <li>Hiển thị timeline tracking cho khách hàng</li>
                            <li>Thông báo email khi có cập nhật (tùy chọn)</li>
                        </ul>
                    </div>
                </div>

                <!-- Order Management -->
                <div class="viettelpost-docs-section">
                    <h3>4. Quản lý đơn hàng</h3>
                    <div class="viettelpost-docs-content">
                        <h4>Trong trang chi tiết đơn hàng</h4>
                        <ul>
                            <li><strong>Meta box ViettelPost:</strong> Hiển thị mã vận đơn, trạng thái</li>
                            <li><strong>Tạo vận đơn:</strong> Button tạo vận đơn nếu chưa có</li>
                            <li><strong>Cập nhật tracking:</strong> Lấy thông tin mới nhất từ ViettelPost</li>
                            <li><strong>In nhãn:</strong> In nhãn vận đơn PDF</li>
                        </ul>

                        <h4>Bulk Actions</h4>
                        <p>Từ trang danh sách đơn hàng, bạn có thể:</p>
                        <ul>
                            <li>Tạo vận đơn cho nhiều đơn hàng cùng lúc</li>
                            <li>Cập nhật tracking cho nhiều đơn hàng</li>
                            <li>In nhãn hàng loạt</li>
                        </ul>
                    </div>
                </div>

                <!-- Troubleshooting -->
                <div class="viettelpost-docs-section">
                    <h3>5. Xử lý sự cố</h3>
                    <div class="viettelpost-docs-content">
                        <h4>Lỗi kết nối API</h4>
                        <ul>
                            <li>Kiểm tra lại username/password ViettelPost</li>
                            <li>Đảm bảo server có thể kết nối internet</li>
                            <li>Kiểm tra firewall/security plugins</li>
                            <li>Liên hệ ViettelPost nếu tài khoản bị khóa</li>
                        </ul>

                        <h4>Không tính được phí vận chuyển</h4>
                        <ul>
                            <li>Đồng bộ lại danh sách địa chỉ</li>
                            <li>Kiểm tra cấu hình địa chỉ người gửi</li>
                            <li>Đảm bảo địa chỉ người nhận đầy đủ</li>
                            <li>Kiểm tra cân nặng sản phẩm</li>
                        </ul>

                        <h4>Không tạo được vận đơn</h4>
                        <ul>
                            <li>Kiểm tra thông tin người nhận đầy đủ</li>
                            <li>Đảm bảo có số điện thoại người nhận</li>
                            <li>Kiểm tra cân nặng và giá trị đơn hàng</li>
                            <li>Xem WooCommerce logs để biết chi tiết lỗi</li>
                        </ul>
                    </div>
                </div>

                <!-- API Reference -->
                <div class="viettelpost-docs-section">
                    <h3>6. API Reference</h3>
                    <div class="viettelpost-docs-content">
                        <h4>Hooks và Filters</h4>

                        <h5>Actions</h5>
                        <pre><code>// Khi vận đơn được tạo thành công
do_action('echbay_viettelpost_order_created', $order_id, $viettelpost_order_number);

// Khi tracking được cập nhật
do_action('echbay_viettelpost_tracking_updated', $order_id, $tracking_info);</code></pre>

                        <h5>Filters</h5>
                        <pre><code>// Customize order data trước khi gửi API
apply_filters('echbay_viettelpost_order_data', $order_data, $order);

// Customize shipping cost
apply_filters('echbay_viettelpost_shipping_cost', $cost, $package);

// Customize settings
apply_filters('echbay_viettelpost_settings', $settings);</code></pre>

                        <h4>Sử dụng API Class</h4>
                        <pre><code>// Khởi tạo API
$api = new EchBay_ViettelPost_API();

// Tính phí vận chuyển
$result = $api->calculate_price($data);

// Tạo vận đơn
$result = $api->create_order($order_data);

// Tra cứu vận đơn
$result = $api->get_order_detail($order_number);</code></pre>
                    </div>
                </div>

                <!-- Support -->
                <div class="viettelpost-docs-section">
                    <h3>7. Hỗ trợ</h3>
                    <div class="viettelpost-docs-content">
                        <h4>Liên hệ hỗ trợ</h4>
                        <ul>
                            <li><strong>Email:</strong> lienhe@echbay.com</li>
                            <li><strong>Website:</strong> <a href="https://echbay.com" target="_blank">echbay.com</a></li>
                            <li><strong>Hotline:</strong> 0984 533 228</li>
                        </ul>

                        <h4>Trước khi liên hệ</h4>
                        <p>Vui lòng chuẩn bị các thông tin sau:</p>
                        <ul>
                            <li>Phiên bản WordPress và WooCommerce</li>
                            <li>Phiên bản plugin ViettelPost</li>
                            <li>Mô tả chi tiết lỗi gặp phải</li>
                            <li>Screenshots nếu có thể</li>
                            <li>WooCommerce error logs</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .viettelpost-documentation {
                max-width: 1200px;
            }

            .viettelpost-docs-container {
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 4px;
                overflow: hidden;
            }

            .viettelpost-docs-section {
                border-bottom: 1px solid #eee;
            }

            .viettelpost-docs-section:last-child {
                border-bottom: none;
            }

            .viettelpost-docs-section h3 {
                background: #f8f9fa;
                margin: 0;
                padding: 15px 20px;
                border-bottom: 1px solid #eee;
                cursor: pointer;
                position: relative;
            }

            .viettelpost-docs-section h3:hover {
                background: #e9ecef;
            }

            .viettelpost-docs-section h3:after {
                content: '▼';
                position: absolute;
                right: 20px;
                transition: transform 0.2s;
            }

            .viettelpost-docs-section.collapsed h3:after {
                transform: rotate(-90deg);
            }

            .viettelpost-docs-content {
                padding: 20px;
                display: block;
            }

            .viettelpost-docs-section.collapsed .viettelpost-docs-content {
                display: none;
            }

            .viettelpost-docs-content h4 {
                color: #0073aa;
                margin-top: 20px;
                margin-bottom: 10px;
            }

            .viettelpost-docs-content h4:first-child {
                margin-top: 0;
            }

            .viettelpost-docs-content h5 {
                color: #333;
                margin-top: 15px;
                margin-bottom: 8px;
            }

            .viettelpost-docs-content ul,
            .viettelpost-docs-content ol {
                margin-left: 20px;
                margin-bottom: 15px;
            }

            .viettelpost-docs-content li {
                margin-bottom: 5px;
                line-height: 1.5;
            }

            .viettelpost-docs-content pre {
                background: #f4f4f4;
                padding: 15px;
                border-radius: 4px;
                overflow-x: auto;
                margin: 15px 0;
            }

            .viettelpost-docs-content code {
                font-family: 'Courier New', monospace;
                font-size: 13px;
                line-height: 1.4;
            }

            .viettelpost-docs-content a {
                color: #0073aa;
                text-decoration: none;
            }

            .viettelpost-docs-content a:hover {
                text-decoration: underline;
            }
        </style>
    <?php
    }

    /**
     * Update settings
     */
    public function update_settings()
    {
        woocommerce_update_options($this->get_settings());
    }

    /**
     * Save settings
     */
    public function save_settings()
    {
        $this->update_settings();
    }

    /**
     * Get settings array
     */
    public function get_settings()
    {
        $settings = array(
            array(
                'name' => 'Cài đặt ViettelPost',
                'type' => 'title',
                'desc' => 'Cấu hình thông tin kết nối và cài đặt ViettelPost',
                'id'   => 'echbay_viettelpost_settings'
            ),

            // API Settings
            array(
                'name' => 'Thông tin API',
                'type' => 'title',
                'id'   => 'echbay_viettelpost_api_settings'
            ),

            array(
                'name' => 'Tên đăng nhập',
                'type' => 'text',
                'desc' => 'Tên đăng nhập tài khoản ViettelPost Partner',
                'id'   => 'echbay_viettelpost_username',
                'default' => ''
            ),

            array(
                'name' => 'Mật khẩu',
                'type' => 'password',
                'desc' => 'Mật khẩu tài khoản ViettelPost Partner',
                'id'   => 'echbay_viettelpost_password',
                'default' => ''
            ),

            array(
                'name' => 'Mã khách hàng',
                'type' => 'text',
                'desc' => 'Mã khách hàng được cấp bởi ViettelPost',
                'id'   => 'echbay_viettelpost_customer_code',
                'default' => ''
            ),

            array(
                'type' => 'sectionend',
                'id'   => 'echbay_viettelpost_api_settings'
            ),

            // Sender Information
            array(
                'name' => 'Thông tin người gửi',
                'type' => 'title',
                'id'   => 'echbay_viettelpost_sender_settings'
            ),

            array(
                'name' => 'Cửa hàng',
                'type' => 'select',
                'desc' => 'Chọn cửa hàng sau đó kéo xuối cuối cùng và bấm nút [Đồng bộ địa chỉ] để đồng bộ địa chỉ người gửi. Trường hợp không tìm thấy cửa hàng, vui lòng kiểm tra lại tài khoản kết nối hoặc thử [Đồng bộ địa chỉ] sau đó thao tác lại.',
                'id'   => 'echbay_viettelpost_sender_inventory',
                'options' => $this->get_inventorys_options(),
                'default' => '',
                'class' => 'wc-enhanced-select'
            ),

            array(
                'name' => 'Tên người gửi',
                'type' => 'text',
                'desc' => 'Tên người/công ty gửi hàng',
                'id'   => 'echbay_viettelpost_sender_name',
                'default' => get_bloginfo('name')
            ),

            array(
                'name' => 'Địa chỉ người gửi',
                'type' => 'textarea',
                'desc' => 'Địa chỉ chi tiết của người gửi',
                'id'   => 'echbay_viettelpost_sender_address',
                'default' => ''
            ),

            array(
                'name' => 'Số điện thoại người gửi',
                'type' => 'text',
                'desc' => 'Số điện thoại liên hệ của người gửi',
                'id'   => 'echbay_viettelpost_sender_phone',
                'default' => ''
            ),

            array(
                'name' => 'Email người gửi',
                'type' => 'email',
                'desc' => 'Email liên hệ của người gửi',
                'id'   => 'echbay_viettelpost_sender_email',
                'default' => get_option('admin_email')
            ),

            array(
                'name' => 'Tỉnh/Thành phố',
                'type' => 'select',
                'desc' => 'Tỉnh/Thành phố của người gửi',
                'id'   => 'echbay_viettelpost_sender_province',
                'options' => $this->get_provinces_options(),
                'default' => '',
                'class' => 'wc-enhanced-select'
            ),

            array(
                'name' => 'Quận/Huyện',
                'type' => 'select',
                'desc' => 'Quận/Huyện của người gửi',
                'id'   => 'echbay_viettelpost_sender_district',
                'options' => $this->get_districts_options(),
                'default' => '',
                'class' => 'wc-enhanced-select'
            ),

            array(
                'name' => 'Phường/Xã',
                'type' => 'select',
                'desc' => 'Phường/Xã của người gửi',
                'id'   => 'echbay_viettelpost_sender_ward',
                'options' => $this->get_wards_options(),
                'default' => '',
                'class' => 'wc-enhanced-select'
            ),

            array(
                'type' => 'sectionend',
                'id'   => 'echbay_viettelpost_sender_settings'
            ),

            // Order Settings
            array(
                'name' => 'Cài đặt đơn hàng',
                'type' => 'title',
                'id'   => 'echbay_viettelpost_order_settings'
            ),

            array(
                'name' => 'Tự động tạo vận đơn',
                'type' => 'select',
                'desc' => 'Tùy chọn tự động tạo vận đơn ViettelPost khi đơn hàng được xử lý',
                'id'   => 'echbay_viettelpost_auto_create_order',
                'options' => array(
                    'no' => 'Không tự động',
                    'yes' => 'Tự động',
                    'queue' => 'Đưa vào hàng đợi',
                ),
                'default' => 'no',
            ),

            array(
                'name' => 'Trạng thái tự động tạo',
                'type' => 'select',
                'desc' => 'Trạng thái đơn hàng để tự động tạo vận đơn.',
                'id'   => 'echbay_viettelpost_auto_create_status',
                'options' => array(
                    'wc-processing' => 'Đang xử lý',
                    'wc-completed' => 'Hoàn thành',
                    // 'wc-shipped' => 'Đã gửi hàng',
                ),
                'default' => 'wc-processing'
            ),

            // thêm option để cập nhật trạng thái đơn sau khi tạo vận đơn thành công
            array(
                'name' => 'Trạng thái cập nhật',
                'type' => 'select',
                'desc' => 'Trạng thái đơn hàng sau khi tạo vận đơn thành công. Để trống sẽ không thực hiện cập nhật.',
                'id'   => 'echbay_viettelpost_update_status',
                'options' => array(
                    '' => '[ Chọn trạng thái ]',
                    'on-hold' => 'Tạm giữ',
                    'wc-processing' => 'Đang xử lý',
                ),
                'default' => ''
            ),

            array(
                'name' => 'Cân nặng mặc định (gram)',
                'type' => 'number',
                'desc' => 'Cân nặng mặc định cho sản phẩm không có thông tin cân nặng',
                'id'   => 'echbay_viettelpost_default_weight',
                'default' => 500,
                'custom_attributes' => array(
                    'min' => 1,
                    'step' => 1
                )
            ),

            array(
                'name' => 'Dịch vụ vận chuyển mặc định',
                'type' => 'select',
                'desc' => 'Dịch vụ vận chuyển ViettelPost mặc định. Mỗi khi tạo đơn, hệ thống sẽ xác định lại dịch vụ vận chuyển khả dụng, nếu tìm thấy dịch vụ tương ứng sẽ áp dụng luôn, nếu không sẽ áp dụng dịch vụ đầu tiên trong kết quả ViettelPost trả về.',
                'id'   => 'echbay_viettelpost_default_service',
                'options' => $this->get_price_all_options(),
                'default' => 'STK'
            ),

            array(
                'name' => 'Tùy chọn địa chỉ checkout',
                'type' => 'checkbox',
                'desc' => 'Sử dụng dropdown địa chỉ ViettelPost trong trang checkout',
                'id'   => 'echbay_viettelpost_customize_checkout',
                'default' => 'yes'
            ),

            array(
                'type' => 'sectionend',
                'id'   => 'echbay_viettelpost_order_settings'
            ),

            // Actions
            array(
                'name' => 'Thao tác',
                'type' => 'title',
                'id'   => 'echbay_viettelpost_actions'
            ),

            array(
                'name' => 'Kiểm tra kết nối',
                'type' => 'viettelpost_test_connection',
                'desc' => 'Kiểm tra kết nối đến API ViettelPost',
                'id'   => 'echbay_viettelpost_test_connection'
            ),

            array(
                'name' => 'Đồng bộ địa chỉ',
                'type' => 'viettelpost_sync_locations',
                'desc' => 'Đồng bộ danh sách tỉnh/huyện/xã từ ViettelPost',
                'id'   => 'echbay_viettelpost_sync_locations'
            ),

            array(
                'type' => 'sectionend',
                'id'   => 'echbay_viettelpost_actions'
            )
        );

        return apply_filters('echbay_viettelpost_settings', $settings);
    }

    /**
     * Get provinces options
     */
    private function get_provinces_options()
    {
        $provinces = get_option('echbay_viettelpost_provinces', array());
        $options = array('' => 'Chọn tỉnh/thành phố');

        foreach ($provinces as $province) {
            $options[$province['PROVINCE_ID']] = $province['PROVINCE_NAME'];
        }

        return $options;
    }

    /**
     * Get districts options
     */
    private function get_districts_options()
    {
        $province_id = get_option('echbay_viettelpost_sender_province', '');
        $options = array('' => 'Chọn quận/huyện');

        if ($province_id) {
            $districts = get_option('echbay_viettelpost_districts_' . $province_id, array());
            foreach ($districts as $district) {
                $options[$district['DISTRICT_ID']] = $district['DISTRICT_NAME'];
            }
        }

        return $options;
    }

    /**
     * Get wards options
     */
    private function get_wards_options()
    {
        $district_id = get_option('echbay_viettelpost_sender_district', '');
        $options = array('' => 'Chọn phường/xã');

        if ($district_id) {
            $wards = get_option('echbay_viettelpost_wards_' . $district_id, array());
            foreach ($wards as $ward) {
                $options[$ward['WARDS_ID']] = $ward['WARDS_NAME'];
            }
        }

        return $options;
    }

    /**
     * Get inventory options
     */
    private function get_inventorys_options()
    {
        $datas = get_option('echbay_viettelpost_inventorys', array());
        $options = array('' => 'Chọn kho hàng');

        foreach ($datas as $data) {
            $options[$data['groupaddressId']] = $data['name'];
        }

        return $options;
    }

    /**
     * Get inventory options
     */
    private function get_price_all_options()
    {
        if (1 < 2) {
            return ECHBAY_VIETTELPOST_ORDER_SERVICE;
        }
        $datas = get_option('echbay_viettelpost_price_all', array());
        // print_r($datas);
        $options = array('' => 'Chọn Dịch vụ vận chuyển');

        foreach ($datas as $data) {
            $options[$data['MA_DV_CHINH']] = $data['TEN_DICHVU'];
        }

        return $options;
    }


    /**
     * Test API connection
     */
    public function test_connection()
    {
        check_ajax_referer('echbay_viettelpost_ajax', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die('Bạn không có quyền thực hiện thao tác này');
        }

        $api = new EchBay_ViettelPost_API();
        $result = $api->login();

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success('Kết nối thành công!');
        }
    }

    /**
     * Sync locations from API
     */
    public function sync_locations()
    {
        check_ajax_referer('echbay_viettelpost_ajax', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die('Bạn không có quyền thực hiện thao tác này');
        }

        $api = new EchBay_ViettelPost_API();

        // đồng bộ thông tin cửa hàng
        $inventorys_result = $api->get_inventorys();
        /**
         * reponse:
         * data.name
         * data.phone
         * data.address
         * data.provinceId
         * data.districtId
         * data.wardsId
         */
        if (is_wp_error($inventorys_result)) {
            wp_send_json_error($inventorys_result->get_error_message());
        }
        $result = [];
        $result['inventorys'] = $inventorys_result;
        // nếu có các dữ liệu tương ứng với option thì chạy lệnh update
        if (isset($inventorys_result['data']) && !empty($inventorys_result['data'])) {
            update_option('echbay_viettelpost_inventorys', $inventorys_result['data']);

            // Get inventory ID from request
            $inventory_id = isset($_POST['inventory_id']) ? $_POST['inventory_id'] : 0;
            if (!empty($inventory_id)) {
                foreach ($inventorys_result['data'] as $inventory) {
                    if ($inventory['groupaddressId'] == $inventory_id) {
                        $inventory_result = $inventory;
                        break;
                    }
                }
            } else {
                $inventory_result = $inventorys_result['data'][0];
            }
            // $result['inventory'] = $inventory_result;
            // $result['post'] = $_POST;
            // $result['inventory_id'] = $inventory_id;

            // Update sender information
            if (isset($inventory_result['name']) && !empty($inventory_result['name'])) {
                $result['name'] = update_option('echbay_viettelpost_sender_name', $inventory_result['name']);
            }
            if (isset($inventory_result['phone']) && !empty($inventory_result['phone'])) {
                $result['phone'] = update_option('echbay_viettelpost_sender_phone', $inventory_result['phone']);
            }
            if (isset($inventory_result['address']) && !empty($inventory_result['address'])) {
                $result['address'] = update_option('echbay_viettelpost_sender_address', $inventory_result['address']);
            }
            if (isset($inventory_result['provinceId']) && !empty($inventory_result['provinceId'])) {
                $result['province'] = update_option('echbay_viettelpost_sender_province', $inventory_result['provinceId']);
            }
            if (isset($inventory_result['districtId']) && !empty($inventory_result['districtId'])) {
                $result['district'] = update_option('echbay_viettelpost_sender_district', $inventory_result['districtId']);
            }
            if (isset($inventory_result['wardsId']) && !empty($inventory_result['wardsId'])) {
                $result['wards'] = update_option('echbay_viettelpost_sender_ward', $inventory_result['wardsId']);
            }
        }

        // Sync provinces
        $provinces_result = $api->get_provinces();
        if (!is_wp_error($provinces_result) && isset($provinces_result['data'])) {
            if (empty($provinces_result['data'])) {
                wp_send_json_error('Không có dữ liệu tỉnh/thành phố');
            }

            // Update provinces option
            update_option('echbay_viettelpost_provinces', $provinces_result['data']);
            $result['provinces'] = $provinces_result['data'];
        }
        // file_put_contents(__DIR__ . '/' . basename(__FILE__, '.php') . '.log', print_r($provinces_result, true));

        $result['msg'] = 'Đồng bộ địa chỉ thành công!';
        wp_send_json_success($result);
    }

    /**
     * Get districts via AJAX
     */
    public function get_districts_ajax()
    {
        check_ajax_referer('echbay_viettelpost_ajax', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die('Bạn không có quyền thực hiện thao tác này');
        }

        $province_id = intval($_POST['province_id']);

        if (!$province_id) {
            wp_send_json_error('ID tỉnh không hợp lệ');
        }

        $api = new EchBay_ViettelPost_API();
        $result = $api->get_districts($province_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } elseif (isset($result['data'])) {
            // Save districts to option for future use
            update_option('echbay_viettelpost_districts_' . $province_id, $result['data']);
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error('Không thể lấy danh sách quận/huyện');
        }
    }

    /**
     * Get wards via AJAX
     */
    public function get_wards_ajax()
    {
        check_ajax_referer('echbay_viettelpost_ajax', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die('Bạn không có quyền thực hiện thao tác này');
        }

        $district_id = intval($_POST['district_id']);

        if (!$district_id) {
            wp_send_json_error('ID quận/huyện không hợp lệ');
        }

        $api = new EchBay_ViettelPost_API();
        $result = $api->get_wards($district_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } elseif (isset($result['data'])) {
            // Save wards to option for future use
            update_option('echbay_viettelpost_wards_' . $district_id, $result['data']);
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error('Không thể lấy danh sách phường/xã');
        }
    }

    /**
     * Enqueue admin scripts
     */
    public function admin_scripts($hook)
    {
        if ($hook !== 'woocommerce_page_wc-settings') {
            return;
        }

        wp_enqueue_script(
            'echbay-viettelpost-admin',
            ECHBAY_VIETTELPOST_PLUGIN_URL . 'assets/admin.js',
            array('jquery'),
            ECHBAY_VIETTELPOST_DEBUG,
            true
        );

        wp_localize_script('echbay-viettelpost-admin', 'echbay_viettelpost_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('echbay_viettelpost_ajax'),
            'messages' => array(
                'testing' => 'Đang kiểm tra...',
                'syncing' => 'Đang đồng bộ...'
            )
        ));
    }

    /**
     * Output test connection field
     */
    public function output_test_connection_field($value)
    {
    ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($value['id']); ?>"><?php echo esc_html($value['name']); ?></label>
            </th>
            <td class="forminp">
                <button type="button" id="<?php echo esc_attr($value['id']); ?>" class="button button-secondary">
                    <?php echo esc_html($value['name']); ?>
                </button>
                <?php if (!empty($value['desc'])) : ?>
                    <p class="description"><?php echo wp_kses_post($value['desc']); ?></p>
                <?php endif; ?>
            </td>
        </tr>
    <?php
    }

    /**
     * Output sync locations field
     */
    public function output_sync_locations_field($value)
    {
    ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($value['id']); ?>"><?php echo esc_html($value['name']); ?></label>
            </th>
            <td class="forminp">
                <button type="button" id="<?php echo esc_attr($value['id']); ?>" class="button button-secondary">
                    <?php echo esc_html($value['name']); ?>
                </button>
                <?php if (!empty($value['desc'])) : ?>
                    <p class="description"><?php echo wp_kses_post($value['desc']); ?></p>
                <?php endif; ?>
            </td>
        </tr>
<?php
    }
}
