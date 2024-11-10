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

                <?php if (is_user_logged_in()) : ?>
                    <div class="event-rsvp">
                        <h3><?php _e('RSVP', 'church-events-manager'); ?></h3>
                        <?php do_action('church_events_before_rsvp_form'); ?>
                        
                        <div id="rsvp-form" data-event-id="<?php the_ID(); ?>">
                            <!-- RSVP form will be loaded via AJAX -->
                        </div>

                        <?php do_action('church_events_after_rsvp_form'); ?>
                    </div>
                <?php endif; ?>

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