<?php
namespace ChurchEventsManager\Notifications;

class NotificationManager {
    public function __construct() {
        // Hook into event publication
        add_action('transition_post_status', [$this, 'handle_event_publication'], 10, 3);
        
        // Register notification endpoint
        add_action('rest_api_init', [$this, 'register_notification_endpoints']);
    }

    public function register_notification_endpoints() {
        register_rest_route('church-events/v1', '/notifications/settings', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_notification_settings'],
                'permission_callback' => '__return_true'
            ]
        ]);
    }

    public function handle_event_publication($new_status, $old_status, $post) {
        // Only proceed if this is a church event being published
        if ($post->post_type !== 'church_event' || $new_status !== 'publish' || $old_status === 'publish') {
            return;
        }

        // Get event details
        $event_meta = $this->get_event_meta($post->ID);
        if (!$event_meta) {
            return;
        }

        // Send notification through Church App system
        if (function_exists('can_send_notification')) {
            $this->send_new_event_notification($post, $event_meta);
        }
    }

    private function send_new_event_notification($post, $event_meta) {
        // Format date for display
        $event_date = wp_date(
            get_option('date_format') . ' ' . get_option('time_format'), 
            strtotime($event_meta->event_date)
        );

        // Prepare notification data
        $notification_data = [
            'title' => __('New Event Added', 'church-events-manager'),
            'body' => sprintf(
                __('%s on %s', 'church-events-manager'),
                $post->post_title,
                $event_date
            ),
            'data' => [
                'type' => 'new_event',
                'event_id' => $post->ID,
                'event_date' => $event_meta->event_date,
                'location' => $event_meta->location
            ]
        ];

        // Send to all users
        $users = get_users(['fields' => 'ID']);
        foreach ($users as $user_id) {
            can_send_notification($user_id, $notification_data);
        }
    }

    public function get_notification_settings($request) {
        return rest_ensure_response([
            'new_events' => true,
            'reminders' => [
                'enabled' => true,
                'timing' => '24h' // 24 hours before event
            ],
            'updates' => true,
            'cancellations' => true
        ]);
    }

    private function get_event_meta($event_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cem_event_meta WHERE event_id = %d",
            $event_id
        ));
    }
} 