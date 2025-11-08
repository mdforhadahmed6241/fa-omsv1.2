<?php
global $wpdb;
$table_name = $wpdb->prefix . 'oms_incomplete_orders';

// Pagination
$paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
$per_page = 20;
$offset = ($paged - 1) * $per_page;

// Search
$search_term = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

// Query
$where_clause = '';
if (!empty($search_term)) {
    $where_clause = $wpdb->prepare(" WHERE phone LIKE %s OR customer_data LIKE %s", '%' . $wpdb->esc_like($search_term) . '%', '%' . $wpdb->esc_like($search_term) . '%');
}

$total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name" . $where_clause);
$incomplete_orders = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $table_name" . $where_clause . " ORDER BY updated_at DESC LIMIT %d OFFSET %d",
    $per_page,
    $offset
));

$num_pages = ceil($total_items / $per_page);

// --- PERFORMANCE FIX: Pre-fetch all success rates for the current page ---
$success_rates = [];
if (!empty($incomplete_orders)) {
    // Pluck all unique phone numbers from the orders on the current page
    $phone_numbers = array_unique(wp_list_pluck($incomplete_orders, 'phone'));
    $api = new OMS_Courier_History_API();

    foreach ($phone_numbers as $phone) {
        if (empty($phone)) continue;
        
        $transient_key = 'oms_courier_rate_' . md5($phone);
        $cached_data = get_transient($transient_key);

        if (false !== $cached_data) {
            $success_rates[$phone] = $cached_data;
        } else {
            $rate_data = $api->get_courier_success_rate($phone);
            set_transient($transient_key, $rate_data, strtotime('tomorrow') - time());
            $success_rates[$phone] = $rate_data;
        }
    }
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Incomplete Orders List</h1>
    <hr class="wp-header-end">

    <!-- Replicated Search Bar from order-list-page.php -->
    <div class="tablenav top">
        <div class="alignright oms-search-box">
            <form method="get">
                <input type="hidden" name="page" value="oms-incomplete-list">
                 <p class="search-box">
                    <label class="screen-reader-text" for="post-search-input">Search Incomplete Orders:</label>
                    <input type="search" id="post-search-input" name="s" value="<?php echo esc_attr($search_term); ?>" placeholder="Search by phone or name...">
                    <input type="submit" id="search-submit" class="button" value="Search Orders">
                </p>
            </form>
        </div>
        <div class="clear"></div>
    </div>
    
    <div class="clear"></div>

    <form method="post">
        <table class="wp-list-table widefat fixed striped table-view-list posts">
            <thead>
                <tr>
                    <!-- Added checkbox column for visual consistency -->
                    <td id="cb" class="manage-column column-cb check-column"><input id="cb-select-all-1" type="checkbox"></td>
                    <th scope="col" class="manage-column column-created-at" style="width: 15%;">Last Updated</th>
                    <th scope="col" class="manage-column column-customer" style="width: 18%;">Customer</th>
                    <th scope="col" class="manage-column column-order-items" style="width: 25%;">Cart Items</th>
                    <th scope="col" class="manage-column column-success-rate" style="width: 10%;">Success Rate</th>
                    <th scope="col" class="manage-column column-note" style="width: 12%;">Note</th>
                    <th scope="col" class="manage-column column-action" style="width: 10%;">Action</th>
                </tr>
            </thead>
            <tbody id="the-list">
                <?php if (!empty($incomplete_orders)) : ?>
                    <?php foreach ($incomplete_orders as $inc_order) :
                        $customer_data = unserialize($inc_order->customer_data);
                        $cart_contents = unserialize($inc_order->cart_contents);
                        $name = $customer_data['billing_first_name'] ?? '';
                        if (!empty($customer_data['billing_last_name'])) {
                            $name .= ' ' . $customer_data['billing_last_name'];
                        }
                        $address = $customer_data['billing_address_1'] ?? '';
                        $note = $customer_data['order_comments'] ?? '';
                    ?>
                        <tr>
                            <!-- Added checkbox column for visual consistency -->
                            <th scope="row" class="check-column"><input type="checkbox" name="incomplete_order_ids[]" value="<?php echo esc_attr($inc_order->id); ?>"></th>
                            <td class="column-created-at">
                                <!-- Added oms-date-id wrapper -->
                                <div class="oms-date-id">
                                     <a href="<?php echo esc_url(admin_url('admin.php?page=oms-incomplete-order-details&id=' . $inc_order->id)); ?>">
                                        <strong>#<?php echo esc_html($inc_order->id); ?></strong>
                                     </a>
                                    <span><?php echo esc_html(date('M j, Y, g:i A', strtotime($inc_order->updated_at))); ?></span>
                                </div>
                            </td>
                            <td class="column-customer">
                                <!-- Added oms-customer-details wrapper and icons -->
                                <div class="oms-customer-details">
                                     <div class="oms-customer-phone-wrapper">
                                        <?php
                                        $phone_number = $inc_order->phone;
                                        $clean_phone = preg_replace('/[^0-9]/', '', $phone_number); // Clean the number
                                        $whatsapp_number = $clean_phone;
                                        if (strlen($whatsapp_number) == 10) {
                                            $whatsapp_number = '+880' . $whatsapp_number;
                                        } elseif (strlen($whatsapp_number) == 11 && strpos($whatsapp_number, '0') === 0) {
                                            $whatsapp_number = '+880' . substr($whatsapp_number, 1);
                                        }
                                        ?>
                                        <span><?php echo esc_html($phone_number); ?></span>
                                        <a href="tel:<?php echo esc_attr($phone_number); ?>" class="oms-phone-icon" title="Call Customer">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="feather feather-phone"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                        </a>
                                        <a href="https://wa.me/<?php echo esc_attr($whatsapp_number); ?>" class="oms-whatsapp-icon" target="_blank" title="WhatsApp Customer">
                                            <span class="dashicons dashicons-whatsapp"></span>
                                        </a>
                                    </div>
                                    <span class="customer-name"><?php echo esc_html($name); ?></span>
                                    <span><?php echo esc_html($address); ?></span>
                                </div>
                            </td>
                            <td class="column-order-items">
                                <?php
                                if (!empty($cart_contents) && is_array($cart_contents)) {
                                    echo '<div class="oms-item-list">';
                                    foreach ($cart_contents as $cart_item) {
                                        $product_id = $cart_item['product_id'] ?? 0;
                                        $product = $product_id ? wc_get_product($product_id) : null;
                                        
                                        if ($product) {
                                            $image_url = wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') ?: wc_placeholder_img_src();
                                            $product_name = $product->get_name();
                                            $sku = $product->get_sku() ?: 'N/A';
                                            $quantity = $cart_item['quantity'];
                                            $price = $product->get_price();
                                            $line_total = $price * $quantity;
                                    ?>
                                            <!-- Replicated oms-item-details-row structure -->
                                            <div class="oms-item-details-row">
                                                <img src="<?php echo esc_url($image_url); ?>" class="oms-item-image" alt="<?php echo esc_attr($product_name); ?>">
                                                <div class="oms-item-info-col">
                                                    <span class="oms-item-title" title="<?php echo esc_attr($product_name); ?>">
                                                        <?php echo esc_html(wp_trim_words($product_name, 4, '...')); ?>
                                                    </span>
                                                    <span class="oms-item-sku" title="<?php echo esc_attr($sku); ?>">
                                                        SKU: <?php echo esc_html(substr($sku, 0, 20)); ?>
                                                    </span>
                                                </div>
                                                <div class="oms-item-qty-col">
                                                    <span class="oms-item-price"><?php echo wc_price($line_total); ?></span>
                                                    <span class="oms-item-qty">Qty: <?php echo esc_html($quantity); ?></span>
                                                </div>
                                            </div>
                                    <?php
                                        } else {
                                            echo '<div class="oms-item-details-row"><em>Product data unavailable (possibly deleted).</em></div>';
                                        }
                                    }
                                    echo '</div>'; // end .oms-item-list
                                } else {
                                    echo 'Cart is empty.';
                                }
                                ?>
                            </td>
                            <td class="column-success-rate">
                                <?php
                                $phone = $inc_order->phone;
                                $rate_data = $success_rates[$phone] ?? null;
                                $output = 'N/A';

                                if ($rate_data && $rate_data['totalOrders'] > 0) {
                                    $colorClass = 'oms-rate-red';
                                    if ($rate_data['successRate'] >= 70) $colorClass = 'oms-rate-green';
                                    elseif ($rate_data['successRate'] >= 45) $colorClass = 'oms-rate-orange';
                                    $output = sprintf(
                                        '<span class="oms-circle %s"></span><span>Success: %d%%<br>Order: %d/%d</span>',
                                        esc_attr($colorClass),
                                        esc_html($rate_data['successRate']),
                                        esc_html($rate_data['successOrders']),
                                        esc_html($rate_data['totalOrders'])
                                    );
                                }
                                ?>
                                <div class="oms-success-rate-badge">
                                    <?php echo $output; // This variable contains HTML, so it should not be escaped here. ?>
                                </div>
                            </td>
                            <td class="column-note">
                                <?php echo esc_html($note); ?>
                            </td>
                            <td class="column-action">
                                <!-- Styled button to match order list -->
                                <a href="<?php echo esc_url(admin_url('admin.php?page=oms-incomplete-order-details&id=' . $inc_order->id)); ?>" class="button oms-action-open-btn">Open</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <!-- Updated colspan -->
                    <tr class="no-items"><td class="colspanchange" colspan="7">No incomplete orders found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Replicated Pagination from order-list-page.php -->
        <?php if ($num_pages > 1) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo esc_html($total_items); ?> items</span>
                <span class="pagination-links">
                    <?php
                    $paginate_base = add_query_arg(['page' => 'oms-incomplete-list'], admin_url('admin.php'));
                    if ($search_term) $paginate_base = add_query_arg('s', $search_term, $paginate_base);
                    
                    echo paginate_links([
                        'base'      => $paginate_base . '&paged=%#%',
                        'format'    => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total'     => $num_pages,
                        'current'   => $paged,
                        'mid_size'  => 2,
                    ]);
                    ?>
                </span>
            </div>
        </div>
        <?php endif; ?>
    </form>
</div>