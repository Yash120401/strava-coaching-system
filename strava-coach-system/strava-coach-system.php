<?php
/**
 * Plugin Name: Strava Coach Mentee System
 * Description: Complete coaching system with Strava integration
 * Version: 1.0.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('STRAVA_COACH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('STRAVA_COACH_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('STRAVA_COACH_VERSION', '1.0.0');

class StravaCoachSystem {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Load required files
        $this->load_dependencies();
        
        // Initialize components (only if not already initialized)
        if (!isset($GLOBALS['strava_coach_components'])) {
            $GLOBALS['strava_coach_components'] = array();
            
            $GLOBALS['strava_coach_components']['user_roles'] = new StravaCoach_UserRoles();
            $GLOBALS['strava_coach_components']['dashboards'] = new StravaCoach_Dashboards();
            $GLOBALS['strava_coach_components']['database'] = new StravaCoach_Database();
            $GLOBALS['strava_coach_components']['ajax'] = new StravaCoach_Ajax();
            $GLOBALS['strava_coach_components']['settings'] = new StravaCoach_Settings();
            $GLOBALS['strava_coach_components']['strava_api'] = new StravaCoach_StravaAPI();
        }
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    private function load_dependencies() {
        $files_to_load = array(
            'class-user-roles.php' => 'StravaCoach_UserRoles',
            'class-database.php' => 'StravaCoach_Database',
            'class-dashboards.php' => 'StravaCoach_Dashboards',
            'class-ajax.php' => 'StravaCoach_Ajax',
            'class-strava-api.php' => 'StravaCoach_StravaAPI',
            'class-settings.php' => 'StravaCoach_Settings',
            'functions.php' => null // functions.php doesn't have a class
        );
        
        foreach ($files_to_load as $file => $class_name) {
            $file_path = STRAVA_COACH_PLUGIN_PATH . 'includes/' . $file;
            
            if (!file_exists($file_path)) {
                error_log('Strava Coach: Missing file - ' . $file_path);
                continue;
            }
            
            // Load functions.php always, or load class files only if class doesn't exist
            if ($class_name === null || !class_exists($class_name)) {
                require_once $file_path;
            }
        }
    }
    
    public function enqueue_scripts() {
        if (is_page('coach-dashboard') || is_page('mentee-dashboard')) {
            wp_enqueue_script('chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js', array(), '3.9.1', true);
            wp_enqueue_script('strava-coach-js', STRAVA_COACH_PLUGIN_URL . 'assets/js/dashboard.js', array('jquery', 'chart-js'), STRAVA_COACH_VERSION, true);
            wp_enqueue_style('strava-coach-css', STRAVA_COACH_PLUGIN_URL . 'assets/css/dashboard.css', array(), STRAVA_COACH_VERSION);
            
            // Localize script for AJAX
            wp_localize_script('strava-coach-js', 'strava_coach_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('strava_coach_nonce'),
                'current_user_id' => get_current_user_id(),
                'user_role' => $this->get_user_role()
            ));
        }
    }
    
    public function enqueue_admin_scripts($hook) {
        wp_enqueue_style('strava-coach-admin-css', STRAVA_COACH_PLUGIN_URL . 'assets/css/admin.css', array(), STRAVA_COACH_VERSION);
    }
    
    private function get_user_role() {
        $user = wp_get_current_user();
        if (in_array('strava_coach', $user->roles)) {
            return 'coach';
        } elseif (in_array('strava_mentee', $user->roles)) {
            return 'mentee';
        }
        return 'none';
    }
    
    public function activate() {
        // Debug file loading
        $db_file = STRAVA_COACH_PLUGIN_PATH . 'includes/class-database.php';
        $roles_file = STRAVA_COACH_PLUGIN_PATH . 'includes/class-user-roles.php';
        $functions_file = STRAVA_COACH_PLUGIN_PATH . 'includes/functions.php';
        
        // Check if files exist
        if (!file_exists($db_file)) {
            wp_die('Missing file: ' . $db_file);
        }
        if (!file_exists($roles_file)) {
            wp_die('Missing file: ' . $roles_file);
        }
        if (!file_exists($functions_file)) {
            wp_die('Missing file: ' . $functions_file);
        }
        
        // Load files and check for errors
        require_once $functions_file;
        
        ob_start();
        require_once $db_file;
        $db_output = ob_get_clean();
        if (!empty($db_output)) {
            wp_die('Error in database file: ' . $db_output);
        }
        
        ob_start();
        require_once $roles_file;
        $roles_output = ob_get_clean();
        if (!empty($roles_output)) {
            wp_die('Error in roles file: ' . $roles_output);
        }
        
        // Check if classes exist after loading
        if (!class_exists('StravaCoach_Database')) {
            wp_die('StravaCoach_Database class not found after loading file: ' . $db_file);
        }
        if (!class_exists('StravaCoach_UserRoles')) {
            wp_die('StravaCoach_UserRoles class not found after loading file: ' . $roles_file);
        }
        
        // Create database tables
        $database = new StravaCoach_Database();
        $database->create_tables();
        
        // Create dashboard pages
        $this->create_dashboard_pages();
        
        // Add user roles
        $roles = new StravaCoach_UserRoles();
        $roles->add_custom_roles();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Clean up components
        if (isset($GLOBALS['strava_coach_components'])) {
            unset($GLOBALS['strava_coach_components']);
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    private function create_dashboard_pages() {
        // Coach Dashboard Page
        $coach_page = array(
            'post_title' => 'Coach Dashboard',
            'post_name' => 'coach-dashboard',
            'post_content' => '[coach_dashboard]',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_author' => 1
        );
        
        if (!get_page_by_path('coach-dashboard')) {
            wp_insert_post($coach_page);
        }
        
        // Mentee Dashboard Page
        $mentee_page = array(
            'post_title' => 'Mentee Dashboard',
            'post_name' => 'mentee-dashboard',
            'post_content' => '[mentee_dashboard]',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_author' => 1
        );
        
        if (!get_page_by_path('mentee-dashboard')) {
            wp_insert_post($mentee_page);
        }
    }
}

// Initialize the plugin
new StravaCoachSystem();