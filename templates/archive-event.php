<?php
/**
 * The template for displaying event archives
 */

// Enqueue public styles for event archive/search pages
wp_enqueue_style('cem-public', CEM_PLUGIN_URL . 'assets/css/public.css', [], CEM_VERSION);
wp_enqueue_style('cem-search', CEM_PLUGIN_URL . 'assets/css/search.css', [], CEM_VERSION);

get_header();
?>

<div id="primary" class="content-area cem-events-archive">
    <main id="main" class="site-main">
        <header class="page-header">
            <h1 class="page-title">
                <?php 
                if (is_tax('event_category')) {
                    single_term_title(__('Events Category: ', 'church-events-manager'));
                } else {
                    _e('Church Events', 'church-events-manager');
                }
                ?>
            </h1>
            <?php 
            // Inline search form for events
            echo \ChurchEventsManager\Search\SearchHandler::get_search_form();
            ?>
        </header>

        <?php if (have_posts()) : ?>
            <div class="events-filter">
                <?php do_action('church_events_before_filter'); ?>
                
                <form class="events-filter-form" method="get">
                    <?php 
                    // Category filter
                    $categories = get_terms([
                        'taxonomy' => 'event_category',
                        'hide_empty' => true
                    ]);
                    if ($categories) : 
                    ?>
                        <select name="event_category">
                            <option value=""><?php _e('All Categories', 'church-events-manager'); ?></option>
                            <?php foreach ($categories as $category) : ?>
                                <option value="<?php echo esc_attr($category->slug); ?>" 
                                    <?php selected(get_query_var('event_category'), $category->slug); ?>>
                                    <?php echo esc_html($category->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>

                    <button type="submit" class="button">
                        <?php _e('Filter', 'church-events-manager'); ?>
                    </button>
                </form>

                <?php do_action('church_events_after_filter'); ?>
            </div>

            <div class="events-list">
                <?php while (have_posts()) : the_post(); ?>
                    <?php do_action('church_events_before_event'); ?>
                    
                    <article id="post-<?php the_ID(); ?>" <?php post_class('event-item'); ?>>
                        <?php do_action('church_events_before_event_content'); ?>

                        <?php if (has_post_thumbnail()) : ?>
                            <div class="event-thumbnail">
                                <?php the_post_thumbnail('medium'); ?>
                            </div>
                        <?php endif; ?>

                        <div class="event-content">
                            <header class="event-header">
                                <h2 class="event-title">
                                    <a href="<?php the_permalink(); ?>">
                                        <?php the_title(); ?>
                                    </a>
                                </h2>

                                <?php do_action('church_events_after_title'); ?>

                                <div class="event-meta">
                                    <?php 
                                    $event_meta = church_events_get_meta(get_the_ID());
                                    if ($event_meta) :
                                        $date = \ChurchEventsManager\I18n\Translator::format_datetime($event_meta->event_date);
                                    ?>
                                        <span class="event-date">
                                            <?php echo esc_html($date); ?>
                                        </span>

                                        <?php if ($event_meta->location) : ?>
                                            <span class="event-location">
                                                <?php echo esc_html($event_meta->location); ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </header>

                            <div class="event-excerpt">
                                <?php the_excerpt(); ?>
                            </div>

                            <footer class="event-footer">
                                <a href="<?php the_permalink(); ?>" class="button">
                                    <?php _e('View Details', 'church-events-manager'); ?>
                                </a>
                            </footer>

                            <?php do_action('church_events_after_event_content'); ?>
                        </div>
                    </article>

                    <?php do_action('church_events_after_event'); ?>
                <?php endwhile; ?>
            </div>

            <?php the_posts_pagination(); ?>

        <?php else : ?>
            <p class="no-events">
                <?php _e('No events found.', 'church-events-manager'); ?>
            </p>
        <?php endif; ?>
    </main>
</div>

<?php
// Removed sidebar to prevent default blog widgets on event pages
get_footer();