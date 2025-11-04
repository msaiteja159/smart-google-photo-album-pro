<?php
/**
 * Google Photos Integration - Import Albums
 */

if (!defined('ABSPATH')) {
    exit;
}

class SPGP_Gallery_Google_Photos {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_spgp_google_photos_authorize', array($this, 'handle_authorization'));
        add_action('wp_ajax_spgp_google_photos_import_album', array($this, 'import_album'));
        add_action('wp_ajax_spgp_google_photos_sync', array($this, 'sync_albums'));
        add_action('wp_ajax_spgp_disconnect_google_photos', array($this, 'disconnect'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'smart-photo-gallery-pro',
            __('Google Photos Import', 'smart-photo-gallery-pro'),
            __('Google Photos', 'smart-photo-gallery-pro'),
            'manage_options',
            'spgp-google-photos',
            array($this, 'google_photos_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('spgp_google_photos_settings', 'spgp_google_photos_client_id');
        register_setting('spgp_google_photos_settings', 'spgp_google_photos_client_secret');
        register_setting('spgp_google_photos_settings', 'spgp_google_photos_access_token');
        register_setting('spgp_google_photos_settings', 'spgp_google_photos_refresh_token');
        register_setting('spgp_google_photos_settings', 'spgp_google_photos_album_mapping', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_album_mapping'),
        ));
    }
    
    /**
     * Sanitize album mapping
     */
    public function sanitize_album_mapping($value) {
        if (!is_array($value)) {
            return array();
        }
        return array_map('intval', $value);
    }
    
    /**
     * Google Photos admin page
     */
    public function google_photos_page() {
        $client_id = get_option('spgp_google_photos_client_id', '');
        $client_secret = get_option('spgp_google_photos_client_secret', '');
        $access_token = get_option('spgp_google_photos_access_token', '');
        $refresh_token = get_option('spgp_google_photos_refresh_token', '');
        $is_connected = !empty($access_token);
        
        // Handle authorization callback
        if (isset($_GET['code']) && !$is_connected) {
            $result = $this->handle_oauth_callback($_GET['code']);
            if ($result) {
                $access_token = get_option('spgp_google_photos_access_token', '');
                $refresh_token = get_option('spgp_google_photos_refresh_token', '');
                $is_connected = !empty($access_token);
                // Show success message
                if ($is_connected) {
                    echo '<div class="notice notice-success"><p>' . __('Successfully connected to Google Photos!', 'smart-photo-gallery-pro') . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error"><p>' . __('Failed to connect to Google Photos. Please check your credentials.', 'smart-photo-gallery-pro') . '</p></div>';
            }
        }
        
        // Handle OAuth errors
        if (isset($_GET['error'])) {
            $error = sanitize_text_field($_GET['error']);
            echo '<div class="notice notice-error"><p>' . sprintf(__('Google Photos connection error: %s', 'smart-photo-gallery-pro'), esc_html($error)) . '</p></div>';
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Google Photos Import', 'smart-photo-gallery-pro'); ?></h1>
            
            <div class="spgp-google-photos-setup">
                <h2><?php _e('Setup', 'smart-photo-gallery-pro'); ?></h2>
                
                <form method="post" action="options.php">
                    <?php settings_fields('spgp_google_photos_settings'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="spgp_google_photos_client_id"><?php _e('Client ID', 'smart-photo-gallery-pro'); ?></label></th>
                            <td>
                                <input type="text" id="spgp_google_photos_client_id" name="spgp_google_photos_client_id" 
                                       value="<?php echo esc_attr($client_id); ?>" class="regular-text" />
                                <p class="description">
                                    <?php _e('Get your Client ID from Google Cloud Console → APIs & Services → Credentials', 'smart-photo-gallery-pro'); ?>
                                    <br>
                                    <a href="https://console.cloud.google.com/apis/credentials" target="_blank"><?php _e('Open Google Cloud Console', 'smart-photo-gallery-pro'); ?></a>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="spgp_google_photos_client_secret"><?php _e('Client Secret', 'smart-photo-gallery-pro'); ?></label></th>
                            <td>
                                <input type="password" id="spgp_google_photos_client_secret" name="spgp_google_photos_client_secret" 
                                       value="<?php echo esc_attr($client_secret); ?>" class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th><label><?php _e('Redirect URI', 'smart-photo-gallery-pro'); ?></label></th>
                            <td>
                                <code><?php echo admin_url('admin.php?page=spgp-google-photos'); ?></code>
                                <p class="description"><?php _e('Add this URL as an authorized redirect URI in your Google OAuth client settings.', 'smart-photo-gallery-pro'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Save Settings', 'smart-photo-gallery-pro')); ?>
                </form>
            </div>
            
            <?php if ($client_id && $client_secret) : ?>
                <div class="spgp-google-photos-connect" style="margin-top: 30px;">
                    <h2><?php _e('Connection', 'smart-photo-gallery-pro'); ?></h2>
                    
                    <?php if (!$is_connected) : ?>
                        <p><?php _e('Click the button below to authorize access to your Google Photos account.', 'smart-photo-gallery-pro'); ?></p>
                        <a href="<?php echo $this->get_authorization_url(); ?>" class="button button-primary button-large">
                            <?php _e('Connect Google Photos', 'smart-photo-gallery-pro'); ?>
                        </a>
                    <?php else : ?>
                        <p style="color: green;">✓ <?php _e('Connected to Google Photos', 'smart-photo-gallery-pro'); ?></p>
                        <button type="button" class="button" id="spgp-disconnect-google"><?php _e('Disconnect', 'smart-photo-gallery-pro'); ?></button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($is_connected) : ?>
                <div class="spgp-google-photos-albums" style="margin-top: 30px;">
                    <h2><?php _e('Import Albums', 'smart-photo-gallery-pro'); ?></h2>
                    <button type="button" class="button button-primary" id="spgp-sync-google-albums">
                        <?php _e('Refresh Albums List', 'smart-photo-gallery-pro'); ?>
                    </button>
                    <div id="spgp-albums-list" style="margin-top: 20px;">
                        <?php $this->render_albums_list(); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Get authorization URL
     */
    private function get_authorization_url() {
        $client_id = get_option('spgp_google_photos_client_id', '');
        $redirect_uri = admin_url('admin.php?page=spgp-google-photos');
        
        // Ensure exact match with redirect URI
        $redirect_uri = esc_url_raw($redirect_uri);
        
        $params = array(
            'client_id' => sanitize_text_field($client_id),
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/photoslibrary.readonly',
            'access_type' => 'offline',
            'prompt' => 'consent',
        );
        
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }
    
    /**
     * Handle OAuth callback
     */
    private function handle_oauth_callback($code) {
        $client_id = get_option('spgp_google_photos_client_id', '');
        $client_secret = get_option('spgp_google_photos_client_secret', '');
        $redirect_uri = admin_url('admin.php?page=spgp-google-photos');
        
        // Ensure redirect URI matches exactly what was configured
        $redirect_uri = esc_url_raw($redirect_uri);
        
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'code' => sanitize_text_field($code),
                'client_id' => sanitize_text_field($client_id),
                'client_secret' => sanitize_text_field($client_secret),
                'redirect_uri' => $redirect_uri,
                'grant_type' => 'authorization_code',
            ),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            error_log('Google Photos OAuth Error: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            error_log('Google Photos OAuth Error Code: ' . $response_code . ' - ' . $body);
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            error_log('Google Photos OAuth Error: ' . $body['error']);
            if (isset($body['error_description'])) {
                error_log('Error Description: ' . $body['error_description']);
            }
            return false;
        }
        
        if (isset($body['access_token'])) {
            update_option('spgp_google_photos_access_token', sanitize_text_field($body['access_token']));
            if (isset($body['refresh_token'])) {
                update_option('spgp_google_photos_refresh_token', sanitize_text_field($body['refresh_token']));
            }
            // Store token expiry if provided
            if (isset($body['expires_in'])) {
                update_option('spgp_google_photos_token_expires', time() + absint($body['expires_in']));
            }
            return true;
        }
        
        return false;
    }
    
    /**
     * Get access token (with refresh if needed)
     */
    private function get_access_token() {
        $access_token = get_option('spgp_google_photos_access_token', '');
        
        // Try to refresh if expired
        if (empty($access_token)) {
            $this->refresh_access_token();
            $access_token = get_option('spgp_google_photos_access_token', '');
        }
        
        return $access_token;
    }
    
    /**
     * Refresh access token
     */
    private function refresh_access_token() {
        $client_id = get_option('spgp_google_photos_client_id', '');
        $client_secret = get_option('spgp_google_photos_client_secret', '');
        $refresh_token = get_option('spgp_google_photos_refresh_token', '');
        
        if (empty($refresh_token)) {
            return false;
        }
        
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $refresh_token,
                'grant_type' => 'refresh_token',
            ),
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['access_token'])) {
            update_option('spgp_google_photos_access_token', $body['access_token']);
            return true;
        }
        
        return false;
    }
    
    /**
     * Get albums from Google Photos
     */
    public function get_albums() {
        $access_token = $this->get_access_token();
        if (empty($access_token)) {
            return array();
        }
        
        $albums = array();
        $page_token = null;
        
        do {
            $url = 'https://photoslibrary.googleapis.com/v1/albums';
            $args = array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                ),
            );
            
            if ($page_token) {
                $url .= '?pageToken=' . $page_token;
            }
            
            $response = wp_remote_get($url, $args);
            
            if (is_wp_error($response)) {
                break;
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($body['albums'])) {
                $albums = array_merge($albums, $body['albums']);
            }
            
            $page_token = isset($body['nextPageToken']) ? $body['nextPageToken'] : null;
            
        } while ($page_token);
        
        return $albums;
    }
    
