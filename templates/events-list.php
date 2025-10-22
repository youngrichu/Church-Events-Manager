<?php
/**
 * Template Name: Events List
 * Description: Displays church events with List, Month, and Day views.
 */

use ChurchEventsManager\Frontend\Calendar;
use ChurchEventsManager\Search\SearchHandler;

get_header();

$view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'list';
$view = in_array($view, ['list','month','day'], true) ? $view : 'list';

// Normalize day param to avoid WP reserved 'day' archive conflict
$dayParam = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';
$monthParam = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : '';

// Compute month for calendar rendering
if ($view === 'day' && $dayParam) {
    $monthForCalendar = substr($dayParam, 0, 7);
} elseif ($monthParam) {
    $monthForCalendar = $monthParam;
} else {
    $monthForCalendar = date('Y-m');
}

// Enqueue assets
wp_enqueue_style('cem-public', CEM_PLUGIN_URL . 'assets/css/public.css', [], CEM_VERSION);
wp_enqueue_style('cem-search', CEM_PLUGIN_URL . 'assets/css/search.css', [], CEM_VERSION);
if ($view === 'month' || $view === 'day') {
    wp_enqueue_style('cem-calendar', CEM_PLUGIN_URL . 'assets/css/calendar.css', [], CEM_VERSION);
    wp_enqueue_script('cem-calendar', CEM_PLUGIN_URL . 'assets/js/calendar.js', ['jquery'], CEM_VERSION, true);
    wp_localize_script('cem-calendar', 'church_events_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('calendar_nonce'),
        'view'     => $view,
        'day'      => $dayParam,
        'month'    => $monthForCalendar,
    ]);
}
?>

