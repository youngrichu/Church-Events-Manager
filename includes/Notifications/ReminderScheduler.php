<?php
namespace ChurchEventsManager\Notifications;

use ChurchEventsManager\Events\RecurrenceExpander;

// Explicitly import WordPress globals for linters and clarity
// use function add_action;
// use function register_deactivation_hook;
// use function wp_next_scheduled;
// use function wp_schedule_event;
// use function wp_unschedule_event;
// use function current_time;
// use function get_option;
// use function get_post_meta;
// use function update_post_meta;
// use function get_users;
// use function wp_date;
// use function __;
// use function sprintf;
// use function function_exists;
// use const HOUR_IN_SECONDS;

class ReminderScheduler {
    public function __construct() {
        // Ensure cron is scheduled and hook the runner
        \add_action('init', [$this, 'schedule_cron']);
        \add_action('cem_send_event_reminders', [$this, 'send_event_reminders']);

        // Unschedule on deactivation
        if (defined('CEM_PLUGIN_FILE')) {
            \register_deactivation_hook(CEM_PLUGIN_FILE, [$this, 'unschedule_cron']);
        }
    }

    public function schedule_cron() {
        // Guard for environments where WP Cron functions are not available to static analyzers
        if (!\function_exists('wp_next_scheduled') || !\function_exists('wp_schedule_event')) {
            return;
        }
        $next = \call_user_func('wp_next_scheduled', 'cem_send_event_reminders');
        if (!$next) {
            // Start slightly in the future to avoid race with init
            \call_user_func('wp_schedule_event', \time() + 60, 'hourly', 'cem_send_event_reminders');
        }
    }

    public function unschedule_cron() {
        // Guard for environments where WP Cron functions are not available to static analyzers
        if (!\function_exists('wp_next_scheduled') || !\function_exists('wp_unschedule_event')) {
            return;
        }
        $timestamp = \call_user_func('wp_next_scheduled', 'cem_send_event_reminders');
        if ($timestamp) {
            \call_user_func('wp_unschedule_event', $timestamp, 'cem_send_event_reminders');
        }
    }

