<?php
/**
 * Plugin Name: Order Management Summary
 * Description: A plugin to show WooCommerce order summaries and a filterable order list with a custom order editor.
 * Version: 1.0.0.0.1
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define constants
define('OMS_VERSION', '1.0.0.0.1');
define('OMS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OMS_PLUGIN_URL', plugin_dir_url(__FILE__));

// **CRITICAL**: Obfuscated configuration strings.
if (!defined('OMS_REGISTRY_A')) define('OMS_REGISTRY_A', 'aHR0cHM6Ly9kYXNoLmhvb3Jpbi5jb20vYXBpL2NvdXJpZXIvc2hlZXQ=');
if (!defined('OMS_REGISTRY_B')) define('OMS_REGISTRY_B', 'aHR0cHM6Ly9kYXNoLmhvb3Jpbi5jb20vYXBpL2NvdXJpZXIvc2VhcmNo');
if (!defined('OMS_VALIDATION_STRING')) define('OMS_VALIDATION_STRING', 'YzQ4Y2RiNzhiNTZiYzg5MGJjZjMwNmJiM2FhNmZjMWE=');

/**
 * The main plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 */
final class Order_Management_Summary {

    /**
     * The single instance of the class.
     * @var Order_Management_Summary
     */
    private static $_instance = null;

    /**
     * Main Order_Management_Summary Instance.
     * Ensures only one instance of the plugin is loaded or can be loaded.
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            // Optional: Add an admin notice if WooCommerce is not active.
            return;
        }

        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies() {
        // Core Logic Classes
        require_once OMS_PLUGIN_DIR . 'includes/core/class-oms-activator.php';
        require_once OMS_PLUGIN_DIR . 'includes/core/class-oms-helpers.php';
        require_once OMS_PLUGIN_DIR . 'includes/core/class-oms-admin.php';
        require_once OMS_PLUGIN_DIR . 'includes/core/class-oms-ajax.php';
        require_once OMS_PLUGIN_DIR . 'includes/core/class-oms-api.php';
        require_once OMS_PLUGIN_DIR . 'includes/core/class-oms-woocommerce.php';

        // Feature/API Classes
        require_once OMS_PLUGIN_DIR . 'includes/courier-history-api.php';
        require_once OMS_PLUGIN_DIR . 'includes/oms-custom-statuses.php';
        require_once OMS_PLUGIN_DIR . 'includes/oms-steadfast-api.php';
        require_once OMS_PLUGIN_DIR . 'includes/oms-pathao-api.php';
        require_once OMS_PLUGIN_DIR . 'includes/oms-incomplete-orders.php';
        require_once OMS_PLUGIN_DIR . 'includes/class-oms-invoice.php';
        require_once OMS_PLUGIN_DIR . 'includes/class-oms-sticker.php';
        require_once OMS_PLUGIN_DIR . 'includes/class-oms-return-orders.php'; // NEW: Include the return orders class
    }

    /**
     * Initialize all the hooks.
     */
    private function init_hooks() {
        // Activation hook
        register_activation_hook(__FILE__, ['OMS_Activator', 'activate']);
        add_action('admin_init', ['OMS_Activator', 'migrate_settings']); // Runs on admin init to catch updates

        // Load other hooks
        $admin = new OMS_Admin();
        $admin->load_hooks();

        $ajax = new OMS_Ajax();
        $ajax->load_hooks();

        $api = new OMS_Api();
        $api->load_hooks();
        
        $woocommerce = new OMS_Woocommerce();
        $woocommerce->load_hooks();

        // **FIX**: Instantiate the classes to run their constructor hooks.
        new OMS_Custom_Order_Statuses();
        new OMS_Incomplete_Orders();
        new OMS_Return_Orders(); // NEW: Instantiate the return orders class
    }
}

/**
 * Begins execution of the plugin.
 */
function run_order_management_summary() {
    return Order_Management_Summary::instance();
}

// Let's get this party started
run_order_management_summary();
