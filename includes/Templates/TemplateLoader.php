<?php
namespace ChurchEventsManager\Templates;

class TemplateLoader {
    private $template_path;
    private $default_path;
    private $page_templates;

    public function __construct() {
        $this->template_path = 'church-events/';
        $this->default_path = CEM_PLUGIN_DIR . 'templates/';
        // Avoid early translation loading; apply translations when registering templates
        $this->page_templates = [
            'events-list.php' => 'Events List'
        ];

        add_filter('template_include', [$this, 'template_loader']);
        // Remove invalid filter registrations for undefined methods.
        // template_loader handles archive and single routing already.
        add_filter('theme_page_templates', [$this, 'register_page_templates']);
    }

    public function template_loader($template) {
        // Load plugin page template when assigned
        if (is_singular('page')) {
            $page_id = get_queried_object_id();
            $slug = get_page_template_slug($page_id);
            if ($slug && isset($this->page_templates[$slug])) {
                $path = $this->default_path . $slug;
                if (file_exists($path)) {
                    // Check if this is an Elementor-built page
                    $is_elementor_preview = class_exists('\\Elementor\\Plugin') && \Elementor\Plugin::$instance->preview->is_preview_mode();
                    $is_elementor_page = $this->is_elementor_page($page_id);
                    
                    // If it's an Elementor page (but not in preview mode), inject content via hook instead
                    if ($is_elementor_page && !$is_elementor_preview) {
                        // Add hook to inject events list content into Elementor pages
                        add_action('elementor/page_templates/canvas/before_content', [$this, 'inject_events_content']);
                        add_action('elementor/page_templates/header-footer/before_content', [$this, 'inject_events_content']);
                        add_action('wp_footer', [$this, 'inject_events_content_fallback']);
                        return $template;
                    }
                    
                    // Use our custom template for non-Elementor pages or when in Elementor preview mode
                    return $path;
                }
            }
        }

        // Use our archive layout for event searches
        if (is_search() && isset($_GET['post_type']) && $_GET['post_type'] === 'church_event') {
            return $this->get_template('archive-event.php');
        }

        if (is_post_type_archive('church_event') || is_tax('event_category')) {
            return $this->get_template('archive-event.php');
        }

        if (is_singular('church_event')) {
            return $this->get_template('single-event.php');
        }

        return $template;
    }

    private function is_elementor_page($post_id) {
        // Robust detection for Elementor-built pages
        if (!class_exists('\\Elementor\\Plugin')) {
            // Fallback to meta-based check
            $edit_mode = get_post_meta($post_id, '_elementor_edit_mode', true);
            $data = get_post_meta($post_id, '_elementor_data', true);
            return ($edit_mode === 'builder') || !empty($data);
        }
        $instance = \Elementor\Plugin::$instance;
        // Use DB API when available
        if (isset($instance->db) && method_exists($instance->db, 'is_built_with_elementor')) {
            try {
                return (bool) $instance->db->is_built_with_elementor($post_id);
            } catch (\Throwable $e) {
                // Fallback to meta-based check on errors
                $edit_mode = get_post_meta($post_id, '_elementor_edit_mode', true);
                $data = get_post_meta($post_id, '_elementor_data', true);
                return ($edit_mode === 'builder') || !empty($data);
            }
        }
        // Last resort: meta-based heuristics
        $edit_mode = get_post_meta($post_id, '_elementor_edit_mode', true);
        $data = get_post_meta($post_id, '_elementor_data', true);
        return ($edit_mode === 'builder') || !empty($data);
    }

    public function get_template($template_name) {
        $template = locate_template([
            $this->template_path . $template_name,
            $template_name
        ]);

        if (!$template) {
            $template = $this->default_path . $template_name;
        }

        return $template;
    }

    public function get_template_part($slug, $name = '') {
        $template = '';

        if ($name) {
            $template = locate_template([
                $this->template_path . "{$slug}-{$name}.php",
                "{$slug}-{$name}.php"
            ]);
        }

        if (!$template) {
            $template = locate_template([
                $this->template_path . "{$slug}.php",
                "{$slug}.php"
            ]);
        }

        if (!$template) {
            if ($name) {
                $fallback = $this->default_path . "{$slug}-{$name}.php";
                $template = file_exists($fallback) ? $fallback : '';
            }

            if (!$template) {
                $template = $this->default_path . "{$slug}.php";
            }
        }

        if ($template) {
            load_template($template, false);
        }
    }

    public function register_page_templates($templates) {
        // Make plugin page templates selectable in the page editor
        foreach ($this->page_templates as $file => $label) {
            $templates[$file] = __($label, 'church-events-manager');
        }
        return $templates;
    }

    /**
     * Inject events list content into Elementor pages
     */
    public function inject_events_content() {
        // Only inject on pages with events-list template
        $page_id = get_queried_object_id();
        $slug = get_page_template_slug($page_id);
        
        if ($slug === 'events-list.php') {
            // Load the events list template content
            ob_start();
            include $this->default_path . 'events-list.php';
            $content = ob_get_clean();
            
            // Output the content
            echo '<div class="elementor-events-injection">' . $content . '</div>';
            
            // Remove the fallback hook since we've injected the content
            remove_action('wp_footer', [$this, 'inject_events_content_fallback']);
        }
    }

    /**
     * Fallback injection method for Elementor pages that don't use standard hooks
     */
    public function inject_events_content_fallback() {
        $page_id = get_queried_object_id();
        $slug = get_page_template_slug($page_id);
        
        if ($slug === 'events-list.php' && $this->is_elementor_page($page_id)) {
            // Use JavaScript to inject content if hooks didn't work
            ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var elementorContent = document.querySelector('.elementor-inner, .elementor-container, .elementor');
                if (elementorContent && !document.querySelector('.elementor-events-injection')) {
                    // Create a notice that the user should use the Elementor widget instead
                    var notice = document.createElement('div');
                    notice.className = 'elementor-events-notice';
                    notice.style.cssText = 'background: #f0f6fc; border: 1px solid #0073aa; padding: 15px; margin: 20px; border-radius: 4px; color: #0073aa;';
                    notice.innerHTML = '<strong>Church Events Manager:</strong> This page uses the Events List template but is built with Elementor. Please use the "Church Events List" widget from the Elementor widget panel to display your events.';
                    elementorContent.insertBefore(notice, elementorContent.firstChild);
                }
            });
            </script>
            <?php
        }
    }
}