<?php
namespace ChurchEventsManager\Admin;

class EventsAdmin {
    // Add a flag to carry invalid end datetime state through redirect
    private $invalid_end = false;
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_event_meta_boxes']);
        add_action('save_post_church_event', [$this, 'save_event_meta']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        // Admin list columns and filters
        add_filter('manage_church_event_posts_columns', [$this, 'add_admin_columns']);
        add_action('manage_church_event_posts_custom_column', [$this, 'render_admin_column'], 10, 2);
        add_filter('manage_edit-church_event_sortable_columns', [$this, 'make_columns_sortable']);
        add_action('restrict_manage_posts', [$this, 'add_admin_filters']);
        add_filter('posts_join', [$this, 'admin_posts_join']);
        add_filter('posts_where', [$this, 'admin_posts_where']);
        add_filter('posts_orderby', [$this, 'admin_posts_orderby']);
        add_filter('posts_groupby', [$this, 'admin_posts_groupby']);
        // Admin notices & redirect flag for validation feedback
        add_action('admin_notices', [$this, 'render_admin_notices']);
        add_filter('redirect_post_location', [$this, 'add_redirect_flag']);
    }

    private function is_events_admin_list() {
        if (!is_admin()) return false;
        global $pagenow;
        if ($pagenow !== 'edit.php') return false;
        $post_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : 'post';
        return $post_type === 'church_event';
    }

    public function enqueue_admin_scripts($hook) {
        // Post editor screens
        if ($hook === 'post.php' || $hook === 'post-new.php') {
            global $post;
            if ($post && $post->post_type === 'church_event') {
                wp_enqueue_style('cem-admin', CEM_PLUGIN_URL . 'assets/css/admin.css', [], CEM_VERSION);
                wp_enqueue_script('cem-admin', CEM_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], CEM_VERSION, true);
            }
            return;
        }
    
