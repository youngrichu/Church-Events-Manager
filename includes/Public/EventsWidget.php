<?php
namespace ChurchEventsManager\Public;

class EventsWidget extends \WP_Widget {
    public function __construct() {
        parent::__construct(
            'church_events_widget',
            __('Church Events Widget', 'church-events-manager'),
            ['description' => __('Display upcoming church events', 'church-events-manager')]
        );

        // Add scripts and styles for the widget
        add_action('wp_enqueue_scripts', [$this, 'enqueue_widget_assets']);
    }

    public function enqueue_widget_assets() {
        wp_enqueue_style(
            'church-events-widget', 
            CEM_PLUGIN_URL . 'assets/css/widget.css',
            [],
            CEM_VERSION
        );

        wp_enqueue_script(
            'church-events-widget',
            CEM_PLUGIN_URL . 'assets/js/widget.js',
            ['jquery'],
            CEM_VERSION,
            true
        );
    }

    public function widget($args, $instance) {
        echo $args['before_widget'];
        
        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }

        $events = $this->get_upcoming_events($instance['number']);
        
        if ($events) {
            echo '<div class="church-events-widget-container">';
            echo '<div class="church-events-list">';
            foreach ($events as $event) {
                $date = date_i18n(get_option('date_format'), strtotime($event->event_date));
                $time = date_i18n(get_option('time_format'), strtotime($event->event_date));
                $end_time = $event->event_end_date ? date_i18n(get_option('time_format'), strtotime($event->event_end_date)) : '';
                
                echo '<div class="event-item" data-event-id="' . esc_attr($event->ID) . '">';
                echo '<div class="event-summary">';
                echo '<h4>' . esc_html($event->post_title) . '</h4>';
                echo '<div class="event-meta">';
                echo '<span class="event-date">' . esc_html($date) . '</span>';
                echo '<span class="event-time">' . esc_html($time) . ($end_time ? ' - ' . esc_html($end_time) : '') . '</span>';
                echo '</div>';
                echo '</div>';
                
                // Hover detail card
                echo '<div class="event-detail-card">';
                echo '<div class="event-card-content">';
                echo '<h4>' . esc_html($event->post_title) . '</h4>';
                echo '<div class="event-datetime">';
                echo '<i class="dashicons dashicons-calendar-alt"></i>';
                echo '<span>' . esc_html($date) . '</span>';
                echo '<span class="event-time">' . esc_html($time) . ($end_time ? ' - ' . esc_html($end_time) : '') . '</span>';
                echo '</div>';
                
                if (!empty($event->location)) {
                    echo '<div class="event-location">';
                    echo '<i class="dashicons dashicons-location"></i>';
                    echo '<span>' . esc_html($event->location) . '</span>';
                    echo '</div>';
                }
                
                if (!empty($event->post_content)) {
                    echo '<div class="event-description">';
                    echo wp_trim_words($event->post_content, 20);
                    echo '</div>';
                }
                
                echo '<a href="' . get_permalink($event->ID) . '" class="event-link">' . 
                     __('View Details', 'church-events-manager') . '</a>';
                echo '</div>'; // .event-card-content
                echo '</div>'; // .event-detail-card
                echo '</div>'; // .event-item
            }
            echo '</div>'; // .church-events-list

            if (!empty($instance['show_all_link'])) {
                $events_page = get_post_type_archive_link('church_event');
                echo '<p class="all-events-link"><a href="' . esc_url($events_page) . '">' . 
                     __('View All Events', 'church-events-manager') . '</a></p>';
            }
            echo '</div>'; // .church-events-widget-container
        } else {
            echo '<p>' . __('No upcoming events.', 'church-events-manager') . '</p>';
        }

        echo $args['after_widget'];
    }

    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('Upcoming Events', 'church-events-manager');
        $number = !empty($instance['number']) ? $instance['number'] : 5;
        $show_all_link = isset($instance['show_all_link']) ? (bool) $instance['show_all_link'] : true;
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?php esc_html_e('Title:', 'church-events-manager'); ?>
            </label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
                name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" 
                value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('number')); ?>">
                <?php esc_html_e('Number of events to show:', 'church-events-manager'); ?>
            </label>
            <input class="tiny-text" id="<?php echo esc_attr($this->get_field_id('number')); ?>" 
                name="<?php echo esc_attr($this->get_field_name('number')); ?>" type="number" 
                step="1" min="1" value="<?php echo esc_attr($number); ?>" size="3">
        </p>
        <p>
            <input type="checkbox" class="checkbox" id="<?php echo esc_attr($this->get_field_id('show_all_link')); ?>" 
                name="<?php echo esc_attr($this->get_field_name('show_all_link')); ?>"
                <?php checked($show_all_link); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('show_all_link')); ?>">
                <?php esc_html_e('Show "View All Events" link', 'church-events-manager'); ?>
            </label>
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = [];
        $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
        $instance['number'] = (!empty($new_instance['number'])) ? (int) $new_instance['number'] : 5;
        $instance['show_all_link'] = isset($new_instance['show_all_link']) ? (bool) $new_instance['show_all_link'] : false;
        return $instance;
    }

    private function get_upcoming_events($limit) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, em.* 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->prefix}cem_event_meta em ON p.ID = em.event_id
            WHERE p.post_type = 'church_event'
            AND p.post_status = 'publish'
            AND em.event_date >= %s
            ORDER BY em.event_date ASC
            LIMIT %d",
            current_time('mysql'),
            $limit
        ));
    }
} 