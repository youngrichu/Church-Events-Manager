<?php
namespace ChurchEventsManager\Elementor;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class ElementorIntegration {
    
    public function __construct() {
        // Hook into Elementor
        add_action('elementor/widgets/register', [$this, 'register_widgets']);
        // Backward compatibility for older Elementor versions (<3.5)
        add_action('elementor/widgets/widgets_registered', [$this, 'register_widgets']);
        add_action('elementor/elements/categories_registered', [$this, 'add_elementor_widget_categories']);
    }

    /**
     * Register our custom Elementor widgets
     */
    public function register_widgets($widgets_manager) {
        // Make sure our widget class is loaded
        require_once CEM_PLUGIN_DIR . 'includes/Elementor/EventsListWidget.php';
        
        // Register the widget (compatibility with older Elementor)
        if (method_exists($widgets_manager, 'register')) {
            $widgets_manager->register(new EventsListWidget());
        } else {
            // Elementor < 3.5
            $widgets_manager->register_widget_type(new EventsListWidget());
        }
    }

    /**
     * Add custom widget category for Church Events Manager
     */
    public function add_elementor_widget_categories($elements_manager) {
        $elements_manager->add_category(
            'church-events',
            [
                'title' => __('Church Events', 'church-events-manager'),
                'icon' => 'eicon-calendar',
            ]
        );
    }
}