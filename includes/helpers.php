<?php
/**
 * Helper Functions
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get plugin settings
 */
function spgp_get_setting($key, $default = '') {
    $settings = get_option('spgp_settings', array());
    return isset($settings[$key]) ? $settings[$key] : $default;
}

/**
 * Update plugin setting
 */
function spgp_update_setting($key, $value) {
    $settings = get_option('spgp_settings', array());
    $settings[$key] = $value;
    update_option('spgp_settings', $settings);
}

/**
 * Get all plugin settings
 */
function spgp_get_settings() {
    return get_option('spgp_settings', array());
}

/**
 * Check if feature is enabled
 */
function spgp_is_feature_enabled($feature) {
    return (bool) spgp_get_setting('enable_' . $feature, 1);
}

/**
 * Get photo attachment ID from post meta
 */
function spgp_get_photo_attachment_id($post_id) {
    return get_post_meta($post_id, '_spgp_photo_attachment_id', true);
}

/**
 * Get photo URL
 */
function spgp_get_photo_url($post_id, $size = 'full') {
    $attachment_id = spgp_get_photo_attachment_id($post_id);
    if ($attachment_id) {
        $image = wp_get_attachment_image_src($attachment_id, $size);
        return $image ? $image[0] : '';
    }
    return '';
}

/**
 * Get photo thumbnail URL
 */
function spgp_get_photo_thumb($post_id, $size = 'medium') {
    return spgp_get_photo_url($post_id, $size);
}

/**
 * Get photo categories
 */
function spgp_get_photo_categories($post_id) {
    return wp_get_post_terms($post_id, 'album_category', array('fields' => 'all'));
}

/**
 * Get photo tags
 */
function spgp_get_photo_tags($post_id) {
    $tags = wp_get_post_tags($post_id, array('fields' => 'names'));
    $ai_tags = spgp_get_ai_tags($post_id);
    return array_unique(array_merge($tags, $ai_tags));
}

/**
 * Get AI tags for a photo
 */
function spgp_get_ai_tags($post_id) {
    if (!absint($post_id)) {
        return array();
    }
    global $wpdb;
    $table = $wpdb->prefix . 'spgp_ai_tags';
    $table_escaped = esc_sql($table);
    $post_id = absint($post_id);
    $tags = $wpdb->get_col($wpdb->prepare(
        "SELECT tag_name FROM {$table_escaped} WHERE photo_id = %d ORDER BY confidence DESC",
        $post_id
    ));
    return $tags ? array_map('sanitize_text_field', $tags) : array();
}

/**
 * Get detected faces for a photo
 */
function spgp_get_faces($post_id) {
    if (!absint($post_id)) {
        return array();
    }
    global $wpdb;
    $table = $wpdb->prefix . 'spgp_faces';
    $table_escaped = esc_sql($table);
    $post_id = absint($post_id);
    $faces = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_escaped} WHERE photo_id = %d",
        $post_id
    ));
    return $faces ? $faces : array();
}

/**
 * Get all detected people
 */
function spgp_get_people() {
    global $wpdb;
    $table = $wpdb->prefix . 'spgp_faces';
    $table_escaped = esc_sql($table);
    
    // Use direct query with escaped table name (prepare doesn't support table names reliably)
    $people = $wpdb->get_results(
        "SELECT DISTINCT face_id, person_name, COUNT(*) as photo_count 
         FROM {$table_escaped} 
         WHERE person_name IS NOT NULL AND person_name != ''
         GROUP BY face_id, person_name
         ORDER BY photo_count DESC"
    );
    
    // Sanitize results
    if ($people) {
        foreach ($people as $person) {
            $person->face_id = sanitize_text_field($person->face_id);
            $person->person_name = sanitize_text_field($person->person_name);
            $person->photo_count = absint($person->photo_count);
        }
    }
    
    return $people ? $people : array();
}

/**
 * Get related photos by tag
 */
function spgp_get_related_photos($post_id, $limit = 12) {
    $tags = spgp_get_photo_tags($post_id);
    if (empty($tags)) {
        return array();
    }
    
    $args = array(
        'post_type' => 'photo_album',
        'post__not_in' => array($post_id),
        'posts_per_page' => $limit,
        'tax_query' => array(
            'relation' => 'OR',
            array(
                'taxonomy' => 'post_tag',
                'field' => 'name',
                'terms' => $tags,
            ),
        ),
    );
    
    $query = new WP_Query($args);
    return $query->posts;
}

/**
 * Get photo view count
 */
function spgp_get_view_count($post_id) {
    if (!absint($post_id)) {
        return 0;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'spgp_views';
    $table_escaped = esc_sql($table);
    $post_id = absint($post_id);
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT view_count FROM {$table_escaped} WHERE photo_id = %d",
        $post_id
    ));
    return $count ? absint($count) : 0;
}

/**
 * Increment photo view count
 */
function spgp_increment_view($post_id) {
    if (!absint($post_id)) {
        return false;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'spgp_views';
    $table_escaped = esc_sql($table);
    $post_id = absint($post_id);
    
    // Check if post exists and is published
    $post = get_post($post_id);
    if (!$post || $post->post_status !== 'publish' || $post->post_type !== 'photo_album') {
        return false;
    }
    
    $wpdb->query($wpdb->prepare(
        "INSERT INTO {$table_escaped} (photo_id, view_count, last_viewed) 
         VALUES (%d, 1, NOW()) 
         ON DUPLICATE KEY UPDATE view_count = view_count + 1, last_viewed = NOW()",
        $post_id
    ));
    
    return true;
}

/**
 * Check if user liked photo
 */
function spgp_is_liked($post_id, $user_id = null) {
    if (!absint($post_id)) {
        return false;
    }
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    if (!absint($user_id)) {
        return false;
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'spgp_likes';
    $table_escaped = esc_sql($table);
    $user_id = absint($user_id);
    $post_id = absint($post_id);
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_escaped} WHERE user_id = %d AND photo_id = %d",
        $user_id, $post_id
    ));
    return (bool) $count;
}

