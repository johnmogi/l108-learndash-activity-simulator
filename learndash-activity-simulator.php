<?php
/**
 * Plugin Name: LearnDash Activity Simulator
 * Description: Simulate LearnDash activity for testing purposes
 * Version: 1.0.0
 * Author: Cascade AI
 * Author URI: https://example.com
 * Text Domain: learndash-activity-simulator
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class LearnDash_Activity_Simulator {
    private $version = '1.0.0';
    private $plugin_path;
    private $plugin_url;
    private $simulation_data = array();
    private $students = array();
    private $courses = array();
    private $lessons = array();
    private $quizzes = array();

    public function __construct() {
        $this->plugin_path = plugin_dir_path(__FILE__);
        $this->plugin_url = plugin_dir_url(__FILE__);

        // Initialize on admin init
        add_action('admin_init', array($this, 'init'));
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register AJAX handlers
        add_action('wp_ajax_las_generate_activity', array($this, 'ajax_generate_activity'));
        add_action('wp_ajax_las_export_activity', array($this, 'ajax_export_activity'));
        add_action('wp_ajax_las_cleanup_activity', array($this, 'ajax_cleanup_activity'));
    }

    public function init() {
        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Load text domain
        load_plugin_textdomain('learndash-activity-simulator', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function enqueue_admin_assets($hook) {
        if ('toplevel_page_learndash-activity-simulator' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'learndash-activity-simulator-admin',
            $this->plugin_url . 'assets/css/admin.css',
            array(),
            $this->version
        );

        wp_enqueue_script(
            'learndash-activity-simulator-admin',
            $this->plugin_url . 'assets/js/admin.js',
            array('jquery'),
            $this->version,
            true
        );

        wp_localize_script('learndash-activity-simulator-admin', 'lasAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('las_nonce'),
            'i18n' => array(
                'select_students' => __('Please select at least one student.', 'learndash-activity-simulator'),
                'select_courses' => __('Please select at least one course.', 'learndash-activity-simulator'),
                'confirm_cleanup' => __('Are you sure you want to delete all simulated activity data? This cannot be undone.', 'learndash-activity-simulator'),
                'generating' => __('Generating activity data...', 'learndash-activity-simulator'),
                'exporting' => __('Exporting data...', 'learndash-activity-simulator'),
                'cleaning' => __('Cleaning up...', 'learndash-activity-simulator'),
                'success' => __('Operation completed successfully!', 'learndash-activity-simulator'),
                'error' => __('An error occurred. Please try again.', 'learndash-activity-simulator'),
                'download_export' => __('Download Export File', 'learndash-activity-simulator'),
                'file' => __('File', 'learndash-activity-simulator'),
                'path' => __('Path', 'learndash-activity-simulator')
            )
        ));
    }

    public function add_admin_menu() {
        add_menu_page(
            __('LearnDash Activity Simulator', 'learndash-activity-simulator'),
            __('LD Activity Simulator', 'learndash-activity-simulator'),
            'manage_options',
            'learndash-activity-simulator',
            array($this, 'render_admin_page'),
            'dashicons-chart-line',
            90
        );
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get available courses, lessons, quizzes, and students
        $this->get_available_content();
        
        include $this->plugin_path . 'templates/admin-page.php';
    }

    private function get_available_content() {
        // Get published courses
        $this->courses = get_posts(array(
            'post_type' => 'sfwd-courses',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));

        // Get published lessons
        $this->lessons = get_posts(array(
            'post_type' => 'sfwd-lessons',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));

        // Get published quizzes
        $this->quizzes = get_posts(array(
            'post_type' => 'sfwd-quiz',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));

        // Get all users except administrators (like the teacher dashboard does)
        $all_users = get_users(array(
            'fields' => array('ID', 'display_name', 'user_email'),
            'number' => -1, // Get all users
            'orderby' => 'display_name',
            'order' => 'ASC'
        ));
        
        // Filter out administrators and keep only actual students
        $this->students = array_filter($all_users, function($user) {
            $user_obj = get_userdata($user->ID);
            if (!$user_obj) return false;
            
            // Exclude administrators and other admin-level roles
            $admin_roles = array('administrator', 'editor', 'author', 'contributor');
            $user_roles = $user_obj->roles;
            
            // Check if user has any admin roles
            foreach ($admin_roles as $admin_role) {
                if (in_array($admin_role, $user_roles)) {
                    return false;
                }
            }
            
            // Include users who have student-like roles or no specific roles (likely students)
            return true;
        });
    }

    public function ajax_generate_activity() {
        check_ajax_referer('las_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'learndash-activity-simulator'));
        }

        $data = isset($_POST['data']) ? $_POST['data'] : array();
        $result = $this->generate_activity($data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }

    public function ajax_export_activity() {
        check_ajax_referer('las_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'learndash-activity-simulator'));
        }

        $result = $this->export_activity();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }

    public function ajax_cleanup_activity() {
        check_ajax_referer('las_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'learndash-activity-simulator'));
        }

        $data = isset($_POST['data']) ? $_POST['data'] : array();
        $result = $this->cleanup_activity($data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }

    /**
     * Generate activity data
     */
    private function generate_activity($data) {
        require_once $this->plugin_path . 'includes/class-activity-generator.php';
        $generator = new LAS_Activity_Generator();
        return $generator->generate($data);
    }

    /**
     * Export activity data to a file
     */
    private function export_activity() {
        require_once $this->plugin_path . 'includes/class-activity-generator.php';
        $generator = new LAS_Activity_Generator();
        return $generator->export();
    }

    /**
     * Clean up all simulated activity data
     */
    private function cleanup_activity($data) {
        require_once $this->plugin_path . 'includes/class-activity-generator.php';
        $generator = new LAS_Activity_Generator();
        return $generator->cleanup();
    }
}

// Initialize the plugin
function learndash_activity_simulator_init() {
    // Check if LearnDash is active
    if (!class_exists('SFWD_LMS')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php _e('LearnDash LMS is required for the LearnDash Activity Simulator to work. Please install and activate LearnDash LMS.', 'learndash-activity-simulator'); ?></p>
            </div>
            <?php
        });
        return;
    }
    
    new LearnDash_Activity_Simulator();
}
add_action('plugins_loaded', 'learndash_activity_simulator_init');

// Activation hook
register_activation_hook(__FILE__, function() {
    // Create exports directory if it doesn't exist
    $upload_dir = wp_upload_dir();
    $exports_dir = $upload_dir['basedir'] . '/learndash-activity-exports';
    
    if (!file_exists($exports_dir)) {
        wp_mkdir_p($exports_dir);
        // Add index.php to prevent directory listing
        file_put_contents($exports_dir . '/index.php', '<?php // Silence is golden');
    }
    
    // Add version option
    add_option('learndash_activity_simulator_version', '1.0.0');
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Clean up any scheduled events or transients if needed
    delete_transient('las_simulation_data');
});
