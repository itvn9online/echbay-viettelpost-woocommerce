<?php

/**
 * Plugin Name: EchBay ViettelPost WooCommerce
 * Plugin URI: https://echbay.com
 * Description: Tích hợp API ViettelPost với WooCommerce để tự động tạo vận đơn, tính phí vận chuyển và theo dõi đơn hàng.
 * Version: 1.3.7
 * Author: EchBay
 * Author URI: https://echbay.com
 * Requires at least: 5.8
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 * Woo: 12345678:abcdefg
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Declare HPOS compatibility
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Define plugin constants
define('ECHBAY_VIETTELPOST_VERSION', file_get_contents(__DIR__ . '/VERSION'));
define('ECHBAY_VIETTELPOST_DEBUG', strpos($_SERVER['HTTP_HOST'], 'demo.') !== false ? date('ymd.His') : ECHBAY_VIETTELPOST_VERSION);
define('ECHBAY_VIETTELPOST_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ECHBAY_VIETTELPOST_PLUGIN_PATH', __DIR__ . '/');
define('ECHBAY_VIETTELPOST_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('ECHBAY_VIETTELPOST_ORDER_SERVICE', [
    '' => 'Chọn Loại dịch vụ',
    'VCN' => 'VCN - CP nhanh thỏa thuận',
    'PHS' => 'PHS - Nội tỉnh tiết kiệm thỏa thuận',
    'VHT' => 'VHT - Hỏa tốc thỏa thuận',
    'VSL7' => 'VSL7 - Cam kết sản lượng 07',
    'STK' => 'STK - Chuyển phát nhanh tiêu chuẩn',
    'SHT' => 'SHT - Chuyển phát hỏa tốc',
    'SCN' => 'SCN - Chuyển phát nhanh',
]);

/**
 * Main plugin class
 */
class EchBay_ViettelPost_WooCommerce
{

    /**
     * The single instance of the class
     */
    protected static $_instance = null;

    /**
     * Main instance
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Hook into actions and filters
     */
    private function init_hooks()
    {
        add_action('init', array($this, 'init'), 0);

        // Add ViettelPost states for Vietnam
        add_filter('woocommerce_states', array($this, 'add_viettelpost_states'));

        // Add settings link to plugin list
        add_filter('plugin_action_links_' . ECHBAY_VIETTELPOST_PLUGIN_BASENAME, array($this, 'plugin_action_links'));

        // Plugin update functionality
        add_action('admin_notices', array($this, 'check_for_updates_notice'));
        add_action('wp_ajax_echbay_viettelpost_update', array($this, 'handle_plugin_update'));

        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // Initialize plugin after WooCommerce is loaded
        add_action('woocommerce_loaded', array($this, 'woocommerce_loaded'));
    }

    /**
     * Init the plugin after plugins_loaded so environment variables are set
     */
    public function init()
    {
        // Include required files
        $this->includes();

        // Initialize classes
        $this->init_classes();
    }

    /**
     * Include required core files
     */
    public function includes()
    {
        // Core classes
        include_once ECHBAY_VIETTELPOST_PLUGIN_PATH . 'includes/class-viettelpost-api.php';
        include_once ECHBAY_VIETTELPOST_PLUGIN_PATH . 'includes/class-viettelpost-settings.php';
        include_once ECHBAY_VIETTELPOST_PLUGIN_PATH . 'includes/class-viettelpost-shipping-method.php';
        include_once ECHBAY_VIETTELPOST_PLUGIN_PATH . 'includes/class-viettelpost-order-handler.php';
        include_once ECHBAY_VIETTELPOST_PLUGIN_PATH . 'includes/class-viettelpost-checkout.php';
    }

    /**
     * Initialize classes
     */
    public function init_classes()
    {
        // Initialize settings
        new EchBay_ViettelPost_Settings();

        // Initialize order handler
        new EchBay_ViettelPost_Order_Handler();

        // Initialize checkout customization
        new EchBay_ViettelPost_Checkout();
    }

    /**
     * Add ViettelPost states for Vietnam
     */
    public function add_viettelpost_states($states)
    {
        // Get ViettelPost provinces
        $provinces = get_option('echbay_viettelpost_provinces', array());

        if (!empty($provinces)) {
            $vn_states = array();
            foreach ($provinces as $province) {
                $vn_states[$province['PROVINCE_ID']] = $province['PROVINCE_NAME'];
            }

            // Add Vietnam states from ViettelPost
            $states['VN'] = $vn_states;
        } else {
            // Fallback: Ensure Vietnam has at least some states to show the field
            $states['VN'] = array(
                'HN' => 'Hà Nội',
                'HCM' => 'Hồ Chí Minh',
                'DN' => 'Đà Nẵng',
                'HP' => 'Hải Phòng',
                'CT' => 'Cần Thơ'
            );
        }

        return $states;
    }

