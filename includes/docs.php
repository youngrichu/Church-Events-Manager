<?php
/**
 * Church Events Manager Documentation
 *
 * This file contains inline documentation and code examples for the plugin.
 * 
 * @package ChurchEventsManager
 */

/**
 * Example: Getting Events
 *
 * @param array $args Query arguments
 * @return array Array of events
 */
function cem_example_get_events($args = []) {
    global $church_events_cache;
    return $church_events_cache->get_events($args);
}

/**
 * Example: Creating an RSVP
 *
 * @param int $event_id Event ID
 * @param int $user_id User ID
 * @return bool|WP_Error True on success, WP_Error on failure
 */
function cem_example_create_rsvp($event_id, $user_id) {
    $rsvp_manager = new \ChurchEventsManager\RSVP\RSVPManager();
    return $rsvp_manager->create_rsvp($event_id, $user_id);
}

/**
 * Example: Sending Notifications
 *
 * @param int $user_id User ID
 * @param array $data Notification data
 */
function cem_example_send_notification($user_id, $data) {
    if (function_exists('can_send_notification')) {
        can_send_notification($user_id, [
            'title' => $data['title'],
            'body' => $data['body'],
            'data' => $data['extra']
        ]);
    }
}

/**
 * Example: Using Filters
 */
add_filter('cem_event_title', function($title, $event_id) {
    // Modify event title
    return $title;
}, 10, 2);

/**
 * Example: Using Actions
 */
add_action('cem_after_event_created', function($event_id) {
    // Do something after event is created
});

/**
 * Available Hooks
 *
 * Actions:
 * - cem_after_event_created
 * - cem_after_event_updated
 * - cem_after_rsvp_created
 * - cem_after_rsvp_updated
 * - cem_before_notification_sent
 *
 * Filters:
 * - cem_event_title
 * - cem_event_content
 * - cem_notification_data
 * - cem_rsvp_status
 * - cem_cache_duration
 */

/**
 * Example: Custom Event Template
 */
function cem_example_custom_template($event) {
    ?>
    <div class="custom-event-template">
        <h2><?php echo esc_html($event->post_title); ?></h2>
        <div class="event-meta">
            <time><?php echo esc_html($event->event_date); ?></time>
            <span class="location"><?php echo esc_html($event->location); ?></span>
        </div>
        <div class="event-content">
            <?php echo wp_kses_post($event->post_content); ?>
        </div>
    </div>
    <?php
}

/**
 * Example: Custom API Endpoint
 */
function cem_example_custom_endpoint() {
    register_rest_route('church-events/v1', '/custom-endpoint', [
        'methods' => 'GET',
        'callback' => function($request) {
            // Handle request
            return rest_ensure_response(['success' => true]);
        },
        'permission_callback' => function() {
            return current_user_can('read');
        }
    ]);
} 