<?php
/**
 * Plugin Update Checker
 * Checks for updates from remote server
 */

if (!defined('ABSPATH')) {
    exit;
}

class SPGP_Plugin_Updater {
    
    private static $instance = null;
    private $update_server_url = '';
    private $plugin_slug = 'smart-photo-gallery-pro';
    private $plugin_file = '';
    private $current_version = '';
    private $license_key = '';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->plugin_file = SPGP_PLUGIN_FILE;
        $this->current_version = SPGP_VERSION;
        
        // Get update server URL from settings (you can configure this)
        // Default: empty (updates disabled until configured)
        // To enable: add_filter('spgp_update_server_url', 'https://yourdomain.com/updates/');
        $this->update_server_url = apply_filters('spgp_update_server_url', '');
        
        // Only hook into update system if server URL is configured
        if (!empty($this->update_server_url)) {
            // Get license key if you want to add license checking
            $this->license_key = get_option('spgp_license_key', '');
            
            // Hook into WordPress update system
            add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'));
            add_filter('plugins_api', array($this, 'plugins_api_handler'), 10, 3);
            add_action('upgrader_process_complete', array($this, 'after_update'), 10, 2);
            add_filter('upgrader_pre_download', array($this, 'maybe_authenticate_download'), 10, 3);
        }
    }
    
    /**
     * Check for plugin updates
     */
    public function check_for_updates($transient) {
        if (empty($transient->checked) || empty($this->update_server_url)) {
            return $transient;
        }
        
        $remote_version = $this->get_remote_version();
        
        if ($remote_version && version_compare($this->current_version, $remote_version, '<')) {
            $plugin_data = get_plugin_data($this->plugin_file);
            
            $obj = new stdClass();
            $obj->slug = $this->plugin_slug;
            $obj->plugin = plugin_basename($this->plugin_file);
            $obj->new_version = $remote_version;
            $obj->url = $this->update_server_url . 'info.json';
            $obj->package = $this->get_download_url();
            
            // Add icons if they exist
            $obj->icons = array();
            $icon_1x_path = SPGP_PLUGIN_DIR . 'assets/images/icon-128x128.png';
            $icon_2x_path = SPGP_PLUGIN_DIR . 'assets/images/icon-256x256.png';
            
            if (file_exists($icon_1x_path)) {
                $obj->icons['1x'] = SPGP_PLUGIN_URL . 'assets/images/icon-128x128.png';
            }
            if (file_exists($icon_2x_path)) {
                $obj->icons['2x'] = SPGP_PLUGIN_URL . 'assets/images/icon-256x256.png';
            }
            
            $transient->response[$obj->plugin] = $obj;
        }
        
        return $transient;
    }
    
    /**
     * Get remote version info
     */
    public function get_remote_version() {
        if (empty($this->update_server_url)) {
            return false;
        }
        
        $cache_key = 'spgp_remote_version_' . md5($this->update_server_url);
        $version = get_transient($cache_key);
        
        if (false === $version) {
            // Try version.json endpoint
            $response = wp_remote_get($this->update_server_url . 'version.json', array(
                'timeout' => 15,
                'headers' => array(
                    'Accept' => 'application/json',
                ),
            ));
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if (isset($data['version'])) {
                    $version = sanitize_text_field($data['version']);
                    set_transient($cache_key, $version, 12 * HOUR_IN_SECONDS);
                }
            }
        }
        
        return $version;
    }
    
    /**
     * Get download URL for update
     */
    private function get_download_url() {
        $url = $this->update_server_url . 'download/';
        
        // Add license key if available
        if (!empty($this->license_key)) {
            $url = add_query_arg('license_key', urlencode($this->license_key), $url);
        }
        
        // Add site URL for validation
        $url = add_query_arg('site_url', urlencode(home_url()), $url);
        
        return $url;
    }
    
    /**
     * Handle plugins_api for update info
     */
    public function plugins_api_handler($res, $action, $args) {
        if ($action !== 'plugin_information' || !isset($args->slug) || $args->slug !== $this->plugin_slug) {
            return $res;
        }
        
        $response = wp_remote_get($this->update_server_url . 'info.json', array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/json',
            ),
        ));
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return $res;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['info'])) {
            $res = (object) $data['info'];
            return $res;
        }
        
        return $res;
    }
    
    /**
     * After update is complete
     */
    public function after_update($upgrader, $hook_extra) {
        if ($hook_extra['action'] === 'update' && $hook_extra['type'] === 'plugin') {
            if (isset($hook_extra['plugins']) && in_array(plugin_basename($this->plugin_file), $hook_extra['plugins'])) {
                // Clear caches
                delete_transient('spgp_remote_version_' . md5($this->update_server_url));
                
                // Run any post-update tasks
                do_action('spgp_after_update');
            }
        }
    }
    
    /**
     * Authenticate download if needed
     */
    public function maybe_authenticate_download($reply, $package, $upgrader) {
        if (strpos($package, $this->update_server_url) !== false) {
            // Add any authentication headers if needed
            add_filter('http_request_args', array($this, 'add_auth_headers'), 10, 2);
        }
        return $reply;
    }
    
    /**
     * Add authentication headers
     */
    public function add_auth_headers($args, $url) {
        if (strpos($url, $this->update_server_url) !== false && !empty($this->license_key)) {
            $args['headers']['X-License-Key'] = $this->license_key;
            $args['headers']['X-Site-URL'] = home_url();
        }
        return $args;
    }
    
    /**
     * Manual check for updates
     */
    public function manual_check() {
        delete_transient('spgp_remote_version_' . md5($this->update_server_url));
        $this->check_for_updates(get_site_transient('update_plugins'));
        return $this->get_remote_version();
    }
}
