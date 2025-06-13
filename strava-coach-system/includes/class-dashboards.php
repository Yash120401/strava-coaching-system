<?php
/**
 * Dashboard Management Class
 * File: includes/class-dashboards.php
 */

class StravaCoach_Dashboards {
    
    public function __construct() {
        add_shortcode('coach_dashboard', array($this, 'coach_dashboard_shortcode'));
        add_shortcode('mentee_dashboard', array($this, 'mentee_dashboard_shortcode'));
        add_action('init', array($this, 'check_dashboard_access'));
        add_action('template_redirect', array($this, 'redirect_unauthorized_users'));
    }
    
    public function check_dashboard_access() {
        // Redirect logic will be handled by template_redirect
    }
    
    public function redirect_unauthorized_users() {
        if (is_page('coach-dashboard') || is_page('mentee-dashboard')) {
            if (!is_user_logged_in()) {
                wp_redirect(wp_login_url(get_permalink()));
                exit;
            }
            
            $user = wp_get_current_user();
            $page_slug = get_post_field('post_name', get_post());
            
            if ($page_slug == 'coach-dashboard' && !in_array('strava_coach', $user->roles)) {
                wp_die('Access denied. You need to be a coach to view this page.');
            }
            
            if ($page_slug == 'mentee-dashboard' && !in_array('strava_mentee', $user->roles)) {
                wp_die('Access denied. You need to be a mentee to view this page.');
            }
        }
    }
    
    public function coach_dashboard_shortcode($atts) {
        if (!current_user_can('view_coach_dashboard')) {
            return '<p>Access denied.</p>';
        }
        
        ob_start();
        $this->render_coach_dashboard();
        return ob_get_clean();
    }
    
    public function mentee_dashboard_shortcode($atts) {
        if (!current_user_can('view_mentee_dashboard')) {
            return '<p>Access denied.</p>';
        }
        
        ob_start();
        $this->render_mentee_dashboard();
        return ob_get_clean();
    }
    
