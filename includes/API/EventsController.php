<?php
namespace ChurchEventsManager\API;

class EventsController {
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('church-events/v1', '/events', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_events'],
                'permission_callback' => '__return_true',
                'args' => [
                    'page' => [
                        'default' => 1,
                        'sanitize_callback' => 'absint'
                    ],
                    'per_page' => [
                        'default' => 10,
                        'sanitize_callback' => 'absint'
                    ],
                    'expand' => [
                        'default' => 'occurrences',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'category' => [
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'date_from' => [
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'date_to' => [
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'orderby' => [
                        'default' => 'date',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'order' => [
                        'default' => 'ASC',
                        'sanitize_callback' => 'sanitize_text_field'
                    ]
                ]
            ]
        ]);

        register_rest_route('church-events/v1', '/events/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_event'],
                'permission_callback' => '__return_true'
            ]
        ]);

        register_rest_route('church-events/v1', '/categories', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_categories'],
                'permission_callback' => '__return_true',
                'args' => [
                    'hide_empty' => [
                        'default' => true,
                        'sanitize_callback' => 'rest_sanitize_boolean'
                    ],
                    'orderby' => [
                        'default' => 'name',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'order' => [
                        'default' => 'ASC',
                        'sanitize_callback' => 'sanitize_text_field'
                    ]
                ]
            ]
        ]);

        register_rest_route('church-events/v1', '/categories/(?P<slug>[a-zA-Z0-9-]+)/events', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_events_by_category'],
                'permission_callback' => '__return_true',
                'args' => [
                    'page' => [
                        'default' => 1,
                        'sanitize_callback' => 'absint'
                    ],
                    'per_page' => [
                        'default' => 10,
                        'sanitize_callback' => 'absint'
                    ]
                ]
            ]
        ]);
    }

    public function get_events($request) {
        global $wpdb;
        
        $page = $request->get_param('page');
        $per_page = $request->get_param('per_page');
        $category = $request->get_param('category');
        $date_from = $request->get_param('date_from');
        $date_to = $request->get_param('date_to');
        $orderby = $request->get_param('orderby');
        $order = strtoupper($request->get_param('order'));
        $expand = $request->get_param('expand');

        // Occurrence-expansion path for recurring series and upcoming lists
        if ($expand === 'occurrences') {
            // Default range: now → +1 year if not provided
            if (empty($date_from)) {
                $date_from = current_time('mysql');
            }
            if (empty($date_to)) {
                $date_to = date('Y-m-d H:i:s', strtotime('+1 year', strtotime($date_from)));
            }

            // Build recurrence-aware query (fetch parent events that may have occurrences in range)
            $query = "SELECT p.*, em.*, 
                 GROUP_CONCAT(t.name) as categories,
                 GROUP_CONCAT(t.slug) as category_slugs
                 FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->prefix}cem_event_meta em ON p.ID = em.event_id
                 LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                 LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                 LEFT JOIN {$wpdb->terms} t ON tt.term_id = t.term_id AND tt.taxonomy = 'event_category'
                 WHERE p.post_type = 'church_event'
                 AND p.post_status = 'publish'
                 AND (
                   (em.is_recurring = 0 AND em.event_date BETWEEN %s AND %s)
                   OR (em.is_recurring = 1 AND em.event_date <= %s AND (em.recurring_end_date IS NULL OR em.recurring_end_date >= %s))
                 )";
            $params = [$date_from, $date_to, $date_to, $date_from];
            if (!empty($category)) {
                $query .= " AND t.slug = %s";
                $params[] = $category;
            }
            $query .= " GROUP BY p.ID ORDER BY em.event_date ASC";

            $events = $wpdb->get_results($wpdb->prepare($query, ...$params));
            $total_events = is_array($events) ? count($events) : 0;

            if (empty($events)) {
                return rest_ensure_response([
                    'events' => [],
                    'total' => 0,
                    'pages' => 0
                ]);
            }

            // Expand recurring occurrences into a flat upcoming list
            $occurrences = [];
            foreach ($events as $event) {
                if ((int)$event->is_recurring === 1) {
                    foreach (\ChurchEventsManager\Events\RecurrenceExpander::expandInRange($event, $date_from, $date_to) as $occ) {
                        $occurrences[] = $occ;
                    }
                } else {
                    if (strtotime($event->event_date) >= strtotime($date_from) && strtotime($event->event_date) <= strtotime($date_to)) {
                        $occurrences[] = $event;
                    }
                }
            }

            // Sort by occurrence start time
            usort($occurrences, function($a, $b) {
                return strtotime($a->event_date) <=> strtotime($b->event_date);
            });

            // Paginate occurrences
            $offset = ($page - 1) * $per_page;
            $paginated = array_slice($occurrences, $offset, $per_page);

            $formatted_events = array_map(function($event) {
                $categories = $event->categories ? explode(',', $event->categories) : [];
                $category_slugs = $event->category_slugs ? explode(',', $event->category_slugs) : [];
                return [
                    'id' => $event->ID,
                    'title' => $event->post_title,
                    'content' => $event->post_content,
                    'date' => $event->event_date, // occurrence date
                    'end_date' => $event->event_end_date,
                    'location' => $event->location,
                    'permalink' => get_permalink($event->ID),
                    'thumbnail' => get_the_post_thumbnail_url($event->ID, 'full'),
                    'is_occurrence' => isset($event->is_occurrence) ? (int)$event->is_occurrence : 0,
                    'occurrence_parent_id' => isset($event->occurrence_parent_id) ? $event->occurrence_parent_id : null,
                    // Recurrence meta
                    'is_recurring' => isset($event->is_recurring) ? (int)$event->is_recurring : 0,
                    'recurring_pattern' => isset($event->recurring_pattern) ? $event->recurring_pattern : null,
                    'recurring_interval' => (isset($event->recurring_interval) && (int)$event->recurring_interval > 0) ? (int)$event->recurring_interval : 1,
                    'recurring_end_date' => isset($event->recurring_end_date) ? $event->recurring_end_date : null,
                    'recurring_count' => isset($event->recurring_count) ? (int)$event->recurring_count : null,
                    'categories' => array_map(function($name, $slug) {
                        return ['name' => $name, 'slug' => $slug];
                    }, $categories, $category_slugs)
                ];
            }, $paginated);

            $total = count($occurrences);
            $response = [
                'events' => $formatted_events,
                'total' => $total,
                'pages' => (int) ceil($total / $per_page)
            ];

            return rest_ensure_response($response);
        }

        // Build the base query
        $query = "SELECT SQL_CALC_FOUND_ROWS p.*, em.*, 
                 GROUP_CONCAT(t.name) as categories,
                 GROUP_CONCAT(t.slug) as category_slugs
                 FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->prefix}cem_event_meta em ON p.ID = em.event_id
                 LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                 LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                 LEFT JOIN {$wpdb->terms} t ON tt.term_id = t.term_id AND tt.taxonomy = 'event_category'
                 WHERE p.post_type = 'church_event'
                 AND p.post_status = 'publish'";

        // Add category filter
        if (!empty($category)) {
            $query .= $wpdb->prepare(" AND t.slug = %s", $category);
        }

        // Add date filters
        if (!empty($date_from)) {
            $query .= $wpdb->prepare(" AND em.event_date >= %s", $date_from);
        }
        if (!empty($date_to)) {
            $query .= $wpdb->prepare(" AND em.event_date <= %s", $date_to);
        }

        // Group by to avoid duplicate events due to multiple categories
        $query .= " GROUP BY p.ID";

        // Add ordering
        $allowed_orderby = ['date' => 'em.event_date', 'title' => 'p.post_title'];
        $orderby_sql = $allowed_orderby[$orderby] ?? 'em.event_date';
        $order_sql = $order === 'DESC' ? 'DESC' : 'ASC';
        $query .= " ORDER BY {$orderby_sql} {$order_sql}";

        // Add pagination
        $offset = ($page - 1) * $per_page;
        $query .= $wpdb->prepare(" LIMIT %d OFFSET %d", $per_page, $offset);

        $events = $wpdb->get_results($query);
        $total_events = $wpdb->get_var("SELECT FOUND_ROWS()");

        if (empty($events)) {
            return rest_ensure_response([
                'events' => [],
                'total' => 0,
                'pages' => 0
            ]);
        }

        $formatted_events = array_map(function($event) {
            $categories = $event->categories ? explode(',', $event->categories) : [];
            $category_slugs = $event->category_slugs ? explode(',', $event->category_slugs) : [];
            
            return [
                'id' => $event->ID,
                'title' => $event->post_title,
                'content' => $event->post_content,
                'date' => $event->event_date,
                'end_date' => $event->event_end_date,
                'location' => $event->location,
                'permalink' => get_permalink($event->ID),
                'thumbnail' => get_the_post_thumbnail_url($event->ID, 'full'),
                // Recurrence meta
                'is_recurring' => isset($event->is_recurring) ? (int)$event->is_recurring : 0,
                'recurring_pattern' => isset($event->recurring_pattern) ? $event->recurring_pattern : null,
                'recurring_interval' => (isset($event->recurring_interval) && (int)$event->recurring_interval > 0) ? (int)$event->recurring_interval : 1,
                'recurring_end_date' => isset($event->recurring_end_date) ? $event->recurring_end_date : null,
                'recurring_count' => isset($event->recurring_count) ? (int)$event->recurring_count : null,
                'categories' => array_map(function($name, $slug) {
                    return ['name' => $name, 'slug' => $slug];
                }, $categories, $category_slugs)
            ];
        }, $events);

        $response = [
            'events' => $formatted_events,
            'total' => (int) $total_events,
            'pages' => ceil($total_events / $per_page)
        ];

        return rest_ensure_response($response);
    }

    public function get_event($request) {
        $event_id = $request['id'];
        
        global $wpdb;
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT p.*, em.*,
             GROUP_CONCAT(t.name) as categories,
             GROUP_CONCAT(t.slug) as category_slugs
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->prefix}cem_event_meta em ON p.ID = em.event_id
             LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
             LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
             LEFT JOIN {$wpdb->terms} t ON tt.term_id = t.term_id AND tt.taxonomy = 'event_category'
             WHERE p.ID = %d
             AND p.post_type = 'church_event'
             AND p.post_status = 'publish'
             GROUP BY p.ID",
            $event_id
        ));

        if (!$event) {
            return new \WP_Error('no_event', 'Event not found', ['status' => 404]);
        }

        $categories = $event->categories ? explode(',', $event->categories) : [];
        $category_slugs = $event->category_slugs ? explode(',', $event->category_slugs) : [];

        $formatted_event = [
            'id' => $event->ID,
            'title' => $event->post_title,
            'content' => $event->post_content,
            'date' => $event->event_date,
            'end_date' => $event->event_end_date,
            'location' => $event->location,
            'permalink' => get_permalink($event->ID),
            'thumbnail' => get_the_post_thumbnail_url($event->ID, 'full'),
            'categories' => array_map(function($name, $slug) {
                return ['name' => $name, 'slug' => $slug];
            }, $categories, $category_slugs)
        ];

        return rest_ensure_response($formatted_event);
    }

    /**
     * Get all event categories
     */
    public function get_categories($request) {
        $args = [
            'taxonomy' => 'event_category',
            'hide_empty' => $request->get_param('hide_empty'),
            'orderby' => $request->get_param('orderby'),
            'order' => $request->get_param('order')
        ];

        $categories = get_terms($args);

        if (is_wp_error($categories)) {
            return new \WP_Error(
                'no_categories',
                'Unable to retrieve categories',
                ['status' => 500]
            );
        }

        $formatted_categories = array_map(function($category) {
            return [
                'id' => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'count' => $category->count,
                'parent' => $category->parent,
                'link' => get_term_link($category),
                'meta' => [
                    'events_url' => rest_url("church-events/v1/categories/{$category->slug}/events")
                ]
            ];
        }, $categories);

        return rest_ensure_response($formatted_categories);
    }

    /**
     * Get events by category slug
     */
    public function get_events_by_category($request) {
        $category_slug = $request->get_param('slug');
        $category = get_term_by('slug', $category_slug, 'event_category');

        if (!$category) {
            return new \WP_Error(
                'invalid_category',
                'Category not found',
                ['status' => 404]
            );
        }

        // Override the category parameter and use the existing get_events method
        $request->set_param('category', $category_slug);
        return $this->get_events($request);
    }
}