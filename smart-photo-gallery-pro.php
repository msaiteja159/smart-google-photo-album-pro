<?php
/**
 * Plugin Name: HH CJ Google Photos
 * Plugin URI: https://example.com/hh-cj-google-photos
 * Description: AI-powered photo management system with Google Photos integration, Pinterest-style masonry grid, face detection, auto-tagging, and advanced search capabilities.
 * Version: 1.0.1
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: smart-photo-gallery-pro
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SPGP_VERSION', '1.0.1');
define('SPGP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SPGP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SPGP_PLUGIN_FILE', __FILE__);

/**
 * Main Plugin Class
 */
class Smart_Photo_Gallery_Pro {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        $files = array(
            'includes/helpers.php',
            'includes/class-gallery-posttype.php',
            'includes/class-gallery-frontend.php',
            'includes/class-gallery-ai.php',
            'includes/class-gallery-upload.php',
            'includes/class-gallery-search.php',
            'includes/class-gallery-admin.php',
            'includes/class-gallery-api.php',
            'includes/class-gallery-google-photos.php',
            'includes/class-plugin-updater.php',
        );
        
        $missing_files = array();
        
        foreach ($files as $file) {
            $file_path = SPGP_PLUGIN_DIR . $file;
            // Normalize path separators for cross-platform compatibility
            $file_path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $file_path);
            
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                $missing_files[] = $file;
                // Log error for debugging
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        'Smart Photo Gallery Pro: Required file not found: %s (Checked: %s)',
                        $file,
                        $file_path
                    ));
                }
            }
        }
        
        // If critical files are missing, show admin notice
        if (!empty($missing_files) && is_admin()) {
            add_action('admin_notices', function() use ($missing_files) {
                $message = sprintf(
                    '<div class="error"><p><strong>HH CJ Google Photos Error:</strong> Missing required files. Please reinstall the plugin. Missing: %s</p></div>',
                    implode(', ', $missing_files)
                );
                echo $message;
            });
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'init'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Load dependencies - this ensures all files are loaded
        $this->load_dependencies();
        
        // Verify critical class exists, try multiple paths
        if (!class_exists('SPGP_Gallery_PostType')) {
            $possible_paths = array(
                SPGP_PLUGIN_DIR . 'includes/class-gallery-posttype.php',
                SPGP_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . 'class-gallery-posttype.php',
                dirname(__FILE__) . '/includes/class-gallery-posttype.php',
                dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'class-gallery-posttype.php',
            );
            
            $loaded = false;
            foreach ($possible_paths as $posttype_file) {
                $posttype_file = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $posttype_file);
                if (file_exists($posttype_file)) {
                    require_once $posttype_file;
                    $loaded = true;
                    break;
                }
            }
            
            // If still not loaded, show error
            if (!$loaded || !class_exists('SPGP_Gallery_PostType')) {
                $posttype_file = SPGP_PLUGIN_DIR . 'includes/class-gallery-posttype.php';
                $debug_info = array(
                    'plugin_dir' => SPGP_PLUGIN_DIR,
                    'expected_file' => $posttype_file,
                    'file_exists' => file_exists($posttype_file),
                    'dir_exists' => is_dir(SPGP_PLUGIN_DIR . 'includes'),
                );
                
                if (is_dir(SPGP_PLUGIN_DIR . 'includes')) {
                    $files = array_slice(scandir(SPGP_PLUGIN_DIR . 'includes'), 2);
                    $debug_info['files_in_includes'] = implode(', ', $files);
                } else {
                    $debug_info['files_in_includes'] = 'Directory not found';
                }
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Smart Photo Gallery Pro Activation Error: ' . print_r($debug_info, true));
                }
                
                wp_die(
                    sprintf(
                        '<h1>Plugin Activation Failed</h1><p><strong>HH CJ Google Photos</strong> could not be activated due to missing files.</p><p><strong>Expected file:</strong><br><code>%s</code></p><p><strong>Plugin directory:</strong><br><code>%s</code></p><p><strong>Includes directory exists:</strong> %s</p><p><strong>Possible causes:</strong></p><ul><li>Incomplete plugin installation - ZIP file may be corrupted</li><li>Files were deleted or moved after installation</li><li>File permissions issue preventing file access</li><li>Incorrect ZIP structure - ensure ZIP contains a folder named "smart-photo-gallery-pro"</li></ul><p><strong>Solution:</strong> Please completely delete the plugin folder and reinstall from a fresh ZIP file.</p>',
                        esc_html($posttype_file),
                        esc_html(SPGP_PLUGIN_DIR),
                        is_dir(SPGP_PLUGIN_DIR . 'includes') ? 'Yes' : 'No'
                    ),
                    'Plugin Activation Error',
                    array('back_link' => true)
                );
            }
        }
        
        // Get instance and register post type
        if (class_exists('SPGP_Gallery_PostType')) {
            $post_type = SPGP_Gallery_PostType::get_instance();
            $post_type->register_post_type();
            $post_type->register_taxonomies();
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set a flag to ensure rewrite rules are flushed after activation
        update_option('spgp_flush_rewrite_rules', true);
        
        // Set default options
        $defaults = array(
            'api_provider' => 'google',
            'google_vision_api_key' => '',
            'aws_access_key' => '',
            'aws_secret_key' => '',
            'aws_region' => 'us-east-1',
            'gallery_layout' => 'masonry',
            'enable_user_uploads' => 1,
            'enable_ai_tagging' => 1,
            'enable_face_detection' => 1,
            'moderate_uploads' => 1,
            'items_per_page' => 20,
            'enable_dark_mode' => 0,
            'enable_infinite_scroll' => 1,
            'enable_favorites' => 1,
            'enable_likes' => 1,
            'enable_views' => 1,
        );
        
        add_option('spgp_settings', $defaults);
        
        // Create database tables if needed
        $this->create_tables();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Create custom database tables
     */
    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Face detection table
        $table_faces = $wpdb->prefix . 'spgp_faces';
        $sql_faces = "CREATE TABLE IF NOT EXISTS $table_faces (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            photo_id bigint(20) NOT NULL,
            face_id varchar(255) NOT NULL,
            person_name varchar(255) DEFAULT NULL,
            bounding_box text,
            confidence decimal(5,2) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY photo_id (photo_id),
            KEY face_id (face_id)
        ) $charset_collate;";
        
        // AI tags table
        $table_tags = $wpdb->prefix . 'spgp_ai_tags';
        $sql_tags = "CREATE TABLE IF NOT EXISTS $table_tags (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            photo_id bigint(20) NOT NULL,
            tag_name varchar(255) NOT NULL,
            tag_type varchar(50) DEFAULT 'label',
            confidence decimal(5,2) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY photo_id (photo_id),
            KEY tag_name (tag_name)
        ) $charset_collate;";
        
        // User favorites table
        $table_favorites = $wpdb->prefix . 'spgp_favorites';
        $sql_favorites = "CREATE TABLE IF NOT EXISTS $table_favorites (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            photo_id bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_photo (user_id, photo_id),
            KEY photo_id (photo_id)
        ) $charset_collate;";
        
        // Photo likes table
        $table_likes = $wpdb->prefix . 'spgp_likes';
        $sql_likes = "CREATE TABLE IF NOT EXISTS $table_likes (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            photo_id bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_photo (user_id, photo_id),
            KEY photo_id (photo_id)
        ) $charset_collate;";
        
        // Photo views table
        $table_views = $wpdb->prefix . 'spgp_views';
        $sql_views = "CREATE TABLE IF NOT EXISTS $table_views (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            photo_id bigint(20) NOT NULL,
            view_count bigint(20) DEFAULT 0,
            last_viewed datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY photo_id (photo_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_faces);
        dbDelta($sql_tags);
        dbDelta($sql_favorites);
        dbDelta($sql_likes);
        dbDelta($sql_views);
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('smart-photo-gallery-pro', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Initialize plugin components
     */
    public function init() {
        // Flush rewrite rules if needed (after activation)
        if (get_option('spgp_flush_rewrite_rules')) {
            flush_rewrite_rules();
            delete_option('spgp_flush_rewrite_rules');
        }
        
        // Initialize post type and taxonomies
        SPGP_Gallery_PostType::get_instance();
        
        // Initialize admin
        if (is_admin()) {
            SPGP_Gallery_Admin::get_instance();
            SPGP_Gallery_Google_Photos::get_instance();
        }
        
        // Initialize frontend
        if (!is_admin() || wp_doing_ajax()) {
            SPGP_Gallery_Frontend::get_instance();
            SPGP_Gallery_Upload::get_instance();
            SPGP_Gallery_Search::get_instance();
        }
        
        // Initialize AI
        SPGP_Gallery_AI::get_instance();
        
        // Initialize REST API
        SPGP_Gallery_API::get_instance();
        
        // Initialize plugin updater
        SPGP_Plugin_Updater::get_instance();
    }
}

// Initialize plugin
Smart_Photo_Gallery_Pro::get_instance();
