<?php
/**
 * Strava API Integration Class
 * File: includes/class-strava-api.php
 */

class StravaCoach_StravaAPI {
    
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $api_base_url = 'https://www.strava.com/api/v3/';
    private $oauth_base_url = 'https://www.strava.com/oauth/';
    
    public function __construct() {
        $db = new StravaCoach_Database();
        $this->client_id = $db->get_setting('strava_client_id');
        $this->client_secret = $db->get_setting('strava_client_secret');
        $this->redirect_uri = admin_url('admin-ajax.php?action=strava_oauth_callback');
        
        // Register OAuth handlers
        add_action('wp_ajax_strava_oauth_start', array($this, 'start_oauth'));
        add_action('wp_ajax_nopriv_strava_oauth_start', array($this, 'start_oauth'));
        add_action('wp_ajax_strava_oauth_callback', array($this, 'handle_oauth_callback'));
        add_action('wp_ajax_nopriv_strava_oauth_callback', array($this, 'handle_oauth_callback'));
        
        // Add webhook endpoints
        add_action('rest_api_init', array($this, 'register_webhook_endpoints'));
        
        // Cron job for syncing
        add_action('strava_coach_sync_cron', array($this, 'daily_sync_all_users'));
        
        // Schedule initial cron if auto-sync is enabled
        $auto_sync = $db->get_setting('auto_sync_enabled', '1');
        $frequency = $db->get_setting('sync_frequency', 'daily');
        
        if ($auto_sync === '1' && !wp_next_scheduled('strava_coach_sync_cron')) {
            wp_schedule_event(time(), $frequency, 'strava_coach_sync_cron');
        }
    }
    
    /**
     * Start OAuth process
     */
    public function start_oauth() {
        if (!wp_verify_nonce($_GET['nonce'], 'strava_coach_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url());
            exit;
        }
        
        if (empty($this->client_id)) {
            wp_die('Strava API not configured. Please contact administrator.');
        }
        
        // Store user ID in session for callback
        $user_id = get_current_user_id();
        set_transient('strava_oauth_user_' . $user_id, $user_id, 600); // 10 minutes
        
        $params = array(
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'response_type' => 'code',
            'scope' => 'read,activity:read_all,profile:read_all',
            'state' => wp_create_nonce('strava_oauth_' . $user_id)
        );
        
        $auth_url = $this->oauth_base_url . 'authorize?' . http_build_query($params);
        wp_redirect($auth_url);
        exit;
    }
    
    /**
     * Handle OAuth callback
     */
    public function handle_oauth_callback() {
        $code = isset($_GET['code']) ? sanitize_text_field($_GET['code']) : '';
        $state = isset($_GET['state']) ? sanitize_text_field($_GET['state']) : '';
        $error = isset($_GET['error']) ? sanitize_text_field($_GET['error']) : '';
        
        if ($error) {
            $this->redirect_with_message('Strava authorization denied: ' . $error, 'error');
            return;
        }
        
        if (!$code) {
            $this->redirect_with_message('No authorization code received', 'error');
            return;
        }
        
        // Find user from stored transients
        $user_id = null;
        global $wpdb;
        $transients = $wpdb->get_results("
            SELECT option_name, option_value 
            FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_strava_oauth_user_%'
        ");
        
        foreach ($transients as $transient) {
            $stored_user_id = get_option($transient->option_name);
            if (wp_verify_nonce($state, 'strava_oauth_' . $stored_user_id)) {
                $user_id = $stored_user_id;
                delete_option($transient->option_name);
                break;
            }
        }
        
        if (!$user_id) {
            $this->redirect_with_message('Invalid authorization state', 'error');
            return;
        }
        
        // Exchange code for access token
        $token_data = $this->exchange_code_for_token($code);
        
        if (!$token_data) {
            $this->redirect_with_message('Failed to get access token', 'error');
            return;
        }
        
        // Save integration data
        $this->save_user_integration($user_id, $token_data);
        
        // Sync initial data
        $this->sync_user_activities($user_id);
        
        $this->redirect_with_message('Successfully connected to Strava!', 'success');
    }
    
    /**
     * Exchange authorization code for access token
     */
    private function exchange_code_for_token($code) {
        $params = array(
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'code' => $code,
            'grant_type' => 'authorization_code'
        );
        
        $response = wp_remote_post($this->oauth_base_url . 'token', array(
            'body' => $params,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));
        
        if (is_wp_error($response)) {
            strava_coach_log('Token exchange error: ' . $response->get_error_message(), 'error');
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['access_token'])) {
            strava_coach_log('Token exchange failed: ' . $body, 'error');
            return false;
        }
        
        return $data;
    }
    
    /**
     * Save user integration data
     */
    private function save_user_integration($user_id, $token_data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'strava_user_integrations';
        
        // Deactivate any existing integrations
        $wpdb->update(
            $table_name,
            array('status' => 'inactive'),
            array('user_id' => $user_id),
            array('%s'),
            array('%d')
        );
        
        // Insert new integration
        $expires_at = date('Y-m-d H:i:s', $token_data['expires_at']);
        
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'strava_user_id' => $token_data['athlete']['id'],
                'access_token' => $token_data['access_token'],
                'refresh_token' => $token_data['refresh_token'],
                'token_expires_at' => $expires_at,
                'athlete_data' => json_encode($token_data['athlete']),
                'status' => 'active'
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        strava_coach_log('User ' . $user_id . ' connected to Strava successfully');
    }
    
    /**
     * Get user's access token (refreshing if needed)
     */
    private function get_user_access_token($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'strava_user_integrations';
        
        $integration = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND status = 'active'",
            $user_id
        ));
        
