<?php
namespace ChurchEventsManager\Cache;

class EventsCache {
    private $cache_group = 'church_events_manager';
    private $cache_time = 3600; // 1 hour default

    public function __construct() {
        // Set cache time from settings
        $options = get_option('church_events_options', []);
        if (!empty($options['cache_duration'])) {
            $this->cache_time = intval($options['cache_duration']) * 60; // Convert minutes to seconds
        }

        // Clear cache when events are modified
        add_action('save_post_church_event', [$this, 'clear_event_cache'], 10, 3);
        add_action('delete_post', [$this, 'clear_event_cache'], 10);
        add_action('cem_rsvp_created', [$this, 'clear_event_rsvp_cache'], 10, 2);
        add_action('cem_rsvp_updated', [$this, 'clear_event_rsvp_cache'], 10, 2);
        add_action('cem_rsvp_deleted', [$this, 'clear_event_rsvp_cache'], 10, 2);
    }

    /**
     * Get cached events list
     */
    public function get_events($args = []) {
        $cache_key = $this->generate_cache_key('events_list', $args);
        $cached_data = wp_cache_get($cache_key, $this->cache_group);

        if (false !== $cached_data) {
            return $cached_data;
        }

        global $wpdb;
        
        $query = "SELECT p.*, em.* 
                 FROM {$wpdb->posts} p
                 JOIN {$wpdb->prefix}cem_event_meta em ON p.ID = em.event_id
                 WHERE p.post_type = 'church_event'
                 AND p.post_status = 'publish'";

        // Add filters based on args
        if (!empty($args['category'])) {
            $query .= $wpdb->prepare(" AND p.ID IN (\n                SELECT object_id FROM {$wpdb->term_relationships} tr\n                JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id\n                WHERE tt.taxonomy = 'event_category' AND tt.term_id = %d\n            )", $args['category']);
        }

        // removed featured filter

        if (!empty($args['upcoming'])) {
            $query .= $wpdb->prepare(" AND em.event_date >= %s", current_time('mysql'));
        }

        $query .= " ORDER BY em.event_date ASC";

        if (!empty($args['limit'])) {
            $query .= $wpdb->prepare(" LIMIT %d", $args['limit']);
        }

        $events = $wpdb->get_results($query);
        wp_cache_set($cache_key, $events, $this->cache_group, $this->cache_time);

        return $events;
    }

    /**
     * Get cached single event
     */
    public function get_event($event_id) {
        $cache_key = $this->generate_cache_key('single_event', ['id' => $event_id]);
        $cached_data = wp_cache_get($cache_key, $this->cache_group);

        if (false !== $cached_data) {
            return $cached_data;
        }

        global $wpdb;
        
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT p.*, em.* 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->prefix}cem_event_meta em ON p.ID = em.event_id
            WHERE p.ID = %d AND p.post_status = 'publish'",
            $event_id
        ));

        if ($event) {
            wp_cache_set($cache_key, $event, $this->cache_group, $this->cache_time);
        }

        return $event;
    }

    /**
     * Get cached RSVP count
     */
    public function get_rsvp_count($event_id) {
        $cache_key = $this->generate_cache_key('rsvp_count', ['id' => $event_id]);
        $cached_data = wp_cache_get($cache_key, $this->cache_group);

        if (false !== $cached_data) {
            return $cached_data;
        }

        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cem_rsvp 
            WHERE event_id = %d AND status = 'attending'",
            $event_id
        ));

        wp_cache_set($cache_key, $count, $this->cache_group, $this->cache_time);

        return $count;
    }

    /**
     * Clear event-related caches
     */
    public function clear_event_cache($post_id, $post = null, $update = null) {
        if (get_post_type($post_id) !== 'church_event') {
            return;
        }

        wp_cache_delete($this->generate_cache_key('single_event', ['id' => $post_id]), $this->cache_group);
        wp_cache_delete($this->generate_cache_key('events_list', []), $this->cache_group);
        wp_cache_delete($this->generate_cache_key('events_list', ['upcoming' => true]), $this->cache_group);
        // removed featured cache key
    }

    /**
     * Clear RSVP-related caches
     */
    public function clear_event_rsvp_cache($event_id, $user_id) {
        wp_cache_delete($this->generate_cache_key('rsvp_count', ['id' => $event_id]), $this->cache_group);
        wp_cache_delete($this->generate_cache_key('single_event', ['id' => $event_id]), $this->cache_group);
    }

    /**
     * Generate cache key based on parameters
     */
    private function generate_cache_key($base, $args = []) {
        $key = $base;
        if (!empty($args)) {
            $key .= '_' . md5(serialize($args));
        }
        return $key;
    }

    /**
     * Clear all plugin caches
     */
    public function clear_all_caches() {
        global $wpdb;
        
        // Clear all transients with our prefix
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_" . $this->cache_group . "%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_timeout_" . $this->cache_group . "%'");
        
        // Clear object cache if available
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group($this->cache_group);
        } else {
            wp_cache_flush();
        }
    }
}