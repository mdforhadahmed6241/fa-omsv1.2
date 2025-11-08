<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles all logic for capturing and managing incomplete orders.
 */
class OMS_Incomplete_Orders {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'oms_incomplete_orders';

        // AJAX handler for capturing checkout data
        add_action('wp_ajax_oms_capture_incomplete_order', [$this, 'capture_incomplete_order']);
        add_action('wp_ajax_nopriv_oms_capture_incomplete_order', [$this, 'capture_incomplete_order']);

        // AJAX handler for creating a real order from an incomplete one
        add_action('wp_ajax_oms_ajax_create_incomplete_order', [$this, 'ajax_create_order_from_incomplete']);

        // AJAX handler for deleting an incomplete order
        add_action('wp_ajax_oms_ajax_delete_incomplete_order', [$this, 'ajax_delete_incomplete_order']);

        // AJAX handler for adding a note to an incomplete order
        add_action('wp_ajax_oms_ajax_add_incomplete_order_note', [$this, 'ajax_add_incomplete_order_note']);

        // Hook to delete the incomplete order record when a real order is placed
        add_action('woocommerce_checkout_order_processed', [$this, 'delete_incomplete_order_on_completion'], 10, 1);

        add_action('init', [$this, 'ensure_wc_session']);
    }

    public function ensure_wc_session() {
         if (!is_admin() && function_exists('WC') && WC()->session && !WC()->session->has_session()) {
            WC()->session->set_customer_session_cookie(true);
        }
    }


    public function capture_incomplete_order() {
        global $wpdb;

        // Check if WC and session exist
        if (!function_exists('WC') || !WC()->session) {
             wp_send_json_error(['message' => 'WooCommerce session not available.']);
            return;
        }


        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $customer_data_json = isset($_POST['customer_data']) ? stripslashes($_POST['customer_data']) : '{}';
        $customer_data = json_decode($customer_data_json, true);

        $session_id = WC()->session->get_customer_id();

        if (empty($phone) || strlen($phone) < 5 || empty($session_id)) {
            wp_send_json_error(['message' => 'Insufficient data (Phone or Session ID missing).']);
            return;
        }

        $cart = WC()->cart->get_cart_for_session();
        if(empty($cart)) {
             wp_send_json_error(['message' => 'Cart is empty.']);
             return; // Don't save if the cart is empty
        }

        $cart_contents = serialize($cart);
        $serialized_customer_data = serialize($customer_data);
        $current_time = current_time('mysql');

        $existing_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $this->table_name WHERE session_id = %s", $session_id));

        try {
            if ($existing_id) {
                $wpdb->update(
                    $this->table_name,
                    [
                        'phone' => $phone,
                        'customer_data' => $serialized_customer_data,
                        'cart_contents' => $cart_contents,
                        'updated_at' => $current_time
                    ],
                    ['id' => $existing_id],
                    ['%s', '%s', '%s', '%s'], // Data formats
                    ['%d'] // Where format
                );
            } else {
                $wpdb->insert(
                    $this->table_name,
                    [
                        'session_id' => $session_id,
                        'phone' => $phone,
                        'customer_data' => $serialized_customer_data,
                        'cart_contents' => $cart_contents,
                        'created_at' => $current_time,
                        'updated_at' => $current_time
                    ],
                     ['%s', '%s', '%s', '%s', '%s', '%s'] // Data formats
                );
            }
        } catch (Exception $e) {
             error_log("OMS Incomplete Order DB Error: " . $e->getMessage());
             wp_send_json_error(['message' => 'Database error during capture.']);
             return;
        }


        wp_send_json_success(['message' => 'Data captured.']);
    }

    public function delete_incomplete_order_on_completion($order_id) {
        // Check if WC and session exist
        if (!function_exists('WC') || !WC()->session) {
            return;
        }

        global $wpdb;
        $session_id = WC()->session->get_customer_id();
        if ($session_id) {
            $wpdb->delete($this->table_name, ['session_id' => $session_id], ['%s']);
        }
    }

    public function ajax_create_order_from_incomplete() {
        check_ajax_referer('oms_ajax_nonce', 'nonce');
        try {
            if (!current_user_can('edit_shop_orders')) {
                throw new Exception('Permission denied.');
            }
            if (!isset($_POST['order_data'])) throw new Exception('No order data received.');

            $order_data = json_decode(stripslashes($_POST['order_data']), true);
            if (json_last_error() !== JSON_ERROR_NONE) throw new Exception('Invalid data format.');

            $incomplete_order_id = isset($_POST['incomplete_order_id']) ? absint($_POST['incomplete_order_id']) : 0;
            if (!$incomplete_order_id) {
                throw new Exception('Incomplete order ID is missing.');
            }

            $order = wc_create_order();
            if (!is_a($order, 'WC_Order')) { // Check if order creation failed
                throw new Exception('Failed to create WooCommerce order object.');
            }
            $order->set_status('wc-processing'); // Set a default status

            // **NEW FEATURE: Set attribution source to 'incomplete'**
            $order->update_meta_data('_wc_order_attribution_source_type', 'incomplete');
            
            $customer_details = $order_data['customer'];
            $name_parts = explode(' ', trim($customer_details['name']), 2);
            $address = [
                'first_name' => $name_parts[0],
                'last_name'  => $name_parts[1] ?? '',
                'address_1'  => $customer_details['address_1'],
                'phone'      => $customer_details['phone']
                // Add city, postcode, country if needed, though they might not be captured
            ];
            $order->set_address($address, 'billing');
            $order->set_address($address, 'shipping'); // Assuming same shipping/billing
            $order->set_customer_note($customer_details['note']);

            // Save selected courier if provided
            if (!empty($order_data['courier_id'])) {
                 $order->update_meta_data('_oms_selected_courier_id', sanitize_text_field($order_data['courier_id']));
            }

            // Save Pathao details if provided
            if (isset($order_data['pathao_location']) && !empty($order_data['pathao_location']['city_id']) && !empty($order_data['pathao_location']['zone_id'])) {
                $order->update_meta_data('_oms_pathao_city_id', sanitize_text_field($order_data['pathao_location']['city_id']));
                $order->update_meta_data('_oms_pathao_zone_id', sanitize_text_field($order_data['pathao_location']['zone_id']));
                // Area is optional
                if (!empty($order_data['pathao_location']['area_id'])) {
                    $order->update_meta_data('_oms_pathao_area_id', sanitize_text_field($order_data['pathao_location']['area_id']));
                }
            }

            // Add items
            if (empty($order_data['items'])) {
                throw new Exception('Cannot create an order with no items.');
            }
            foreach ($order_data['items'] as $item_data) {
                $product = wc_get_product(absint($item_data['product_id']));
                if (!$product) {
                     error_log("OMS Create Incomplete: Product ID {$item_data['product_id']} not found.");
                     continue; // Skip if product doesn't exist
                }
                $item_price = wc_format_decimal($item_data['price']);
                $item_qty = absint($item_data['quantity']);
                $item_total = $item_price * $item_qty;

                $item = new WC_Order_Item_Product();
                $item->set_product($product);
                $item->set_quantity($item_qty);
                $item->set_subtotal($item_total); // Set subtotal based on price * qty
                $item->set_total($item_total);    // Set total based on price * qty
                $order->add_item($item);
            }

            // Add shipping and discount
            $totals = $order_data['totals'];
            if (!empty($totals['shipping']) && $totals['shipping'] > 0) {
                $shipping_rate = new WC_Order_Item_Shipping();
                $shipping_rate->set_method_title("Delivery Charge"); // Or a more specific title if available
                $shipping_rate->set_total(wc_format_decimal($totals['shipping']));
                $order->add_item($shipping_rate);
            }
            if (!empty($totals['discount']) && $totals['discount'] > 0) {
                $fee = new WC_Order_Item_Fee();
                $fee->set_name('Discount');
                $fee->set_amount(-wc_format_decimal($totals['discount'])); // Discount is negative
                $fee->set_total(-wc_format_decimal($totals['discount']));  // Discount is negative
                $fee->set_tax_status('none'); // Usually discounts don't have tax
                $order->add_item($fee);
            }

            $order->calculate_totals(); // Recalculate based on added items
            $order_id = $order->save();

            // --- ADDED LOGGING & ACTION HOOK ---
            error_log("OMS Log (Incomplete): Attempting to fire oms_order_created for Order ID: " . $order_id); // LOGGING
            if ($order_id && $order) {
                do_action('oms_order_created', $order_id, $order); // Signal that OMS created an order
                error_log("OMS Log (Incomplete): Fired oms_order_created for Order ID: " . $order_id); // LOGGING
                // Delete incomplete AFTER signaling and successful save
                global $wpdb;
                $delete_result = $wpdb->delete($this->table_name, ['id' => $incomplete_order_id], ['%d']);
                 if (false === $delete_result) {
                     error_log("OMS Log (Incomplete): Failed to delete incomplete record ID: " . $incomplete_order_id . " after creating order " . $order_id); // LOGGING
                 }
            } elseif (!$order_id) {
                 error_log("OMS Log (Incomplete): SKIPPED firing oms_order_created because order save failed."); // LOGGING
                 throw new Exception('Failed to save the WooCommerce order.');
            } else {
                 error_log("OMS Log (Incomplete): SKIPPED firing oms_order_created for Order ID: " . $order_id . " (Invalid order object?)"); // LOGGING
            }
            // --- END ADDED LOGGING & ACTION HOOK ---

            wp_send_json_success([
                'message' => 'Order created successfully!',
                'order_id' => $order_id,
                'redirect_url' => admin_url('admin.php?page=oms-order-details&order_id=' . $order_id)
            ]);

        } catch (Exception $e) {
            error_log("OMS Create Incomplete Error: " . $e->getMessage()); // Log the error
            wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
        }
    }


    public function ajax_delete_incomplete_order() {
        check_ajax_referer('oms_ajax_nonce', 'nonce');
        try {
            if (!current_user_can('manage_woocommerce')) {
                throw new Exception('Permission denied.');
            }
            $incomplete_order_id = isset($_POST['incomplete_order_id']) ? absint($_POST['incomplete_order_id']) : 0;
            if (!$incomplete_order_id) {
                throw new Exception('Invalid ID.');
            }

            global $wpdb;
            $result = $wpdb->delete($this->table_name, ['id' => $incomplete_order_id], ['%d']);

            if ($result === false) {
                 throw new Exception('Database error occurred while deleting.');
            }
            if ($result === 0) {
                 throw new Exception('Record not found or already deleted.');
            }


            wp_send_json_success([
                'message' => 'Incomplete order record deleted successfully.',
                'redirect_url' => admin_url('admin.php?page=oms-incomplete-list')
            ]);

        } catch (Exception $e) {
             error_log("OMS Delete Incomplete Error: " . $e->getMessage()); // Log the error
            wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function ajax_add_incomplete_order_note() {
        check_ajax_referer('oms_ajax_nonce', 'nonce');
        try {
            if (!current_user_can('edit_shop_orders')) {
                throw new Exception('Permission denied.');
            }
            $incomplete_order_id = isset($_POST['incomplete_order_id']) ? absint($_POST['incomplete_order_id']) : 0;
            $note = isset($_POST['note']) ? wp_kses_post(trim($_POST['note'])) : ''; // Allow basic HTML in notes

            if (empty($incomplete_order_id)) {
                throw new Exception('Missing required data (ID).');
            }

            global $wpdb;
            $existing_data_serialized = $wpdb->get_var($wpdb->prepare(
                "SELECT customer_data FROM $this->table_name WHERE id = %d",
                $incomplete_order_id
            ));

            if (!$existing_data_serialized) {
                throw new Exception('Invalid incomplete order ID.');
            }

            // Use maybe_unserialize for safety
            $customer_data = maybe_unserialize($existing_data_serialized);
            if (!is_array($customer_data)) { // Check if unserialization worked
                 $customer_data = []; // Initialize if unserialization failed
                 error_log("OMS Add Note: Failed to unserialize customer data for incomplete order ID $incomplete_order_id.");
            }
            $customer_data['order_comments'] = $note; // Update or add the note

            $update_result = $wpdb->update(
                $this->table_name,
                ['customer_data' => serialize($customer_data), 'updated_at' => current_time('mysql')], // Also update timestamp
                ['id' => $incomplete_order_id],
                ['%s', '%s'], // Data formats
                ['%d'] // Where format
            );

             if ($update_result === false) {
                 throw new Exception('Database error occurred while updating note.');
             }

            wp_send_json_success(['message' => 'Note updated successfully.']);
        } catch (Exception $e) {
             error_log("OMS Add Note Error: " . $e->getMessage()); // Log the error
            wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
        }
    }
}

// Instantiate the class
new OMS_Incomplete_Orders();
