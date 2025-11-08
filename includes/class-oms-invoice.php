<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the generation of printable invoices.
 */
class OMS_Invoice {

    /**
     * Generates the complete HTML for one or more invoices.
     *
     * @param array $order_ids An array of WooCommerce order IDs.
     * @return string The generated HTML content for the invoices.
     */
    public function generate_invoices_html($order_ids) {
        $company_name = get_option('oms_invoice_company_name', get_bloginfo('name'));
        $mobile_number = get_option('oms_invoice_mobile_number', '');

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale-1.0">
            <title>Invoices</title>
            <style>
                @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap');
                body {
                    font-family: 'Roboto', sans-serif;
                    margin: 0;
                    padding: 0;
                    background-color: #f5f5f5;
                    color: #333;
                }
                .invoice-page {
                    width: 210mm;
                    min-height: 297mm;
                    padding: 20mm;
                    margin: 10mm auto;
                    box-sizing: border-box;
                    page-break-after: always;
                    background: #fff;
                    box-shadow: 0 0 10px rgba(0,0,0,0.1);
                }
                .invoice-page:last-child {
                    page-break-after: auto;
                }
                
                .invoice-header {
                    margin-bottom: 20px;
                    padding-bottom: 20px;
                    border-bottom: 1px solid #eee;
                }
                .company-details h1 {
                    color: #000;
                    margin: 0;
                    font-size: 26px;
                    font-weight: 700;
                }
                 .company-details p {
                    margin: 5px 0 0;
                    font-size: 14px;
                 }

                .customer-invoice-details {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    gap: 20px;
                    margin-bottom: 30px;
                }

                .invoice-meta {
                    text-align: right;
                    flex-shrink: 0;
                }
                .invoice-meta .invoice-id-text {
                     margin: 0 0 10px;
                     font-size: 24px;
                     font-weight: bold;
                     color: #000;
                }
                .invoice-meta img {
                    max-width: 180px;
                    height: auto;
                    margin-top: 10px;
                }
                 .invoice-meta p {
                     margin: 5px 0 0;
                     font-size: 14px;
                 }

                .billing-info {
                    margin-bottom: 0;
                    flex: 1;
                }
                 .billing-info h3 {
                     margin: 0 0 10px;
                     font-size: 16px;
                     padding-bottom: 5px;
                     color: #000;
                 }
                 .billing-info p {
                     margin: 4px 0;
                     font-size: 14px;
                     line-height: 1.6;
                 }

                .items-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 30px;
                }
                .items-table th, .items-table td {
                    padding: 12px;
                    text-align: left;
                    font-size: 14px;
                }
                .items-table thead {
                    border-bottom: 2px solid #333;
                }
                .items-table th {
                    font-weight: bold;
                }
                .items-table tbody tr {
                    border-bottom: 1px solid #eee;
                }
                .items-table .item-image {
                    width: 50px;
                    height: 50px;
                    object-fit: cover;
                    border-radius: 4px;
                }
                .items-table td.align-right { text-align: right; }
                .items-table td.align-center { text-align: center; }

                .footer-grid {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    gap: 30px;
                }

                .shipping-note-box {
                    flex-basis: 50%;
                }
                .shipping-note-box h3 {
                    margin: 0 0 10px;
                    font-size: 16px;
                }
                 .shipping-note-box p {
                     margin: 0;
                     font-size: 14px;
                     font-style: italic;
                     color: #555;
                 }

                .totals-box {
                    flex-basis: 40%;
                }
                .totals-box table {
                    width: 100%;
                }
                .totals-box td {
                    padding: 10px;
                    font-size: 14px;
                }
                .totals-box .total-amount td {
                    font-weight: bold;
                    font-size: 18px;
                    border-top: 2px solid #333;
                }
                @media print {
                    body {
                        background-color: #fff;
                    }
                    .invoice-page {
                        margin: 0;
                        border: none;
                        box-shadow: none;
                        width: 100%;
                        min-height: 0;
                    }
                }
            </style>
        </head>
        <body>
        <?php foreach ($order_ids as $index => $order_id) :
            $order = wc_get_order($order_id);
            if (!$order) continue;
            ?>
            <div class="invoice-page">
                <div class="invoice-header">
                    <div class="company-details">
                        <h1><?php echo esc_html($company_name); ?></h1>
                        <p>Mobile: <?php echo esc_html($mobile_number); ?></p>
                    </div>
                </div>

