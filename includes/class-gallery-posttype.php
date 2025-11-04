<?php
/**
 * Custom Post Type and Taxonomies
 */

if (!defined('ABSPATH')) {
    exit;
}

class SPGP_Gallery_PostType {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Register post type and taxonomies on init hook
        add_action('init', array($this, 'register_post_type'), 5);
        add_action('init', array($this, 'register_taxonomies'), 5);
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
    }
    
    /**
     * Register custom post type
     */
    public function register_post_type() {
        $labels = array(
            'name' => __('Photos', 'smart-photo-gallery-pro'),
            'singular_name' => __('Photo', 'smart-photo-gallery-pro'),
            'menu_name' => __('HH CJ Google Photos', 'smart-photo-gallery-pro'),
            'add_new' => __('Add New Photo', 'smart-photo-gallery-pro'),
            'add_new_item' => __('Add New Photo', 'smart-photo-gallery-pro'),
            'edit_item' => __('Edit Photo', 'smart-photo-gallery-pro'),
            'new_item' => __('New Photo', 'smart-photo-gallery-pro'),
            'view_item' => __('View Photo', 'smart-photo-gallery-pro'),
            'search_items' => __('Search Photos', 'smart-photo-gallery-pro'),
            'not_found' => __('No photos found', 'smart-photo-gallery-pro'),
            'not_found_in_trash' => __('No photos found in Trash', 'smart-photo-gallery-pro'),
        );
        
        $args = array(
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => false, // We'll add it to our custom menu
            'query_var' => true,
            'rewrite' => array('slug' => 'photo'),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => null,
            'menu_icon' => 'dashicons-format-gallery',
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'comments', 'author'),
            'show_in_rest' => true,
        );
        
        register_post_type('photo_album', $args);
    }
    
    /**
     * Register taxonomies
     */
    public function register_taxonomies() {
        // Album Category
        $category_labels = array(
            'name' => __('Album Categories', 'smart-photo-gallery-pro'),
            'singular_name' => __('Album Category', 'smart-photo-gallery-pro'),
            'search_items' => __('Search Categories', 'smart-photo-gallery-pro'),
            'all_items' => __('All Categories', 'smart-photo-gallery-pro'),
            'parent_item' => __('Parent Category', 'smart-photo-gallery-pro'),
            'parent_item_colon' => __('Parent Category:', 'smart-photo-gallery-pro'),
            'edit_item' => __('Edit Category', 'smart-photo-gallery-pro'),
            'update_item' => __('Update Category', 'smart-photo-gallery-pro'),
            'add_new_item' => __('Add New Category', 'smart-photo-gallery-pro'),
            'new_item_name' => __('New Category Name', 'smart-photo-gallery-pro'),
            'menu_name' => __('Categories', 'smart-photo-gallery-pro'),
        );
        
        register_taxonomy('album_category', array('photo_album'), array(
            'hierarchical' => true,
            'labels' => $category_labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'photo-category'),
            'show_in_rest' => true,
        ));
        
        // Default categories
        $default_categories = array('Events', 'People', 'Travels', 'Birthdays', 'Location');
        foreach ($default_categories as $cat) {
            if (!term_exists($cat, 'album_category')) {
                wp_insert_term($cat, 'album_category');
            }
        }
    }
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'spgp_photo_details',
            __('Photo Details', 'smart-photo-gallery-pro'),
            array($this, 'photo_details_callback'),
            'photo_album',
            'normal',
            'high'
        );
        
        add_meta_box(
            'spgp_photo_upload',
            __('Photo Upload', 'smart-photo-gallery-pro'),
            array($this, 'photo_upload_callback'),
            'photo_album',
            'side',
            'default'
        );
        
        if (spgp_is_feature_enabled('face_detection')) {
            add_meta_box(
                'spgp_detected_faces',
                __('Detected Faces', 'smart-photo-gallery-pro'),
                array($this, 'detected_faces_callback'),
                'photo_album',
                'side',
                'default'
            );
        }
    }
    
    /**
     * Photo details meta box
     */
    public function photo_details_callback($post) {
        wp_nonce_field('spgp_photo_details', 'spgp_photo_details_nonce');
        
        $event_date = get_post_meta($post->ID, '_spgp_event_date', true);
        $event_date_end = get_post_meta($post->ID, '_spgp_event_date_end', true);
        $location = get_post_meta($post->ID, '_spgp_location', true);
        $photographer = get_post_meta($post->ID, '_spgp_photographer', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="spgp_event_date"><?php _e('Event Date Start', 'smart-photo-gallery-pro'); ?></label></th>
                <td>
                    <input type="date" id="spgp_event_date" name="spgp_event_date" value="<?php echo esc_attr($event_date); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="spgp_event_date_end"><?php _e('Event Date End', 'smart-photo-gallery-pro'); ?></label></th>
                <td>
                    <input type="date" id="spgp_event_date_end" name="spgp_event_date_end" value="<?php echo esc_attr($event_date_end); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="spgp_location"><?php _e('Location', 'smart-photo-gallery-pro'); ?></label></th>
                <td>
                    <input type="text" id="spgp_location" name="spgp_location" value="<?php echo esc_attr($location); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="spgp_photographer"><?php _e('Photographer', 'smart-photo-gallery-pro'); ?></label></th>
                <td>
                    <input type="text" id="spgp_photographer" name="spgp_photographer" value="<?php echo esc_attr($photographer); ?>" class="regular-text" />
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Photo upload meta box
     */
    public function photo_upload_callback($post) {
        $attachment_id = get_post_meta($post->ID, '_spgp_photo_attachment_id', true);
        ?>
        <div class="spgp-photo-upload">
            <?php if ($attachment_id) : 
                $image = wp_get_attachment_image_src($attachment_id, 'medium');
            ?>
                <div class="spgp-current-image">
                    <img src="<?php echo esc_url($image[0]); ?>" alt="" style="max-width: 100%; height: auto;" />
                    <p>
                        <input type="hidden" name="spgp_photo_attachment_id" id="spgp_photo_attachment_id" value="<?php echo esc_attr($attachment_id); ?>" />
                        <button type="button" class="button spgp-remove-image"><?php _e('Remove Image', 'smart-photo-gallery-pro'); ?></button>
                    </p>
                </div>
            <?php else : ?>
                <div class="spgp-no-image">
                    <button type="button" class="button spgp-upload-image"><?php _e('Upload Photo', 'smart-photo-gallery-pro'); ?></button>
                    <input type="hidden" name="spgp_photo_attachment_id" id="spgp_photo_attachment_id" value="" />
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var frame;
            $('.spgp-upload-image').on('click', function(e) {
                e.preventDefault();
                if (frame) {
                    frame.open();
                    return;
                }
                frame = wp.media({
                    title: '<?php _e('Select Photo', 'smart-photo-gallery-pro'); ?>',
                    button: { text: '<?php _e('Use this photo', 'smart-photo-gallery-pro'); ?>' },
                    multiple: false
                });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#spgp_photo_attachment_id').val(attachment.id);
                    $('.spgp-no-image').html('<img src="' + attachment.url + '" style="max-width: 100%;" /><p><button type="button" class="button spgp-remove-image"><?php _e('Remove Image', 'smart-photo-gallery-pro'); ?></button></p>').removeClass('spgp-no-image').addClass('spgp-current-image');
                });
                frame.open();
            });
            $(document).on('click', '.spgp-remove-image', function(e) {
                e.preventDefault();
                $('#spgp_photo_attachment_id').val('');
                $('.spgp-current-image').html('<button type="button" class="button spgp-upload-image"><?php _e('Upload Photo', 'smart-photo-gallery-pro'); ?></button>').removeClass('spgp-current-image').addClass('spgp-no-image');
            });
        });
        </script>
        <?php
    }
    
    /**
     * Detected faces meta box
     */
    public function detected_faces_callback($post) {
        $faces = spgp_get_faces($post->ID);
        if (empty($faces)) {
            echo '<p>' . __('No faces detected yet.', 'smart-photo-gallery-pro') . '</p>';
            return;
        }
        ?>
        <div class="spgp-detected-faces">
            <?php foreach ($faces as $face) : ?>
                <div class="spgp-face-item" data-face-id="<?php echo esc_attr($face->face_id); ?>">
                    <p>
                        <strong><?php _e('Face ID:', 'smart-photo-gallery-pro'); ?></strong> <?php echo esc_html($face->face_id); ?><br>
                        <label>
                            <?php _e('Person Name:', 'smart-photo-gallery-pro'); ?>
                            <input type="text" class="spgp-person-name" value="<?php echo esc_attr($face->person_name); ?>" data-face-id="<?php echo esc_attr($face->face_id); ?>" />
                        </label>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Save meta boxes
     */
    public function save_meta_boxes($post_id) {
        // Check nonce
        if (!isset($_POST['spgp_photo_details_nonce']) || !wp_verify_nonce($_POST['spgp_photo_details_nonce'], 'spgp_photo_details')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save photo attachment ID
        if (isset($_POST['spgp_photo_attachment_id'])) {
            update_post_meta($post_id, '_spgp_photo_attachment_id', intval($_POST['spgp_photo_attachment_id']));
        }
        
        // Save event dates
        if (isset($_POST['spgp_event_date'])) {
            update_post_meta($post_id, '_spgp_event_date', sanitize_text_field($_POST['spgp_event_date']));
        }
        
        if (isset($_POST['spgp_event_date_end'])) {
            update_post_meta($post_id, '_spgp_event_date_end', sanitize_text_field($_POST['spgp_event_date_end']));
        }
        
        // Save location
        if (isset($_POST['spgp_location'])) {
            update_post_meta($post_id, '_spgp_location', sanitize_text_field($_POST['spgp_location']));
        }
        
        // Save photographer
        if (isset($_POST['spgp_photographer'])) {
            update_post_meta($post_id, '_spgp_photographer', sanitize_text_field($_POST['spgp_photographer']));
        }
        
        // Trigger AI processing if enabled
        if (spgp_is_feature_enabled('ai_tagging') && isset($_POST['spgp_photo_attachment_id'])) {
            $attachment_id = intval($_POST['spgp_photo_attachment_id']);
            if ($attachment_id) {
                // Queue AI processing (async)
                do_action('spgp_process_photo_ai', $post_id, $attachment_id);
            }
        }
    }
}
