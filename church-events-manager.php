<?php
/**
 * Plugin Name: Church Events Manager
 * Description: A simple WordPress plugin to manage church events
 * Version: 1.1.0
 * Author: Habtamu
 * Author URI: https://github.com/youngrichu
 * License: GPL v2 or later
 * Text Domain: church-events-manager
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('CEM_VERSION', '1.1.0');
define('CEM_PLUGIN_FILE', __FILE__);
define('CEM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CEM_PLUGIN_URL', plugin_dir_url(__FILE__));
// Add database schema version for migrations
define('CEM_DB_VERSION', '1.1.0');

// Load translations on init hook with priority 1 (before post type registration at priority 10)
// WordPress 6.7.0+ requires translations to be loaded at init or later
add_action('init', function() {
    load_plugin_textdomain(
        'church-events-manager',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}, 1);

// Register activation hook
register_activation_hook(__FILE__, function() {
    // Create custom tables
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Create events meta table
    $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cem_event_meta (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        event_id bigint(20) NOT NULL,
        event_date datetime NOT NULL,
        event_end_date datetime,
        location varchar(255),
        is_recurring tinyint(1) DEFAULT 0,
        recurring_pattern varchar(50),
        is_all_day tinyint(1) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY event_id (event_id)
    ) $charset_collate;";

    dbDelta($sql);

    // Register post type
    register_post_type('church_event', [
        'public' => true,
        'has_archive' => true,
        'rewrite' => ['slug' => 'events']
    ]);

    // Flush rewrite rules
    flush_rewrite_rules();
});

// Register deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Unregister post type
    unregister_post_type('church_event');
    
    // Flush rewrite rules
    flush_rewrite_rules();
});

// Note: Post type and taxonomy registration is now handled by the Plugin class
// to avoid duplicate registration



// Render event details meta box
function cem_render_event_details($post) {
    wp_nonce_field('save_event_details', 'event_details_nonce');

    global $wpdb;
    $event_meta = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}cem_event_meta WHERE event_id = %d",
        $post->ID
    ));

    ?>
    <p>
        <label for="event_date"><?php _e('Event Date:', 'church-events-manager'); ?></label><br>
        <input type="datetime-local" id="event_date" name="event_date" 
               value="<?php echo esc_attr($event_meta ? date('Y-m-d\TH:i', strtotime($event_meta->event_date)) : ''); ?>" required>
    </p>
    <p>
        <label for="event_end_date"><?php _e('End Date:', 'church-events-manager'); ?></label><br>
        <input type="datetime-local" id="event_end_date" name="event_end_date" 
               value="<?php echo esc_attr($event_meta && $event_meta->event_end_date ? date('Y-m-d\TH:i', strtotime($event_meta->event_end_date)) : ''); ?>">
    </p>
    <p>
        <label for="location"><?php _e('Location:', 'church-events-manager'); ?></label><br>
        <input type="text" id="location" name="location" class="large-text" 
               value="<?php echo esc_attr($event_meta ? $event_meta->location : ''); ?>">
    </p>
    <?php
}

// Save event details
// Minimal save hook: update only location without touching date/time/recurrence
add_action('save_post_church_event', function($post_id, $post, $update) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (get_post_type($post_id) !== 'church_event') return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (!isset($_POST['location'])) return;

    global $wpdb;
    $loc = sanitize_text_field($_POST['location']);

    // Only update location; do not modify other fields
    $wpdb->update(
        "{$wpdb->prefix}cem_event_meta",
        ['location' => $loc],
        ['event_id' => $post_id]
    );

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('CEM: location-only save handler updated location for post_id=' . $post_id);
    }
}, 5, 3);


// Note: API Controllers, Notifications, etc. are now initialized within the Plugin class
// to avoid duplicate instantiation

// Add autoloader
spl_autoload_register(function($class) {
    $prefix = 'ChurchEventsManager\\';
    $base_dir = CEM_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Initialize the plugin on init hook (after translations are loaded at priority 1)
add_action('init', function() {
    // Initialize core Plugin class which handles all component initialization
    if (class_exists('ChurchEventsManager\\Core\\Plugin')) {
        new \ChurchEventsManager\Core\Plugin();
    }
}, 5); // Priority 5 - after translations (priority 1) but before post type registration (priority 10)

// Force migration check on plugin activation/update
register_activation_hook(__FILE__, function() {
    // Force migration check by clearing the version
    delete_option('cem_db_version');
    new \ChurchEventsManager\Core\Migrations();
});

// Ensure core plugin bootstrap is initialized (merged with main initialization above)

// Register query var for occurrence and conditional rewrite rules
add_filter('query_vars', function($vars){
    $vars[] = 'occurrence';
    return $vars;
});

add_action('init', function(){
    // Recognize the %occurrence% tag from pretty URLs
    add_rewrite_tag('%occurrence%', '([0-9]{4}-[0-9]{2}-[0-9]{2})');

    $opts = get_option('church_events_options');
    if (is_array($opts) && !empty($opts['use_pretty_occurrence_urls'])) {
        // /event/<slug>/<YYYY-MM-DD>/
        add_rewrite_rule(
            '^event/([^/]+)/([0-9]{4}-[0-9]{2}-[0-9]{2})/?$',
            'index.php?post_type=church_event&name=$matches[1]&occurrence=$matches[2]',
            'top'
        );
    }
});

// Flush rewrite rules when the pretty URL setting changes
add_action('update_option_church_events_options', function($old_value, $value){
    $old = (is_array($old_value) && !empty($old_value['use_pretty_occurrence_urls']));
    $new = (is_array($value) && !empty($value['use_pretty_occurrence_urls']));
    if ($old !== $new) {
        flush_rewrite_rules();
    }
}, 10, 2);

// Helper: fetch event meta row by event ID
if (!function_exists('church_events_get_meta')) {
    function church_events_get_meta($event_id) {
        global $wpdb;
        if (!$event_id) { return null; }
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cem_event_meta WHERE event_id = %d",
            intval($event_id)
        ));
    }
}

// Helper: occurrence URL builder, respects pretty URL setting
if (!function_exists('church_events_use_pretty_occurrence_urls')) {
    function church_events_use_pretty_occurrence_urls() {
        $opts = get_option('church_events_options');
        return is_array($opts) && !empty($opts['use_pretty_occurrence_urls']);
    }
}

if (!function_exists('church_events_get_occurrence_link')) {
    function church_events_get_occurrence_link($event_id, $date_str) {
        $base = get_permalink($event_id);
        if (!$date_str) { return $base; }

        if (church_events_use_pretty_occurrence_urls()) {
            $slug = get_post_field('post_name', $event_id);
            $date = preg_replace('/[^0-9\-]/', '', $date_str);
            if (strlen($date) > 10) { $date = substr($date, 0, 10); }
            $path = sprintf('event/%s/%s/', $slug, $date);
            return home_url('/' . $path);
        }

        return add_query_arg('occurrence', rawurlencode($date_str), $base);
    }
}

// One-time rewrite flush to ensure pretty occurrence URLs take effect after code updates
add_action('init', function(){
    $opts = get_option('church_events_options');
    $need_pretty = (is_array($opts) && !empty($opts['use_pretty_occurrence_urls']));
    $installed = get_option('cem_pretty_rules_initialized');
    // Bump the initialization marker so sites flush once after rule changes
    if ($need_pretty && $installed !== '2') {
        flush_rewrite_rules();
        update_option('cem_pretty_rules_initialized', '2');
    }
});

// Reorganize admin menu: add Locations under Events (remove duplicate Categories and move Shortcodes into Settings)
add_action('admin_menu', function() {
    // Parent slug is CPT list page
    $parent_slug = 'edit.php?post_type=church_event';

    // Locations submenu (simple locations list)
    add_submenu_page(
        $parent_slug,
        __('Locations', 'church-events-manager'),
        __('Locations', 'church-events-manager'),
        'edit_posts',
        'church-events-locations',
        function() {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('Locations', 'church-events-manager') . '</h1>';
            echo '<p class="description">' . esc_html__('This page lists locations detected from your events. Future versions will allow editing and merging locations.', 'church-events-manager') . '</p>';
            global $wpdb;
            $table = $wpdb->prefix . 'cem_event_meta';
            $locations = $wpdb->get_col("SELECT DISTINCT location FROM {$table} WHERE location IS NOT NULL AND location <> '' ORDER BY location ASC");
            if (empty($locations)) {
                echo '<p>' . esc_html__('No locations found yet.', 'church-events-manager') . '</p>';
            } else {
                echo '<table class="widefat fixed striped">';
                echo '<thead><tr><th>' . esc_html__('Location', 'church-events-manager') . '</th><th>' . esc_html__('Events count', 'church-events-manager') . '</th></tr></thead>';
                echo '<tbody>';
                foreach ($locations as $loc) {
                    $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE location = %s", $loc));
                    echo '<tr><td>' . esc_html($loc) . '</td><td>' . esc_html($count) . '</td></tr>';
                }
                echo '</tbody></table>';
            }
            echo '</div>';
        }
    );
});

// Adjust default events per page in list view
add_filter('cem_events_per_page', function($per_page) {
    // Set a compact default; can be overridden via theme/plugin
    return 8;
});

// Disable Block Editor (Gutenberg) for church_event to ensure metabox form POSTs
add_filter('use_block_editor_for_post_type', function($use, $post_type) {
    if ($post_type === 'church_event') {
        return false;
    }
    return $use;
}, 10, 2);

// Back-compat: if Gutenberg plugin is active, also disable for this CPT
add_filter('gutenberg_can_edit_post_type', function($can_edit, $post_type) {
    if ($post_type === 'church_event') {
        return false;
    }
    return $can_edit;
}, 10, 2);