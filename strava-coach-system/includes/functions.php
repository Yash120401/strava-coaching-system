<?php
/**
 * Helper Functions
 * File: includes/functions.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get coach's mentees
 */
function strava_coach_get_mentees($coach_id) {
    $db = new StravaCoach_Database();
    return $db->get_coach_mentees($coach_id);
}

/**
 * Get mentee's coach
 */
function strava_coach_get_coach($mentee_id) {
    $db = new StravaCoach_Database();
    return $db->get_mentee_coach($mentee_id);
}

/**
 * Check if user is connected to Strava
 */
function strava_coach_is_connected($user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'strava_user_integrations';
    
    $integration = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_name WHERE user_id = %d AND status = 'active'",
        $user_id
    ));
    
    return !empty($integration);
}

/**
 * Get user's Strava activities with filters
 */
function strava_coach_get_activities($user_id, $filters = array()) {
    $db = new StravaCoach_Database();
    return $db->get_user_activities($user_id, $filters);
}

/**
 * Get current week's plan for mentee
 */
function strava_coach_get_current_plan($mentee_id) {
    global $wpdb;
    
    $plans_table = $wpdb->prefix . 'strava_weekly_plans';
    $activities_table = $wpdb->prefix . 'strava_plan_activities';
    
    $week_start = date('Y-m-d', strtotime('monday this week'));
    
    $plan = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $plans_table WHERE mentee_id = %d AND week_start_date = %s AND status = 'active'",
        $mentee_id, $week_start
    ));
    
    if ($plan) {
        $activities = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $activities_table WHERE weekly_plan_id = %d ORDER BY day_of_week",
            $plan->id
        ));
        
        $plan->activities = $activities;
    }
    
    return $plan;
}

/**
 * Get weekly score for a plan
 */
function strava_coach_get_weekly_score($plan_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'strava_weekly_scores';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE weekly_plan_id = %d",
        $plan_id
    ));
}

/**
 * Format distance from meters to km
 */
function strava_coach_format_distance($meters) {
    if (empty($meters)) return '0 km';
    return round($meters / 1000, 2) . ' km';
}

/**
 * Format duration from seconds to human readable
 */
function strava_coach_format_duration($seconds) {
    if (empty($seconds)) return '0m';
    
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    if ($hours > 0) {
        return $hours . 'h ' . $minutes . 'm';
    }
    return $minutes . 'm';
}

/**
 * Format pace from decimal minutes per km
 */
function strava_coach_format_pace($pace_decimal) {
    if (empty($pace_decimal)) return '0:00/km';
    
    $minutes = floor($pace_decimal);
    $seconds = round(($pace_decimal - $minutes) * 60);
    
    return sprintf('%d:%02d/km', $minutes, $seconds);
}

/**
 * Get activity type options
 */
function strava_coach_get_activity_types() {
    return array(
        'Run' => 'Running',
        'Ride' => 'Cycling', 
        'Swim' => 'Swimming',
        'Walk' => 'Walking',
        'Hike' => 'Hiking',
        'Workout' => 'Strength Training',
        'Yoga' => 'Yoga',
        'Crossfit' => 'CrossFit'
    );
}

/**
 * Get intensity level options
 */
function strava_coach_get_intensity_levels() {
    return array(
        'recovery' => 'Recovery',
        'easy' => 'Easy',
        'moderate' => 'Moderate',
        'hard' => 'Hard',
        'very_hard' => 'Very Hard'
    );
}

/**
 * Calculate activity statistics for a user
 */