                <div class="customer-invoice-details">
                    <div class="billing-info">
                        <h3>Bill To:</h3>
                        <p><strong>Name:</strong> <?php echo esc_html($order->get_formatted_billing_full_name()); ?></p>
                        <p><strong>Address:</strong> <?php echo esc_html($order->get_billing_address_1()); ?></p>
                        <p><strong>Mobile:</strong> <?php echo esc_html($order->get_billing_phone()); ?></p>
                        <p><strong>Delivery:</strong> <?php echo esc_html(current($order->get_shipping_methods())->get_name() ?: 'N/A'); ?></p>
                        <p><strong>Tracking:</strong> <?php echo esc_html($order->get_meta('_steadfast_tracking_code') ?: 'N/A'); ?></p>
                    </div>
                    <div class="invoice-meta">
                        <p class="invoice-id-text">Invoice: <?php echo esc_html($order->get_order_number()); ?></p>
                        <?php
                        $barcode_url = "https://barcode.tec-it.com/barcode.ashx?data=" . esc_attr($order->get_order_number()) . "&code=Code128&dpi=96";
                        ?>
                        <img src="<?php echo esc_url($barcode_url); ?>" alt="Barcode for order <?php echo esc_attr($order->get_order_number()); ?>">
                        <p>Date: <?php echo esc_html($order->get_date_created()->date_i18n('d/m/Y')); ?></p>
                    </div>
                </div>


                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Product</th>
                            <th class="align-right">Unit Price</th>
                            <th class="align-center">Qty</th>
                            <th class="align-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order->get_items() as $item_id => $item) :
                            $product = $item->get_product();
                            $image_url = $product ? wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') : wc_placeholder_img_src();
                        ?>
                        <tr>
                            <td class="align-center"><img src="<?php echo esc_url($image_url); ?>" class="item-image" alt="<?php echo esc_attr($item->get_name()); ?>"></td>
                            <td>
                                <?php echo esc_html($item->get_name()); ?>
                                <br><small>SKU: <?php echo esc_html($product ? $product->get_sku() : 'N/A'); ?></small>
                            </td>
                            <td class="align-right"><?php echo wp_kses_post(wc_price($item->get_subtotal() / $item->get_quantity(), ['currency' => $order->get_currency()])); ?></td>
                            <td class="align-center"><?php echo esc_html($item->get_quantity()); ?></td>
                            <td class="align-right"><?php echo wp_kses_post($order->get_formatted_line_subtotal($item)); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="footer-grid">
                    <div class="shipping-note-box">
                        <h3>Shipping Note</h3>
                        <p><?php echo esc_html($order->get_customer_note() ?: 'None'); ?></p>
                    </div>
                    <div class="totals-box">
                        <table>
                            <tr>
                                <td>Sub Total</td>
                                <td class="align-right"><?php echo wp_kses_post($order->get_subtotal_to_display()); ?></td>
                            </tr>
                            <tr>
                                <td>Delivery Charge</td>
                                <td class="align-right"><?php echo wp_kses_post($order->get_shipping_to_display()); ?></td>
                            </tr>
                             <?php if ($order->get_discount_total() > 0) : ?>
                            <tr>
                                <td>Discount</td>
                                <td class="align-right">-<?php echo wp_kses_post(wc_price($order->get_discount_total(), ['currency' => $order->get_currency()])); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr class="total-amount">
                                <td>Total Amount</td>
                                <td class="align-right"><?php echo wp_kses_post($order->get_formatted_order_total()); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

            </div>
        <?php endforeach; ?>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
