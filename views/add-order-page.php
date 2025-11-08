<?php
$all_couriers = OMS_Helpers::get_couriers();
$default_courier_id = get_option('oms_default_courier');
?>
<div class="wrap oms-order-details-wrap">
    <div id="oms-add-order-page-marker" data-couriers="<?php echo esc_attr(json_encode($all_couriers)); ?>"></div>

    <h1>Add New Order</h1>

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
                        <input type="text" id="oms-customer-phone" placeholder="Enter phone to find history...">
                        <a href="#" id="oms-tel-link" class="oms-input-icon" style="display: none;"><span class="dashicons dashicons-phone"></span></a>
                    </div>
                </div>
                <div class="oms-form-group">
                    <label for="oms-customer-name">Name</label>
                    <input type="text" id="oms-customer-name">
                </div>
                <div class="oms-form-group oms-full-width">
                    <label for="oms-customer-address">Address</label>
                    <textarea id="oms-customer-address" rows="3"></textarea>
                </div>
                <div class="oms-form-group oms-full-width">
                    <label for="oms-shipping-note">Shipping Note</label>
                    <textarea id="oms-shipping-note" rows="2"></textarea>
                </div>
            </div>
             
            <div id="oms-pathao-location-card" class="oms-pathao-location-section" style="display:none;">
                <?php
                    global $wpdb;
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
                <h2>Order Source</h2>
                 <div class="oms-form-group">
                    <label for="oms-order-source">Select Order Source</label>
                    <select id="oms-order-source">
                        <option value="admin">Admin Panel (Default)</option>
                        <option value="admin-whatsapp">Whatsapp</option>
                        <option value="admin-messenger">Messenger</option>
                        <option value="admin-tiktok">Tiktok</option>
                        <option value="admin-instagram">Instagram</option>
                        <option value="admin-call">Call</option>
                    </select>
                </div>
            </div>
            
            <div class="oms-card">
                <h2>Customer History</h2>
                <div class="oms-customer-history-stats">
                    <p>Enter a phone number to view customer history.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="oms-layout-grid-row">
        <div class="oms-card" id="oms-ordered-products-card">
            <h2>Ordered Products</h2>
            <div id="oms-ordered-products">
                <!-- Products will be added here via JavaScript -->
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
             <button class="button button-primary button-hero" id="oms-save-order-btn">Create Order</button>
             <span class="spinner"></span>
        </div>
        <div id="oms-save-response" class="oms-response-message"></div>
    </div>
</div>
