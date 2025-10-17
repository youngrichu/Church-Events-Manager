<?php
namespace ChurchEventsManager\Import;

class ImportManager {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_import_page']);
        add_action('admin_post_import_events', [$this, 'handle_import']);
        add_action('admin_notices', [$this, 'display_import_notices']);
    }

    public function add_import_page() {
        add_submenu_page(
            'edit.php?post_type=church_event',
            __('Import Events', 'church-events-manager'),
            __('Import', 'church-events-manager'),
            'manage_options',
            'church-events-import',
            [$this, 'render_import_page']
        );
    }

    public function render_import_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Import Events', 'church-events-manager'); ?></h1>

            <div class="card">
                <h2><?php _e('CSV Import', 'church-events-manager'); ?></h2>
                <p><?php _e('Import events from a CSV file.', 'church-events-manager'); ?></p>
                
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
                    <?php wp_nonce_field('import_events', 'import_events_nonce'); ?>
                    <input type="hidden" name="action" value="import_events">
                    <input type="hidden" name="import_type" value="csv">

                    <p>
                        <label for="csv_file"><?php _e('Choose CSV File:', 'church-events-manager'); ?></label>
                        <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                    </p>

                    <p>
                        <label>
                            <input type="checkbox" name="skip_duplicates" value="1" checked>
                            <?php _e('Skip duplicate events', 'church-events-manager'); ?>
                        </label>
                    </p>

                    <?php submit_button(__('Import CSV', 'church-events-manager')); ?>
                </form>

                <h3><?php _e('CSV Format', 'church-events-manager'); ?></h3>
                <p><?php _e('Your CSV file should include the following columns:', 'church-events-manager'); ?></p>
                <ul>
                    <li>title (required)</li>
                    <li>description</li>
                    <li>date (required, format: YYYY-MM-DD HH:mm:ss)</li>
                    <li>end_date (format: YYYY-MM-DD HH:mm:ss)</li>
                    <li>location</li>
                    <li>categories (comma-separated)</li>
                    <li>is_recurring (1 or 0)</li>
                    <li>recurring_pattern (daily, weekly, monthly)</li>
                    <!-- removed max_attendees -->
                </ul>
            </div>

            <div class="card">
                <h2><?php _e('iCal Import', 'church-events-manager'); ?></h2>
                <p><?php _e('Import events from an iCal (.ics) file.', 'church-events-manager'); ?></p>
                
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
                    <?php wp_nonce_field('import_events', 'import_events_nonce'); ?>
                    <input type="hidden" name="action" value="import_events">
                    <input type="hidden" name="import_type" value="ical">

                    <p>
                        <label for="ical_file"><?php _e('Choose iCal File:', 'church-events-manager'); ?></label>
                        <input type="file" name="ical_file" id="ical_file" accept=".ics" required>
                    </p>

                    <p>
                        <label>
                            <input type="checkbox" name="skip_duplicates" value="1" checked>
                            <?php _e('Skip duplicate events', 'church-events-manager'); ?>
                        </label>
                    </p>

                    <?php submit_button(__('Import iCal', 'church-events-manager')); ?>
                </form>
            </div>
        </div>
        <?php
    }

    public function handle_import() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        check_admin_referer('import_events', 'import_events_nonce');

        $import_type = $_POST['import_type'];
        $skip_duplicates = !empty($_POST['skip_duplicates']);

        try {
            switch ($import_type) {
                case 'csv':
                    $result = $this->import_from_csv($_FILES['csv_file'], $skip_duplicates);
                    break;
                case 'ical':
                    $result = $this->import_from_ical($_FILES['ical_file'], $skip_duplicates);
                    break;
                default:
                    throw new \Exception(__('Invalid import type', 'church-events-manager'));
            }

            set_transient('cem_import_message', [
                'type' => 'success',
                'message' => sprintf(
                    __('Successfully imported %d events.', 'church-events-manager'),
                    $result['imported']
                )
            ], 45);

        } catch (\Exception $e) {
            set_transient('cem_import_message', [
                'type' => 'error',
                'message' => $e->getMessage()
            ], 45);
        }

        wp_redirect(admin_url('edit.php?post_type=church_event&page=church-events-import'));
        exit;
    }

    private function import_from_csv($file, $skip_duplicates) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception(__('Error uploading file', 'church-events-manager'));
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            throw new \Exception(__('Error reading file', 'church-events-manager'));
        }

        $headers = fgetcsv($handle);
+        // Normalize CSV headers: trim, lowercase, and strip UTF-8 BOM to avoid "title" mismatch
+        if ($headers && is_array($headers)) {
+            $headers = array_map(function($h) {
+                $h = (string) $h;
+                // Remove BOM if present on first cell or any header
+                $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
+                return strtolower(trim($h));
+            }, $headers);
+        }
         $required_fields = ['title', 'date'];
