<?php
namespace ChurchEventsManager\Admin;

class SettingsPage {
    private $options;

    public function __construct() {
        add_action('admin_menu', [$this, 'add_plugin_page']);
        add_action('admin_init', [$this, 'page_init']);
    }

    public function add_plugin_page() {
        add_submenu_page(
            'edit.php?post_type=church_event',
            __('Events Settings', 'church-events-manager'),
            __('Settings', 'church-events-manager'),
            'manage_options',
            'church-events-settings',
            [$this, 'create_admin_page']
        );
    }

    public function create_admin_page() {
        $this->options = get_option('church_events_options', [
            'reminder_time' => '24',
            'enable_google_maps' => '0',
            'google_maps_api_key' => '',
            'default_timezone' => 'UTC',
            'events_per_page' => '10',
            'enable_recurring' => '1',
            'notification_types' => ['new_event', 'reminder']
        ]);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
            <?php
                settings_fields('church_events_options_group');
                do_settings_sections('church-events-settings');
                submit_button();
            ?>
            </form>
        </div>
        <?php
    }

    public function page_init() {
        register_setting(
            'church_events_options_group',
            'church_events_options',
            [$this, 'sanitize']
        );

        // General Settings Section
        add_settings_section(
            'general_settings_section',
            __('General Settings', 'church-events-manager'),
            [$this, 'general_section_info'],
            'church-events-settings'
        );

        add_settings_field(
            'events_per_page',
            __('Events Per Page', 'church-events-manager'),
            [$this, 'events_per_page_callback'],
            'church-events-settings',
            'general_settings_section'
        );

        add_settings_field(
            'default_timezone',
            __('Default Timezone', 'church-events-manager'),
            [$this, 'default_timezone_callback'],
            'church-events-settings',
            'general_settings_section'
        );

        add_settings_field(
            'enable_recurring',
            __('Enable Recurring Events', 'church-events-manager'),
            [$this, 'enable_recurring_callback'],
            'church-events-settings',
            'general_settings_section'
        );

        // Notification Settings Section
        add_settings_section(
            'notification_settings_section',
            __('Notification Settings', 'church-events-manager'),
            [$this, 'notification_section_info'],
            'church-events-settings'
        );

        add_settings_field(
            'reminder_time',
            __('Reminder Time (hours before event)', 'church-events-manager'),
            [$this, 'reminder_time_callback'],
            'church-events-settings',
            'notification_settings_section'
        );

        add_settings_field(
            'notification_types',
            __('Enable Notifications For', 'church-events-manager'),
            [$this, 'notification_types_callback'],
            'church-events-settings',
            'notification_settings_section'
        );

        // Maps Settings Section
        add_settings_section(
            'maps_settings_section',
            __('Google Maps Settings', 'church-events-manager'),
            [$this, 'maps_section_info'],
            'church-events-settings'
        );

        add_settings_field(
            'enable_google_maps',
            __('Enable Google Maps', 'church-events-manager'),
            [$this, 'enable_google_maps_callback'],
            'church-events-settings',
            'maps_settings_section'
        );

        add_settings_field(
            'google_maps_api_key',
            __('Google Maps API Key', 'church-events-manager'),
            [$this, 'google_maps_api_key_callback'],
            'church-events-settings',
            'maps_settings_section'
        );

        // Cache Settings Section
        add_settings_section(
            'cache_settings_section',
            __('Cache Settings', 'church-events-manager'),
            [$this, 'cache_section_info'],
            'church-events-settings'
        );

        add_settings_field(
            'cache_duration',
            __('Cache Duration (minutes)', 'church-events-manager'),
            [$this, 'cache_duration_callback'],
            'church-events-settings',
            'cache_settings_section'
        );

        // Cleanup Settings Section
        add_settings_section(
            'cleanup_settings_section',
            __('Cleanup Settings', 'church-events-manager'),
            [$this, 'cleanup_section_info'],
            'church-events-settings'
        );

        add_settings_field(
            'clean_data_on_uninstall',
            __('Clean Data on Uninstall', 'church-events-manager'),
            [$this, 'clean_data_callback'],
            'church-events-settings',
            'cleanup_settings_section'
        );
    }

    public function sanitize($input) {
        $sanitized = [];
        
        if (isset($input['events_per_page'])) {
            $sanitized['events_per_page'] = absint($input['events_per_page']);
        }

        if (isset($input['reminder_time'])) {
            $sanitized['reminder_time'] = absint($input['reminder_time']);
        }

        if (isset($input['enable_google_maps'])) {
            $sanitized['enable_google_maps'] = '1';
        }

        if (isset($input['google_maps_api_key'])) {
            $sanitized['google_maps_api_key'] = sanitize_text_field($input['google_maps_api_key']);
        }

        if (isset($input['default_timezone'])) {
            $sanitized['default_timezone'] = sanitize_text_field($input['default_timezone']);
        }

        if (isset($input['enable_recurring'])) {
            $sanitized['enable_recurring'] = '1';
        }

        if (isset($input['notification_types']) && is_array($input['notification_types'])) {
            $sanitized['notification_types'] = array_map('sanitize_text_field', $input['notification_types']);
        }

        if (isset($input['cache_duration'])) {
            $sanitized['cache_duration'] = absint($input['cache_duration']);
        }

        if (isset($input['clean_data_on_uninstall'])) {
            $sanitized['clean_data_on_uninstall'] = '1';
        }

        return $sanitized;
    }

