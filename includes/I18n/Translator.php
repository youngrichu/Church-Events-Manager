<?php
namespace ChurchEventsManager\I18n;

class Translator {
    public function __construct() {
        add_action('plugins_loaded', [$this, 'load_textdomain'], 10);
    }

    public function load_textdomain() {
        load_plugin_textdomain(
            'church-events-manager',
            false,
            dirname(plugin_basename(CEM_PLUGIN_FILE)) . '/languages'
        );
    }

    /**
     * Load translations for JavaScript files
     */
    public function load_script_translations() {
        wp_set_script_translations(
            'cem-admin',
            'church-events-manager',
            CEM_PLUGIN_DIR . 'languages'
        );

        wp_set_script_translations(
            'cem-calendar',
            'church-events-manager',
            CEM_PLUGIN_DIR . 'languages'
        );

        wp_set_script_translations(
            'cem-maps',
            'church-events-manager',
            CEM_PLUGIN_DIR . 'languages'
        );
    }

    /**
     * Register script translations
     */
    public function register_script_translations() {
        if (function_exists('wp_set_script_translations')) {
            add_action('wp_enqueue_scripts', [$this, 'load_script_translations']);
            add_action('admin_enqueue_scripts', [$this, 'load_script_translations']);
        }
    }

    /**
     * Get translated date format
     */
    public static function get_date_format() {
        return apply_filters(
            'cem_date_format',
            _x('F j, Y', 'Event date format', 'church-events-manager')
        );
    }

    /**
     * Get translated time format
     */
    public static function get_time_format() {
        return apply_filters(
            'cem_time_format',
            _x('g:i a', 'Event time format', 'church-events-manager')
        );
    }

    /**
     * Format date and time according to locale
     */
    public static function format_datetime($datetime, $format = 'both') {
        if (!$datetime) {
            return '';
        }

        $timestamp = strtotime($datetime);

        switch ($format) {
            case 'date':
                return date_i18n(self::get_date_format(), $timestamp);
            case 'time':
                return date_i18n(self::get_time_format(), $timestamp);
            default:
                return date_i18n(
                    self::get_date_format() . ' ' . self::get_time_format(),
                    $timestamp
                );
        }
    }

    /**
     * Get localized recurring patterns
     */
    public static function get_recurring_patterns() {
        return [
            'daily' => _x('Daily', 'Recurring pattern', 'church-events-manager'),
            'weekly' => _x('Weekly', 'Recurring pattern', 'church-events-manager'),
            'monthly' => _x('Monthly', 'Recurring pattern', 'church-events-manager')
        ];
    }

    /**
     * Get localized RSVP statuses
     */
    public static function get_rsvp_statuses() {
        return [
            'attending' => _x('Attending', 'RSVP status', 'church-events-manager'),
            'not_attending' => _x('Not Attending', 'RSVP status', 'church-events-manager'),
            'maybe' => _x('Maybe', 'RSVP status', 'church-events-manager')
        ];
    }
}