function strava_coach_calculate_stats($user_id, $date_from = null, $date_to = null) {
    $filters = array();
    
    if ($date_from) {
        $filters['date_from'] = $date_from;
    }
    if ($date_to) {
        $filters['date_to'] = $date_to;
    }
    
    $activities = strava_coach_get_activities($user_id, $filters);
    
    $stats = array(
        'total_activities' => count($activities),
        'total_distance' => 0,
        'total_duration' => 0,
        'avg_pace' => 0,
        'activities_by_type' => array()
    );
    
    $total_pace_activities = 0;
    $total_pace = 0;
    
    foreach ($activities as $activity) {
        $stats['total_distance'] += $activity->distance;
        $stats['total_duration'] += $activity->duration;
        
        if ($activity->pace > 0) {
            $total_pace += $activity->pace;
            $total_pace_activities++;
        }
        
        if (!isset($stats['activities_by_type'][$activity->activity_type])) {
            $stats['activities_by_type'][$activity->activity_type] = 0;
        }
        $stats['activities_by_type'][$activity->activity_type]++;
    }
    
    if ($total_pace_activities > 0) {
        $stats['avg_pace'] = $total_pace / $total_pace_activities;
    }
    
    return $stats;
}

/**
 * Get week boundaries
 */
function strava_coach_get_week_boundaries($date = null) {
    if (!$date) {
        $date = current_time('Y-m-d');
    }
    
    $week_start = date('Y-m-d', strtotime('monday this week', strtotime($date)));
    $week_end = date('Y-m-d', strtotime('sunday this week', strtotime($date)));
    
    return array(
        'start' => $week_start,
        'end' => $week_end
    );
}

/**
 * Check if current user can manage a specific mentee
 */
function strava_coach_can_manage_mentee($coach_id, $mentee_id) {
    $db = new StravaCoach_Database();
    $mentees = $db->get_coach_mentees($coach_id);
    
    foreach ($mentees as $mentee) {
        if ($mentee->mentee_id == $mentee_id) {
            return true;
        }
    }
    
    return false;
}

/**
 * Get plugin settings
 */
function strava_coach_get_setting($setting_name, $default = '') {
    $db = new StravaCoach_Database();
    $value = $db->get_setting($setting_name);
    
    return $value !== null ? $value : $default;
}

/**
 * Update plugin settings
 */
function strava_coach_update_setting($setting_name, $setting_value) {
    $db = new StravaCoach_Database();
    return $db->update_setting($setting_name, $setting_value);
}

/**
 * Log debug information (useful during development)
 */
function strava_coach_log($message, $type = 'info') {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Strava Coach ' . strtoupper($type) . '] ' . $message);
    }
}

/**
 * Validate email address
 */
function strava_coach_is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate secure random token
 */
function strava_coach_generate_token($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Check if feature is enabled
 */
function strava_coach_is_feature_enabled($feature) {
    $enabled_features = strava_coach_get_setting('enabled_features', array());
    
    if (is_string($enabled_features)) {
        $enabled_features = json_decode($enabled_features, true) ?: array();
    }
    
    return in_array($feature, $enabled_features);
}

/**
 * Get days of week array
 */
function strava_coach_get_days_of_week() {
    return array(
        1 => 'Monday',
        2 => 'Tuesday', 
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday'
    );
}

/**
 * Sanitize and validate form data
 */
function strava_coach_sanitize_form_data($data, $fields) {
    $sanitized = array();
    
    foreach ($fields as $field => $type) {
        if (!isset($data[$field])) {
            continue;
        }
        
        switch ($type) {
            case 'int':
                $sanitized[$field] = intval($data[$field]);
                break;
            case 'float':
                $sanitized[$field] = floatval($data[$field]);
                break;
            case 'email':
                $sanitized[$field] = sanitize_email($data[$field]);
                break;
            case 'text':
                $sanitized[$field] = sanitize_text_field($data[$field]);
                break;
            case 'textarea':
                $sanitized[$field] = sanitize_textarea_field($data[$field]);
                break;
            case 'url':
                $sanitized[$field] = esc_url_raw($data[$field]);
                break;
            default:
                $sanitized[$field] = sanitize_text_field($data[$field]);
        }
    }
    
    return $sanitized;
}