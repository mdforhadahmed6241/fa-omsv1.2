<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fired during plugin activation.
 * This class defines all code necessary to run during the plugin's activation.
 */
class OMS_Activator {

    /**
     * The main activation method.
     */
    public static function activate() {
        self::create_database_tables();
    }

    /**
     * Creates the custom database tables required by the plugin.
     */
    private static function create_database_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $table_incomplete = $wpdb->prefix . 'oms_incomplete_orders';
        $sql_incomplete = "CREATE TABLE $table_incomplete (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            phone varchar(20) NOT NULL,
            customer_data longtext NOT NULL,
            cart_contents longtext NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY session_id (session_id)
        ) $charset_collate;";
        dbDelta($sql_incomplete);

        // --- NEW TABLE FOR RETURN ORDERS ---
        $table_returns = $wpdb->prefix . 'oms_return_orders';
        $sql_returns = "CREATE TABLE $table_returns (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            order_status_slug varchar(60) NOT NULL,
            order_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            create_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            receive_status tinyint(1) DEFAULT 0 NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY order_id (order_id),
            KEY receive_status (receive_status)
        ) $charset_collate;";
        dbDelta($sql_returns);

        $table_cities = $wpdb->prefix . 'oms_pathao_cities';
        $sql_cities = "CREATE TABLE $table_cities (
            city_id int(11) NOT NULL,
            city_name varchar(255) NOT NULL,
            PRIMARY KEY  (city_id)
        ) $charset_collate;";
        dbDelta($sql_cities);

        $table_zones = $wpdb->prefix . 'oms_pathao_zones';
        $sql_zones = "CREATE TABLE $table_zones (
            zone_id int(11) NOT NULL,
            city_id int(11) NOT NULL,
            zone_name varchar(255) NOT NULL,
            PRIMARY KEY  (zone_id),
            KEY city_id (city_id)
        ) $charset_collate;";
        dbDelta($sql_zones);

        $table_areas = $wpdb->prefix . 'oms_pathao_areas';
        $sql_areas = "CREATE TABLE $table_areas (
            area_id int(11) NOT NULL,
            zone_id int(11) NOT NULL,
            area_name varchar(255) NOT NULL,
            PRIMARY KEY  (area_id),
            KEY zone_id (zone_id)
        ) $charset_collate;";
        dbDelta($sql_areas);

        // This function is called here to ensure it runs after tables are created.
        self::migrate_settings();
    }

    /**
     * Migrates old settings to the new courier-based structure.
     */
    public static function migrate_settings() { // <-- CHANGED from private to public
        if (get_option('oms_settings_migrated')) {
            return;
        }

        $new_couriers = [];
        $old_steadfast = get_option('oms_steadfast_settings');
        $old_pathao = get_option('oms_pathao_settings');
        $old_active = get_option('oms_active_courier');

        if (!empty($old_steadfast['api_key']) && !empty($old_steadfast['secret_key'])) {
            $steadfast_id = 'steadfast_' . time();
            $new_couriers[] = [ 'id' => $steadfast_id, 'name' => 'Steadfast (Default)', 'type' => 'steadfast', 'credentials' => $old_steadfast ];
            if ($old_active === 'steadfast') { update_option('oms_default_courier', $steadfast_id); }
        }

        if (!empty($old_pathao['client_id']) && !empty($old_pathao['client_secret'])) {
            $pathao_id = 'pathao_' . time();
            $new_couriers[] = [ 'id' => $pathao_id, 'name' => 'Pathao (Default)', 'type' => 'pathao', 'credentials' => $old_pathao ];
             if ($old_active === 'pathao') { update_option('oms_default_courier', $pathao_id); }
        }

        if (!empty($new_couriers)) { update_option('oms_couriers', $new_couriers); }
        
        delete_option('oms_steadfast_settings');
        delete_option('oms_pathao_settings');
        delete_option('oms_active_courier');

        update_option('oms_settings_migrated', true);
    }
}