        // Events list screen
        if ($hook === 'edit.php' && isset($_GET['post_type']) && sanitize_text_field($_GET['post_type']) === 'church_event') {
            wp_enqueue_style('cem-admin', CEM_PLUGIN_URL . 'assets/css/admin.css', [], CEM_VERSION);
        }
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
            'normal',
            'low'
        );

        // Removed notification meta box; notifications are handled via the church app plugin integration
    }

    public function render_event_details_meta_box($post) {
        wp_nonce_field('church_event_details', 'church_event_details_nonce');
        
        global $wpdb;
        $event_meta = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cem_event_meta WHERE event_id = %d",
            $post->ID
        ));

        ?>
        <div class="church-event-meta cem-card cem-date-time">
            <div class="cem-grid">
                <div class="cem-field" id="start_time_field">
                    <label for="event_time"><?php _e('Start Time', 'church-events-manager'); ?></label>
                    <input type="time" id="event_time" name="event_time" 
                        value="<?php echo esc_attr($event_meta ? date('H:i', strtotime($event_meta->event_date)) : ''); ?>">
                </div>
                <div class="cem-field" id="end_time_field">
                    <label for="event_end_time"><?php _e('End Time', 'church-events-manager'); ?></label>
                    <input type="time" id="event_end_time" name="event_end_time" 
                        value="<?php echo esc_attr($event_meta && $event_meta->event_end_date ? date('H:i', strtotime($event_meta->event_end_date)) : ''); ?>">
                </div>
                <div class="cem-field" id="start_date_field">
                    <label for="event_date"><?php _e('Date', 'church-events-manager'); ?></label>
                    <input type="date" id="event_date" name="event_date" 
                        value="<?php echo esc_attr($event_meta ? date('Y-m-d', strtotime($event_meta->event_date)) : ''); ?>" required>
                </div>
                <div class="cem-field" id="end_date_field">
                    <label for="event_end_date"><?php _e('End date (optional)', 'church-events-manager'); ?></label>
                    <input type="date" id="event_end_date" name="event_end_date" placeholder="YYYY-MM-DD"
                        value="<?php echo esc_attr($event_meta && $event_meta->event_end_date ? date('Y-m-d', strtotime($event_meta->event_end_date)) : ''); ?>">
                </div>
            </div>

            <div class="cem-controls">
                <label>
                    <input type="checkbox" id="is_all_day" name="is_all_day" value="1" 
                        <?php checked($event_meta && isset($event_meta->is_all_day) ? $event_meta->is_all_day : 0, 1); ?>>
                    <?php _e('All day event', 'church-events-manager'); ?>
                </label>
                <label>
                    <input type="checkbox" id="hide_end_time" name="hide_end_time" value="1">
                    <?php _e('Hide end time', 'church-events-manager'); ?>
                </label>
                <label>
                    <input type="checkbox" id="same_day_event" name="same_day_event" value="1">
                    <?php _e('Same day event', 'church-events-manager'); ?>
                </label>
            </div>

            <div class="cem-field" id="location_field" style="margin-top:12px;">
                <label for="location"><?php _e('Location', 'church-events-manager'); ?></label>
                <input type="text" id="location" name="location" class="regular-text" 
                    value="<?php echo esc_attr($event_meta ? $event_meta->location : ''); ?>" />
            </div>

            <?php if (\ChurchEventsManager\Maps\GoogleMapsHandler::is_maps_enabled()) : ?>
                <div class="cem-admin-map-container" style="margin-top:12px;">
                    <div class="cem-map" style="height: 300px;"></div>
                    <div class="cem-location-search" style="margin-top:8px;">
                        <input type="text" id="cem-location-search" 
                               placeholder="<?php esc_attr_e('Search for a location', 'church-events-manager'); ?>" 
                               class="widefat">
                    </div>
                    <div class="cem-location-details">
                        <input type="hidden" id="cem-lat" name="event_lat" value="">
                        <input type="hidden" id="cem-lng" name="event_lng" value="">
                        <input type="hidden" id="cem-formatted-address" name="event_formatted_address" value="">
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_recurring_meta_box($post) {
        global $wpdb;
        $event_meta = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cem_event_meta WHERE event_id = %d",
            $post->ID
        ));

        // Determine current end option selection
        $current_end_option = 'never';
        if (!empty($event_meta) && !empty($event_meta->recurring_end_date)) {
            $current_end_option = 'on_date';
        } elseif (!empty($event_meta) && !empty($event_meta->recurring_count)) {
            $current_end_option = 'after_count';
        }
        $interval_val = (!empty($event_meta) && isset($event_meta->recurring_interval)) ? max(1, (int)$event_meta->recurring_interval) : 1;

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

            <p>
                <label for="recurring_interval"><?php _e('Frequency:', 'church-events-manager'); ?></label>
                <input type="number" min="1" id="recurring_interval" name="recurring_interval" value="<?php echo esc_attr($interval_val); ?>" style="width:90px;" />
                <span class="description"><?php _e('Occurs every N units based on the Repeat selection', 'church-events-manager'); ?></span>
            </p>

            <p>
                <label for="recurrence_ends"><?php _e('Ends:', 'church-events-manager'); ?></label>
                <select name="recurrence_ends" id="recurrence_ends">
                    <option value="never" <?php selected($current_end_option, 'never'); ?>><?php _e('Never', 'church-events-manager'); ?></option>
                    <option value="on_date" <?php selected($current_end_option, 'on_date'); ?>><?php _e('On date', 'church-events-manager'); ?></option>
                    <option value="after_count" <?php selected($current_end_option, 'after_count'); ?>><?php _e('After count', 'church-events-manager'); ?></option>
                </select>
            </p>

            <p class="recurrence-end-date" style="<?php echo ($current_end_option === 'on_date') ? 'display:block;' : 'display:none;'; ?>">
                <label for="recurring_end_date"><?php _e('End Date:', 'church-events-manager'); ?></label>
                <input type="date" id="recurring_end_date" name="recurring_end_date" value="<?php echo esc_attr($event_meta && !empty($event_meta->recurring_end_date) ? date('Y-m-d', strtotime($event_meta->recurring_end_date)) : ''); ?>" />
            </p>

            <p class="recurrence-end-count" style="<?php echo ($current_end_option === 'after_count') ? 'display:block;' : 'display:none;'; ?>">
                <label for="recurring_count"><?php _e('Occurrences:', 'church-events-manager'); ?></label>
                <input type="number" min="1" id="recurring_count" name="recurring_count" value="<?php echo esc_attr($event_meta && !empty($event_meta->recurring_count) ? (int)$event_meta->recurring_count : ''); ?>" style="width:90px;" />
            </p>
        </div>
        <?php
    }

    public function save_event_meta($post_id) {
        // Skip during programmatic generation of recurring instances
        if (!empty($GLOBALS['cem_generating_recurring'])) {
            return;
        }

        // Only enforce nonce when it is present; allow capability-guarded saves from other editors
        if (isset($_POST['church_event_details_nonce'])) {
            if (!wp_verify_nonce($_POST['church_event_details_nonce'], 'church_event_details')) {
                return;
            }
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Ensure correct post type
        if (get_post_type($post_id) !== 'church_event') {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // ---- DEBUG: log incoming payload and context (guarded by WP_DEBUG) ----
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $posted_debug = [];
            foreach (['location','event_date','event_time','event_end_date','event_end_time','is_all_day','is_recurring','recurring_pattern','recurring_interval','recurrence_ends','recurring_end_date','recurring_count','church_event_details_nonce'] as $k) {
                if (isset($_POST[$k])) { $posted_debug[$k] = $_POST[$k]; }
            }
            error_log('CEM: save_event_meta START post_id=' . $post_id . ' type=' . get_post_type($post_id) . ' nonce_present=' . (isset($_POST['church_event_details_nonce']) ? '1' : '0') . ' posted=' . wp_json_encode($posted_debug));
        }
        // ---- END DEBUG ----

        global $wpdb;

        // Fetch existing row to support partial updates (Block Editor may not post all metabox fields)
        $existing_row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cem_event_meta WHERE event_id = %d",
            $post_id
        ));

        // Preserve previous flags/values when inputs are not posted
        $prev_is_all_day = $existing_row && isset($existing_row->is_all_day) ? (int) $existing_row->is_all_day : 0;
        $is_all_day = isset($_POST['is_all_day']) ? 1 : $prev_is_all_day;

        // Build start datetime: support both date+time inputs and datetime-local
        $raw_start = isset($_POST['event_date']) ? sanitize_text_field($_POST['event_date']) : '';
        $start_date_part = '';
        $start_time_part = '';
        if ($raw_start !== '') {
            if (strpos($raw_start, 'T') !== false) {
                list($start_date_part, $start_time_part) = explode('T', $raw_start, 2);
            } else {
                $start_date_part = $raw_start;
                $start_time_part = isset($_POST['event_time']) ? sanitize_text_field($_POST['event_time']) : '';
            }
        }
        // Normalize time to H:i:s if present
        if ($start_time_part && preg_match('/^\d{2}:\d{2}$/', $start_time_part)) {
            $start_time_part .= ':00';
        }
        $event_date_time = $is_all_day ? '00:00:00' : ($start_time_part ?: '00:00:00');
        if ($start_date_part === '') {
            // No date posted: use existing meta value if present; otherwise fall back to the post's publish date
            if ($existing_row && !empty($existing_row->event_date)) {
                $event_date = $existing_row->event_date;
            } else {
                $post_obj = get_post($post_id);
                $event_date = $post_obj && !empty($post_obj->post_date) ? $post_obj->post_date : current_time('mysql');
            }
        } else {
            $event_date = trim($start_date_part . ' ' . $event_date_time);
        }

        // Build end datetime: support both date+time inputs and datetime-local
        $event_end_date = $existing_row && !empty($existing_row->event_end_date) ? $existing_row->event_end_date : null;
        $raw_end = isset($_POST['event_end_date']) ? sanitize_text_field($_POST['event_end_date']) : '';
        if ($raw_end !== '') {
            $end_date_date = '';
            $end_time_part = '';
            if (strpos($raw_end, 'T') !== false) {
                list($end_date_date, $end_time_part) = explode('T', $raw_end, 2);
            } else {
                $end_date_date = $raw_end;
                $end_time_part = isset($_POST['event_end_time']) ? sanitize_text_field($_POST['event_end_time']) : '';
            }
            if ($end_time_part && preg_match('/^\d{2}:\d{2}$/', $end_time_part)) {
                $end_time_part .= ':00';
            }
            $end_date_time = $is_all_day ? '23:59:59' : ($end_time_part ?: '00:00:00');
            $candidate_end = trim($end_date_date . ' ' . $end_date_time);
            // Server-side validation: end must not be before start
            $start_ts = strtotime($event_date);
            $end_ts = strtotime($candidate_end);
            if ($start_ts !== false && $end_ts !== false && $end_ts < $start_ts) {
                // Mark invalid and skip saving end datetime; notify user via admin notice after redirect
                $this->invalid_end = true;
                $event_end_date = null;
            } else {
                $event_end_date = $candidate_end;
            }
        }

        // Build data with graceful fallbacks to existing values for partial updates
        $data = [
            'event_id' => $post_id,
            'event_date' => $event_date,
            'event_end_date' => $event_end_date,
            'location' => isset($_POST['location']) ? sanitize_text_field($_POST['location']) : ($existing_row ? sanitize_text_field($existing_row->location) : ''),
            'is_recurring' => isset($_POST['is_recurring']) ? 1 : ($existing_row && isset($existing_row->is_recurring) ? (int)$existing_row->is_recurring : 0),
            'recurring_pattern' => isset($_POST['recurring_pattern']) ? sanitize_text_field($_POST['recurring_pattern']) : ($existing_row && isset($existing_row->recurring_pattern) ? sanitize_text_field($existing_row->recurring_pattern) : ''),
            'is_all_day' => $is_all_day,
            // New recurrence fields
            'recurring_interval' => isset($_POST['recurring_interval']) ? max(1, (int)sanitize_text_field($_POST['recurring_interval'])) : ($existing_row && isset($existing_row->recurring_interval) ? max(1, (int)$existing_row->recurring_interval) : 1),
            'recurring_end_date' => (function() use ($existing_row) {
                $ends = isset($_POST['recurrence_ends']) ? sanitize_text_field($_POST['recurrence_ends']) : '';
                if ($ends === 'on_date' && !empty($_POST['recurring_end_date'])) {
                    $d = sanitize_text_field($_POST['recurring_end_date']);
                    // store as end of day to include that date
                    return $d . ' 23:59:59';
                }
                // If ends not posted or "never", keep existing value
                return $existing_row && !empty($existing_row->recurring_end_date) ? $existing_row->recurring_end_date : null;
            })(),
            'recurring_count' => (function() use ($existing_row) {
                $ends = isset($_POST['recurrence_ends']) ? sanitize_text_field($_POST['recurrence_ends']) : '';
                if ($ends === 'after_count' && !empty($_POST['recurring_count'])) {
                    return max(1, (int)sanitize_text_field($_POST['recurring_count']));
                }
                // If ends not posted or "never", keep existing value
                return $existing_row && isset($existing_row->recurring_count) ? (int)$existing_row->recurring_count : null;
            })()
        ];

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}cem_event_meta WHERE event_id = %d",
            $post_id
        ));

        if ($existing) {
            // Update without non-existent columns to avoid failing saves
            $wpdb->update(
                "{$wpdb->prefix}cem_event_meta",
                $data,
                ['event_id' => $post_id]
            );
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CEM: save_event_meta UPDATE rows=' . $wpdb->rows_affected . ' error=' . ($wpdb->last_error ?: 'none') . ' data=' . wp_json_encode($data));
            }
        } else {
            $wpdb->insert(
                "{$wpdb->prefix}cem_event_meta",
                $data
            );
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CEM: save_event_meta INSERT rows=' . $wpdb->rows_affected . ' error=' . ($wpdb->last_error ?: 'none') . ' data=' . wp_json_encode($data));
            }
        }

        // Removed per-post notification toggle; notifications are handled via app plugin
    }

    // Admin list columns
    public function add_admin_columns($columns) {
        // Keep existing columns and taxonomy column
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                // Insert after title
                $new['event_datetime'] = __('Date & Time', 'church-events-manager');
                $new['location'] = __('Location', 'church-events-manager');
                $new['event_status'] = __('Status', 'church-events-manager');
                // Add recurring indicator
                $new['recurring'] = __('Recurring', 'church-events-manager');
            }
        }
        return $new;
    }

    public function render_admin_column($column, $post_id) {
        global $wpdb;
        switch ($column) {
            case 'location':
                $loc = $wpdb->get_var($wpdb->prepare("SELECT location FROM {$wpdb->prefix}cem_event_meta WHERE event_id = %d", $post_id));
                echo esc_html($loc ?: '—');
                break;
            case 'event_datetime':
                $row = $wpdb->get_row($wpdb->prepare("SELECT event_date, event_end_date, is_all_day, is_recurring FROM {$wpdb->prefix}cem_event_meta WHERE event_id = %d", $post_id));
                if ($row && $row->event_date) {
                    $start = strtotime($row->event_date);
                    $end = $row->event_end_date ? strtotime($row->event_end_date) : null;
                    $is_all_day = isset($row->is_all_day) ? (int) $row->is_all_day : 0;
                    $is_recurring = isset($row->is_recurring) ? (int) $row->is_recurring : 0;
                    $date_str = date('M j, Y', $start);
                    if ($is_all_day) {
                        echo esc_html($date_str . ($end ? ' — ' . date('M j, Y', $end) : ''));
                        if ($is_recurring) {
                            echo ' <span class="cem-badge cem-badge--recurring" title="' . esc_attr(__('Recurring', 'church-events-manager')) . '">' . esc_html(__('Recurring', 'church-events-manager')) . '</span>';
                        }
                        echo '<br><span class="description">' . esc_html__('All-day', 'church-events-manager') . '</span>';
                    } else {
                        $time_str = date('g:i A', $start);
                        $out = $date_str . ' ' . $time_str;
                        if ($end) {
                            $out .= ' — ' . date('M j, Y g:i A', $end);
                        }
                        echo esc_html($out);
                        if ($is_recurring) {
                            echo ' <span class="cem-badge cem-badge--recurring" title="' . esc_attr(__('Recurring', 'church-events-manager')) . '">' . esc_html(__('Recurring', 'church-events-manager')) . '</span>';
                        }
                    }
                } else {
                    echo '—';
                }
                break;
            case 'event_status':
                $status = get_post_status($post_id);
                $row = $wpdb->get_row($wpdb->prepare("SELECT event_date FROM {$wpdb->prefix}cem_event_meta WHERE event_id = %d", $post_id));
                $now = current_time('timestamp');
                $when = ($row && strtotime($row->event_date) >= $now) ? __('Upcoming', 'church-events-manager') : __('Past', 'church-events-manager');
                $label = ($status === 'publish') ? __('Published', 'church-events-manager') : ucfirst($status);
                echo esc_html($label . ' • ' . $when);
                break;
            case 'recurring':
                $is_rec = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT is_recurring FROM {$wpdb->prefix}cem_event_meta WHERE event_id = %d",
                    $post_id
                ));
                echo $is_rec ? esc_html__('Yes', 'church-events-manager') : '—';
                break;
        }
    }

    public function make_columns_sortable($columns) {
        $columns['event_datetime'] = 'event_datetime';
        $columns['event_status'] = 'event_status';
        return $columns;
    }

    public function add_admin_filters() {
        if (!$this->is_events_admin_list()) return;
        echo '<input type="date" name="date_from" value="' . esc_attr(isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '') . '" placeholder="dd/mm/yyyy" />';
        echo '<input type="date" name="date_to" value="' . esc_attr(isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '') . '" placeholder="dd/mm/yyyy" />';
        echo '<input type="text" name="event_location" value="' . esc_attr(isset($_GET['event_location']) ? sanitize_text_field($_GET['event_location']) : '') . '" placeholder="' . esc_attr(__('Location', 'church-events-manager')) . '" />';
        echo '<select name="event_status">';
        echo '<option value="">' . esc_html__('All Statuses', 'church-events-manager') . '</option>';
        echo '<option value="upcoming"' . selected(isset($_GET['event_status']) ? sanitize_text_field($_GET['event_status']) : '', 'upcoming', false) . '>' . esc_html__('Upcoming', 'church-events-manager') . '</option>';
        echo '<option value="past"' . selected(isset($_GET['event_status']) ? sanitize_text_field($_GET['event_status']) : '', 'past', false) . '>' . esc_html__('Past', 'church-events-manager') . '</option>';
        echo '</select>';
    }

    public function admin_posts_join($join) {
        if (!$this->is_events_admin_list()) return $join;
        global $wpdb;
        // ensure only one join
        if (strpos($join, 'cem_event_meta em') === false) {
            $join .= " LEFT JOIN {$wpdb->prefix}cem_event_meta em ON {$wpdb->posts}.ID = em.event_id ";
        }
        return $join;
    }

    public function admin_posts_where($where) {
        if (!$this->is_events_admin_list()) return $where;
        global $wpdb;
        // Date range filters
        if (!empty($_GET['date_from'])) {
            $where .= $wpdb->prepare(" AND DATE(em.event_date) >= %s", sanitize_text_field($_GET['date_from']));
        }
        if (!empty($_GET['date_to'])) {
            $where .= $wpdb->prepare(" AND DATE(em.event_date) <= %s", sanitize_text_field($_GET['date_to']));
        }
        // Location filter
        if (!empty($_GET['event_location'])) {
            $like = '%' . $wpdb->esc_like(sanitize_text_field($_GET['event_location'])) . '%';
            $where .= $wpdb->prepare(" AND em.location LIKE %s", $like);
        }
        // Status filter
        if (!empty($_GET['event_status'])) {
            $now_mysql = current_time('mysql');
            if ($_GET['event_status'] === 'upcoming') {
                $where .= $wpdb->prepare(" AND em.event_date >= %s", $now_mysql);
            } elseif ($_GET['event_status'] === 'past') {
                $where .= $wpdb->prepare(" AND em.event_date < %s", $now_mysql);
            }
        }
        return $where;
    }

    public function admin_posts_orderby($orderby) {
        if (!$this->is_events_admin_list()) return $orderby;
        if (isset($_GET['orderby']) && $_GET['orderby'] === 'event_datetime') {
            $dir = (isset($_GET['order']) && strtolower($_GET['order']) === 'asc') ? 'ASC' : 'DESC';
            $orderby = "em.event_date $dir";
        }
        return $orderby;
    }

    public function admin_posts_groupby($groupby) {
        if (!$this->is_events_admin_list()) return $groupby;
        global $wpdb;
        return "{$wpdb->posts}.ID";
    }

    public function render_admin_notices() {
        if (!is_admin()) return;
        if (isset($_GET['cem_invalid_end']) && $_GET['cem_invalid_end'] === '1') {
            echo '<div class="notice notice-error"><p>' . esc_html__('End date/time must be after start date/time. Please correct the values.', 'church-events-manager') . '</p></div>';
        }
    }

    public function add_redirect_flag($location) {
        if ($this->invalid_end) {
            $location = add_query_arg('cem_invalid_end', '1', $location);
            // reset flag to avoid persisting across unrelated requests
            $this->invalid_end = false;
        }
        return $location;
    }
}