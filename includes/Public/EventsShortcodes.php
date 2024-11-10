<?php
namespace ChurchEventsManager\Public;

class EventsShortcodes {
    public function __construct() {
        add_shortcode('church_events', [$this, 'events_shortcode']);
        add_shortcode('church_event', [$this, 'single_event_shortcode']);
    }

    public function events_shortcode($atts) {
        $atts = shortcode_atts([
            'limit' => 5,
            'category' => '',
            'featured' => false,
            'view' => 'list', // list or calendar
        ], $atts, 'church_events');

        $events = $this->get_events($atts);
        
        ob_start();
        
        if ($atts['view'] === 'calendar') {
            $this->render_calendar_view($events);
        } else {
            $this->render_list_view($events);
        }
        
        return ob_get_clean();
    }

    public function single_event_shortcode($atts) {
        $atts = shortcode_atts([
            'id' => 0,
            'show_rsvp' => true
        ], $atts, 'church_event');

        if (!$atts['id']) {
            return '';
        }

        $event = $this->get_single_event($atts['id']);
        if (!$event) {
            return '';
        }

        ob_start();
        ?>
        <div class="church-event-single">
            <h2><?php echo esc_html($event->post_title); ?></h2>
            
            <div class="event-meta">
                <p class="event-datetime">
                    <?php 
                    echo esc_html(date_i18n(
                        get_option('date_format') . ' ' . get_option('time_format'), 
                        strtotime($event->event_date)
                    )); 
                    ?>
                </p>
                
                <?php if ($event->location): ?>
                    <p class="event-location"><?php echo esc_html($event->location); ?></p>
                <?php endif; ?>
            </div>

            <div class="event-content">
                <?php echo wp_kses_post($event->post_content); ?>
            </div>

            <?php if ($atts['show_rsvp'] && is_user_logged_in()): ?>
                <div class="event-rsvp">
                    <!-- RSVP form will be injected here via JavaScript -->
                    <div id="event-rsvp-form" data-event-id="<?php echo esc_attr($event->ID); ?>"></div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_events($atts) {
        global $wpdb;

        $query = "SELECT p.*, em.* 
                 FROM {$wpdb->posts} p
                 JOIN {$wpdb->prefix}cem_event_meta em ON p.ID = em.event_id
                 WHERE p.post_type = 'church_event'
                 AND p.post_status = 'publish'
                 AND em.event_date >= %s";

        $params = [current_time('mysql')];

        if ($atts['featured']) {
            $query .= " AND em.is_featured = 1";
        }

        if (!empty($atts['category'])) {
            $query .= " AND p.ID IN (
                SELECT object_id FROM {$wpdb->term_relationships} tr
                JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                WHERE t.slug = %s
            )";
            $params[] = $atts['category'];
        }

        $query .= " ORDER BY em.event_date ASC LIMIT %d";
        $params[] = $atts['limit'];

        return $wpdb->get_results($wpdb->prepare($query, $params));
    }

    private function get_single_event($event_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT p.*, em.* 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->prefix}cem_event_meta em ON p.ID = em.event_id
            WHERE p.ID = %d
            AND p.post_status = 'publish'",
            $event_id
        ));
    }

    private function render_list_view($events) {
        if (!$events) {
            echo '<p>' . __('No upcoming events.', 'church-events-manager') . '</p>';
            return;
        }
        ?>
        <div class="church-events-list">
            <?php foreach ($events as $event): ?>
                <div class="event-item">
                    <h3><a href="<?php echo get_permalink($event->ID); ?>"><?php echo esc_html($event->post_title); ?></a></h3>
                    <div class="event-meta">
                        <span class="event-date">
                            <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($event->event_date))); ?>
                        </span>
                        <span class="event-time">
                            <?php echo esc_html(date_i18n(get_option('time_format'), strtotime($event->event_date))); ?>
                        </span>
                        <?php if ($event->location): ?>
                            <span class="event-location"><?php echo esc_html($event->location); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="event-excerpt">
                        <?php echo wp_trim_words($event->post_content, 20); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function render_calendar_view($events) {
        // Calendar view implementation will be added here
        // This will require additional JavaScript for a proper calendar interface
        echo '<div class="church-events-calendar">';
        echo '<p>' . __('Calendar view coming soon.', 'church-events-manager') . '</p>';
        echo '</div>';
    }
} 