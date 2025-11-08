<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles the registration and integration of custom order statuses for WooCommerce.
 */
class OMS_Custom_Order_Statuses {

    /**
     * Constructor to hook into WordPress and WooCommerce.
     */
    public function __construct() {
        // Register the new statuses with WordPress itself.
        add_action('init', [$this, 'register_custom_order_statuses']);
        
        // Add the new statuses to WooCommerce's list of statuses.
        add_filter('wc_order_statuses', [$this, 'add_custom_statuses_to_wc']);
        
        // (Optional but recommended) Add statuses to WooCommerce reports.
        add_filter('woocommerce_reports_order_statuses', [$this, 'add_statuses_to_reports']);
    }

    /**
     * Registers the new post statuses with WordPress.
     */
    public function register_custom_order_statuses() {
        
        $custom_statuses = $this->get_custom_statuses();

        foreach ($custom_statuses as $slug => $label) {
            register_post_status('wc-' . $slug, [
                'label'                     => $label,
                'public'                    => true,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                // **FIXED**: Correctly formatted 'label_count' using _n_noop()
                'label_count'               => _n_noop("{$label} <span class=\"count\">(%s)</span>", "{$label} <span class=\"count\">(%s)</span>", 'woocommerce')
            ]);
        }
    }

    /**
     * Adds the custom statuses to the list recognized by WooCommerce.
     *
     * @param array $order_statuses The existing WooCommerce order statuses.
     * @return array The modified list of statuses.
     */
    public function add_custom_statuses_to_wc($order_statuses) {
        // We need to merge our statuses into the existing array.
        $new_statuses = [];
        $custom_statuses = $this->get_custom_statuses();

        foreach ($custom_statuses as $slug => $label) {
            $new_statuses['wc-' . $slug] = $label;
        }

        return array_merge($order_statuses, $new_statuses);
    }
    
    /**
     * Adds relevant custom statuses to WooCommerce analytics reports.
     *
     * @param array $report_statuses The existing statuses included in reports.
     * @return array The modified list of report statuses.
     */
    public function add_statuses_to_reports($report_statuses) {
        // Add statuses that should be considered part of a "paid" or "valid" sale.
        $statuses_for_reports = [
            'shipped',
            'delivered',
            'partial-return',
            'exchange', // **NEW**: Added exchange to reports
        ];

        return array_merge($report_statuses, $statuses_for_reports);
    }
    
    /**
     * Helper function to define all custom statuses in one place.
     *
     * @return array A list of [slug => Label].
     */
    private function get_custom_statuses() {
        return [
            'no-response'           => 'No Response',
            'shipped'               => 'Shipped',
            'delivered'             => 'Delivered',
            'returned'              => 'Returned',
            'partial-return'        => 'Partial Return',
            'warehouse'             => 'Warehouse',
            'exchange'              => 'Exchange',
            'ready-to-ship'         => 'Ready To Ship',
        ];
    }
}
