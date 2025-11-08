<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles REST API endpoints for the plugin (e.g., webhooks).
 */
class OMS_Api {

    public function __construct() {
        // Intentionally left blank.
    }

    /**
     * Register REST API hooks.
     */
    public function load_hooks() {
        add_action('rest_api_init', [$this, 'register_webhook_endpoints']);
    }

    /**
     * Registers the webhook endpoints for courier status updates.
     */
    public function register_webhook_endpoints() {
        register_rest_route('oms/v1', '/webhook/(?P<courier_id>[a-zA-Z0-9_]+)', [
            'methods' => 'POST', 
            'permission_callback' => '__return_true',
            'callback' => function(WP_REST_Request $request) {
                $courier = OMS_Helpers::get_courier_by_id($request->get_param('courier_id'));
                if (!$courier) {
                    return new WP_REST_Response(['message' => 'Courier configuration not found.'], 404);
                }
                if ($courier['type'] === 'steadfast') { 
                    $api = new OMS_Steadfast_API($courier['credentials']); 
                    return $api->handle_webhook($request); 
                } elseif ($courier['type'] === 'pathao') { 
                    $api = new OMS_Pathao_API($courier['credentials']); 
                    return $api->handle_webhook($request); 
                }
                return new WP_REST_Response(['message' => 'Invalid courier type for webhook.'], 400);
            },
        ]);
    }
}
