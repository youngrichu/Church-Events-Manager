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
            return;
        }
        // Fallback: use Expo push integration if available
        if (class_exists('Church_App_Notifications_Expo_Push')) {
            $this->send_new_event_notification_via_expo($post, $event_meta);
            return;
        }

        // Optional debug log when no sender is available
        if (\defined('WP_DEBUG') && \constant('WP_DEBUG')) {
            error_log('CEM: NotificationManager no sender available (can_send_notification missing and Expo classes not found)');
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

    // Fallback sender using Expo push notifications via Church App Notifications tables/classes
    private function send_new_event_notification_via_expo($post, $event_meta) {
        try {
            global $wpdb;

            // Format event date and location
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

            // Prepare row for app_notifications table
            $notification_row = [
                'user_id' => 0, // Send to all users
                'title' => sprintf(__('New Event: %s', 'church-events-manager'), $post->post_title),
                'body' => !empty($event_date) && !empty($location) 
                    ? sprintf(
                        __('New event scheduled for %s at %s', 'church-events-manager'),
                        $event_date,
                        $location
                    )
                    : wp_trim_words(wp_strip_all_tags($post->post_content), 20),
                'type' => 'event',
                'reference_id' => $post->ID,
                'reference_type' => 'church_event',
                'reference_url' => "dubaidebremewi://events/{$post->ID}",
                'image_url' => get_the_post_thumbnail_url($post->ID, 'full'),
                'created_at' => current_time('mysql')
            ];

            $table_name = $wpdb->prefix . 'app_notifications';
            $result = $wpdb->insert(
                $table_name,
                $notification_row,
                ['%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
            );

            if ($result === false) {
                if (\defined('WP_DEBUG') && \constant('WP_DEBUG')) {
                    error_log('CEM: Failed to insert notification (Expo fallback): ' . $wpdb->last_error);
                }
                return;
            }

            $notification_id = $wpdb->insert_id;
            if (\defined('WP_DEBUG') && \constant('WP_DEBUG')) {
                error_log('CEM: Created notification (Expo fallback) ID=' . $notification_id);
            }

            // Send push via Expo
            if (class_exists('Church_App_Notifications_Expo_Push')) {
                $expo = new \Church_App_Notifications_Expo_Push();
                $sent = $expo->send_notification($notification_id);
                if (\defined('WP_DEBUG') && \constant('WP_DEBUG')) {
                    error_log('CEM: Expo push sent (fallback) result=' . ($sent ? 'true' : 'false'));
                }
            }
        } catch (\Exception $e) {
            if (\defined('WP_DEBUG') && \constant('WP_DEBUG')) {
                error_log('CEM: Error in send_new_event_notification_via_expo: ' . $e->getMessage());
            }
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