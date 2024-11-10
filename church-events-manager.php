<?php
/**
 * Plugin Name: Church Events Manager
 * Description: A simple WordPress plugin to manage church events
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: church-events-manager
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('CEM_VERSION', '1.0.0');
define('CEM_PLUGIN_FILE', __FILE__);
define('CEM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CEM_PLUGIN_URL', plugin_dir_url(__FILE__));

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

// Register the Events post type
add_action('init', function() {
    register_post_type('church_event', [
        'labels' => [
            'name' => __('Events', 'church-events-manager'),
            'singular_name' => __('Event', 'church-events-manager'),
            'add_new' => __('Add New', 'church-events-manager'),
            'add_new_item' => __('Add New Event', 'church-events-manager'),
            'edit_item' => __('Edit Event', 'church-events-manager'),
            'new_item' => __('New Event', 'church-events-manager'),
            'view_item' => __('View Event', 'church-events-manager'),
            'search_items' => __('Search Events', 'church-events-manager'),
            'not_found' => __('No events found', 'church-events-manager'),
            'not_found_in_trash' => __('No events found in Trash', 'church-events-manager'),
            'menu_name' => __('Events', 'church-events-manager')
        ],
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_position' => 20,
        'menu_icon' => 'dashicons-calendar-alt',
        'supports' => ['title', 'editor', 'thumbnail'],
        'has_archive' => true,
        'rewrite' => ['slug' => 'events', 'with_front' => false],
        'show_in_rest' => true,
        'publicly_queryable' => true,
        'taxonomies' => ['event_category']
    ]);

    // Register the Event Category taxonomy
    register_taxonomy('event_category', ['church_event'], [
        'labels' => [
            'name' => __('Event Categories', 'church-events-manager'),
            'singular_name' => __('Event Category', 'church-events-manager'),
            'search_items' => __('Search Categories', 'church-events-manager'),
            'all_items' => __('All Categories', 'church-events-manager'),
            'parent_item' => __('Parent Category', 'church-events-manager'),
            'parent_item_colon' => __('Parent Category:', 'church-events-manager'),
            'edit_item' => __('Edit Category', 'church-events-manager'),
            'update_item' => __('Update Category', 'church-events-manager'),
            'add_new_item' => __('Add New Category', 'church-events-manager'),
            'new_item_name' => __('New Category Name', 'church-events-manager'),
            'menu_name' => __('Categories', 'church-events-manager')
        ],
        'hierarchical' => true,
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => ['slug' => 'event-category'],
        'show_in_rest' => true,
        'capabilities' => [
            'manage_terms' => 'manage_categories',
            'edit_terms' => 'manage_categories',
            'delete_terms' => 'manage_categories',
            'assign_terms' => 'edit_posts'
        ]
    ]);
});

// Add meta box for event details
add_action('add_meta_boxes', function() {
    add_meta_box(
        'event_details',
        __('Event Details', 'church-events-manager'),
        'cem_render_event_details',
        'church_event',
        'normal',
        'high'
    );
});

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
add_action('save_post_church_event', function($post_id) {
    if (!isset($_POST['event_details_nonce']) || 
        !wp_verify_nonce($_POST['event_details_nonce'], 'save_event_details')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    global $wpdb;

    $data = [
        'event_id' => $post_id,
        'event_date' => sanitize_text_field($_POST['event_date']),
        'event_end_date' => !empty($_POST['event_end_date']) ? sanitize_text_field($_POST['event_end_date']) : null,
        'location' => sanitize_text_field($_POST['location'])
    ];

    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}cem_event_meta WHERE event_id = %d",
        $post_id
    ));

    if ($existing) {
        $wpdb->update(
            $wpdb->prefix . 'cem_event_meta',
            $data,
            ['event_id' => $post_id]
        );
    } else {
        $wpdb->insert(
            $wpdb->prefix . 'cem_event_meta',
            $data
        );
    }
});

// Initialize the API Controller
add_action('init', function() {
    new \ChurchEventsManager\API\EventsController();
});

// Initialize Notifications
add_action('init', function() {
    new \ChurchEventsManager\Notifications\NotificationManager();
});

// Initialize the Event Notification Manager
add_action('init', function() {
    if (class_exists('Church_App_Notifications_API')) {
        new \ChurchEventsManager\Notifications\EventNotificationManager();
    }
});

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