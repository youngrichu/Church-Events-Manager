<?php
namespace ChurchEventsManager\Notifications;

class EventNotificationManager {
    private $api;
    private $hooks;

    public function __construct() {
        // Initialize notification classes
        $this->api = new \Church_App_Notifications_API();
        $this->hooks = new \Church_App_Notifications_Hooks();

        // Hook into event publication
        add_action('transition_post_status', [$this, 'handle_event_status_change'], 10, 3);
    }

    public function handle_event_status_change($new_status, $old_status, $post) {
        // Only proceed if this is an event being published
        if ($post->post_type !== 'church_event' || $new_status !== 'publish' || $old_status === 'publish') {
            return;
        }

        // Check if notification was already sent
        if (get_post_meta($post->ID, '_event_notification_sent', true)) {
            return;
        }

        $this->send_event_notification($post);
        
        // Mark notification as sent
        update_post_meta($post->ID, '_event_notification_sent', true);
    }

    public function send_event_notification($event) {
        global $wpdb;

        // Get event meta data
        $event_meta = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cem_event_meta WHERE event_id = %d",
            $event->ID
        ));

        // Format event date
        $event_date = date_i18n(
            get_option('date_format') . ' ' . get_option('time_format'),
            strtotime($event_meta->event_date)
        );

        // Check if notification already exists
        $existing_notification = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}app_notifications 
            WHERE reference_type = 'church_event' 
            AND reference_id = %d 
            AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
            $event->ID
        ));

        if ($existing_notification) {
            return;
        }

        // Prepare notification data
        $notification_data = [
            'user_id' => 0, // Send to all users
            'title' => sprintf(__('New Event: %s', 'church-events-manager'), $event->post_title),
            'body' => sprintf(
                __('New event scheduled for %s at %s', 'church-events-manager'),
                $event_date,
                $event_meta->location
            ),
            'type' => 'event',
            'reference_id' => $event->ID,
            'reference_type' => 'church_event',
            'reference_url' => get_permalink($event->ID),
            'image_url' => get_the_post_thumbnail_url($event->ID, 'full'),
            'created_at' => current_time('mysql')
        ];

        // Insert into notifications table
        $table_name = $wpdb->prefix . 'app_notifications';
        $wpdb->insert(
            $table_name,
            $notification_data,
            [
                '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s'
            ]
        );

        // Send push notifications to all users
        $users = get_users();
        foreach ($users as $user) {
            $token = get_user_meta($user->ID, 'expo_push_token', true);
            if ($token) {
                $this->hooks->send_push_notification(
                    $token,
                    $notification_data['title'],
                    $notification_data['body'],
                    [
                        'type' => 'event',
                        'event_id' => $event->ID,
                        'url' => $notification_data['reference_url']
                    ]
                );
            }
        }

        // No need to call $this->api->send_notification() since we're already handling both DB insert and push notifications
    }
} 