    /**
     * Called when WooCommerce is loaded
     */
    public function woocommerce_loaded()
    {
        // Add ViettelPost shipping method
        add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));
    }

    /**
     * Add ViettelPost shipping method
     */
    public function add_shipping_method($methods)
    {
        $methods['viettelpost'] = 'EchBay_ViettelPost_Shipping_Method';
        return $methods;
    }

    /**
     * Add settings link to plugin action links
     */
    public function plugin_action_links($links)
    {
        $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=viettelpost') . '">Cài đặt</a>';
        $docs_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=viettelpost&section=documentation') . '">Tài liệu</a>';

        array_unshift($links, $settings_link);
        array_unshift($links, $docs_link);

        return $links;
    }

    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active()
    {
        return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));
    }

    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice()
    {
        echo '<div class="error"><p><strong>' .
            sprintf(
                'EchBay ViettelPost requires WooCommerce to be installed and active. You can download %s here.',
                '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
            ) .
            '</strong></p></div>';
    }

    /**
     * Plugin activation hook
     */
    public static function activate()
    {
        // Create queue table
        self::create_queue_table();
    }

    /**
     * Plugin deactivation hook
     */
    public static function deactivate()
    {
        // Plugin deactivated - queue table will remain for data integrity
        // You can manually drop the table if needed
    }

    /**
     * Check for plugin updates and show notice
     */
    public function check_for_updates_notice()
    {
        // Only show on admin pages
        if (!is_admin()) {
            return;
        }

        // Check if we should show the notice (once per day)
        $last_check = get_option('echbay_viettelpost_last_update_check', 0);
        if (time() - $last_check < DAY_IN_SECONDS) {
            return;
        }

        $remote_version = $this->get_remote_version();
        if ($remote_version && version_compare(ECHBAY_VIETTELPOST_VERSION, $remote_version, '<')) {
            echo '<div class="notice notice-info is-dismissible">
                <p><strong>EchBay ViettelPost:</strong> Có phiên bản mới <strong>' . esc_html($remote_version) . '</strong> (hiện tại: ' . esc_html(ECHBAY_VIETTELPOST_VERSION) . '). 
                <button type="button" class="button button-primary" onclick="echbayViettelpostUpdate()">Cập nhật ngay</button>
                <span id="echbay-viettelpost-update-status"></span>
                </p>
            </div>';

            // Add JavaScript for update functionality
            echo '<script>
                function echbayViettelpostUpdate() {
                    var statusEl = document.getElementById("echbay-viettelpost-update-status");
                    statusEl.innerHTML = "Đang cập nhật...";
                    
                    var xhr = new XMLHttpRequest();
                    xhr.open("POST", ajaxurl, true);
                    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                    
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4) {
                            if (xhr.status === 200) {
                                var response = JSON.parse(xhr.responseText);
                                if (response.success) {
                                    statusEl.innerHTML = "Cập nhật thành công! Đang tải lại trang...";
                                    setTimeout(function() {
                                        window.location.reload();
                                    }, 2000);
                                } else {
                                    statusEl.innerHTML = "Lỗi: " + response.data;
                                }
                            } else {
                                statusEl.innerHTML = "Lỗi kết nối";
                            }
                        }
                    };
                    
                    xhr.send("action=echbay_viettelpost_update&_wpnonce=' . wp_create_nonce('echbay_viettelpost_update') . '");
                }
            </script>';
        }

        // Update last check time
        update_option('echbay_viettelpost_last_update_check', time());
    }

    /**
     * Get remote version from GitHub
     */
    private function get_remote_version()
    {
        $response = wp_remote_get('https://github.com/itvn9online/echbay-viettelpost-woocommerce/raw/refs/heads/main/VERSION', array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'EchBay-ViettelPost-Plugin/' . ECHBAY_VIETTELPOST_VERSION
            )
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        return trim(wp_remote_retrieve_body($response));
    }

    /**
     * Handle plugin update AJAX request
     */
    public function handle_plugin_update()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['_wpnonce'], 'echbay_viettelpost_update')) {
            wp_die(json_encode(array('success' => false, 'data' => 'Nonce verification failed')));
        }

        // Check user permissions
        if (!current_user_can('update_plugins')) {
            wp_die(json_encode(array('success' => false, 'data' => 'Insufficient permissions')));
        }

        try {
            $result = $this->perform_plugin_update();
            wp_die(json_encode(array('success' => $result['success'], 'data' => $result['message'])));
        } catch (Exception $e) {
            wp_die(json_encode(array('success' => false, 'data' => $e->getMessage())));
        }
    }

    /**
     * Perform the actual plugin update
     */
    private function perform_plugin_update()
    {
        // Check remote version again
        $remote_version = $this->get_remote_version();
        if (!$remote_version) {
            return array('success' => false, 'message' => 'Không thể kiểm tra phiên bản mới');
        }

        if (!version_compare(ECHBAY_VIETTELPOST_VERSION, $remote_version, '<')) {
            return array('success' => false, 'message' => 'Plugin đã là phiên bản mới nhất');
        }

        // Download the zip file
        $zip_url = 'https://github.com/itvn9online/echbay-viettelpost-woocommerce/archive/refs/heads/main.zip';
        $temp_file = download_url($zip_url);

        if (is_wp_error($temp_file)) {
            return array('success' => false, 'message' => 'Không thể tải file: ' . $temp_file->get_error_message());
        }

        // Extract the zip file
        $temp_dir = wp_upload_dir()['basedir'] . '/echbay-viettelpost-temp-' . time();
        $unzip_result = unzip_file($temp_file, $temp_dir);

        if (is_wp_error($unzip_result)) {
            unlink($temp_file);
            return array('success' => false, 'message' => 'Không thể giải nén file: ' . $unzip_result->get_error_message());
        }

        // Find the extracted plugin directory
        $extracted_plugin_dir = $temp_dir . '/echbay-viettelpost-woocommerce-main';
        if (!is_dir($extracted_plugin_dir)) {
            unlink($temp_file);
            $this->remove_directory($temp_dir);
            return array('success' => false, 'message' => 'Không tìm thấy thư mục plugin trong file tải về');
        }

        // Backup current plugin (optional)
        $plugin_dir = ECHBAY_VIETTELPOST_PLUGIN_PATH;
        $backup_dir = $plugin_dir . '_backup_' . time();
        rename($plugin_dir, $backup_dir);

        // Copy new plugin files
        $copy_result = $this->copy_directory($extracted_plugin_dir, $plugin_dir);

        if (!$copy_result) {
            // Restore backup if copy failed
            rename($backup_dir, $plugin_dir);
            unlink($temp_file);
            $this->remove_directory($temp_dir);
            return array('success' => false, 'message' => 'Không thể sao chép file plugin mới');
        }

        // Clean up
        unlink($temp_file);
        $this->remove_directory($temp_dir);
        $this->remove_directory($backup_dir); // Remove backup after successful update

        return array('success' => true, 'message' => 'Cập nhật plugin thành công');
    }

    /**
     * Recursively copy directory
     */
    private function copy_directory($source, $destination)
    {
        if (!is_dir($source)) {
            return false;
        }

        if (!is_dir($destination)) {
            wp_mkdir_p($destination);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $dest_path = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();

            if ($item->isDir()) {
                wp_mkdir_p($dest_path);
            } else {
                copy($item, $dest_path);
            }
        }

        return true;
    }

    /**
     * Recursively remove directory
     */
    private function remove_directory($dir)
    {
        if (!is_dir($dir)) {
            return false;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item);
            } else {
                unlink($item);
            }
        }

        rmdir($dir);
        return true;
    }

    /**
     * Create queue table
     */
    private static function create_queue_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'echbay_viettelpost_queue';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            attempts int(11) NOT NULL DEFAULT 0,
            max_attempts int(11) NOT NULL DEFAULT 3,
            created_at datetime NOT NULL,
            sent_at datetime NULL,
            error_message text NULL,
            vtp_id varchar(100) NULL,
            status enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

/**
 * Main instance of plugin
 */
function EchBay_ViettelPost()
{
    return EchBay_ViettelPost_WooCommerce::instance();
}

// Global for backwards compatibility
$GLOBALS['echbay_viettelpost'] = EchBay_ViettelPost();

// Activation and deactivation hooks
register_activation_hook(__FILE__, array('EchBay_ViettelPost_WooCommerce', 'activate'));
register_deactivation_hook(__FILE__, array('EchBay_ViettelPost_WooCommerce', 'deactivate'));
