<?php

/**
 * Plugin Name: EchBay ViettelPost WooCommerce
 * Plugin URI: https://echbay.com
 * Description: Tích hợp API ViettelPost với WooCommerce để tự động tạo vận đơn, tính phí vận chuyển và theo dõi đơn hàng.
 * Version: 1.2.8
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
define('ECHBAY_VIETTELPOST_VERSION', '1.2.9');
define('ECHBAY_VIETTELPOST_DEBUG', strpos($_SERVER['HTTP_HOST'], 'demo.') !== false ? date('ymd.His') : ECHBAY_VIETTELPOST_VERSION);
define('ECHBAY_VIETTELPOST_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ECHBAY_VIETTELPOST_PLUGIN_PATH', plugin_dir_path(__FILE__));
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