    // Section Callbacks
    public function general_section_info() {
        echo '<p>' . __('Configure general settings for the events system.', 'church-events-manager') . '</p>';
    }

    public function notification_section_info() {
        echo '<p>' . __('Configure notification settings for events.', 'church-events-manager') . '</p>';
    }

    public function maps_section_info() {
        echo '<p>' . __('Configure Google Maps integration for event locations.', 'church-events-manager') . '</p>';
    }

    public function cache_section_info() {
        echo '<p>' . __('Configure caching settings to improve performance.', 'church-events-manager') . '</p>';
    }

    public function cleanup_section_info() {
        echo '<p>' . __('Configure data cleanup settings when uninstalling the plugin.', 'church-events-manager') . '</p>';
        echo '<p class="description" style="color: #d63638;">' . 
             __('Warning: If enabled, all plugin data will be permanently deleted when the plugin is uninstalled.', 'church-events-manager') . 
             '</p>';
    }

    // Field Callbacks
    public function events_per_page_callback() {
        printf(
            '<input type="number" id="events_per_page" name="church_events_options[events_per_page]" value="%s" min="1" max="100" />',
            isset($this->options['events_per_page']) ? esc_attr($this->options['events_per_page']) : '10'
        );
    }

    public function default_timezone_callback() {
        $current = isset($this->options['default_timezone']) ? $this->options['default_timezone'] : 'UTC';
        echo '<select id="default_timezone" name="church_events_options[default_timezone]">';
        $timezones = timezone_identifiers_list();
        foreach ($timezones as $timezone) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($timezone),
                selected($current, $timezone, false),
                esc_html($timezone)
            );
        }
        echo '</select>';
    }

    public function enable_recurring_callback() {
        printf(
            '<input type="checkbox" id="enable_recurring" name="church_events_options[enable_recurring]" value="1" %s />',
            isset($this->options['enable_recurring']) ? checked($this->options['enable_recurring'], '1', false) : ''
        );
    }

    public function reminder_time_callback() {
        printf(
            '<input type="number" id="reminder_time" name="church_events_options[reminder_time]" value="%s" min="1" max="168" />',
            isset($this->options['reminder_time']) ? esc_attr($this->options['reminder_time']) : '24'
        );
    }

    public function notification_types_callback() {
        $types = isset($this->options['notification_types']) ? $this->options['notification_types'] : [];
        $options = [
            'new_event' => __('New Events', 'church-events-manager'),
            'reminder' => __('Event Reminders', 'church-events-manager'),
            'update' => __('Event Updates', 'church-events-manager'),
            'cancellation' => __('Event Cancellations', 'church-events-manager')
        ];

        foreach ($options as $value => $label) {
            printf(
                '<label><input type="checkbox" name="church_events_options[notification_types][]" value="%s" %s /> %s</label><br>',
                esc_attr($value),
                in_array($value, $types) ? 'checked' : '',
                esc_html($label)
            );
        }
    }

    public function enable_google_maps_callback() {
        printf(
            '<input type="checkbox" id="enable_google_maps" name="church_events_options[enable_google_maps]" value="1" %s />',
            isset($this->options['enable_google_maps']) ? checked($this->options['enable_google_maps'], '1', false) : ''
        );
    }

    public function google_maps_api_key_callback() {
        printf(
            '<input type="text" id="google_maps_api_key" name="church_events_options[google_maps_api_key]" value="%s" class="regular-text" />',
            isset($this->options['google_maps_api_key']) ? esc_attr($this->options['google_maps_api_key']) : ''
        );
    }

    public function cache_duration_callback() {
        printf(
            '<input type="number" id="cache_duration" name="church_events_options[cache_duration]" value="%s" min="1" max="1440" />',
            isset($this->options['cache_duration']) ? esc_attr($this->options['cache_duration']) : '60'
        );
        echo '<p class="description">' . __('Time in minutes to cache event data. Default: 60 minutes', 'church-events-manager') . '</p>';
    }

    public function clean_data_callback() {
        printf(
            '<input type="checkbox" id="clean_data_on_uninstall" name="church_events_options[clean_data_on_uninstall]" value="1" %s />',
            isset($this->options['clean_data_on_uninstall']) ? checked($this->options['clean_data_on_uninstall'], '1', false) : ''
        );
        echo '<p class="description">' . 
             __('Check this to remove all plugin data (events, RSVPs, settings) when uninstalling the plugin.', 'church-events-manager') . 
             '</p>';
    }
} 