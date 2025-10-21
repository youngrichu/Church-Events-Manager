<?php
namespace ChurchEventsManager\Elementor;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use ChurchEventsManager\Frontend\EventsShortcodes;
use ChurchEventsManager\Frontend\Calendar;
use ChurchEventsManager\Search\SearchHandler;
use ChurchEventsManager\Events\RecurrenceExpander;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class EventsListWidget extends Widget_Base {

    public function get_name() {
        return 'church_events_list';
    }

    public function get_title() {
        return __('Church Events List', 'church-events-manager');
    }

    public function get_icon() {
        return 'eicon-calendar';
    }

    public function get_categories() {
        return ['church-events'];
    }

    public function get_keywords() {
        return ['church', 'events', 'calendar', 'list'];
    }

    protected function register_controls() {
        // Main Content Section
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Content', 'church-events-manager'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'widget_title',
            [
                'label' => __('Widget Title', 'church-events-manager'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Church Events', 'church-events-manager'),
                'placeholder' => __('Enter widget title', 'church-events-manager'),
            ]
        );

        $this->add_control(
            'show_view_switcher',
            [
                'label' => __('Show View Switcher', 'church-events-manager'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'church-events-manager'),
                'label_off' => __('No', 'church-events-manager'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'default_view',
            [
                'label' => __('Default View', 'church-events-manager'),
                'type' => Controls_Manager::SELECT,
                'default' => 'list',
                'options' => [
                    'list' => __('List View', 'church-events-manager'),
                    'month' => __('Month View', 'church-events-manager'),
                    'day' => __('Day View', 'church-events-manager'),
                ],
            ]
        );

        $this->add_control(
            'show_search',
            [
                'label' => __('Show Search Form', 'church-events-manager'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'church-events-manager'),
                'label_off' => __('No', 'church-events-manager'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_pagination',
            [
                'label' => __('Show Pagination', 'church-events-manager'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'church-events-manager'),
                'label_off' => __('No', 'church-events-manager'),
                'return_value' => 'yes',
                'default' => 'yes',
                'condition' => [
                    'default_view' => 'list',
                ],
            ]
        );

        $this->add_control(
            'events_per_page',
            [
                'label' => __('Events Per Page', 'church-events-manager'),
                'type' => Controls_Manager::NUMBER,
                'default' => 10,
                'min' => 1,
                'max' => 50,
                'condition' => [
                    'default_view' => 'list',
                    'show_pagination' => 'yes',
                ],
            ]
        );

        $this->end_controls_section();

        // Filtering Section
        $this->start_controls_section(
            'filtering_section',
            [
                'label' => __('Filtering', 'church-events-manager'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'category_filter',
            [
                'label' => __('Event Category', 'church-events-manager'),
                'type' => Controls_Manager::TEXT,
                'placeholder' => __('Leave empty for all categories', 'church-events-manager'),
                'description' => __('Enter category slug to filter events', 'church-events-manager'),
            ]
        );

        $this->add_control(
            'group_recurring',
            [
                'label' => __('Group Recurring Events', 'church-events-manager'),
                'type' => Controls_Manager::SELECT,
                'default' => '',
                'options' => [
                    '' => __('Use Global Setting', 'church-events-manager'),
                    '1' => __('Yes', 'church-events-manager'),
                    '0' => __('No', 'church-events-manager'),
                ],
                'condition' => [
                    'default_view' => 'list',
                ],
            ]
        );

        $this->add_control(
            'show_recurring_badge',
            [
                'label' => __('Show Recurring Badge', 'church-events-manager'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'church-events-manager'),
                'label_off' => __('No', 'church-events-manager'),
                'return_value' => 'yes',
                'default' => 'yes',
                'condition' => [
                    'default_view' => 'list',
                ],
            ]
        );

        $this->end_controls_section();

        // Display Options Section
        $this->start_controls_section(
            'display_section',
            [
                'label' => __('Display Options', 'church-events-manager'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'show_thumbnails',
            [
                'label' => __('Show Event Thumbnails', 'church-events-manager'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'church-events-manager'),
                'label_off' => __('No', 'church-events-manager'),
                'return_value' => 'yes',
                'default' => 'yes',
                'condition' => [
                    'default_view' => 'list',
                ],
            ]
        );

        $this->add_control(
            'show_excerpts',
            [
                'label' => __('Show Event Excerpts', 'church-events-manager'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'church-events-manager'),
                'label_off' => __('No', 'church-events-manager'),
                'return_value' => 'yes',
                'default' => 'yes',
                'condition' => [
                    'default_view' => 'list',
                ],
            ]
        );

        $this->add_control(
            'excerpt_length',
            [
                'label' => __('Excerpt Length (words)', 'church-events-manager'),
                'type' => Controls_Manager::NUMBER,
                'default' => 28,
                'min' => 5,
                'max' => 100,
                'condition' => [
                    'default_view' => 'list',
                    'show_excerpts' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'show_location',
            [
                'label' => __('Show Event Location', 'church-events-manager'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'church-events-manager'),
                'label_off' => __('No', 'church-events-manager'),
                'return_value' => 'yes',
                'default' => 'yes',
                'condition' => [
                    'default_view' => 'list',
                ],
            ]
        );

        $this->end_controls_section();

        // Sidebar Section
        $this->start_controls_section(
            'sidebar_section',
            [
                'label' => __('Sidebar', 'church-events-manager'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'show_sidebar',
            [
                'label' => __('Show Sidebar', 'church-events-manager'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'church-events-manager'),
                'label_off' => __('No', 'church-events-manager'),
                'return_value' => 'yes',
                'default' => 'no',
            ]
        );

        $this->add_control(
            'sidebar_position',
            [
                'label' => __('Sidebar Position', 'church-events-manager'),
                'type' => Controls_Manager::SELECT,
                'default' => 'right',
                'options' => [
                    'left' => __('Left', 'church-events-manager'),
                    'right' => __('Right', 'church-events-manager'),
                ],
                'condition' => [
                    'show_sidebar' => 'yes',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section
        $this->start_controls_section(
            'style_section',
            [
                'label' => __('Style', 'church-events-manager'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => __('Title Color', 'church-events-manager'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .page-title' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'text_color',
            [
                'label' => __('Text Color', 'church-events-manager'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .church-events-widget' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'link_color',
            [
                'label' => __('Link Color', 'church-events-manager'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .church-events-widget a' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'background_color',
            [
                'label' => __('Background Color', 'church-events-manager'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .church-events-widget' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        
        // Enqueue necessary assets
        $this->enqueue_widget_assets($settings);
        
        // Get current view from URL or use default
        $current_view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : $settings['default_view'];
        $current_view = in_array($current_view, ['list', 'month', 'day'], true) ? $current_view : $settings['default_view'];
        
        // Get current date parameters
        $dayParam = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';
        $monthParam = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : '';
        
        // Compute month for calendar rendering
        if ($current_view === 'day' && $dayParam) {
            $monthForCalendar = substr($dayParam, 0, 7);
        } elseif ($monthParam) {
            $monthForCalendar = $monthParam;
        } else {
            $monthForCalendar = date('Y-m');
        }
        
        echo '<div class="church-events-widget">';
        
        // Widget header with title and toolbar
        $this->render_widget_header($settings, $current_view, $dayParam, $monthForCalendar);
        
        // Main content area with optional sidebar
        if ($settings['show_sidebar'] === 'yes') {
            echo '<div class="events-with-sidebar sidebar-' . esc_attr($settings['sidebar_position']) . '">';
            
            if ($settings['sidebar_position'] === 'left') {
                $this->render_sidebar();
            }
            
            echo '<div class="events-main-content">';
            $this->render_events_content($settings, $current_view, $dayParam, $monthForCalendar);
            echo '</div>';
            
            if ($settings['sidebar_position'] === 'right') {
                $this->render_sidebar();
            }
            
            echo '</div>';
        } else {
            $this->render_events_content($settings, $current_view, $dayParam, $monthForCalendar);
        }
        
        echo '</div>';
    }
    
    private function enqueue_widget_assets($settings) {
        /**
         * Allow themes/plugins to control whether the global public.css should be enqueued
         * for the Elementor Events widget. When disabled, a lighter, widget-scoped stylesheet
         * is loaded instead to avoid overriding shortcode-only layouts.
         *
         * Usage:
         *   add_filter('cem_enqueue_public_css', function ($load, $settings) { return false; });
         */
        $load_public_css = apply_filters('cem_enqueue_public_css', true, $settings);
        if ($load_public_css) {
            wp_enqueue_style('cem-public', CEM_PLUGIN_URL . 'assets/css/public.css', [], CEM_VERSION);
        } else {
            wp_enqueue_style('cem-widget', CEM_PLUGIN_URL . 'assets/css/widget.css', [], CEM_VERSION);
        }
        
        if ($settings['show_search'] === 'yes') {
            wp_enqueue_style('cem-search', CEM_PLUGIN_URL . 'assets/css/search.css', [], CEM_VERSION);
        }
        
        // Enqueue calendar assets for month/day views
        $current_view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : $settings['default_view'];
        if ($current_view === 'month' || $current_view === 'day') {
            wp_enqueue_style('cem-calendar', CEM_PLUGIN_URL . 'assets/css/calendar.css', [], CEM_VERSION);
            wp_enqueue_script('cem-calendar', CEM_PLUGIN_URL . 'assets/js/calendar.js', ['jquery'], CEM_VERSION, true);
            
            $dayParam = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';
            $monthParam = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : '';
            $monthForCalendar = ($current_view === 'day' && $dayParam) ? substr($dayParam, 0, 7) : ($monthParam ?: date('Y-m'));
            
            wp_localize_script('cem-calendar', 'church_events_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('calendar_nonce'),
                'view'     => $current_view,
                'day'      => $dayParam,
                'month'    => $monthForCalendar,
            ]);
        }
    }
    
    private function render_widget_header($settings, $current_view, $dayParam, $monthForCalendar) {
        echo '<header class="page-header">';
        
        if (!empty($settings['widget_title'])) {
            echo '<h1 class="page-title">' . esc_html($settings['widget_title']) . '</h1>';
        }
        
        echo '<div class="events-toolbar">';
        
        // Search form
        if ($settings['show_search'] === 'yes') {
            echo SearchHandler::get_search_form();
        }
        
        // View switcher
        if ($settings['show_view_switcher'] === 'yes') {
            $this->render_view_switcher($current_view, $dayParam, $monthForCalendar);
        }
        
        echo '</div>';
        echo '</header>';
    }
    
    private function render_view_switcher($current_view, $dayParam, $monthForCalendar) {
        $base = get_permalink();
        $qs = $_GET;
        
        echo '<nav class="view-switcher" aria-label="View switcher">';
        
        // List view
        $qs['view'] = 'list';
        unset($qs['date'], $qs['month']);
        $list_url = esc_url(add_query_arg($qs, $base));
        echo '<a class="view-tab ' . ($current_view === 'list' ? 'active' : '') . '" href="' . $list_url . '">' . __('List', 'church-events-manager') . '</a>';
        
        // Month view
        $qs['view'] = 'month';
        $qs['month'] = $monthForCalendar;
        unset($qs['date']);
        $month_url = esc_url(add_query_arg($qs, $base));
        echo '<a class="view-tab ' . ($current_view === 'month' ? 'active' : '') . '" href="' . $month_url . '">' . __('Month', 'church-events-manager') . '</a>';
        
        // Day view
        $qs['view'] = 'day';
        $qs['date'] = $dayParam ?: date('Y-m-d');
        unset($qs['month']);
        $day_url = esc_url(add_query_arg($qs, $base));
        echo '<a class="view-tab ' . ($current_view === 'day' ? 'active' : '') . '" href="' . $day_url . '">' . __('Day', 'church-events-manager') . '</a>';
        
        echo '</nav>';
    }
    
    private function render_events_content($settings, $current_view, $dayParam, $monthForCalendar) {
        if ($current_view === 'list') {
            $this->render_list_view($settings);
        } else {
            $this->render_calendar_view($current_view, $dayParam, $monthForCalendar);
        }
    }
    
    private function render_list_view($settings) {
        global $wpdb;
        
        $now = current_time('mysql');
        $end = date('Y-m-d H:i:s', strtotime('+1 year', strtotime($now)));
        
        // Build query with category filter if specified
        $where_category = '';
        if (!empty($settings['category_filter'])) {
            $category_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT tr.object_id FROM {$wpdb->term_relationships} tr
                 JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                 JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                 WHERE tt.taxonomy = 'event_category' AND t.slug = %s",
                $settings['category_filter']
            ));
            
            if (!empty($category_ids)) {
                $where_category = " AND p.ID IN (" . implode(',', array_map('intval', $category_ids)) . ")";
            }
        }
        
        $sql = "SELECT p.*, em.* FROM {$wpdb->posts} p
             JOIN {$wpdb->prefix}cem_event_meta em ON p.ID = em.event_id
             WHERE p.post_type = 'church_event' AND p.post_status = 'publish'
             {$where_category}
             AND (
                (em.is_recurring = 0 AND em.event_date BETWEEN %s AND %s)
                OR (em.is_recurring = 1 AND em.event_date <= %s AND (em.recurring_end_date IS NULL OR em.recurring_end_date >= %s))
             )
             ORDER BY em.event_date ASC";
        
        $events = $wpdb->get_results($wpdb->prepare($sql, $now, $end, $end, $now));
        
        // Process events (group recurring if needed)
        $occurrences = $this->process_events($events, $settings, $now, $end);
        
        if (!empty($occurrences)) {
            $this->render_events_list($occurrences, $settings);
        } else {
            echo '<p class="no-events">' . __('No upcoming events found.', 'church-events-manager') . '</p>';
        }
    }
    
    private function process_events($events, $settings, $now, $end) {
        $opts = get_option('church_events_options', []);
        $groupedList = !empty($opts['group_recurring_in_list']);
        
        // Override with widget setting if specified
        if ($settings['group_recurring'] !== '') {
            $groupedList = (bool) $settings['group_recurring'];
        }
        
        $occurrences = [];
        
        if ($groupedList) {
            // Group recurring events - show only next occurrence per series
            $processed_series = [];
            foreach ($events as $event) {
                if ((int)$event->is_recurring === 1) {
                    if (!in_array($event->ID, $processed_series)) {
                        $expanded = RecurrenceExpander::expandInRange($event, $now, $end);
                        foreach ($expanded as $occ) {
                            if (strtotime($occ->event_date) >= strtotime($now)) {
                                $occurrences[] = $occ;
                                $processed_series[] = $event->ID;
                                break;
                            }
                        }
                    }
                } else {
                    if (strtotime($event->event_date) >= strtotime($now)) {
                        $occurrences[] = $event;
                    }
                }
            }
        } else {
            // Show all occurrences
            foreach ($events as $event) {
                if ((int)$event->is_recurring === 1) {
                    $expanded = RecurrenceExpander::expandInRange($event, $now, $end);
                    foreach ($expanded as $occ) {
                        $occurrences[] = $occ;
                    }
                } else {
                    if (strtotime($event->event_date) >= strtotime($now)) {
                        $occurrences[] = $event;
                    }
                }
            }
        }
        
        // Sort by date
        usort($occurrences, function($a, $b) {
            return strtotime($a->event_date) <=> strtotime($b->event_date);
        });
        
        return $occurrences;
    }
    
    private function render_events_list($occurrences, $settings) {
        // Pagination
        $per_page = $settings['events_per_page'] ?: 10;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $total_items = count($occurrences);
        $total_pages = (int) ceil($total_items / $per_page);
        $offset = ($current_page - 1) * $per_page;
        $page_slice = array_slice($occurrences, $offset, $per_page);
        
        echo '<div class="events-list">';
        
        foreach ($page_slice as $event) {
            $this->render_event_item($event, $settings);
        }
        
        echo '</div>';
        
        // Pagination links
        if ($settings['show_pagination'] === 'yes' && $total_pages > 1) {
            $this->render_pagination($current_page, $total_pages);
        }
    }
    
    private function render_event_item($event, $settings) {
        $ts = strtotime($event->event_date);
        $dow = strtoupper(date_i18n('D', $ts));
        $daynum = date_i18n('j', $ts);
        $has_thumb = has_post_thumbnail($event->ID);
        
        echo '<article class="event-item events-card">';
        
        // Date column
        echo '<div class="event-date-col">';
        echo '<div class="event-dow">' . esc_html($dow) . '</div>';
        echo '<div class="event-day">' . esc_html($daynum) . '</div>';
        echo '</div>';
        
        // Content column
        echo '<div class="event-content-col">';
        
        $occurrence_link = function_exists('church_events_get_occurrence_link') 
            ? church_events_get_occurrence_link($event->ID, $event->event_date)
            : get_permalink($event->ID);
            
        echo '<h2 class="event-title">';
        echo '<a href="' . esc_url($occurrence_link) . '">' . esc_html(get_the_title($event->ID)) . '</a>';
        echo '</h2>';
        
        echo '<div class="event-meta">';
        $end_ts = !empty($event->event_end_date) ? strtotime($event->event_end_date) : null;
        $is_all_day = !empty($event->is_all_day);
        $time_text = $is_all_day
            ? __('All day', 'church-events-manager')
            : date_i18n(get_option('time_format'), $ts) . (($end_ts && $end_ts > $ts) ? ' – ' . date_i18n(get_option('time_format'), $end_ts) : '');
        echo '<span class="event-date">' . esc_html($time_text) . '</span>';
        
        if ($settings['show_location'] === 'yes' && !empty($event->location)) {
            echo '<span class="event-location">' . esc_html($event->location) . '</span>';
        }
        
        if ($settings['show_recurring_badge'] === 'yes' && (int)$event->is_recurring === 1) {
            echo '<span class="event-series-tag">&middot; ' . __('Recurring series', 'church-events-manager') . '</span>';
        }
        
        echo '</div>';
        
        if ($settings['show_excerpts'] === 'yes') {
            $excerpt_length = $settings['excerpt_length'] ?: 28;
            echo '<div class="event-excerpt">';
            echo wp_trim_words(get_post_field('post_content', $event->ID), $excerpt_length);
            echo '</div>';
        }
        
        echo '</div>';
        
        // Thumbnail column
        if ($settings['show_thumbnails'] === 'yes' && $has_thumb) {
            echo '<div class="event-thumb-col">';
            echo get_the_post_thumbnail($event->ID, 'medium_large', ['class' => 'event-thumb']);
            echo '</div>';
        }
        
        echo '</article>';
    }
    
    private function render_pagination($current_page, $total_pages) {
        $base_url = get_permalink();
        $add_args = $_GET;
        unset($add_args['paged']);
        
        $pagination = paginate_links([
            'base'      => esc_url_raw(add_query_arg('paged', '%#%', $base_url)),
            'format'    => false,
            'current'   => $current_page,
            'total'     => $total_pages,
            'prev_text' => __('Prev', 'church-events-manager'),
            'next_text' => __('Next', 'church-events-manager'),
            'type'      => 'plain',
            'add_args'  => $add_args,
        ]);
        
        if (!empty($pagination)) {
            echo '<div class="pagination">' . $pagination . '</div>';
        }
    }
    
    private function render_calendar_view($current_view, $dayParam, $monthForCalendar) {
        $dateForCalendar = $monthForCalendar . '-01';
        $calendar = new Calendar($dateForCalendar);
        echo $calendar->render();
        
        // Day navigation for day view
        if ($current_view === 'day' && $dayParam) {
            $this->render_day_navigation($dayParam);
        }
    }
    
    private function render_day_navigation($dayParam) {
        $current = new \DateTime($dayParam);
        $prev = clone $current; $prev->modify('-1 day');
        $next = clone $current; $next->modify('+1 day');
        $dayLabel = date_i18n(get_option('date_format'), $current->getTimestamp());
        $base = get_permalink();
        
        echo '<div class="day-navigation">';
        echo '<a class="button" href="' . esc_url(add_query_arg(['view'=>'day','date'=>$prev->format('Y-m-d')], $base)) . '">&lt;</a>';
        echo '<span class="day-label">' . esc_html($dayLabel) . '</span>';
        echo '<a class="button" href="' . esc_url(add_query_arg(['view'=>'day','date'=>$next->format('Y-m-d')], $base)) . '">&gt;</a>';
        echo '<a class="button button-secondary" href="' . esc_url(add_query_arg(['view'=>'month','month'=>substr($dayParam, 0, 7)], $base)) . '">' . __('Back to month', 'church-events-manager') . '</a>';
        echo '</div>';
    }
    
    private function render_sidebar() {
        echo '<aside class="events-sidebar">';
        
        // Check if there's a custom sidebar for events
        if (is_active_sidebar('events-sidebar')) {
            dynamic_sidebar('events-sidebar');
        } else {
            // Default sidebar content
            echo '<div class="sidebar-widget">';
            echo '<h3>' . __('Event Categories', 'church-events-manager') . '</h3>';
            
            $categories = get_terms([
                'taxonomy' => 'event_category',
                'hide_empty' => true,
            ]);
            
            if (!empty($categories) && !is_wp_error($categories)) {
                echo '<ul class="event-categories">';
                foreach ($categories as $category) {
                    $category_url = add_query_arg(['event_category' => $category->slug], get_permalink());
                    echo '<li><a href="' . esc_url($category_url) . '">' . esc_html($category->name) . ' (' . $category->count . ')</a></li>';
                }
                echo '</ul>';
            }
            echo '</div>';
        }
        
        echo '</aside>';
    }

    protected function content_template() {
        ?>
        <# 
        var view = settings.view || 'list';
        #>
        <div class="elementor-church-events-preview">
            <# if ( view === 'calendar' ) { #>
                <div style="padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; text-align: center;">
                    <i class="eicon-calendar" style="font-size: 48px; color: #6c757d; margin-bottom: 10px;"></i>
                    <p><?php echo __('Calendar View Preview', 'church-events-manager'); ?></p>
                    <small><?php echo __('Calendar will be displayed on the frontend', 'church-events-manager'); ?></small>
                </div>
            <# } else { #>
                <div style="padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;">
                    <h4 style="margin: 0 0 15px 0;"><?php echo __('Events List Preview', 'church-events-manager'); ?></h4>
                    <div style="border-left: 3px solid #007cba; padding-left: 15px; margin-bottom: 10px;">
                        <strong><?php echo __('Sample Event Title', 'church-events-manager'); ?></strong><br>
                        <small style="color: #666;"><?php echo __('January 15, 2024 at 10:00 AM', 'church-events-manager'); ?></small><br>
                        <span style="font-size: 14px;"><?php echo __('Sample event description...', 'church-events-manager'); ?></span>
                    </div>
                    <small><?php echo __('Actual events will be displayed on the frontend', 'church-events-manager'); ?></small>
                </div>
            <# } #>
        </div>
        <?php
    }
}