<?php
/**
 * Search Functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class SPGP_Gallery_Search {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_spgp_search_photos', array($this, 'ajax_search'));
        add_action('wp_ajax_nopriv_spgp_search_photos', array($this, 'ajax_search'));
    }
    
    /**
     * AJAX search handler
     */
    public function ajax_search() {
        // Optional nonce verification for public searches
        if (isset($_GET['nonce']) && !wp_verify_nonce($_GET['nonce'], 'spgp_ajax_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'smart-photo-gallery-pro')));
            return;
        }
        
        $query = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        $category = isset($_GET['category']) ? absint($_GET['category']) : 0;
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        $tags = isset($_GET['tags']) ? sanitize_text_field(wp_unslash($_GET['tags'])) : '';
        $page = isset($_GET['page']) ? absint($_GET['page']) : 1;
        $per_page = absint(spgp_get_setting('items_per_page', 20));
        
        // Validate dates
        if (!empty($date_from) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
            $date_from = '';
        }
        if (!empty($date_to) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
            $date_to = '';
        }
        
        // Limit per_page to prevent abuse
        $per_page = min($per_page, 100);
        
        $results = $this->search_photos($query, $category, $date_from, $date_to, $tags, $page, $per_page);
        
        wp_send_json_success($results);
    }
    
    /**
     * Search photos
     */
    public function search_photos($query = '', $category = 0, $date_from = '', $date_to = '', $tags = '', $page = 1, $per_page = 20) {
        $args = array(
            'post_type' => 'photo_album',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC',
        );
        
        // Text search
        if (!empty($query)) {
            $args['s'] = $query;
            
            // Also search in AI tags
            global $wpdb;
            $ai_table = $wpdb->prefix . 'spgp_ai_tags';
            $ai_tag_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT photo_id FROM $ai_table WHERE tag_name LIKE %s",
                '%' . $wpdb->esc_like($query) . '%'
            ));
            
            if (!empty($ai_tag_ids)) {
                if (isset($args['post__in'])) {
                    $args['post__in'] = array_merge($args['post__in'], $ai_tag_ids);
                } else {
                    $args['post__in'] = $ai_tag_ids;
                }
            }
        }
        
        // Category filter
        if ($category > 0) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'album_category',
                    'field' => 'term_id',
                    'terms' => $category,
                ),
            );
        }
        
        // Date range filter
        if (!empty($date_from) || !empty($date_to)) {
            $meta_query = array('relation' => 'OR');
            
            if (!empty($date_from) && !empty($date_to)) {
                $meta_query[] = array(
                    'key' => '_spgp_event_date',
                    'value' => array($date_from, $date_to),
                    'compare' => 'BETWEEN',
                    'type' => 'DATE',
                );
                $meta_query[] = array(
                    'key' => '_spgp_event_date_end',
                    'value' => array($date_from, $date_to),
                    'compare' => 'BETWEEN',
                    'type' => 'DATE',
                );
            } elseif (!empty($date_from)) {
                $meta_query[] = array(
                    'key' => '_spgp_event_date',
                    'value' => $date_from,
                    'compare' => '>=',
                    'type' => 'DATE',
                );
            } elseif (!empty($date_to)) {
                $meta_query[] = array(
                    'key' => '_spgp_event_date',
                    'value' => $date_to,
                    'compare' => '<=',
                    'type' => 'DATE',
                );
            }
            
            if (isset($args['meta_query'])) {
                $args['meta_query'] = array_merge($args['meta_query'], $meta_query);
            } else {
                $args['meta_query'] = $meta_query;
            }
        }
        
        // Tags filter
        if (!empty($tags)) {
            $tag_array = is_array($tags) ? $tags : explode(',', $tags);
            $tag_array = array_map('trim', $tag_array);
            
            if (!isset($args['tax_query'])) {
                $args['tax_query'] = array('relation' => 'AND');
            }
            
            $args['tax_query'][] = array(
                'taxonomy' => 'post_tag',
                'field' => 'name',
                'terms' => $tag_array,
                'operator' => 'IN',
            );
        }
        
        // Execute query
        $search_query = new WP_Query($args);
        
        $photos = array();
        if ($search_query->have_posts()) {
            while ($search_query->have_posts()) {
                $search_query->the_post();
                $post_id = get_the_ID();
                
                $photos[] = array(
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'url' => get_permalink(),
                    'thumbnail' => spgp_get_photo_thumb($post_id, 'medium'),
                    'image' => spgp_get_photo_url($post_id, 'large'),
                    'categories' => wp_get_post_terms($post_id, 'album_category', array('fields' => 'names')),
                    'tags' => spgp_get_photo_tags($post_id),
                    'date' => get_the_date(),
                    'event_date' => get_post_meta($post_id, '_spgp_event_date', true),
                    'views' => spgp_get_view_count($post_id),
                    'likes' => spgp_get_like_count($post_id),
                );
            }
        }
        wp_reset_postdata();
        
        return array(
            'photos' => $photos,
            'total' => $search_query->found_posts,
            'pages' => $search_query->max_num_pages,
            'current_page' => $page,
        );
    }
}
