<?php
global $wpdb;
$table_name = $wpdb->prefix . 'oms_return_orders';

// --- Global Date Filtering Logic ---
$current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'summary';
$filter_type = isset($_GET['filter']) ? sanitize_key($_GET['filter']) : 'today';
$start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';

$today = current_time('Y-m-d');
$today_start = $today . ' 00:00:00';
$today_end = $today . ' 23:59:59';
$where_clause = '';

if ($current_tab === 'summary') {
    switch ($filter_type) {
        case 'yesterday':
            $yesterday = date('Y-m-d', strtotime('-1 day', current_time('timestamp')));
            $where_clause = $wpdb->prepare("WHERE create_date >= %s AND create_date <= %s", $yesterday . ' 00:00:00', $yesterday . ' 23:59:59');
            break;
        case '7d':
            $seven_days_ago = date('Y-m-d', strtotime('-7 days', current_time('timestamp')));
            $where_clause = $wpdb->prepare("WHERE create_date >= %s AND create_date <= %s", $seven_days_ago . ' 00:00:00', $today_end);
            break;
        case '30d':
            $thirty_days_ago = date('Y-m-d', strtotime('-30 days', current_time('timestamp')));
            $where_clause = $wpdb->prepare("WHERE create_date >= %s AND create_date <= %s", $thirty_days_ago . ' 00:00:00', $today_end);
            break;
        case 'custom':
            if (!empty($start_date) && !empty($end_date)) {
                $where_clause = $wpdb->prepare("WHERE create_date >= %s AND create_date <= %s", $start_date . ' 00:00:00', $end_date . ' 23:59:59');
            } else {
                 $where_clause = $wpdb->prepare("WHERE create_date >= %s AND create_date <= %s", $today_start, $today_end);
            }
            break;
        case 'today':
        default:
            $where_clause = $wpdb->prepare("WHERE create_date >= %s AND create_date <= %s", $today_start, $today_end);
            break;
    }

    // --- Summary Data Fetching ---
    $total_returns = $wpdb->get_var("SELECT COUNT(id) FROM $table_name $where_clause");
    $total_received = $wpdb->get_var("SELECT COUNT(id) FROM $table_name $where_clause AND receive_status = 1");
    $total_not_received = $wpdb->get_var("SELECT COUNT(id) FROM $table_name $where_clause AND receive_status = 0");
}

// --- List Table Setup ---
$list_tab = isset($_GET['list_tab']) ? sanitize_key($_GET['list_tab']) : 'not-received';
$paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
$per_page = 20;
$offset = ($paged - 1) * $per_page;
$search_term = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$list_where = '';

if ($current_tab === 'return-list') {
    $receive_status_filter = ($list_tab === 'received') ? 1 : 0;
    $list_where = $wpdb->prepare("WHERE receive_status = %d", $receive_status_filter);

    if (!empty($search_term)) {
        // Search by order_id, which is stored as number, or by a customer name/phone (need to JOIN)
        // This query requires a complex JOIN or two queries for proper filtering. Since we cannot modify table structures here, we'll try to filter by Order ID or phone/name from metadata for better performance in the single query.
        $search_where_clause = $wpdb->prepare(" AND (order_id = %d OR order_id IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_billing_phone', '_billing_first_name', '_billing_last_name') AND meta_value LIKE %s) )", $search_term, '%' . $wpdb->esc_like($search_term) . '%');
        $list_where .= $search_where_clause;
    }
    
    // Fetch count for pagination
    $total_list_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name $list_where");

    // Fetch list items
    $return_orders = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name $list_where ORDER BY create_date DESC LIMIT %d OFFSET %d",
        $per_page,
        $offset
    ));
    $num_pages = ceil($total_list_items / $per_page);
}
// --- End List Table Setup ---
?>

