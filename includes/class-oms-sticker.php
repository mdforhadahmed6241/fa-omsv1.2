<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the generation of printable stickers.
 */
class OMS_Sticker {

    /**
     * Generates the complete HTML for one or more stickers, with one sticker per order.
     *
     * @param array $order_ids An array of WooCommerce order IDs.
     * @return string The generated HTML content for the stickers.
     */
    public function generate_stickers_html($order_ids) {
        $company_name = get_option('oms_invoice_company_name', get_bloginfo('name'));

        // 1. Fetch all unique order objects
        $unique_order_ids = array_unique(array_map('absint', $order_ids));
        $orders_to_print = [];
        foreach ($unique_order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $orders_to_print[$order_id] = $order;
            }
        }

        // 2. Sort orders by the SKU of their first item for picking efficiency
        uasort($orders_to_print, function($a, $b) {
            $sku_a = 'ZZZ'; // Default for orders with no items, sorts them last
            $sku_b = 'ZZZ';

            $items_a = $a->get_items();
            if (!empty($items_a)) {
                $first_item_a = reset($items_a);
                $product_a = $first_item_a->get_product();
                if ($product_a && $product_a->get_sku()) {
                    $sku_a = $product_a->get_sku();
                }
            }

            $items_b = $b->get_items();
            if (!empty($items_b)) {
                $first_item_b = reset($items_b);
                $product_b = $first_item_b->get_product();
                if ($product_b && $product_b->get_sku()) {
                    $sku_b = $product_b->get_sku();
                }
            }
            return strcasecmp($sku_a, $sku_b);
        });

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Stickers</title>
            <style>
                @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap');
                body {
                    margin: 0;
                    padding: 0;
                    font-family: 'Roboto', sans-serif;
                    background-color: #EAEAEA;
                }
                .sticker-page {
                    width: 50mm;
                    height: 77mm;
                    overflow: hidden;
                    box-sizing: border-box;
                    padding: 3mm;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    text-align: center;
                    background-color: white;
                    margin: 10mm auto;
                }
                .company-name, .cod-box {
                    background-color: #000;
                    color: #fff;
                    font-weight: 700;
                    border-radius: 5px;
                    display: inline-block;
                    -webkit-print-color-adjust: exact;
                    color-adjust: exact;
                }
                .company-name {
                    font-size: 13px;
                    padding: 3px 8px;
                    margin-bottom: 4px;
                }
                .order-id {
                    font-size: 15px;
                    font-weight: 700;
                    margin: 2px 0;
                }
                .customer-details p {
                    margin: 1px 0;
                    font-size: 11px;
                }
                .cod-box {
                    padding: 4px 10px;
                    border-radius: 8px;
                    margin: 5px 0;
                    font-size: 13px;
                }
                .products-list, .note-section {
                     font-size: 10px;
                     margin: 3px 0;
                }
                .barcode {
                    margin-top: auto;
                    width: 100%; /* Ensure barcode container takes full width */
                }
                 .barcode img {
                    width: 90%; /* Correction 2: Set width to 90% of the container */
                    height: 14mm; /* Correction 2: Adjusted height (approx 55px) */
                }

                @media print {
                    body {
                        background-color: #FFF;
                        margin: 0;
                        padding: 0;
                    }
                    .sticker-page {
                        margin: 0;
                        padding: 3mm;
                        page-break-after: always;
                    }
                    .sticker-page:last-child {
                        page-break-after: auto;
                    }
                    @page {
                        size: 50mm 77mm;
                        margin: 0;
                    }
                }
            </style>
        </head>
        <body>
        <?php foreach ($orders_to_print as $order) :
                $order_id = $order->get_id();
                $order_number = $order->get_order_number();
                
                $order_skus = [];
                foreach ($order->get_items() as $order_item) {
                    $product_for_sku = $order_item->get_product();
                    $order_skus[] = $product_for_sku && $product_for_sku->get_sku() ? $product_for_sku->get_sku() : 'N/A';
                }
                // Use array_unique to avoid listing the same SKU multiple times if quantity > 1
                $product_list_str = implode(', ', array_unique($order_skus));
            ?>
            <div class="sticker-page">
                <div class="company-name"><?php echo esc_html($company_name); ?></div>
                
                <!-- Replaced Order ID with Phone Number for privacy/simplicity on sticker -->
                <p class="order-id"><?php echo esc_html($order->get_billing_phone()); ?></p>

                <div class="customer-details">
                    <p><?php echo esc_html($order->get_formatted_billing_full_name()); ?></p>
                    <p><?php echo esc_html($order->get_billing_address_1()); ?></p>
                </div>

                <div class="cod-box">
                    COD - <?php echo wp_kses_post($order->get_total()); ?>
                </div>

                <p class="products-list">
                    Products: (<?php echo esc_html($product_list_str); ?>)
                </p>
                
                <p class="note-section">
                    Note - <?php echo esc_html($order->get_customer_note() ?: ''); ?>
                </p>

                <div class="barcode">
                    <?php
                    // Barcode uses the actual Order Number for tracking purposes
                    $barcode_url = "https://barcode.tec-it.com/barcode.ashx?data=" . esc_attr($order_number) . "&code=Code128&dpi=96";
                    ?>
                    <img src="<?php echo esc_url($barcode_url); ?>" alt="Barcode for order <?php echo esc_attr($order_number); ?>">
                </div>
            </div>
            <?php endforeach; ?>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
