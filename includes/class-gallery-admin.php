<?php
/**
 * Admin Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class SPGP_Gallery_Admin {
    
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
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_filter('manage_photo_album_posts_columns', array($this, 'add_custom_columns'));
        add_action('manage_photo_album_posts_custom_column', array($this, 'custom_column_content'), 10, 2);
        add_action('admin_footer', array($this, 'add_admin_footer_scripts'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('HH CJ Google Photos', 'smart-photo-gallery-pro'),
            __('HH CJ Google Photos', 'smart-photo-gallery-pro'),
            'manage_options',
            'smart-photo-gallery-pro',
            array($this, 'settings_page'),
            'dashicons-format-gallery',
            30
        );
        
        add_submenu_page(
            'smart-photo-gallery-pro',
            __('Settings', 'smart-photo-gallery-pro'),
            __('Settings', 'smart-photo-gallery-pro'),
            'manage_options',
            'smart-photo-gallery-pro',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'smart-photo-gallery-pro',
            __('All Photos', 'smart-photo-gallery-pro'),
            __('All Photos', 'smart-photo-gallery-pro'),
            'edit_posts',
            'edit.php?post_type=photo_album'
        );
        
        add_submenu_page(
            'smart-photo-gallery-pro',
            __('Add New Photo', 'smart-photo-gallery-pro'),
            __('Add New Photo', 'smart-photo-gallery-pro'),
            'edit_posts',
            'post-new.php?post_type=photo_album'
        );
        
        if (spgp_is_feature_enabled('face_detection')) {
            add_submenu_page(
                'smart-photo-gallery-pro',
                __('People', 'smart-photo-gallery-pro'),
                __('People', 'smart-photo-gallery-pro'),
                'edit_posts',
                'spgp-people',
                array($this, 'people_page')
            );
        }
        
        add_submenu_page(
            'smart-photo-gallery-pro',
            __('User Uploads', 'smart-photo-gallery-pro'),
            __('User Uploads', 'smart-photo-gallery-pro'),
            'edit_posts',
            'spgp-user-uploads',
            array($this, 'user_uploads_page')
        );
        
        add_submenu_page(
            'smart-photo-gallery-pro',
            __('Check for Updates', 'smart-photo-gallery-pro'),
            __('Check Updates', 'smart-photo-gallery-pro'),
            'manage_options',
            'spgp-check-updates',
            array($this, 'check_updates_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('spgp_settings_group', 'spgp_settings');
        
        // API Settings
        add_settings_section('spgp_api_section', __('API Configuration', 'smart-photo-gallery-pro'), null, 'spgp_settings');
        
        add_settings_field('api_provider', __('API Provider', 'smart-photo-gallery-pro'), array($this, 'api_provider_callback'), 'spgp_settings', 'spgp_api_section');
        add_settings_field('google_vision_api_key', __('Google Vision API Key', 'smart-photo-gallery-pro'), array($this, 'google_vision_api_key_callback'), 'spgp_settings', 'spgp_api_section');
        add_settings_field('aws_access_key', __('AWS Access Key', 'smart-photo-gallery-pro'), array($this, 'aws_access_key_callback'), 'spgp_settings', 'spgp_api_section');
        add_settings_field('aws_secret_key', __('AWS Secret Key', 'smart-photo-gallery-pro'), array($this, 'aws_secret_key_callback'), 'spgp_settings', 'spgp_api_section');
        add_settings_field('aws_region', __('AWS Region', 'smart-photo-gallery-pro'), array($this, 'aws_region_callback'), 'spgp_settings', 'spgp_api_section');
        
        // Gallery Settings
        add_settings_section('spgp_gallery_section', __('Gallery Settings', 'smart-photo-gallery-pro'), null, 'spgp_settings');
        
        add_settings_field('gallery_layout', __('Gallery Layout', 'smart-photo-gallery-pro'), array($this, 'gallery_layout_callback'), 'spgp_settings', 'spgp_gallery_section');
        add_settings_field('items_per_page', __('Items Per Page', 'smart-photo-gallery-pro'), array($this, 'items_per_page_callback'), 'spgp_settings', 'spgp_gallery_section');
        add_settings_field('enable_infinite_scroll', __('Enable Infinite Scroll', 'smart-photo-gallery-pro'), array($this, 'enable_infinite_scroll_callback'), 'spgp_settings', 'spgp_gallery_section');
        add_settings_field('enable_dark_mode', __('Enable Dark Mode', 'smart-photo-gallery-pro'), array($this, 'enable_dark_mode_callback'), 'spgp_settings', 'spgp_gallery_section');
        
        // Feature Settings
        add_settings_section('spgp_features_section', __('Feature Settings', 'smart-photo-gallery-pro'), null, 'spgp_settings');
        
        add_settings_field('enable_user_uploads', __('Enable User Uploads', 'smart-photo-gallery-pro'), array($this, 'enable_user_uploads_callback'), 'spgp_settings', 'spgp_features_section');
        add_settings_field('moderate_uploads', __('Moderate User Uploads', 'smart-photo-gallery-pro'), array($this, 'moderate_uploads_callback'), 'spgp_settings', 'spgp_features_section');
        add_settings_field('enable_ai_tagging', __('Enable AI Auto-Tagging', 'smart-photo-gallery-pro'), array($this, 'enable_ai_tagging_callback'), 'spgp_settings', 'spgp_features_section');
        add_settings_field('enable_face_detection', __('Enable Face Detection', 'smart-photo-gallery-pro'), array($this, 'enable_face_detection_callback'), 'spgp_settings', 'spgp_features_section');
        add_settings_field('enable_favorites', __('Enable Favorites', 'smart-photo-gallery-pro'), array($this, 'enable_favorites_callback'), 'spgp_settings', 'spgp_features_section');
        add_settings_field('enable_likes', __('Enable Likes', 'smart-photo-gallery-pro'), array($this, 'enable_likes_callback'), 'spgp_settings', 'spgp_features_section');
        add_settings_field('enable_views', __('Enable View Tracking', 'smart-photo-gallery-pro'), array($this, 'enable_views_callback'), 'spgp_settings', 'spgp_features_section');
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (isset($_GET['settings-updated'])) {
            add_settings_error('spgp_messages', 'spgp_message', __('Settings Saved', 'smart-photo-gallery-pro'), 'updated');
        }
        
        settings_errors('spgp_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('spgp_settings_group');
                do_settings_sections('spgp_settings');
                submit_button(__('Save Settings', 'smart-photo-gallery-pro'));
                ?>
            </form>
        </div>
        <?php
    }
    
    // Settings field callbacks
    public function api_provider_callback() {
        $value = spgp_get_setting('api_provider', 'google');
        ?>
        <select name="spgp_settings[api_provider]">
            <option value="google" <?php selected($value, 'google'); ?>>Google Vision API</option>
            <option value="aws" <?php selected($value, 'aws'); ?>>AWS Rekognition</option>
        </select>
        <?php
    }
    
    public function google_vision_api_key_callback() {
        $value = spgp_get_setting('google_vision_api_key', '');
        ?>
        <input type="text" name="spgp_settings[google_vision_api_key]" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description"><?php _e('Get your API key from Google Cloud Console', 'smart-photo-gallery-pro'); ?></p>
        <?php
    }
    
    public function aws_access_key_callback() {
        $value = spgp_get_setting('aws_access_key', '');
        ?>
        <input type="text" name="spgp_settings[aws_access_key]" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <?php
    }
    
    public function aws_secret_key_callback() {
        $value = spgp_get_setting('aws_secret_key', '');
        ?>
        <input type="password" name="spgp_settings[aws_secret_key]" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <?php
    }
    
    public function aws_region_callback() {
        $value = spgp_get_setting('aws_region', 'us-east-1');
        ?>
        <input type="text" name="spgp_settings[aws_region]" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description"><?php _e('e.g., us-east-1, eu-west-1', 'smart-photo-gallery-pro'); ?></p>
        <?php
    }
    
    public function gallery_layout_callback() {
        $value = spgp_get_setting('gallery_layout', 'masonry');
        ?>
        <select name="spgp_settings[gallery_layout]">
            <option value="masonry" <?php selected($value, 'masonry'); ?>>Masonry Grid</option>
            <option value="grid" <?php selected($value, 'grid'); ?>>Regular Grid</option>
            <option value="justified" <?php selected($value, 'justified'); ?>>Justified Grid</option>
        </select>
        <?php
    }
    
    public function items_per_page_callback() {
        $value = spgp_get_setting('items_per_page', 20);
        ?>
        <input type="number" name="spgp_settings[items_per_page]" value="<?php echo esc_attr($value); ?>" min="1" max="100" />
        <?php
    }
    
    public function enable_infinite_scroll_callback() {
        $value = spgp_get_setting('enable_infinite_scroll', 1);
        ?>
        <input type="checkbox" name="spgp_settings[enable_infinite_scroll]" value="1" <?php checked($value, 1); ?> />
        <?php
    }
    
    public function enable_dark_mode_callback() {
        $value = spgp_get_setting('enable_dark_mode', 0);
        ?>
        <input type="checkbox" name="spgp_settings[enable_dark_mode]" value="1" <?php checked($value, 1); ?> />
        <?php
    }
    
    public function enable_user_uploads_callback() {
        $value = spgp_get_setting('enable_user_uploads', 1);
        ?>
        <input type="checkbox" name="spgp_settings[enable_user_uploads]" value="1" <?php checked($value, 1); ?> />
        <?php
    }
    
    public function moderate_uploads_callback() {
        $value = spgp_get_setting('moderate_uploads', 1);
        ?>
        <input type="checkbox" name="spgp_settings[moderate_uploads]" value="1" <?php checked($value, 1); ?> />
        <?php
    }
    
    public function enable_ai_tagging_callback() {
        $value = spgp_get_setting('enable_ai_tagging', 1);
        ?>
        <input type="checkbox" name="spgp_settings[enable_ai_tagging]" value="1" <?php checked($value, 1); ?> />
        <?php
    }
    
    public function enable_face_detection_callback() {
        $value = spgp_get_setting('enable_face_detection', 1);
        ?>
        <input type="checkbox" name="spgp_settings[enable_face_detection]" value="1" <?php checked($value, 1); ?> />
        <?php
    }
    
    public function enable_favorites_callback() {
        $value = spgp_get_setting('enable_favorites', 1);
        ?>
        <input type="checkbox" name="spgp_settings[enable_favorites]" value="1" <?php checked($value, 1); ?> />
        <?php
    }
    
    public function enable_likes_callback() {
        $value = spgp_get_setting('enable_likes', 1);
        ?>
        <input type="checkbox" name="spgp_settings[enable_likes]" value="1" <?php checked($value, 1); ?> />
        <?php
    }
    
    public function enable_views_callback() {
        $value = spgp_get_setting('enable_views', 1);
        ?>
        <input type="checkbox" name="spgp_settings[enable_views]" value="1" <?php checked($value, 1); ?> />
        <?php
    }
    
    /**
     * Add custom columns
     */
    public function add_custom_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['spgp_thumbnail'] = __('Photo', 'smart-photo-gallery-pro');
        $new_columns['title'] = $columns['title'];
        $new_columns['album_category'] = __('Categories', 'smart-photo-gallery-pro');
        $new_columns['spgp_event_date'] = __('Event Date', 'smart-photo-gallery-pro');
        $new_columns['spgp_views'] = __('Views', 'smart-photo-gallery-pro');
        $new_columns['date'] = $columns['date'];
        return $new_columns;
    }
    
    /**
     * Custom column content
     */
    public function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'spgp_thumbnail':
                $thumb = spgp_get_photo_thumb($post_id, 'thumbnail');
                if ($thumb) {
                    echo '<img src="' . esc_url($thumb) . '" style="width: 50px; height: 50px; object-fit: cover;" />';
                }
                break;
            case 'spgp_event_date':
                $date_start = get_post_meta($post_id, '_spgp_event_date', true);
                $date_end = get_post_meta($post_id, '_spgp_event_date_end', true);
                if ($date_start) {
                    echo esc_html($date_start);
                    if ($date_end && $date_end != $date_start) {
                        echo ' - ' . esc_html($date_end);
                    }
                }
                break;
            case 'spgp_views':
                echo spgp_get_view_count($post_id);
                break;
        }
    }
    
    /**
     * People page
     */
    public function people_page() {
        $people = spgp_get_people();
        ?>
        <div class="wrap">
            <h1><?php _e('Detected People', 'smart-photo-gallery-pro'); ?></h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Face ID', 'smart-photo-gallery-pro'); ?></th>
                        <th><?php _e('Person Name', 'smart-photo-gallery-pro'); ?></th>
                        <th><?php _e('Photo Count', 'smart-photo-gallery-pro'); ?></th>
                        <th><?php _e('Actions', 'smart-photo-gallery-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($people)) : ?>
                        <tr>
                            <td colspan="4"><?php _e('No people detected yet.', 'smart-photo-gallery-pro'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($people as $person) : ?>
                            <tr>
                                <td><?php echo esc_html($person->face_id); ?></td>
                                <td>
                                    <input type="text" class="spgp-person-name-input" data-face-id="<?php echo esc_attr($person->face_id); ?>" value="<?php echo esc_attr($person->person_name); ?>" />
                                </td>
                                <td><?php echo esc_html($person->photo_count); ?></td>
                                <td>
                                    <button type="button" class="button spgp-update-person-name" data-face-id="<?php echo esc_attr($person->face_id); ?>"><?php _e('Update', 'smart-photo-gallery-pro'); ?></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * User uploads page
     */
    public function user_uploads_page() {
        $args = array(
            'post_type' => 'photo_album',
            'post_status' => 'pending',
            'posts_per_page' => -1,
        );
        $uploads = new WP_Query($args);
        ?>
        <div class="wrap">
            <h1><?php _e('User Uploads - Pending Approval', 'smart-photo-gallery-pro'); ?></h1>
            <?php if (!$uploads->have_posts()) : ?>
                <p><?php _e('No pending uploads.', 'smart-photo-gallery-pro'); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Photo', 'smart-photo-gallery-pro'); ?></th>
                            <th><?php _e('Title', 'smart-photo-gallery-pro'); ?></th>
                            <th><?php _e('Author', 'smart-photo-gallery-pro'); ?></th>
                            <th><?php _e('Date', 'smart-photo-gallery-pro'); ?></th>
                            <th><?php _e('Actions', 'smart-photo-gallery-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($uploads->have_posts()) : $uploads->the_post(); ?>
                            <tr>
                                <td><?php echo get_the_post_thumbnail(get_the_ID(), 'thumbnail'); ?></td>
                                <td><?php echo get_the_title(); ?></td>
                                <td><?php echo get_the_author(); ?></td>
                                <td><?php echo get_the_date(); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('post.php?post=' . get_the_ID() . '&action=edit'); ?>" class="button"><?php _e('Review', 'smart-photo-gallery-pro'); ?></a>
                                    <button type="button" class="button button-primary spgp-approve-upload" data-post-id="<?php echo get_the_ID(); ?>"><?php _e('Approve', 'smart-photo-gallery-pro'); ?></button>
                                    <button type="button" class="button button-link-delete spgp-reject-upload" data-post-id="<?php echo get_the_ID(); ?>"><?php _e('Reject', 'smart-photo-gallery-pro'); ?></button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <?php wp_reset_postdata(); ?>
        </div>
        <?php
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'smart-photo-gallery-pro') === false && $hook !== 'edit.php') {
            return;
        }
        
        wp_enqueue_media();
        
        // Fix hook check for Google Photos page
        if ($hook === 'photo-gallery_page_spgp-google-photos') {
            return;
        }
    }
    
    /**
     * Check for updates page
     */
    public function check_updates_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Handle manual update check
        if (isset($_POST['check_updates']) && check_admin_referer('spgp_check_updates')) {
            $updater = SPGP_Plugin_Updater::get_instance();
            $latest_version = $updater->manual_check();
            
            if ($latest_version && version_compare(SPGP_VERSION, $latest_version, '<')) {
                echo '<div class="notice notice-success"><p>' . sprintf(__('Update available! Latest version: %s (Current: %s)', 'smart-photo-gallery-pro'), $latest_version, SPGP_VERSION) . '</p></div>';
            } else {
                echo '<div class="notice notice-info"><p>' . __('You are using the latest version.', 'smart-photo-gallery-pro') . '</p></div>';
            }
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Check for Updates', 'smart-photo-gallery-pro'); ?></h1>
            
            <div class="card">
                <h2><?php _e('Current Version', 'smart-photo-gallery-pro'); ?></h2>
                <p><strong><?php echo SPGP_VERSION; ?></strong></p>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('spgp_check_updates'); ?>
                <p>
                    <input type="submit" name="check_updates" class="button button-primary" value="<?php esc_attr_e('Check for Updates', 'smart-photo-gallery-pro'); ?>" />
                </p>
            </form>
            
            <div class="card">
                <h3><?php _e('Update Server Configuration', 'smart-photo-gallery-pro'); ?></h3>
                <p><?php _e('Update server URL can be configured using the filter:', 'smart-photo-gallery-pro'); ?></p>
                <code>add_filter('spgp_update_server_url', 'your_custom_url');</code>
            </div>
            
            <div class="card">
                <h3><?php _e('How Updates Work', 'smart-photo-gallery-pro'); ?></h3>
                <ol>
                    <li><?php _e('Plugin checks update server for new version', 'smart-photo-gallery-pro'); ?></li>
                    <li><?php _e('If update available, notification appears in WordPress admin', 'smart-photo-gallery-pro'); ?></li>
                    <li><?php _e('Click "Update Now" to download and install', 'smart-photo-gallery-pro'); ?></li>
                    <li><?php _e('Plugin automatically updates to latest version', 'smart-photo-gallery-pro'); ?></li>
                </ol>
            </div>
        </div>
        <?php
    }
    
    /**
     * Add admin footer scripts
     */
    public function add_admin_footer_scripts() {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'spgp') === false) {
            return;
        }
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Update person name
            $('.spgp-update-person-name').on('click', function() {
                var button = $(this);
                var faceId = button.data('face-id');
                var name = $('.spgp-person-name-input[data-face-id="' + faceId + '"]').val();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'spgp_update_person_name',
                        face_id: faceId,
                        person_name: name,
                        nonce: '<?php echo wp_create_nonce('spgp_update_person'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('<?php _e('Person name updated!', 'smart-photo-gallery-pro'); ?>');
                        }
                    }
                });
            });
            
            // Approve upload
            $('.spgp-approve-upload').on('click', function() {
                var button = $(this);
                var postId = button.data('post-id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'spgp_approve_upload',
                        post_id: postId,
                        nonce: '<?php echo wp_create_nonce('spgp_approve_upload'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            button.closest('tr').fadeOut();
                        }
                    }
                });
            });
            
            // Reject upload
            $('.spgp-reject-upload').on('click', function() {
                if (!confirm('<?php _e('Are you sure you want to reject this upload?', 'smart-photo-gallery-pro'); ?>')) {
                    return;
                }
                
                var button = $(this);
                var postId = button.data('post-id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'spgp_reject_upload',
                        post_id: postId,
                        nonce: '<?php echo wp_create_nonce('spgp_reject_upload'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            button.closest('tr').fadeOut();
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }
}
