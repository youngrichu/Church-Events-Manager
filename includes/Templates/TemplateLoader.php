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
            $slug = get_page_template_slug(get_queried_object_id());
            if ($slug && isset($this->page_templates[$slug])) {
                $path = $this->default_path . $slug;
                if (file_exists($path)) {
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
}