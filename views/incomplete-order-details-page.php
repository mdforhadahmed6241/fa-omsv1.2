<?php
global $wpdb;
$table_name = $wpdb->prefix . 'oms_incomplete_orders';
$id = isset($_GET['id']) ? absint($_GET['id']) : 0;

$inc_order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));

if (!$inc_order) {
    echo '<div class="wrap"><h1>Incomplete Order Not Found</h1><p>The requested record could not be found. It may have been completed or deleted.</p></div>';
    return;
}

$customer_data = unserialize($inc_order->customer_data);
$cart_contents = unserialize($inc_order->cart_contents);

// Data for form fields
$phone = $inc_order->phone;
$name = ($customer_data['billing_first_name'] ?? '') . ' ' . ($customer_data['billing_last_name'] ?? '');
$address = $customer_data['billing_address_1'] ?? '';
$note = $customer_data['order_comments'] ?? '';

// Check the 3-minute wait time
$updated_time = strtotime($inc_order->updated_at);
$current_time = current_time('timestamp');
$time_diff = $current_time - $updated_time;
$can_edit = $time_diff > 180; // 3 minutes = 180 seconds
$remaining_time = 180 - $time_diff;

// **FIXED**: Courier selection logic using the new helper class
$all_couriers = OMS_Helpers::get_couriers();
$default_courier_id = get_option('oms_default_courier');
?>

