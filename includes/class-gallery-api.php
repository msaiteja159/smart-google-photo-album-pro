<?php
/**
 * REST API Endpoints
 */

if (!defined('ABSPATH')) {
    exit;
}

class SPGP_Gallery_API {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('wp_ajax_spgp_toggle_like', array($this, 'toggle_like'));
        add_action('wp_ajax_nopriv_spgp_toggle_like', array($this, 'toggle_like'));
        add_action('wp_ajax_spgp_toggle_favorite', array($this, 'toggle_favorite'));
        add_action('wp_ajax_nopriv_spgp_toggle_favorite', array($this, 'toggle_favorite'));
        add_action('wp_ajax_spgp_update_person_name', array($this, 'update_person_name'));
        add_action('wp_ajax_spgp_get_related_photos', array($this, 'get_related_photos'));
        add_action('wp_ajax_nopriv_spgp_get_related_photos', array($this, 'get_related_photos'));
        add_action('wp_ajax_spgp_get_photo_details', array($this, 'get_photo_details_ajax'));
        add_action('wp_ajax_nopriv_spgp_get_photo_details', array($this, 'get_photo_details_ajax'));
    }
    
    /**
     * Register REST routes
     */
    public function register_routes() {
        register_rest_route('spgp/v1', '/photos', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_photos'),
            'permission_callback' => '__return_true',
        ));
        
        register_rest_route('spgp/v1', '/photos/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_photo'),
            'permission_callback' => '__return_true',
        ));
    }
    
    /**
     * Get photos endpoint
     */
    public function get_photos($request) {
        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: spgp_get_setting('items_per_page', 20);
        $category = $request->get_param('category') ?: 0;
        $tags = $request->get_param('tags') ?: '';
        
        $search = SPGP_Gallery_Search::get_instance();
        $results = $search->search_photos('', $category, '', '', $tags, $page, $per_page);
        
        return rest_ensure_response($results);
    }
    
    /**
     * Get single photo endpoint
     */
    public function get_photo($request) {
        $id = $request->get_param('id');
        $post = get_post($id);
        
        if (!$post || $post->post_type !== 'photo_album') {
            return new WP_Error('not_found', __('Photo not found.', 'smart-photo-gallery-pro'), array('status' => 404));
        }
        
        $photo = array(
            'id' => $post->ID,
            'title' => get_the_title($post->ID),
            'description' => get_the_content(null, false, $post->ID),
            'url' => get_permalink($post->ID),
            'image' => spgp_get_photo_url($post->ID, 'full'),
            'thumbnail' => spgp_get_photo_thumb($post->ID, 'medium'),
            'categories' => wp_get_post_terms($post->ID, 'album_category', array('fields' => 'all')),
            'tags' => spgp_get_photo_tags($post->ID),
            'event_date' => get_post_meta($post->ID, '_spgp_event_date', true),
            'event_date_end' => get_post_meta($post->ID, '_spgp_event_date_end', true),
            'location' => get_post_meta($post->ID, '_spgp_location', true),
            'views' => spgp_get_view_count($post->ID),
            'likes' => spgp_get_like_count($post->ID),
            'is_liked' => spgp_is_liked($post->ID),
            'is_favorited' => spgp_is_favorited($post->ID),
        );
        
        return rest_ensure_response($photo);
    }
    
    /**
     * Toggle like
     */
    public function toggle_like() {
        check_ajax_referer('spgp_ajax_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in to like photos.', 'smart-photo-gallery-pro')));
            return;
        }
        
        if (!spgp_is_feature_enabled('likes')) {
            wp_send_json_error(array('message' => __('Likes are disabled.', 'smart-photo-gallery-pro')));
            return;
        }
        
        $post_id = isset($_POST['photo_id']) ? absint($_POST['photo_id']) : 0;
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid photo ID.', 'smart-photo-gallery-pro')));
            return;
        }
        
        // Verify post exists and is published
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'photo_album' || $post->post_status !== 'publish') {
            wp_send_json_error(array('message' => __('Photo not found.', 'smart-photo-gallery-pro')));
            return;
        }
        
        $user_id = absint(get_current_user_id());
        if (!$user_id) {
            wp_send_json_error(array('message' => __('Invalid user.', 'smart-photo-gallery-pro')));
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'spgp_likes';
        $table_escaped = esc_sql($table);
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_escaped} WHERE user_id = %d AND photo_id = %d",
            $user_id, $post_id
        ));
        
        if ($existing) {
            // Unlike
            $wpdb->delete($table_escaped, array('user_id' => $user_id, 'photo_id' => $post_id), array('%d', '%d'));
            $liked = false;
        } else {
            // Like
            $wpdb->insert($table_escaped, array('user_id' => $user_id, 'photo_id' => $post_id), array('%d', '%d'));
            $liked = true;
        }
        
        wp_send_json_success(array(
            'liked' => $liked,
            'count' => absint(spgp_get_like_count($post_id)),
        ));
    }
    
    /**
     * Toggle favorite
     */
    public function toggle_favorite() {
        check_ajax_referer('spgp_ajax_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in to favorite photos.', 'smart-photo-gallery-pro')));
            return;
        }
        
        if (!spgp_is_feature_enabled('favorites')) {
            wp_send_json_error(array('message' => __('Favorites are disabled.', 'smart-photo-gallery-pro')));
            return;
        }
        
        $post_id = isset($_POST['photo_id']) ? absint($_POST['photo_id']) : 0;
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid photo ID.', 'smart-photo-gallery-pro')));
            return;
        }
        
        // Verify post exists and is published
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'photo_album' || $post->post_status !== 'publish') {
            wp_send_json_error(array('message' => __('Photo not found.', 'smart-photo-gallery-pro')));
            return;
        }
        
        $user_id = absint(get_current_user_id());
        if (!$user_id) {
            wp_send_json_error(array('message' => __('Invalid user.', 'smart-photo-gallery-pro')));
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'spgp_favorites';
        $table_escaped = esc_sql($table);
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_escaped} WHERE user_id = %d AND photo_id = %d",
            $user_id, $post_id
        ));
        
        if ($existing) {
            // Unfavorite
            $wpdb->delete($table_escaped, array('user_id' => $user_id, 'photo_id' => $post_id), array('%d', '%d'));
            $favorited = false;
        } else {
            // Favorite
            $wpdb->insert($table_escaped, array('user_id' => $user_id, 'photo_id' => $post_id), array('%d', '%d'));
            $favorited = true;
        }
        
        wp_send_json_success(array('favorited' => $favorited));
    }
    
    /**
     * Update person name
     */
    public function update_person_name() {
        check_ajax_referer('spgp_update_person', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'smart-photo-gallery-pro')));
            return;
        }
        
        $face_id = isset($_POST['face_id']) ? sanitize_text_field($_POST['face_id']) : '';
        $person_name = isset($_POST['person_name']) ? sanitize_text_field($_POST['person_name']) : '';
        
        if (empty($face_id)) {
            wp_send_json_error(array('message' => __('Invalid face ID.', 'smart-photo-gallery-pro')));
            return;
        }
        
        // Validate person_name length
        if (strlen($person_name) > 255) {
            wp_send_json_error(array('message' => __('Person name is too long.', 'smart-photo-gallery-pro')));
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'spgp_faces';
        $table_escaped = esc_sql($table);
        
        $result = $wpdb->update(
            $table_escaped,
            array('person_name' => $person_name),
            array('face_id' => $face_id),
            array('%s'),
            array('%s')
        );
        
        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to update person name.', 'smart-photo-gallery-pro')));
            return;
        }
        
        wp_send_json_success(array('message' => __('Person name updated.', 'smart-photo-gallery-pro')));
    }
    
    /**
     * Get photo details via AJAX
     */
    public function get_photo_details_ajax() {
        // Verify nonce (optional for public read, but good practice)
        if (isset($_GET['nonce']) && !wp_verify_nonce($_GET['nonce'], 'spgp_ajax_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'smart-photo-gallery-pro')));
            return;
        }
        
        $post_id = isset($_GET['photo_id']) ? absint($_GET['photo_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid photo ID.', 'smart-photo-gallery-pro')));
            return;
        }
        
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'photo_album' || $post->post_status !== 'publish') {
            wp_send_json_error(array('message' => __('Photo not found.', 'smart-photo-gallery-pro')));
            return;
        }
        
        $photo = array(
            'id' => absint($post->ID),
            'title' => sanitize_text_field(get_the_title($post->ID)),
            'description' => wp_kses_post(get_the_content(null, false, $post->ID)),
            'url' => esc_url(get_permalink($post->ID)),
            'image' => esc_url(spgp_get_photo_url($post->ID, 'full')),
            'thumbnail' => esc_url(spgp_get_photo_thumb($post->ID, 'medium')),
            'categories' => array_map('sanitize_text_field', wp_get_post_terms($post->ID, 'album_category', array('fields' => 'names'))),
            'tags' => array_map('sanitize_text_field', spgp_get_photo_tags($post->ID)),
            'event_date' => sanitize_text_field(get_post_meta($post->ID, '_spgp_event_date', true)),
            'event_date_end' => sanitize_text_field(get_post_meta($post->ID, '_spgp_event_date_end', true)),
            'location' => sanitize_text_field(get_post_meta($post->ID, '_spgp_location', true)),
            'views' => absint(spgp_get_view_count($post->ID)),
            'likes' => absint(spgp_get_like_count($post->ID)),
            'is_liked' => spgp_is_liked($post->ID),
            'is_favorited' => spgp_is_favorited($post->ID),
        );
        
        wp_send_json_success($photo);
    }
    
    /**
     * Get related photos
     */
    public function get_related_photos() {
        // Verify nonce (optional for public read)
        if (isset($_GET['nonce']) && !wp_verify_nonce($_GET['nonce'], 'spgp_ajax_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'smart-photo-gallery-pro')));
            return;
        }
        
        $post_id = isset($_GET['photo_id']) ? absint($_GET['photo_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid photo ID.', 'smart-photo-gallery-pro')));
            return;
        }
        
        // Verify post exists and is published
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'photo_album' || $post->post_status !== 'publish') {
            wp_send_json_error(array('message' => __('Photo not found.', 'smart-photo-gallery-pro')));
            return;
        }
        
        $related = spgp_get_related_photos($post_id, 12);
        $photos = array();
        
        foreach ($related as $photo) {
            if ($photo->post_status !== 'publish') {
                continue; // Skip unpublished posts
            }
            $photos[] = array(
                'id' => absint($photo->ID),
                'title' => sanitize_text_field(get_the_title($photo->ID)),
                'url' => esc_url(get_permalink($photo->ID)),
                'thumbnail' => esc_url(spgp_get_photo_thumb($photo->ID, 'medium')),
                'image' => esc_url(spgp_get_photo_url($photo->ID, 'large')),
            );
        }
        
        wp_send_json_success(array('photos' => $photos));
    }
}
