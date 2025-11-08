<?php
// --- SETUP for Tabs ---
$current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'all_orders';

// Get the default courier for fallback
$default_courier_id = get_option('oms_default_courier');
$default_courier = $default_courier_id ? OMS_Helpers::get_courier_by_id($default_courier_id) : null;
$default_courier_name = $default_courier ? $default_courier['name'] : 'Default Courier';

// Define the statuses for each tab
$not_confirmed_statuses = ['processing', 'on-hold', 'no-response', 'cancelled', 'pending'];
$confirmed_statuses = ['completed', 'ready-to-ship', 'shipped'];
$shipped_statuses = ['delivered', 'returned', 'partial-return'];

$tab_statuses = [
    'all_orders'    => array_merge($not_confirmed_statuses, $confirmed_statuses, $shipped_statuses),
    'not-confirmed' => $not_confirmed_statuses,
    'confirmed'     => $confirmed_statuses,
    'shipped'       => $shipped_statuses,
];

// --- SETUP: Get query variables for filters, search, and pagination ---
$paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
$search_term = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

// --- QUERY: Build arguments based on the current tab and filters ---
$args = [
    'limit'    => 20,
    'paged'    => $paged,
    'orderby'  => 'date',
    'order'    => 'DESC',
    'paginate' => true,
    'status'   => ($status_filter === 'all') ? $tab_statuses[$current_tab] : $status_filter,
];

if (!empty($search_term)) {
    // Modify search to look in meta keys for phone, name etc. if needed
    // Example: Searching billing phone (adjust meta key if using HPOS)
     // $args['meta_query'] = array(
     //     'relation' => 'OR',
     //     array(
     //         'key'     => '_billing_phone',
     //         'value'   => $search_term,
     //         'compare' => 'LIKE',
     //     ),
     //     // Add more fields to search here (e.g., first name, last name)
     // );
     // Basic search still uses default WC search for order number, etc.
     $args['s'] = $search_term;
}

$results = wc_get_orders($args);
$orders = $results->orders;
$total_orders = $results->total;
$num_pages = $results->max_num_pages;

// --- PERFORMANCE FIX: Pre-fetch all success rates for the current page ---
$success_rates = [];
if (!empty($orders)) {
    // Pluck all unique phone numbers from the orders on the current page
    $phone_numbers = array_unique(wp_list_pluck($orders, 'billing_phone'));
    $api = new OMS_Courier_History_API();

    foreach ($phone_numbers as $phone) {
        if (empty($phone)) continue;

        $transient_key = 'oms_courier_rate_' . md5($phone);
        $cached_data = get_transient($transient_key);

        if (false !== $cached_data) {
            // Use cached data if available
            $success_rates[$phone] = $cached_data;
        } else {
            // Otherwise, fetch from API and cache it for the rest of the day
            $rate_data = $api->get_courier_success_rate($phone);
            set_transient($transient_key, $rate_data, strtotime('tomorrow') - time());
            $success_rates[$phone] = $rate_data;
        }
    }
}
// --- End Performance Fix ---


// --- Get status counts for the current tab's filters ---
$all_wc_statuses = wc_get_order_statuses();
$status_counts = [];
$statuses_to_count_for_links = ($current_tab === 'all_orders')
    ? array_map(fn($s) => str_replace('wc-', '', $s), array_keys($all_wc_statuses))
    : $tab_statuses[$current_tab];

foreach ($statuses_to_count_for_links as $status_slug) {
    $count = wc_orders_count($status_slug);
    if ($count > 0) {
        $status_counts[$status_slug] = $count;
    }
}

