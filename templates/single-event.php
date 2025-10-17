<?php
/**
 * The template for displaying single events
 */

get_header();
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <?php while (have_posts()) : the_post(); ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class('event-single'); ?>>
                <?php do_action('church_events_before_single_event'); ?>

                <header class="event-header">
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
                    <?php if (has_post_thumbnail()) : ?>
                        <div class="event-thumbnail">
                            <?php the_post_thumbnail('large'); ?>
                        </div>
                    <?php endif; ?>

                    <h1 class="event-title">
                        <?php the_title(); ?>
                    </h1>

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
                        <div class="event-meta">
                            <div class="event-datetime">
                                <span class="meta-label">
                                    <?php _e('Date & Time:', 'church-events-manager'); ?>
                                </span>
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
                            </div>

                            <?php if ($event_meta->location) : ?>
                                <div class="event-location">
                                    <span class="meta-label">
                                        <?php _e('Location:', 'church-events-manager'); ?>
                                    </span>
                                    <?php echo esc_html($event_meta->location); ?>
                                    
                                    <?php if (\ChurchEventsManager\Maps\GoogleMapsHandler::is_maps_enabled()) : ?>
                                        <div id="event-map" class="event-map"></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($event_meta->is_recurring) : ?>
                                <div class="event-recurring">
                                    <span class="meta-label">
                                        <?php _e('Recurring:', 'church-events-manager'); ?>
                                    </span>
                                    <?php 
                                    $patterns = \ChurchEventsManager\I18n\Translator::get_recurring_patterns();
                                    echo esc_html($patterns[$event_meta->recurring_pattern] ?? '');
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </header>

                <div class="event-content">
                    <?php the_content(); ?>
                </div>

                <!-- RSVP section removed -->

                <footer class="event-footer">
                    <?php 
                    // Series at a glance: upcoming dates for recurring events
                    if (!empty($event_meta) && !empty($event_meta->is_recurring)) :
                        $now = current_time('mysql');
                        $horizon = date('Y-m-d H:i:s', strtotime('+6 months', strtotime($now)));
                        $event_row = (object) array_merge((array)$event_meta, ['ID' => get_the_ID()]);
                        $upcoming = \ChurchEventsManager\Events\RecurrenceExpander::expandInRange($event_row, $now, $horizon);

                        // Group occurrences by month label
                        $groups = [];
                        foreach ($upcoming as $occ) {
                            $monthKey = date('Y-m', strtotime($occ->event_date));
                            $monthLabel = date_i18n('F Y', strtotime($occ->event_date));
                            if (!isset($groups[$monthKey])) { $groups[$monthKey] = ['label' => $monthLabel, 'items' => []]; }
                            $groups[$monthKey]['items'][] = $occ;
                        }

                        // Split into visible (first N) and hidden (rest) lists
                        $limit = 6; $shown = 0; $visible = []; $hidden = [];
                        foreach ($groups as $key => $grp) {
                            $visItems = []; $hidItems = [];
                            foreach ($grp['items'] as $occ) {
                                if ($shown < $limit) { $visItems[] = $occ; $shown++; }
                                else { $hidItems[] = $occ; }
                            }
                            if ($visItems) { $visible[$key] = ['label' => $grp['label'], 'items' => $visItems]; }
                            if ($hidItems) { $hidden[$key] = ['label' => $grp['label'], 'items' => $hidItems]; }
                        }

                        if (!empty($visible)) : ?>
                            <section class="event-series-overview" aria-labelledby="series-title">
                                <h2 id="series-title" class="series-title"><?php _e('Upcoming Dates', 'church-events-manager'); ?></h2>
                                <div class="series-groups series-visible">
                                    <?php foreach ($visible as $grp) : ?>
                                        <div class="series-group">
                                            <div class="series-month"><?php echo esc_html($grp['label']); ?></div>
                                            <ul class="series-dates">
                                                <?php foreach ($grp['items'] as $occ) : ?>
                                                    <li class="series-date">
                                                        <a href="<?php echo esc_url(church_events_get_occurrence_link(get_the_ID(), $occ->event_date)); ?>">
                                                            <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($occ->event_date))); ?>
                                                        </a>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <?php 
                                // Hidden groups rendering
                                $remaining = 0; foreach ($hidden as $hg) { $remaining += count($hg['items']); }
                                if (!empty($hidden)) : ?>
                                    <div class="series-groups series-hidden" style="display:none;">
                                        <?php foreach ($hidden as $grp) : ?>
                                            <div class="series-group">
                                                <div class="series-month"><?php echo esc_html($grp['label']); ?></div>
                                                <ul class="series-dates">
                                                    <?php foreach ($grp['items'] as $occ) : ?>
                                                        <li class="series-date">
                                                            <a href="<?php echo esc_url(church_events_get_occurrence_link(get_the_ID(), $occ->event_date)); ?>">
                                                                <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($occ->event_date))); ?>
                                                            </a>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="button button-secondary series-toggle" aria-expanded="false"
                                            data-show-text="<?php esc_attr_e('Show all dates', 'church-events-manager'); ?>"
                                            data-hide-text="<?php esc_attr_e('Show fewer dates', 'church-events-manager'); ?>">
                                        <?php printf(esc_html__('Show all dates (%d more)', 'church-events-manager'), $remaining); ?>
                                    </button>
                                    <script>
                                    (function(){
                                        var btn = document.querySelector('.series-toggle');
                                        if (!btn) return;
                                        var hidden = document.querySelector('.series-hidden');
                                        btn.addEventListener('click', function(){
                                            var expanded = btn.getAttribute('aria-expanded') === 'true';
                                            if (expanded) {
                                                hidden.style.display = 'none';
                                                btn.setAttribute('aria-expanded', 'false');
                                                btn.textContent = btn.getAttribute('data-show-text');
                                            } else {
                                                hidden.style.display = '';
                                                btn.setAttribute('aria-expanded', 'true');
                                                btn.textContent = btn.getAttribute('data-hide-text');
                                            }
                                        });
                                    })();
                                    </script>
                                <?php endif; ?>
                            </section>
                        <?php endif; 
                    endif; ?>

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

            <?php 
            if (comments_open() || get_comments_number()) {
                comments_template();
            }
            ?>
        <?php endwhile; ?>
    </main>
</div>

<?php
get_sidebar();
get_footer();