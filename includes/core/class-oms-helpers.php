<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contains helper/utility functions for the plugin.
 */
class OMS_Helpers {

    /**
     * Get all configured couriers.
     * @return array
     */
    public static function get_couriers() {
        return get_option('oms_couriers', []);
    }

    /**
     * Get a specific courier by its ID.
     * @param string $courier_id
     * @return array|null
     */
    public static function get_courier_by_id($courier_id) {
        $couriers = self::get_couriers();
        foreach ($couriers as $courier) {
            if (isset($courier['id']) && $courier['id'] === $courier_id) {
                return $courier;
            }
        }
        return null;
    }

    /**
     * Get the allowed next statuses based on the current status.
     * @param string $current_status
     * @return array
     */
    public static function get_allowed_next_statuses($current_status) {
        $type1 = ['processing', 'on-hold', 'completed', 'cancelled', 'no-response', 'exchange', 'warehouse'];
        $type2 = ['completed', 'ready-to-ship', 'shipped'];
        $type3 = ['delivered', 'returned', 'partial-return', 'refunded'];
        $allowed = [];
        if (in_array($current_status, $type1)) $allowed = array_merge($allowed, $type1);
        if (in_array($current_status, $type2)) $allowed = array_merge($allowed, $type2);
        if (in_array($current_status, $type3)) $allowed = array_merge($allowed, $type3);
        if ($current_status === 'shipped') $allowed = array_merge($allowed, $type3);
        if (in_array($current_status, ['pending', 'draft'])) $allowed = $type1;
        return array_values(array_unique(array_diff($allowed, [$current_status])));
    }

    /**
     * Check if a status transition is valid according to the workflow.
     * @param string $from_status
     * @param string $to_status
     * @return bool
     */
    public static function is_valid_status_transition($from_status, $to_status) {
        if (get_option('oms_workflow_enabled', 'yes') !== 'yes') {
            return true;
        }
        return in_array($to_status, self::get_allowed_next_statuses($from_status));
    }
}
