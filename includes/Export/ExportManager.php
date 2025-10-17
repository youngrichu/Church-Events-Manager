<?php
namespace ChurchEventsManager\Export;

class ExportManager {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_export_page']);
        add_action('admin_post_export_events', [$this, 'handle_events_export']);
        add_action('admin_post_export_rsvps', [$this, 'handle_rsvps_export']);
    }

    public function add_export_page() {
        add_submenu_page(
            'edit.php?post_type=church_event',
            __('Export Events', 'church-events-manager'),
            __('Export', 'church-events-manager'),
            'manage_options',
            'church-events-export',
            [$this, 'render_export_page']
        );
    }

    public function render_export_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Export Events & RSVPs', 'church-events-manager'); ?></h1>

            <div class="card">
                <h2><?php _e('Export Events', 'church-events-manager'); ?></h2>
                <p><?php _e('Export all events to CSV format.', 'church-events-manager'); ?></p>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('export_events', 'export_events_nonce'); ?>
                    <input type="hidden" name="action" value="export_events">
                    
                    <p>
                        <label>
                            <input type="checkbox" name="include_recurring" value="1">
                            <?php _e('Include recurring event instances', 'church-events-manager'); ?>
                        </label>
                    </p>
                    
                    <p>
                        <label for="date_range"><?php _e('Date Range:', 'church-events-manager'); ?></label>
                        <select name="date_range" id="date_range">
                            <option value="all"><?php _e('All Dates', 'church-events-manager'); ?></option>
                            <option value="future"><?php _e('Future Events', 'church-events-manager'); ?></option>
                            <option value="past"><?php _e('Past Events', 'church-events-manager'); ?></option>
                            <option value="custom"><?php _e('Custom Range', 'church-events-manager'); ?></option>
                        </select>
                    </p>

                    <div class="custom-range" style="display: none;">
                        <p>
                            <label for="start_date"><?php _e('Start Date:', 'church-events-manager'); ?></label>
                            <input type="date" name="start_date" id="start_date">
                        </p>
                        <p>
                            <label for="end_date"><?php _e('End Date:', 'church-events-manager'); ?></label>
                            <input type="date" name="end_date" id="end_date">
                        </p>
                    </div>

                    <?php submit_button(__('Export Events', 'church-events-manager')); ?>
                </form>
            </div>

            <div class="card">
                <h2><?php _e('Export RSVPs', 'church-events-manager'); ?></h2>
                <p><?php _e('Export RSVP data for specific events.', 'church-events-manager'); ?></p>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('export_rsvps', 'export_rsvps_nonce'); ?>
                    <input type="hidden" name="action" value="export_rsvps">
                    
                    <p>
                        <label for="event_id"><?php _e('Select Event:', 'church-events-manager'); ?></label>
                        <select name="event_id" id="event_id">
                            <option value="all"><?php _e('All Events', 'church-events-manager'); ?></option>
                            <?php
                            $events = get_posts([
                                'post_type' => 'church_event',
                                'posts_per_page' => -1,
                                'orderby' => 'title',
                                'order' => 'ASC'
                            ]);
                            foreach ($events as $event) {
                                printf(
                                    '<option value="%d">%s</option>',
                                    $event->ID,
                                    esc_html($event->post_title)
                                );
                            }
                            ?>
                        </select>
                    </p>

                    <p>
                        <label>
                            <input type="checkbox" name="include_user_data" value="1">
                            <?php _e('Include user details (name, email)', 'church-events-manager'); ?>
                        </label>
                    </p>

                    <?php submit_button(__('Export RSVPs', 'church-events-manager')); ?>
                </form>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#date_range').on('change', function() {
                $('.custom-range').toggle($(this).val() === 'custom');
            });
        });
        </script>
        <?php
    }

    public function handle_events_export() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        check_admin_referer('export_events', 'export_events_nonce');

        $include_recurring = !empty($_POST['include_recurring']);
        $date_range = $_POST['date_range'];
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';

        global $wpdb;
        
        $query = "SELECT p.*, em.* 
                 FROM {$wpdb->posts} p
                 JOIN {$wpdb->prefix}cem_event_meta em ON p.ID = em.event_id
                 WHERE p.post_type = 'church_event'
                 AND p.post_status = 'publish'";

        if ($date_range === 'future') {
            $query .= $wpdb->prepare(" AND em.event_date >= %s", current_time('mysql'));
        } elseif ($date_range === 'past') {
            $query .= $wpdb->prepare(" AND em.event_date < %s", current_time('mysql'));
        } elseif ($date_range === 'custom' && $start_date && $end_date) {
            $query .= $wpdb->prepare(" AND em.event_date BETWEEN %s AND %s", $start_date, $end_date);
        }

        if (!$include_recurring) {
            $query .= " AND em.is_recurring = 0";
        }

        $events = $wpdb->get_results($query);

        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=events-export-' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        // Add UTF-8 BOM for Excel
        fputs($output, "\xEF\xBB\xBF");

        // CSV headers aligned with importer expectations
        fputcsv($output, [
            'title',
            'description',
            'date',
            'end_date',
            'location',
            'categories',
            'is_recurring',
            'recurring_pattern'
        ]);

        foreach ($events as $event) {
            $rsvp_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}cem_rsvp WHERE event_id = %d AND status = 'attending'",
                $event->ID
            ));

            $categories = wp_get_post_terms($event->ID, 'event_category', ['fields' => 'names']);

            fputcsv($output, [
                $event->post_title,
                wp_strip_all_tags($event->post_content),
                $event->event_date,
                $event->event_end_date ?: '',
                $event->location,
                implode(', ', $categories),
                (int) $event->is_recurring,
                $event->recurring_pattern
            ]);
        }

        fclose($output);
        exit;
    }

    public function handle_rsvps_export() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        check_admin_referer('export_rsvps', 'export_rsvps_nonce');

        $event_id = $_POST['event_id'];
        $include_user_data = !empty($_POST['include_user_data']);

        global $wpdb;

        $query = "SELECT r.*, p.post_title as event_title 
                 FROM {$wpdb->prefix}cem_rsvp r
                 JOIN {$wpdb->posts} p ON p.ID = r.event_id
                 WHERE 1=1";

        if ($event_id !== 'all') {
            $query .= $wpdb->prepare(" AND r.event_id = %d", $event_id);
        }

        $rsvps = $wpdb->get_results($query);

        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=rsvps-export-' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        // Add UTF-8 BOM for Excel
        fputs($output, "\xEF\xBB\xBF");

        // CSV headers
        $headers = ['Event', 'Status', 'RSVP Date'];
        if ($include_user_data) {
            $headers = array_merge($headers, ['User Name', 'User Email']);
        }
        fputcsv($output, $headers);

        foreach ($rsvps as $rsvp) {
            $row = [
                $rsvp->event_title,
                $rsvp->status,
                $rsvp->created_at
            ];

            if ($include_user_data) {
                $user = get_userdata($rsvp->user_id);
                $row[] = $user ? $user->display_name : '';
                $row[] = $user ? $user->user_email : '';
            }

            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }
}