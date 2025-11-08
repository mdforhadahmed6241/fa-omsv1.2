    <?php
    if (!defined('ABSPATH')) {
        exit;
    }
    
    /**
     * The admin-specific functionality of the plugin.
     *
     * Defines the plugin name, version, and hooks for the admin area.
     */
    class OMS_Admin {
    
        public function __construct() {
            // Intentionally left blank.
        }
    
        /**
         * Register all hooks for the admin area.
         */
        public function load_hooks() {
            add_action('admin_menu', [$this, 'plugin_admin_menu']);
            add_action('admin_init', [$this, 'register_settings']);
            add_action('admin_notices', [$this, 'bulk_update_admin_notice']);
        }
    
        /**
         * Register the administration menu for this plugin into the WordPress Dashboard.
         */
        public function plugin_admin_menu() {
            $main_page = add_menu_page('Order Summary', 'Order', 'manage_options', 'order-management-summary', [$this, 'render_summary_page'], 'dashicons-cart', 25);
            
            // --- PAGE DEFINITIONS ---
            $summary_page = add_submenu_page('order-management-summary', 'Summary', 'Summary', 'manage_options', 'order-management-summary', [$this, 'render_summary_page']);
            $order_list_page = add_submenu_page('order-management-summary', 'Order List', 'Order List', 'manage_options', 'oms-order-list', [$this, 'render_order_list_page']);
            $add_order_page = add_submenu_page('order-management-summary', 'Add Order', 'Add Order', 'manage_options', 'oms-add-order', [$this, 'render_add_order_page']);
            $return_page = add_submenu_page('order-management-summary', 'Return Product', 'Return Product', 'manage_options', 'oms-return-product', [$this, 'render_return_product_page']);
            $barcode_scanner_page = add_submenu_page('order-management-summary', 'Barcode Scanner', 'Barcode Scanner', 'manage_options', 'oms-barcode-scanner', [$this, 'render_barcode_scanner_page']);
            $incomplete_list_page = add_submenu_page('order-management-summary', 'Incomplete List', 'Incomplete List', 'manage_options', 'oms-incomplete-list', [$this, 'render_incomplete_list_page']);
            $settings_page = add_submenu_page('order-management-summary', 'Settings', 'Settings', 'manage_options', 'oms-settings', [$this, 'render_settings_page']);
            
            // Hidden Details Pages
            $order_details_page = add_submenu_page(null, 'Order Details', 'Order Details', 'manage_options', 'oms-order-details', [$this, 'render_order_details_page']);
            $incomplete_order_details_page = add_submenu_page(null, 'Incomplete Order Details', 'Incomplete Order Details', 'manage_options', 'oms-incomplete-order-details', [$this, 'render_incomplete_details_page']);
            
            // --- GLOBAL HOOKS (GLOBAL STYLES & NOTICES) ---
            $all_plugin_pages = [
                $main_page, $summary_page, $order_list_page, $add_order_page, $return_page, 
                $barcode_scanner_page, $incomplete_list_page, $settings_page, 
                $order_details_page, $incomplete_order_details_page
            ];
            
            foreach ($all_plugin_pages as $page) { 
                if ($page) { 
                    add_action("admin_print_styles-{$page}", [$this, 'enqueue_global_styles']); // CHANGED
                    add_action("admin_head-{$page}", [$this, 'hide_notices_css']); 
                } 
            }
            
            // --- PAGE-SPECIFIC STYLES & SCRIPTS ---
    
            // Summary Page
            if ($summary_page) {
                add_action("admin_print_styles-{$summary_page}", [$this, 'enqueue_summary_styles']);
            }
    
            // Order List / Incomplete List
            $list_pages = [$order_list_page, $incomplete_list_page];
            foreach ($list_pages as $page) {
                if ($page) {
                    add_action("admin_print_styles-{$page}", [$this, 'enqueue_order_list_styles']);
                }
            }
            if ($order_list_page) { 
                add_action("admin_print_scripts-{$order_list_page}", [$this, 'enqueue_order_list_scripts']); 
                add_action("load-{$order_list_page}", [$this, 'handle_bulk_actions']); 
            }
    
            // Order Details / Add Order / Incomplete Details (Shared styles & scripts)
            $details_pages = [$add_order_page, $order_details_page, $incomplete_order_details_page];
            foreach ($details_pages as $page) { 
                if ($page) {
                    add_action("admin_print_styles-{$page}", [$this, 'enqueue_order_details_styles']);
                    add_action("admin_print_scripts-{$page}", [$this, 'enqueue_order_details_scripts']); 
                }
            }
            
            // Barcode Scanner Page
            if ($barcode_scanner_page) {
                add_action("admin_print_styles-{$barcode_scanner_page}", [$this, 'enqueue_barcode_scanner_styles']);
                add_action("admin_print_scripts-{$barcode_scanner_page}", [$this, 'enqueue_barcode_scanner_scripts']);
            }
            
            // Return Product Page
            if ($return_page) {
                 add_action("admin_print_styles-{$return_page}", [$this, 'enqueue_return_page_styles']);
                 add_action("admin_print_scripts-{$return_page}", [$this, 'enqueue_return_page_scripts']);
            }
    
            // Settings Page
            if ($settings_page) {
                add_action("admin_print_styles-{$settings_page}", [$this, 'enqueue_settings_styles']);
                // Note: Settings page JS is inlined in settings-page.php, so no script enqueue
            }
    
            // Status Styles (shared across multiple pages)
            $status_style_pages = [$order_list_page, $order_details_page, $incomplete_order_details_page, $incomplete_list_page, $return_page];
            foreach($status_style_pages as $page) { 
                if ($page) add_action("admin_head-{$page}", [$this, 'inject_custom_status_styles']); 
            }
        }
    
        /**
         * Enqueue GLOBAL styles for the admin area.
         */
        public function enqueue_global_styles() {
            wp_enqueue_style('oms-admin-global-style', OMS_PLUGIN_URL . 'assets/css/admin-global.css', [], OMS_VERSION);
        }
        
        /**
         * Enqueue Summary Page styles.
         */
        public function enqueue_summary_styles() {
            wp_enqueue_style('oms-summary-page-style', OMS_PLUGIN_URL . 'assets/css/summary-page.css', ['oms-admin-global-style'], OMS_VERSION);
        }
        
        /**
         * Enqueue Order List Page styles.
         */
        public function enqueue_order_list_styles() {
            wp_enqueue_style('oms-order-list-page-style', OMS_PLUGIN_URL . 'assets/css/order-list-page.css', ['oms-admin-global-style'], OMS_VERSION);
        }
        
        /**
         * Enqueue Order Details/Add Page styles.
         */
        public function enqueue_order_details_styles() {
            wp_enqueue_style('oms-order-details-page-style', OMS_PLUGIN_URL . 'assets/css/order-details-page.css', ['oms-admin-global-style'], OMS_VERSION);
        }
        
        /**
         * Enqueue Settings Page styles.
         */
        public function enqueue_settings_styles() {
            wp_enqueue_style('oms-settings-page-style', OMS_PLUGIN_URL . 'assets/css/settings-page.css', ['oms-admin-global-style'], OMS_VERSION);
        }
        
        /**
         * Enqueue Barcode Scanner Page styles.
         */
        public function enqueue_barcode_scanner_styles() {
            wp_enqueue_style('oms-barcode-scanner-page-style', OMS_PLUGIN_URL . 'assets/css/barcode-scanner-page.css', ['oms-admin-global-style'], OMS_VERSION);
        }
        
        /**
         * Enqueue Return Product Page styles.
         */
        public function enqueue_return_page_styles() {
            wp_enqueue_style('oms-return-product-page-style', OMS_PLUGIN_URL . 'assets/css/return-product-page.css', ['oms-admin-global-style'], OMS_VERSION);
        }
    
        /**
         * Enqueue scripts for the order details/add pages.
         */
        public function enqueue_order_details_scripts() {
            wp_enqueue_script('oms-order-details-js', OMS_PLUGIN_URL . 'assets/js/admin-order-details.js', [], OMS_VERSION, true);
            $data_to_pass = ['ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('oms_ajax_nonce'), 'allowed_statuses' => []];
            if (isset($_GET['page'], $_GET['order_id']) && $_GET['page'] === 'oms-order-details' && ($order = wc_get_order(absint($_GET['order_id'])))) {
                $allowed_slugs = OMS_Helpers::get_allowed_next_statuses($order->get_status());
                $all_statuses = wc_get_order_statuses();
                foreach ($allowed_slugs as $slug) { if (isset($all_statuses['wc-' . $slug])) $data_to_pass['allowed_statuses'][$slug] = $all_statuses['wc-' . $slug]; }
            }
            wp_localize_script('oms-order-details-js', 'oms_order_details', $data_to_pass);
        }
    
        /**
         * Enqueue scripts for the order list page.
         */
        public function enqueue_order_list_scripts() {
            wp_enqueue_script('oms-order-list-js', OMS_PLUGIN_URL . 'assets/js/admin-order-list.js', ['jquery'], OMS_VERSION, true);
            wp_localize_script('oms-order-list-js', 'oms_order_list_data', ['ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('oms_ajax_nonce')]);
        }
    
        /**
         * Enqueue scripts for the barcode scanner page.
         */
        public function enqueue_barcode_scanner_scripts() {
            wp_enqueue_script('oms-barcode-scanner-js', OMS_PLUGIN_URL . 'assets/js/admin-barcode-scanner.js', ['jquery'], OMS_VERSION, true);
            wp_localize_script('oms-barcode-scanner-js', 'oms_barcode_scanner_data', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('oms_scan_nonce'),
                'return_scan_nonce' => wp_create_nonce('oms_return_scan_nonce'), // NEW: Add return scan nonce
            ]);
        }
        
        /**
         * NEW: Enqueue script for the Return Product page.
         */
        public function enqueue_return_page_scripts() {
            wp_enqueue_script('oms-return-page-js', OMS_PLUGIN_URL . 'assets/js/admin-return-page.js', ['jquery'], OMS_VERSION, true);
            wp_localize_script('oms-return-page-js', 'oms_return_data', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('oms_return_nonce'),
                'scan_nonce' => wp_create_nonce('oms_return_scan_nonce') // Same nonce as scanner for consistency
            ]);
        }
    
        /**
         * Injects CSS for custom statuses into the admin head.
         */
        public function inject_custom_status_styles() {
            $styles = ['no-response'=>'#7f8c8d','shipped'=>'#2980b9','delivered'=>'#27ae60','returned'=>'#c0392b','partial-return'=>'#d35400','warehouse'=>'#8e44ad','exchange'=>'#16a085','ready-to-ship'=>'#f39c12'];
            echo '<style>';
            foreach ($styles as $slug => $color) { echo '.oms-status-badge.status-'.esc_attr($slug).', .oms-status-button.status-'.esc_attr($slug).' { background-color: '.esc_attr($color).' !important; }'; }
            echo '</style>';
        }
        
        /**
         * Hides non-plugin notices from plugin pages.
         */
        public function hide_notices_css() {
            $screen = get_current_screen();
            if (strpos($screen->id, 'oms-') !== false || strpos($screen->id, 'order-management') !== false) { 
                echo '<style>.notice:not(.oms-notice), .updated, .update-nag, #wp__notice-list .notice:not(.oms-notice) { display: none !important; }</style>'; 
            } 
        }
        
        /**
         * Register the settings for the plugin.
         */
        public function register_settings() {
            // General Settings
            $settings_group = 'oms_settings_group';
            register_setting($settings_group, 'oms_default_courier', ['sanitize_callback' => 'sanitize_text_field']);
            add_settings_section('oms_general_section', 'General Settings', null, 'oms-settings');
            add_settings_field('oms_default_courier_field', 'Select Default Courier', [$this, 'default_courier_callback'], 'oms-settings', 'oms_general_section');
            register_setting($settings_group, 'oms_workflow_enabled', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'yes']);
            add_settings_section('oms_workflow_section', 'Workflow Settings', null, 'oms-settings');
            add_settings_field('oms_workflow_enabled_field', 'Enable Status Workflow', [$this, 'workflow_enabled_callback'], 'oms-settings', 'oms_workflow_section');
    
            // Invoice Settings
            $invoice_settings_group = 'oms_invoice_settings_group';
            register_setting($invoice_settings_group, 'oms_invoice_company_name', ['sanitize_callback' => 'sanitize_text_field']);
            register_setting($invoice_settings_group, 'oms_invoice_mobile_number', ['sanitize_callback' => 'sanitize_text_field']);
            add_settings_section('oms_invoice_section', 'Invoice Details', null, 'oms-invoice-settings');
            add_settings_field('oms_invoice_company_name_field', 'Company Name', [$this, 'invoice_company_name_callback'], 'oms-invoice-settings', 'oms_invoice_section');
            add_settings_field('oms_invoice_mobile_number_field', 'Invoice Mobile Number', [$this, 'invoice_mobile_number_callback'], 'oms-invoice-settings', 'oms_invoice_section');
        }
        
        /**
         * Callback for the default courier setting field.
         */
        public function default_courier_callback() {
            $couriers = OMS_Helpers::get_couriers();
            $default_courier = get_option('oms_default_courier');
            echo '<select name="oms_default_courier" class="regular-text"><option value="">-- None --</option>';
            if (!empty($couriers)) { 
                foreach($couriers as $courier) { 
                    echo '<option value="' . esc_attr($courier['id']) . '" ' . selected($courier['id'], $default_courier, false) . '>' . esc_html($courier['name']) . '</option>'; 
                } 
            }
            echo '</select><p class="description">Select the default courier for new orders and for sending from the list page.</p>';
        }
        
        /**
         * Callback for the workflow enabled setting field.
         */
        public function workflow_enabled_callback() {
            $option = get_option('oms_workflow_enabled', 'yes');
            echo '<label class="oms-switch"><input type="checkbox" name="oms_workflow_enabled" value="yes" ' . checked('yes', $option, false) . '><span class="oms-slider round"></span></label><p class="description">When enabled, the custom order status workflow rules will be enforced.</p>';
        }
    
        /**
         * Callback for the invoice company name field.
         */
        public function invoice_company_name_callback() {
            $company_name = get_option('oms_invoice_company_name');
            echo '<input type="text" name="oms_invoice_company_name" value="' . esc_attr($company_name) . '" class="regular-text" placeholder="e.g., Online Shop (Snap Style)">';
            echo '<p class="description">This name will appear at the top of the invoice.</p>';
        }
    
        /**
         * Callback for the invoice mobile number field.
         */
        public function invoice_mobile_number_callback() {
            $mobile_number = get_option('oms_invoice_mobile_number');
            echo '<input type="text" name="oms_invoice_mobile_number" value="' . esc_attr($mobile_number) . '" class="regular-text" placeholder="e.g., 01958449987">';
            echo '<p class="description">This mobile number will be displayed on the invoice.</p>';
        }
    
        /**
         * Handles bulk actions from the order list page.
         */
        public function handle_bulk_actions() {
            if (isset($_POST['oms_bulk_action_nonce']) && wp_verify_nonce($_POST['oms_bulk_action_nonce'], 'oms_bulk_actions')) {
                $action = sanitize_key($_POST['action'] ?? $_POST['action2'] ?? '');
                $order_ids = isset($_POST['order_ids']) ? array_map('absint', $_POST['order_ids']) : [];
                if ($action && $action !== '-1' && !empty($order_ids)) {
                    $updated = 0; $skipped = 0;
                    foreach ($order_ids as $order_id) { 
                        if ($order = wc_get_order($order_id)) { 
                            // Check if the old status is a return status before updating (prevents re-entry into return table if moved back and forth)
                            $old_status = $order->get_status();
                            $is_valid = OMS_Helpers::is_valid_status_transition($old_status, $action);
                            
                            if ($is_valid) { 
                                $order->update_status($action);
                                $order->add_order_note('Status updated via bulk action.', true);
                                $updated++;
                            } else { 
                                $skipped++; 
                            } 
                        } 
                    }
                    wp_safe_redirect(add_query_arg(['page' => 'oms-order-list', 'tab' => sanitize_text_field($_POST['tab']), 'bulk_update_success' => 1, 'updated' => $updated, 'skipped' => $skipped], admin_url('admin.php')));
                    exit;
                }
            }
        }
        
        /**
         * Displays admin notices for bulk updates.
         */
        public function bulk_update_admin_notice() {
            if (isset($_GET['page'], $_GET['bulk_update_success']) && $_GET['page'] === 'oms-order-list') {
                if ($updated = absint($_GET['updated'] ?? 0)) { 
                    printf('<div class="oms-notice notice notice-success is-dismissible"><p>%s</p></div>', esc_html(sprintf(_n('%d order updated.', '%d orders updated.', $updated), $updated))); 
                }
                if ($skipped = absint($_GET['skipped'] ?? 0)) { 
                    printf('<div class="oms-notice notice notice-warning is-dismissible"><p>%s</p></div>', esc_html(sprintf(_n('%d order skipped due to workflow rules.', '%d orders skipped due to workflow rules.', $skipped), $skipped))); 
                }
            }
        }
        
        /**
         * Renders the summary page.
         */
        public function render_summary_page() { require_once OMS_PLUGIN_DIR . 'views/summary-page.php'; }
        public function render_order_list_page() { require_once OMS_PLUGIN_DIR . 'views/order-list-page.php'; }
        public function render_add_order_page() { require_once OMS_PLUGIN_DIR . 'views/add-order-page.php'; }
        public function render_settings_page() { require_once OMS_PLUGIN_DIR . 'views/settings-page.php'; }
        public function render_order_details_page() { require_once OMS_PLUGIN_DIR . 'views/order-details-page.php'; }
        public function render_incomplete_list_page() { require_once OMS_PLUGIN_DIR . 'views/incomplete-list-page.php'; }
        public function render_incomplete_details_page() { require_once OMS_PLUGIN_DIR . 'views/incomplete-order-details-page.php'; }
        public function render_barcode_scanner_page() { require_once OMS_PLUGIN_DIR . 'views/barcode-scanner-page.php'; }
        public function render_return_product_page() { require_once OMS_PLUGIN_DIR . 'views/return-product-page.php'; } // NEW: Return Product Page
    }
    

