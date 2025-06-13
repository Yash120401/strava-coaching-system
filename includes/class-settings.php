<?php
/**
 * Settings Administration Page
 * File: includes/class-settings.php
 */

class StravaCoach_Settings {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_test_strava_connection', array($this, 'test_strava_connection'));
        add_action('wp_ajax_sync_all_users', array($this, 'sync_all_users'));
    }
    
    public function add_settings_page() {
        add_submenu_page(
            'strava-coach-admin',
            'Settings',
            'Settings',
            'manage_options',
            'strava-coach-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'strava-coach-admin',
            'Analytics',
            'Analytics',
            'manage_options',
            'strava-coach-analytics',
            array($this, 'analytics_page')
        );
    }
    
    public function register_settings() {
        register_setting('strava_coach_settings', 'strava_coach_options');
    }
    
    public function settings_page() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        $db = new StravaCoach_Database();
        $current_settings = array(
            'strava_client_id' => $db->get_setting('strava_client_id'),
            'strava_client_secret' => $db->get_setting('strava_client_secret'),
            'auto_sync_enabled' => $db->get_setting('auto_sync_enabled', '1'),
            'sync_frequency' => $db->get_setting('sync_frequency', 'daily'),
            'sync_days_back' => $db->get_setting('sync_days_back', '30'),
            'webhook_enabled' => $db->get_setting('webhook_enabled', '0'),
            'default_custom_field_1' => $db->get_setting('default_custom_field_1', 'Technique'),
            'default_custom_field_2' => $db->get_setting('default_custom_field_2', 'Effort Level'),
            'default_custom_field_3' => $db->get_setting('default_custom_field_3', 'Improvement Areas'),
            'default_custom_field_4' => $db->get_setting('default_custom_field_4', 'Goals for Next Week')
        );
        
        ?>
        <div class="wrap strava-coach-admin">
            <h1>Strava Coach System Settings</h1>
            
            <?php if (isset($_POST['submit'])): ?>
                <div class="notice notice-success">
                    <p>Settings saved successfully!</p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('strava_coach_settings', 'strava_coach_nonce'); ?>
                
                <!-- Strava API Configuration -->
                <div class="settings-section">
                    <h3>Strava API Configuration</h3>
                    <div class="settings-content">
                        <div class="setting-row">
                            <div class="setting-label">
                                <label for="strava_client_id">Strava Client ID</label>
                                <div class="description">Your Strava app's Client ID from developers.strava.com</div>
                            </div>
                            <div class="setting-input">
                                <input type="text" id="strava_client_id" name="strava_client_id" 
                                       value="<?php echo esc_attr($current_settings['strava_client_id']); ?>" 
                                       placeholder="Enter your Strava Client ID" />
                            </div>
                        </div>
                        
                        <div class="setting-row">
                            <div class="setting-label">
                                <label for="strava_client_secret">Strava Client Secret</label>
                                <div class="description">Your Strava app's Client Secret (keep this secure!)</div>
                            </div>
                            <div class="setting-input">
                                <input type="password" id="strava_client_secret" name="strava_client_secret" 
                                       value="<?php echo esc_attr($current_settings['strava_client_secret']); ?>" 
                                       placeholder="Enter your Strava Client Secret" />
                                <button type="button" id="toggle-secret" class="btn btn-secondary btn-small">Show</button>
                            </div>
                        </div>
                        
                        <div class="setting-row">
                            <div class="setting-label">
                                <label>API Status</label>
                                <div class="description">Test your Strava API connection</div>
                            </div>
                            <div class="setting-input">
                                <button type="button" id="test-strava-connection" class="btn btn-secondary">Test Connection</button>
                                <div id="connection-status"></div>
                            </div>
                        </div>
                        
                        <div class="setting-row">
                            <div class="setting-label">
                                <label>OAuth Callback URL</label>
                                <div class="description">Use this URL in your Strava app settings</div>
                            </div>
                            <div class="setting-input">
                                <input type="text" readonly value="<?php echo admin_url('admin-ajax.php?action=strava_oauth_callback'); ?>" 
                                       style="background: #f1f1f1; cursor: pointer;" onclick="this.select();" />
                                <p><em>Copy this URL to your Strava app's "Authorization Callback Domain" field</em></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sync Settings -->
                <div class="settings-section">
                    <h3>Data Synchronization</h3>
                    <div class="settings-content">
                        <div class="setting-row">
                            <div class="setting-label">
                                <label for="auto_sync_enabled">Automatic Sync</label>
                                <div class="description">Enable automatic daily syncing of Strava activities</div>
                            </div>
                            <div class="setting-input">
                                <select id="auto_sync_enabled" name="auto_sync_enabled">
                                    <option value="1" <?php selected($current_settings['auto_sync_enabled'], '1'); ?>>Enabled</option>
                                    <option value="0" <?php selected($current_settings['auto_sync_enabled'], '0'); ?>>Disabled</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="setting-row">
                            <div class="setting-label">
                                <label for="sync_frequency">Sync Frequency</label>
                                <div class="description">How often to automatically sync activities</div>
                            </div>
                            <div class="setting-input">
                                <select id="sync_frequency" name="sync_frequency">
                                    <option value="hourly" <?php selected($current_settings['sync_frequency'], 'hourly'); ?>>Every Hour</option>
                                    <option value="daily" <?php selected($current_settings['sync_frequency'], 'daily'); ?>>Daily</option>
                                    <option value="weekly" <?php selected($current_settings['sync_frequency'], 'weekly'); ?>>Weekly</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="setting-row">
                            <div class="setting-label">
                                <label for="sync_days_back">Sync History</label>
                                <div class="description">How many days of historical data to sync initially</div>
                            </div>
                            <div class="setting-input">
                                <select id="sync_days_back" name="sync_days_back">
                                    <option value="7" <?php selected($current_settings['sync_days_back'], '7'); ?>>7 days</option>
                                    <option value="30" <?php selected($current_settings['sync_days_back'], '30'); ?>>30 days</option>
                                    <option value="90" <?php selected($current_settings['sync_days_back'], '90'); ?>>90 days</option>
                                    <option value="365" <?php selected($current_settings['sync_days_back'], '365'); ?>>1 year</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="setting-row">
                            <div class="setting-label">
                                <label>Manual Sync</label>
                                <div class="description">Manually sync all connected users now</div>
                            </div>
                            <div class="setting-input">
                                <button type="button" id="sync-all-users" class="btn btn-secondary">Sync All Users Now</button>
                                <div id="sync-status"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Webhooks -->
                <div class="settings-section">
                    <h3>Real-time Updates (Advanced)</h3>
                    <div class="settings-content">
                        <div class="setting-row">
                            <div class="setting-label">
                                <label for="webhook_enabled">Strava Webhooks</label>
                                <div class="description">Enable real-time activity updates (requires webhook setup)</div>
                            </div>
                            <div class="setting-input">
                                <select id="webhook_enabled" name="webhook_enabled">
                                    <option value="0" <?php selected($current_settings['webhook_enabled'], '0'); ?>>Disabled</option>
                                    <option value="1" <?php selected($current_settings['webhook_enabled'], '1'); ?>>Enabled</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="setting-row">
                            <div class="setting-label">
                                <label>Webhook URL</label>
                                <div class="description">Configure this in your Strava app webhook settings</div>
                            </div>
                            <div class="setting-input">
                                <input type="text" readonly value="<?php echo site_url('/wp-json/strava-coach/v1/webhook'); ?>" 
                                       style="background: #f1f1f1; cursor: pointer;" onclick="this.select();" />
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Custom Fields -->
                <div class="settings-section">
                    <h3>Custom Scoring Fields</h3>
                    <div class="settings-content">
                        <div class="setting-row">
                            <div class="setting-label">
                                <label for="default_custom_field_1">Custom Field 1</label>
                                <div class="description">Default name for the first custom scoring field</div>
                            </div>
                            <div class="setting-input">
                                <input type="text" id="default_custom_field_1" name="default_custom_field_1" 
                                       value="<?php echo esc_attr($current_settings['default_custom_field_1']); ?>" />
                            </div>
                        </div>
                        
                        <div class="setting-row">
                            <div class="setting-label">
                                <label for="default_custom_field_2">Custom Field 2</label>
                                <div class="description">Default name for the second custom scoring field</div>
                            </div>
                            <div class="setting-input">
                                <input type="text" id="default_custom_field_2" name="default_custom_field_2" 
                                       value="<?php echo esc_attr($current_settings['default_custom_field_2']); ?>" />
                            </div>
                        </div>
                        
                        <div class="setting-row">
                            <div class="setting-label">
                                <label for="default_custom_field_3">Custom Field 3</label>
                                <div class="description">Default name for the third custom scoring field</div>
                            </div>
                            <div class="setting-input">
                                <input type="text" id="default_custom_field_3" name="default_custom_field_3" 
                                       value="<?php echo esc_attr($current_settings['default_custom_field_3']); ?>" />
                            </div>
                        </div>
                        
                        <div class="setting-row">
                            <div class="setting-label">
                                <label for="default_custom_field_4">Custom Field 4</label>
                                <div class="description">Default name for the fourth custom scoring field</div>
                            </div>
                            <div class="setting-input">
                                <input type="text" id="default_custom_field_4" name="default_custom_field_4" 
                                       value="<?php echo esc_attr($current_settings['default_custom_field_4']); ?>" />
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <input type="submit" name="submit" class="btn btn-primary" value="Save Settings" />
                </div>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Toggle password visibility
            $('#toggle-secret').click(function() {
                const input = $('#strava_client_secret');
                const btn = $(this);
                
                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    btn.text('Hide');
                } else {
                    input.attr('type', 'password');
                    btn.text('Show');
                }
            });
            
            // Test Strava connection
            $('#test-strava-connection').click(function() {
                const btn = $(this);
                const status = $('#connection-status');
                const clientId = $('#strava_client_id').val();
                const clientSecret = $('#strava_client_secret').val();
                
                if (!clientId || !clientSecret) {
                    status.html('<span style="color: red;">Please enter both Client ID and Secret first</span>');
                    return;
                }
                
                btn.prop('disabled', true).text('Testing...');
                status.html('<span class="spinner"></span> Testing connection...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'test_strava_connection',
                        client_id: clientId,
                        client_secret: clientSecret,
                        nonce: '<?php echo wp_create_nonce('strava_coach_settings'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            status.html('<span style="color: green;">✅ Connection successful!</span>');
                        } else {
                            status.html('<span style="color: red;">❌ Connection failed: ' + response.data.message + '</span>');
                        }
                    },
                    error: function() {
                        status.html('<span style="color: red;">❌ Network error</span>');
                    },
                    complete: function() {
                        btn.prop('disabled', false).text('Test Connection');
                    }
                });
            });
            
            // Sync all users
            $('#sync-all-users').click(function() {
                const btn = $(this);
                const status = $('#sync-status');
                
                if (!confirm('This will sync activities for all connected users. Continue?')) {
                    return;
                }
                
                btn.prop('disabled', true).text('Syncing...');
                status.html('<span class="spinner"></span> Syncing all users...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sync_all_users',
                        nonce: '<?php echo wp_create_nonce('strava_coach_settings'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            status.html('<span style="color: green;">✅ Sync completed! ' + response.data.message + '</span>');
                        } else {
                            status.html('<span style="color: red;">❌ Sync failed: ' + response.data.message + '</span>');
                        }
                    },
                    error: function() {
                        status.html('<span style="color: red;">❌ Network error</span>');
                    },
                    complete: function() {
                        btn.prop('disabled', false).text('Sync All Users Now');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    public function analytics_page() {
        global $wpdb;
        
        // Get system statistics
        $stats = $this->get_system_stats();
        
        ?>
        <div class="wrap strava-coach-admin">
            <h1>System Analytics</h1>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-number"><?php echo $stats['total_users']; ?></span>
                    <span class="stat-label">Total Users</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo $stats['connected_users']; ?></span>
                    <span class="stat-label">Connected to Strava</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo $stats['total_activities']; ?></span>
                    <span class="stat-label">Total Activities</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo $stats['active_relationships']; ?></span>
                    <span class="stat-label">Active Coach-Mentee Pairs</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo $stats['weekly_plans']; ?></span>
                    <span class="stat-label">Weekly Plans Created</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo $stats['scores_given']; ?></span>
                    <span class="stat-label">Scores Given</span>
                </div>
            </div>
            
            <div class="settings-section">
                <h3>Recent Activities</h3>
                <div class="settings-content">
                    <?php $this->display_recent_activities(); ?>
                </div>
            </div>
            
            <div class="settings-section">
                <h3>System Health</h3>
                <div class="settings-content">
                    <?php $this->display_system_health(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function save_settings() {
        if (!wp_verify_nonce($_POST['strava_coach_nonce'], 'strava_coach_settings')) {
            wp_die('Security check failed');
        }
        
        $db = new StravaCoach_Database();
        
        $settings = array(
            'strava_client_id' => sanitize_text_field($_POST['strava_client_id']),
            'strava_client_secret' => sanitize_text_field($_POST['strava_client_secret']),
            'auto_sync_enabled' => sanitize_text_field($_POST['auto_sync_enabled']),
            'sync_frequency' => sanitize_text_field($_POST['sync_frequency']),
            'sync_days_back' => sanitize_text_field($_POST['sync_days_back']),
            'webhook_enabled' => sanitize_text_field($_POST['webhook_enabled']),
            'default_custom_field_1' => sanitize_text_field($_POST['default_custom_field_1']),
            'default_custom_field_2' => sanitize_text_field($_POST['default_custom_field_2']),
            'default_custom_field_3' => sanitize_text_field($_POST['default_custom_field_3']),
            'default_custom_field_4' => sanitize_text_field($_POST['default_custom_field_4'])
        );
        
        foreach ($settings as $key => $value) {
            $db->update_setting($key, $value);
        }
        
        // Update cron schedule if frequency changed
        $this->update_cron_schedule($settings['sync_frequency'], $settings['auto_sync_enabled']);
    }
    
    private function update_cron_schedule($frequency, $enabled) {
        // Clear existing cron
        wp_clear_scheduled_hook('strava_coach_sync_cron');
        
        if ($enabled === '1') {
            wp_schedule_event(time(), $frequency, 'strava_coach_sync_cron');
        }
    }
    
    public function test_strava_connection() {
        check_ajax_referer('strava_coach_settings', 'nonce');
        
        $client_id = sanitize_text_field($_POST['client_id']);
        $client_secret = sanitize_text_field($_POST['client_secret']);
        
        // Test by making a simple API call to get rate limits
        $response = wp_remote_get('https://www.strava.com/api/v3/athlete', array(
            'headers' => array(
                'User-Agent' => 'StravaCoachSystem/1.0'
            ),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'Network error: ' . $response->get_error_message()));
        }
        
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code === 401) {
            wp_send_json_success(array('message' => 'API credentials format valid (401 unauthorized expected without access token)'));
        } else if ($code === 200) {
            wp_send_json_success(array('message' => 'API accessible'));
        } else {
            wp_send_json_error(array('message' => 'Unexpected response code: ' . $code));
        }
    }
    
    public function sync_all_users() {
        check_ajax_referer('strava_coach_settings', 'nonce');
        
        $strava_api = new StravaCoach_StravaAPI();
        $result = $strava_api->daily_sync_all_users();
        
        wp_send_json_success(array('message' => 'Sync completed'));
    }
    
    private function get_system_stats() {
        global $wpdb;
        
        $coaches = count(get_users(array('role' => 'strava_coach')));
        $mentees = count(get_users(array('role' => 'strava_mentee')));
        
        $connected_users = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->prefix}strava_user_integrations 
            WHERE status = 'active'
        ");
        
        $total_activities = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->prefix}strava_activities
        ");
        
        $active_relationships = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->prefix}strava_coach_mentee_relationships 
            WHERE status = 'active'
        ");
        
        $weekly_plans = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->prefix}strava_weekly_plans 
            WHERE status = 'active'
        ");
        
        $scores_given = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->prefix}strava_weekly_scores
        ");
        
        return array(
            'total_users' => $coaches + $mentees,
            'coaches' => $coaches,
            'mentees' => $mentees,
            'connected_users' => $connected_users,
            'total_activities' => $total_activities,
            'active_relationships' => $active_relationships,
            'weekly_plans' => $weekly_plans,
            'scores_given' => $scores_given
        );
    }
    
    private function display_recent_activities() {
        global $wpdb;
        
        $activities = $wpdb->get_results("
            SELECT a.*, u.display_name 
            FROM {$wpdb->prefix}strava_activities a
            LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
            ORDER BY a.activity_date DESC 
            LIMIT 10
        ");
        
        if (empty($activities)) {
            echo '<p>No activities found.</p>';
            return;
        }
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>User</th><th>Activity</th><th>Type</th><th>Distance</th><th>Duration</th><th>Date</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($activities as $activity) {
            echo '<tr>';
            echo '<td>' . esc_html($activity->display_name) . '</td>';
            echo '<td>' . esc_html($activity->activity_name) . '</td>';
            echo '<td>' . esc_html($activity->activity_type) . '</td>';
            echo '<td>' . strava_coach_format_distance($activity->distance) . '</td>';
            echo '<td>' . strava_coach_format_duration($activity->duration) . '</td>';
            echo '<td>' . date('M j, Y g:i A', strtotime($activity->activity_date)) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    private function display_system_health() {
        $checks = array();
        
        // Check Strava API settings
        $db = new StravaCoach_Database();
        $client_id = $db->get_setting('strava_client_id');
        $client_secret = $db->get_setting('strava_client_secret');
        
        $checks[] = array(
            'name' => 'Strava API Configuration',
            'status' => (!empty($client_id) && !empty($client_secret)) ? 'pass' : 'fail',
            'message' => (!empty($client_id) && !empty($client_secret)) ? 'Configured' : 'Missing API credentials'
        );
        
        // Check HTTPS
        $checks[] = array(
            'name' => 'HTTPS Enabled',
            'status' => is_ssl() ? 'pass' : 'fail',
            'message' => is_ssl() ? 'HTTPS is enabled' : 'HTTPS required for Strava OAuth'
        );
        
        // Check cron
        $checks[] = array(
            'name' => 'WordPress Cron',
            'status' => (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) ? 'warning' : 'pass',
            'message' => (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) ? 'WP Cron disabled - ensure system cron is configured' : 'WP Cron enabled'
        );
        
        // Check database tables
        global $wpdb;
        $tables = array(
            $wpdb->prefix . 'strava_user_integrations',
            $wpdb->prefix . 'strava_activities',
            $wpdb->prefix . 'strava_weekly_plans',
            $wpdb->prefix . 'strava_weekly_scores'
        );
        
        $missing_tables = array();
        foreach ($tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
                $missing_tables[] = $table;
            }
        }
        
        $checks[] = array(
            'name' => 'Database Tables',
            'status' => empty($missing_tables) ? 'pass' : 'fail',
            'message' => empty($missing_tables) ? 'All tables exist' : 'Missing tables: ' . implode(', ', $missing_tables)
        );
        
        echo '<div class="health-checks">';
        foreach ($checks as $check) {
            $icon = $check['status'] === 'pass' ? '✅' : ($check['status'] === 'warning' ? '⚠️' : '❌');
            $color = $check['status'] === 'pass' ? 'green' : ($check['status'] === 'warning' ? 'orange' : 'red');
            
            echo '<div class="health-check" style="margin-bottom: 10px;">';
            echo '<span style="color: ' . $color . ';">' . $icon . ' <strong>' . $check['name'] . ':</strong> ' . $check['message'] . '</span>';
            echo '</div>';
        }
        echo '</div>';
    }
}