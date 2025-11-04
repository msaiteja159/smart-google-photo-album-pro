<?php
/**
 * AI Integration - Google Vision API / AWS Rekognition
 */

if (!defined('ABSPATH')) {
    exit;
}

class SPGP_Gallery_AI {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('spgp_process_photo_ai', array($this, 'process_photo_async'), 10, 2);
        add_action('wp_ajax_spgp_process_photo_ai', array($this, 'process_photo'));
        add_action('add_attachment', array($this, 'auto_process_on_upload'), 10, 1);
    }
    
    /**
     * Process photo asynchronously
     */
    public function process_photo_async($post_id, $attachment_id) {
        // Schedule background processing
        wp_schedule_single_event(time() + 5, 'spgp_process_photo_ai', array($post_id, $attachment_id));
    }
    
    /**
     * Process photo with AI
     */
    public function process_photo($post_id = null, $attachment_id = null) {
        if (!$post_id && isset($_POST['post_id'])) {
            check_ajax_referer('spgp_ajax_nonce', 'nonce');
            $post_id = intval($_POST['post_id']);
        }
        
        if (!$attachment_id && isset($_POST['attachment_id'])) {
            $attachment_id = intval($_POST['attachment_id']);
        }
        
        if (!$attachment_id) {
            $attachment_id = get_post_meta($post_id, '_spgp_photo_attachment_id', true);
        }
        
        if (!$attachment_id) {
            return false;
        }
        
        $file_path = get_attached_file($attachment_id);
        if (!file_exists($file_path)) {
            return false;
        }
        
        $provider = spgp_get_setting('api_provider', 'google');
        
        if ($provider === 'google') {
            $this->process_with_google_vision($post_id, $attachment_id, $file_path);
        } elseif ($provider === 'aws') {
            $this->process_with_aws_rekognition($post_id, $attachment_id, $file_path);
        }
        
        return true;
    }
    
    /**
     * Process with Google Vision API
     */
    private function process_with_google_vision($post_id, $attachment_id, $file_path) {
        $api_key = spgp_get_setting('google_vision_api_key', '');
        if (empty($api_key)) {
            return false;
        }
        
        // Read image file
        $image_data = base64_encode(file_get_contents($file_path));
        
        // Prepare request
        $requests = array();
        
        // Label detection
        if (spgp_is_feature_enabled('ai_tagging')) {
            $requests[] = array(
                'image' => array('content' => $image_data),
                'features' => array(
                    array('type' => 'LABEL_DETECTION', 'maxResults' => 20),
                ),
            );
        }
        
        // Face detection
        if (spgp_is_feature_enabled('face_detection')) {
            $requests[] = array(
                'image' => array('content' => $image_data),
                'features' => array(
                    array('type' => 'FACE_DETECTION', 'maxResults' => 10),
                ),
            );
        }
        
        if (empty($requests)) {
            return false;
        }
        
        $body = json_encode(array('requests' => $requests));
        
        // Make API call
        $response = wp_remote_post('https://vision.googleapis.com/v1/images:annotate?key=' . $api_key, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => $body,
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            error_log('Google Vision API Error: ' . $response->get_error_message());
            return false;
        }
        
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($response_body['responses'])) {
            foreach ($response_body['responses'] as $response_item) {
                // Process labels
                if (isset($response_item['labelAnnotations'])) {
                    $this->save_labels($post_id, $response_item['labelAnnotations']);
                }
                
                // Process faces
                if (isset($response_item['faceAnnotations'])) {
                    $this->save_faces($post_id, $attachment_id, $response_item['faceAnnotations']);
                }
            }
        }
        
        return true;
    }
    
    /**
     * Process with AWS Rekognition
     */
    private function process_with_aws_rekognition($post_id, $attachment_id, $file_path) {
        $access_key = spgp_get_setting('aws_access_key', '');
        $secret_key = spgp_get_setting('aws_secret_key', '');
        $region = spgp_get_setting('aws_region', 'us-east-1');
        
        if (empty($access_key) || empty($secret_key)) {
            return false;
        }
        
        // Note: This is a simplified version. In production, use AWS SDK
        $image_data = base64_encode(file_get_contents($file_path));
        
        // Detect labels
        if (spgp_is_feature_enabled('ai_tagging')) {
            $this->aws_detect_labels($post_id, $image_data, $access_key, $secret_key, $region);
        }
        
        // Detect faces
        if (spgp_is_feature_enabled('face_detection')) {
            $this->aws_detect_faces($post_id, $attachment_id, $image_data, $access_key, $secret_key, $region);
        }
        
        return true;
    }
    
    /**
     * AWS Detect Labels (simplified - use AWS SDK in production)
     */
    private function aws_detect_labels($post_id, $image_data, $access_key, $secret_key, $region) {
        // This is a placeholder. In production, use AWS SDK for PHP:
        // use Aws\Rekognition\RekognitionClient;
        // $client = new RekognitionClient(['version' => 'latest', 'region' => $region, 'credentials' => ['key' => $access_key, 'secret' => $secret_key]]);
        // $result = $client->detectLabels(['Image' => ['Bytes' => base64_decode($image_data)], 'MaxLabels' => 20]);
        
        // For now, we'll just log that AWS is selected
        error_log('AWS Rekognition: Labels detection would run here (requires AWS SDK)');
    }
    
    /**
     * AWS Detect Faces (simplified - use AWS SDK in production)
     */
    private function aws_detect_faces($post_id, $attachment_id, $image_data, $access_key, $secret_key, $region) {
        // This is a placeholder. In production, use AWS SDK for PHP:
        // $result = $client->detectFaces(['Image' => ['Bytes' => base64_decode($image_data)], 'Attributes' => ['ALL']]);
        
        // For now, we'll just log that AWS is selected
        error_log('AWS Rekognition: Face detection would run here (requires AWS SDK)');
    }
    
    /**
     * Save labels/tags to database
     */
    private function save_labels($post_id, $labels) {
        global $wpdb;
        $table = $wpdb->prefix . 'spgp_ai_tags';
        
        foreach ($labels as $label) {
            $tag_name = sanitize_text_field($label['description']);
            $confidence = isset($label['score']) ? floatval($label['score']) * 100 : 0;
            
            // Only save tags with confidence > 60%
            if ($confidence < 60) {
                continue;
            }
            
            $wpdb->insert(
                $table,
                array(
                    'photo_id' => $post_id,
                    'tag_name' => $tag_name,
                    'tag_type' => 'label',
                    'confidence' => $confidence,
                ),
                array('%d', '%s', '%s', '%f')
            );
            
            // Also add as WordPress tag for search compatibility
            wp_set_post_tags($post_id, $tag_name, true);
        }
    }
    
    /**
     * Save faces to database
     */
    private function save_faces($post_id, $attachment_id, $faces) {
        global $wpdb;
        $table = $wpdb->prefix . 'spgp_faces';
        
        foreach ($faces as $index => $face) {
            $face_id = 'face_' . $post_id . '_' . $attachment_id . '_' . $index;
            
            // Get bounding box
            $bounding_box = '';
            if (isset($face['boundingPoly']['vertices'])) {
                $bounding_box = json_encode($face['boundingPoly']['vertices']);
            }
            
            $confidence = isset($face['detectionConfidence']) ? floatval($face['detectionConfidence']) * 100 : 0;
            
            $wpdb->insert(
                $table,
                array(
                    'photo_id' => $post_id,
                    'face_id' => $face_id,
                    'person_name' => null,
                    'bounding_box' => $bounding_box,
                    'confidence' => $confidence,
                ),
                array('%d', '%s', '%s', '%s', '%f')
            );
            
            // Try to match with existing faces (grouping)
            $this->group_faces($face_id, $post_id, $attachment_id);
        }
        
        // Update person category
        $this->update_person_category($post_id);
    }
    
    /**
     * Group similar faces (simplified - use face matching API in production)
     */
    private function group_faces($face_id, $post_id, $attachment_id) {
        // In production, use face matching API to group similar faces
        // For now, we'll create unique face IDs for each detection
        // Google Vision API doesn't have built-in face grouping, but AWS Rekognition does
    }
    
    /**
     * Update person category taxonomy
     */
    private function update_person_category($post_id) {
        $faces = spgp_get_faces($post_id);
        if (empty($faces)) {
            return;
        }
        
        // Ensure "People" category exists
        $people_term = get_term_by('slug', 'people', 'album_category');
        if (!$people_term) {
            $people_term = wp_insert_term('People', 'album_category', array('slug' => 'people'));
            if (!is_wp_error($people_term)) {
                $people_term_id = $people_term['term_id'];
            }
        } else {
            $people_term_id = $people_term->term_id;
        }
        
        if ($people_term_id) {
            wp_set_post_terms($post_id, array($people_term_id), 'album_category', true);
        }
    }
    
    /**
     * Auto process on attachment upload
     */
    public function auto_process_on_upload($attachment_id) {
        // Only process if it's attached to a photo_album post
        $post_id = wp_get_post_parent_id($attachment_id);
        if ($post_id && get_post_type($post_id) === 'photo_album') {
            if (spgp_is_feature_enabled('ai_tagging') || spgp_is_feature_enabled('face_detection')) {
                $this->process_photo_async($post_id, $attachment_id);
            }
        }
    }
    
    /**
     * Get similar photos using cosine similarity (simplified)
     */
    public function get_similar_photos($post_id, $limit = 12) {
        if (!absint($post_id)) {
            return array();
        }
        
        $post_id = absint($post_id);
        $limit = absint($limit);
        $limit = min($limit, 50); // Prevent abuse
        
        $tags = spgp_get_ai_tags($post_id);
        if (empty($tags)) {
            return array();
        }
        
        // Sanitize tags
        $tags = array_map('sanitize_text_field', $tags);
        
        // Get photos with similar tags
        global $wpdb;
        $table = $wpdb->prefix . 'spgp_ai_tags';
        $table_escaped = esc_sql($table);
        
        // Build safe query
        $placeholders = array_fill(0, count($tags), '%s');
        $tag_placeholders = implode(',', $placeholders);
        
        $similar = $wpdb->get_results($wpdb->prepare(
            "SELECT photo_id, COUNT(*) as match_count 
             FROM {$table_escaped} 
             WHERE photo_id != %d AND tag_name IN ($tag_placeholders)
             GROUP BY photo_id
             ORDER BY match_count DESC
             LIMIT %d",
            array_merge(array($post_id), $tags, array($limit))
        ));
        
        if (empty($similar)) {
            return array();
        }
        
        $post_ids = array_map('absint', wp_list_pluck($similar, 'photo_id'));
        if (empty($post_ids)) {
            return array();
        }
        
        return get_posts(array(
            'post_type' => 'photo_album',
            'post_status' => 'publish', // Only published posts
            'post__in' => $post_ids,
            'posts_per_page' => $limit,
            'orderby' => 'post__in',
        ));
    }
}