    private function render_coach_dashboard() {
        $current_user_id = get_current_user_id();
        $db = new StravaCoach_Database();
        $mentees = $db->get_coach_mentees($current_user_id);
        
        ?>
        <div id="coach-dashboard" class="strava-coach-dashboard">
            <div class="dashboard-header">
                <h1>Coach Dashboard</h1>
                <div class="header-stats">
                    <div class="stat-box">
                        <span class="stat-number"><?php echo count($mentees); ?></span>
                        <span class="stat-label">Active Mentees</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-number" id="total-activities">-</span>
                        <span class="stat-label">This Week Activities</span>
                    </div>
                </div>
            </div>
            
            <!-- Mentee Management Section -->
            <div class="dashboard-section">
                <h2>Manage Mentees</h2>
                <div class="mentee-management">
                    <button id="add-mentee-btn" class="btn btn-primary">Add New Mentee</button>
                    
                    <div class="mentees-grid">
                        <?php if (empty($mentees)): ?>
                            <p>No mentees assigned yet. <a href="#" id="add-first-mentee">Add your first mentee</a></p>
                        <?php else: ?>
                            <?php foreach ($mentees as $mentee): ?>
                                <div class="mentee-card" data-mentee-id="<?php echo $mentee->mentee_id; ?>">
                                    <div class="mentee-info">
                                        <h3><?php echo esc_html($mentee->display_name); ?></h3>
                                        <p><?php echo esc_html($mentee->user_email); ?></p>
                                        <div class="mentee-stats">
                                            <span class="activity-count">Loading...</span>
                                            <span class="last-activity">Last activity: Loading...</span>
                                        </div>
                                    </div>
                                    <div class="mentee-actions">
                                        <button class="btn btn-small view-mentee-progress" data-mentee-id="<?php echo $mentee->mentee_id; ?>">View Progress</button>
                                        <button class="btn btn-small create-weekly-plan" data-mentee-id="<?php echo $mentee->mentee_id; ?>">Create Plan</button>
                                        <button class="btn btn-small btn-danger remove-mentee" data-mentee-id="<?php echo $mentee->mentee_id; ?>">Remove</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Analytics Section -->
            <div class="dashboard-section">
                <h2>Performance Analytics</h2>
                <div class="analytics-controls">
                    <select id="mentee-filter">
                        <option value="">All Mentees</option>
                        <?php foreach ($mentees as $mentee): ?>
                            <option value="<?php echo $mentee->mentee_id; ?>"><?php echo esc_html($mentee->display_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select id="time-filter">
                        <option value="7">Last 7 days</option>
                        <option value="30" selected>Last 30 days</option>
                        <option value="90">Last 90 days</option>
                    </select>
                    
                    <select id="activity-filter">
                        <option value="">All Activities</option>
                        <option value="Run">Running</option>
                        <option value="Ride">Cycling</option>
                        <option value="Swim">Swimming</option>
                        <option value="Walk">Walking</option>
                    </select>
                    
                    <select id="chart-type-filter">
                        <option value="line">Line Charts</option>
                        <option value="bar">Bar Charts</option>
                        <option value="mixed">Mixed Charts</option>
                    </select>
                    
                    <button id="refresh-analytics" class="btn btn-secondary">Refresh</button>
                </div>
                
                <div class="charts-container">
                    <div class="chart-wrapper">
                        <canvas id="mentee-performance-chart"></canvas>
                    </div>
                    
                    <div class="charts-grid">
                        <div class="chart-wrapper-small">
                            <canvas id="activity-type-chart"></canvas>
                        </div>
                        <div class="chart-wrapper-small">
                            <canvas id="weekly-progress-chart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-wrapper">
                        <canvas id="plan-comparison-chart"></canvas>
                    </div>
                    
                    <div class="summary-stats">
                        <div class="stat-item">
                            <span class="stat-label">Total Distance</span>
                            <span class="stat-value" id="total-distance">-</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Activities</span>
                            <span class="stat-value" id="total-activities">-</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Avg Pace</span>
                            <span class="stat-value" id="avg-pace">-</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Total Elevation</span>
                            <span class="stat-value" id="total-elevation">-</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Weekly Plans Section -->
            <div class="dashboard-section">
                <h2>Weekly Plans & Scoring</h2>
                <div class="plans-container">
                    <div class="active-plans">
                        <h3>Current Week Plans</h3>
                        <div id="current-plans-list">
                            <!-- Dynamically loaded -->
                        </div>
                    </div>
                    
                    <div class="scoring-section">
                        <h3>Weekly Scoring</h3>
                        <div id="pending-scores">
                            <!-- Dynamically loaded -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modals -->
        <div id="add-mentee-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Add New Mentee</h2>
                <form id="add-mentee-form">
                    <label for="available-users">Select User:</label>
                    <select id="available-users" name="mentee_id" required>
                        <option value="">Choose a user...</option>
                        <?php
                        // Get users with mentee role who don't have a coach
                        $available_mentees = $this->get_available_mentees();
                        foreach ($available_mentees as $mentee):
                        ?>
                            <option value="<?php echo $mentee->ID; ?>"><?php echo $mentee->display_name . ' (' . $mentee->user_email . ')'; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">Add Mentee</button>
                </form>
            </div>
        </div>
        
        <div id="weekly-plan-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Create Weekly Plan</h2>
                <form id="weekly-plan-form">
                    <input type="hidden" id="plan-mentee-id" name="mentee_id" />
                    
                    <label for="week-start">Week Starting:</label>
                    <input type="date" id="week-start" name="week_start" required />
                    
                    <label for="plan-name">Plan Name:</label>
                    <input type="text" id="plan-name" name="plan_name" placeholder="e.g., Base Building Week 1" required />
                    
                    <div id="daily-activities">
                        <!-- Days will be generated by JavaScript -->
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Save Weekly Plan</button>
                </form>
            </div>
        </div>
        
        <div id="scoring-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Score Weekly Performance</h2>
                <form id="scoring-form">
                    <input type="hidden" id="score-plan-id" name="weekly_plan_id" />
                    
                    <div class="score-section">
                        <label>Pace Score (0-10):</label>
                        <input type="range" name="pace_score" min="0" max="10" step="0.1" />
                        <span class="score-display">5.0</span>
                    </div>
                    
                    <div class="score-section">
                        <label>Distance Score (0-10):</label>
                        <input type="range" name="distance_score" min="0" max="10" step="0.1" />
                        <span class="score-display">5.0</span>
                    </div>
                    
                    <div class="score-section">
                        <label>Consistency Score (0-10):</label>
                        <input type="range" name="consistency_score" min="0" max="10" step="0.1" />
                        <span class="score-display">5.0</span>
                    </div>
                    
                    <div class="score-section">
                        <label>Elevation Score (0-10):</label>
                        <input type="range" name="elevation_score" min="0" max="10" step="0.1" />
                        <span class="score-display">5.0</span>
                    </div>
                    
                    <div class="custom-fields">
                        <h3>Custom Feedback Fields</h3>
                        <div class="custom-field">
                            <label id="custom-field-1-label">Technique:</label>
                            <textarea name="custom_field_1_value" rows="3"></textarea>
                        </div>
                        <div class="custom-field">
                            <label id="custom-field-2-label">Effort Level:</label>
                            <textarea name="custom_field_2_value" rows="3"></textarea>
                        </div>
                        <div class="custom-field">
                            <label id="custom-field-3-label">Improvement Areas:</label>
                            <textarea name="custom_field_3_value" rows="3"></textarea>
                        </div>
                        <div class="custom-field">
                            <label id="custom-field-4-label">Goals for Next Week:</label>
                            <textarea name="custom_field_4_value" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="score-section">
                        <label>Coach Notes:</label>
                        <textarea name="coach_notes" rows="4" placeholder="Overall feedback and notes..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Submit Score</button>
                </form>
            </div>
        </div>
        <?php
    }
    
    private function render_mentee_dashboard() {
        $current_user_id = get_current_user_id();
        $db = new StravaCoach_Database();
        $coach_id = $db->get_mentee_coach($current_user_id);
        $coach = $coach_id ? get_user_by('ID', $coach_id) : null;
        
        ?>
        <div id="mentee-dashboard" class="strava-coach-dashboard">
            <div class="dashboard-header">
                <h1>My Training Dashboard</h1>
                <div class="header-stats">
                    <?php if ($coach): ?>
                        <div class="stat-box">
                            <span class="stat-label">Coach</span>
                            <span class="stat-number"><?php echo esc_html($coach->display_name); ?></span>
                        </div>
                    <?php else: ?>
                        <div class="stat-box">
                            <span class="stat-label">Status</span>
                            <span class="stat-number">No Coach Assigned</span>
                        </div>
                    <?php endif; ?>
                    <div class="stat-box">
                        <span class="stat-number" id="week-activities">-</span>
                        <span class="stat-label">This Week</span>
                    </div>
                </div>
            </div>
            
            <!-- Strava Connection Section -->
            <div class="dashboard-section">
                <h2>Strava Connection</h2>
                <div class="strava-connection">
                    <div id="strava-status">
                        <!-- Will be populated by JavaScript -->
                    </div>
                    <div class="strava-actions">
                        <button id="connect-strava" class="btn btn-primary" style="display: none;">Connect to Strava</button>
                        <button id="sync-strava" class="btn btn-secondary" style="display: none;">Sync Data</button>
                        <button id="disconnect-strava" class="btn btn-danger" style="display: none;">Disconnect</button>
                    </div>
                    <div id="last-sync">
                        <!-- Last sync info -->
                    </div>
                </div>
            </div>
            
            <!-- Performance Analytics -->
            <div class="dashboard-section">
                <h2>My Performance</h2>
                <div class="analytics-controls">
                    <select id="time-filter">
                        <option value="7">Last 7 days</option>
                        <option value="30" selected>Last 30 days</option>
                        <option value="90">Last 90 days</option>
                    </select>
                    
                    <select id="activity-filter">
                        <option value="">All Activities</option>
                        <option value="Run">Running</option>
                        <option value="Ride">Cycling</option>
                        <option value="Swim">Swimming</option>
                        <option value="Walk">Walking</option>
                    </select>
                    
                    <select id="chart-type-filter">
                        <option value="line">Line Charts</option>
                        <option value="bar">Bar Charts</option>
                        <option value="mixed">Mixed Charts</option>
                    </select>
                    
                    <button id="refresh-analytics" class="btn btn-secondary">Refresh</button>
                </div>
                
                <div class="charts-container">
                    <div class="chart-wrapper">
                        <canvas id="performance-chart"></canvas>
                    </div>
                    
                    <div class="charts-grid">
                        <div class="chart-wrapper-small">
                            <canvas id="mentee-activity-type-chart"></canvas>
                        </div>
                        <div class="chart-wrapper-small">
                            <canvas id="mentee-weekly-progress-chart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-wrapper">
                        <canvas id="mentee-plan-comparison-chart"></canvas>
                    </div>
                    
                    <div class="summary-stats">
                        <div class="stat-item">
                            <span class="stat-label">Total Distance</span>
                            <span class="stat-value" id="mentee-total-distance">-</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Activities</span>
                            <span class="stat-value" id="mentee-total-activities">-</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Avg Pace</span>
                            <span class="stat-value" id="mentee-avg-pace">-</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Plan Adherence</span>
                            <span class="stat-value" id="mentee-plan-adherence">-</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Weekly Plans -->
            <div class="dashboard-section">
                <h2>Training Plans</h2>
                <div class="plans-container">
                    <div class="current-plan">
                        <h3>This Week's Plan</h3>
                        <div id="current-plan">
                            <!-- Dynamically loaded -->
                        </div>
                    </div>
                    
                    <div class="plan-history">
                        <h3>Previous Plans & Scores</h3>
                        <div id="plan-history">
                            <!-- Dynamically loaded -->
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Weekly Score Display -->
            <div class="dashboard-section">
                <h2>Weekly Scores</h2>
                <div id="weekly-scores">
                    <!-- Dynamically loaded -->
                </div>
            </div>
        </div>
        <?php
    }
    
    private function get_available_mentees() {
        $db = new StravaCoach_Database();
        $mentees = get_users(array('role' => 'strava_mentee'));
        $available = array();
        
        foreach ($mentees as $mentee) {
            $coach_id = $db->get_mentee_coach($mentee->ID);
            if (!$coach_id) {
                $available[] = $mentee;
            }
        }
        
        return $available;
    }
}