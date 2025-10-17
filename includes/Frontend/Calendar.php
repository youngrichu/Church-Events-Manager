<?php
namespace ChurchEventsManager\Frontend;

use ChurchEventsManager\Events\RecurrenceExpander;

class Calendar {
    private $events = [];
    private $current_date;
    private $current_month;
    private $current_year;

    public function __construct($date = null) {
        $this->current_date = $date ? new \DateTime($date) : new \DateTime();
        $this->current_month = $this->current_date->format('m');
        $this->current_year = $this->current_date->format('Y');
    }

    public function load_events() {
        global $wpdb;
        
        $start_date = $this->current_date->format('Y-m-01 00:00:00');
        $end_date = $this->current_date->format('Y-m-t 23:59:59');

        // Fetch all events that may have occurrences in the month:
        // - non-recurring events within range
        // - recurring parent events with start before end and optional end after start
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
            $start_date,
            $end_date,
            $end_date,
            $start_date
        ));

        // Expand recurring occurrences into day buckets
        foreach ($events as $event) {
            if ((int)$event->is_recurring === 1) {
                $occurrences = RecurrenceExpander::expandInRange($event, $start_date, $end_date);
                foreach ($occurrences as $occ) {
                    $date = date('Y-m-d', strtotime($occ->event_date));
                    if (!isset($this->events[$date])) { $this->events[$date] = []; }
                    $this->events[$date][] = $occ;
                }
            } else {
                $date = date('Y-m-d', strtotime($event->event_date));
                if (!isset($this->events[$date])) { $this->events[$date] = []; }
                $this->events[$date][] = $event;
            }
        }
    }

    public function render() {
        $this->load_events();
        
        $output = '<div class="church-events-calendar">';
        
        // Calendar navigation
        $output .= $this->render_navigation();
        
        // Calendar header
        $output .= '<table class="calendar-table">';
        $output .= '<thead>';
        $output .= '<tr>';
        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        foreach ($days as $day) {
            $output .= '<th>' . esc_html__($day, 'church-events-manager') . '</th>';
        }
        $output .= '</tr>';
        $output .= '</thead>';
        
        // Calendar body
        $output .= '<tbody>';
        $output .= $this->render_calendar_days();
        $output .= '</tbody>';
        $output .= '</table>';
        
        // Event details popup
        $output .= '<div id="calendar-event-popup" class="calendar-popup" style="display:none;"></div>';
        
        $output .= '</div>';
        
        return $output;
    }

    private function render_navigation() {
        $prev_month = clone $this->current_date;
        $prev_month->modify('-1 month');
        
        $next_month = clone $this->current_date;
        $next_month->modify('+1 month');

        $output = '<div class="calendar-navigation">';
        $output .= '<button class="prev-month" data-month="' . $prev_month->format('Y-m') . '">&lt;</button>';
        $output .= '<h2>' . $this->current_date->format('F Y') . '</h2>';
        $output .= '<button class="next-month" data-month="' . $next_month->format('Y-m') . '">&gt;</button>';
        $output .= '</div>';

        return $output;
    }

    private function render_calendar_days() {
        $output = '';
        
        $first_day = new \DateTime($this->current_year . '-' . $this->current_month . '-01');
        $last_day = new \DateTime($this->current_year . '-' . $this->current_month . '-' . $first_day->format('t'));
        
        $start_day_of_week = $first_day->format('w');
        
        $current_day = clone $first_day;
        $current_day->modify('-' . $start_day_of_week . ' days');
        
        for ($week = 0; $week < 6; $week++) {
            $output .= '<tr>';
            for ($day = 0; $day < 7; $day++) {
                $date = $current_day->format('Y-m-d');
                $is_current_month = $current_day->format('m') === $this->current_month;
                
                $class = 'calendar-day';
                $class .= $is_current_month ? ' current-month' : ' other-month';
                if (isset($this->events[$date])) {
                    $class .= ' has-events';
                }
                
                $output .= '<td class="' . $class . '" data-date="' . $date . '">';
                $output .= '<div class="day-number">' . $current_day->format('j') . '</div>';
                
                if (isset($this->events[$date])) {
                    $output .= $this->render_day_events($this->events[$date]);
                }
                
                $output .= '</td>';
                
                $current_day->modify('+1 day');
            }
            $output .= '</tr>';
        }
        
        return $output;
    }

    private function render_day_events($events) {
        $output = '<div class="day-events">';
        foreach ($events as $index => $event) {
            if ($index >= 3) {
                $remaining = count($events) - 3;
                $output .= '<div class="more-events">+' . $remaining . ' more</div>';
                break;
            }
            
            $time = date_i18n(get_option('time_format'), strtotime($event->event_date));
            $output .= '<div class="calendar-event" data-event-id="' . $event->ID . '">';
            $output .= '<span class="event-time">' . esc_html($time) . '</span> ';
            $output .= '<span class="event-title">' . esc_html($event->post_title) . '</span>';
            $output .= '</div>';
        }
        $output .= '</div>';
        return $output;
    }

    public static function get_event_details($event_id) {
        $event = get_post($event_id);
        if (!$event) {
            return '';
        }

        global $wpdb;
        $event_meta = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cem_event_meta WHERE event_id = %d",
            $event_id
        ));

        $output = '<div class="event-popup-content">';
        $output .= '<h3>' . esc_html($event->post_title) . '</h3>';
        
        $date = date_i18n(get_option('date_format'), strtotime($event_meta->event_date));
        $time = date_i18n(get_option('time_format'), strtotime($event_meta->event_date));
        
        $output .= '<div class="event-meta">';
        $output .= '<p><strong>' . __('Date:', 'church-events-manager') . '</strong> ' . esc_html($date) . '</p>';
        $output .= '<p><strong>' . __('Time:', 'church-events-manager') . '</strong> ' . esc_html($time) . '</p>';
        
        if ($event_meta->location) {
            $output .= '<p><strong>' . __('Location:', 'church-events-manager') . '</strong> ' . 
                      esc_html($event_meta->location) . '</p>';
        }
        
        $output .= '</div>';
        
        $output .= '<div class="event-description">';
        $output .= wp_trim_words($event->post_content, 30);
        $output .= '</div>';
        
        $output .= '<a href="' . esc_url(\church_events_get_occurrence_link($event_id, $event_meta->event_date)) . '" class="view-event-link">' . 
                  __('View Details', 'church-events-manager') . '</a>';
        
        $output .= '</div>';
        
        return $output;
    }
}