<div class="wrap">
    <h1>Return Product Management</h1>
    
    <h2 class="nav-tab-wrapper">
        <a href="?page=oms-return-product&tab=summary" class="nav-tab <?php echo $current_tab == 'summary' ? 'nav-tab-active' : ''; ?>">Summary & Scanner</a>
        <a href="?page=oms-return-product&tab=return-list" class="nav-tab <?php echo $current_tab == 'return-list' ? 'nav-tab-active' : ''; ?>">Return List</a>
    </h2>

    <!-- Summary & Scanner Tab -->
    <div id="oms-summary-tab" class="oms-tab-content <?php echo $current_tab == 'summary' ? 'active' : ''; ?>" style="<?php echo $current_tab != 'summary' ? 'display: none;' : ''; ?>">
        
        <div class="oms-summary-section-stack"> <!-- MODIFIED: Changed from oms-return-summary-grid to a simple stacking div -->

            <div class="oms-card oms-return-stats-container"> <!-- Summary Card Column 1 -->
                <h2>Return Summary (<?php echo esc_html(ucfirst($filter_type)); ?>)</h2>
                
                <form method="GET" class="oms-date-filter-bar">
                    <input type="hidden" name="page" value="oms-return-product">
                    <input type="hidden" name="tab" value="summary">
                    
                    <label for="filter-select">Filter:</label>
                    <select id="filter-select" name="filter">
                        <option value="today" <?php selected($filter_type, 'today'); ?>>Today</option>
                        <option value="yesterday" <?php selected($filter_type, 'yesterday'); ?>>Yesterday</option>
                        <option value="7d" <?php selected($filter_type, '7d'); ?>>Last 7 Days</option>
                        <option value="30d" <?php selected($filter_type, '30d'); ?>>Last 30 Days</option>
                        <option value="custom" <?php selected($filter_type, 'custom'); ?>>Custom Range</option>
                    </select>
                    
                    <label for="start_date" style="<?php echo $filter_type !== 'custom' ? 'display:none;' : ''; ?>">From:</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo esc_attr($start_date); ?>" style="<?php echo $filter_type !== 'custom' ? 'display:none;' : ''; ?>">
                    <label for="end_date" style="<?php echo $filter_type !== 'custom' ? 'display:none;' : ''; ?>">To:</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo esc_attr($end_date); ?>" style="<?php echo $filter_type !== 'custom' ? 'display:none;' : ''; ?>">
                    
                    <input type="submit" class="button" value="Apply Filter">
                </form>

                <div class="oms-return-stats-grid">
                    <div class="oms-summary-card oms-card">
                        <h3>Total Returns</h3>
                        <div class="oms-main-stat"><?php echo esc_html($total_returns); ?></div>
                    </div>
                    <div class="oms-summary-card oms-card">
                        <h3>Total Received</h3>
                        <div class="oms-main-stat oms-stat-green"><?php echo esc_html($total_received); ?></div>
                    </div>
                    <div class="oms-summary-card oms-card">
                        <h3>Total Not Received</h3>
                        <div class="oms-main-stat oms-stat-red"><?php echo esc_html($total_not_received); ?></div>
                    </div>
                </div>
            </div>

            <div class="oms-card oms-return-scanner-card"> <!-- Scanner Card Column 2 -->
                <h2>Receive Product Scanner</h2>
                <p>Scan the order barcode to mark the product as **Received** into your inventory.</p>
                <input type="text" id="return-scan-input" class="oms-barcode-input" placeholder="Click here and start scanning..." autocomplete="off">
                <div id="return-scan-feedback" class="oms-scan-feedback"></div>

                <div class="oms-scan-log-container">
                    <h3>Last 5 Scan Log (Returns)</h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 25%;">Time</th>
                                <th style="width: 30%;">Order #</th>
                                <th style="width: 45%;">Result</th>
                            </tr>
                        </thead>
                        <tbody id="return-scan-log">
                            <!-- Log entries will be added here by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

        </div> <!-- END MODIFIED CONTAINER -->
    </div>

    <!-- Return List Tab -->
    <div id="oms-return-list-tab" class="oms-tab-content <?php echo $current_tab == 'return-list' ? 'active' : ''; ?>" style="<?php echo $current_tab != 'return-list' ? 'display: none;' : ''; ?>">
        <h2 class="nav-tab-wrapper">
            <a href="?page=oms-return-product&tab=return-list&list_tab=not-received" class="nav-tab <?php echo $list_tab == 'not-received' ? 'nav-tab-active' : ''; ?>">
                Not Received (<?php echo esc_html($wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE receive_status = 0")); ?>)
            </a>
            <a href="?page=oms-return-product&tab=return-list&list_tab=received" class="nav-tab <?php echo $list_tab == 'received' ? 'nav-tab-active' : ''; ?>">
                Received (<?php echo esc_html($wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE receive_status = 1")); ?>)
            </a>
        </h2>

        <form method="get">
            <input type="hidden" name="page" value="oms-return-product">
            <input type="hidden" name="tab" value="return-list">
            <input type="hidden" name="list_tab" value="<?php echo esc_attr($list_tab); ?>">

            <p class="search-box">
                <label class="screen-reader-text" for="post-search-input">Search Orders:</label>
                <input type="search" id="post-search-input" name="s" value="<?php echo esc_attr($search_term); ?>">
                <input type="submit" id="search-submit" class="button" value="Search Orders">
            </p>
        </form>

        <div class="clear"></div>

        <table class="wp-list-table widefat fixed striped table-view-list posts oms-return-list-table">
            <thead>
                <tr>
                    <th scope="col" class="manage-column" style="width: 10%;">Order ID</th>
                    <th scope="col" class="manage-column" style="width: 15%;">Order Date</th>
                    <th scope="col" class="manage-column" style="width: 15%;">Return Date</th>
                    <th scope="col" class="manage-column" style="width: 25%;">Customer & Phone</th>
                    <th scope="col" class="manage-column" style="width: 15%;">Status</th>
                    <th scope="col" class="manage-column" style="width: 20%;">Action</th>
                </tr>
            </thead>
            <tbody id="the-list">
                <?php if (!empty($return_orders)) : ?>
                    <?php foreach ($return_orders as $return_item) : 
                        $order = wc_get_order($return_item->order_id);
                        if (!$order) continue; 
                    ?>
                        <tr id="return-row-<?php echo esc_attr($order->get_id()); ?>">
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=oms-order-details&order_id=' . $order->get_id())); ?>">
                                    <strong>#<?php echo esc_html($order->get_order_number()); ?></strong>
                                </a>
                            </td>
                            <td><?php echo esc_html($order->get_date_created()->date_i18n('M j, Y, g:i A')); ?></td>
                            <td><?php echo esc_html(date('M j, Y, g:i A', strtotime($return_item->create_date))); ?></td>
                            <td>
                                <strong><?php echo esc_html($order->get_formatted_billing_full_name()); ?></strong><br>
                                <?php echo esc_html($order->get_billing_phone()); ?>
                            </td>
                            <td>
                                <span class="oms-status-badge status-<?php echo esc_attr($return_item->order_status_slug); ?>">
                                    <?php echo esc_html(wc_get_order_status_name('wc-' . $return_item->order_status_slug)); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($return_item->receive_status == 0) : ?>
                                    <button class="button button-primary oms-update-return-btn" data-order-id="<?php echo esc_attr($order->get_id()); ?>" data-status="1">
                                        Mark as Received
                                    </button>
                                <?php else : ?>
                                    <button class="button button-secondary oms-update-return-btn" data-order-id="<?php echo esc_attr($order->get_id()); ?>" data-status="0">
                                        Mark as Not Received
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr class="no-items"><td class="colspanchange" colspan="6">No return orders found for this list.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_list_items > $per_page) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo esc_html($total_list_items); ?> items</span>
                    <span class="pagination-links">
                        <?php
                        $paginate_base = add_query_arg(['page' => 'oms-return-product', 'tab' => 'return-list', 'list_tab' => $list_tab], admin_url('admin.php'));
                        if ($search_term) $paginate_base = add_query_arg('s', $search_term, $paginate_base);
                        
                        echo paginate_links([
                            'base'      => $paginate_base . '&paged=%#%',
                            'format'    => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total'     => $num_pages,
                            'current'   => $paged,
                        ]);
                        ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<audio id="oms-audio-success" src="https://actions.google.com/sounds/v1/alarms/beep_short.ogg" preload="auto"></audio>
<audio id="oms-audio-error" src="https://actions.google.com/sounds/v1/alarms/alarm_clock.ogg" preload="auto"></audio>
