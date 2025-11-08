<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles all AJAX requests for the plugin.
 */
class OMS_Ajax {

    public function __construct() {
        // Intentionally left blank.
    }

    /**
     * Register all AJAX hooks.
     */
    public function load_hooks() {
        $ajax_actions = [
            'send_to_courier', 'sync_pathao_cities', 'sync_pathao_zones', 'sync_pathao_areas',
            'clear_plugin_cache', 'get_pathao_zones_for_order_page', 'get_pathao_areas_for_order_page',
            'search_products', 'get_product_details_for_order', 'save_order_details', 'update_order_status',
            'create_order', 'get_customer_history', 'add_order_note', 'get_courier_history', 'save_couriers',
            'get_invoice_html', 'get_sticker_html', 'update_status_from_scan'
        ];
        foreach ($ajax_actions as $action) {
            add_action('wp_ajax_oms_ajax_' . $action, [$this, 'ajax_' . $action]);
        }
    }

    /**
     * AJAX handler for updating order status via barcode scan.
     */
    public function ajax_update_status_from_scan() {
        check_ajax_referer('oms_scan_nonce', 'nonce');
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $order_number = isset($_POST['order_number']) ? sanitize_text_field(trim($_POST['order_number'])) : '';
        $target_status = isset($_POST['target_status']) ? sanitize_key($_POST['target_status']) : '';

        if (empty($order_number) || empty($target_status)) {
            wp_send_json_error(['message' => 'Missing order number or target status.']);
        }

        global $wpdb;
        // Try finding order by _order_number meta first (WooCommerce HPOS compatibility)
        $order_id = $wpdb->get_var($wpdb->prepare(
            "SELECT order_id FROM {$wpdb->prefix}wc_orders WHERE number = %s LIMIT 1",
             $order_number
        ));

        // Fallback for older WC versions or if number isn't stored separately yet
        if (!$order_id) {
             $order_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_order_number' AND meta_value = %s", $order_number));
        }

        // Final fallback: assume order number might be the ID
        if (!$order_id) {
            $order_id = absint($order_number);
        }


        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => "Order #{$order_number} not found."]);
        }

        $current_status = $order->get_status();
        $current_status_name = wc_get_order_status_name($current_status);

        $response_payload = [
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(), // Use getter for consistency
            'previous_status_name' => $current_status_name
        ];

        if ($current_status === $target_status) {
            $response_payload['status'] = 'skipped';
            $response_payload['message'] = "Order is already in '" . wc_get_order_status_name($target_status) . "' status.";
            wp_send_json_success($response_payload);
        }

        // Use helper to check validity
        if (class_exists('OMS_Helpers') && OMS_Helpers::is_valid_status_transition($current_status, $target_status)) {
            $order->update_status($target_status, 'Status updated via barcode scan.', true); // true = save order notes
            $order->save(); // Ensure changes are persisted
            $response_payload['status'] = 'success';
            $response_payload['message'] = 'Status successfully updated to ' . wc_get_order_status_name($target_status);
            wp_send_json_success($response_payload);
        } else {
            $response_payload['status'] = 'skipped';
            $response_payload['message'] = "Invalid transition from '{$current_status_name}' to '" . wc_get_order_status_name($target_status) . "'. Workflow rules applied.";
            wp_send_json_success($response_payload);
        }
    }


    /**
     * AJAX handler to generate and return HTML for invoices.
     */
    public function ajax_get_invoice_html() {
        check_ajax_referer('oms_invoice_nonce', 'nonce');
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $order_ids = isset($_POST['order_ids']) ? array_map('absint', $_POST['order_ids']) : [];
        if (empty($order_ids)) {
            wp_send_json_error(['message' => 'No orders selected.']);
        }

        $invoice_generator = new OMS_Invoice();
        $html = $invoice_generator->generate_invoices_html($order_ids);

        if ($html) {
            wp_send_json_success(['html' => $html]);
        } else {
            wp_send_json_error(['message' => 'Could not generate invoice. An unknown error occurred.']);
        }
    }

    /**
     * AJAX handler to generate and return HTML for stickers.
     */
    public function ajax_get_sticker_html() {
        check_ajax_referer('oms_sticker_nonce', 'nonce');
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $order_ids = isset($_POST['order_ids']) ? array_map('absint', $_POST['order_ids']) : [];
        if (empty($order_ids)) {
            wp_send_json_error(['message' => 'No orders selected.']);
        }

        $sticker_generator = new OMS_Sticker();
        $html = $sticker_generator->generate_stickers_html($order_ids);

        if ($html) {
            wp_send_json_success(['html' => $html]);
        } else {
            wp_send_json_error(['message' => 'Could not generate sticker. An unknown error occurred.']);
        }
    }

    /**
     * AJAX handler to send an order to a courier.
     */
    public function ajax_send_to_courier() {
        check_ajax_referer('oms_ajax_nonce', 'nonce');
        if (!current_user_can('edit_shop_orders')) wp_send_json_error(['message' => 'Permission denied.']);

        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $order = wc_get_order($order_id);
        if (!$order) wp_send_json_error(['message' => 'Order not found.']);

        $courier_id = isset($_POST['courier_id']) ? sanitize_text_field($_POST['courier_id']) : ($order->get_meta('_oms_selected_courier_id', true) ?: get_option('oms_default_courier'));
        $courier = class_exists('OMS_Helpers') ? OMS_Helpers::get_courier_by_id($courier_id) : null;
        if (!$courier) wp_send_json_error(['message' => 'Courier configuration not found. Please select a valid courier.']);

        $result = ['success' => false, 'message' => 'Invalid courier type specified.'];
        $final_response = [];

        if ($courier['type'] === 'steadfast' && class_exists('OMS_Steadfast_API')) {
            $api = new OMS_Steadfast_API($courier['credentials']);
            $result = $api->create_consignment($order);
            if ($result['success']) { $final_response = [ 'success' => true, 'message' => $result['message'], 'courier_name' => esc_html($courier['name']), 'consignment_id' => $result['consignment_id'], 'tracking_url' => "https://steadfast.com.bd/t/" . $result['tracking_code'] ]; }
        } elseif ($courier['type'] === 'pathao' && class_exists('OMS_Pathao_API')) {
            $api = new OMS_Pathao_API($courier['credentials']);
            $location_data = [ 'city_id' => $order->get_meta('_oms_pathao_city_id', true), 'zone_id' => $order->get_meta('_oms_pathao_zone_id', true), 'area_id' => $order->get_meta('_oms_pathao_area_id', true) ];
            if (empty($location_data['city_id']) || empty($location_data['zone_id'])) { wp_send_json_error(['message' => 'Pathao requires a City and Zone. Please set this on the order details page before sending.']); return; }
            $result = $api->create_order($order, $location_data);
             if ($result['success']) { $final_response = [ 'success' => true, 'message' => $result['message'], 'courier_name' => esc_html($courier['name']), 'consignment_id' => $result['consignment_id'], 'tracking_url' => "https://merchant.pathao.com/courier/orders/" . $result['consignment_id'] ]; }
        }

        if (!empty($final_response)) {
            $order->update_meta_data('_oms_selected_courier_id', $courier_id); // Save the selected courier
            $order->save();
            wp_send_json_success($final_response);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }

    public function ajax_sync_pathao_cities() {
        check_ajax_referer('oms_sync_nonce', 'nonce'); // Use correct nonce
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Permission denied.']);
        $courier_id = isset($_POST['courier_id']) ? sanitize_text_field($_POST['courier_id']) : null;
        $courier = $courier_id && class_exists('OMS_Helpers') ? OMS_Helpers::get_courier_by_id($courier_id) : null;
        if (!$courier || $courier['type'] !== 'pathao') { wp_send_json_error(['message' => 'A valid Pathao courier configuration is required to sync.']); }

        if (!class_exists('OMS_Pathao_API')) { wp_send_json_error(['message' => 'Pathao API class not found.']); }
        $api = new OMS_Pathao_API($courier['credentials']);
        $cities = $api->get_cities();
        if (is_array($cities)) {
            global $wpdb; $table_name = $wpdb->prefix . 'oms_pathao_cities';
            $wpdb->query("TRUNCATE TABLE $table_name");
            foreach ($cities as $city) { $wpdb->insert($table_name, ['city_id' => absint($city['city_id']), 'city_name' => sanitize_text_field($city['city_name'])]); }
            wp_send_json_success(['message' => 'Cities synced.', 'cities' => $cities]);
        } else { wp_send_json_error(['message' => 'Failed to fetch cities from Pathao API. Check credentials for ' . esc_html($courier['name']) . '.']); }
    }

    public function ajax_sync_pathao_zones() {
        check_ajax_referer('oms_sync_nonce', 'nonce'); // Use correct nonce
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Permission denied.']);
        $city_id = isset($_POST['city_id']) ? absint($_POST['city_id']) : 0;
        if (!$city_id) wp_send_json_error(['message' => 'City ID is required.']);
        $courier_id = isset($_POST['courier_id']) ? sanitize_text_field($_POST['courier_id']) : null;
        $courier = $courier_id && class_exists('OMS_Helpers') ? OMS_Helpers::get_courier_by_id($courier_id) : null;
        if (!$courier || $courier['type'] !== 'pathao') { wp_send_json_error(['message' => 'A valid Pathao courier configuration is required to sync.']); }

        if (!class_exists('OMS_Pathao_API')) { wp_send_json_error(['message' => 'Pathao API class not found.']); }
        $api = new OMS_Pathao_API($courier['credentials']);
        $zones = $api->get_zones($city_id);
        if (is_array($zones)) {
            global $wpdb; $table_name = $wpdb->prefix . 'oms_pathao_zones';
            foreach ($zones as $zone) { $wpdb->replace($table_name, [ 'zone_id' => absint($zone['zone_id']), 'city_id' => $city_id, 'zone_name' => sanitize_text_field($zone['zone_name']) ]); }
            wp_send_json_success(['message' => 'Zones for city ' . $city_id . ' synced.', 'zones' => $zones]);
        } else { wp_send_json_error(['message' => 'Failed to fetch zones for city ' . $city_id]); }
    }

    public function ajax_sync_pathao_areas() {
        check_ajax_referer('oms_sync_nonce', 'nonce'); // Use correct nonce
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Permission denied.']);
        $zone_id = isset($_POST['zone_id']) ? absint($_POST['zone_id']) : 0;
        if (!$zone_id) wp_send_json_error(['message' => 'Zone ID is required.']);
        $courier_id = isset($_POST['courier_id']) ? sanitize_text_field($_POST['courier_id']) : null;
        $courier = $courier_id && class_exists('OMS_Helpers') ? OMS_Helpers::get_courier_by_id($courier_id) : null;
        if (!$courier || $courier['type'] !== 'pathao') { wp_send_json_error(['message' => 'A valid Pathao courier configuration is required to sync.']); }

        if (!class_exists('OMS_Pathao_API')) { wp_send_json_error(['message' => 'Pathao API class not found.']); }
        $api = new OMS_Pathao_API($courier['credentials']);
        $areas = $api->get_areas($zone_id);
        if (is_array($areas)) {
            global $wpdb; $table_name = $wpdb->prefix . 'oms_pathao_areas';
             foreach ($areas as $area) { $wpdb->replace($table_name, [ 'area_id' => absint($area['area_id']), 'zone_id' => $zone_id, 'area_name' => sanitize_text_field($area['area_name']) ]); }
            wp_send_json_success(['message' => 'Areas for zone ' . $zone_id . ' synced.']);
        } else { wp_send_json_success(['message' => 'No areas found for zone ' . $zone_id . '.']); } // Success even if no areas
    }

    public function ajax_clear_plugin_cache() {
        check_ajax_referer('oms_cache_nonce', 'nonce'); // Use correct nonce
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Permission denied.']);
        global $wpdb; $count = 0;
        // Adjusted transient key patterns
        $transient_keys = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_oms\_pathao\_token\_%' OR option_name LIKE '\_transient\_oms\_courier\_rate\_%' OR option_name LIKE '\_transient\_oms\_courier\_history\_%'");
        foreach ($transient_keys as $key) {
            $transient_name = str_replace(['_transient_timeout_', '_transient_'], '', $key); // Handle both types
            if (delete_transient($transient_name)) { $count++; }
        }
        wp_send_json_success(['message' => $count . ' cache entries cleared successfully.']);
    }

    public function ajax_get_pathao_zones_for_order_page() {
        check_ajax_referer('oms_ajax_nonce', 'nonce');
        $city_id = isset($_POST['city_id']) ? absint($_POST['city_id']) : 0; if (!$city_id) wp_send_json_error([]);
        global $wpdb; $table = $wpdb->prefix . 'oms_pathao_zones';
        $zones = $wpdb->get_results($wpdb->prepare("SELECT zone_id, zone_name FROM $table WHERE city_id = %d ORDER BY zone_name ASC", $city_id));
        wp_send_json_success($zones);
    }

    public function ajax_get_pathao_areas_for_order_page() {
        check_ajax_referer('oms_ajax_nonce', 'nonce');
        $zone_id = isset($_POST['zone_id']) ? absint($_POST['zone_id']) : 0; if (!$zone_id) wp_send_json_error([]);
        global $wpdb; $table = $wpdb->prefix . 'oms_pathao_areas';
        $areas = $wpdb->get_results($wpdb->prepare("SELECT area_id, area_name FROM $table WHERE zone_id = %d ORDER BY area_name ASC", $zone_id));
        wp_send_json_success($areas);
    }

    public function ajax_search_products() {
        check_ajax_referer('oms_ajax_nonce', 'nonce');
        $search_term = isset($_POST['search_term']) ? sanitize_text_field(trim($_POST['search_term'])) : ''; if (strlen($search_term) < 2) { wp_send_json_error([]); }
        $products = wc_get_products(['s' => $search_term, 'limit' => 10, 'status' => 'publish', 'return' => 'ids']); // Get IDs only for performance
        $found_products = [];
        if (!empty($products)) {
            foreach ($products as $product_id) {
                $p = wc_get_product($product_id);
                if ($p) {
                     $found_products[] = [
                        'id'=>$p->get_id(),
                        'name'=>$p->get_name(),
                        'sku'=>$p->get_sku()?:'N/A',
                        'price_html'=>$p->get_price_html(),
                        'image_url'=>wp_get_attachment_image_url($p->get_image_id(),'thumbnail') ?: wc_placeholder_img_src(), // Fallback placeholder
                        'stock_quantity'=>$p->get_stock_quantity()??'∞' // Use infinity symbol if null
                    ];
                }
            }
        }
        wp_send_json_success($found_products);
    }

    public function ajax_get_product_details_for_order() {
        check_ajax_referer('oms_ajax_nonce', 'nonce');
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $product = wc_get_product($product_id);
        if (!$product) { wp_send_json_error(['message' => 'Product not found.']); }
        wp_send_json_success([
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'sku' => $product->get_sku() ?: 'N/A',
            'price' => $product->get_price(), // Use raw price for calculations
            'image_url' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') ?: wc_placeholder_img_src() // Fallback placeholder
        ]);
    }

    public function ajax_save_order_details() {
        check_ajax_referer('oms_ajax_nonce', 'nonce');
        try {
            if (!current_user_can('edit_shop_orders')) { // Check capability
                throw new Exception('Permission denied.');
            }
            if (!isset($_POST['order_data'])) throw new Exception('No data.');
            $data = json_decode(stripslashes($_POST['order_data']), true);
            if (json_last_error() !== JSON_ERROR_NONE) throw new Exception('Invalid data.');
            $order = wc_get_order(isset($data['order_id']) ? absint($data['order_id']) : 0);
            if (!$order) throw new Exception('Order not found.');

            // --- Update Customer Details ---
            $cust = $data['customer'];
            $name = explode(' ', trim($cust['name']), 2);
            $order->set_billing_first_name($name[0]);
            $order->set_billing_last_name($name[1] ?? '');
            $order->set_shipping_first_name($name[0]); // Assume same shipping/billing
            $order->set_shipping_last_name($name[1] ?? '');
            $order->set_billing_phone($cust['phone']);
            $order->set_billing_address_1($cust['address_1']);
            $order->set_shipping_address_1($cust['address_1']); // Assume same shipping/billing
            if (isset($cust['note'])) $order->set_customer_note($cust['note']);

            // --- Update Courier & Pathao Meta ---
            if (isset($data['courier_id'])) $order->update_meta_data('_oms_selected_courier_id', sanitize_text_field($data['courier_id']));
            if (isset($data['pathao_location'])) {
                $order->update_meta_data('_oms_pathao_city_id', sanitize_text_field($data['pathao_location']['city_id']));
                $order->update_meta_data('_oms_pathao_zone_id', sanitize_text_field($data['pathao_location']['zone_id']));
                $order->update_meta_data('_oms_pathao_area_id', sanitize_text_field($data['pathao_location']['area_id']));
            }

            // --- Update Order Items ---
            $order->remove_order_items('line_item'); // Clear existing items
            if (empty($data['items'])) {
                 throw new Exception('Cannot update order with no items.');
            }
            foreach ($data['items'] as $item_data) {
                $product = wc_get_product(absint($item_data['product_id']));
                if (!$product) continue; // Skip if product not found

                $item_price = wc_format_decimal($item_data['price']);
                $item_qty = absint($item_data['quantity']);
                $item_total = $item_price * $item_qty;

                $item = new WC_Order_Item_Product();
                $item->set_product($product);
                $item->set_quantity($item_qty);
                $item->set_subtotal($item_total); // Set subtotal
                $item->set_total($item_total);    // Set total
                $order->add_item($item);
            }

            // --- Update Totals (Shipping, Discount) ---
            $totals = $data['totals'];
            $order->remove_order_items('shipping'); // Clear existing shipping
            if ($totals['shipping'] >= 0) { // Allow 0 shipping
                $ship = new WC_Order_Item_Shipping();
                $ship->set_method_title("Delivery Charge");
                $ship->set_total(wc_format_decimal($totals['shipping']));
                $order->add_item($ship);
            }
            $order->remove_order_items('fee'); // Clear existing fees (used for discount)
            if ($totals['discount'] > 0) {
                $fee = new WC_Order_Item_Fee();
                $fee->set_name('Discount');
                $fee->set_amount(-wc_format_decimal($totals['discount'])); // Negative value
                $fee->set_total(-wc_format_decimal($totals['discount']));  // Negative value
                $fee->set_tax_status('none');
                $order->add_item($fee);
            }

            $order->calculate_totals(true); // Recalculate totals, true = and save
            $order_id_saved = $order->save(); // Save all changes

             if (!$order_id_saved) {
                 throw new Exception('Failed to save order updates.');
             }

            wp_send_json_success(['message' => 'Order updated successfully!', 'new_total' => $order->get_total()]);
        } catch (Exception $e) {
            error_log("OMS Save Order Details Error: " . $e->getMessage()); // Log error
            wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function ajax_update_order_status() {
        ob_start(); check_ajax_referer('oms_ajax_nonce', 'nonce');
        try {
            if (!current_user_can('edit_shop_orders')) { // Check capability
                throw new Exception('Permission denied.');
            }
            $order = wc_get_order(isset($_POST['order_id']) ? absint($_POST['order_id']) : 0);
            $new_status = isset($_POST['new_status']) ? sanitize_key($_POST['new_status']) : ''; // Use sanitize_key
            if (!$order) throw new Exception('Order not found.');

            // Use helper to check validity
            if (class_exists('OMS_Helpers') && !OMS_Helpers::is_valid_status_transition($order->get_status(), $new_status)) {
                 throw new Exception('This status change is not permitted by the workflow rules.');
             }

            // Check if status is valid WC status
            if (empty($new_status) || !array_key_exists('wc-'.$new_status, wc_get_order_statuses())) {
                 throw new Exception('Invalid target status specified.');
             }

            $result = $order->update_status($new_status, 'Status updated from custom order page.', true); // true = save order notes

             if (!$result) {
                 throw new Exception('Failed to update order status.');
             }

            $order->save(); // Persist changes
            ob_clean(); wp_send_json_success(['message' => 'Status updated successfully!']);
        } catch (Exception $e) {
            ob_clean();
            error_log("OMS Update Status Error: " . $e->getMessage()); // Log error
            wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
        }
    }


    /**
     * AJAX handler to create a new WooCommerce order from the custom Add Order page.
     */
    public function ajax_create_order() {
        check_ajax_referer('oms_ajax_nonce', 'nonce');
        try {
            if (!current_user_can('edit_shop_orders')) { // Check capability
                 throw new Exception('Permission denied.');
            }
            if (!isset($_POST['order_data'])) throw new Exception('No data.');
            $data = json_decode(stripslashes($_POST['order_data']), true);
            if (json_last_error() !== JSON_ERROR_NONE) throw new Exception('Invalid data.');

            $order = wc_create_order(['status' => 'wc-completed']); // Start with 'completed' status
             if (!is_a($order, 'WC_Order')) { // Check if order creation failed
                 throw new Exception('Failed to create WooCommerce order object.');
             }

            $cust = $data['customer']; $name = explode(' ', trim($cust['name']), 2);
            $addr = ['first_name'=>$name[0],'last_name'=>$name[1]??'','address_1'=>$cust['address_1'],'phone'=>$cust['phone']];
            $order->set_address($addr,'billing'); $order->set_address($addr,'shipping');
            $order->set_customer_note($cust['note']);

            // FIX: Save the Order Source from the data, defaulting to 'admin' only if empty
            // The value is sanitized and then saved to the WooCommerce attribution meta field.
            $order_source = !empty($data['order_source']) ? sanitize_text_field($data['order_source']) : 'admin';
            $order->update_meta_data('_wc_order_attribution_source_type', $order_source);


            if (!empty($data['courier_id'])) $order->update_meta_data('_oms_selected_courier_id', sanitize_text_field($data['courier_id']));
            if (isset($data['pathao_location']) && !empty($data['pathao_location']['city_id']) && !empty($data['pathao_location']['zone_id'])) {
                $order->update_meta_data('_oms_pathao_city_id', sanitize_text_field($data['pathao_location']['city_id']));
                $order->update_meta_data('_oms_pathao_zone_id', sanitize_text_field($data['pathao_location']['zone_id']));
                if (!empty($data['pathao_location']['area_id'])) $order->update_meta_data('_oms_pathao_area_id', sanitize_text_field($data['pathao_location']['area_id']));
            }

            if (empty($data['items'])) {
                 throw new Exception('Cannot create an order with no items.');
            }
            foreach ($data['items'] as $item_data) {
                $product = wc_get_product(absint($item_data['product_id'])); if (!$product) continue;
                $item_price = wc_format_decimal($item_data['price']);
                $item_qty = absint($item_data['quantity']);
                $item_total = $item_price * $item_qty;
                $item = new WC_Order_Item_Product();
                $item->set_product($product);
                $item->set_quantity($item_qty);
                $item->set_subtotal($item_total);
                $item->set_total($item_total);
                $order->add_item($item);
            }
            $totals = $data['totals'];
            if ($totals['shipping'] >= 0) { $ship = new WC_Order_Item_Shipping(); $ship->set_method_title("Delivery Charge"); $ship->set_total(wc_format_decimal($totals['shipping'])); $order->add_item($ship); }
            if ($totals['discount'] > 0) { $fee = new WC_Order_Item_Fee(); $fee->set_name('Discount'); $fee->set_amount(-wc_format_decimal($totals['discount'])); $fee->set_total(-wc_format_decimal($totals['discount'])); $fee->set_tax_status('none'); $order->add_item($fee); }

            $order->calculate_totals(true); // Recalculate and save totals
            $order_id = $order->save(); // Save the order

            // --- ADDED LOGGING & ACTION HOOK ---
            error_log("OMS Log (Create Order): Attempting to fire oms_order_created for Order ID: " . $order_id); // LOGGING
            if ($order_id && $order) {
                do_action('oms_order_created', $order_id, $order); // Signal that OMS created an order
                error_log("OMS Log (Create Order): Fired oms_order_created for Order ID: " . $order_id); // LOGGING
            } elseif (!$order_id) {
                 error_log("OMS Log (Create Order): SKIPPED firing oms_order_created because order save failed."); // LOGGING
                 throw new Exception('Failed to save the WooCommerce order after creation.');
            } else {
                 error_log("OMS Log (Create Order): SKIPPED firing oms_order_created for Order ID: " . $order_id . " (Invalid order object?)"); // LOGGING
            }
            // --- END ADDED LOGGING & ACTION HOOK ---


            wp_send_json_success(['message' => 'Order created successfully!', 'order_id' => $order_id, 'redirect_url' => admin_url('admin.php?page=oms-order-details&order_id=' . $order_id)]);
        } catch (Exception $e) {
             error_log("OMS ajax_create_order Exception: " . $e->getMessage()); // LOGGING
             wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
         }
    }

    public function ajax_get_customer_history() {
        check_ajax_referer('oms_ajax_nonce', 'nonce');
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        if (empty($phone)) wp_send_json_error(['message' => 'Phone number required.']);

        // Use WC HPOS-compatible query if available
        if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
             $orders = wc_get_orders(['limit' => -1, 'billing_phone' => $phone, 'return' => 'objects']);
        } else {
             $orders = wc_get_orders(['limit' => -1, 'billing_phone' => $phone]);
        }

        // --- FIXED INITIALIZATION ---
        // Initialize history array with slugs WITHOUT 'wc-' prefix using wc_get_order_statuses()
        $history = [];
        $all_wc_statuses_with_prefix = wc_get_order_statuses(); // Gets wc-completed => Completed, wc-cancelled => Cancelled, etc.
        foreach ($all_wc_statuses_with_prefix as $wc_slug => $label) {
            $slug = str_replace('wc-', '', $wc_slug); // Get slug without prefix
            $history[$slug] = 0; // Initialize e.g., $history['completed'] = 0, $history['cancelled'] = 0
        }
        // Ensure base keys exist
        $history['total_value'] = 0;
        $history['total_orders'] = 0;
        // --- END FIXED INITIALIZATION ---


        // Define conversion statuses using slugs WITHOUT 'wc-' prefix
        $conv_statuses = ['completed', 'shipped', 'ready-to-ship', 'delivered', 'exchange'];

        if ($orders) {
            $history['total_orders'] = count($orders);
            foreach ($orders as $order) {
                $status = $order->get_status(); // Get status without 'wc-'
                if (isset($history[$status])) {
                     $history[$status]++; // Increment using slug like 'completed', 'cancelled'
                } else {
                     // Log if a status is encountered that wasn't initialized (shouln't happen with new init)
                     error_log("OMS Customer History: Encountered unexpected status '{$status}' for order ID " . $order->get_id());
                     $history[$status] = 1; // Initialize and count just in case
                }
                // Check if status should count towards conversion value
                if (in_array($status, $conv_statuses)) {
                     $history['total_value'] += $order->get_total();
                }
            }
        }
        $history['total_value_formatted'] = wc_price($history['total_value']);

        // Log the final array before sending
        error_log("OMS Customer History Result for phone {$phone}: " . print_r($history, true));

        wp_send_json_success($history);
    }



    public function ajax_add_order_note() {
        check_ajax_referer('oms_ajax_nonce', 'nonce');
        try {
            if (!current_user_can('edit_shop_orders')) throw new Exception('Permission denied.');
            $order = wc_get_order(isset($_POST['order_id']) ? absint($_POST['order_id']) : 0);
            $note = isset($_POST['note']) ? wp_kses_post(trim($_POST['note'])) : ''; // Allow basic HTML
            if (!$order || empty($note)) throw new Exception('Missing data.');
            $order->add_order_note($note, false, true); // Add as private note, not visible to customer
            $order->save();
            wp_send_json_success(['message' => 'Note added successfully.']);
        } catch (Exception $e) {
             error_log("OMS Add Note Error: " . $e->getMessage()); // Log error
            wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function ajax_get_courier_history() {
        check_ajax_referer('oms_ajax_nonce', 'nonce');
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        if (empty($phone)) { wp_send_json_error(['message' => 'Phone number is required.']); }
        $transient_key = 'oms_courier_history_' . md5($phone);

        if (false !== ($cached_json = get_transient($transient_key))) {
            $cached_data = json_decode($cached_json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($cached_data)) {
                wp_send_json_success($cached_data);
                return;
            } else {
                delete_transient($transient_key); // Delete invalid cached data
            }
        }

        if (!class_exists('OMS_Courier_History_API')) { wp_send_json_error(['message' => 'Courier History API class not found.']); }
        $api = new OMS_Courier_History_API();
        $all_data = $api->get_overall_history_from_search_api($phone);

        if (isset($all_data['error'])) {
            wp_send_json_error(['message' => $all_data['error']]);
        } else {
            set_transient($transient_key, json_encode($all_data), DAY_IN_SECONDS); // Cache for 1 day
            wp_send_json_success($all_data);
        }
    }

    public function ajax_save_couriers() {
        check_ajax_referer('oms_settings_nonce', 'nonce'); // Use correct nonce
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Permission denied.']);
        $couriers_data = isset($_POST['couriers']) ? json_decode(stripslashes($_POST['couriers']), true) : [];
        if (json_last_error() !== JSON_ERROR_NONE) wp_send_json_error(['message' => 'Invalid data format.']);
        $sanitized_couriers = $this->sanitize_couriers_settings($couriers_data);
        update_option('oms_couriers', $sanitized_couriers);
        wp_send_json_success(['message' => 'Courier settings saved successfully.']);
    }

    private function sanitize_couriers_settings($input) {
        $sanitized_input = [];
        if (is_array($input)) {
            foreach ($input as $courier_data) {
                if (empty($courier_data['id']) || empty($courier_data['name']) || empty($courier_data['type'])) continue;
                $sanitized_courier = [
                    'id' => sanitize_text_field($courier_data['id']),
                    'name' => sanitize_text_field($courier_data['name']),
                    'type' => sanitize_text_field($courier_data['type']),
                    'credentials' => []
                ];
                 if (is_array($courier_data['credentials'])) {
                     foreach ($courier_data['credentials'] as $cred_key => $cred_value) {
                         // Sanitize all except password-like fields
                         if (strpos($cred_key, 'password') !== false || strpos($cred_key, 'secret') !== false || strpos($cred_key, 'key') !== false) {
                              $sanitized_courier['credentials'][$cred_key] = trim($cred_value); // Keep sensitive fields as is, just trim
                         } else if ($cred_key === 'auto_send') {
                             $sanitized_courier['credentials'][$cred_key] = ($cred_value === 'yes' ? 'yes' : 'no'); // Sanitize checkbox
                         }
                         else {
                             $sanitized_courier['credentials'][$cred_key] = sanitize_text_field($cred_value);
                         }
                     }
                 }
                $sanitized_input[] = $sanitized_courier;
            }
        }
        return $sanitized_input;
    }
}

