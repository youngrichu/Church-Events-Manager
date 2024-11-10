<?php
namespace ChurchEventsManager\Search;

class SearchHandler {
    public function __construct() {
        add_action('pre_get_posts', [$this, 'modify_search_query']);
        add_filter('posts_join', [$this, 'search_join']);
        add_filter('posts_where', [$this, 'search_where']);
        add_filter('posts_groupby', [$this, 'search_groupby']);
    }

    /**
     * Modify the main query for event searches
     */
    public function modify_search_query($query) {
        if (!is_admin() && $query->is_main_query() && $query->is_search()) {
            // Check if we're specifically searching events
            if (isset($_GET['post_type']) && $_GET['post_type'] === 'church_event') {
                $query->set('post_type', 'church_event');
                
                // Handle date filtering
                if (!empty($_GET['date_from'])) {
                    $this->add_date_filter($query, 'from');
                }
                if (!empty($_GET['date_to'])) {
                    $this->add_date_filter($query, 'to');
                }

                // Handle category filtering
                if (!empty($_GET['event_category'])) {
                    $query->set('tax_query', [
                        [
                            'taxonomy' => 'event_category',
                            'field' => 'slug',
                            'terms' => $_GET['event_category']
                        ]
                    ]);
                }

                // Handle location filtering
                if (!empty($_GET['location'])) {
                    add_filter('posts_where', [$this, 'filter_by_location']);
                }
            }
        }
    }

    /**
     * Join the event meta table for searches
     */
    public function search_join($join) {
        global $wpdb;
        
        if ($this->is_event_search()) {
            $join .= " LEFT JOIN {$wpdb->prefix}cem_event_meta em ON {$wpdb->posts}.ID = em.event_id ";
        }
        
        return $join;
    }

    /**
     * Modify where clause for event searches
     */
    public function search_where($where) {
        global $wpdb;
        
        if ($this->is_event_search() && !empty($_GET['s'])) {
            $search_term = '%' . $wpdb->esc_like($_GET['s']) . '%';
            
            $where .= $wpdb->prepare(
                " AND (
                    {$wpdb->posts}.post_title LIKE %s
                    OR {$wpdb->posts}.post_content LIKE %s
                    OR em.location LIKE %s
                )",
                $search_term,
                $search_term,
                $search_term
            );
        }
        
        return $where;
    }

    /**
     * Add groupby clause to prevent duplicate results
     */
    public function search_groupby($groupby) {
        global $wpdb;
        
        if ($this->is_event_search()) {
            $groupby = "{$wpdb->posts}.ID";
        }
        
        return $groupby;
    }

    /**
     * Filter events by location
     */
    public function filter_by_location($where) {
        global $wpdb;
        
        if (!empty($_GET['location'])) {
            $location = '%' . $wpdb->esc_like($_GET['location']) . '%';
            $where .= $wpdb->prepare(" AND em.location LIKE %s", $location);
        }
        
        return $where;
    }

    /**
     * Add date filtering to query
     */
    private function add_date_filter($query, $type) {
        global $wpdb;
        
        $meta_query = $query->get('meta_query', []);
        
        if ($type === 'from') {
            $meta_query[] = [
                'key' => 'event_date',
                'value' => sanitize_text_field($_GET['date_from']),
                'compare' => '>=',
                'type' => 'DATE'
            ];
        } else {
            $meta_query[] = [
                'key' => 'event_date',
                'value' => sanitize_text_field($_GET['date_to']),
                'compare' => '<=',
                'type' => 'DATE'
            ];
        }
        
        $query->set('meta_query', $meta_query);
    }

    /**
     * Check if current query is an event search
     */
    private function is_event_search() {
        return is_search() && 
               isset($_GET['post_type']) && 
               $_GET['post_type'] === 'church_event';
    }

    /**
     * Get search form HTML
     */
    public static function get_search_form() {
        ob_start();
        ?>
        <form role="search" method="get" class="church-events-search-form" action="<?php echo esc_url(home_url('/')); ?>">
            <input type="hidden" name="post_type" value="church_event">
            
            <div class="search-row">
                <div class="search-field">
                    <label for="events-search"><?php _e('Search Events:', 'church-events-manager'); ?></label>
                    <input type="search" id="events-search" name="s" 
                           value="<?php echo get_search_query(); ?>" 
                           placeholder="<?php esc_attr_e('Search events...', 'church-events-manager'); ?>">
                </div>

                <div class="search-field">
                    <label for="event-category"><?php _e('Category:', 'church-events-manager'); ?></label>
                    <select name="event_category" id="event-category">
                        <option value=""><?php _e('All Categories', 'church-events-manager'); ?></option>
                        <?php
                        $categories = get_terms([
                            'taxonomy' => 'event_category',
                            'hide_empty' => true
                        ]);
                        foreach ($categories as $category) {
                            printf(
                                '<option value="%s" %s>%s</option>',
                                esc_attr($category->slug),
                                selected($category->slug, $_GET['event_category'] ?? '', false),
                                esc_html($category->name)
                            );
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div class="search-row">
                <div class="search-field">
                    <label for="date-from"><?php _e('From:', 'church-events-manager'); ?></label>
                    <input type="date" id="date-from" name="date_from" 
                           value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>">
                </div>

                <div class="search-field">
                    <label for="date-to"><?php _e('To:', 'church-events-manager'); ?></label>
                    <input type="date" id="date-to" name="date_to" 
                           value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>">
                </div>

                <div class="search-field">
                    <label for="location"><?php _e('Location:', 'church-events-manager'); ?></label>
                    <input type="text" id="location" name="location" 
                           value="<?php echo esc_attr($_GET['location'] ?? ''); ?>"
                           placeholder="<?php esc_attr_e('Enter location...', 'church-events-manager'); ?>">
                </div>
            </div>

            <div class="search-submit">
                <button type="submit" class="button">
                    <?php _e('Search Events', 'church-events-manager'); ?>
                </button>
                <?php if (!empty($_GET['s']) || !empty($_GET['event_category']) || !empty($_GET['date_from']) || !empty($_GET['date_to']) || !empty($_GET['location'])): ?>
                    <a href="<?php echo esc_url(get_post_type_archive_link('church_event')); ?>" class="button button-secondary">
                        <?php _e('Clear Filters', 'church-events-manager'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </form>
        <?php
        return ob_get_clean();
    }
} 