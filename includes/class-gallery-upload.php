<?php
/**
 * User Upload Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class SPGP_Gallery_Upload {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_spgp_upload_photo', array($this, 'handle_upload'));
        add_action('wp_ajax_nopriv_spgp_upload_photo', array($this, 'handle_upload'));
        add_action('wp_ajax_spgp_approve_upload', array($this, 'approve_upload'));
        add_action('wp_ajax_spgp_reject_upload', array($this, 'reject_upload'));
    }
    
    /**
     * Handle photo upload
     */
    public function handle_upload() {
        // Check if user uploads are enabled
        if (!spgp_is_feature_enabled('user_uploads')) {
            wp_send_json_error(array('message' => __('User uploads are disabled.', 'smart-photo-gallery-pro')));
            return;
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in to upload photos.', 'smart-photo-gallery-pro')));
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'spgp_upload_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'smart-photo-gallery-pro')));
            return;
        }
        
        // Check file upload
        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => __('File upload failed.', 'smart-photo-gallery-pro')));
            return;
        }
        
        $file = $_FILES['photo'];
        
        // Validate file
        $validation = spgp_validate_image($file);
        if (is_wp_error($validation)) {
            wp_send_json_error(array('message' => $validation->get_error_message()));
            return;
        }
        
        // Handle file upload
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        $upload = wp_handle_upload($file, array('test_form' => false));
        
        if (isset($upload['error'])) {
            wp_send_json_error(array('message' => $upload['error']));
            return;
        }
        
        // Create attachment
        $attachment = array(
            'post_mime_type' => $upload['type'],
            'post_title' => sanitize_text_field($_POST['title'] ?? ''),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        $attachment_id = wp_insert_attachment($attachment, $upload['file']);
        
        if (is_wp_error($attachment_id)) {
            wp_send_json_error(array('message' => __('Failed to create attachment.', 'smart-photo-gallery-pro')));
            return;
        }
        
        // Generate attachment metadata
        $attach_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attach_data);
        
        // Determine post status
        $moderate = spgp_get_setting('moderate_uploads', 1);
        $post_status = $moderate ? 'pending' : 'publish';
        
        // Verify post type exists before creating
        if (!post_type_exists('photo_album')) {
            // Try to register it
            require_once SPGP_PLUGIN_DIR . 'includes/class-gallery-posttype.php';
            $post_type = SPGP_Gallery_PostType::get_instance();
            $post_type->register_post_type();
        }
        
        // Create photo post
        $post_data = array(
            'post_title' => sanitize_text_field($_POST['title'] ?? ''),
            'post_content' => wp_kses_post($_POST['description'] ?? ''),
            'post_status' => $post_status,
            'post_type' => 'photo_album',
            'post_author' => get_current_user_id(),
        );
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            wp_delete_attachment($attachment_id, true);
            wp_send_json_error(array('message' => __('Failed to create photo post. Error: ', 'smart-photo-gallery-pro') . $post_id->get_error_message()));
            return;
        }
        
        if (!$post_id || $post_id === 0) {
            wp_delete_attachment($attachment_id, true);
            wp_send_json_error(array('message' => __('Failed to create photo post. Please try again.', 'smart-photo-gallery-pro')));
            return;
        }
        
        // Save attachment ID
        update_post_meta($post_id, '_spgp_photo_attachment_id', $attachment_id);
        
        // Save event date if provided
        if (!empty($_POST['event_date'])) {
            update_post_meta($post_id, '_spgp_event_date', sanitize_text_field($_POST['event_date']));
        }
        
        if (!empty($_POST['event_date_end'])) {
            update_post_meta($post_id, '_spgp_event_date_end', sanitize_text_field($_POST['event_date_end']));
        }
        
        // Save location if provided
        if (!empty($_POST['location'])) {
            update_post_meta($post_id, '_spgp_location', sanitize_text_field($_POST['location']));
        }
        
        // Set categories
        if (!empty($_POST['category'])) {
            $categories = is_array($_POST['category']) ? $_POST['category'] : array($_POST['category']);
            $category_ids = array_map('absint', $categories);
            // Verify categories exist and belong to correct taxonomy
            $valid_categories = array();
            foreach ($category_ids as $cat_id) {
                $term = get_term($cat_id, 'album_category');
                if ($term && !is_wp_error($term)) {
                    $valid_categories[] = $cat_id;
                }
            }
            if (!empty($valid_categories)) {
                wp_set_post_terms($post_id, $valid_categories, 'album_category');
            }
        }
        
        // Set tags
        if (!empty($_POST['tags'])) {
            $tags = is_array($_POST['tags']) ? $_POST['tags'] : explode(',', $_POST['tags']);
            // Sanitize tags
            $tags = array_map('sanitize_text_field', array_map('trim', $tags));
            $tags = array_filter($tags); // Remove empty tags
            // Limit tag count to prevent abuse
            $tags = array_slice($tags, 0, 20);
            if (!empty($tags)) {
                wp_set_post_tags($post_id, $tags);
            }
        }
        
        // Trigger AI processing
        if (spgp_is_feature_enabled('ai_tagging') || spgp_is_feature_enabled('face_detection')) {
            do_action('spgp_process_photo_ai', $post_id, $attachment_id);
        }
        
        $message = $moderate 
            ? __('Photo uploaded successfully! It will be published after admin approval.', 'smart-photo-gallery-pro')
            : __('Photo uploaded successfully!', 'smart-photo-gallery-pro');
        
        wp_send_json_success(array(
            'message' => $message,
            'post_id' => $post_id,
            'redirect' => $moderate ? '' : get_permalink($post_id),
        ));
    }
    
    /**
     * Approve upload
     */
    public function approve_upload() {
        check_ajax_referer('spgp_approve_upload', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'smart-photo-gallery-pro')));
            return;
        }
        
        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);
        
        if (!$post || $post->post_type !== 'photo_album') {
            wp_send_json_error(array('message' => __('Invalid post.', 'smart-photo-gallery-pro')));
            return;
        }
        
        wp_update_post(array(
            'ID' => $post_id,
            'post_status' => 'publish',
        ));
        
        wp_send_json_success(array('message' => __('Photo approved.', 'smart-photo-gallery-pro')));
    }
    
    /**
     * Reject upload
     */
    public function reject_upload() {
        check_ajax_referer('spgp_reject_upload', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'smart-photo-gallery-pro')));
            return;
        }
        
        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);
        
        if (!$post || $post->post_type !== 'photo_album') {
            wp_send_json_error(array('message' => __('Invalid post.', 'smart-photo-gallery-pro')));
            return;
        }
        
        // Delete attachment
        $attachment_id = get_post_meta($post_id, '_spgp_photo_attachment_id', true);
        if ($attachment_id) {
            wp_delete_attachment($attachment_id, true);
        }
        
        // Delete post
        wp_delete_post($post_id, true);
        
        wp_send_json_success(array('message' => __('Photo rejected and deleted.', 'smart-photo-gallery-pro')));
    }
}
