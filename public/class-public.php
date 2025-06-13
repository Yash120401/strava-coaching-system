<?php
/**
 * Public-facing functionality of the plugin
 * File: public/class-public.php
 */

class Strava_Coaching_Public
{

    private $plugin_name;
    private $version;

    /**
     * Constructor
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // Add AJAX handlers for public-facing functionality
        add_action('wp_ajax_get_public_chart_data', array($this, 'ajax_get_public_chart_data'));
        add_action('wp_ajax_nopriv_get_public_chart_data', array($this, 'ajax_get_public_chart_data'));

        // Add disconnect and sync AJAX handlers (reuse admin handlers)
        add_action('wp_ajax_disconnect_strava', array($this, 'ajax_public_disconnect'));
        add_action('wp_ajax_sync_strava_activities', array($this, 'ajax_public_sync'));
    }

    /**
     * Enqueue public styles
     */
    public function enqueue_styles()
    {
        wp_enqueue_style(
            $this->plugin_name,
            STRAVA_COACHING_PLUGIN_URL . 'public/css/public-style.css',
            array(),
            $this->version,
            'all'
        );

        // Enqueue chart styles if on a page with chart shortcodes
        global $post;
        if (
            is_a($post, 'WP_Post') && (
                has_shortcode($post->post_content, 'strava_dashboard') ||
                has_shortcode($post->post_content, 'strava_progress_chart') ||
                has_shortcode($post->post_content, 'strava_activity_chart')
            )
        ) {
            wp_enqueue_style(
                $this->plugin_name . '-charts',
                STRAVA_COACHING_PLUGIN_URL . 'public/css/public-charts.css',
                array(),
                $this->version,
                'all'
            );
        }
    }

