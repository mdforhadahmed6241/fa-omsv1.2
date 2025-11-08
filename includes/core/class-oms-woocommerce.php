<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles interactions with WooCommerce hooks.
 */
class OMS_Woocommerce {

    public function __construct() {
        // Intentionally left blank.
    }

    /**
     * Register all WooCommerce-related hooks.
     */
    public function load_hooks() {
        add_action('woocommerce_order_status_changed', [$this, 'auto_send_to_courier_on_status_change'], 10, 3);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_checkout_scripts']);
    }

    /**
     * Automatically send order to courier when status changes to 'ready-to-ship'.
     */
    public function auto_send_to_courier_on_status_change($order_id, $old_status, $new_status) {
        if ($new_status !== 'ready-to-ship') return;
        
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        $courier_id = $order->get_meta('_oms_selected_courier_id', true) ?: get_option('oms_default_courier');
        $courier = OMS_Helpers::get_courier_by_id($courier_id);
        if (!$courier) return;

        if (!isset($courier['credentials']['auto_send']) || $courier['credentials']['auto_send'] !== 'yes') return;

        if ($courier['type'] === 'steadfast' && !$order->get_meta('_steadfast_consignment_id')) {
            $api = new OMS_Steadfast_API($courier['credentials']);
            $api->create_consignment($order);
        } elseif ($courier['type'] === 'pathao' && !$order->get_meta('_pathao_consignment_id')) {
            $api = new OMS_Pathao_API($courier['credentials']);
            $location_data = [ 
                'city_id' => $order->get_meta('_oms_pathao_city_id', true), 
                'zone_id' => $order->get_meta('_oms_pathao_zone_id', true), 
                'area_id' => $order->get_meta('_oms_pathao_area_id', true) 
            ];
            $api->create_order($order, $location_data);
        }
    }
    
    /**
     * Enqueues scripts on the frontend checkout page.
     */
    public function enqueue_checkout_scripts() {
        if (is_checkout()) {
            wp_enqueue_script('oms-checkout-tracker', OMS_PLUGIN_URL . 'assets/js/checkout-tracker.js', ['jquery'], OMS_VERSION, true);
        }
    }
}

