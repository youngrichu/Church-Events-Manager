<?php
namespace ChurchEventsManager\Maps;

class GoogleMapsHandler {
    private $api_key;
    private $is_enabled;

    public function __construct() {
        $options = get_option('church_events_options', []);
        $this->api_key = $options['google_maps_api_key'] ?? '';
        $this->is_enabled = isset($options['enable_google_maps']) && $options['enable_google_maps'] === '1';

        if ($this->is_enabled && $this->api_key) {
            add_action('wp_enqueue_scripts', [$this, 'enqueue_maps_scripts']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_maps_scripts']);
            add_action('wp_footer', [$this, 'add_maps_template']);
            add_action('admin_footer', [$this, 'add_admin_maps_template']);
        }
    }

    public function enqueue_maps_scripts() {
        wp_enqueue_script(
            'google-maps',
            "https://maps.googleapis.com/maps/api/js?key={$this->api_key}&libraries=places",
            [],
            null,
            true
        );

        wp_enqueue_script(
            'cem-maps',
            CEM_PLUGIN_URL . 'assets/js/maps.js',
            ['jquery', 'google-maps'],
            CEM_VERSION,
            true
        );

        wp_localize_script('cem-maps', 'cemMaps', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cem_maps_nonce')
        ]);
    }

    public function enqueue_admin_maps_scripts($hook) {
        if (!in_array($hook, ['post.php', 'post-new.php'])) {
            return;
        }

        global $post;
        if ($post->post_type !== 'church_event') {
            return;
        }

        $this->enqueue_maps_scripts();
    }

    public function add_maps_template() {
        ?>
        <script type="text/template" id="cem-map-template">
            <div class="cem-map-container">
                <div class="cem-map" style="height: 300px;"></div>
                <div class="cem-map-details">
                    <p class="cem-address"></p>
                    <div class="cem-directions">
                        <a href="#" target="_blank" class="get-directions">
                            <?php _e('Get Directions', 'church-events-manager'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </script>
        <?php
    }

    public function add_admin_maps_template() {
        ?>
        <script type="text/template" id="cem-admin-map-template">
            <div class="cem-admin-map-container">
                <div class="cem-map" style="height: 300px;"></div>
                <div class="cem-location-search">
                    <input type="text" id="cem-location-search" 
                           placeholder="<?php esc_attr_e('Search for a location', 'church-events-manager'); ?>"
                           class="widefat">
                </div>
                <div class="cem-location-details">
                    <input type="hidden" id="cem-lat" name="event_lat">
                    <input type="hidden" id="cem-lng" name="event_lng">
                    <input type="hidden" id="cem-formatted-address" name="event_formatted_address">
                </div>
            </div>
        </script>
        <?php
    }

    public function geocode_address($address) {
        $url = add_query_arg([
            'address' => urlencode($address),
            'key' => $this->api_key
        ], 'https://maps.googleapis.com/maps/api/geocode/json');

        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response));

        if ($data->status !== 'OK') {
            return false;
        }

        return [
            'lat' => $data->results[0]->geometry->location->lat,
            'lng' => $data->results[0]->geometry->location->lng,
            'formatted_address' => $data->results[0]->formatted_address
        ];
    }

    public static function is_maps_enabled() {
        $options = get_option('church_events_options', []);
        return isset($options['enable_google_maps']) && 
               $options['enable_google_maps'] === '1' && 
               !empty($options['google_maps_api_key']);
    }
} 