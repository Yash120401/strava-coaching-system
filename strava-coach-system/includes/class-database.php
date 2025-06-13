<?php
/**
 * Database Management Class
 * File: includes/class-database.php
 */

class StravaCoach_Database {
    
    public function __construct() {
        // Database hooks
    }
    
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Coach-Mentee Relationships Table
        $relationships_table = $wpdb->prefix . 'strava_coach_mentee_relationships';
        $relationships_sql = "CREATE TABLE $relationships_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            coach_id bigint(20) NOT NULL,
            mentee_id bigint(20) NOT NULL,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY coach_id (coach_id),
            KEY mentee_id (mentee_id)
        ) $charset_collate;";
        
        // Strava Integration Table
        $strava_users_table = $wpdb->prefix . 'strava_user_integrations';
        $strava_users_sql = "CREATE TABLE $strava_users_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            strava_user_id bigint(20) NOT NULL,
            access_token text NOT NULL,
            refresh_token text NOT NULL,
            token_expires_at datetime NOT NULL,
            athlete_data longtext,
            last_sync datetime,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        // Strava Activities Table
        $activities_table = $wpdb->prefix . 'strava_activities';
        $activities_sql = "CREATE TABLE $activities_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            strava_activity_id bigint(20) NOT NULL,
            activity_type varchar(50) NOT NULL,
            activity_name varchar(255),
            activity_date datetime NOT NULL,
            distance decimal(10,2),
            duration int(11),
            elevation_gain decimal(8,2),
            average_speed decimal(8,4),
            max_speed decimal(8,4),
            average_heartrate decimal(5,1),
            max_heartrate int(3),
            average_cadence decimal(5,1),
            average_power decimal(8,1),
            kilojoules decimal(8,1),
            pace decimal(8,4),
            raw_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY activity_date (activity_date),
            KEY activity_type (activity_type)
        ) $charset_collate;";
        
        // Weekly Plans Table
        $weekly_plans_table = $wpdb->prefix . 'strava_weekly_plans';
        $weekly_plans_sql = "CREATE TABLE $weekly_plans_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            coach_id bigint(20) NOT NULL,
            mentee_id bigint(20) NOT NULL,
            week_start_date date NOT NULL,
            plan_name varchar(255),
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY coach_id (coach_id),
            KEY mentee_id (mentee_id),
            KEY week_start_date (week_start_date)
        ) $charset_collate;";
        
        // Plan Activities Table
        $plan_activities_table = $wpdb->prefix . 'strava_plan_activities';
        $plan_activities_sql = "CREATE TABLE $plan_activities_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            weekly_plan_id bigint(20) NOT NULL,
            day_of_week tinyint(1) NOT NULL,
            activity_type varchar(50) NOT NULL,
            activity_name varchar(255),
            target_distance decimal(10,2),
            target_duration int(11),
            target_pace decimal(8,4),
            target_heartrate_min int(3),
            target_heartrate_max int(3),
            target_elevation decimal(8,2),
            intensity_level varchar(20),
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY weekly_plan_id (weekly_plan_id),
            KEY day_of_week (day_of_week),
            KEY activity_type (activity_type)
        ) $charset_collate;";
        
        // Weekly Scores Table
        $weekly_scores_table = $wpdb->prefix . 'strava_weekly_scores';
        $weekly_scores_sql = "CREATE TABLE $weekly_scores_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            weekly_plan_id bigint(20) NOT NULL,
            coach_id bigint(20) NOT NULL,
            mentee_id bigint(20) NOT NULL,
            pace_score decimal(3,1) DEFAULT 0,
            distance_score decimal(3,1) DEFAULT 0,
            consistency_score decimal(3,1) DEFAULT 0,
            elevation_score decimal(3,1) DEFAULT 0,
            overall_score decimal(3,1) DEFAULT 0,
            custom_field_1_name varchar(100),
            custom_field_1_value text,
            custom_field_2_name varchar(100),
            custom_field_2_value text,
            custom_field_3_name varchar(100),
            custom_field_3_value text,
            custom_field_4_name varchar(100),
            custom_field_4_value text,
            coach_notes text,
            scored_at datetime DEFAULT CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY weekly_plan_id (weekly_plan_id),
            KEY coach_id (coach_id),
            KEY mentee_id (mentee_id)
        ) $charset_collate;";
        
        // Settings Table
        $settings_table = $wpdb->prefix . 'strava_coach_settings';
        $settings_sql = "CREATE TABLE $settings_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            setting_name varchar(100) NOT NULL,
            setting_value longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_setting (setting_name)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($relationships_sql);
        dbDelta($strava_users_sql);
        dbDelta($activities_sql);
        dbDelta($weekly_plans_sql);
        dbDelta($plan_activities_sql);
        dbDelta($weekly_scores_sql);
        dbDelta($settings_sql);
        
        // Insert default settings
        $this->insert_default_settings();
    }
    
    private function insert_default_settings() {
        global $wpdb;
        
        $settings_table = $wpdb->prefix . 'strava_coach_settings';
        
        $default_settings = array(
            array('strava_client_id', ''),
            array('strava_client_secret', ''),
            array('auto_sync_enabled', '1'),
            array('sync_frequency', 'daily'),
            array('sync_days_back', '30'),
            array('default_custom_field_1', 'Technique'),
            array('default_custom_field_2', 'Effort Level'),
            array('default_custom_field_3', 'Improvement Areas'),
            array('default_custom_field_4', 'Goals for Next Week')
        );
        
        foreach ($default_settings as $setting) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $settings_table WHERE setting_name = %s",
                $setting[0]
            ));
            
            if (!$existing) {
                $wpdb->insert(
                    $settings_table,
                    array(
                        'setting_name' => $setting[0],
                        'setting_value' => $setting[1]
                    ),
                    array('%s', '%s')
                );
            }
        }
    }
    
    // Helper methods
    public function get_mentee_coach($mentee_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'strava_coach_mentee_relationships';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT coach_id FROM $table_name WHERE mentee_id = %d AND status = 'active'",
            $mentee_id
        ));
    }
    
    public function get_coach_mentees($coach_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'strava_coach_mentee_relationships';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, u.display_name, u.user_email 
             FROM $table_name r 
             LEFT JOIN {$wpdb->users} u ON r.mentee_id = u.ID 
             WHERE r.coach_id = %d AND r.status = 'active'",
            $coach_id
        ));
    }
    
    public function save_strava_activity($user_id, $activity_data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'strava_activities';
        
        // Calculate pace from average speed
        $pace = 0;
        if (!empty($activity_data['average_speed']) && $activity_data['average_speed'] > 0) {
            $pace = 1000 / ($activity_data['average_speed'] * 60);
        }
        
        $data = array(
            'user_id' => $user_id,
            'strava_activity_id' => $activity_data['id'],
            'activity_type' => $activity_data['type'],
            'activity_name' => $activity_data['name'],
            'activity_date' => date('Y-m-d H:i:s', strtotime($activity_data['start_date'])),
            'distance' => isset($activity_data['distance']) ? $activity_data['distance'] : 0,
            'duration' => isset($activity_data['moving_time']) ? $activity_data['moving_time'] : 0,
            'elevation_gain' => isset($activity_data['total_elevation_gain']) ? $activity_data['total_elevation_gain'] : 0,
            'average_speed' => isset($activity_data['average_speed']) ? $activity_data['average_speed'] : 0,
            'max_speed' => isset($activity_data['max_speed']) ? $activity_data['max_speed'] : 0,
            'average_heartrate' => isset($activity_data['average_heartrate']) ? $activity_data['average_heartrate'] : 0,
            'max_heartrate' => isset($activity_data['max_heartrate']) ? $activity_data['max_heartrate'] : 0,
            'average_cadence' => isset($activity_data['average_cadence']) ? $activity_data['average_cadence'] : 0,
            'average_power' => isset($activity_data['average_watts']) ? $activity_data['average_watts'] : 0,
            'kilojoules' => isset($activity_data['kilojoules']) ? $activity_data['kilojoules'] : 0,
            'pace' => $pace,
            'raw_data' => json_encode($activity_data)
        );
        
        // Try to insert, if duplicate then update
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE user_id = %d AND strava_activity_id = %d",
            $user_id, $activity_data['id']
        ));
        
        if ($existing) {
            return $wpdb->update(
                $table_name,
                $data,
                array('id' => $existing)
            );
        } else {
            return $wpdb->insert($table_name, $data);
        }
    }
    
    public function get_user_activities($user_id, $filters = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'strava_activities';
        
        $where_clauses = array("user_id = %d");
        $params = array($user_id);
        
        if (!empty($filters['activity_type'])) {
            $where_clauses[] = "activity_type = %s";
            $params[] = $filters['activity_type'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = "activity_date >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = "activity_date <= %s";
            $params[] = $filters['date_to'];
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        $order_by = !empty($filters['order_by']) ? $filters['order_by'] : 'activity_date DESC';
        $limit = !empty($filters['limit']) ? "LIMIT " . intval($filters['limit']) : '';
        
        $sql = "SELECT * FROM $table_name WHERE $where_sql ORDER BY $order_by $limit";
        
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }
    
    public function get_setting($setting_name, $default = '') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'strava_coach_settings';
        
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM $table_name WHERE setting_name = %s",
            $setting_name
        ));
        
        return $value !== null ? $value : $default;
    }
    
    public function update_setting($setting_name, $setting_value) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'strava_coach_settings';
        
        return $wpdb->replace(
            $table_name,
            array(
                'setting_name' => $setting_name,
                'setting_value' => $setting_value
            ),
            array('%s', '%s')
        );
    }
}