/**
 * Get photo like count
 */
function spgp_get_like_count($post_id) {
    if (!absint($post_id)) {
        return 0;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'spgp_likes';
    $table_escaped = esc_sql($table);
    $post_id = absint($post_id);
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_escaped} WHERE photo_id = %d",
        $post_id
    ));
    return $count ? absint($count) : 0;
}

/**
 * Check if photo is favorited
 */
function spgp_is_favorited($post_id, $user_id = null) {
    if (!absint($post_id)) {
        return false;
    }
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    if (!absint($user_id)) {
        return false;
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'spgp_favorites';
    $table_escaped = esc_sql($table);
    $user_id = absint($user_id);
    $post_id = absint($post_id);
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_escaped} WHERE user_id = %d AND photo_id = %d",
        $user_id, $post_id
    ));
    return (bool) $count;
}

/**
 * Sanitize and validate image upload
 */
function spgp_validate_image($file) {
    // Validate file array structure
    if (!isset($file['name']) || !isset($file['type']) || !isset($file['tmp_name']) || !isset($file['size']) || !isset($file['error'])) {
        return new WP_Error('invalid_file', __('Invalid file upload.', 'smart-photo-gallery-pro'));
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = array(
            UPLOAD_ERR_INI_SIZE => __('The uploaded file exceeds the upload_max_filesize directive in php.ini.', 'smart-photo-gallery-pro'),
            UPLOAD_ERR_FORM_SIZE => __('The uploaded file exceeds the MAX_FILE_SIZE directive.', 'smart-photo-gallery-pro'),
            UPLOAD_ERR_PARTIAL => __('The uploaded file was only partially uploaded.', 'smart-photo-gallery-pro'),
            UPLOAD_ERR_NO_FILE => __('No file was uploaded.', 'smart-photo-gallery-pro'),
            UPLOAD_ERR_NO_TMP_DIR => __('Missing a temporary folder.', 'smart-photo-gallery-pro'),
            UPLOAD_ERR_CANT_WRITE => __('Failed to write file to disk.', 'smart-photo-gallery-pro'),
            UPLOAD_ERR_EXTENSION => __('File upload stopped by extension.', 'smart-photo-gallery-pro'),
        );
        return new WP_Error('upload_error', isset($error_messages[$file['error']]) ? $error_messages[$file['error']] : __('File upload error.', 'smart-photo-gallery-pro'));
    }
    
    // Validate file extension
    $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp');
    $file_type = wp_check_filetype($file['name'], array(
        'jpg|jpeg|jpe' => 'image/jpeg',
        'gif' => 'image/gif',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ));
    
    if (empty($file_type['ext']) || !in_array(strtolower($file_type['ext']), $allowed_extensions)) {
        return new WP_Error('invalid_file', __('Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.', 'smart-photo-gallery-pro'));
    }
    
    // Validate MIME type
    $allowed_mime_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp');
    $mime_type = $file['type'];
    
    // Also check file extension matches MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detected_mime = $finfo && $file['tmp_name'] ? finfo_file($finfo, $file['tmp_name']) : false;
    if ($finfo) {
        finfo_close($finfo);
    }
    
    // Validate detected MIME type if available
    if ($detected_mime && !in_array($detected_mime, $allowed_mime_types)) {
        return new WP_Error('invalid_file', __('File type does not match file extension.', 'smart-photo-gallery-pro'));
    }
    
    // Check MIME type
    if (!in_array($mime_type, $allowed_mime_types)) {
        return new WP_Error('invalid_file', __('Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.', 'smart-photo-gallery-pro'));
    }
    
    // Validate file size
    $max_size = apply_filters('spgp_max_upload_size', 10 * 1024 * 1024); // 10MB default, filterable
    if ($file['size'] > $max_size) {
        return new WP_Error('file_too_large', sprintf(__('File is too large. Maximum size is %s.', 'smart-photo-gallery-pro'), size_format($max_size)));
    }
    
    // Check if file is actually an image
    if (!function_exists('getimagesize')) {
        return true; // Skip if function not available
    }
    
    $image_info = @getimagesize($file['tmp_name']);
    if ($image_info === false) {
        return new WP_Error('invalid_file', __('File is not a valid image.', 'smart-photo-gallery-pro'));
    }
    
    return true;
}

/**
 * Format file size
 */
function spgp_format_file_size($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Get share URL for social media
 */
function spgp_get_share_url($network, $url, $title = '', $image = '') {
    $url = urlencode($url);
    $title = urlencode($title);
    $image = urlencode($image);
    
    $share_urls = array(
        'facebook' => "https://www.facebook.com/sharer/sharer.php?u={$url}",
        'twitter' => "https://twitter.com/intent/tweet?url={$url}&text={$title}",
        'whatsapp' => "https://wa.me/?text={$title}%20{$url}",
        'instagram' => "https://www.instagram.com/", // Instagram doesn't support direct sharing
        'linkedin' => "https://www.linkedin.com/sharing/share-offsite/?url={$url}",
        'pinterest' => "https://pinterest.com/pin/create/button/?url={$url}&media={$image}&description={$title}",
    );
    
    return isset($share_urls[$network]) ? $share_urls[$network] : $url;
}

/**
 * Generate nonce for AJAX requests
 */
function spgp_get_nonce() {
    return wp_create_nonce('spgp_ajax_nonce');
}
