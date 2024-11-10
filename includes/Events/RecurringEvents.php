<?php
namespace ChurchEventsManager\Events;

class RecurringEvents {
    public function __construct() {
        add_action('save_post_church_event', [$this, 'handle_recurring_event'], 20, 3);
        add_action('before_delete_post', [$this, 'handle_recurring_event_deletion']);
    }

    public function handle_recurring_event($post_id, $post, $update) {
        // Check if recurring events are enabled in settings
        $options = get_option('church_events_options', []);
        if (empty($options['enable_recurring']) || $options['enable_recurring'] !== '1') {
            return;
        }

        global $wpdb;
        $event_meta = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cem_event_meta WHERE event_id = %d",
            $post_id
        ));

        if (!$event_meta || !$event_meta->is_recurring) {
            return;
        }

        // Delete existing recurring instances
        $this->delete_recurring_instances($post_id);

        // Generate new recurring instances
        $this->generate_recurring_instances($post_id, $post, $event_meta);
    }

    private function generate_recurring_instances($parent_id, $parent_post, $event_meta) {
        $start_date = new \DateTime($event_meta->event_date);
        $end_date = $event_meta->event_end_date ? new \DateTime($event_meta->event_end_date) : null;
        $duration = $end_date ? $end_date->diff($start_date) : null;

        // Generate instances for the next 6 months
        $limit_date = new \DateTime();
        $limit_date->modify('+6 months');

        $dates = $this->generate_recurring_dates(
            $start_date,
            $limit_date,
            $event_meta->recurring_pattern
        );

        foreach ($dates as $date) {
            $instance_end_date = null;
            if ($duration) {
                $instance_end_date = clone $date;
                $instance_end_date->add($duration);
            }

            $this->create_event_instance(
                $parent_id,
                $parent_post,
                $event_meta,
                $date,
                $instance_end_date
            );
        }
    }

    private function generate_recurring_dates($start_date, $limit_date, $pattern) {
        $dates = [];
        $current_date = clone $start_date;

        while ($current_date <= $limit_date) {
            $dates[] = clone $current_date;

            switch ($pattern) {
                case 'daily':
                    $current_date->modify('+1 day');
                    break;

                case 'weekly':
                    $current_date->modify('+1 week');
                    break;

                case 'monthly':
                    $current_date->modify('+1 month');
                    break;

                default:
                    return $dates;
            }
        }

        return $dates;
    }

    private function create_event_instance($parent_id, $parent_post, $parent_meta, $date, $end_date = null) {
        global $wpdb;

        // Create the event post
        $instance_data = [
            'post_title' => $parent_post->post_title,
            'post_content' => $parent_post->post_content,
            'post_status' => 'publish',
            'post_type' => 'church_event',
            'post_parent' => $parent_id,
        ];

        $instance_id = wp_insert_post($instance_data);

        if (!is_wp_error($instance_id)) {
            // Copy taxonomies
            $taxonomies = get_object_taxonomies('church_event');
            foreach ($taxonomies as $taxonomy) {
                $terms = wp_get_object_terms($parent_id, $taxonomy, ['fields' => 'ids']);
                wp_set_object_terms($instance_id, $terms, $taxonomy);
            }

            // Copy featured image
            if (has_post_thumbnail($parent_id)) {
                $thumbnail_id = get_post_thumbnail_id($parent_id);
                set_post_thumbnail($instance_id, $thumbnail_id);
            }

            // Create event meta
            $meta_data = [
                'event_id' => $instance_id,
                'event_date' => $date->format('Y-m-d H:i:s'),
                'event_end_date' => $end_date ? $end_date->format('Y-m-d H:i:s') : null,
                'location' => $parent_meta->location,
                'is_recurring' => 0, // Instance is not recurring
                'max_attendees' => $parent_meta->max_attendees,
                'is_featured' => $parent_meta->is_featured,
            ];

            $wpdb->insert(
                $wpdb->prefix . 'cem_event_meta',
                $meta_data
            );

            // Store reference to parent event
            update_post_meta($instance_id, '_recurring_parent_id', $parent_id);
        }
    }

    public function delete_recurring_instances($parent_id) {
        $instances = get_posts([
            'post_type' => 'church_event',
            'post_parent' => $parent_id,
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        foreach ($instances as $instance_id) {
            wp_delete_post($instance_id, true);
        }
    }

    public function handle_recurring_event_deletion($post_id) {
        if (get_post_type($post_id) !== 'church_event') {
            return;
        }

        // Delete all recurring instances when parent is deleted
        $this->delete_recurring_instances($post_id);
    }

    public function is_recurring_instance($post_id) {
        return (bool) get_post_meta($post_id, '_recurring_parent_id', true);
    }

    public function get_recurring_parent($post_id) {
        return get_post_meta($post_id, '_recurring_parent_id', true);
    }
} 