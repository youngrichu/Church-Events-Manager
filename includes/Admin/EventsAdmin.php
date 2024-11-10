<?php
namespace ChurchEventsManager\Admin;

class EventsAdmin {
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_event_meta_boxes']);
        add_action('save_post_church_event', [$this, 'save_event_meta']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    public function enqueue_admin_scripts($hook) {
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }

        global $post;
        if ('church_event' !== $post->post_type) {
            return;
        }

        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-datepicker', CEM_PLUGIN_URL . 'assets/css/jquery-ui.min.css');
        wp_enqueue_script('cem-admin', CEM_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], CEM_VERSION, true);
    }

    public function add_event_meta_boxes() {
        add_meta_box(
            'church_event_details',
            __('Event Details', 'church-events-manager'),
            [$this, 'render_event_details_meta_box'],
            'church_event',
            'normal',
            'high'
        );

        add_meta_box(
            'church_event_recurring',
            __('Recurring Settings', 'church-events-manager'),
            [$this, 'render_recurring_meta_box'],
            'church_event',
            'side'
        );

        add_meta_box(
            'church_event_notification',
            __('Notification Settings', 'church-events-manager'),
            [$this, 'render_notification_meta_box'],
            'church_event',
            'side'
        );
    }

    public function render_event_details_meta_box($post) {
        wp_nonce_field('church_event_details', 'church_event_details_nonce');
        
        global $wpdb;
        $event_meta = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cem_event_meta WHERE event_id = %d",
            $post->ID
        ));

        ?>
        <div class="church-event-meta">
            <p>
                <label for="event_date"><?php _e('Event Date:', 'church-events-manager'); ?></label>
                <input type="text" id="event_date" name="event_date" class="datepicker" 
                    value="<?php echo esc_attr($event_meta ? date('Y-m-d', strtotime($event_meta->event_date)) : ''); ?>" required>
                
                <label for="event_time"><?php _e('Time:', 'church-events-manager'); ?></label>
                <input type="time" id="event_time" name="event_time" 
                    value="<?php echo esc_attr($event_meta ? date('H:i', strtotime($event_meta->event_date)) : ''); ?>" required>
            </p>

            <p>
                <label for="event_end_date"><?php _e('End Date:', 'church-events-manager'); ?></label>
                <input type="text" id="event_end_date" name="event_end_date" class="datepicker" 
                    value="<?php echo esc_attr($event_meta && $event_meta->event_end_date ? date('Y-m-d', strtotime($event_meta->event_end_date)) : ''); ?>">
                
                <label for="event_end_time"><?php _e('End Time:', 'church-events-manager'); ?></label>
                <input type="time" id="event_end_time" name="event_end_time" 
                    value="<?php echo esc_attr($event_meta && $event_meta->event_end_date ? date('H:i', strtotime($event_meta->event_end_date)) : ''); ?>">
            </p>

            <p>
                <label for="location"><?php _e('Location:', 'church-events-manager'); ?></label>
                <input type="text" id="location" name="location" class="large-text" 
                    value="<?php echo esc_attr($event_meta ? $event_meta->location : ''); ?>">
            </p>

            <p>
                <label for="max_attendees"><?php _e('Maximum Attendees:', 'church-events-manager'); ?></label>
                <input type="number" id="max_attendees" name="max_attendees" min="0" 
                    value="<?php echo esc_attr($event_meta ? $event_meta->max_attendees : ''); ?>">
            </p>

            <p>
                <label>
                    <input type="checkbox" name="is_featured" value="1" 
                        <?php checked($event_meta && $event_meta->is_featured, 1); ?>>
                    <?php _e('Featured Event', 'church-events-manager'); ?>
                </label>
            </p>
        </div>
        <?php
    }

    public function render_recurring_meta_box($post) {
        global $wpdb;
        $event_meta = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cem_event_meta WHERE event_id = %d",
            $post->ID
        ));

        ?>
        <p>
            <label>
                <input type="checkbox" name="is_recurring" value="1" 
                    <?php checked($event_meta && $event_meta->is_recurring, 1); ?>>
                <?php _e('This is a recurring event', 'church-events-manager'); ?>
            </label>
        </p>

        <div class="recurring-options" style="<?php echo ($event_meta && $event_meta->is_recurring) ? 'display:block;' : 'display:none;'; ?>">
            <p>
                <label for="recurring_pattern"><?php _e('Repeat:', 'church-events-manager'); ?></label>
                <select name="recurring_pattern" id="recurring_pattern">
                    <option value="daily" <?php selected($event_meta ? $event_meta->recurring_pattern : '', 'daily'); ?>>
                        <?php _e('Daily', 'church-events-manager'); ?>
                    </option>
                    <option value="weekly" <?php selected($event_meta ? $event_meta->recurring_pattern : '', 'weekly'); ?>>
                        <?php _e('Weekly', 'church-events-manager'); ?>
                    </option>
                    <option value="monthly" <?php selected($event_meta ? $event_meta->recurring_pattern : '', 'monthly'); ?>>
                        <?php _e('Monthly', 'church-events-manager'); ?>
                    </option>
                </select>
            </p>
        </div>
        <?php
    }

    public function render_notification_meta_box($post) {
        $notify_all = get_post_meta($post->ID, '_notify_all_users', true);
        ?>
        <p>
            <label>
                <input type="checkbox" name="notify_all_users" value="1" 
                    <?php checked($notify_all, 1); ?>>
                <?php _e('Send notification to all users when published', 'church-events-manager'); ?>
            </label>
        </p>
        <?php
    }

    public function save_event_meta($post_id) {
        if (!isset($_POST['church_event_details_nonce']) || 
            !wp_verify_nonce($_POST['church_event_details_nonce'], 'church_event_details')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        global $wpdb;

        $event_date = sprintf(
            '%s %s',
            sanitize_text_field($_POST['event_date']),
            sanitize_text_field($_POST['event_time'])
        );

        $event_end_date = null;
        if (!empty($_POST['event_end_date'])) {
            $event_end_date = sprintf(
                '%s %s',
                sanitize_text_field($_POST['event_end_date']),
                sanitize_text_field($_POST['event_end_time'])
            );
        }

        $data = [
            'event_id' => $post_id,
            'event_date' => $event_date,
            'event_end_date' => $event_end_date,
            'location' => sanitize_text_field($_POST['location']),
            'is_recurring' => isset($_POST['is_recurring']) ? 1 : 0,
            'recurring_pattern' => sanitize_text_field($_POST['recurring_pattern'] ?? ''),
            'max_attendees' => intval($_POST['max_attendees']),
            'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
        ];

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}cem_event_meta WHERE event_id = %d",
            $post_id
        ));

        if ($existing) {
            $wpdb->update(
                "{$wpdb->prefix}cem_event_meta",
                $data,
                ['event_id' => $post_id]
            );
        } else {
            $wpdb->insert(
                "{$wpdb->prefix}cem_event_meta",
                $data
            );
        }

        if (isset($_POST['notify_all_users'])) {
            update_post_meta($post_id, '_notify_all_users', 1);
        } else {
            delete_post_meta($post_id, '_notify_all_users');
        }
    }
} 