<div id="primary" class="content-area">
  <main id="main" class="site-main">
    <header class="page-header">
      <?php
        $elementor_settings = get_post_meta(get_the_ID(), '_elementor_page_settings', true);
        $hide_title = is_array($elementor_settings) && isset($elementor_settings['hide_title']) && $elementor_settings['hide_title'] === 'yes';
      ?>
      <?php if (!$hide_title) : ?>
      <h1 class="page-title"><?php echo esc_html(get_the_title()); ?></h1>
      <?php endif; ?>

      <div class="events-toolbar">
        <?php 
          // Inline search form for events (routes to site search with post_type=church_event)
          echo SearchHandler::get_search_form();
        ?>

        <nav class="view-switcher" aria-label="View switcher">
          <?php 
            $base = get_permalink();
            $qs = $_GET; // keep existing filters where applicable
            $qs['view'] = 'list';
            $list_url = esc_url(add_query_arg($qs, $base));
            $qs['view'] = 'month';
            $qs['month'] = $monthForCalendar;
            unset($qs['date']);
            $month_url = esc_url(add_query_arg($qs, $base));
            $qs['view'] = 'day';
            $qs['date'] = $dayParam ? $dayParam : date('Y-m-d');
            unset($qs['month']);
            $day_url = esc_url(add_query_arg($qs, $base));
          ?>
          <a class="view-tab <?php echo $view === 'list' ? 'active' : ''; ?>" href="<?php echo $list_url; ?>"><?php _e('List', 'church-events-manager'); ?></a>
          <a class="view-tab <?php echo $view === 'month' ? 'active' : ''; ?>" href="<?php echo $month_url; ?>"><?php _e('Month', 'church-events-manager'); ?></a>
          <a class="view-tab <?php echo $view === 'day' ? 'active' : ''; ?>" href="<?php echo $day_url; ?>"><?php _e('Day', 'church-events-manager'); ?></a>
        </nav>
      </div>
    </header>

    <?php if ($view === 'list') : ?>
      <?php
        // Upcoming events list similar to shortcode rendering
        global $wpdb;
        $now = current_time('mysql');
        $end = date('Y-m-d H:i:s', strtotime('+1 year', strtotime($now)));

        // Production: compute totals without outputting debug comments
        $total_events = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'church_event' AND post_status = 'publish'");
        $total_meta = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cem_event_meta");
        
        // Production: table existence check without output
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}cem_event_meta'"
        );
        
        if ($table_exists) {
            $columns = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}cem_event_meta");
            $column_names = array_map(function($col) { return $col->Field; }, $columns);
        }
        
        $sql = "SELECT p.*, em.* FROM {$wpdb->posts} p
             JOIN {$wpdb->prefix}cem_event_meta em ON p.ID = em.event_id
             WHERE p.post_type = 'church_event' AND p.post_status = 'publish'
             AND (
                (em.is_recurring = 0 AND em.event_date BETWEEN %s AND %s)
                OR (em.is_recurring = 1 AND em.event_date <= %s AND (em.recurring_end_date IS NULL OR em.recurring_end_date >= %s))
             )
             ORDER BY em.event_date ASC";
        
        // Execute the query
        $events = $wpdb->get_results($wpdb->prepare($sql, $now, $end, $end, $now));
        
        // If no events found, optional internal checks (no output)
        if (empty($events)) {
            // Test the JOIN separately
            $join_test = $wpdb->get_results("
                SELECT p.ID, p.post_title, em.event_date 
                FROM {$wpdb->posts} p 
                LEFT JOIN {$wpdb->prefix}cem_event_meta em ON p.ID = em.event_id 
                WHERE p.post_type = 'church_event' AND p.post_status = 'publish'
                LIMIT 5
            ");
            
            // Check for future events specifically
            $future_events = $wpdb->get_results($wpdb->prepare("
                SELECT COUNT(*) as count 
                FROM {$wpdb->prefix}cem_event_meta 
                WHERE event_date >= %s
            ", $now));
        }

        // Group recurring events (settings toggle) - Check this FIRST to avoid duplicates
        $opts = get_option('church_events_options', []);
        $groupedList = !empty($opts['group_recurring_in_list']);
        
        $occurrences = [];
        
        if ($groupedList) {
            // When grouping is enabled, show only one entry per recurring series
            $processed_series = [];
            foreach ($events as $event) {
                if ((int)$event->is_recurring === 1) {
                    // For recurring events, find the next occurrence and show only once per series
                    if (!in_array($event->ID, $processed_series, true)) {
                        $next = null;
                        try {
                            $expanded = ChurchEventsManager\Events\RecurrenceExpander::expandInRange($event, $now, $end);
                            foreach ($expanded as $occ) {
                                if (strtotime($occ->event_date) >= strtotime($now)) { 
                                    $next = $occ; 
                                    break; 
                                }
                            }
                        } catch (Exception $e) {
                            // Silently ignore expansion errors in production
                        }
                        
                        if ($next) { 
                            $occurrences[] = $next; 
                            $processed_series[] = $event->ID;
                        }
                    }
                } else {
                    // For non-recurring events, add directly if in future
                    if (strtotime($event->event_date) >= strtotime($now)) {
                        $occurrences[] = $event;
                    }
                }
            }
        } else {
            // When grouping is disabled, expand all recurring events to show individual occurrences
            foreach ($events as $event) {
                if ((int)$event->is_recurring === 1) {
                    try {
                        $expanded = ChurchEventsManager\Events\RecurrenceExpander::expandInRange($event, $now, $end);
                        foreach ($expanded as $occ) {
                            $occurrences[] = $occ;
                        }
                    } catch (Exception $e) {
                        // Silently ignore expansion errors in production
                    }
                } else {
                    if (strtotime($event->event_date) >= strtotime($now)) {
                        $occurrences[] = $event;
                    }
                }
            }
        }

        // Sort by occurrence date
        usort($occurrences, function($a, $b) {
            return strtotime($a->event_date) <=> strtotime($b->event_date);
        });

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CEM Events List: Final occurrences count: ' . count($occurrences) . ', Grouped: ' . ($groupedList ? 'yes' : 'no'));
        }
      ?>

      <?php if (!empty($occurrences)) : ?>
        <?php 
          // Pagination for occurrences (or series when grouped)
          $per_page = apply_filters('cem_events_per_page', 10);
          $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
          $total_items = count($occurrences);
          $total_pages = (int) ceil($total_items / $per_page);
          $offset = ($current_page - 1) * $per_page;
          $page_slice = array_slice($occurrences, $offset, $per_page);
        ?>
        <div class="events-list">
          <?php foreach ($page_slice as $event) : ?>
            <?php 
              $ts = strtotime($event->event_date);
              $dow = strtoupper(date_i18n('D', $ts));
              $daynum = date_i18n('j', $ts);
              $has_thumb = has_post_thumbnail($event->ID);
            ?>
            <article class="event-item events-card">
              <div class="event-date-col">
                <div class="event-dow"><?php echo esc_html($dow); ?></div>
                <div class="event-day"><?php echo esc_html($daynum); ?></div>
              </div>
              <div class="event-content-col">
                <h2 class="event-title">
                  <?php $occurrence_link = church_events_get_occurrence_link($event->ID, $event->event_date); ?>
                  <a href="<?php echo esc_url($occurrence_link); ?>"><?php echo esc_html(get_the_title($event->ID)); ?></a>
                </h2>
                <div class="event-meta">
                  <span class="event-date">
                    <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $ts)); ?>
                  </span>
                  <?php if (!empty($event->location)) : ?>
                    <span class="event-location"><?php echo esc_html($event->location); ?></span>
                  <?php endif; ?>
                  <?php if ($groupedList && (int)$event->is_recurring === 1) : ?>
                    <span class="event-series-tag">&middot; <?php _e('Recurring series', 'church-events-manager'); ?></span>
                    <a class="event-details-link" href="<?php echo esc_url(church_events_get_occurrence_link($event->ID, $event->event_date)); ?>"><?php _e('View details', 'church-events-manager'); ?></a>
                  <?php endif; ?>
                </div>
                <div class="event-excerpt">
                  <?php echo wp_trim_words(get_post_field('post_content', $event->ID), 28); ?>
                </div>
              </div>
              <?php if ($has_thumb) : ?>
                <div class="event-thumb-col">
                  <?php echo get_the_post_thumbnail($event->ID, 'medium_large', ['class' => 'event-thumb']); ?>
                </div>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
        <?php if ($total_pages > 1) : ?>
          <?php 
            $base_url = get_permalink();
            $add_args = $_GET; 
            unset($add_args['paged']);
            $add_args['view'] = 'list';
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
          ?>
          <?php if (!empty($pagination)) : ?>
            <div class="pagination"><?php echo $pagination; ?></div>
          <?php endif; ?>
        <?php endif; ?>
      <?php else : ?>
        <p class="no-events"><?php _e('No upcoming events found.', 'church-events-manager'); ?></p>
      <?php endif; ?>

    <?php else : ?>
      <?php
        // Calendar rendering for Month and Day views
        $dateForCalendar = $monthForCalendar . '-01';
        $calendar = new Calendar($dateForCalendar);
        echo $calendar->render();
      ?>

      <?php if ($view === 'day' && $dayParam) : ?>
        <?php 
          $current = new DateTime($dayParam);
          $prev = clone $current; $prev->modify('-1 day');
          $next = clone $current; $next->modify('+1 day');
          $dayLabel = date_i18n(get_option('date_format'), $current->getTimestamp());
          $base = get_permalink();
        ?>
        <div class="day-navigation">
          <a class="button" href="<?php echo esc_url(add_query_arg(['view'=>'day','date'=>$prev->format('Y-m-d')], $base)); ?>">&lt;</a>
          <span class="day-label"><?php echo esc_html($dayLabel); ?></span>
          <a class="button" href="<?php echo esc_url(add_query_arg(['view'=>'day','date'=>$next->format('Y-m-d')], $base)); ?>">&gt;</a>
          <a class="button button-secondary" href="<?php echo esc_url(add_query_arg(['view'=>'month','month'=>$monthForCalendar], $base)); ?>"><?php _e('Back to month', 'church-events-manager'); ?></a>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </main>
</div>

<?php get_footer(); ?>