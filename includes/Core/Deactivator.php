<?php
namespace ChurchEventsManager\Core;

class Deactivator {
    public static function deactivate() {
        // Clear scheduled cron events
        wp_clear_scheduled_hook('cem_daily_reminder_check');
        
        // Clear transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_cem_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_timeout_cem_%'");
        
        // Clear cache safely
        if (isset($GLOBALS['church_events_cache'])) {
            try {
                $GLOBALS['church_events_cache']->clear_all_caches();
            } catch (\Exception $e) {
                // Log error but don't break deactivation
                error_log('Church Events Manager: Error clearing cache during deactivation - ' . $e->getMessage());
            }
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
} 