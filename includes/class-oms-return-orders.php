<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the database operations and status tracking for returned orders.
 */
class OMS_Return_Orders {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'oms_return_orders';

        // Hook to check for status changes to 'returned' or 'partial-return'
        add_action('woocommerce_order_status_changed', [$this, 'track_returned_order'], 10, 4);

        // AJAX handlers for the Return Product page
        add_action('wp_ajax_oms_ajax_update_return_status', [$this, 'ajax_update_return_status']);
        add_action('wp_ajax_oms_ajax_return_scan', [$this, 'ajax_return_scan']);
    }

    /**
     * Tracks the order when its status changes to 'returned' or 'partial-return'.
     *
     * @param int $order_id
     * @param string $old_status
     * @param string $new_status
     * @param WC_Order $order
     */
    public function track_returned_order($order_id, $old_status, $new_status, $order) {
        // Only track if the new status is a return status AND the old status wasn't already a return status
        $return_statuses = ['returned', 'partial-return'];
        if (in_array($new_status, $return_statuses) && !in_array($old_status, $return_statuses)) {
            global $wpdb;
            
            // Check if the order is already in the return table
            $existing_record = $wpdb->get_row($wpdb->prepare("SELECT id FROM $this->table_name WHERE order_id = %d", $order_id));

            // Determine if the product is 'Not Received' (0) or 'Received' (1).
            // When an order first changes to 'returned', the product is NOT yet received, so default to 0.
            $receive_status = 0; 

            if (!$existing_record) {
                // Insert new record, mark as NOT received (receive_status = 0)
                $wpdb->insert(
                    $this->table_name,
                    [
                        'order_id'          => $order_id,
                        'order_status_slug' => $new_status,
                        'order_date'        => $order->get_date_created()->format('Y-m-d H:i:s'),
                        'create_date'       => current_time('mysql'),
                        'receive_status'    => $receive_status,
                    ],
                    ['%d', '%s', '%s', '%s', '%d']
                );
            } else {
                 // If it already exists, update the status_slug and create_date (marking when it became returned)
                 // Keep the existing receive_status, but ensure it's not set to 'Received' if the status changes back.
                 // However, the intent is just to track the return action. We'll update only the status-specific fields.
                 $wpdb->update(
                    $this->table_name,
                    [
                        'order_status_slug' => $new_status,
                        'create_date'       => current_time('mysql'), // The date it entered the return table
                        'receive_status'    => 0, // Reset to Not Received on new return entry
                    ],
                    ['order_id' => $order_id],
                    ['%s', '%s', '%d'],
                    ['%d']
                );
            }
        } elseif (!in_array($new_status, $return_statuses) && in_array($old_status, $return_statuses)) {
            // Optional: If the order leaves a return status, you might want to remove it from the table.
            // For now, we'll keep it for history unless the status changes back to 'processing' or 'completed'.
            // To simplify, we only insert/update when a return status is set.
        }
    }
    
    /**
     * AJAX handler to update the receive status from the list page.
     */
    public function ajax_update_return_status() {
        check_ajax_referer('oms_return_nonce', 'nonce');
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(['message' => 'Permission denied.'], 403);
        }

        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $new_receive_status = isset($_POST['receive_status']) ? absint($_POST['receive_status']) : 0; // 1 or 0
        
        if (!$order_id) {
            wp_send_json_error(['message' => 'Invalid Order ID.'], 400);
        }

        global $wpdb;
        $result = $wpdb->update(
            $this->table_name,
            ['receive_status' => $new_receive_status],
            ['order_id' => $order_id],
            ['%d'],
            ['%d']
        );

        if ($result !== false) {
             $order = wc_get_order($order_id);
             if ($order && $new_receive_status == 1) {
                 $order->add_order_note('Product(s) received back into warehouse inventory from Return List page.', true);
                 $order->save();
             }
             wp_send_json_success(['message' => 'Return status updated successfully.']);
        } else {
             wp_send_json_error(['message' => 'Failed to update return status in database.']);
        }
    }
    
    /**
     * AJAX handler to update the receive status via barcode scan.
     */
    public function ajax_return_scan() {
        check_ajax_referer('oms_return_scan_nonce', 'nonce');
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $order_number = isset($_POST['order_number']) ? sanitize_text_field(trim($_POST['order_number'])) : '';

        if (empty($order_number)) {
            wp_send_json_error(['message' => 'Missing order number.']);
        }

        global $wpdb;
        
        // Find the actual WooCommerce Order ID from the order number (handles WC versions)
        $order_id_query = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_order_number' AND meta_value = %s", $order_number));
        if (!$order_id_query) { 
             $order_id_query = $wpdb->get_var($wpdb->prepare("SELECT order_id FROM {$wpdb->prefix}wc_orders WHERE number = %s LIMIT 1", $order_number));
        }
        $order_id = $order_id_query ?: absint($order_number); // Fallback to assuming order number is ID

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => "Order #{$order_number} not found in WooCommerce."], 404);
        }

        // Check if this order is in the return table and NOT yet received (receive_status = 0)
        $return_order = $wpdb->get_row($wpdb->prepare("SELECT id, receive_status FROM $this->table_name WHERE order_id = %d", $order->get_id()));

        $response_payload = [
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
        ];

        if (!$return_order) {
            $response_payload['status'] = 'error';
            $response_payload['message'] = "Order is not marked as 'Returned' or 'Partial Return' in the system.";
            wp_send_json_success($response_payload);
        }
        
        if ((int)$return_order->receive_status === 1) {
            $response_payload['status'] = 'skipped';
            $response_payload['message'] = "Order is already marked as received.";
            wp_send_json_success($response_payload);
        }
        
        // Update receive status to 1 (Received)
        $update_result = $wpdb->update(
            $this->table_name,
            ['receive_status' => 1],
            ['id' => $return_order->id],
            ['%d'],
            ['%d']
        );

        if ($update_result !== false) {
            $order->add_order_note('Product(s) received back into warehouse inventory via Return Scanner.', true);
            $order->save();
            $response_payload['status'] = 'success';
            $response_payload['message'] = 'Order successfully marked as received.';
            wp_send_json_success($response_payload);
        } else {
            $response_payload['status'] = 'error';
            $response_payload['message'] = 'Database error: Could not update status.';
            wp_send_json_error($response_payload);
        }
    }
}