    public function send_event_reminders() {
        // Respect settings: only send if reminders are enabled
        $options = \function_exists('get_option') ? \get_option('church_events_options', []) : [];
        $types = isset($options['notification_types']) ? (array) $options['notification_types'] : [];
        if (!\in_array('reminder', $types, true)) {
            return;
        }

        $reminder_hours = isset($options['reminder_time']) ? max(1, (int) $options['reminder_time']) : 24;
        // Fallback for environments where HOUR_IN_SECONDS may not be defined during static analysis
        $hourInSeconds = \defined('HOUR_IN_SECONDS') ? \constant('HOUR_IN_SECONDS') : 3600;
        $lead_seconds = $reminder_hours * $hourInSeconds;

        // Fallback to time() when current_time is unavailable to static analyzers
        $now_ts = \function_exists('current_time') ? \current_time('timestamp') : \time();
        $window_start = \date('Y-m-d H:i:s', $now_ts + $lead_seconds);
        // Use a one-hour window to align with hourly cron
        $window_end = \date('Y-m-d H:i:s', $now_ts + $lead_seconds + $hourInSeconds);

        if (\defined('WP_DEBUG') && \constant('WP_DEBUG')) {
            \error_log('CEM: Reminder window start=' . $window_start . ' end=' . $window_end . ' lead_hours=' . $reminder_hours);
        }

        global $wpdb;

        // Fetch events that could have occurrences in the target window
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, em.* 
             FROM {$wpdb->posts} p
             JOIN {$wpdb->prefix}cem_event_meta em ON p.ID = em.event_id
             WHERE p.post_type = 'church_event'
               AND p.post_status = 'publish'
               AND (
                 (em.is_recurring = 0 AND em.event_date BETWEEN %s AND %s)
                 OR (em.is_recurring = 1 AND em.event_date <= %s AND (em.recurring_end_date IS NULL OR em.recurring_end_date >= %s))
               )
             ORDER BY em.event_date ASC",
            $window_start,
            $window_end,
            $window_end,
            $window_start
        ));

        if (\defined('WP_DEBUG') && \constant('WP_DEBUG')) {
            \error_log('CEM: Reminder candidate events count=' . (is_array($events) ? count($events) : 0));
        }

        if (empty($events)) {
            return;
        }

        foreach ($events as $event) {
            $occurrences = [];
            $is_recurring = isset($event->is_recurring) ? (int) $event->is_recurring : 0;

            if ($is_recurring === 1) {
                try {
                    $occurrences = RecurrenceExpander::expandInRange($event, $window_start, $window_end);
                } catch (\Throwable $e) {
                    // Skip problematic events
                    continue;
                }
            } else {
                // Non-recurring event within window
                if (\strtotime($event->event_date) >= \strtotime($window_start) && \strtotime($event->event_date) <= \strtotime($window_end)) {
                    $occurrences[] = $event;
                }
            }

            if (empty($occurrences)) {
                continue;
            }

            foreach ($occurrences as $occ) {
                $occ_date = isset($occ->event_date) ? $occ->event_date : null;
                if (!$occ_date) { continue; }

                if (\defined('WP_DEBUG') && \constant('WP_DEBUG')) {
                    \error_log('CEM: Reminder occurrence for event ID=' . $event->ID . ' at ' . $occ_date);
                }

                // Deduplicate per occurrence using a meta key based on occurrence datetime
                $meta_key = '_cem_reminder_' . \md5($occ_date);
                if (\get_post_meta($event->ID, $meta_key, true)) {
                    if (\defined('WP_DEBUG') && \constant('WP_DEBUG')) { \error_log('CEM: Reminder already sent meta=' . $meta_key . ' event ID=' . $event->ID); }
                    continue; // already sent
                }

                // Format readable date/time with safe fallbacks for static analyzers
                $date_format = \function_exists('get_option') ? \get_option('date_format') : 'Y-m-d';
                $time_format = \function_exists('get_option') ? \get_option('time_format') : 'H:i';
                $formatted_date = \function_exists('wp_date')
                    ? \wp_date($date_format . ' ' . $time_format, \strtotime($occ_date))
                    : \date($date_format . ' ' . $time_format, \strtotime($occ_date));

                // Build payload for Church App Notifications plugin
                $location = !empty($event->location) ? $event->location : '';
                $notification_data = [
                    'title' => \function_exists('__') ? \__('Upcoming Event Reminder', 'church-events-manager') : 'Upcoming Event Reminder',
                    'body' => $location
                        ? \sprintf(\function_exists('__') ? \__('"%s" at %s • %s', 'church-events-manager') : '"%s" at %s • %s', $event->post_title, $location, $formatted_date)
                        : \sprintf(\function_exists('__') ? \__('"%s" • %s', 'church-events-manager') : '"%s" • %s', $event->post_title, $formatted_date),
                    'data' => [
                        'type' => 'reminder',
                        'event_id' => $event->ID,
                        'event_date' => $occ_date,
                        'location' => $location,
                    ],
                ];

                // Send via Church App Notifications if available
                if (\function_exists('can_send_notification')) {
                    $users = \get_users(['fields' => 'ID']);
                    foreach ($users as $user_id) {
                        // Delegate to external plugin
                        \can_send_notification($user_id, $notification_data);
                    }

                    // Mark as sent
                    \update_post_meta($event->ID, $meta_key, 1);
                    if (\defined('WP_DEBUG') && \constant('WP_DEBUG')) { \error_log('CEM: Reminder sent via can_send_notification for event ID=' . $event->ID); }
                } else if (\class_exists('Church_App_Notifications_Expo_Push')) {
                    // Fallback: insert into app_notifications and push via Expo
                    $row = [
                        'user_id' => 0,
                        'title' => $notification_data['title'],
                        'body' => $notification_data['body'],
                        'type' => 'reminder',
                        'reference_id' => $event->ID,
                        'reference_type' => 'church_event',
                        'reference_url' => "dubaidebremewi://events/{$event->ID}",
                        'image_url' => \get_the_post_thumbnail_url($event->ID, 'full'),
                        'created_at' => \current_time('mysql')
                    ];
                    $table_name = $wpdb->prefix . 'app_notifications';
                    $result = $wpdb->insert($table_name, $row, ['%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']);
                    if ($result !== false) {
                        $notification_id = $wpdb->insert_id;
                        $expo = new \Church_App_Notifications_Expo_Push();
                        $expo->send_notification($notification_id);
                        \update_post_meta($event->ID, $meta_key, 1);
                        if (\defined('WP_DEBUG') && \constant('WP_DEBUG')) { \error_log('CEM: Reminder sent via Expo fallback for event ID=' . $event->ID . ' notifID=' . $notification_id); }
                    } else {
                        if (\defined('WP_DEBUG') && \constant('WP_DEBUG')) { \error_log('CEM: Reminder Expo insert failed: ' . $wpdb->last_error); }
                    }
                } else {
                    if (\defined('WP_DEBUG') && \constant('WP_DEBUG')) { \error_log('CEM: Reminder send skipped - no sender available'); }
                }
            }
        }
    }
}