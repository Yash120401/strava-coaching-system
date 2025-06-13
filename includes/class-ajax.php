<?php
/**
 * AJAX Handler Class
 * File: includes/class-ajax.php
 */

class StravaCoach_Ajax {
    
    public function __construct() {
        // Dashboard AJAX handlers
        add_action('wp_ajax_get_analytics_data', array($this, 'get_analytics_data'));
        add_action('wp_ajax_get_dashboard_stats', array($this, 'get_dashboard_stats'));
        add_action('wp_ajax_get_current_plans', array($this, 'get_current_plans'));
        add_action('wp_ajax_get_all_weekly_scores', array($this, 'get_all_weekly_scores'));
        add_action('wp_ajax_get_weekly_scores', array($this, 'get_weekly_scores'));
        add_action('wp_ajax_get_mentee_stats', array($this, 'get_mentee_stats'));
        
        // Strava integration AJAX handlers
        add_action('wp_ajax_check_strava_connection', array($this, 'check_strava_connection'));
        add_action('wp_ajax_sync_strava_data', array($this, 'sync_strava_data'));
        add_action('wp_ajax_disconnect_strava', array($this, 'disconnect_strava'));
        
        // Weekly plan AJAX handlers
        add_action('wp_ajax_save_weekly_plan', array($this, 'save_weekly_plan'));
        add_action('wp_ajax_submit_weekly_score', array($this, 'submit_weekly_score'));
        
        // User management handlers
        add_action('wp_ajax_remove_mentee_from_coach', array($this, 'remove_mentee_from_coach'));
    }
    
    public function get_analytics_data() {
        check_ajax_referer('strava_coach_nonce', 'nonce');
        
        $filters = isset($_POST['filters']) ? $_POST['filters'] : array();
        $user_id = get_current_user_id();
        $db = new StravaCoach_Database();
        
        // Determine which user's data to get
        $target_user_id = $user_id;
        if (!empty($filters['mentee_id']) && current_user_can('view_coach_dashboard')) {
            $target_user_id = intval($filters['mentee_id']);
        }
        
        // Build filters for database query
        $db_filters = array();
        
        if (!empty($filters['time_period'])) {
            $days = intval($filters['time_period']);
            $db_filters['date_from'] = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        }
        
        if (!empty($filters['activity_type'])) {
            $db_filters['activity_type'] = sanitize_text_field($filters['activity_type']);
        }
        
        $db_filters['order_by'] = 'activity_date ASC';
        
        $activities = $db->get_user_activities($target_user_id, $db_filters);
        
        // Process data for charts
        $chart_data = array(
            'labels' => array(),
            'distances' => array(),
            'durations' => array(),
            'paces' => array(),
            'elevations' => array(),
            'activity_types' => array(),
            'weekly_progress' => array(),
            'summary' => array()
        );
        
        $total_distance = 0;
        $total_duration = 0;
        $total_elevation = 0;
        $pace_sum = 0;
        $pace_count = 0;
        $activity_type_counts = array();
        $weekly_data = array();
        
        foreach ($activities as $activity) {
            // Main chart data
            $chart_data['labels'][] = date('M j', strtotime($activity->activity_date));
            $chart_data['distances'][] = round($activity->distance / 1000, 2); // Convert to km
            $chart_data['durations'][] = round($activity->duration / 60, 1); // Convert to minutes
            $chart_data['paces'][] = $activity->pace ? round($activity->pace, 2) : 0;
            $chart_data['elevations'][] = $activity->elevation_gain ? round($activity->elevation_gain, 1) : 0;
            
            // Summary calculations
            $total_distance += $activity->distance;
            $total_duration += $activity->duration;
            $total_elevation += $activity->elevation_gain;
            
            if ($activity->pace > 0) {
                $pace_sum += $activity->pace;
                $pace_count++;
            }
            
            // Activity type distribution
            if (!isset($activity_type_counts[$activity->activity_type])) {
                $activity_type_counts[$activity->activity_type] = 0;
            }
            $activity_type_counts[$activity->activity_type]++;
            
            // Weekly progress data
            $week_key = date('Y-W', strtotime($activity->activity_date));
            if (!isset($weekly_data[$week_key])) {
                $weekly_data[$week_key] = array(
                    'distance' => 0,
                    'activities' => 0,
                    'week_start' => date('M j', strtotime($activity->activity_date . ' -' . (date('w', strtotime($activity->activity_date)) - 1) . ' days'))
                );
            }
            $weekly_data[$week_key]['distance'] += $activity->distance / 1000; // km
            $weekly_data[$week_key]['activities']++;
        }
        
        // Process weekly data for chart
        ksort($weekly_data);
        $chart_data['weekly_progress'] = array(
            'weeks' => array(),
            'distances' => array(),
            'activities' => array()
        );
        
        foreach ($weekly_data as $week_data) {
            $chart_data['weekly_progress']['weeks'][] = $week_data['week_start'];
            $chart_data['weekly_progress']['distances'][] = round($week_data['distance'], 1);
            $chart_data['weekly_progress']['activities'][] = $week_data['activities'];
        }
        
        // Summary statistics
        $chart_data['summary'] = array(
            'total_distance' => $total_distance / 1000, // km
            'total_activities' => count($activities),
            'total_duration' => $total_duration / 3600, // hours
            'total_elevation' => $total_elevation,
            'avg_pace' => $pace_count > 0 ? $pace_sum / $pace_count : 0
        );
        
        $chart_data['activity_types'] = $activity_type_counts;
        
        // Add plan comparison data if available
        $chart_data['plan_comparison'] = $this->get_plan_comparison_data($target_user_id, $filters);
        
        wp_send_json_success($chart_data);
    }
    
