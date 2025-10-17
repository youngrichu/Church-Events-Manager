<?php
namespace ChurchEventsManager\Core;

class Activator {
    public static function activate() {
        // Create database tables
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Create events meta table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cem_event_meta (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_id bigint(20) NOT NULL,
            event_date datetime NOT NULL,
            event_end_date datetime,
            location varchar(255),
            is_recurring tinyint(1) DEFAULT 0,
            recurring_pattern varchar(50),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY event_id (event_id)
        ) $charset_collate;";

        // Create RSVP table
        $sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cem_rsvp (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            status varchar(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY event_user (event_id, user_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Add capabilities to administrator role
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $capabilities = [
                'edit_church_events',
                'edit_others_church_events',
                'publish_church_events',
                'read_church_events',
                'read_private_church_events',
                'delete_church_events',
                'delete_others_church_events',
                'delete_private_church_events',
                'delete_published_church_events',
                'edit_private_church_events',
                'edit_published_church_events',
                'manage_church_events',
                'manage_church_event_categories'
            ];

            foreach ($capabilities as $cap) {
                $admin_role->add_cap($cap);
            }
        }

        // Set flag to flush rewrite rules
        update_option('cem_flush_rewrite_rules', true);

        // Set initial plugin version
        update_option('cem_version', CEM_VERSION);
        update_option('cem_db_version', CEM_DB_VERSION);

        // Clear any existing transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_cem_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_timeout_cem_%'");
    }
}