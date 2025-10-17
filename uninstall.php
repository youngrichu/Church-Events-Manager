<?php
// If uninstall is not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Get options for cleanup settings
$options = get_option('church_events_options', []);
$clean_data = isset($options['clean_data_on_uninstall']) && $options['clean_data_on_uninstall'] === '1';

if ($clean_data) {
    global $wpdb;

    // Delete custom post type posts and meta
    $posts = get_posts([
        'post_type' => 'church_event',
        'numberposts' => -1,
        'post_status' => 'any',
        'fields' => 'ids'
    ]);

    foreach ($posts as $post_id) {
        wp_delete_post($post_id, true);
    }

    // Delete custom taxonomies terms
    $terms = get_terms([
        'taxonomy' => 'event_category',
        'hide_empty' => false,
        'fields' => 'ids'
    ]);

    foreach ($terms as $term_id) {
        wp_delete_term($term_id, 'event_category');
    }

    // Drop custom tables
    $tables = [
        $wpdb->prefix . 'cem_event_meta',
        $wpdb->prefix . 'cem_rsvp'
    ];

    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }

    // Delete plugin options
    delete_option('church_events_options');

    // Delete transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_cem_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_timeout_cem_%'");

    // Delete user meta related to events
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '_cem_%'");

    // Clear any cached data
    wp_cache_flush();
} 