    /**
     * Get plan vs actual comparison data
     */
    private function get_plan_comparison_data($user_id, $filters) {
        global $wpdb;
        
        $plans_table = $wpdb->prefix . 'strava_weekly_plans';
        $activities_table = $wpdb->prefix . 'strava_plan_activities';
        $strava_activities_table = $wpdb->prefix . 'strava_activities';
        
        // Get recent plans for the user
        $days = isset($filters['time_period']) ? intval($filters['time_period']) : 30;
        $date_from = date('Y-m-d', strtotime("-{$days} days"));
        
        $plans = $wpdb->get_results($wpdb->prepare("
            SELECT p.id, p.plan_name, p.week_start_date
            FROM $plans_table p
            WHERE p.mentee_id = %d 
            AND p.week_start_date >= %s
            AND p.status = 'active'
            ORDER BY p.week_start_date ASC
        ", $user_id, $date_from));
        
        $comparison_data = array(
            'weeks' => array(),
            'planned_distance' => array(),
            'actual_distance' => array(),
            'planned_activities' => array(),
            'actual_activities' => array()
        );
        
        foreach ($plans as $plan) {
            $week_start = $plan->week_start_date;
            $week_end = date('Y-m-d', strtotime($week_start . ' +6 days'));
            
            // Get planned activities for this week
            $planned_activities = $wpdb->get_results($wpdb->prepare("
                SELECT target_distance, target_duration, activity_type
                FROM $activities_table
                WHERE weekly_plan_id = %d
            ", $plan->id));
            
            // Calculate planned totals
            $planned_distance = 0;
            $planned_count = 0;
            
            foreach ($planned_activities as $activity) {
                if ($activity->target_distance > 0 || $activity->target_duration > 0) {
                    $planned_distance += floatval($activity->target_distance) / 1000; // Convert to km
                    $planned_count++;
                }
            }
            
            // Get actual activities for that week
            $actual_result = $wpdb->get_row($wpdb->prepare("
                SELECT SUM(distance) as total_distance, COUNT(*) as activity_count
                FROM $strava_activities_table
                WHERE user_id = %d
                AND activity_date >= %s
                AND activity_date <= %s
            ", $user_id, $week_start . ' 00:00:00', $week_end . ' 23:59:59'));
            
            $actual_distance = $actual_result && $actual_result->total_distance ? $actual_result->total_distance / 1000 : 0;
            $actual_count = $actual_result && $actual_result->activity_count ? intval($actual_result->activity_count) : 0;
            
            $comparison_data['weeks'][] = date('M j', strtotime($week_start));
            $comparison_data['planned_distance'][] = round($planned_distance, 1);
            $comparison_data['actual_distance'][] = round($actual_distance, 1);
            $comparison_data['planned_activities'][] = $planned_count;
            $comparison_data['actual_activities'][] = $actual_count;
        }
        
        return $comparison_data;
    }
    
    public function get_dashboard_stats() {
        check_ajax_referer('strava_coach_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $db = new StravaCoach_Database();
        
        // Get this week's activities
        $week_start = date('Y-m-d H:i:s', strtotime('monday this week'));
        $week_end = date('Y-m-d H:i:s', strtotime('sunday this week 23:59:59'));
        
        $week_activities = $db->get_user_activities($user_id, array(
            'date_from' => $week_start,
            'date_to' => $week_end
        ));
        
        // Get total activities (last 30 days)
        $month_activities = $db->get_user_activities($user_id, array(
            'date_from' => date('Y-m-d H:i:s', strtotime('-30 days'))
        ));
        
        $stats = array(
            'week_activities' => count($week_activities),
            'total_activities' => count($month_activities),
            'week_distance' => 0,
            'week_duration' => 0
        );
        
        foreach ($week_activities as $activity) {
            $stats['week_distance'] += $activity->distance;
            $stats['week_duration'] += $activity->duration;
        }
        
        $stats['week_distance'] = round($stats['week_distance'] / 1000, 1); // km
        $stats['week_duration'] = round($stats['week_duration'] / 60, 0); // minutes
        
        wp_send_json_success($stats);
    }
    
    public function check_strava_connection() {
        check_ajax_referer('strava_coach_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'strava_user_integrations';
        $integration = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND status = 'active'",
            $user_id
        ));
        
        if ($integration) {
            $athlete_data = json_decode($integration->athlete_data, true);
            $response_data = array(
                'connected' => true,
                'athlete_name' => $athlete_data['firstname'] . ' ' . $athlete_data['lastname'],
                'last_sync' => $integration->last_sync ? date('M j, Y g:i A', strtotime($integration->last_sync)) : null
            );
        } else {
            $response_data = array(
                'connected' => false
            );
        }
        
        wp_send_json_success($response_data);
    }
    
    public function sync_strava_data() {
        check_ajax_referer('strava_coach_nonce', 'nonce');
        
        if (!current_user_can('connect_strava')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        $user_id = get_current_user_id();
        
        // Check if user is connected to Strava
        if (!strava_coach_is_connected($user_id)) {
            wp_send_json_error(array('message' => 'Not connected to Strava. Please connect first.'));
        }
        
        try {
            $strava_api = new StravaCoach_StravaAPI();
            $db = new StravaCoach_Database();
            $sync_days = $db->get_setting('sync_days_back', '30');
            
            $result = $strava_api->sync_user_activities($user_id, intval($sync_days));
            
            if ($result !== false) {
                wp_send_json_success(array(
                    'message' => "Successfully synced {$result} activities",
                    'count' => $result
                ));
            } else {
                wp_send_json_error(array('message' => 'No activities found or sync failed'));
            }
            
        } catch (Exception $e) {
            strava_coach_log('Sync error for user ' . $user_id . ': ' . $e->getMessage(), 'error');
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function disconnect_strava() {
        check_ajax_referer('strava_coach_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'strava_user_integrations';
        $result = $wpdb->update(
            $table_name,
            array('status' => 'inactive'),
            array('user_id' => $user_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => 'Failed to disconnect'));
        }
    }
    
    public function save_weekly_plan() {
        check_ajax_referer('strava_coach_nonce', 'nonce');
        
        if (!current_user_can('create_weekly_plans')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        $coach_id = get_current_user_id();
        $mentee_id = intval($_POST['mentee_id']);
        $week_start = sanitize_text_field($_POST['week_start']);
        $plan_name = sanitize_text_field($_POST['plan_name']);
        $activities_json = $_POST['activities'];
        
        // Validate inputs
        if (empty($mentee_id) || empty($week_start) || empty($plan_name)) {
            wp_send_json_error(array('message' => 'Missing required fields'));
        }
        
        // Validate that coach can manage this mentee
        $db = new StravaCoach_Database();
        $coach_mentees = $db->get_coach_mentees($coach_id);
        $can_manage = false;
        
        foreach ($coach_mentees as $mentee) {
            if ($mentee->mentee_id == $mentee_id) {
                $can_manage = true;
                break;
            }
        }
        
        if (!$can_manage) {
            wp_send_json_error(array('message' => 'You cannot create plans for this mentee'));
        }
        
        // Parse activities JSON
        $activities_data = json_decode(stripslashes($activities_json), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => 'Invalid activities data'));
        }
        
        // Process activities
        $processed_activities = array();
        foreach ($activities_data as $day => $activity) {
            if (!empty($activity['activity_type'])) {
                $processed_activities[] = array(
                    'day_of_week' => intval($activity['day_of_week']),
                    'activity_type' => sanitize_text_field($activity['activity_type']),
                    'activity_name' => sanitize_text_field($activity['activity_name']),
                    'target_distance' => floatval($activity['target_distance']) * 1000, // Convert km to meters
                    'target_duration' => intval($activity['target_duration']) * 60, // Convert minutes to seconds
                    'target_pace' => floatval($activity['target_pace']),
                    'target_heartrate_min' => intval($activity['target_heartrate_min']),
                    'target_heartrate_max' => intval($activity['target_heartrate_max']),
                    'target_elevation' => floatval($activity['target_elevation']),
                    'intensity_level' => sanitize_text_field($activity['intensity_level']),
                    'notes' => sanitize_textarea_field($activity['notes'])
                );
            }
        }
        
        if (empty($processed_activities)) {
            wp_send_json_error(array('message' => 'No activities defined in the plan'));
        }
        
        // Save the plan
        global $wpdb;
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            $plans_table = $wpdb->prefix . 'strava_weekly_plans';
            $activities_table = $wpdb->prefix . 'strava_plan_activities';
            
            // Insert or update weekly plan
            $existing_plan = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $plans_table WHERE mentee_id = %d AND week_start_date = %s",
                $mentee_id, $week_start
            ));
            
            if ($existing_plan) {
                $wpdb->update(
                    $plans_table,
                    array('plan_name' => $plan_name),
                    array('id' => $existing_plan),
                    array('%s'),
                    array('%d')
                );
                $plan_id = $existing_plan;
                
                // Delete existing activities
                $wpdb->delete($activities_table, array('weekly_plan_id' => $plan_id), array('%d'));
            } else {
                $result = $wpdb->insert(
                    $plans_table,
                    array(
                        'coach_id' => $coach_id,
                        'mentee_id' => $mentee_id,
                        'week_start_date' => $week_start,
                        'plan_name' => $plan_name
                    ),
                    array('%d', '%d', '%s', '%s')
                );
                
                if ($result === false) {
                    throw new Exception('Failed to create weekly plan');
                }
                
                $plan_id = $wpdb->insert_id;
            }
            
            // Insert activities
            foreach ($processed_activities as $activity) {
                $result = $wpdb->insert(
                    $activities_table,
                    array(
                        'weekly_plan_id' => $plan_id,
                        'day_of_week' => $activity['day_of_week'],
                        'activity_type' => $activity['activity_type'],
                        'activity_name' => $activity['activity_name'],
                        'target_distance' => $activity['target_distance'],
                        'target_duration' => $activity['target_duration'],
                        'target_pace' => $activity['target_pace'],
                        'target_heartrate_min' => $activity['target_heartrate_min'],
                        'target_heartrate_max' => $activity['target_heartrate_max'],
                        'target_elevation' => $activity['target_elevation'],
                        'intensity_level' => $activity['intensity_level'],
                        'notes' => $activity['notes']
                    ),
                    array('%d', '%d', '%s', '%s', '%f', '%d', '%f', '%d', '%d', '%f', '%s', '%s')
                );
                
                if ($result === false) {
                    throw new Exception('Failed to save activity');
                }
            }
            
            $wpdb->query('COMMIT');
            wp_send_json_success(array(
                'plan_id' => $plan_id,
                'message' => 'Weekly plan saved successfully'
            ));
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function submit_weekly_score() {
        check_ajax_referer('strava_coach_nonce', 'nonce');
        
        if (!current_user_can('score_activities')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        $coach_id = get_current_user_id();
        $weekly_plan_id = intval($_POST['weekly_plan_id']);
        
        // Get plan details to verify coach ownership
        global $wpdb;
        $plans_table = $wpdb->prefix . 'strava_weekly_plans';
        $plan = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $plans_table WHERE id = %d AND coach_id = %d",
            $weekly_plan_id, $coach_id
        ));
        
        if (!$plan) {
            wp_send_json_error(array('message' => 'Plan not found or permission denied'));
        }
        
        // Calculate overall score
        $pace_score = floatval($_POST['pace_score']);
        $distance_score = floatval($_POST['distance_score']);
        $consistency_score = floatval($_POST['consistency_score']);
        $elevation_score = floatval($_POST['elevation_score']);
        $overall_score = ($pace_score + $distance_score + $consistency_score + $elevation_score) / 4;
        
        // Prepare score data
        $scores_table = $wpdb->prefix . 'strava_weekly_scores';
        $score_data = array(
            'weekly_plan_id' => $weekly_plan_id,
            'coach_id' => $coach_id,
            'mentee_id' => $plan->mentee_id,
            'pace_score' => $pace_score,
            'distance_score' => $distance_score,
            'consistency_score' => $consistency_score,
            'elevation_score' => $elevation_score,
            'overall_score' => $overall_score,
            'custom_field_1_name' => 'Technique',
            'custom_field_1_value' => sanitize_textarea_field($_POST['custom_field_1_value']),
            'custom_field_2_name' => 'Effort Level',
            'custom_field_2_value' => sanitize_textarea_field($_POST['custom_field_2_value']),
            'custom_field_3_name' => 'Improvement Areas',
            'custom_field_3_value' => sanitize_textarea_field($_POST['custom_field_3_value']),
            'custom_field_4_name' => 'Goals for Next Week',
            'custom_field_4_value' => sanitize_textarea_field($_POST['custom_field_4_value']),
            'coach_notes' => sanitize_textarea_field($_POST['coach_notes'])
        );
        
        $format = array('%d', '%d', '%d', '%f', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');
        
        // Check if score already exists
        $existing_score = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $scores_table WHERE weekly_plan_id = %d",
            $weekly_plan_id
        ));
        
        if ($existing_score) {
            $result = $wpdb->update(
                $scores_table,
                $score_data,
                array('id' => $existing_score),
                $format,
                array('%d')
            );
        } else {
            $result = $wpdb->insert($scores_table, $score_data, $format);
        }
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => 'Failed to save score'));
        }
    }
    
    public function get_current_plans() {
        check_ajax_referer('strava_coach_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        
        global $wpdb;
        $plans_table = $wpdb->prefix . 'strava_weekly_plans';
        $activities_table = $wpdb->prefix . 'strava_plan_activities';
        
        // Get current week's start
        $week_start = date('Y-m-d', strtotime('monday this week'));
        
        $response_data = array();
        
        if (in_array('strava_coach', $user->roles)) {
            // Coach view - get all plans for current week
            $plans = $wpdb->get_results($wpdb->prepare("
                SELECT p.*, u.display_name as mentee_name 
                FROM $plans_table p 
                LEFT JOIN {$wpdb->users} u ON p.mentee_id = u.ID 
                WHERE p.coach_id = %d AND p.week_start_date = %s AND p.status = 'active'
            ", $user_id, $week_start));
            
            $current_plans_html = '';
            foreach ($plans as $plan) {
                $current_plans_html .= '<div class="plan-item">';
                $current_plans_html .= '<h4>' . esc_html($plan->plan_name) . ' - ' . esc_html($plan->mentee_name) . '</h4>';
                $current_plans_html .= '<button class="btn btn-small score-weekly-plan" data-plan-id="' . $plan->id . '">Score This Week</button>';
                $current_plans_html .= '</div>';
            }
            
            $response_data['current_plans'] = $current_plans_html ?: '<p>No plans for this week.</p>';
            
        } else {
            // Mentee view - get own plan
            $db = new StravaCoach_Database();
            $coach_id = $db->get_mentee_coach($user_id);
            
            if ($coach_id) {
                $plan = $wpdb->get_row($wpdb->prepare("
                    SELECT * FROM $plans_table 
                    WHERE mentee_id = %d AND week_start_date = %s AND status = 'active'
                ", $user_id, $week_start));
                
                if ($plan) {
                    $activities = $wpdb->get_results($wpdb->prepare("
                        SELECT * FROM $activities_table 
                        WHERE weekly_plan_id = %d 
                        ORDER BY day_of_week
                    ", $plan->id));
                    
                    $plan_html = '<h4>' . esc_html($plan->plan_name) . '</h4>';
                    $plan_html .= '<div class="daily-activities">';
                    
                    $days = ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                    
                    foreach ($activities as $activity) {
                        $plan_html .= '<div class="daily-activity">';
                        $plan_html .= '<strong>' . $days[$activity->day_of_week] . ':</strong> ';
                        $plan_html .= esc_html($activity->activity_name ?: $activity->activity_type);
                        
                        if ($activity->target_distance > 0) {
                            $plan_html .= ' - ' . round($activity->target_distance / 1000, 1) . 'km';
                        }
                        if ($activity->target_duration > 0) {
                            $plan_html .= ' - ' . round($activity->target_duration / 60) . 'min';
                        }
                        if ($activity->notes) {
                            $plan_html .= '<br><em>' . esc_html($activity->notes) . '</em>';
                        }
                        $plan_html .= '</div>';
                    }
                    
                    $plan_html .= '</div>';
                    $response_data['mentee_plan'] = $plan_html;
                } else {
                    $response_data['mentee_plan'] = '<p>No plan assigned for this week.</p>';
                }
            } else {
                $response_data['mentee_plan'] = '<p>No coach assigned.</p>';
            }
        }
        
        wp_send_json_success($response_data);
    }
    
    public function get_weekly_scores() {
        check_ajax_referer('strava_coach_nonce', 'nonce');
        
        $plan_id = intval($_POST['plan_id']);
        
        global $wpdb;
        $scores_table = $wpdb->prefix . 'strava_weekly_scores';
        
        $score = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $scores_table WHERE weekly_plan_id = %d",
            $plan_id
        ));
        
        wp_send_json_success($score);
    }
    
    public function get_all_weekly_scores() {
        check_ajax_referer('strava_coach_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        
        global $wpdb;
        $scores_table = $wpdb->prefix . 'strava_weekly_scores';
        $plans_table = $wpdb->prefix . 'strava_weekly_plans';
        
        if (in_array('strava_mentee', $user->roles)) {
            // Mentee view - get own scores
            $scores = $wpdb->get_results($wpdb->prepare("
                SELECT s.*, p.plan_name, p.week_start_date,
                       c.display_name as coach_name
                FROM $scores_table s
                LEFT JOIN $plans_table p ON s.weekly_plan_id = p.id
                LEFT JOIN {$wpdb->users} c ON s.coach_id = c.ID
                WHERE s.mentee_id = %d
                ORDER BY p.week_start_date DESC
                LIMIT 10
            ", $user_id));
        } else {
            // Coach view - get scores for all mentees with pending scores
            $scores = $wpdb->get_results($wpdb->prepare("
                SELECT s.*, p.plan_name, p.week_start_date, u.display_name as mentee_name
                FROM $scores_table s
                LEFT JOIN $plans_table p ON s.weekly_plan_id = p.id
                LEFT JOIN {$wpdb->users} u ON s.mentee_id = u.ID
                WHERE s.coach_id = %d
                ORDER BY p.week_start_date DESC
                LIMIT 20
            ", $user_id));
            
            // Also get pending scores (plans without scores)
            $pending_plans = $wpdb->get_results($wpdb->prepare("
                SELECT p.*, u.display_name as mentee_name
                FROM $plans_table p
                LEFT JOIN $scores_table s ON p.id = s.weekly_plan_id
                LEFT JOIN {$wpdb->users} u ON p.mentee_id = u.ID
                WHERE p.coach_id = %d 
                AND s.id IS NULL
                AND p.week_start_date <= CURDATE()
                AND p.status = 'active'
                ORDER BY p.week_start_date DESC
                LIMIT 10
            ", $user_id));
        }
        
        $scores_html = '';
        
        if (!empty($scores)) {
            foreach ($scores as $score) {
                $scores_html .= '<div class="score-card">';
                $scores_html .= '<h4>' . esc_html($score->plan_name);
                if (isset($score->mentee_name)) {
                    $scores_html .= ' - ' . esc_html($score->mentee_name);
                } elseif (isset($score->coach_name)) {
                    $scores_html .= ' (by ' . esc_html($score->coach_name) . ')';
                }
                $scores_html .= '</h4>';
                $scores_html .= '<p><strong>Week of ' . date('M j, Y', strtotime($score->week_start_date)) . '</strong></p>';
                
                $scores_html .= '<div class="score-grid">';
                $scores_html .= '<div class="score-item"><span>Pace:</span><span class="score-value">' . $score->pace_score . '/10</span></div>';
                $scores_html .= '<div class="score-item"><span>Distance:</span><span class="score-value">' . $score->distance_score . '/10</span></div>';
                $scores_html .= '<div class="score-item"><span>Consistency:</span><span class="score-value">' . $score->consistency_score . '/10</span></div>';
                $scores_html .= '<div class="score-item"><span>Elevation:</span><span class="score-value">' . $score->elevation_score . '/10</span></div>';
                $scores_html .= '</div>';
                
                $scores_html .= '<p><strong>Overall: ' . round($score->overall_score, 1) . '/10</strong></p>';
                
                // Show custom feedback fields
                if ($score->custom_field_1_value) {
                    $scores_html .= '<div class="feedback-field"><strong>' . esc_html($score->custom_field_1_name) . ':</strong> ' . esc_html($score->custom_field_1_value) . '</div>';
                }
                if ($score->custom_field_2_value) {
                    $scores_html .= '<div class="feedback-field"><strong>' . esc_html($score->custom_field_2_name) . ':</strong> ' . esc_html($score->custom_field_2_value) . '</div>';
                }
                if ($score->custom_field_3_value) {
                    $scores_html .= '<div class="feedback-field"><strong>' . esc_html($score->custom_field_3_name) . ':</strong> ' . esc_html($score->custom_field_3_value) . '</div>';
                }
                if ($score->custom_field_4_value) {
                    $scores_html .= '<div class="feedback-field"><strong>' . esc_html($score->custom_field_4_name) . ':</strong> ' . esc_html($score->custom_field_4_value) . '</div>';
                }
                
                if ($score->coach_notes) {
                    $scores_html .= '<div class="coach-notes"><strong>Coach Notes:</strong><br><em>' . esc_html($score->coach_notes) . '</em></div>';
                }
                
                $scores_html .= '</div>';
            }
        }
        
        $response_data = array();
        
        if (in_array('strava_mentee', $user->roles)) {
            $response_data = $scores_html ?: '<p>No scores available yet. Complete some training plans to receive feedback from your coach!</p>';
        } else {
            $response_data = $scores_html ?: '<p>No scores given yet.</p>';
            
            // Add pending scores section for coaches
            if (!empty($pending_plans)) {
                $pending_html = '<h4>Plans Awaiting Scores:</h4>';
                foreach ($pending_plans as $plan) {
                    $pending_html .= '<div class="pending-plan">';
                    $pending_html .= '<strong>' . esc_html($plan->plan_name) . '</strong> - ' . esc_html($plan->mentee_name);
                    $pending_html .= '<span class="week-date"> (Week of ' . date('M j', strtotime($plan->week_start_date)) . ')</span>';
                    $pending_html .= '<button class="btn btn-small score-weekly-plan" data-plan-id="' . $plan->id . '">Score Now</button>';
                    $pending_html .= '</div>';
                }
                $response_data = array('scores' => $scores_html, 'pending' => $pending_html);
            }
        }
        
        wp_send_json_success($response_data);
    }
}