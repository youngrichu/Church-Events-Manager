<?php
namespace ChurchEventsManager\Core;

class DatabaseHelper {
    
    /**
     * Safely execute a database query with error handling
     */
    public static function safe_query($query, $params = []) {
        global $wpdb;
        
        try {
            if (!empty($params)) {
                $result = $wpdb->get_results($wpdb->prepare($query, $params));
            } else {
                $result = $wpdb->get_results($query);
            }
            
            if ($wpdb->last_error) {
                error_log("CEM Database Error: " . $wpdb->last_error . " Query: " . $query);
                return false;
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("CEM Database Exception: " . $e->getMessage() . " Query: " . $query);
            return false;
        }
    }
    
    /**
     * Safely get a single row with error handling
     */
    public static function safe_get_row($query, $params = []) {
        global $wpdb;
        
        try {
            if (!empty($params)) {
                $result = $wpdb->get_row($wpdb->prepare($query, $params));
            } else {
                $result = $wpdb->get_row($query);
            }
            
            if ($wpdb->last_error) {
                error_log("CEM Database Error: " . $wpdb->last_error . " Query: " . $query);
                return false;
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("CEM Database Exception: " . $e->getMessage() . " Query: " . $query);
            return false;
        }
    }
    
    /**
     * Safely get a single variable with error handling
     */
    public static function safe_get_var($query, $params = []) {
        global $wpdb;
        
        try {
            if (!empty($params)) {
                $result = $wpdb->get_var($wpdb->prepare($query, $params));
            } else {
                $result = $wpdb->get_var($query);
            }
            
            if ($wpdb->last_error) {
                error_log("CEM Database Error: " . $wpdb->last_error . " Query: " . $query);
                return false;
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("CEM Database Exception: " . $e->getMessage() . " Query: " . $query);
            return false;
        }
    }
    
    /**
     * Check if a column exists in a table
     */
    public static function column_exists($table, $column) {
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
    
    /**
     * Get event meta with fallback for missing columns
     */
    public static function get_event_meta($event_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'cem_event_meta';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
        if (!$table_exists) {
            error_log("CEM: Table $table does not exist");
            return false;
        }
        
        // Get basic event data first
        $basic_query = "SELECT event_id, event_date, event_end_date, location FROM $table WHERE event_id = %d";
        $event_meta = self::safe_get_row($basic_query, [$event_id]);
        
        if (!$event_meta) {
            return false;
        }
        
        // Add optional columns if they exist
        $optional_columns = [
            'is_recurring' => 0,
            'recurring_pattern' => '',
            'recurring_end_date' => null,
            'recurring_count' => null,
            'recurring_interval' => 1,
            'is_all_day' => 0,
            'notification_sent' => 0
        ];
        
        foreach ($optional_columns as $column => $default) {
            if (self::column_exists($table, $column)) {
                $value = $wpdb->get_var($wpdb->prepare(
                    "SELECT $column FROM $table WHERE event_id = %d",
                    $event_id
                ));
                $event_meta->$column = $value !== null ? $value : $default;
            } else {
                $event_meta->$column = $default;
            }
        }
        
        return $event_meta;
    }
    
    /**
     * Get event meta with safe column selection
     */
    public static function get_event_meta_safe($event_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'cem_event_meta';
        
        // Build SELECT query with only existing columns
        $available_columns = [];
        $columns = $wpdb->get_results("DESCRIBE $table", ARRAY_A);
        
        if ($columns) {
            foreach ($columns as $col) {
                $available_columns[] = $col['Field'];
            }
        }
        
        if (empty($available_columns)) {
            return false;
        }
        
        $select_columns = implode(', ', $available_columns);
        $query = "SELECT $select_columns FROM $table WHERE event_id = %d";
        
        return self::safe_get_row($query, [$event_id]);
    }
}