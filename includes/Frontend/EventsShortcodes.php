<?php
namespace ChurchEventsManager\Frontend;

use ChurchEventsManager\Events\RecurrenceExpander;

class EventsShortcodes {
    public function __construct() {
        add_shortcode('church_events', [__CLASS__, 'render_events_list']);
        add_shortcode('church_event', [__CLASS__, 'render_single_event']);
    }

    public static function render_events_list($atts = []) {
        global $wpdb;
        $atts = shortcode_atts([
            'limit' => 10,
            'category' => '',
            'view' => 'list',
            'group_recurring' => '', // empty => use setting; '1'/'true' to enable; '0'/'false' to disable
            'show_badge' => '1',
            'excerpt_chars' => 160,
        ], $atts, 'church_events');

        // Calendar view
        if ($atts['view'] === 'calendar') {
            // Enqueue calendar assets and localize AJAX data
            wp_enqueue_style('cem-calendar', CEM_PLUGIN_URL . 'assets/css/calendar.css', [], CEM_VERSION);
            wp_enqueue_script('cem-calendar', CEM_PLUGIN_URL . 'assets/js/calendar.js', ['jquery'], CEM_VERSION, true);
            wp_localize_script('cem-calendar', 'church_events_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('calendar_nonce'),
            ]);

            // Determine initial month if provided via query param
            $month = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : null;
            $date = $month ? ($month . '-01') : null;
            $calendar = new Calendar($date);
            return $calendar->render();
        }

        $now = current_time('mysql');
        $end = date('Y-m-d H:i:s', strtotime('+1 year', strtotime($now))); // sensible horizon
        
        // Fetch parents and non-recurring (fix prepare placeholders vs args)
        $query = "SELECT p.*, em.* FROM {$wpdb->posts} p
             JOIN {$wpdb->prefix}cem_event_meta em ON p.ID = em.event_id
             LEFT JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
             LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             LEFT JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
             WHERE p.post_type = 'church_event' AND p.post_status = 'publish'
             AND (
                (em.is_recurring = 0 AND em.event_date >= %s)
                OR (em.is_recurring = 1 AND em.event_date <= %s AND (em.recurring_end_date IS NULL OR em.recurring_end_date >= %s))
             )";
        $params = [ $now, $end, $now ];
        if (!empty($atts['category'])) {
            $query .= " AND t.slug = %s";
            $params[] = $atts['category'];
        }
        $query .= " ORDER BY em.event_date ASC";
        $events = $wpdb->get_results($wpdb->prepare($query, $params));

        // Determine grouping behavior (attribute overrides setting)
        $opts = get_option('church_events_options', []);
        $setting_grouped = !empty($opts['group_recurring_in_list']);
        $attr = strtolower(trim((string)$atts['group_recurring']));
        $grouped = $attr !== '' ? in_array($attr, ['1','true','yes','on'], true) : $setting_grouped;

        // Build output list: occurrences or grouped series
        $entries = [];
        if ($grouped) {
            foreach ($events as $event) {
                if ((int)$event->is_recurring === 1) {
                    $next = null;
                    foreach (RecurrenceExpander::expandInRange($event, $now, $end) as $occ) {
                        if (strtotime($occ->event_date) >= strtotime($now)) { $next = $occ; break; }
                    }
                    if ($next) { $entries[] = $next; }
                } else {
                    if (strtotime($event->event_date) >= strtotime($now)) { $entries[] = $event; }
                }
            }
        } else {
            // Expand and collect occurrences
            foreach ($events as $event) {
                if ((int)$event->is_recurring === 1) {
                    foreach (RecurrenceExpander::expandInRange($event, $now, $end) as $occ) {
                        $entries[] = $occ;
                    }
                } else {
                    if (strtotime($event->event_date) >= strtotime($now)) {
                        $entries[] = $event;
                    }
                }
            }
        }

        // Sort by occurrence date
        usort($entries, function($a, $b) {
            return strtotime($a->event_date) <=> strtotime($b->event_date);
        });

        // Apply limit
        $limit = intval($atts['limit']);
        if ($limit > 0) {
            $entries = array_slice($entries, 0, $limit);
        }

        // No stylesheet enqueued for list view to keep structure-only output

        // Render using Events List markup so styling can match
        $output = '<div class="church-events-list events-list" data-cem="struct-only-v1">';
        foreach ($entries as $event) {
            $ts = strtotime($event->event_date);
            $dow = strtoupper(date_i18n('M', $ts));
            $daynum = date_i18n('j', $ts);
            $has_thumb = has_post_thumbnail($event->ID);

            $output .= '<article class="event-item">';
            // Date column
            $output .= '<div class="event-date-col">';
            $output .= '<div class="event-dow">' . esc_html($dow) . '</div>';
            $output .= '<div class="event-day">' . esc_html($daynum) . '</div>';
            $output .= '</div>';

            // Content column
            $output .= '<div class="event-content-col">';
            $output .= '<h2 class="event-title"><a href="' . esc_url(church_events_get_occurrence_link($event->ID, $event->event_date)) . '">' . esc_html(get_the_title($event->ID)) . '</a></h2>';
            $output .= '<div class="event-meta">';
            $end_ts = !empty($event->event_end_date) ? strtotime($event->event_end_date) : null;
            $is_all_day = !empty($event->is_all_day);
            $time_text = '';
            if ($is_all_day) {
                $time_text = __('All day', 'church-events-manager');
            } else {
                $time_text = date_i18n(get_option('time_format'), $ts);
                if ($end_ts && $end_ts > $ts) {
                    $time_text .= ' – ' . date_i18n(get_option('time_format'), $end_ts);
                }
            }
            $output .= '<span class="event-time">' . esc_html($time_text) . '</span>';
            if ($grouped && (int)$event->is_recurring === 1 && in_array(strtolower((string)$atts['show_badge']), ['1','true','yes','on'], true)) {
                $output .= '<span class="event-series-tag">&middot; ' . esc_html(__('Recurring series', 'church-events-manager')) . '</span>';
            }
            $output .= '</div>'; // .event-meta

            $excerpt_chars = intval($atts['excerpt_chars']);
            $excerpt_chars = $excerpt_chars > 0 ? $excerpt_chars : 160;
            $raw_content = get_post_field('post_content', $event->ID);
            $excerpt = wp_html_excerpt(wp_strip_all_tags($raw_content), $excerpt_chars, '&hellip;');

            $output .= '<div class="event-excerpt">' . esc_html($excerpt) . '</div>';
            $output .= '</div>'; // .event-content-col

            // Thumbnail column removed (no image in shortcode output)

            $output .= '</article>';
        }
        $output .= '</div>'; // .church-events-list

        // View More CTA removed for structure-only output controlled by Elementor grid

        return $output;
    }

    public static function render_single_event($atts = []) {
        $atts = shortcode_atts([
            'id' => 0,
        ], $atts, 'church_event');

        $post = get_post(intval($atts['id']));
        if (!$post) return '';

        ob_start();
        echo '<div class="single-event">';
        echo '<h2>' . esc_html($post->post_title) . '</h2>';
        echo apply_filters('the_content', $post->post_content);
        echo '</div>';
        return ob_get_clean();
    }
}