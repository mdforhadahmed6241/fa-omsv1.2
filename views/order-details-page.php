<?php
$order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
$order = wc_get_order($order_id);

if (!$order) {
    echo '<div class="wrap"><h1>Invalid Order</h1><p>The requested order could not be found.</p></div>';
    return;
}

$statuses = wc_get_order_statuses();
$current_status = $order->get_status();

// **FIXED**: Courier selection logic using the new helper class
$all_couriers = OMS_Helpers::get_couriers();
$default_courier_id = get_option('oms_default_courier');
$selected_courier_id = $order->get_meta('_oms_selected_courier_id', true) ?: $default_courier_id;
$selected_courier = OMS_Helpers::get_courier_by_id($selected_courier_id);

// --- Pre-load all Pathao Cities for the dropdown ---
global $wpdb;
$cities_table = $wpdb->prefix . 'oms_pathao_cities';
$all_cities = $wpdb->get_results("SELECT city_id, city_name FROM $cities_table ORDER BY city_name ASC");

?>
<div class="wrap oms-order-details-wrap">
    <div id="oms-order-details-page-marker" data-couriers="<?php echo esc_attr(json_encode($all_couriers)); ?>"></div>

    <h1><?php printf(esc_html__('Edit Order #%s', 'oms-plugin'), esc_html($order->get_order_number())); ?></h1>
    <input type="hidden" id="oms-order-id" value="<?php echo esc_attr($order_id); ?>">

    <?php if (get_option('oms_workflow_enabled', 'yes') === 'yes') : ?>
        <div class="oms-card oms-workflow-info-bar">
            <p><strong>Workflow is active.</strong> From the current status (<?php echo esc_html(wc_get_order_status_name($current_status)); ?>), you can change to:</p>
            <div id="oms-allowed-status-list" class="oms-status-button-group"></div>
        </div>
    <?php endif; ?>

    <div class="oms-card oms-status-bar">
        <div class="oms-status-control">
            <label for="oms-order-status">Order Status:</label>
            <select id="oms-order-status" name="order_status">
                <?php foreach ($statuses as $slug => $name) : $status_val = str_replace('wc-', '', $slug); ?>
                    <option value="<?php echo esc_attr($status_val); ?>" <?php selected($current_status, $status_val); ?>><?php echo esc_html($name); ?></option>
                <?php endforeach; ?>
            </select>
            <button class="button button-primary" id="oms-update-status-btn">Update Status</button>
            <span class="spinner"></span>
        </div>
        <div id="oms-status-response" class="oms-response-message"></div>
    </div>
    
    <div class="oms-card oms-courier-history-section">
        <h2>Courier Order History</h2>
        <div id="oms-courier-history-container">
            <p>Enter a mobile number to see courier history.</p>
        </div>
    </div>

    <div class="oms-layout-grid-row">
        <div class="oms-main-content-column">
            <div class="oms-card" id="oms-customer-details-card">
                <h2>Customer Details</h2>
                <div class="oms-customer-fields">
                    <div class="oms-form-group"><label for="oms-customer-phone">Mobile Number</label><div class="oms-input-with-icon"><input type="text" id="oms-customer-phone" value="<?php echo esc_attr($order->get_billing_phone()); ?>"><a href="tel:<?php echo esc_attr($order->get_billing_phone()); ?>" id="oms-tel-link" class="oms-input-icon"><span class="dashicons dashicons-phone"></span></a></div></div>
                    <div class="oms-form-group"><label for="oms-customer-name">Name</label><input type="text" id="oms-customer-name" value="<?php echo esc_attr($order->get_formatted_billing_full_name()); ?>"></div>
                    <div class="oms-form-group oms-full-width"><label for="oms-customer-address">Address</label><textarea id="oms-customer-address" rows="3"><?php echo esc_textarea($order->get_billing_address_1() . ', ' . $order->get_billing_city()); ?></textarea></div>
                    <div class="oms-form-group oms-full-width"><label for="oms-shipping-note">Shipping Note</label><textarea id="oms-shipping-note" rows="2"><?php echo esc_textarea($order->get_customer_note()); ?></textarea></div>
                </div>
                
                <div id="oms-pathao-location-card" class="oms-pathao-location-section" style="display:none;">
                    <?php
                        $saved_city_id = $order->get_meta('_oms_pathao_city_id', true);
                        $saved_zone_id = $order->get_meta('_oms_pathao_zone_id', true);
                        $saved_area_id = $order->get_meta('_oms_pathao_area_id', true);
                    ?>
                    <input type="hidden" id="oms-pathao-saved-city" value="<?php echo esc_attr($saved_city_id); ?>">
                    <input type="hidden" id="oms-pathao-saved-zone" value="<?php echo esc_attr($saved_zone_id); ?>">
                    <input type="hidden" id="oms-pathao-saved-area" value="<?php echo esc_attr($saved_area_id); ?>">

                    <h2 class="oms-pathao-heading">Pathao Delivery Location</h2>
                    <div class="oms-pathao-location-grid">
                        <div class="oms-form-group">
                            <label for="oms-pathao-city">City</label>
                            <select id="oms-pathao-city">
                                <option value="">Select City</option>
                                <?php foreach ($all_cities as $city) : ?>
                                    <option value="<?php echo esc_attr($city->city_id); ?>"><?php echo esc_html($city->city_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="oms-form-group">
                            <label for="oms-pathao-zone">Zone</label>
                            <select id="oms-pathao-zone"><option value="">Select City First</option></select>
                        </div>
                        <div class="oms-form-group">
                            <label for="oms-pathao-area">Area (Optional)</label>
                            <select id="oms-pathao-area"><option value="">Select Zone First</option></select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="oms-card" id="oms-ordered-products-card">
                 <h2>Ordered Products</h2>
                <div id="oms-ordered-products">
                    <?php foreach ($order->get_items() as $item_id => $item) : $product = $item->get_product(); if(!$product) continue;?>
                        <div class="oms-ordered-product-item" data-product-id="<?php echo esc_attr($item->get_product_id()); ?>" data-variation-id="<?php echo esc_attr($item->get_variation_id()); ?>">
                            <img src="<?php echo esc_url(wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') ?: wc_placeholder_img_src()); ?>" alt="<?php echo esc_attr($item->get_name()); ?>">
                            <div class="oms-ordered-item-details"><span class="oms-product-name"><?php echo esc_html($item->get_name()); ?></span><span class="oms-product-sku">SKU: <?php echo esc_html($product->get_sku() ?: 'N/A'); ?></span></div>
                            <div class="oms-item-controls">
                                <div class="oms-quantity-control"><button class="button qty-btn minus">-</button><input type="number" class="oms-item-quantity" value="<?php echo esc_attr($item->get_quantity()); ?>" min="1"><button class="button qty-btn plus">+</button></div>
                                <div class="oms-price-control"><span>Price:</span><input type="number" class="oms-item-price" value="<?php echo esc_attr(wc_format_decimal($item->get_subtotal() / ($item->get_quantity() ?: 1) )); ?>" step="any"></div>
                                <div class="oms-total-control"><span>Total:</span><span class="oms-item-total"><?php echo wp_kses_post($order->get_formatted_line_subtotal($item)); ?></span></div>
                                <button class="oms-remove-item-btn">&times;</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="oms-sidebar-column">
             <div class="oms-card">
                <h2>Delivery Method</h2>
                <div class="oms-form-group">
                    <label for="oms-courier-select">Select Courier</label>
                    <select id="oms-courier-select">
                        <option value="">-- Select Courier --</option>
                        <?php foreach($all_couriers as $c) : ?>
                            <option value="<?php echo esc_attr($c['id']); ?>" <?php selected($c['id'], $selected_courier_id); ?>><?php echo esc_html($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="oms-courier-action-container">
                <?php
                $consignment_id_steadfast = $order->get_meta('_steadfast_consignment_id');
                $consignment_id_pathao = $order->get_meta('_pathao_consignment_id');

                if ($consignment_id_steadfast) {
                    $tracking_code = $order->get_meta('_steadfast_tracking_code');
                    $tracking_url = "https://steadfast.com.bd/t/{$tracking_code}";
                    echo '<div class="oms-courier-info"><p><strong>Consignment ID:</strong> ' . esc_html($consignment_id_steadfast) . '</p><a href="' . esc_url($tracking_url) . '" target="_blank" class="button button-secondary">Track on Steadfast</a></div>';
                } elseif ($consignment_id_pathao) {
                    $tracking_url = "https://merchant.pathao.com/courier/orders/{$consignment_id_pathao}";
                     echo '<div class="oms-courier-info"><p><strong>Consignment ID:</strong> ' . esc_html($consignment_id_pathao) . '</p><a href="' . esc_url($tracking_url) . '" target="_blank" class="button button-secondary">Track on Pathao</a></div>';
                } elseif (in_array($order->get_status(), ['completed', 'ready-to-ship', 'shipped'])) {
                    echo '<button class="button button-primary" id="oms-send-to-courier-btn">Send to Courier</button><span id="oms-courier-spinner" class="spinner"></span><div id="oms-courier-response" class="oms-response-message" style="text-align: left; margin-top: 10px;"></div>';
                } else {
                     echo "<p>Order must be 'Completed', 'Ready to Ship' or 'Shipped' to send to courier.</p>";
                }
                ?>
                </div>
            </div>
            
            <div class="oms-card">
                <h2>Customer History</h2>
                <div class="oms-customer-history-stats">
                   <!-- Loaded by JS -->
                </div>
            </div>

            <div class="oms-card oms-note-card-rebuilt">
                <h2>Private Note</h2>
                <div class="oms-note-add-form">
                    <?php
                    $notes = wc_get_order_notes(['order_id' => $order_id, 'type' => 'internal', 'orderby' => 'date_created', 'order' => 'DESC']);
                    $latest_note_content = '';
                    $system_message_identifiers = ['Status updated from custom order page.', 'Order status changed from', 'was upgraded to', 'API returned', 'was generated for this customer', 'sent to'];
                    if ($notes) {
                        foreach($notes as $note) {
                            $is_system_note = false;
                            foreach ($system_message_identifiers as $identifier) { if (strpos($note->content, $identifier) !== false) { $is_system_note = true; break; } }
                            if (!$is_system_note) { $latest_note_content = $note->content; break; }
                        }
                    }
                    ?>
                    <textarea id="oms-add-note-textarea" placeholder="Add a private note for staff..." rows="4"><?php echo esc_textarea($latest_note_content); ?></textarea>
                    <button id="oms-add-note-button" class="button button-primary">Add Note</button>
                    <span class="spinner"></span>
                </div>
                <div id="oms-note-response" style="display:none; margin-top: 10px;"></div>
            </div>

            <div class="oms-card">
                <h2>Click To Add Products</h2>
                <div class="oms-product-search-box"><input type="text" id="oms-product-search" placeholder="Type to search..."></div>
                <div id="oms-search-results"></div>
            </div>
        </div>
    </div>

    <div class="oms-payment-footer oms-card">
        <div class="oms-payment-fields">
            <div class="oms-form-group"><label for="oms-order-discount">Discount</label><input type="number" id="oms-order-discount" value="<?php echo esc_attr($order->get_discount_total()); ?>" step="any"></div>
            <div class="oms-form-group"><label for="oms-order-subtotal">Sub Total</label><input type="text" id="oms-order-subtotal" value="<?php echo esc_attr($order->get_subtotal()); ?>" readonly></div>
            <div class="oms-form-group"><label for="oms-order-shipping">Delivery Charge</label><input type="number" id="oms-order-shipping" value="<?php echo esc_attr($order->get_shipping_total()); ?>" step="any"></div>
            <div class="oms-form-group"><label for="oms-order-grandtotal" class="oms-grand-total-label">Grand Total</label><input type="text" id="oms-order-grandtotal" value="<?php echo esc_attr($order->get_total()); ?>" readonly></div>
        </div>
        <div class="oms-save-actions">
             <button class="button button-primary button-hero" id="oms-save-order-btn">Update Order</button>
             <span class="spinner"></span>
        </div>
        <div id="oms-save-response" class="oms-response-message"></div>
    </div>
    
    <?php if ($notes) : ?>
    <div class="oms-card oms-note-history-rebuilt">
        <h4>Note History</h4>
        <ul class="oms-note-list">
            <?php foreach ($notes as $note) : ?>
            <li class="oms-note-item">
                <div class="oms-note-content"><?php echo wp_kses_post(wpautop($note->content)); ?></div>
                <div class="oms-note-meta">
                    Added by <?php echo esc_html($note->added_by); ?> on <?php echo esc_html($note->date_created->date('M j, Y \a\t g:i a')); ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>