-        $missing_fields = array_diff($required_fields, $headers);
+        $missing_fields = array_diff($required_fields, $headers);

        if (!empty($missing_fields)) {
            fclose($handle);
            throw new \Exception(sprintf(
                __('Missing required fields: %s', 'church-events-manager'),
                implode(', ', $missing_fields)
            ));
        }

        $imported = 0;
        $row = 2; // Start at row 2 (after headers)

        while (($data = fgetcsv($handle)) !== false) {
            try {
                $event_data = array_combine($headers, $data);
                
                if ($skip_duplicates && $this->event_exists($event_data['title'], $event_data['date'])) {
                    continue;
                }

                $this->create_event($event_data);
                $imported++;
            } catch (\Exception $e) {
                // Log error but continue importing
                error_log(sprintf(
                    'Error importing event at row %d: %s',
                    $row,
                    $e->getMessage()
                ));
            }
            $row++;
        }

        fclose($handle);
        return ['imported' => $imported];
    }

    private function import_from_ical($file, $skip_duplicates) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception(__('Error uploading file', 'church-events-manager'));
        }

        $ical_string = file_get_contents($file['tmp_name']);
        if (!$ical_string) {
            throw new \Exception(__('Error reading file', 'church-events-manager'));
        }

        // Basic iCal parsing
        preg_match_all('/BEGIN:VEVENT.*?END:VEVENT/s', $ical_string, $events);
        
        $imported = 0;
        foreach ($events[0] as $event) {
            try {
                preg_match('/SUMMARY:(.*)/', $event, $summary);
                preg_match('/DTSTART:(.*)/', $event, $start);
                preg_match('/DTEND:(.*)/', $event, $end);
                preg_match('/LOCATION:(.*)/', $event, $location);
                preg_match('/DESCRIPTION:(.*)/', $event, $description);

                $event_data = [
                    'title' => $summary[1] ?? '',
                    'date' => $this->format_ical_date($start[1] ?? ''),
                    'end_date' => $this->format_ical_date($end[1] ?? ''),
                    'location' => $location[1] ?? '',
                    'description' => $description[1] ?? ''
                ];

                if ($skip_duplicates && $this->event_exists($event_data['title'], $event_data['date'])) {
                    continue;
                }

                $this->create_event($event_data);
                $imported++;
            } catch (\Exception $e) {
                // Log error but continue importing
                error_log(sprintf(
                    'Error importing iCal event: %s',
                    $e->getMessage()
                ));
            }
        }

        return ['imported' => $imported];
    }

    private function event_exists($title, $date) {
        global $wpdb;
        
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT p.ID 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->prefix}cem_event_meta em ON p.ID = em.event_id
            WHERE p.post_title = %s
            AND em.event_date = %s
            AND p.post_type = 'church_event'",
            $title,
            $date
        ));
    }

    private function create_event($data) {
        $post_data = [
            'post_title' => sanitize_text_field($data['title']),
            'post_content' => wp_kses_post($data['description'] ?? ''),
            'post_status' => 'publish',
            'post_type' => 'church_event'
        ];

        $post_id = wp_insert_post($post_data);
        if (is_wp_error($post_id)) {
            throw new \Exception($post_id->get_error_message());
        }

        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'cem_event_meta',
            [
                'event_id' => $post_id,
                'event_date' => $data['date'],
                'event_end_date' => $data['end_date'] ?? null,
                'location' => $data['location'] ?? '',
                'is_recurring' => $data['is_recurring'] ?? 0,
                'recurring_pattern' => $data['recurring_pattern'] ?? ''
            ]
        );

        if (!empty($data['categories'])) {
            $categories = array_map('trim', explode(',', $data['categories']));
            wp_set_object_terms($post_id, $categories, 'event_category');
        }

        return $post_id;
    }

    private function format_ical_date($date) {
        // Basic iCal date formatting (you might want to enhance this)
        $formatted = preg_replace('/[^0-9]/', '', $date);
        return sprintf(
            '%s-%s-%s %s:%s:%s',
            substr($formatted, 0, 4),
            substr($formatted, 4, 2),
            substr($formatted, 6, 2),
            substr($formatted, 8, 2),
            substr($formatted, 10, 2),
            substr($formatted, 12, 2)
        );
    }

    public function display_import_notices() {
        $message = get_transient('cem_import_message');
        if ($message) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($message['type']),
                esc_html($message['message'])
            );
            delete_transient('cem_import_message');
        }
    }
}