    /**
     * Enqueue public scripts
     */
    public function enqueue_scripts()
    {
        global $post;

        // Check if page contains our shortcodes
        if (
            is_a($post, 'WP_Post') && (
                has_shortcode($post->post_content, 'strava_dashboard') ||
                has_shortcode($post->post_content, 'strava_progress_chart') ||
                has_shortcode($post->post_content, 'strava_activity_chart') ||
                has_shortcode($post->post_content, 'strava_connect_button')
            )
        ) {

            // Enqueue Chart.js
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js',
                array(),
                '4.4.0',
                true
            );

            // Enqueue public scripts
            wp_enqueue_script(
                $this->plugin_name,
                STRAVA_COACHING_PLUGIN_URL . 'public/js/public-script.js',
                array('jquery', 'chartjs'),
                $this->version,
                true
            );

            // Localize script
            wp_localize_script($this->plugin_name, 'stravaPublic', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('strava_public_nonce'),
                'disconnectNonce' => wp_create_nonce('disconnect_strava'),
                'syncNonce' => wp_create_nonce('sync_strava_activities'),
                'currentUserId' => get_current_user_id(),
                'isLoggedIn' => is_user_logged_in()
            ));
        }
    }

    /**
     * Main Dashboard Shortcode
     * Usage: [strava_dashboard type="coach|mentee"]
     */
    public function strava_dashboard_shortcode($atts)
    {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return '<div class="strava-notice">Please log in to view your dashboard.</div>';
        }

        $atts = shortcode_atts(array(
            'type' => 'auto' // auto, coach, or mentee
        ), $atts);

        $user_id = get_current_user_id();

        // Determine dashboard type
        if ($atts['type'] === 'auto') {
            if (is_coach($user_id)) {
                $dashboard_type = 'coach';
            } elseif (is_mentee($user_id)) {
                $dashboard_type = 'mentee';
            } else {
                return '<div class="strava-notice">Please select a role (Coach or Mentee) in your <a href="' . admin_url('admin.php?page=strava-coaching') . '">Strava Coaching settings</a>.</div>';
            }
        } else {
            $dashboard_type = $atts['type'];
        }

        // Verify user has appropriate role
        if ($dashboard_type === 'coach' && !is_coach($user_id)) {
            return '<div class="strava-notice">You need to be a coach to view this dashboard.</div>';
        }

        if ($dashboard_type === 'mentee' && !is_mentee($user_id)) {
            return '<div class="strava-notice">You need to be a mentee to view this dashboard.</div>';
        }

        // Start output buffering
        ob_start();

        // Check for success message from OAuth redirect
        if (isset($_GET['strava_connected']) && $_GET['strava_connected'] === '1') {
            $synced = isset($_GET['synced']) ? intval($_GET['synced']) : 0;
            ?>
            <div class="strava-notice strava-success">
                <strong>🎉 Successfully connected to Strava!</strong>
                <?php if ($synced > 0): ?>
                    <br>Synced <?php echo $synced; ?> activities from the last 30 days.
                <?php endif; ?>
            </div>
            <script>
                // Remove the success parameters from URL after displaying
                jQuery(document).ready(function () {
                    if (window.history.replaceState) {
                        const url = new URL(window.location);
                        url.searchParams.delete('strava_connected');
                        url.searchParams.delete('synced');
                        window.history.replaceState({}, document.title, url.toString());
                    }
                });
            </script>
            <?php
        }

        if ($dashboard_type === 'coach') {
            $this->render_coach_dashboard($user_id);
        } else {
            $this->render_mentee_dashboard($user_id);
        }

        return ob_get_clean();
    }

    /**
     * Progress Chart Shortcode
     * Usage: [strava_progress_chart user_id="123" type="distance|pace|heartrate" days="30"]
     */
    public function strava_progress_chart_shortcode($atts)
    {
        if (!is_user_logged_in()) {
            return '<div class="strava-notice">Please log in to view progress charts.</div>';
        }

        $atts = shortcode_atts(array(
            'user_id' => get_current_user_id(),
            'type' => 'distance',
            'days' => '30',
            'height' => '400'
        ), $atts);

        $current_user_id = get_current_user_id();
        $target_user_id = intval($atts['user_id']);

        // Permission check
        if (!$this->can_view_user_data($current_user_id, $target_user_id)) {
            return '<div class="strava-notice">You do not have permission to view this data.</div>';
        }

        // Generate unique chart ID
        $chart_id = 'strava_chart_' . uniqid();

        ob_start();
        ?>
        <div class="strava-progress-chart-container" data-user-id="<?php echo esc_attr($target_user_id); ?>">
            <canvas id="<?php echo esc_attr($chart_id); ?>" class="strava-progress-chart"
                data-type="<?php echo esc_attr($atts['type']); ?>" data-days="<?php echo esc_attr($atts['days']); ?>"
                style="max-height: <?php echo esc_attr($atts['height']); ?>px;"></canvas>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Activity Chart Shortcode
     * Usage: [strava_activity_chart mentee_id="123" week="current"]
     */
    public function strava_activity_chart_shortcode($atts)
    {
        if (!is_user_logged_in()) {
            return '<div class="strava-notice">Please log in to view activity charts.</div>';
        }

        $atts = shortcode_atts(array(
            'mentee_id' => get_current_user_id(),
            'week' => 'current',
            'show_plan' => 'yes',
            'height' => '400'
        ), $atts);

        $current_user_id = get_current_user_id();
        $mentee_id = intval($atts['mentee_id']);

        // Permission check
        if (!$this->can_view_user_data($current_user_id, $mentee_id)) {
            return '<div class="strava-notice">You do not have permission to view this data.</div>';
        }

        // Calculate week start date
        if ($atts['week'] === 'current') {
            $week_start = date('Y-m-d', strtotime('monday this week'));
        } else {
            $week_start = $atts['week'];
        }

        $chart_id = 'strava_activity_' . uniqid();

        ob_start();
        ?>
        <div class="strava-activity-chart-container">
            <canvas id="<?php echo esc_attr($chart_id); ?>" class="strava-activity-chart"
                data-mentee-id="<?php echo esc_attr($mentee_id); ?>" data-week-start="<?php echo esc_attr($week_start); ?>"
                data-show-plan="<?php echo esc_attr($atts['show_plan']); ?>"
                style="max-height: <?php echo esc_attr($atts['height']); ?>px;"></canvas>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Training Plan Display Shortcode
     * Usage: [strava_training_plan mentee_id="123" week="current"]
     */
    public function training_plan_shortcode($atts)
    {
        if (!is_user_logged_in()) {
            return '<div class="strava-notice">Please log in to view training plans.</div>';
        }

        $atts = shortcode_atts(array(
            'mentee_id' => get_current_user_id(),
            'week' => 'current',
            'show_completed' => 'yes'
        ), $atts);

        $current_user_id = get_current_user_id();
        $mentee_id = intval($atts['mentee_id']);

        // Permission check
        if (!$this->can_view_user_data($current_user_id, $mentee_id)) {
            return '<div class="strava-notice">You do not have permission to view this training plan.</div>';
        }

        ob_start();
        $this->render_training_plan($mentee_id, $atts['week'], $atts['show_completed'] === 'yes');
        return ob_get_clean();
    }

    /**
     * Strava Connect Button Shortcode
     * Usage: [strava_connect_button text="Connect to Strava" show_controls="yes"]
     */
    public function strava_connect_shortcode($atts)
    {
        if (!is_user_logged_in()) {
            return '<div class="strava-notice">Please log in to connect to Strava.</div>';
        }

        $atts = shortcode_atts(array(
            'text' => 'Connect to Strava',
            'disconnect_text' => 'Disconnect from Strava',
            'sync_text' => 'Sync Activities',
            'class' => 'strava-connect-btn',
            'show_controls' => 'yes',
            'redirect_to_same_page' => 'yes'
        ), $atts);

        $user_id = get_current_user_id();

        if (!class_exists('Strava_Coaching_API')) {
            return '<div class="strava-notice">Strava API not configured.</div>';
        }

        if (is_strava_connected($user_id)) {
            if ($atts['show_controls'] === 'yes') {
                // Show disconnect and sync buttons
                ob_start();
                ?>
                <div class="strava-connection-controls">
                    <div class="strava-connected-status">
                        <span class="status-icon">✅</span>
                        <span class="status-text">Connected to Strava</span>
                    </div>
                    <div class="strava-control-buttons">
                        <button class="strava-sync-btn" onclick="stravaPublicSync()">
                            <span class="btn-icon">🔄</span>
                            <?php echo esc_html($atts['sync_text']); ?>
                        </button>
                        <button class="strava-disconnect-btn" onclick="stravaPublicDisconnect()">
                            <span class="btn-icon">🔌</span>
                            <?php echo esc_html($atts['disconnect_text']); ?>
                        </button>
                    </div>
                </div>
                <script>
                    function stravaPublicSync() {
                        if (typeof stravaPublic === 'undefined') {
                            alert('Please wait for the page to fully load.');
                            return;
                        }

                        // Show loading
                        jQuery('.strava-sync-btn').prop('disabled', true).html('<span class="btn-icon">⏳</span> Syncing...');

                        jQuery.post(stravaPublic.ajaxurl, {
                            action: 'sync_strava_activities',
                            nonce: stravaPublic.syncNonce
                        }, function (response) {
                            if (response.success) {
                                jQuery('.strava-sync-btn').html('<span class="btn-icon">✅</span> ' + response.data.message);
                                setTimeout(function () {
                                    location.reload();
                                }, 2000);
                            } else {
                                jQuery('.strava-sync-btn').prop('disabled', false).html('<span class="btn-icon">❌</span> Sync Failed');
                                alert(response.data.message || 'Failed to sync activities');
                            }
                        }).fail(function () {
                            jQuery('.strava-sync-btn').prop('disabled', false).html('<span class="btn-icon">❌</span> Network Error');
                        });
                    }

                    function stravaPublicDisconnect() {
                        if (typeof stravaPublic === 'undefined') {
                            alert('Please wait for the page to fully load.');
                            return;
                        }

                        if (confirm('Are you sure you want to disconnect from Strava? This will remove all synced data.')) {
                            jQuery('.strava-disconnect-btn').prop('disabled', true).html('<span class="btn-icon">⏳</span> Disconnecting...');

                            jQuery.post(stravaPublic.ajaxurl, {
                                action: 'disconnect_strava',
                                user_id: <?php echo $user_id; ?>,
                                nonce: stravaPublic.disconnectNonce
                            }, function (response) {
                                if (response.success) {
                                    jQuery('.strava-disconnect-btn').html('<span class="btn-icon">✅</span> Disconnected');
                                    setTimeout(function () {
                                        location.reload();
                                    }, 1500);
                                } else {
                                    jQuery('.strava-disconnect-btn').prop('disabled', false).html('<span class="btn-icon">❌</span> Failed');
                                    alert('Failed to disconnect. Please try again.');
                                }
                            }).fail(function () {
                                jQuery('.strava-disconnect-btn').prop('disabled', false).html('<span class="btn-icon">❌</span> Network Error');
                            });
                        }
                    }
                </script>
                <?php
                return ob_get_clean();
            } else {
                return '<div class="strava-connected">✅ Already connected to Strava</div>';
            }
        }

        // Not connected - show connect button
        $strava_api = new Strava_Coaching_API();

        // If redirect to same page is enabled, store current URL
        if ($atts['redirect_to_same_page'] === 'yes') {
            // Store current page URL in transient
            $current_url = home_url(add_query_arg(array(), $GLOBALS['wp']->request));
            set_transient('strava_redirect_url_' . $user_id, $current_url, 3600); // Store for 1 hour
        }

        $auth_url = $strava_api->get_auth_url($user_id);

        return sprintf(
            '<a href="%s" class="%s">%s</a>',
            esc_url($auth_url),
            esc_attr($atts['class']),
            esc_html($atts['text'])
        );
    }

    /**
     * Render Coach Dashboard
     */
    private function render_coach_dashboard($user_id)
    {
        $mentees = get_coach_mentees($user_id);
        ?>
        <div class="strava-public-dashboard strava-coach-dashboard">
            <h2>🏆 Coach Dashboard</h2>

            <!-- Stats Grid -->
            <div class="strava-stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($mentees); ?></div>
                    <div class="stat-label">Active Mentees</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $this->count_active_plans($user_id); ?></div>
                    <div class="stat-label">Active Plans</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $this->count_weekly_activities_all_mentees($user_id); ?></div>
                    <div class="stat-label">Weekly Activities</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $this->count_unscored_activities($user_id); ?></div>
                    <div class="stat-label">Pending Reviews</div>
                </div>
            </div>

            <!-- Mentees Section -->
            <div class="strava-mentees-section">
                <h3>Your Mentees</h3>
                <?php if (empty($mentees)): ?>
                    <p class="no-data">No mentees assigned yet. Add mentees from your <a
                            href="<?php echo admin_url('admin.php?page=strava-coaching'); ?>">admin dashboard</a>.</p>
                <?php else: ?>
                    <div class="mentees-grid">
                        <?php foreach ($mentees as $mentee): ?>
                            <div class="mentee-card">
                                <div class="mentee-header">
                                    <?php echo get_avatar($mentee->ID, 50); ?>
                                    <div class="mentee-info">
                                        <h4><?php echo esc_html($mentee->display_name); ?></h4>
                                        <p><?php echo $this->get_last_activity_date($mentee->ID); ?></p>
                                    </div>
                                </div>
                                <div class="mentee-stats">
                                    <div class="stat-row">
                                        <span>This Week:</span>
                                        <strong><?php echo $this->count_weekly_activities($mentee->ID); ?> activities</strong>
                                    </div>
                                    <div class="stat-row">
                                        <span>Distance:</span>
                                        <strong><?php echo round($this->get_weekly_distance($mentee->ID), 1); ?> km</strong>
                                    </div>
                                    <div class="stat-row">
                                        <span>Avg Score:</span>
                                        <strong><?php echo $this->get_avg_weekly_score($mentee->ID); ?>/10</strong>
                                    </div>
                                </div>
                                <div class="mentee-chart-container">
                                    <canvas class="mentee-mini-chart" data-mentee-id="<?php echo $mentee->ID; ?>"></canvas>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Charts Section -->
            <div class="strava-charts-section">
                <h3>Performance Overview</h3>
                <div class="chart-controls">
                    <select id="public-mentee-selector" class="mentee-selector">
                        <option value="">All Mentees</option>
                        <?php foreach ($mentees as $mentee): ?>
                            <option value="<?php echo $mentee->ID; ?>">
                                <?php echo esc_html($mentee->display_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="public-date-range" class="date-range-selector">
                        <option value="7">Last 7 Days</option>
                        <option value="30" selected>Last 30 Days</option>
                        <option value="90">Last 90 Days</option>
                    </select>
                </div>
                <canvas id="public-coach-chart" class="strava-chart"></canvas>
            </div>
        </div>
        <?php
    }

    /**
     * Render Mentee Dashboard
     */
    private function render_mentee_dashboard($user_id)
    {
        $coach = get_mentee_coach($user_id);
        $current_plan = $this->get_current_training_plan($user_id);
        $recent_activities = $this->get_recent_activities($user_id, 5);
        ?>
        <div class="strava-public-dashboard strava-mentee-dashboard">
            <h2>🎯 My Training Dashboard</h2>

            <!-- Connection Status -->
            <?php if (!is_strava_connected($user_id)): ?>
                <div class="strava-notice strava-warning">
                    <p>⚠️ Your Strava account is not connected. <a
                            href="<?php echo admin_url('admin.php?page=strava-coaching'); ?>">Connect now</a> to start tracking your
                        activities.</p>
                </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="strava-stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $this->count_weekly_activities($user_id); ?></div>
                    <div class="stat-label">This Week</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo round($this->get_weekly_distance($user_id), 1); ?> km</div>
                    <div class="stat-label">Distance</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $this->get_avg_weekly_score($user_id); ?>/10</div>
                    <div class="stat-label">Avg Score</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo round($this->calculate_weekly_progress($user_id)); ?>%</div>
                    <div class="stat-label">Plan Progress</div>
                </div>
            </div>

            <div class="strava-dashboard-grid">
                <!-- Coach Info -->
                <div class="dashboard-section">
                    <h3>Your Coach</h3>
                    <?php if ($coach): ?>
                        <div class="coach-info">
                            <?php echo get_avatar($coach->ID, 60); ?>
                            <div class="coach-details">
                                <h4><?php echo esc_html($coach->display_name); ?></h4>
                                <p><?php echo esc_html($coach->user_email); ?></p>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="no-data">No coach assigned yet.</p>
                    <?php endif; ?>
                </div>

                <!-- Current Plan -->
                <div class="dashboard-section">
                    <h3>Current Training Plan</h3>
                    <?php if ($current_plan): ?>
                        <div class="current-plan-info">
                            <h4><?php echo esc_html($current_plan->plan_name); ?></h4>
                            <p class="plan-dates">
                                <?php echo date('M j', strtotime($current_plan->week_start)); ?> -
                                <?php echo date('M j', strtotime($current_plan->week_end)); ?>
                            </p>
                            <?php $this->render_week_plan_summary($current_plan); ?>
                        </div>
                    <?php else: ?>
                        <p class="no-data">No active training plan this week.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Progress Chart -->
            <div class="strava-progress-section">
                <h3>My Progress</h3>
                <canvas id="public-mentee-progress-chart" class="strava-chart"></canvas>
            </div>

            <!-- Recent Activities -->
            <div class="strava-activities-section">
                <h3>Recent Activities</h3>
                <?php if (empty($recent_activities)): ?>
                    <p class="no-data">No activities recorded yet. Make sure your Strava account is connected and synced.</p>
                <?php else: ?>
                    <div class="activities-list">
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <?php echo get_activity_icon($activity->activity_type); ?>
                                </div>
                                <div class="activity-details">
                                    <h4><?php echo esc_html($activity->name); ?></h4>
                                    <p class="activity-date"><?php echo date('M j, Y', strtotime($activity->start_date)); ?></p>
                                    <div class="activity-stats">
                                        <?php if ($activity->distance): ?>
                                            <span>📏 <?php echo format_distance($activity->distance); ?> km</span>
                                        <?php endif; ?>
                                        <?php if ($activity->moving_time): ?>
                                            <span>⏱️ <?php echo format_duration($activity->moving_time); ?></span>
                                        <?php endif; ?>
                                        <?php if ($activity->average_heartrate): ?>
                                            <span>❤️ <?php echo round($activity->average_heartrate); ?> bpm</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="activity-score">
                                    <?php
                                    $score = $this->get_activity_score($activity->id);
                                    if ($score):
                                        ?>
                                        <div class="score-badge score-<?php echo $this->get_score_class($score->overall_score); ?>">
                                            <?php echo $score->overall_score; ?>/10
                                        </div>
                                        <?php if ($score->comments): ?>
                                            <p class="coach-feedback"><?php echo esc_html($score->comments); ?></p>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="score-pending">Pending review</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render training plan view
     */
    private function render_training_plan($mentee_id, $week, $show_completed)
    {
        global $wpdb;

        // Get training plan
        if ($week === 'current') {
            $week_start = date('Y-m-d', strtotime('monday this week'));
        } else {
            $week_start = $week;
        }

        $plan = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}strava_training_plans 
             WHERE mentee_id = %d AND week_start = %s AND status = 'active'",
            $mentee_id,
            $week_start
        ));

        if (!$plan) {
            echo '<p class="no-data">No training plan found for this week.</p>';
            return;
        }

        $plan_data = json_decode($plan->plan_data, true);
        $workouts = isset($plan_data['workouts']) ? $plan_data['workouts'] : array();

        // Get completed activities for the week
        $week_end = date('Y-m-d', strtotime($week_start . ' +6 days'));
        $activities = $this->get_week_activities($mentee_id, $week_start, $week_end);

        ?>
        <div class="strava-training-plan">
            <h3><?php echo esc_html($plan->plan_name); ?></h3>
            <p class="plan-week"><?php echo date('M j', strtotime($week_start)); ?> -
                <?php echo date('M j', strtotime($week_end)); ?></p>

            <?php if (isset($plan_data['notes']) && $plan_data['notes']): ?>
                <div class="plan-notes">
                    <h4>Coach Notes:</h4>
                    <p><?php echo nl2br(esc_html($plan_data['notes'])); ?></p>
                </div>
            <?php endif; ?>

            <div class="week-schedule">
                <?php
                $days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
                $day_names = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');

                for ($i = 0; $i < 7; $i++):
                    $day = $days[$i];
                    $day_name = $day_names[$i];
                    $workout = isset($workouts[$day]) ? $workouts[$day] : null;
                    $date = date('Y-m-d', strtotime($week_start . ' +' . $i . ' days'));
                    $completed = $this->get_day_activities($activities, $date);
                    ?>
                    <div class="day-schedule <?php echo $completed ? 'completed' : ''; ?>">
                        <h4><?php echo $day_name; ?> <span class="date"><?php echo date('M j', strtotime($date)); ?></span></h4>

                        <?php if ($workout && !empty($workout['type'])): ?>
                            <div class="planned-workout">
                                <div class="workout-type">
                                    <?php echo get_activity_icon($workout['type']); ?>
                                    <?php echo ucfirst($workout['type']); ?>
                                </div>

                                <?php if (!empty($workout['distance'])): ?>
                                    <p>📏 <?php echo $workout['distance']; ?> km</p>
                                <?php endif; ?>

                                <?php if (!empty($workout['pace'])): ?>
                                    <p>⏱️ Target pace: <?php echo $workout['pace']; ?></p>
                                <?php endif; ?>

                                <?php if (!empty($workout['duration'])): ?>
                                    <p>⏲️ <?php echo $workout['duration']; ?> minutes</p>
                                <?php endif; ?>

                                <?php if (!empty($workout['notes'])): ?>
                                    <p class="workout-notes"><?php echo esc_html($workout['notes']); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="rest-day">😴 Rest Day</div>
                        <?php endif; ?>

                        <?php if ($show_completed && $completed): ?>
                            <div class="completed-activities">
                                <h5>✅ Completed:</h5>
                                <?php foreach ($completed as $activity): ?>
                                    <div class="completed-activity">
                                        <?php echo get_activity_icon($activity->activity_type); ?>
                                        <?php echo esc_html($activity->name); ?>
                                        (<?php echo format_distance($activity->distance); ?> km)
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render week plan summary
     */
    private function render_week_plan_summary($plan)
    {
        $plan_data = json_decode($plan->plan_data, true);
        $workouts = isset($plan_data['workouts']) ? $plan_data['workouts'] : array();

        $total_distance = 0;
        $workout_count = 0;

        foreach ($workouts as $workout) {
            if (!empty($workout['type'])) {
                $workout_count++;
                if (!empty($workout['distance'])) {
                    $total_distance += floatval($workout['distance']);
                }
            }
        }

        ?>
        <div class="plan-summary">
            <span>📅 <?php echo $workout_count; ?> workouts</span>
            <span>📏 <?php echo $total_distance; ?> km total</span>
        </div>
        <?php
    }

    /**
     * AJAX handler for public chart data
     */
    public function ajax_get_public_chart_data()
    {
        check_ajax_referer('strava_public_nonce', 'nonce');

        $chart_type = sanitize_text_field($_POST['chart_type']);
        $user_id = intval($_POST['user_id']);
        $date_range = intval($_POST['date_range']);

        $current_user_id = get_current_user_id();

        // Permission check
        if (!$this->can_view_user_data($current_user_id, $user_id)) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        switch ($chart_type) {
            case 'progress':
                $data = $this->get_progress_chart_data($user_id, $date_range);
                break;
            case 'weekly':
                $data = $this->get_weekly_summary_data($user_id);
                break;
            case 'comparison':
                $data = $this->get_comparison_chart_data($user_id);
                break;
            default:
                wp_send_json_error(array('message' => 'Invalid chart type'));
        }

        wp_send_json_success($data);
    }

    /**
     * Helper Methods
     */
    private function can_view_user_data($current_user_id, $target_user_id)
    {
        // Users can view their own data
        if ($current_user_id === $target_user_id) {
            return true;
        }

        // Coaches can view their mentees' data
        if (is_coach($current_user_id)) {
            $mentees = get_coach_mentees($current_user_id);
            foreach ($mentees as $mentee) {
                if ($mentee->ID == $target_user_id) {
                    return true;
                }
            }
        }

        return false;
    }

    private function get_recent_activities($user_id, $limit = 5)
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}strava_activities 
             WHERE user_id = %d 
             ORDER BY start_date DESC 
             LIMIT %d",
            $user_id,
            $limit
        ));
    }

    private function get_current_training_plan($mentee_id)
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}strava_training_plans 
             WHERE mentee_id = %d AND status = 'active' 
             AND week_start <= CURDATE() AND week_end >= CURDATE()
             ORDER BY created_at DESC LIMIT 1",
            $mentee_id
        ));
    }

    private function get_week_activities($user_id, $start_date, $end_date)
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}strava_activities 
             WHERE user_id = %d 
             AND DATE(start_date) >= %s 
             AND DATE(start_date) <= %s
             ORDER BY start_date ASC",
            $user_id,
            $start_date,
            $end_date
        ));
    }

    private function get_day_activities($activities, $date)
    {
        $day_activities = array();

        foreach ($activities as $activity) {
            if (date('Y-m-d', strtotime($activity->start_date)) === $date) {
                $day_activities[] = $activity;
            }
        }

        return $day_activities;
    }

    private function get_activity_score($activity_id)
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}activity_scores 
             WHERE activity_id = %d",
            $activity_id
        ));
    }

    private function get_score_class($score)
    {
        if ($score >= 8)
            return 'excellent';
        if ($score >= 6)
            return 'good';
        if ($score >= 4)
            return 'average';
        return 'poor';
    }

    private function count_weekly_activities($user_id)
    {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}strava_activities 
             WHERE user_id = %d 
             AND start_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            $user_id
        ));
    }

    private function get_weekly_distance($user_id)
    {
        global $wpdb;

        $distance = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(distance) FROM {$wpdb->prefix}strava_activities 
             WHERE user_id = %d 
             AND start_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            $user_id
        ));

        return $distance ? $distance / 1000 : 0;
    }

    private function get_avg_weekly_score($user_id)
    {
        global $wpdb;

        $avg_score = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(acs.overall_score) 
             FROM {$wpdb->prefix}activity_scores acs
             INNER JOIN {$wpdb->prefix}strava_activities sa ON acs.activity_id = sa.id
             WHERE sa.user_id = %d 
             AND sa.start_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            $user_id
        ));

        return $avg_score ? round($avg_score, 1) : '--';
    }

    private function calculate_weekly_progress($user_id)
    {
        $weekly_activities = $this->count_weekly_activities($user_id);
        $target_activities = 3; // Default target

        return min(100, ($weekly_activities / $target_activities) * 100);
    }

    private function get_last_activity_date($user_id)
    {
        global $wpdb;

        $last_date = $wpdb->get_var($wpdb->prepare(
            "SELECT start_date FROM {$wpdb->prefix}strava_activities 
             WHERE user_id = %d 
             ORDER BY start_date DESC 
             LIMIT 1",
            $user_id
        ));

        return $last_date ? 'Last activity: ' . human_time_diff(strtotime($last_date)) . ' ago' : 'No activities yet';
    }

    private function count_active_plans($coach_id)
    {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}strava_training_plans 
             WHERE coach_id = %d AND status = 'active'",
            $coach_id
        ));
    }

    private function count_weekly_activities_all_mentees($coach_id)
    {
        $mentees = get_coach_mentees($coach_id);
        $total = 0;

        foreach ($mentees as $mentee) {
            $total += $this->count_weekly_activities($mentee->ID);
        }

        return $total;
    }

    private function count_unscored_activities($coach_id)
    {
        global $wpdb;

        $query = "
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}strava_activities sa
            INNER JOIN {$wpdb->prefix}coach_mentee_relationships cmr ON sa.user_id = cmr.mentee_id
            LEFT JOIN {$wpdb->prefix}activity_scores acs ON sa.id = acs.activity_id
            WHERE cmr.coach_id = %d 
            AND cmr.status = 'active'
            AND acs.id IS NULL
            AND sa.start_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ";

        return $wpdb->get_var($wpdb->prepare($query, $coach_id));
    }

    private function get_progress_chart_data($user_id, $days)
    {
        global $wpdb;

        $start_date = date('Y-m-d', strtotime("-{$days} days"));

        $query = "SELECT 
                    DATE(start_date) as activity_date,
                    AVG(distance) as avg_distance,
                    AVG(average_speed) as avg_speed,
                    COUNT(*) as activity_count
                  FROM {$wpdb->prefix}strava_activities
                  WHERE user_id = %d
                  AND start_date >= %s
                  GROUP BY DATE(start_date)
                  ORDER BY activity_date ASC";

        $results = $wpdb->get_results($wpdb->prepare($query, $user_id, $start_date));

        $labels = array();
        $distance = array();
        $pace = array();

        foreach ($results as $row) {
            $labels[] = date('M j', strtotime($row->activity_date));
            $distance[] = round($row->avg_distance / 1000, 2);

            if ($row->avg_speed > 0) {
                $pace_seconds = 1000 / $row->avg_speed;
                $pace[] = round($pace_seconds / 60, 2);
            } else {
                $pace[] = 0;
            }
        }

        return array(
            'labels' => $labels,
            'distance' => $distance,
            'pace' => $pace
        );
    }

    private function get_weekly_summary_data($user_id)
    {
        global $wpdb;

        $query = "SELECT 
                    WEEK(start_date) as week_num,
                    YEAR(start_date) as year,
                    SUM(distance) as total_distance,
                    SUM(moving_time) as total_time,
                    COUNT(*) as activity_count
                  FROM {$wpdb->prefix}strava_activities
                  WHERE user_id = %d
                  AND start_date >= DATE_SUB(NOW(), INTERVAL 8 WEEK)
                  GROUP BY YEAR(start_date), WEEK(start_date)
                  ORDER BY year DESC, week_num DESC
                  LIMIT 8";

        $results = $wpdb->get_results($wpdb->prepare($query, $user_id));
        $results = array_reverse($results);

        $labels = array();
        $distance = array();
        $activities = array();

        foreach ($results as $row) {
            $labels[] = 'Week ' . $row->week_num;
            $distance[] = round($row->total_distance / 1000, 2);
            $activities[] = $row->activity_count;
        }

        return array(
            'labels' => $labels,
            'distance' => $distance,
            'activities' => $activities
        );
    }

    private function get_comparison_chart_data($user_id)
    {
        // Get current week's plan vs actual
        global $wpdb;

        $week_start = date('Y-m-d', strtotime('monday this week'));
        $week_end = date('Y-m-d', strtotime('sunday this week'));

        // Get plan data
        $plan = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}strava_training_plans
             WHERE mentee_id = %d
             AND week_start = %s
             AND status = 'active'",
            $user_id,
            $week_start
        ));

        if (!$plan) {
            return array(
                'labels' => array(),
                'planned' => array(),
                'actual' => array()
            );
        }

        // Similar logic to admin chart data
        // ... (implementation similar to admin class)

        return array(
            'labels' => array('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'),
            'planned' => array(5, 0, 8, 0, 10, 15, 0),
            'actual' => array(4.5, 0, 7.8, 0, 9.2, 14.5, 0)
        );
    }

    /**
     * AJAX handler for public disconnect
     */
    public function ajax_public_disconnect()
    {
        check_ajax_referer('disconnect_strava', 'nonce');

        $user_id = intval($_POST['user_id']);
        if ($user_id !== get_current_user_id()) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        if (class_exists('Strava_Coaching_API')) {
            $strava_api = new Strava_Coaching_API();
            $result = $strava_api->disconnect_user($user_id);
            wp_send_json_success(array('disconnected' => $result));
        } else {
            wp_send_json_error(array('message' => 'Strava API class not found'));
        }
    }

    /**
     * AJAX handler for public sync
     */
    public function ajax_public_sync()
    {
        // Accept the nonce that's sent from frontend
        if (!check_ajax_referer('sync_strava_activities', 'nonce', false)) {
            // Try without nonce check for logged-in users
            if (!is_user_logged_in()) {
                wp_send_json_error(array('message' => 'Not authorized'));
                return;
            }
        }

        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        if (class_exists('Strava_Coaching_API')) {
            $strava_api = new Strava_Coaching_API();
            $synced_count = $strava_api->sync_user_activities($user_id, 30);

            if ($synced_count !== false) {
                wp_send_json_success(array(
                    'message' => "Synced {$synced_count} new activities",
                    'count' => $synced_count
                ));
            } else {
                wp_send_json_error(array('message' => 'Failed to sync activities'));
            }
        } else {
            wp_send_json_error(array('message' => 'Strava API class not found'));
        }
    }

    // Placeholder methods for hooks
    public function add_rewrite_endpoints()
    { /* TODO */
    }
    public function handle_custom_endpoints()
    { /* TODO */
    }
    public function sync_all_user_activities()
    { /* TODO */
    }
}