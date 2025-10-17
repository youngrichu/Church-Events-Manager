<?php
namespace ChurchEventsManager\Core;

class Plugin {
    public function __construct() {
        // Register post type and taxonomies
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_taxonomies']);
        
        // Add capabilities on plugin activation
        register_activation_hook(CEM_PLUGIN_FILE, [$this, 'add_capabilities']);
        
        // Load other components
        $this->load_dependencies();

        // Ensure Events List page exists after WordPress initializes rewrite rules
        add_action('init', [$this, 'ensure_events_list_page']);
    }

    public function register_post_type() {
        $labels = [
            'name'               => __('Events', 'church-events-manager'),
            'singular_name'      => __('Event', 'church-events-manager'),
            'menu_name'          => __('Events', 'church-events-manager'),
            'add_new'           => __('Add New', 'church-events-manager'),
            'add_new_item'      => __('Add New Event', 'church-events-manager'),
            'edit_item'         => __('Edit Event', 'church-events-manager'),
            'new_item'          => __('New Event', 'church-events-manager'),
            'view_item'         => __('View Event', 'church-events-manager'),
            'search_items'      => __('Search Events', 'church-events-manager'),
            'not_found'         => __('No events found', 'church-events-manager'),
            'not_found_in_trash'=> __('No events found in Trash', 'church-events-manager'),
            'all_items'         => __('All Events', 'church-events-manager')
        ];

        $args = [
            'labels'              => $labels,
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_position'       => 20,
            'menu_icon'           => 'dashicons-calendar-alt',
            'show_in_admin_bar'   => true,
            'show_in_nav_menus'   => true,
            'can_export'          => true,
            'has_archive'         => true,
            'exclude_from_search' => false,
            'publicly_queryable'  => true,
            'rewrite'            => ['slug' => 'events'],
            'supports'           => ['title', 'editor', 'thumbnail', 'excerpt'],
            'show_in_rest'       => true,
            'capability_type'    => 'post',
            'capabilities'       => [
                'publish_posts'       => 'publish_posts',
                'edit_posts'         => 'edit_posts',
                'edit_others_posts'  => 'edit_others_posts',
                'delete_posts'       => 'delete_posts',
                'delete_others_posts'=> 'delete_others_posts',
                'read_private_posts' => 'read_private_posts',
                'edit_post'          => 'edit_post',
                'delete_post'        => 'delete_post',
                'read_post'          => 'read_post',
            ],
            'map_meta_cap'       => true,
        ];

        register_post_type('church_event', $args);
    }

    public function register_taxonomies() {
        $labels = [
            'name'              => __('Event Categories', 'church-events-manager'),
            'singular_name'     => __('Event Category', 'church-events-manager'),
            'search_items'      => __('Search Categories', 'church-events-manager'),
            'all_items'         => __('All Categories', 'church-events-manager'),
            'parent_item'       => __('Parent Category', 'church-events-manager'),
            'parent_item_colon' => __('Parent Category:', 'church-events-manager'),
            'edit_item'         => __('Edit Category', 'church-events-manager'),
            'update_item'       => __('Update Category', 'church-events-manager'),
            'add_new_item'      => __('Add New Category', 'church-events-manager'),
            'new_item_name'     => __('New Category Name', 'church-events-manager'),
            'menu_name'         => __('Categories', 'church-events-manager'),
        ];

        register_taxonomy('event_category', ['church_event'], [
            'labels'            => $labels,
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'event-category'],
            'show_in_rest'      => true,
            'capabilities'      => [
                'manage_terms'  => 'manage_categories',
                'edit_terms'    => 'manage_categories',
                'delete_terms'  => 'manage_categories',
                'assign_terms'  => 'edit_posts'
            ]
        ]);
    }

    public function add_capabilities() {
        // Get the administrator role
        $admin = get_role('administrator');
        
        if ($admin) {
            // Add post type capabilities
            $admin->add_cap('publish_posts');
            $admin->add_cap('edit_posts');
            $admin->add_cap('edit_others_posts');
            $admin->add_cap('delete_posts');
            $admin->add_cap('delete_others_posts');
            $admin->add_cap('read_private_posts');
            $admin->add_cap('edit_post');
            $admin->add_cap('delete_post');
            $admin->add_cap('read_post');
            
            // Add taxonomy capabilities
            $admin->add_cap('manage_categories');
        }
    }

    private function load_dependencies() {
        // Load admin functionality
        if (is_admin()) {
            new \ChurchEventsManager\Admin\EventsAdmin();
            new \ChurchEventsManager\Admin\SettingsPage();
            new \ChurchEventsManager\Export\ExportManager();
            new \ChurchEventsManager\Import\ImportManager();
        }
        
        // Load REST API endpoints
        new \ChurchEventsManager\API\EventsController();
        
        // Load RSVP functionality
        new \ChurchEventsManager\RSVP\RSVPManager();
        
        // Load notifications
        new \ChurchEventsManager\Notifications\NotificationManager();
        
        // Register widget
        add_action('widgets_init', function() {
            register_widget('\\ChurchEventsManager\\Frontend\\EventsWidget');
        });
        
        // Initialize shortcodes
        new \ChurchEventsManager\Frontend\EventsShortcodes();

        // Initialize AJAX handlers for calendar interactions
        new \ChurchEventsManager\Ajax\CalendarHandler();

        // Initialize template loader for archive/single and page templates
        new \ChurchEventsManager\Templates\TemplateLoader();
        
        // Initialize cache manager
        $cache_manager = new \ChurchEventsManager\Cache\EventsCache();
        $GLOBALS['church_events_cache'] = $cache_manager;
        
        // Initialize search functionality
        new \ChurchEventsManager\Search\SearchHandler();
        
        // Initialize recurring events
        new \ChurchEventsManager\Events\RecurringEvents();
        
        // Initialize internationalization
        new \ChurchEventsManager\I18n\Translator();
        
        // Run database migrations if needed
        new \ChurchEventsManager\Core\Migrations();
    }

    public function ensure_events_list_page() {
        // Create the front-end Events List page if missing and assign template
        $page_id = (int) get_option('cem_events_page_id');
        $page = $page_id ? get_post($page_id) : null;

        if (!$page || $page->post_status === 'trash') {
            $existing = get_page_by_path('events-list');
            if ($existing) {
                $page_id = $existing->ID;
                if ($existing->post_status !== 'publish') {
                    wp_update_post(['ID' => $page_id, 'post_status' => 'publish']);
                }
            } else {
                $page_id = wp_insert_post([
                    'post_title'   => __('Events', 'church-events-manager'),
                    'post_name'    => 'events-list',
                    'post_type'    => 'page',
                    'post_status'  => 'publish',
                    'post_content' => ''
                ]);
            }
            if ($page_id && !is_wp_error($page_id)) {
                update_post_meta($page_id, '_wp_page_template', 'events-list.php');
                update_option('cem_events_page_id', $page_id);
            }
        } else {
            // Ensure the template is correct
            $tpl = get_post_meta($page_id, '_wp_page_template', true);
            if ($tpl !== 'events-list.php') {
                update_post_meta($page_id, '_wp_page_template', 'events-list.php');
            }
        }
    }
}