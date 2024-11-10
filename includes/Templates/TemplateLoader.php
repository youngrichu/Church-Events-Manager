<?php
namespace ChurchEventsManager\Templates;

class TemplateLoader {
    private $template_path;
    private $default_path;

    public function __construct() {
        $this->template_path = 'church-events/';
        $this->default_path = CEM_PLUGIN_DIR . 'templates/';

        add_filter('template_include', [$this, 'template_loader']);
        add_filter('archive_template', [$this, 'archive_template']);
        add_filter('single_template', [$this, 'single_template']);
    }

    public function template_loader($template) {
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
} 