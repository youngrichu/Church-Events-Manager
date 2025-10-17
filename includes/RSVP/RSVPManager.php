<?php
namespace ChurchEventsManager\RSVP;

class RSVPManager {
    public function __construct() {
        // No need for register_rsvp_endpoints here as it's handled in EventsController
    }

    public function create_rsvp($event_id, $user_id, $status = 'attending') {
        global $wpdb;

        // Check if event exists
        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'church_event') {
            return new \WP_Error('invalid_event', 'Event not found');
        }

        // Check if user already has RSVP
        $existing_rsvp = $this->get_user_rsvp($event_id, $user_id);
        if ($existing_rsvp) {
            return new \WP_Error('duplicate_rsvp', 'User has already RSVP\'d to this event');
        }

        // Insert RSVP (no capacity restriction)
        $result = $wpdb->insert(
            $wpdb->prefix . 'cem_rsvp',
            [
                'event_id' => $event_id,
                'user_id' => $user_id,
                'status' => $status,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%s', '%s']
        );

        if ($result === false) {
            return new \WP_Error('db_error', 'Failed to create RSVP');
        }

        do_action('cem_rsvp_created', $event_id, $user_id, $status);
        return true;
    }

    public function update_rsvp($event_id, $user_id, $status) {
        global $wpdb;

        $existing_rsvp = $this->get_user_rsvp($event_id, $user_id);
        if (!$existing_rsvp) {
            return new \WP_Error('not_found', 'No existing RSVP found');
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'cem_rsvp',
            [
                'status' => $status,
                'updated_at' => current_time('mysql')
            ],
            [
                'event_id' => $event_id,
                'user_id' => $user_id
            ],
            ['%s', '%s'],
            ['%d', '%d']
        );

        if ($result === false) {
            return new \WP_Error('db_error', 'Failed to update RSVP');
        }

        do_action('cem_rsvp_updated', $event_id, $user_id, $status);
        return true;
    }

    public function delete_rsvp($event_id, $user_id) {
        global $wpdb;

        $existing_rsvp = $this->get_user_rsvp($event_id, $user_id);
        if (!$existing_rsvp) {
            return new \WP_Error('not_found', 'No existing RSVP found');
        }

        $result = $wpdb->delete(
            $wpdb->prefix . 'cem_rsvp',
            [
                'event_id' => $event_id,
                'user_id' => $user_id
            ],
            ['%d', '%d']
        );

        if ($result === false) {
            return new \WP_Error('db_error', 'Failed to delete RSVP');
        }

        do_action('cem_rsvp_deleted', $event_id, $user_id);
        return true;
    }

    public function get_user_rsvp($event_id, $user_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cem_rsvp 
            WHERE event_id = %d AND user_id = %d",
            $event_id,
            $user_id
        ));
    }

    public function get_attendee_count($event_id) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cem_rsvp 
            WHERE event_id = %d AND status = 'attending'",
            $event_id
        ));
    }

    private function get_event_meta($event_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cem_event_meta 
            WHERE event_id = %d",
            $event_id
        ));
    }

    public function get_rsvp_status($event_id, $user_id) {
        $rsvp = $this->get_user_rsvp($event_id, $user_id);
        $attendee_count = $this->get_attendee_count($event_id);

        return [
            'user_rsvp' => $rsvp ? $rsvp->status : null,
            'attendee_count' => $attendee_count,
            // removed max_attendees key
            'is_full' => false,
        ];
    }
}