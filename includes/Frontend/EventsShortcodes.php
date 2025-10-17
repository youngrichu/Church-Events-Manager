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
            // New options
            'group_recurring' => '', // empty => use setting; '1'/'true' to enable; '0'/'false' to disable
            'show_badge' => '1',
            'show_more' => '1',
            'more_url' => '',
            'more_text' => __('View More', 'church-events-manager'),
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
        
        // Fetch parents and non-recurring
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, em.* FROM {$wpdb->posts} p
             JOIN {$wpdb->prefix}cem_event_meta em ON p.ID = em.event_id
             LEFT JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
             LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             LEFT JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
             WHERE p.post_type = 'church_event' AND p.post_status = 'publish'
             AND (
                (em.is_recurring = 0 AND em.event_date >= %s)
                OR (em.is_recurring = 1 AND em.event_date <= %s AND (em.recurring_end_date IS NULL OR em.recurring_end_date >= %s))
             )
             " . ($atts['category'] ? " AND t.slug = %s" : "") . "
             ORDER BY em.event_date ASC",
             $now,
             $end,
             $now,
             $atts['category'] ? $atts['category'] : null
        ));

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

        // Render
        $output = '<div class="church-events-list">';
        foreach ($entries as $event) {
            $output .= '<div class="event-item">';
            $output .= '<h4><a href="' . esc_url(\church_events_get_occurrence_link($event->ID, $event->event_date)) . '">' . esc_html($event->post_title) . '</a></h4>';
            $output .= '<div class="meta">';
            $output .= esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($event->event_date)));
            if (!empty($event->location)) {
                $output .= ' — ' . esc_html($event->location);
            }
            if ($grouped && (int)$event->is_recurring === 1 && in_array(strtolower((string)$atts['show_badge']), ['1','true','yes','on'], true)) {
                $output .= ' <span class="event-series-tag">&middot; ' . esc_html(__('Recurring series', 'church-events-manager')) . '</span>';
                $output .= ' <a class="event-details-link" href="' . esc_url(\church_events_get_occurrence_link($event->ID, $event->event_date)) . '">' . esc_html(__('View details', 'church-events-manager')) . '</a>';
            }
            $output .= '</div>';
            $output .= '<div class="excerpt">' . wp_trim_words($event->post_content, 25) . '</div>';
            $output .= '</div>';
        }
        $output .= '</div>';

        // View More CTA
        $show_more = in_array(strtolower((string)$atts['show_more']), ['1','true','yes','on'], true);
        if ($show_more) {
            $more_url = trim((string)$atts['more_url']);
            if (!$more_url) {
                $events_page_id = (int) get_option('cem_events_page_id');
                $more_url = $events_page_id ? get_permalink($events_page_id) : get_post_type_archive_link('church_event');
            }
            $output .= '<div class="shortcode-view-more">';
            $output .= '<a class="button button-secondary" href="' . esc_url($more_url) . '">' . esc_html($atts['more_text']) . '</a>';
            $output .= '</div>';
        }

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