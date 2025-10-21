<?php
namespace ChurchEventsManager\Core;

class Migrations {
    private $current_version;
    private $installed_version;

    public function __construct() {
        $this->current_version = CEM_DB_VERSION;
        $this->installed_version = get_option('cem_db_version', '0.0.0');

        if (version_compare($this->installed_version, $this->current_version, '<')) {
            $this->run_migrations();
        }
    }

    private function run_migrations() {
        // Run migrations in order
        $migrations = [
            '1.0.0' => 'create_initial_tables',
            '1.0.1' => 'add_notification_fields',
            '1.0.2' => 'update_recurring_fields',
            '1.0.3' => 'add_all_day_flag',
            '1.0.4' => 'remove_deprecated_fields',
            '1.1.0' => 'update_schema_for_v1_1_0'
        ];

        foreach ($migrations as $version => $method) {
            if (version_compare($this->installed_version, $version, '<')) {
                if (method_exists($this, $method)) {
                    $result = $this->$method();
                    if ($result === false) {
                        error_log("CEM Migration failed for version {$version}");
                        continue;
                    }
                }
                update_option('cem_db_version', $version);
                error_log("CEM Migration completed for version {$version}");
            }
        }
    }

    private function create_initial_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = [
            // Event meta table
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cem_event_meta (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                event_id bigint(20) NOT NULL,
                event_date datetime NOT NULL,
                event_end_date datetime,
                location varchar(255),
                is_recurring tinyint(1) DEFAULT 0,
                recurring_pattern varchar(50),
                recurring_interval int DEFAULT 1,
                recurring_end_date datetime,
                recurring_count int DEFAULT NULL,
                is_all_day tinyint(1) DEFAULT 0,
                notification_sent tinyint(1) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY event_id (event_id)
            ) $charset_collate;",

            // RSVP table
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cem_rsvp (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                event_id bigint(20) NOT NULL,
                user_id bigint(20) NOT NULL,
                status varchar(20) NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY event_user (event_id, user_id)
            ) $charset_collate;"
        ];

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        foreach ($sql as $query) {
            dbDelta($query);
        }
    }

    private function add_notification_fields() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'cem_event_meta';
        $column = 'notification_sent';
        
        if (!$this->column_exists($table, $column)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN $column tinyint(1) DEFAULT 0");
        }
    }

    private function update_recurring_fields() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'cem_event_meta';
        $columns = [
            'recurring_end_date' => 'datetime',
            'recurring_count' => 'int DEFAULT NULL',
            'recurring_interval' => 'int DEFAULT 1'
        ];
        
        foreach ($columns as $column => $definition) {
            if (!$this->column_exists($table, $column)) {
                $wpdb->query("ALTER TABLE $table ADD COLUMN $column $definition");
            }
        }
    }

    private function add_all_day_flag() {
        global $wpdb;
        $table = $wpdb->prefix . 'cem_event_meta';
        $column = 'is_all_day';
        if (!$this->column_exists($table, $column)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN $column tinyint(1) DEFAULT 0");
        }
    }

    private function remove_deprecated_fields() {
        // Skip dropping columns for cross-DB compatibility (e.g., SQLite).
        // These columns are deprecated and unused; leaving them does not affect functionality.
        return true;
    }

    private function update_schema_for_v1_1_0() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'cem_event_meta';
        
        // Ensure all required columns exist for v1.1.0
        $required_columns = [
            'is_recurring' => 'tinyint(1) DEFAULT 0',
            'recurring_pattern' => 'varchar(50)',
            'recurring_end_date' => 'datetime',
            'recurring_count' => 'int DEFAULT NULL',
            'recurring_interval' => 'int DEFAULT 1',
            'is_all_day' => 'tinyint(1) DEFAULT 0',
            'notification_sent' => 'tinyint(1) DEFAULT 0'
        ];
        
        foreach ($required_columns as $column => $definition) {
            if (!$this->column_exists($table, $column)) {
                $sql = "ALTER TABLE $table ADD COLUMN $column $definition";
                $result = $wpdb->query($sql);
                if ($result === false) {
                    error_log("CEM: Failed to add column {$column} to {$table}. Error: " . $wpdb->last_error);
                    return false;
                }
                error_log("CEM: Added column {$column} to {$table}");
            }
        }
        
        return true;
    }

    private function column_exists($table, $column) {
        global $wpdb;
        
        // Use DESCRIBE for MySQL/MariaDB
        $columns = $wpdb->get_results("DESCRIBE $table", ARRAY_A);
        if ($columns) {
            foreach ($columns as $col) {
                if ($col['Field'] === $column) {
                    return true;
                }
            }
            return false;
        }
        
        // Fallback: Cross-DB approach for other databases
        $wpdb->get_results("SELECT * FROM $table LIMIT 1");
        $cols = method_exists($wpdb, 'get_col_info') ? $wpdb->get_col_info('name') : [];
        if (!is_array($cols)) {
            $cols = [];
        }
        return in_array($column, $cols, true);
    }

    public static function get_schema_version() {
        return get_option('cem_db_version', '0.0.0');
    }

    public static function needs_upgrade() {
        $installed_version = self::get_schema_version();
        return version_compare($installed_version, CEM_DB_VERSION, '<');
    }
}