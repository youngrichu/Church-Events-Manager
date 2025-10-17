<?php
namespace ChurchEventsManager\Events;

class RecurringEvents {
    public function __construct() {
        // With RRULE-on-read approach, do not generate child posts on save
        // Keeping hook for future features (e.g., cache warming) but making handler a no-op
        add_action('save_post_church_event', [$this, 'handle_recurring_events'], 20, 3);
    }

    public function handle_recurring_events($post_id, $post, $update) {
        // Intentionally no-op to avoid creating child instances
        // Previously this generated weekly child posts until year end.
        return;
    }
}