        if (!$integration) {
            return false;
        }
        
        // Check if token needs refresh
        if (strtotime($integration->token_expires_at) <= time() + 300) { // Refresh 5 minutes early
            $new_token_data = $this->refresh_access_token($integration->refresh_token);
            
            if (!$new_token_data) {
                strava_coach_log('Failed to refresh token for user ' . $user_id, 'error');
                return false;
            }
            
            // Update token in database
            $wpdb->update(
                $table_name,
                array(
                    'access_token' => $new_token_data['access_token'],
                    'refresh_token' => $new_token_data['refresh_token'],
                    'token_expires_at' => date('Y-m-d H:i:s', $new_token_data['expires_at'])
                ),
                array('id' => $integration->id),
                array('%s', '%s', '%s'),
                array('%d')
            );
            
            return $new_token_data['access_token'];
        }
        
        return $integration->access_token;
    }
    
    /**
     * Refresh access token
     */
    private function refresh_access_token($refresh_token) {
        $params = array(
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'refresh_token' => $refresh_token,
            'grant_type' => 'refresh_token'
        );
        
        $response = wp_remote_post($this->oauth_base_url . 'token', array(
            'body' => $params,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return isset($data['access_token']) ? $data : false;
    }
    
    /**
     * Make API request
     */
    private function make_api_request($endpoint, $user_id, $params = array()) {
        $access_token = $this->get_user_access_token($user_id);
        
        if (!$access_token) {
            return false;
        }
        
        $url = $this->api_base_url . $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token
            )
        ));
        
        if (is_wp_error($response)) {
            strava_coach_log('API request error: ' . $response->get_error_message(), 'error');
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            strava_coach_log('API request failed with code ' . $code, 'error');
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }
    
    /**
     * Sync user activities
     */
    public function sync_user_activities($user_id, $days_back = 30) {
        try {
            $after_timestamp = strtotime("-{$days_back} days");
            
            $params = array(
                'after' => $after_timestamp,
                'per_page' => 200
            );
            
            $activities = $this->make_api_request('athlete/activities', $user_id, $params);
            
            if ($activities === false) {
                strava_coach_log("Failed to get activities from Strava API for user {$user_id}", 'error');
                throw new Exception('Failed to retrieve activities from Strava API');
            }
            
            if (!is_array($activities)) {
                strava_coach_log("Invalid response from Strava API for user {$user_id}: " . print_r($activities, true), 'error');
                throw new Exception('Invalid response from Strava API');
            }
            
            $db = new StravaCoach_Database();
            $synced_count = 0;
            $error_count = 0;
            
            foreach ($activities as $activity) {
                try {
                    if ($db->save_strava_activity($user_id, $activity)) {
                        $synced_count++;
                    } else {
                        $error_count++;
                        strava_coach_log("Failed to save activity {$activity['id']} for user {$user_id}", 'warning');
                    }
                } catch (Exception $e) {
                    $error_count++;
                    strava_coach_log("Error saving activity {$activity['id']} for user {$user_id}: " . $e->getMessage(), 'error');
                }
            }
            
            strava_coach_log("Synced {$synced_count} activities for user {$user_id} (errors: {$error_count})");
            
            // Update last sync time
            global $wpdb;
            $table_name = $wpdb->prefix . 'strava_user_integrations';
            $wpdb->update(
                $table_name,
                array('last_sync' => current_time('mysql')),
                array('user_id' => $user_id, 'status' => 'active'),
                array('%s'),
                array('%d', '%s')
            );
            
            return $synced_count;
            
        } catch (Exception $e) {
            strava_coach_log("Sync failed for user {$user_id}: " . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * Get detailed activity data
     */
    public function get_activity_details($user_id, $activity_id) {
        return $this->make_api_request("activities/{$activity_id}", $user_id);
    }
    
    /**
     * Get athlete stats
     */
    public function get_athlete_stats($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'strava_user_integrations';
        
        $integration = $wpdb->get_row($wpdb->prepare(
            "SELECT strava_user_id FROM $table_name WHERE user_id = %d AND status = 'active'",
            $user_id
        ));
        
        if (!$integration) {
            return false;
        }
        
        return $this->make_api_request("athletes/{$integration->strava_user_id}/stats", $user_id);
    }
    
    /**
     * Daily sync for all connected users
     */
    public function daily_sync_all_users() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'strava_user_integrations';
        
        $users = $wpdb->get_results(
            "SELECT user_id FROM $table_name WHERE status = 'active'"
        );
        
        foreach ($users as $user) {
            $this->sync_user_activities($user->user_id, 7); // Sync last 7 days
            sleep(1); // Rate limiting
        }
        
        strava_coach_log('Daily sync completed for ' . count($users) . ' users');
    }
    
    /**
     * Disconnect user from Strava
     */
    public function disconnect_user($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'strava_user_integrations';
        
        return $wpdb->update(
            $table_name,
            array('status' => 'inactive'),
            array('user_id' => $user_id),
            array('%s'),
            array('%d')
        );
    }
    
    /**
     * Redirect with message
     */
    private function redirect_with_message($message, $type = 'info') {
        $user = wp_get_current_user();
        
        if (in_array('strava_coach', $user->roles)) {
            $redirect_url = get_permalink(get_page_by_path('coach-dashboard'));
        } else {
            $redirect_url = get_permalink(get_page_by_path('mentee-dashboard'));
        }
        
        $redirect_url = add_query_arg(array(
            'strava_message' => urlencode($message),
            'strava_type' => $type
        ), $redirect_url);
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Register REST API endpoints for webhooks
     */
    public function register_webhook_endpoints() {
        register_rest_route('strava-coach/v1', '/webhook', array(
            'methods' => array('GET', 'POST'),
            'callback' => array($this, 'handle_webhook_request'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * Handle webhook requests (both verification and events)
     */
    public function handle_webhook_request($request) {
        $method = $request->get_method();
        
        if ($method === 'GET') {
            return $this->handle_webhook_verification($request);
        } else if ($method === 'POST') {
            return $this->handle_webhook_event($request);
        }
        
        return new WP_Error('invalid_method', 'Method not allowed', array('status' => 405));
    }
    
    /**
     * Get webhook verification (updated)
     */
    private function handle_webhook_verification($request) {
        $hub_mode = $request->get_param('hub.mode');
        $hub_challenge = $request->get_param('hub.challenge');
        $hub_verify_token = $request->get_param('hub.verify_token');
        
        if ($hub_mode !== 'subscribe') {
            return new WP_Error('invalid_mode', 'Invalid hub mode', array('status' => 400));
        }
        
        $db = new StravaCoach_Database();
        $expected_token = $db->get_setting('strava_webhook_token', 'strava_coach_webhook_' . wp_generate_password(16, false));
        
        if ($hub_verify_token !== $expected_token) {
            strava_coach_log('Webhook verification failed: invalid token', 'error');
            return new WP_Error('invalid_token', 'Invalid verify token', array('status' => 403));
        }
        
        strava_coach_log('Webhook verified successfully');
        return array('hub.challenge' => $hub_challenge);
    }
    
    /**
     * Handle webhook events (updated)
     */
    private function handle_webhook_event($request) {
        $event = $request->get_json_params();
        
        if (!$event || !isset($event['object_type'])) {
            strava_coach_log('Invalid webhook event received', 'error');
            return new WP_Error('invalid_event', 'Invalid event data', array('status' => 400));
        }
        
        strava_coach_log('Webhook event received: ' . json_encode($event));
        
        if ($event['object_type'] === 'activity' && $event['aspect_type'] === 'create') {
            $this->handle_new_activity_webhook($event);
        }
        
        return array('status' => 'EVENT_RECEIVED');
    }
    
    /**
     * Handle new activity webhook
     */
    private function handle_new_activity_webhook($event) {
        // Find user by Strava athlete ID
        global $wpdb;
        $table_name = $wpdb->prefix . 'strava_user_integrations';
        
        $integration = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id FROM $table_name WHERE strava_user_id = %d AND status = 'active'",
            $event['owner_id']
        ));
        
        if ($integration) {
            // Sync this specific activity
            $activity_data = $this->get_activity_details($integration->user_id, $event['object_id']);
            
            if ($activity_data) {
                $db = new StravaCoach_Database();
                $db->save_strava_activity($integration->user_id, $activity_data);
                
                strava_coach_log('New activity synced via webhook for user ' . $integration->user_id);
            }
        }
    }
}