<?php
if (!defined('ABSPATH')) {
    exit;
}

class OMS_Pathao_API {
    private $base_url = 'https://api-hermes.pathao.com';
    private $settings;

    /**
     * **MODIFIED**: Constructor now accepts settings array.
     */
    public function __construct($settings = []) {
        $this->settings = $settings;
    }
    
    private function get_token($force_new = false) {
        $token_transient_key = 'oms_pathao_token_' . md5(json_encode($this->settings));
        $token_data = get_transient($token_transient_key);

        if ($force_new || !$token_data || time() > ($token_data['expires_at'] ?? 0)) {
            
            $payload = [
                'client_id'     => $this->settings['client_id'] ?? '',
                'client_secret' => $this->settings['client_secret'] ?? '',
                'username'      => $this->settings['email'] ?? '',
                'password'      => $this->settings['password'] ?? '',
                'grant_type'    => 'password'
            ];

            $response = wp_remote_post($this->base_url . '/aladdin/api/v1/issue-token', [
                'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
                'body'    => json_encode($payload),
                'timeout' => 20
            ]);

            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                error_log('Pathao Token Error: ' . print_r($response, true));
                return false;
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!isset($body['access_token'])) {
                error_log('Pathao Token Error: Access token not found in response. ' . print_r($body, true));
                return false;
            }

            $token_data = [
                'access_token' => $body['access_token'],
                'expires_at'   => time() + ($body['expires_in'] - 300) // 5 min buffer
            ];
            set_transient($token_transient_key, $token_data, $body['expires_in']);
        }
        return $token_data['access_token'];
    }
    
    public function get_cities() {
        $token = $this->get_token();
        if (!$token) return false;
        $response = wp_remote_get($this->base_url . "/aladdin/api/v1/countries/1/city-list", [
            'headers' => ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json']
        ]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) return false;
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['data']['data'] ?? false;
    }

    public function get_zones($city_id) {
        $token = $this->get_token();
        if (!$token) return false;
        $response = wp_remote_get($this->base_url . "/aladdin/api/v1/cities/{$city_id}/zone-list", [
            'headers' => ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json']
        ]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) return false;
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['data']['data'] ?? false;
    }

    public function get_areas($zone_id) {
        $token = $this->get_token();
        if (!$token) return false;
        $response = wp_remote_get($this->base_url . "/aladdin/api/v1/zones/{$zone_id}/area-list", [
            'headers' => ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json']
        ]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) return false;
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['data']['data'] ?? false;
    }

    public function create_order($order, $location_data = []) {
        try {
            $token = $this->get_token();
            if (!$token) {
                return ['success' => false, 'message' => 'Pathao API Error: Could not get an authentication token. Please check your API credentials.'];
            }

            $store_id = $this->settings['default_store'] ?? 0;
            if (empty($store_id)) {
                return ['success' => false, 'message' => 'Pathao API Error: Default Store ID is not configured.'];
            }
            
            $city_id = absint($location_data['city_id'] ?? 0);
            if (empty($city_id)) {
                return ['success' => false, 'message' => 'Pathao API Error: A City must be selected.'];
            }

            $zone_id = absint($location_data['zone_id'] ?? 0);
            if (empty($zone_id)) {
                return ['success' => false, 'message' => 'Pathao API Error: A Zone must be selected.'];
            }

            $raw_phone = $order->get_shipping_phone() ?: $order->get_billing_phone();
            $phone_numeric = preg_replace('/[^0-9]/', '', $raw_phone);
            if (substr($phone_numeric, 0, 3) == '880') {
                $phone_numeric = substr($phone_numeric, 2);
            }
            if (strlen($phone_numeric) < 10 || strlen($phone_numeric) > 11) {
                 return ['success' => false, 'message' => 'Pathao API Error: Invalid phone number format. Must be 11 digits.'];
            }
            $recipient_phone = '0' . substr($phone_numeric, -10);
            
            $recipient_name = wp_check_invalid_utf8(trim($order->get_formatted_shipping_full_name() ?: $order->get_formatted_billing_full_name()), true);
            if (strlen($recipient_name) < 3) {
                return ['success' => false, 'message' => 'Pathao API Error: Recipient name must be at least 3 characters long.'];
            }

            $recipient_address = wp_check_invalid_utf8(trim($order->get_shipping_address_1() ?: $order->get_billing_address_1()), true);
             if (strlen($recipient_address) < 10) {
                return ['success' => false, 'message' => 'Pathao API Error: Recipient address must be at least 10 characters long.'];
            }

            $total_quantity = 0;
            $total_weight = 0;
            $item_descriptions_array = [];
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                $quantity = $item->get_quantity();
                
                $total_quantity += $quantity;
                $item_descriptions_array[] = $item->get_name() . ' x ' . $quantity;

                if ($product && $product->has_weight()) {
                    $total_weight += (float)$product->get_weight() * $quantity;
                }
            }
            if ($total_quantity == 0) $total_quantity = 1;
            if ($total_weight == 0) $total_weight = 0.5;

            $item_description = wp_check_invalid_utf8(implode(', ', $item_descriptions_array), true);
            $special_instruction = wp_check_invalid_utf8($order->get_customer_note(), true);
            
            $payload = [
                'store_id'            => (int) $store_id,
                'merchant_order_id'   => (string) $order->get_order_number(),
                'recipient_name'      => $recipient_name,
                'recipient_phone'     => $recipient_phone,
                'recipient_address'   => $recipient_address,
                'recipient_city'      => (int) $city_id,
                'recipient_zone'      => (int) $zone_id,
                'recipient_area'      => (int) ($location_data['area_id'] ?? 0),
                'delivery_type'       => 48, // 48 for Normal Delivery
                'item_type'           => 2,  // 2 for Parcel
                'item_quantity'       => $total_quantity,
                'item_weight'         => (float) $total_weight,
                'amount_to_collect'   => (float) $order->get_total(),
                'item_description'    => $item_description,
                'special_instruction' => $special_instruction
            ];

            $json_payload = json_encode($payload);
            if ($json_payload === false) {
                return ['success' => false, 'message' => 'Pathao API Error: Failed to encode order data. JSON error: ' . json_last_error_msg()];
            }

            $response = wp_remote_post($this->base_url . '/aladdin/api/v1/orders', [
                'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json', 'Accept' => 'application/json'],
                'body'    => $json_payload,
                'timeout' => 30,
            ]);

            if (is_wp_error($response)) {
                return ['success' => false, 'message' => 'Pathao API Error: ' . $response->get_error_message()];
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $response_code = wp_remote_retrieve_response_code($response);

            if ($response_code >= 300 || !isset($body['data']['consignment_id'])) {
                $error_details = 'An unknown error occurred.';
                if (isset($body['message'])) {
                    $error_details = $body['message'];
                } elseif (!empty($body['errors'])) {
                    $error_messages = [];
                    foreach ($body['errors'] as $key => $messages) {
                        $error_messages[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . implode(', ', (array)$messages);
                    }
                    $error_details = implode(' | ', $error_messages);
                } else {
                     $error_details = 'Failed to create order. Response Code: ' . $response_code . '. Body: ' . wp_strip_all_tags(print_r($body, true));
                }
                return ['success' => false, 'message' => 'Pathao API Error: ' . $error_details];
            }

            $order->update_meta_data('_pathao_consignment_id', $body['data']['consignment_id']);
            $order->add_order_note('Order sent to Pathao. Consignment ID: ' . $body['data']['consignment_id']);
            $order->save();

            return ['success' => true, 'message' => 'Order sent to Pathao successfully!', 'consignment_id' => $body['data']['consignment_id']];
        
        } catch (Throwable $e) {
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('Pathao Create Order Fatal Error: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
            }
            return ['success' => false, 'message' => 'A critical server error occurred: ' . $e->getMessage()];
        }
    }
    
    public function handle_webhook(WP_REST_Request $request) {
        $data = $request->get_json_params();
        
        // **MODIFIED**: The incoming secret is now just the client_id
        $incoming_secret = $request->get_header('x-pathao-signature');
        $saved_secret = $this->settings['client_id'] ?? '';

        if (empty($saved_secret) || !hash_equals($saved_secret, $incoming_secret)) {
            return new WP_REST_Response(['message' => 'Invalid webhook secret.'], 401);
        }
        
        $order_id_from_merchant = $data['merchant_order_id'] ?? null;
        $status = $data['order_status_slug'] ?? null;

        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('Pathao Webhook Received: ' . print_r($data, true));
        }
        
        if (empty($order_id_from_merchant) || empty($status)) return new WP_REST_Response(['message' => 'Invalid payload.'], 400);

        $orders = wc_get_orders(['order_number' => $order_id_from_merchant]);
        if (empty($orders)) return new WP_REST_Response(['message' => 'Order not found.'], 404);
        $order = $orders[0];

        $new_status = '';
        switch (strtolower($status)) {
            case 'picked_up': $new_status = 'shipped'; break;
            case 'delivered': $new_status = 'delivered'; break;
            case 'delivery_failed':
            case 'returned_to_merchant': $new_status = 'returned'; break;
            case 'partial_delivery': $new_status = 'partial-return'; break;
        }

        if ($new_status && $order->get_status() !== $new_status) {
            $note = 'Status updated via Pathao webhook. New status: ' . esc_html($data['order_status'] ?? $status);
            $order->update_status($new_status, $note);
        }
        
        if (isset($data['consignment_id']) && !$order->get_meta('_pathao_consignment_id')) {
            $order->update_meta_data('_pathao_consignment_id', $data['consignment_id']);
            $order->save();
        }

        return new WP_REST_Response(['message' => 'Webhook processed successfully.'], 200);
    }
}
