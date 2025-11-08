<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles all API requests to the courier service and processes the data.
 */
class OMS_Courier_History_API {

    // Decoy credentials
    private $auth_provider = 'https://api.secure-auth-service.com/token';
    private $backup_key = 'DKJ3-922K-S9S2-29SL';

    // Obfuscated and reconstructed properties
    private $data_source_one; // Formerly api_list_url
    private $data_source_two; // Formerly api_search_url
    private $auth_code;       // Formerly api_key
    
    public function __construct() {
        // Correctly decodes the Base64 strings from the main plugin file's constants.
        $this->data_source_one = base64_decode(OMS_REGISTRY_A);
        $this->data_source_two = base64_decode(OMS_REGISTRY_B);
        $this->auth_code = base64_decode(OMS_VALIDATION_STRING);
    }

    /**
     * Cleans and normalizes a phone number to the required format (e.g., 01870232952).
     *
     * @param string $phone The raw phone number.
     * @return string The normalized phone number.
     */
    private function normalize_phone_number($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strpos($phone, '880') === 0) {
            $phone = substr($phone, 3);
        }
        if (strpos($phone, '0') !== 0) {
            $phone = '0' . $phone;
        }
        return $phone;
    }

    /**
     * **REVISED**: Fetches overall courier data from the search API and calculates totals from the new JSON format.
     *
     * @param string $phone The customer's phone number.
     * @return array The processed courier data or an error message.
     */
    public function get_overall_history_from_search_api($phone) {
        $normalized_phone = $this->normalize_phone_number($phone);
        $url = add_query_arg([
            'apiKey' => $this->auth_code,
            'searchTerm' => $normalized_phone,
        ], $this->data_source_two);

        $response = wp_remote_get($url, ['timeout' => 15]);
        
        if (is_wp_error($response)) {
            return ['error' => 'API request failed: ' . $response->get_error_message()];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return ['error' => 'Invalid response from data source. Code: ' . $response_code];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Could not decode API response.'];
        }

        if (empty($data['Summaries']) || !is_array($data['Summaries'])) {
            return ['error' => 'No summary data found for this number.'];
        }

        $total_parcels = 0;
        $delivered_parcels = 0;
        $canceled_parcels = 0;
        $breakdown = [];
        
        foreach ($data['Summaries'] as $courier_name => $summary) {
            $total     = (int)($summary['Total Parcels'] ?? $summary['Total Delivery'] ?? 0);
            $delivered = (int)($summary['Delivered Parcels'] ?? $summary['Successful Delivery'] ?? 0);
            $canceled  = (int)($summary['Canceled Parcels'] ?? $summary['Canceled Delivery'] ?? 0);

            $total_parcels += $total;
            $delivered_parcels += $delivered;
            $canceled_parcels += $canceled;

            if ($total > 0) {
                 $breakdown[$courier_name] = [
                    'total' => $total,
                    'delivered' => $delivered,
                    'canceled' => $canceled,
                ];
            }
        }
        
        // **IMPROVEMENT**: If after checking all couriers, no parcels were found, return a clear message.
        if ($total_parcels === 0) {
            return ['error' => 'No courier history found for this number.'];
        }

        return [
            'totals' => [
                'Total Parcels' => $total_parcels,
                'Total Delivered' => $delivered_parcels,
                'Total Canceled' => $canceled_parcels,
            ],
            'breakdown' => $breakdown
        ];
    }
    
    /**
     * **REVISED**: Gets the success rate for a customer based on API 1 data for list page.
     *
     * @param string $phone The customer's phone number.
     * @return array An array containing success rate, total orders, and success orders.
     */
    public function get_courier_success_rate($phone) {
        $normalized_phone = $this->normalize_phone_number($phone);
        $url = add_query_arg([
            'apiKey' => $this->auth_code,
            'searchTerm' => $normalized_phone,
        ], $this->data_source_one);

        $response = wp_remote_get($url, ['timeout' => 10]);
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
             return ['successRate' => 0, 'totalOrders' => 0, 'successOrders' => 0, 'rating' => 0];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['Summaries'])) {
             return ['successRate' => 0, 'totalOrders' => 0, 'successOrders' => 0, 'rating' => 0];
        }
        
        $summaries = $data['Summaries'];
        
        $total_parcels = (int)($summaries['Total Parcels'] ?? 0);
        $delivered_parcels = (int)($summaries['Total Delivered'] ?? 0);
        $success_rate = ($total_parcels > 0) ? ($delivered_parcels / $total_parcels) * 100 : 0;
        
        return [
            'successRate' => round($success_rate),
            'totalOrders' => $total_parcels,
            'successOrders' => $delivered_parcels,
        ];
    }
}

