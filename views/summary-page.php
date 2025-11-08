<?php
// --- Date Filtering Logic ---
$selected_date_str = isset($_GET['oms_date']) ? sanitize_text_field($_GET['oms_date']) : current_time('Y-m-d');
try {
    $selected_date = new DateTime($selected_date_str);
    $previous_day = clone $selected_date;
    $previous_day->modify('-1 day');
} catch (Exception $e) {
    // Default to today if the date is invalid
    $selected_date = new DateTime(current_time('Y-m-d'));
    $previous_day = clone $selected_date;
    $previous_day->modify('-1 day');
}

// --- Fetch Data for Selected Date ---
$orders_today = wc_get_orders([
    'date_created' => $selected_date->format('Y-m-d'),
    'limit' => -1,
]);
$revenue_today = 0;
$status_counts_today = array_fill_keys(array_keys(wc_get_order_statuses()), 0);
foreach ($orders_today as $order) {
    $revenue_today += $order->get_total();
    $status_slug = 'wc-' . $order->get_status();
    if (array_key_exists($status_slug, $status_counts_today)) {
        $status_counts_today[$status_slug]++;
    }
}

// --- Fetch Data for Previous Day ---
$orders_yesterday = wc_get_orders([
    'date_created' => $previous_day->format('Y-m-d'),
    'limit' => -1,
]);
$status_counts_yesterday = array_fill_keys(array_keys(wc_get_order_statuses()), 0);
foreach ($orders_yesterday as $order) {
     $status_slug = 'wc-' . $order->get_status();
    if (array_key_exists($status_slug, $status_counts_yesterday)) {
        $status_counts_yesterday[$status_slug]++;
    }
}
$order_count_today = count($orders_today);
$order_count_yesterday = count($orders_yesterday);

// --- Comparison Logic ---
$comparison_text = '';
$comparison_class = 'neutral';
if ($order_count_yesterday > 0) {
    $difference = $order_count_today - $order_count_yesterday;
    $percentage_change = ($difference / $order_count_yesterday) * 100;
    if ($percentage_change > 0) {
        $comparison_class = 'increase';
        $comparison_text = sprintf('&#9650; %.2f%% from previous day (%d)', abs($percentage_change), $order_count_yesterday);
    } elseif ($percentage_change < 0) {
        $comparison_class = 'decrease';
        $comparison_text = sprintf('&#9660; %.2f%% from previous day (%d)', abs($percentage_change), $order_count_yesterday);
    }
} elseif ($order_count_today > 0) {
    $comparison_class = 'increase';
    $comparison_text = 'No orders on the previous day.';
}

// --- DEFINITIVE FIX for Date Filter Links ---
$base_url = admin_url('admin.php?page=order-management-summary');
$today_url = add_query_arg('oms_date', current_time('Y-m-d'), $base_url);
$yesterday_url = add_query_arg('oms_date', date('Y-m-d', strtotime('-1 day', current_time('timestamp'))), $base_url);
?>

<div class="wrap">
    <h1>Order Summary</h1>

    <div class="oms-date-filter-bar oms-card">
        <form method="GET">
            <input type="hidden" name="page" value="order-management-summary">
            <label for="oms-date-select">Select a Date:</label>
            <input type="date" id="oms-date-select" name="oms_date" value="<?php echo esc_attr($selected_date->format('Y-m-d')); ?>">
            <input type="submit" class="button" value="Filter">
        </form>
        <!-- Corrected links -->
        <a href="<?php echo esc_url($today_url); ?>" class="button">Today</a>
        <a href="<?php echo esc_url($yesterday_url); ?>" class="button">Yesterday</a>
    </div>

    <p>A quick overview of your store's performance for the selected date.</p>

    <div class="oms-summary-container">
        <div class="oms-summary-card oms-card">
            <h3>Orders</h3>
            <div class="oms-main-stat"><?php echo esc_html($order_count_today); ?></div>
            <?php if (!empty($comparison_text)) : ?>
                <div class="oms-comparison <?php echo esc_attr($comparison_class); ?>">
                    <?php echo wp_kses_post($comparison_text); ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="oms-summary-card oms-card">
            <h3>Revenue</h3>
            <div class="oms-main-stat"><?php echo wp_kses_post(wc_price($revenue_today)); ?></div>
            <div class="oms-comparison neutral">
                Total sales from <?php echo esc_html($order_count_today); ?> orders.
            </div>
        </div>
    </div>

    <div class="oms-status-breakdown">
        <h2>Status Breakdown for <?php echo esc_html($selected_date->format('F j, Y')); ?></h2>
        <div class="oms-summary-container">
            <?php foreach (wc_get_order_statuses() as $slug => $name) :
                $count_today = $status_counts_today[$slug];
                $count_yesterday = $status_counts_yesterday[$slug];
                $status_comparison_text = '';
                $status_comparison_class = 'neutral';
                 if ($count_today > $count_yesterday) {
                    $status_comparison_class = 'increase';
                    $status_comparison_text = sprintf('&#9650; %d from previous day', $count_today - $count_yesterday);
                } elseif ($count_today < $count_yesterday) {
                    $status_comparison_class = 'decrease';
                    $status_comparison_text = sprintf('&#9660; %d from previous day', $count_yesterday - $count_today);
                } else {
                     $status_comparison_text = 'Same as previous day';
                }
            ?>
                <div class="oms-status-card oms-card">
                    <h4><?php echo esc_html($name); ?></h4>
                    <div class="oms-status-count"><?php echo esc_html($count_today); ?></div>
                     <div class="oms-comparison <?php echo esc_attr($status_comparison_class); ?>">
                        <?php echo wp_kses_post($status_comparison_text); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

