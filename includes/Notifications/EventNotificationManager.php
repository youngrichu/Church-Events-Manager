<?php
namespace ChurchEventsManager\Notifications;

class EventNotificationManager {
    private $api;
    private $hooks;
    private $expo_push;

    public function __construct() {
        // Initialize notification classes
        $this->api = new \Church_App_Notifications_API();
        $this->hooks = new \Church_App_Notifications_Hooks();
        $this->expo_push = new \Church_App_Notifications_Expo_Push();

        // Hook into event publication
        add_action('transition_post_status', [$this, 'handle_event_status_change'], 10, 3);
    }

    public function handle_event_status_change($new_status, $old_status, $post) {
        try {
            // Only proceed if this is an event being published
            if ($post->post_type !== 'church_event' || $new_status !== 'publish') {
                return;
            }

            // Don't send notification for updates unless specifically requested
            if ($old_status === 'publish' && !get_post_meta($post->ID, '_notify_update', true)) {
                return;
            }

            // Check if notification was already sent recently (within 5 minutes)
            $recently_sent = get_post_meta($post->ID, '_event_notification_sent', true);
            $sent_time = get_post_meta($post->ID, '_event_notification_sent_time', true);
            
            if ($recently_sent && $sent_time && (time() - strtotime($sent_time) < 300)) {
                error_log('Skipping notification - already sent recently');
                return;
            }

            $this->send_event_notification($post);
            
            // Mark notification as sent with timestamp
            update_post_meta($post->ID, '_event_notification_sent', true);
            update_post_meta($post->ID, '_event_notification_sent_time', current_time('mysql'));

            // Clear the update notification flag
            delete_post_meta($post->ID, '_notify_update');
            
        } catch (\Exception $e) {
            error_log('Error in handle_event_status_change: ' . $e->getMessage());
        }
    }

    public function send_event_notification($event) {
        try {
            global $wpdb;

            // Get event meta data
            $event_meta = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}cem_event_meta WHERE event_id = %d",
                $event->ID
            ));

            // Format event date and location with fallbacks
            $event_date = '';
            $location = '';
            
            if ($event_meta) {
                if (!empty($event_meta->event_date)) {
                    $event_date = date_i18n(
                        get_option('date_format') . ' ' . get_option('time_format'),
                        strtotime($event_meta->event_date)
                    );
                }
                $location = !empty($event_meta->location) ? $event_meta->location : '';
            }

            // Prepare notification data
            $notification_data = [
                'user_id' => 0, // Send to all users
                'title' => sprintf(__('New Event: %s', 'church-events-manager'), $event->post_title),
                'body' => !empty($event_date) && !empty($location) 
                    ? sprintf(
                        __('New event scheduled for %s at %s', 'church-events-manager'),
                        $event_date,
                        $location
                    )
                    : wp_trim_words(wp_strip_all_tags($event->post_content), 20),
                'type' => 'event',
                'reference_id' => $event->ID,
                'reference_type' => 'church_event',
                'reference_url' => "dubaidebremewi://events/{$event->ID}",
                'image_url' => get_the_post_thumbnail_url($event->ID, 'full'),
                'created_at' => current_time('mysql')
            ];

            // Insert into notifications table
            $table_name = $wpdb->prefix . 'app_notifications';
            $result = $wpdb->insert(
                $table_name,
                $notification_data,
                [
                    '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s'
                ]
            );

            if ($result === false) {
                error_log('Failed to insert notification: ' . $wpdb->last_error);
                return;
            }

            $notification_id = $wpdb->insert_id;
            error_log('Created notification with ID: ' . $notification_id);

            // Send push notification using Expo
            $sent = $this->expo_push->send_notification($notification_id);
            error_log('Push notification sent: ' . ($sent ? 'true' : 'false'));

        } catch (\Exception $e) {
            error_log('Error in send_event_notification: ' . $e->getMessage());
        }
    }
}