<div class="wrap oms-order-details-wrap">
    <h1>Edit Incomplete Order #<?php echo esc_html($inc_order->id); ?></h1>
    <input type="hidden" id="oms-incomplete-order-id" value="<?php echo esc_attr($inc_order->id); ?>">
    <div id="oms-add-order-page-marker" data-couriers="<?php echo esc_attr(json_encode($all_couriers)); ?>"></div>

    <div class="oms-card oms-courier-history-section">
        <h2>Courier Order History</h2>
        <div id="oms-courier-history-container">
             <p>Enter a mobile number to see courier history.</p>
        </div>
    </div>

    <div class="oms-layout-grid-row">
        <div class="oms-card" id="oms-customer-details-card">
            <h2>Customer Details</h2>
            <div class="oms-customer-fields">
                <div class="oms-form-group">
                    <label for="oms-customer-phone">Mobile Number</label>
                    <div class="oms-input-with-icon">
                        <input type="text" id="oms-customer-phone" value="<?php echo esc_attr($phone); ?>">
                         <a href="tel:<?php echo esc_attr($phone); ?>" id="oms-tel-link" class="oms-input-icon"><span class="dashicons dashicons-phone"></span></a>
                    </div>
                </div>
                <div class="oms-form-group">
                    <label for="oms-customer-name">Name</label>
                    <input type="text" id="oms-customer-name" value="<?php echo esc_attr($name); ?>">
                </div>
                <div class="oms-form-group oms-full-width">
                    <label for="oms-customer-address">Address</label>
                    <textarea id="oms-customer-address" rows="3"><?php echo esc_textarea($address); ?></textarea>
                </div>
            </div>
            
            <div id="oms-pathao-location-card" class="oms-pathao-location-section" style="display:none;">
                <?php
                $cities_table = $wpdb->prefix . 'oms_pathao_cities';
                $all_cities = $wpdb->get_results("SELECT city_id, city_name FROM $cities_table ORDER BY city_name ASC");
                ?>
                <input type="hidden" id="oms-pathao-saved-city" value="">
                <input type="hidden" id="oms-pathao-saved-zone" value="">
                <input type="hidden" id="oms-pathao-saved-area" value="">

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
        <div class="oms-sidebar-column">
             <div class="oms-card">
                <h2>Delivery Method</h2>
                <div class="oms-form-group">
                    <label for="oms-courier-select">Select Courier</label>
                    <select id="oms-courier-select">
                        <option value="">-- Select Courier --</option>
                        <?php foreach($all_couriers as $c) : ?>
                            <option value="<?php echo esc_attr($c['id']); ?>" <?php selected($c['id'], $default_courier_id); ?>><?php echo esc_html($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="oms-card">
                <h2>Customer History</h2>
                <div class="oms-customer-history-stats">
                    <p>Enter a phone number to view customer history.</p>
                </div>
            </div>
            <div class="oms-card oms-note-card-rebuilt">
                <h2>Private Note</h2>
                <div class="oms-note-add-form">
                    <textarea id="oms-add-incomplete-note-textarea" placeholder="Add a private note for staff..." rows="4"><?php echo esc_textarea($note); ?></textarea>
                    <button id="oms-add-incomplete-note-button" class="button button-primary">Add Note</button>
                    <span class="spinner"></span>
                </div>
                <div id="oms-incomplete-note-response" style="display:none; margin-top: 10px;"></div>
            </div>
        </div>
    </div>

    <div class="oms-layout-grid-row">
        <div class="oms-card" id="oms-ordered-products-card">
            <h2>Ordered Products</h2>
            <div id="oms-ordered-products">
                <?php
                if (!empty($cart_contents) && is_array($cart_contents)) {
                    foreach ($cart_contents as $cart_item) {
                        $product_id = $cart_item['product_id'] ?? 0;
                        $product = $product_id ? wc_get_product($product_id) : null;
                        if ($product) {
                            $image_url = wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') ?: wc_placeholder_img_src();
                            $product_name = $product->get_name();
                            $sku = $product->get_sku() ?: 'N/A';
                            $quantity = $cart_item['quantity'];
                            $price = $product->get_price();
                            $total = $price * $quantity;
                ?>
                    <div class="oms-ordered-product-item" data-product-id="<?php echo esc_attr($product_id); ?>" data-variation-id="0">
                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($product_name); ?>">
                        <div class="oms-ordered-item-details">
                            <span class="oms-product-name"><?php echo esc_html($product_name); ?></span>
                            <span class="oms-product-sku">SKU: <?php echo esc_html($sku); ?></span>
                        </div>
                        <div class="oms-item-controls">
                            <div class="oms-quantity-control">
                                <button class="button qty-btn minus">-</button>
                                <input type="number" class="oms-item-quantity" value="<?php echo esc_attr($quantity); ?>" min="1">
                                <button class="button qty-btn plus">+</button>
                            </div>
                            <div class="oms-price-control">
                                <span>Price:</span>
                                <input type="number" class="oms-item-price" value="<?php echo esc_attr($price); ?>" step="any">
                            </div>
                            <div class="oms-total-control">
                                <span>Total:</span>
                                <span class="oms-item-total"><?php echo esc_html($total); ?></span>
                            </div>
                            <button class="oms-remove-item-btn">&times;</button>
                        </div>
                    </div>
                <?php
                        }
                    }
                }
                ?>
            </div>
        </div>
        <div class="oms-sidebar-column">
            <div class="oms-card">
                <h2>Click To Add Products</h2>
                <div class="oms-product-search-box"><input type="text" id="oms-product-search" placeholder="Type to search..."></div>
                <div id="oms-search-results"></div>
            </div>
        </div>
    </div>

    <div class="oms-payment-footer oms-card">
        <div class="oms-payment-fields">
            <div class="oms-form-group"><label for="oms-order-discount">Discount</label><input type="number" id="oms-order-discount" value="0" step="any"></div>
            <div class="oms-form-group"><label for="oms-order-subtotal">Sub Total</label><input type="text" id="oms-order-subtotal" value="0.00" readonly></div>
            <div class="oms-form-group"><label for="oms-order-shipping">Delivery Charge</label><input type="number" id="oms-order-shipping" value="0" step="any"></div>
            <div class="oms-form-group"><label for="oms-order-grandtotal" class="oms-grand-total-label">Grand Total</label><input type="text" id="oms-order-grandtotal" value="0.00" readonly></div>
        </div>
        <div class="oms-save-actions">
            <?php if ($can_edit) : ?>
                <button class="button button-primary button-hero" id="oms-create-order-from-incomplete-btn">Create Order</button>
            <?php else : ?>
                <button class="button button-primary button-hero" id="oms-create-order-from-incomplete-btn" disabled>
                    Please Wait <span id="oms-wait-timer"><?php echo esc_html($remaining_time); ?></span>s
                </button>
            <?php endif; ?>
             <button class="button button-link-delete" id="oms-delete-incomplete-order-btn">Delete Permanently</button>
            <span class="spinner"></span>
        </div>
        <div id="oms-delete-confirm-prompt" style="display: none; margin-top: 10px; text-align: right;">
            <span>Are you sure?</span>
            <button class="button button-danger" id="oms-delete-confirm-btn">Yes, Delete</button>
            <button class="button button-secondary" id="oms-delete-cancel-btn">Cancel</button>
        </div>
        <div id="oms-save-response" class="oms-response-message"></div>
    </div>
</div>