// Calculate the total for the "All" link by summing counts within the tab's definition
$total_count_for_tab = 0;
foreach($tab_statuses[$current_tab] as $status_slug_in_tab) {
    $total_count_for_tab += wc_orders_count($status_slug_in_tab);
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Order List</h1>
    <a href="<?php echo admin_url('admin.php?page=oms-add-order'); ?>" class="page-title-action">Add New</a>
    <hr class="wp-header-end">

    <h2 class="nav-tab-wrapper">
        <a href="?page=oms-order-list&tab=all_orders" class="nav-tab <?php echo $current_tab == 'all_orders' ? 'nav-tab-active' : ''; ?>">
            All Orders
        </a>
        <a href="?page=oms-order-list&tab=not-confirmed" class="nav-tab <?php echo $current_tab == 'not-confirmed' ? 'nav-tab-active' : ''; ?>">
            Not Confirmed Orders
        </a>
        <a href="?page=oms-order-list&tab=confirmed" class="nav-tab <?php echo $current_tab == 'confirmed' ? 'nav-tab-active' : ''; ?>">
            Confirmed Orders
        </a>
        <a href="?page=oms-order-list&tab=shipped" class="nav-tab <?php echo $current_tab == 'shipped' ? 'nav-tab-active' : ''; ?>">
            Shipped Orders
        </a>
    </h2>

    <!-- Search Form (for filters ONLY) -->
    <form method="get" class="oms-search-form">
        <input type="hidden" name="page" value="oms-order-list">
        <input type="hidden" name="tab" value="<?php echo esc_attr($current_tab); ?>">
        <?php if ($status_filter !== 'all') : ?>
            <input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>">
        <?php endif; ?>
        <?php if ($search_term) : /* Keep search term in URL for filter links */ ?>
            <input type="hidden" name="s" value="<?php echo esc_attr($search_term); ?>">
        <?php endif; ?>


        <!-- Filter Bar with Scrolling Container -->
        <div class="oms-filter-bar-scroll-container">
            <ul class="subsubsub">
                <li class="all"><a href="?page=oms-order-list&tab=<?php echo esc_attr($current_tab); ?>" class="<?php echo ($status_filter === 'all') ? 'current' : ''; ?>">All <span class="count">(<?php echo esc_html($total_count_for_tab); ?>)</span></a></li>
                <?php foreach ($status_counts as $slug => $count) :
                    $wc_slug = 'wc-' . $slug;
                    if (isset($all_wc_statuses[$wc_slug])) :
                ?>
                <li><a href="?page=oms-order-list&tab=<?php echo esc_attr($current_tab); ?>&status=<?php echo esc_attr($slug); ?>" class="<?php echo ($status_filter === $slug) ? 'current' : ''; ?>"><?php echo esc_html($all_wc_statuses[$wc_slug]); ?> <span class="count">(<?php echo esc_html($count); ?>)</span></a></li>
                <?php endif; endforeach; ?>
            </ul>
        </div>

        <!-- This form no longer contains the search box or bulk actions -->
    </form> <!-- End Search Form -->

    <div class="clear"></div>
    
    <!-- BUG FIX: Add an empty, invisible GET form for the search box to target -->
    <form method="get" id="oms-search-form-main"></form>

    <!-- Bulk Action Form -->
    <form method="post">
        <?php wp_nonce_field('oms_bulk_actions', 'oms_bulk_action_nonce'); ?>
        <input type="hidden" name="tab" value="<?php echo esc_attr($current_tab); ?>">
        <!-- Hidden inputs to preserve filters during bulk actions -->
        <?php if ($status_filter !== 'all') : ?><input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>"><?php endif; ?>
        <?php if ($search_term) : ?><input type="hidden" name="s" value="<?php echo esc_attr($search_term); ?>"><?php endif; ?>

        <!-- LAYOUT FIX: Combined top nav bar -->
        <div class="tablenav top">
        
            <!-- Bulk Actions (part of the POST form) -->
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-top" class="screen-reader-text">Select bulk action</label>
                <select name="action" id="bulk-action-selector-top">
                    <option value="-1">Bulk actions</option>
                    <?php foreach (wc_get_order_statuses() as $slug => $name) : ?>
                        <option value="<?php echo esc_attr(str_replace('wc-', '', $slug)); ?>">Change status to <?php echo esc_html(strtolower($name)); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="submit" id="doaction" name="doaction" class="button action" value="Apply">
                
                <?php if ($current_tab === 'confirmed') : ?>
                    <button type="button" id="oms-print-invoice-btn" class="button button-primary">Print Invoice</button>
                    <button type="button" id="oms-print-sticker-btn" class="button">Print Sticker</button>
                <?php endif; ?>
            </div>
            
            <!-- Search Box (HTML is here, but inputs are linked to the GET form) -->
            <div class="alignright oms-search-box">
                <!-- BUG FIX: Removed nested <form> tag -->
                 <p class="search-box">
                    <!-- BUG FIX: Add 'form' attribute to all inputs to link them to the external GET form -->
                    <input type="hidden" name="page" value="oms-order-list" form="oms-search-form-main">
                    <input type="hidden" name="tab" value="<?php echo esc_attr($current_tab); ?>" form="oms-search-form-main">
                    <?php if ($status_filter !== 'all') : ?>
                        <input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>" form="oms-search-form-main">
                    <?php endif; ?>
                 
                    <label class="screen-reader-text" for="post-search-input">Search Orders:</label>
                    <input type="search" id="post-search-input" name="s" value="<?php echo esc_attr($search_term); ?>" placeholder="Search orders..." form="oms-search-form-main">
                    <input type="submit" id="search-submit" class="button" value="Search Orders" form="oms-search-form-main">
                </p>
                <!-- BUG FIX: Removed closing </form> tag -->
            </div>
            
            <div class="clear"></div>
        </div>
        <!-- END LAYOUT FIX -->

        <table class="wp-list-table widefat fixed striped table-view-list posts">
            <thead>
                <tr>
                    <td id="cb" class="manage-column column-cb check-column"><input id="cb-select-all-1" type="checkbox"></td>
                    <th scope="col" class="manage-column column-created-at" style="width: 15%;">Created At</th>
                    <th scope="col" class="manage-column column-customer" style="width: 18%;">Customer</th>
                    <th scope="col" class="manage-column column-order-items" style="width: 25%;">Order Items</th>
                    <th scope="col" class="manage-column column-success-rate" style="width: 10%;">Success Rate</th>
                    <th scope="col" class="manage-column column-note" style="width: 12%;">Note</th>
                    <?php if ($current_tab === 'confirmed' || $current_tab === 'shipped') : ?>
                        <th scope="col" class="manage-column column-courier-status" style="width: 10%;">Courier Status</th>
                    <?php endif; ?>
                    <th scope="col" class="manage-column column-action" style="width: 10%;">Action</th>
                </tr>
            </thead>
            <tbody id="the-list">
                <?php if (!empty($orders)) : ?>
                    <?php foreach ($orders as $order) : ?>
                        <tr>
                            <th scope="row" class="check-column"><input type="checkbox" name="order_ids[]" value="<?php echo esc_attr($order->get_id()); ?>"></th>
                            <td class="column-created-at">
                                <div class="oms-date-id">
                                     <a href="<?php echo esc_url(admin_url('admin.php?page=oms-order-details&order_id=' . $order->get_id())); ?>"><strong>#<?php echo esc_html($order->get_order_number()); ?></strong></a>
                                    <span><?php echo esc_html($order->get_date_created()->date_i18n('M j, Y, g:i A')); ?></span>
                                    <span class="oms-status-badge status-<?php echo esc_attr($order->get_status()); ?>"><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></span>
                                </div>
                            </td>
                            <td class="column-customer">
                                <div class="oms-customer-details">
                                     <div class="oms-customer-phone-wrapper">
                                        <?php
                                        $phone_number = $order->get_billing_phone();
                                        $clean_phone = preg_replace('/[^0-9]/', '', $phone_number); // Clean the number
                                        $whatsapp_number = $clean_phone;
                                        if (strlen($whatsapp_number) == 10) { // Assuming BD number without leading 0, add +880
                                            $whatsapp_number = '+880' . $whatsapp_number;
                                        } elseif (strlen($whatsapp_number) == 11 && strpos($whatsapp_number, '0') === 0) { // Assuming BD number with leading 0, replace with +880
                                            $whatsapp_number = '+880' . substr($whatsapp_number, 1);
                                        }
                                        // Add other country code logic if needed
                                        ?>
                                        <span><?php echo esc_html($phone_number); ?></span>
                                        <a href="tel:<?php echo esc_attr($phone_number); ?>" class="oms-phone-icon" title="Call Customer">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="feather feather-phone"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                        </a>
                                        <a href="https://wa.me/<?php echo esc_attr($whatsapp_number); ?>" class="oms-whatsapp-icon" target="_blank" title="WhatsApp Customer">
                                            <span class="dashicons dashicons-whatsapp"></span>
                                        </a>
                                    </div>
                                    <span class="customer-name"><?php echo esc_html($order->get_formatted_billing_full_name()); ?></span>
                                    <span><?php echo wp_kses_post($order->get_billing_address_1()); ?></span>
                                </div>
                            </td>
                           <td class="column-order-items">
                                <div class="oms-item-list">
                                    <?php
                                    $items = $order->get_items();
                                    foreach ($items as $item_id => $item) :
                                        $product = $item->get_product();
                                        if (!$product) continue; // Skip if product data is missing

                                        $image_url = wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') ?: wc_placeholder_img_src();
                                        $product_name = $item->get_name();
                                        $sku = $product->get_sku() ?: 'N/A';
                                        $quantity = $item->get_quantity();
                                        // Get line total (price * quantity for this item)
                                        $line_total = $order->get_line_total($item); // Use order function to get formatted price
                                    ?>
                                    <div class="oms-item-details-row">
                                        <img src="<?php echo esc_url($image_url); ?>" class="oms-item-image" alt="<?php echo esc_attr($product_name); ?>">
                                        <div class="oms-item-info-col">
                                            <span class="oms-item-title" title="<?php echo esc_attr($product_name); ?>">
                                                <?php echo esc_html(wp_trim_words($product_name, 4, '...')); // Limit title words ?>
                                            </span>
                                            <span class="oms-item-sku" title="<?php echo esc_attr($sku); ?>">
                                                SKU: <?php echo esc_html(substr($sku, 0, 20)); // Limit SKU characters ?>
                                            </span>
                                        </div>
                                        <div class="oms-item-qty-col">
                                             <span class="oms-item-price"><?php echo wc_price($line_total, ['currency' => $order->get_currency()]); ?></span>
                                            <span class="oms-item-qty">Qty: <?php echo esc_html($quantity); ?></span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td class="column-success-rate">
                                <?php
                                $phone = $order->get_billing_phone();
                                $rate_data = isset($success_rates[$phone]) ? $success_rates[$phone] : null;
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
                                    <?php echo $output; ?>
                                </div>
                            </td>
                            <td class="column-note">
                                <?php
                                $notes = wc_get_order_notes(['order_id' => $order->get_id(), 'type' => 'internal', 'orderby' => 'date_created', 'order' => 'DESC']);
                                $latest_note_content = '';
                                $system_message_identifiers = [ 'Status updated from custom order page.', 'Order status changed from', 'was upgraded to', 'API returned', 'was generated for this customer', 'sent to' ];
                                if ($notes) {
                                    foreach($notes as $note) {
                                        $is_system_note = false;
                                        foreach ($system_message_identifiers as $identifier) { if (strpos($note->content, $identifier) !== false) { $is_system_note = true; break; } }
                                        if (!$is_system_note) { $latest_note_content = $note->content; break; }
                                    }
                                }
                                echo esc_html($latest_note_content);
                                ?>
                            </td>
                            <?php if ($current_tab === 'confirmed' || $current_tab === 'shipped') : ?>
                                <td class="oms-courier-status-cell column-courier-status" data-order-id="<?php echo esc_attr($order->get_id()); ?>">
                                    <?php
                                    $steadfast_id = $order->get_meta('_steadfast_consignment_id');
                                    $pathao_id = $order->get_meta('_pathao_consignment_id');

                                    if ($steadfast_id) {
                                        $tracking_code = $order->get_meta('_steadfast_tracking_code');
                                        $tracking_url = "https://steadfast.com.bd/t/{$tracking_code}";
                                        echo '<a href="' . esc_url($tracking_url) . '" target="_blank" class="button button-secondary">Track Steadfast</a>';
                                        echo '<span class="oms-parcel-id">Parcel ID: ' . esc_html($steadfast_id) . '</span>';
                                    } elseif ($pathao_id) {
                                        $tracking_url = "https://merchant.pathao.com/courier/orders/{$pathao_id}";
                                        echo '<a href="' . esc_url($tracking_url) . '" target="_blank" class="button button-secondary">Track Pathao</a>';
                                        echo '<span class="oms-parcel-id">Parcel ID: ' . esc_html($pathao_id) . '</span>';
                                    } else {
                                        $button_courier = $default_courier;
                                        $order_courier_id = $order->get_meta('_oms_selected_courier_id', true);
                                        if ($order_courier_id) {
                                            $order_courier = OMS_Helpers::get_courier_by_id($order_courier_id);
                                            if ($order_courier) {
                                                $button_courier = $order_courier;
                                            }
                                        }
                                        $button_name = $button_courier ? $button_courier['name'] : 'Courier';
                                        $button_courier_id = $button_courier ? $button_courier['id'] : '';

                                        echo '<button class="button button-primary oms-send-to-courier-list-btn" data-order-id="' . esc_attr($order->get_id()) . '" data-courier-id="' . esc_attr($button_courier_id) .'">Send to ' . esc_html($button_name) . '</button>';
                                        echo '<span class="oms-parcel-id">Not Uploaded</span>';
                                    }
                                    ?>
                                </td>
                            <?php endif; ?>
                            <td class="column-action">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=oms-order-details&order_id=' . $order->get_id())); ?>" class="button oms-action-open-btn">Open</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else :
                    $colspan = ($current_tab === 'confirmed' || $current_tab === 'shipped') ? 8 : 7;
                ?>
                    <tr class="no-items"><td class="colspanchange" colspan="<?php echo $colspan; ?>">No orders found for this tab.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="tablenav bottom">
            <!-- Bulk Action Dropdown Moved to Separate Form -->
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-bottom" class="screen-reader-text">Select bulk action</label>
                <select name="action2" id="bulk-action-selector-bottom">
                    <option value="-1">Bulk actions</option>
                    <?php foreach (wc_get_order_statuses() as $slug => $name) : ?>
                        <option value="<?php echo esc_attr(str_replace('wc-', '', $slug)); ?>">Change status to <?php echo esc_html(strtolower($name)); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="submit" id="doaction2" name="doaction2" class="button action" value="Apply"> <!-- Added name="doaction2" -->
            </div>

            <?php if ($num_pages > 1) : ?>
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo esc_html($total_orders); ?> items</span>
                <span class="pagination-links">
                     <?php
                     // Rebuild base URL for pagination, including current filters
                     $paginate_base = add_query_arg(['page' => 'oms-order-list', 'tab' => $current_tab], admin_url('admin.php'));
                     if ($status_filter !== 'all') $paginate_base = add_query_arg('status', $status_filter, $paginate_base);
                     if ($search_term) $paginate_base = add_query_arg('s', $search_term, $paginate_base);
                     $paginate_format = '&paged=%#%'; // Format for query args

                    echo paginate_links([
                        'base'      => $paginate_base . $paginate_format,
                        'format'    => '', // Already part of the base
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total'     => $num_pages,
                        'current'   => $paged,
                        'mid_size'  => 2,
                    ]);
                    ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
    </form> <!-- End Bulk Action Form -->
</div>
<script>
// Keep existing JS for Print Invoice/Sticker functionality
jQuery(document).ready(function($) {
    $('#oms-print-invoice-btn').on('click', function(e) {
        e.preventDefault();
        var order_ids = [];
        $('tbody#the-list input[type="checkbox"][name="order_ids[]"]:checked').each(function() {
            order_ids.push($(this).val());
        });

        if (order_ids.length === 0) {
            alert('Please select at least one order to print an invoice.');
            return;
        }

        var $button = $(this);
        $button.prop('disabled', true).text('Printing...');

        $.ajax({
            url: "<?php echo admin_url('admin-ajax.php'); ?>",
            type: 'POST',
            data: {
                action: 'oms_ajax_get_invoice_html',
                nonce: "<?php echo wp_create_nonce('oms_invoice_nonce'); ?>",
                order_ids: order_ids
            },
            success: function(response) {
                if (response.success) {
                    var printWindow = window.open('', '_blank');
                    printWindow.document.write(response.data.html);
                    printWindow.document.close();
                    printWindow.focus();
                    setTimeout(function() {
                        printWindow.print();
                        printWindow.close();
                    }, 500); // Wait for content to render
                } else {
                    alert('Error: ' + (response.data.message || 'Could not generate invoice.'));
                }
            },
            error: function() {
                alert('An AJAX error occurred. Please try again.');
            },
            complete: function() {
                 $button.prop('disabled', false).text('Print Invoice');
            }
        });
    });

    $('#oms-print-sticker-btn').on('click', function(e) {
        e.preventDefault();
        var order_ids = [];
        $('tbody#the-list input[type="checkbox"][name="order_ids[]"]:checked').each(function() {
            order_ids.push($(this).val());
        });

        if (order_ids.length === 0) {
            alert('Please select at least one order to print a sticker.');
            return;
        }

        var $button = $(this);
        $button.prop('disabled', true).text('Printing...');

        $.ajax({
            url: "<?php echo admin_url('admin-ajax.php'); ?>",
            type: 'POST',
            data: {
                action: 'oms_ajax_get_sticker_html',
                nonce: "<?php echo wp_create_nonce('oms_sticker_nonce'); ?>",
                order_ids: order_ids
            },
            success: function(response) {
                if (response.success) {
                    var printWindow = window.open('', '_blank');
                    printWindow.document.write(response.data.html);
                    printWindow.document.close();
                    printWindow.focus();
                    setTimeout(function() {
                        printWindow.print();
                        printWindow.close();
                    }, 500); // Wait for content to render
                } else {
                    alert('Error: ' + (response.data.message || 'Could not generate sticker.'));
                }
            },
            error: function() {
                alert('An AJAX error occurred. Please try again.');
            },
            complete: function() {
                 $button.prop('disabled', false).text('Print Sticker');
            }
        });
    });
});
</script>