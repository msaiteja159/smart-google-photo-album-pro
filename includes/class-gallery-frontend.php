<?php
/**
 * Frontend Display
 */

if (!defined('ABSPATH')) {
    exit;
}

class SPGP_Gallery_Frontend {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('smart_gallery', array($this, 'gallery_shortcode'));
        add_shortcode('smart_upload', array($this, 'upload_shortcode'));
        add_shortcode('smart_search', array($this, 'search_shortcode'));
        add_action('template_redirect', array($this, 'track_photo_view'));
        add_filter('single_template', array($this, 'single_photo_template'));
        add_filter('archive_template', array($this, 'archive_template'));
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        // Styles
        wp_enqueue_style('spgp-gallery-css', SPGP_PLUGIN_URL . 'assets/css/gallery.css', array(), SPGP_VERSION);
        
        // Scripts
        wp_enqueue_script('masonry', 'https://unpkg.com/masonry-layout@4/dist/masonry.pkgd.min.js', array(), '4.2.2', true);
        wp_enqueue_script('lightgallery', 'https://cdn.jsdelivr.net/npm/lightgallery@2/dist/js/lightgallery.min.js', array(), '2.7.0', true);
        wp_enqueue_script('lightgallery-thumbnail', 'https://cdn.jsdelivr.net/npm/lightgallery@2/plugins/thumbnail/lg-thumbnail.min.js', array('lightgallery'), '2.7.0', true);
        wp_enqueue_script('spgp-gallery-js', SPGP_PLUGIN_URL . 'assets/js/gallery.js', array('jquery', 'masonry', 'lightgallery'), SPGP_VERSION, true);
        
        // Localize script
        wp_localize_script('spgp-gallery-js', 'spgpData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => spgp_get_nonce(),
            'uploadNonce' => wp_create_nonce('spgp_upload_nonce'),
            'pluginUrl' => SPGP_PLUGIN_URL,
            'itemsPerPage' => spgp_get_setting('items_per_page', 20),
            'infiniteScroll' => spgp_get_setting('enable_infinite_scroll', 1),
            'darkMode' => spgp_get_setting('enable_dark_mode', 0),
            'enableFavorites' => spgp_get_setting('enable_favorites', 1),
            'enableLikes' => spgp_get_setting('enable_likes', 1),
            'strings' => array(
                'loading' => __('Loading...', 'smart-photo-gallery-pro'),
                'loadMore' => __('Load More', 'smart-photo-gallery-pro'),
                'noMore' => __('No more photos', 'smart-photo-gallery-pro'),
                'share' => __('Share', 'smart-photo-gallery-pro'),
                'download' => __('Download', 'smart-photo-gallery-pro'),
            ),
        ));
        
        // LightGallery CSS
        wp_enqueue_style('lightgallery-css', 'https://cdn.jsdelivr.net/npm/lightgallery@2/dist/css/lightgallery.min.css', array(), '2.7.0');
        wp_enqueue_style('lightgallery-thumbnail-css', 'https://cdn.jsdelivr.net/npm/lightgallery@2/plugins/thumbnail/lg-thumbnail.min.css', array('lightgallery-css'), '2.7.0');
    }
    
    /**
     * Gallery shortcode
     */
    public function gallery_shortcode($atts) {
        $atts = shortcode_atts(array(
            'category' => '',
            'tags' => '',
            'columns' => '3',
            'layout' => '',
            'sort' => 'newest',
            'limit' => '',
            'show_all' => 'yes', // Show all photos by default on frontpage
            'category_wise' => 'yes', // Show category-wise albums
        ), $atts, 'smart_gallery');
        
        $layout = $atts['layout'] ?: spgp_get_setting('gallery_layout', 'masonry');
        
        ob_start();
        // Use category-wise template if enabled
        if ($atts['category_wise'] === 'yes') {
            $category_template = SPGP_PLUGIN_DIR . 'templates/gallery-grid-v2.php';
            if (file_exists($category_template)) {
                include $category_template;
            } else {
                include SPGP_PLUGIN_DIR . 'templates/gallery-grid.php';
            }
        } else {
            include SPGP_PLUGIN_DIR . 'templates/gallery-grid.php';
        }
        return ob_get_clean();
    }
    
    /**
     * Upload shortcode
     */
    public function upload_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to upload photos.', 'smart-photo-gallery-pro') . '</p>';
        }
        
        if (!spgp_is_feature_enabled('user_uploads')) {
            return '<p>' . __('User uploads are disabled.', 'smart-photo-gallery-pro') . '</p>';
        }
        
        ob_start();
        include SPGP_PLUGIN_DIR . 'templates/upload-form.php';
        return ob_get_clean();
    }
    
    /**
     * Search shortcode
     */
    public function search_shortcode($atts) {
        ob_start();
        include SPGP_PLUGIN_DIR . 'templates/search-results.php';
        return ob_get_clean();
    }
    
    /**
     * Track photo view
     */
    public function track_photo_view() {
        if (is_singular('photo_album') && spgp_get_setting('enable_views', 1)) {
            $post_id = get_queried_object_id();
            if ($post_id) {
                spgp_increment_view($post_id);
            }
        }
    }
    
    /**
     * Custom single photo template
     */
    public function single_photo_template($template) {
        global $post;
        if (isset($post->post_type) && $post->post_type === 'photo_album') {
            // Try overlay template first
            $overlay_template = SPGP_PLUGIN_DIR . 'templates/single-photo-overlay.php';
            if (file_exists($overlay_template)) {
                return $overlay_template;
            }
            // Fallback to regular template
            $custom_template = SPGP_PLUGIN_DIR . 'templates/single-photo.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        return $template;
    }
    
    /**
     * Custom archive template
     */
    public function archive_template($template) {
        if (is_post_type_archive('photo_album') || is_tax('album_category')) {
            $custom_template = SPGP_PLUGIN_DIR . 'templates/archive-photo.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        return $template;
    }
}
