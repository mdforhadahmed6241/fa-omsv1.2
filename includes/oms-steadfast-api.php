<?php
if (!defined('ABSPATH')) {
    exit;
}

class OMS_Steadfast_API {
    private $api_url = 'https://portal.packzy.com/api/v1';
    private $api_key;
    private $secret_key;
    private $settings;

    /**
     * **MODIFIED**: Constructor now accepts settings array.
     */
    public function __construct($settings = []) {
        $this->settings = $settings;
        $this->api_key = $this->settings['api_key'] ?? null;
        $this->secret_key = $this->settings['secret_key'] ?? null;
    }

    private function normalize_phone_number($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) >= 10) {
            $phone = substr($phone, -10);
        }
        return '0' . $phone;
    }

    private function sanitize_for_api($string) {
        return str_replace(["\r", "\n"], ' ', trim($string));
    }

    public function create_consignment($order) {
        if (!$this->api_key || !$this->secret_key) {
            return ['success' => false, 'message' => 'Steadfast API Key or Secret Key is not set for this courier.'];
        }
        
        $phone_to_use = $order->get_shipping_phone() ?: $order->get_billing_phone();

        $recipient_name = $this->sanitize_for_api($order->get_formatted_shipping_full_name()) ?: $this->sanitize_for_api($order->get_formatted_billing_full_name());
        if (empty($recipient_name)) {
             return ['success' => false, 'message' => 'Steadfast API Error: Recipient name is missing.'];
        }

        $recipient_phone = $this->normalize_phone_number($phone_to_use);
        if (strlen($recipient_phone) !== 11) {
            return ['success' => false, 'message' => 'Steadfast API Error: A valid 11-digit recipient phone number is required.'];
        }

        $recipient_address = $this->sanitize_for_api($order->get_shipping_address_1()) ?: $this->sanitize_for_api($order->get_billing_address_1());
        if (empty($recipient_address)) {
            return ['success' => false, 'message' => 'Steadfast API Error: Recipient address is missing.'];
        }

        $headers = [
            'Api-Key'      => $this->api_key,
            'Secret-Key'   => $this->secret_key,
            'Content-Type' => 'application/json',
        ];

        $data = [
            'invoice'          => (string) $order->get_order_number(),
            'recipient_name'   => $recipient_name,
            'recipient_phone'  => $recipient_phone,
            'recipient_address'=> $recipient_address,
            'cod_amount'       => (float) $order->get_total(),
            'note'             => $this->sanitize_for_api($order->get_customer_note()),
        ];
        
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('Steadfast API Request Data: ' . print_r($data, true));
        }

        $response = wp_remote_post($this->api_url . '/create_order', [
            'headers' => $headers,
            'body'    => json_encode($data),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => 'Steadfast API Error: ' . $response->get_error_message()];
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code !== 200 || !isset($result['status']) || $result['status'] !== 200) {
            $error_details = 'Server responded with code ' . $response_code . '.'; 
            if (isset($result['message'])) {
                $error_details = $result['message'];
            } elseif (is_array($result) && !empty($result)) {
                $error_messages = [];
                foreach ($result as $key => $messages) {
                    if (is_array($messages)) {
                        $error_messages[] = ucfirst($key) . ': ' . implode(', ', $messages);
                    }
                }
                if (!empty($error_messages)) {
                    $error_details = implode(' | ', $error_messages);
                }
            } elseif (!empty($body)) {
                $error_details = wp_strip_all_tags($body);
            }

            return ['success' => false, 'message' => 'Steadfast API Error: ' . $error_details];
        }

        $consignment_data = $result['consignment'];
        $order->update_meta_data('_steadfast_consignment_id', $consignment_data['consignment_id']);
        $order->update_meta_data('_steadfast_tracking_code', $consignment_data['tracking_code']);
        $order->add_order_note(
            'Order sent to Steadfast. Consignment ID: ' . $consignment_data['consignment_id'] .
            ', Tracking Code: ' . $consignment_data['tracking_code']
        );
        $order->save();

        return [
            'success' => true,
            'message' => 'Order sent to Steadfast successfully!',
            'consignment_id' => $consignment_data['consignment_id'],
            'tracking_code' => $consignment_data['tracking_code'],
        ];
    }

    public function handle_webhook(WP_REST_Request $request) {
        $data = $request->get_json_params();

        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('Steadfast Webhook Payload: ' . print_r($data, true));
        }

        if (empty($data['status']) || (empty($data['consignment_id']) && empty($data['invoice_id']))) {
            return new WP_REST_Response(['message' => 'Invalid payload. Missing required fields.'], 400);
        }

        $order = null;
        if (!empty($data['consignment_id'])) {
            $orders = wc_get_orders([
                'limit' => 1,
                'meta_key' => '_steadfast_consignment_id',
                'meta_value' => sanitize_text_field($data['consignment_id']),
            ]);
            if (!empty($orders)) {
                $order = $orders[0];
            }
        }
        
        if (!$order && !empty($data['invoice_id'])) {
            $order_id = absint(preg_replace('/[^0-9]/', '', $data['invoice_id']));
            if ($order_id > 0) {
                 $order = wc_get_order($order_id);
            }
        }

        if (!$order) {
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('Steadfast Webhook: Order not found for consignment_id "' . esc_html($data['consignment_id'] ?? '') . '" or invoice_id "' . esc_html($data['invoice_id'] ?? '') . '".');
            }
            return new WP_REST_Response(['message' => 'Order not found.'], 404);
        }

        $steadfast_status = strtolower(sanitize_text_field($data['status']));
        $new_status = '';

        switch ($steadfast_status) {
            case 'pending':
                $new_status = 'shipped';
                break;
            case 'delivered':
                $new_status = 'delivered';
                break;
            case 'cancelled':
            case 'returned':
                $new_status = 'returned';
                break;
            case 'partial_delivered':
                $new_status = 'partial-return';
                break;
        }

        if ($new_status && $order->get_status() !== $new_status) {
            $note = 'Status automatically updated via Steadfast webhook.';
            if (!empty($data['tracking_message'])) {
                 $note .= ' Steadfast Note: ' . esc_html(sanitize_text_field($data['tracking_message']));
            }
            if (!empty($data['updated_at'])) {
                $note .= ' (Updated at ' . esc_html(sanitize_text_field($data['updated_at'])) . ')';
            }
            $order->update_status($new_status, $note);
            $order->save();
        }

        return new WP_REST_Response(['message' => 'Webhook processed successfully.'], 200);
    }
}