    /**
     * Get media items from an album
     */
    public function get_album_media($album_id, $page_token = null) {
        $access_token = $this->get_access_token();
        if (empty($access_token)) {
            return array('mediaItems' => array(), 'nextPageToken' => null);
        }
        
        $url = 'https://photoslibrary.googleapis.com/v1/mediaItems:search';
        
        $body_data = array(
            'albumId' => $album_id,
            'pageSize' => 100,
        );
        
        if ($page_token) {
            $body_data['pageToken'] = $page_token;
        }
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($body_data),
        ));
        
        if (is_wp_error($response)) {
            return array('mediaItems' => array(), 'nextPageToken' => null);
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        return array(
            'mediaItems' => isset($body['mediaItems']) ? $body['mediaItems'] : array(),
            'nextPageToken' => isset($body['nextPageToken']) ? $body['nextPageToken'] : null,
        );
    }
    
    /**
     * Import album
     */
    public function import_album() {
        check_ajax_referer('spgp_google_photos', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'smart-photo-gallery-pro')));
            return;
        }
        
        $album_id = isset($_POST['album_id']) ? sanitize_text_field($_POST['album_id']) : '';
        $album_title = isset($_POST['album_title']) ? sanitize_text_field($_POST['album_title']) : '';
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        
        if (empty($album_id)) {
            wp_send_json_error(array('message' => __('Album ID required.', 'smart-photo-gallery-pro')));
            return;
        }
        
        // Create or get category
        if ($category_id > 0) {
            $category = get_term($category_id, 'album_category');
        } else {
            // Create category from album title
            $category = wp_insert_term($album_title, 'album_category');
            if (!is_wp_error($category)) {
                $category_id = $category['term_id'];
            } else {
                // Term might already exist
                $category = get_term_by('name', $album_title, 'album_category');
                if ($category) {
                    $category_id = $category->term_id;
                }
            }
        }
        
        // Get all media items from album
        $all_media = array();
        $page_token = null;
        
        do {
            $result = $this->get_album_media($album_id, $page_token);
            $all_media = array_merge($all_media, $result['mediaItems']);
            $page_token = $result['nextPageToken'];
        } while ($page_token);
        
        if (empty($all_media)) {
            wp_send_json_error(array('message' => __('No photos found in album.', 'smart-photo-gallery-pro')));
            return;
        }
        
        $imported = 0;
        $skipped = 0;
        
        foreach ($all_media as $media_item) {
            // Check if already imported
            $existing = get_posts(array(
                'post_type' => 'photo_album',
                'meta_query' => array(
                    array(
                        'key' => '_spgp_google_photos_id',
                        'value' => $media_item['id'],
                        'compare' => '=',
                    ),
                ),
                'posts_per_page' => 1,
            ));
            
            if (!empty($existing)) {
                $skipped++;
                continue;
            }
            
            // Download image
            $image_url = $media_item['baseUrl'] . '=w2048-h2048'; // Get high-res version
            $upload = $this->download_and_import_image($image_url, $media_item);
            
            if ($upload && !is_wp_error($upload)) {
                // Create photo post
                $post_data = array(
                    'post_title' => $media_item['filename'] ?? 'Imported Photo',
                    'post_content' => isset($media_item['description']) ? $media_item['description'] : '',
                    'post_status' => 'publish',
                    'post_type' => 'photo_album',
                );
                
                $post_id = wp_insert_post($post_data);
                
                if ($post_id && !is_wp_error($post_id)) {
                    // Set attachment
                    update_post_meta($post_id, '_spgp_photo_attachment_id', $upload);
                    update_post_meta($post_id, '_spgp_google_photos_id', $media_item['id']);
                    
                    // Set category
                    if ($category_id) {
                        wp_set_post_terms($post_id, array($category_id), 'album_category');
                    }
                    
                    // Set date
                    if (isset($media_item['mediaMetadata']['creationTime'])) {
                        $date = date('Y-m-d', strtotime($media_item['mediaMetadata']['creationTime']));
                        update_post_meta($post_id, '_spgp_event_date', $date);
                    }
                    
                    $imported++;
                }
            }
        }
        
        // Store album mapping
        $mappings = get_option('spgp_google_photos_album_mapping', array());
        $mappings[$album_id] = $category_id;
        update_option('spgp_google_photos_album_mapping', $mappings);
        
        wp_send_json_success(array(
            'message' => sprintf(__('Imported %d photos. Skipped %d duplicates.', 'smart-photo-gallery-pro'), $imported, $skipped),
            'imported' => $imported,
            'skipped' => $skipped,
        ));
    }
    
    /**
     * Download and import image
     */
    private function download_and_import_image($url, $media_item) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $tmp = download_url($url);
        
        if (is_wp_error($tmp)) {
            return $tmp;
        }
        
        $filename = $media_item['filename'] ?? 'google-photos-' . time() . '.jpg';
        $file_array = array(
            'name' => $filename,
            'tmp_name' => $tmp,
        );
        
        $attachment_id = media_handle_sideload($file_array, 0);
        
        @unlink($tmp);
        
        return $attachment_id;
    }
    
    /**
     * Sync albums (refresh list)
     */
    public function sync_albums() {
        check_ajax_referer('spgp_google_photos', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'smart-photo-gallery-pro')));
            return;
        }
        
        $albums = $this->get_albums();
        
        if (empty($albums)) {
            wp_send_json_error(array('message' => __('No albums found or connection failed.', 'smart-photo-gallery-pro')));
            return;
        }
        
        // Store albums
        update_option('spgp_google_photos_albums_cache', $albums);
        
        ob_start();
        $this->render_albums_list();
        $html = ob_get_clean();
        
        wp_send_json_success(array(
            'message' => sprintf(__('Found %d albums.', 'smart-photo-gallery-pro'), count($albums)),
            'html' => $html,
        ));
    }
    
    /**
     * Render albums list
     */
    private function render_albums_list() {
        $albums = get_option('spgp_google_photos_albums_cache', array());
        $mappings = get_option('spgp_google_photos_album_mapping', array());
        $categories = get_terms(array('taxonomy' => 'album_category', 'hide_empty' => false));
        
        if (empty($albums)) {
            echo '<p>' . __('No albums found. Click "Refresh Albums List" to load albums from Google Photos.', 'smart-photo-gallery-pro') . '</p>';
            return;
        }
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Album Name', 'smart-photo-gallery-pro'); ?></th>
                    <th><?php _e('Photos', 'smart-photo-gallery-pro'); ?></th>
                    <th><?php _e('Map to Category', 'smart-photo-gallery-pro'); ?></th>
                    <th><?php _e('Actions', 'smart-photo-gallery-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($albums as $album) : 
                    $category_id = isset($mappings[$album['id']]) ? $mappings[$album['id']] : 0;
                ?>
                    <tr>
                        <td><strong><?php echo esc_html($album['title']); ?></strong></td>
                        <td><?php echo isset($album['mediaItemsCount']) ? esc_html($album['mediaItemsCount']) : '?'; ?></td>
                        <td>
                            <select class="spgp-category-select" data-album-id="<?php echo esc_attr($album['id']); ?>">
                                <option value="0"><?php _e('Auto-create Category', 'smart-photo-gallery-pro'); ?></option>
                                <?php foreach ($categories as $cat) : ?>
                                    <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected($category_id, $cat->term_id); ?>>
                                        <?php echo esc_html($cat->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <button type="button" class="button button-primary spgp-import-album" 
                                    data-album-id="<?php echo esc_attr($album['id']); ?>"
                                    data-album-title="<?php echo esc_attr($album['title']); ?>">
                                <?php _e('Import', 'smart-photo-gallery-pro'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Disconnect from Google Photos
     */
    public function disconnect() {
        check_ajax_referer('spgp_google_photos', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'smart-photo-gallery-pro')));
            return;
        }
        
        delete_option('spgp_google_photos_access_token');
        delete_option('spgp_google_photos_refresh_token');
        
        wp_send_json_success(array('message' => __('Disconnected successfully.', 'smart-photo-gallery-pro')));
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'photo-gallery_page_spgp-google-photos') {
            return;
        }
        
        wp_enqueue_script('spgp-google-photos-admin', SPGP_PLUGIN_URL . 'assets/js/google-photos-admin.js', array('jquery'), SPGP_VERSION, true);
        wp_localize_script('spgp-google-photos-admin', 'spgpGooglePhotos', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('spgp_google_photos'),
            'strings' => array(
                'importing' => __('Importing...', 'smart-photo-gallery-pro'),
                'imported' => __('Imported successfully!', 'smart-photo-gallery-pro'),
                'error' => __('An error occurred.', 'smart-photo-gallery-pro'),
            ),
        ));
    }
}
