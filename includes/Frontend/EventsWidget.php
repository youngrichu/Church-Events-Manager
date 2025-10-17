<?php
namespace ChurchEventsManager\Frontend;

use ChurchEventsManager\Events\RecurrenceExpander;

class EventsWidget extends \WP_Widget {
    public function __construct() {
        parent::__construct(
            'church_events_widget',
            __('Church Events', 'church-events-manager'),
            ['description' => __('Displays upcoming church events with recurrence expansion', 'church-events-manager')]
        );
    }

    public function widget($args, $instance) {
        echo $args['before_widget'];
        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }

        $limit = !empty($instance['limit']) ? intval($instance['limit']) : 5;
        $this->render_upcoming_events($limit);

        echo $args['after_widget'];
    }

    public function form($instance) {
        $title = isset($instance['title']) ? $instance['title'] : __('Upcoming Events', 'church-events-manager');
        $limit = isset($instance['limit']) ? intval($instance['limit']) : 5;
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php _e('Title:', 'church-events-manager'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('limit')); ?>"><?php _e('Number of events to show:', 'church-events-manager'); ?></label>
            <input class="tiny-text" id="<?php echo esc_attr($this->get_field_id('limit')); ?>" name="<?php echo esc_attr($this->get_field_name('limit')); ?>" type="number" step="1" min="1" value="<?php echo esc_attr($limit); ?>" size="3">
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = [];
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['limit'] = (!empty($new_instance['limit'])) ? intval($new_instance['limit']) : 5;
        return $instance;
    }

    private function render_upcoming_events($limit) {
        global $wpdb;
        $now = current_time('mysql');
        $horizon = date('Y-m-d H:i:s', strtotime('+1 year', strtotime($now)));
        
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, em.* FROM {$wpdb->posts} p
             JOIN {$wpdb->prefix}cem_event_meta em ON p.ID = em.event_id
             WHERE p.post_type = 'church_event' AND p.post_status = 'publish'
             AND (
                (em.is_recurring = 0 AND em.event_date >= %s)
                OR (em.is_recurring = 1 AND em.event_date <= %s AND (em.recurring_end_date IS NULL OR em.recurring_end_date >= %s))
             )
             ORDER BY em.event_date ASC",
            $now,
            $horizon,
            $now
        ));

        $occurrences = [];
        foreach ($events as $event) {
            if ((int)$event->is_recurring === 1) {
                foreach (RecurrenceExpander::expandInRange($event, $now, $horizon) as $occ) {
                    $occurrences[] = $occ;
                }
            } else {
                if (strtotime($event->event_date) >= strtotime($now)) {
                    $occurrences[] = $event;
                }
            }
        }

        usort($occurrences, function($a, $b) {
            return strtotime($a->event_date) <=> strtotime($b->event_date);
        });

        $occurrences = array_slice($occurrences, 0, $limit);

        echo '<ul class="church-events-widget-list">';
        foreach ($occurrences as $event) {
            echo '<li class="event">';
            echo '<a href="' . esc_url(\church_events_get_occurrence_link($event->ID, $event->event_date)) . '">' . esc_html($event->post_title) . '</a>';
            echo '<span class="event-date">' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($event->event_date))) . '</span>';
            echo '</li>';
        }
        echo '</ul>';
    }
}