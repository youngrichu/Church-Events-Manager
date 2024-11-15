<?php
namespace ChurchEventsManager\Ajax;

class CalendarHandler {
    public function __construct() {
        add_action('wp_ajax_get_calendar_month', [$this, 'get_calendar_month']);
        add_action('wp_ajax_nopriv_get_calendar_month', [$this, 'get_calendar_month']);
        
        add_action('wp_ajax_get_event_details', [$this, 'get_event_details']);
        add_action('wp_ajax_nopriv_get_event_details', [$this, 'get_event_details']);
        
        add_action('wp_ajax_get_day_events', [$this, 'get_day_events']);
        add_action('wp_ajax_nopriv_get_day_events', [$this, 'get_day_events']);
    }

    public function get_calendar_month() {
        check_ajax_referer('calendar_nonce', 'nonce');

        $month = isset($_POST['month']) ? sanitize_text_field($_POST['month']) : date('Y-m');
        $calendar = new \ChurchEventsManager\Public\Calendar($month . '-01');
        
        wp_send_json_success([
            'html' => $calendar->render(),
            'month' => $month
        ]);
    }

    public function get_event_details() {
        check_ajax_referer('calendar_nonce', 'nonce');

        $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        if (!$event_id) {
            wp_send_json_error(['message' => __('Invalid event ID', 'church-events-manager')]);
        }

        $html = \ChurchEventsManager\Public\Calendar::get_event_details($event_id);
        wp_send_json_success(['html' => $html]);
    }

    public function get_day_events() {
        check_ajax_referer('calendar_nonce', 'nonce');

        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        if (!$date) {
            wp_send_json_error(['message' => __('Invalid date', 'church-events-manager')]);
        }

        global $wpdb;
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, em.* 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->prefix}cem_event_meta em ON p.ID = em.event_id
            WHERE p.post_type = 'church_event'
            AND p.post_status = 'publish'
            AND DATE(em.event_date) = %s
            ORDER BY em.event_date ASC",
            $date
        ));

        ob_start();
        ?>
        <div class="calendar-day-events-modal">
            <div class="modal-header">
                <h3><?php echo date_i18n(get_option('date_format'), strtotime($date)); ?></h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-content">
                <?php if ($events): ?>
                    <div class="events-list">
                        <?php foreach ($events as $event): ?>
                            <div class="event-item">
                                <h4><?php echo esc_html($event->post_title); ?></h4>
                                <div class="event-meta">
                                    <span class="event-time">
                                        <?php echo date_i18n(get_option('time_format'), strtotime($event->event_date)); ?>
                                    </span>
                                    <?php if ($event->location): ?>
                                        <span class="event-location">
                                            <?php echo esc_html($event->location); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="event-description">
                                    <?php echo wp_trim_words($event->post_content, 20); ?>
                                </div>
                                <a href="<?php echo get_permalink($event->ID); ?>" class="event-link">
                                    <?php _e('View Details', 'church-events-manager'); ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p><?php _e('No events scheduled for this day.', 'church-events-manager'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        $html = ob_get_clean();
        
        wp_send_json_success(['html' => $html]);
    }
} 