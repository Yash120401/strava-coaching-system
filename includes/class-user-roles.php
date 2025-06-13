<?php
/**
 * User Roles Management
 * File: includes/class-user-roles.php
 */

class StravaCoach_UserRoles {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_assign_mentee_to_coach', array($this, 'assign_mentee_to_coach'));
        add_action('wp_ajax_remove_mentee_from_coach', array($this, 'remove_mentee_from_coach'));
    }
    
    public function add_custom_roles() {
        // Add Coach Role
        add_role('strava_coach', 'Strava Coach', array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
            'manage_mentees' => true,
            'view_coach_dashboard' => true,
            'create_weekly_plans' => true,
            'score_activities' => true
        ));
        
        // Add Mentee Role
        add_role('strava_mentee', 'Strava Mentee', array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
            'view_mentee_dashboard' => true,
            'connect_strava' => true,
            'view_weekly_plans' => true
        ));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Strava Coach System',
            'Strava Coach',
            'manage_options',
            'strava-coach-admin',
            array($this, 'admin_page'),
            'dashicons-chart-line',
            30
        );
        
        add_submenu_page(
            'strava-coach-admin',
            'Manage Users',
            'Manage Users',
            'manage_options',
            'strava-coach-users',
            array($this, 'manage_users_page')
        );
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Strava Coach System</h1>
            <div class="card">
                <h2>System Overview</h2>
                <p><strong>Coaches:</strong> <?php echo $this->count_users_by_role('strava_coach'); ?></p>
                <p><strong>Mentees:</strong> <?php echo $this->count_users_by_role('strava_mentee'); ?></p>
                <p><strong>Active Relationships:</strong> <?php echo $this->count_active_relationships(); ?></p>
            </div>
            
            <div class="card">
                <h2>Quick Actions</h2>
                <a href="<?php echo admin_url('admin.php?page=strava-coach-users'); ?>" class="button button-primary">Manage Users</a>
                <a href="<?php echo get_permalink(get_page_by_path('coach-dashboard')); ?>" class="button">Coach Dashboard</a>
                <a href="<?php echo get_permalink(get_page_by_path('mentee-dashboard')); ?>" class="button">Mentee Dashboard</a>
            </div>
        </div>
        <?php
    }
    
    public function manage_users_page() {
        if (isset($_POST['create_coach'])) {
            $this->create_user($_POST, 'strava_coach');
        }
        if (isset($_POST['create_mentee'])) {
            $this->create_user($_POST, 'strava_mentee');
        }
        
        ?>
        <div class="wrap">
            <h1>Manage Users</h1>
            
            <!-- Create New Users -->
            <div class="card">
                <h2>Create New Coach</h2>
                <form method="post">
                    <table class="form-table">
                        <tr>
                            <th><label for="coach_username">Username</label></th>
                            <td><input type="text" name="username" id="coach_username" required /></td>
                        </tr>
                        <tr>
                            <th><label for="coach_email">Email</label></th>
                            <td><input type="email" name="email" id="coach_email" required /></td>
                        </tr>
                        <tr>
                            <th><label for="coach_first_name">First Name</label></th>
                            <td><input type="text" name="first_name" id="coach_first_name" /></td>
                        </tr>
                        <tr>
                            <th><label for="coach_last_name">Last Name</label></th>
                            <td><input type="text" name="last_name" id="coach_last_name" /></td>
                        </tr>
                    </table>
                    <input type="submit" name="create_coach" class="button button-primary" value="Create Coach" />
                </form>
            </div>
            
            <div class="card">
                <h2>Create New Mentee</h2>
                <form method="post">
                    <table class="form-table">
                        <tr>
                            <th><label for="mentee_username">Username</label></th>
                            <td><input type="text" name="username" id="mentee_username" required /></td>
                        </tr>
                        <tr>
                            <th><label for="mentee_email">Email</label></th>
                            <td><input type="email" name="email" id="mentee_email" required /></td>
                        </tr>
                        <tr>
                            <th><label for="mentee_first_name">First Name</label></th>
                            <td><input type="text" name="first_name" id="mentee_first_name" /></td>
                        </tr>
                        <tr>
                            <th><label for="mentee_last_name">Last Name</label></th>
                            <td><input type="text" name="last_name" id="mentee_last_name" /></td>
                        </tr>
                    </table>
                    <input type="submit" name="create_mentee" class="button button-primary" value="Create Mentee" />
                </form>
            </div>
            
            <!-- Existing Users -->
            <div class="card">
                <h2>Coaches</h2>
                <?php $this->display_users_table('strava_coach'); ?>
            </div>
            
            <div class="card">
                <h2>Mentees</h2>
                <?php $this->display_users_table('strava_mentee'); ?>
            </div>
            
            <!-- Coach-Mentee Relationships -->
            <div class="card">
                <h2>Coach-Mentee Relationships</h2>
                <?php $this->display_relationships_table(); ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.assign-mentee').click(function() {
                var coachId = $(this).data('coach-id');
                var menteeId = $(this).data('mentee-id');
                
                $.post(ajaxurl, {
                    action: 'assign_mentee_to_coach',
                    coach_id: coachId,
                    mentee_id: menteeId,
                    nonce: '<?php echo wp_create_nonce('strava_coach_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                });
            });
            
            $('.remove-mentee').click(function() {
                var relationshipId = $(this).data('relationship-id');
                
                if (confirm('Are you sure you want to remove this relationship?')) {
                    $.post(ajaxurl, {
                        action: 'remove_mentee_from_coach',
                        relationship_id: relationshipId,
                        nonce: '<?php echo wp_create_nonce('strava_coach_nonce'); ?>'
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }
    
    private function create_user($data, $role) {
        $user_data = array(
            'user_login' => sanitize_text_field($data['username']),
            'user_email' => sanitize_email($data['email']),
            'first_name' => sanitize_text_field($data['first_name']),
            'last_name' => sanitize_text_field($data['last_name']),
            'user_pass' => wp_generate_password(),
            'role' => $role
        );
        
        $user_id = wp_insert_user($user_data);
        
        if (!is_wp_error($user_id)) {
            // Send email with login details
            wp_new_user_notification($user_id, null, 'both');
            echo '<div class="notice notice-success"><p>User created successfully! Login details sent via email.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Error creating user: ' . $user_id->get_error_message() . '</p></div>';
        }
    }
    
    private function display_users_table($role) {
        $users = get_users(array('role' => $role));
        
        if (empty($users)) {
            echo '<p>No ' . ($role == 'strava_coach' ? 'coaches' : 'mentees') . ' found.</p>';
            return;
        }
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Registration Date</th><th>Actions</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($users as $user) {
            echo '<tr>';
            echo '<td>' . $user->ID . '</td>';
            echo '<td>' . $user->first_name . ' ' . $user->last_name . '</td>';
            echo '<td>' . $user->user_email . '</td>';
            echo '<td>' . date('Y-m-d', strtotime($user->user_registered)) . '</td>';
            echo '<td>';
            if ($role == 'strava_mentee') {
                $coach_id = $this->get_mentee_coach($user->ID);
                if (!$coach_id) {
                    echo '<select class="mentee-coach-select" data-mentee-id="' . $user->ID . '">';
                    echo '<option value="">Assign to Coach</option>';
                    $coaches = get_users(array('role' => 'strava_coach'));
                    foreach ($coaches as $coach) {
                        echo '<option value="' . $coach->ID . '">' . $coach->first_name . ' ' . $coach->last_name . '</option>';
                    }
                    echo '</select>';
                    echo '<button class="button assign-mentee" data-mentee-id="' . $user->ID . '">Assign</button>';
                } else {
                    $coach = get_user_by('ID', $coach_id);
                    echo 'Assigned to: ' . $coach->first_name . ' ' . $coach->last_name;
                }
            }
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    private function display_relationships_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'strava_coach_mentee_relationships';
        $relationships = $wpdb->get_results("
            SELECT r.id, r.coach_id, r.mentee_id, r.created_at,
                   c.display_name as coach_name, 
                   m.display_name as mentee_name
            FROM $table_name r
            LEFT JOIN {$wpdb->users} c ON r.coach_id = c.ID
            LEFT JOIN {$wpdb->users} m ON r.mentee_id = m.ID
            WHERE r.status = 'active'
            ORDER BY r.created_at DESC
        ");
        
        if (empty($relationships)) {
            echo '<p>No active relationships found.</p>';
            return;
        }
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Coach</th><th>Mentee</th><th>Start Date</th><th>Actions</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($relationships as $rel) {
            echo '<tr>';
            echo '<td>' . $rel->coach_name . '</td>';
            echo '<td>' . $rel->mentee_name . '</td>';
            echo '<td>' . date('Y-m-d', strtotime($rel->created_at)) . '</td>';
            echo '<td><button class="button remove-mentee" data-relationship-id="' . $rel->id . '">Remove</button></td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    private function count_users_by_role($role) {
        $users = get_users(array('role' => $role));
        return count($users);
    }
    
    private function count_active_relationships() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'strava_coach_mentee_relationships';
        return $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'active'");
    }
    
    private function get_mentee_coach($mentee_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'strava_coach_mentee_relationships';
        return $wpdb->get_var($wpdb->prepare(
            "SELECT coach_id FROM $table_name WHERE mentee_id = %d AND status = 'active'",
            $mentee_id
        ));
    }
    
    public function assign_mentee_to_coach() {
        check_ajax_referer('strava_coach_nonce', 'nonce');
        
        $coach_id = intval($_POST['coach_id']);
        $mentee_id = intval($_POST['mentee_id']);
        
        // Check if mentee is already assigned to another coach
        if ($this->get_mentee_coach($mentee_id)) {
            wp_send_json_error(array('message' => 'Mentee is already assigned to another coach.'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'strava_coach_mentee_relationships';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'coach_id' => $coach_id,
                'mentee_id' => $mentee_id,
                'status' => 'active',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s')
        );
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => 'Failed to create relationship.'));
        }
    }
    
    public function remove_mentee_from_coach() {
        check_ajax_referer('strava_coach_nonce', 'nonce');
        
        $relationship_id = intval($_POST['relationship_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'strava_coach_mentee_relationships';
        
        $result = $wpdb->update(
            $table_name,
            array('status' => 'inactive'),
            array('id' => $relationship_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => 'Failed to remove relationship.'));
        }
    }
}