<?php
/**
 * The template for displaying single events
 */

get_header();

// Enqueue public styles
wp_enqueue_style('cem-public', CEM_PLUGIN_URL . 'assets/css/public.css', [], CEM_VERSION);
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <?php while (have_posts()) : the_post(); ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class('event-single'); ?>>
                <?php do_action('church_events_before_single_event'); ?>

                <?php 
                // Back to Events link
                $events_page_id = (int) get_option('cem_events_page_id');
                $back_url = $events_page_id ? get_permalink($events_page_id) : get_post_type_archive_link('church_event');
                ?>
                <div class="event-actions">
                    <a class="button button-secondary back-to-events" href="<?php echo esc_url($back_url); ?>">
                        <?php _e('Back to Events', 'church-events-manager'); ?>
                    </a>
                </div>

                <div class="event-layout-container">
                    <!-- Main Content Area -->
                    <div class="event-main-content">
                        <header class="event-header">
                            <?php if (has_post_thumbnail()) : ?>
                                <div class="event-thumbnail">
                                    <?php the_post_thumbnail('large'); ?>
                                </div>
                            <?php endif; ?>

                            <h1 class="event-title">
                                <?php the_title(); ?>
                            </h1>
                        </header>

                        <div class="event-content">
                            <?php the_content(); ?>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <aside class="event-sidebar">
                        <!-- Search Box -->
                        <div class="sidebar-search-box">
                            <h3 class="sidebar-title"><?php _e('Search', 'church-events-manager'); ?></h3>
                            <form role="search" method="get" class="sidebar-search-form" action="<?php echo esc_url(home_url('/')); ?>">
                                <div class="search-input-wrapper">
                                    <input type="search" 
                                           class="search-field" 
                                           placeholder="<?php echo esc_attr_x('Search...', 'placeholder', 'church-events-manager'); ?>" 
                                           value="<?php echo get_search_query(); ?>" 
                                           name="s" />
                                    <button type="submit" class="search-submit">
                                        <span class="screen-reader-text"><?php echo _x('Search', 'submit button', 'church-events-manager'); ?></span>
                                        <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="11" cy="11" r="8"></circle>
                                            <path d="m21 21-4.35-4.35"></path>
                                        </svg>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <?php 
                        $event_meta = church_events_get_meta(get_the_ID());
                        // If a specific occurrence is requested via pretty URL or query arg, adjust meta to that instance
                        $occParam = sanitize_text_field(get_query_var('occurrence'));
                        if (empty($occParam)) {
                            $occParam = isset($_GET['occurrence']) ? sanitize_text_field($_GET['occurrence']) : '';
                        }
                        if ($occParam && !empty($event_meta) && !empty($event_meta->is_recurring)) {
                            // Normalize to date-only if pretty URL (YYYY-MM-DD)
                            $occDateOnly = preg_replace('/[^0-9\-]/', '', $occParam);
                            if (strlen($occDateOnly) > 10) { $occDateOnly = substr($occDateOnly, 0, 10); }

                            $occTs = strtotime($occParam);
                            $dayStart = ($occDateOnly ? ($occDateOnly . ' 00:00:00') : date('Y-m-d 00:00:00', $occTs));
                            $dayEnd   = ($occDateOnly ? ($occDateOnly . ' 23:59:59') : date('Y-m-d 23:59:59', $occTs));
                            $event_row = (object) array_merge((array)$event_meta, ['ID' => get_the_ID()]);
                            $matches = \ChurchEventsManager\Events\RecurrenceExpander::expandInRange($event_row, $dayStart, $dayEnd);
                            if (!empty($matches)) {
                                // If provided had time, match exact datetime; otherwise take first match for that day
                                $chosen = $matches[0];
                                if ($occTs && strlen($occParam) > 10) {
                                    foreach ($matches as $m) {
                                        if (strtotime($m->event_date) === $occTs) { $chosen = $m; break; }
                                    }
                                }
                                $event_meta->event_date = $chosen->event_date;
                                $event_meta->event_end_date = $chosen->event_end_date;
                            }
                        }
                        
                        if ($event_meta) : 
                        ?>
                            <!-- Event Details Box -->
                            <div class="event-details-box">
                                <h3 class="details-title"><?php _e('Event Details', 'church-events-manager'); ?></h3>
                                
                                <div class="event-datetime">
                                    <i class="icon-calendar"></i>
                                    <div class="detail-content">
                                        <strong><?php _e('Date & Time', 'church-events-manager'); ?></strong>
                                        <span>
                                            <?php 
                                            echo esc_html(
                                                \ChurchEventsManager\I18n\Translator::format_datetime($event_meta->event_date)
                                            ); 
                                            
                                            if ($event_meta->event_end_date) {
                                                echo ' - ';
                                                echo esc_html(
                                                    \ChurchEventsManager\I18n\Translator::format_datetime($event_meta->event_end_date)
                                                );
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </div>

                                <?php if ($event_meta->location) : ?>
                                    <div class="event-location">
                                        <i class="icon-location"></i>
                                        <div class="detail-content">
                                            <strong><?php _e('Location', 'church-events-manager'); ?></strong>
                                            <span><?php echo esc_html($event_meta->location); ?></span>
                                        </div>
                                        
                                        <?php if (\ChurchEventsManager\Maps\GoogleMapsHandler::is_maps_enabled()) : ?>
                                            <div id="event-map" class="event-map"></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($event_meta->is_recurring) : ?>
                                    <div class="event-recurring">
                                        <i class="icon-recurring"></i>
                                        <div class="detail-content">
                                            <strong><?php _e('Recurring', 'church-events-manager'); ?></strong>
                                            <span>
                                                <?php 
                                                $patterns = \ChurchEventsManager\I18n\Translator::get_recurring_patterns();
                                                echo esc_html($patterns[$event_meta->recurring_pattern] ?? '');
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php 
                        // Upcoming dates for recurring events - styled like Recent Posts
                        if (!empty($event_meta) && !empty($event_meta->is_recurring)) :
                            $now = current_time('mysql');
                            $horizon = date('Y-m-d H:i:s', strtotime('+6 months', strtotime($now)));
                            $event_row = (object) array_merge((array)$event_meta, ['ID' => get_the_ID()]);
                            $upcoming = \ChurchEventsManager\Events\RecurrenceExpander::expandInRange($event_row, $now, $horizon);

                            if (!empty($upcoming)) : 
                                // Limit to first 5 upcoming dates for sidebar
                                $upcoming = array_slice($upcoming, 0, 5);
                            ?>
                                <!-- Upcoming Dates Box -->
                                <div class="upcoming-dates-box">
                                    <h3 class="upcoming-title"><?php _e('Upcoming Dates', 'church-events-manager'); ?></h3>
                                    <ul class="upcoming-dates-list">
                                        <?php foreach ($upcoming as $occ) : ?>
                                            <li class="upcoming-date-item">
                                                <a href="<?php echo esc_url(church_events_get_occurrence_link(get_the_ID(), $occ->event_date)); ?>">
                                                    <span class="date-text">
                                                        <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($occ->event_date))); ?>
                                                    </span>
                                                    <span class="time-text">
                                                        <?php echo esc_html(date_i18n(get_option('time_format'), strtotime($occ->event_date))); ?>
                                                    </span>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; 
                        endif; ?>


                    </aside>
                </div>

                <footer class="event-footer">

                    <?php 
                    $categories = get_the_terms(get_the_ID(), 'event_category');
                    if ($categories) : 
                    ?>
                        <div class="event-categories">
                            <span class="meta-label">
                                <?php _e('Categories:', 'church-events-manager'); ?>
                            </span>
                            <?php 
                            echo get_the_term_list(
                                get_the_ID(), 
                                'event_category', 
                                '', 
                                ', '
                            ); 
                            ?>
                        </div>
                    <?php endif; ?>

                    <?php do_action('church_events_after_single_event'); ?>

                    <?php 
                    // Next/Previous event navigation
                    $prev_post = get_adjacent_post(false, '', true, 'event_category');
                    $next_post = get_adjacent_post(false, '', false, 'event_category');
                    ?>
                    <nav class="event-nav" aria-label="Event navigation">
                        <?php if ($prev_post) : ?>
                            <a class="button button-secondary prev-event" href="<?php echo esc_url(get_permalink($prev_post)); ?>">
                                &larr; <?php echo esc_html(get_the_title($prev_post)); ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($next_post) : ?>
                            <a class="button button-secondary next-event" href="<?php echo esc_url(get_permalink($next_post)); ?>">
                                <?php echo esc_html(get_the_title($next_post)); ?> &rarr;
                            </a>
                        <?php endif; ?>
                    </nav>
                </footer>
            </article>


        <?php endwhile; ?>
    </main>
</div>

<?php
get_footer();