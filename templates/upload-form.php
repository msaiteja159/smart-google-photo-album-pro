<?php
/**
 * Upload Form Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$categories = get_terms(array(
    'taxonomy' => 'album_category',
    'hide_empty' => false,
));
?>

<div class="spgp-upload-form-wrapper">
    <form class="spgp-upload-form" enctype="multipart/form-data">
        <h2><?php _e('Upload Photo', 'smart-photo-gallery-pro'); ?></h2>
        
        <div class="spgp-upload-field">
            <label for="spgp-photo-file"><?php _e('Photo *', 'smart-photo-gallery-pro'); ?></label>
            <input type="file" id="spgp-photo-file" name="photo" accept="image/*" required />
            <div class="spgp-upload-preview"></div>
        </div>
        
        <div class="spgp-upload-field">
            <label for="spgp-photo-title"><?php _e('Photo Title *', 'smart-photo-gallery-pro'); ?></label>
            <input type="text" id="spgp-photo-title" name="title" required />
        </div>
        
        <div class="spgp-upload-field">
            <label for="spgp-photo-description"><?php _e('Description', 'smart-photo-gallery-pro'); ?></label>
            <textarea id="spgp-photo-description" name="description" rows="5"></textarea>
        </div>
        
        <div class="spgp-upload-field">
            <label for="spgp-photo-category"><?php _e('Category', 'smart-photo-gallery-pro'); ?></label>
            <select id="spgp-photo-category" name="category[]" multiple>
                <?php foreach ($categories as $cat) : ?>
                    <option value="<?php echo esc_attr($cat->term_id); ?>"><?php echo esc_html($cat->name); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php _e('Hold Ctrl/Cmd to select multiple categories', 'smart-photo-gallery-pro'); ?></p>
        </div>
        
        <div class="spgp-upload-field">
            <label for="spgp-photo-tags"><?php _e('Tags', 'smart-photo-gallery-pro'); ?></label>
            <input type="text" id="spgp-photo-tags" name="tags" placeholder="<?php esc_attr_e('Separate tags with commas', 'smart-photo-gallery-pro'); ?>" />
        </div>
        
        <div class="spgp-upload-field">
            <label for="spgp-event-date"><?php _e('Event Date Start', 'smart-photo-gallery-pro'); ?></label>
            <input type="date" id="spgp-event-date" name="event_date" />
        </div>
        
        <div class="spgp-upload-field">
            <label for="spgp-event-date-end"><?php _e('Event Date End', 'smart-photo-gallery-pro'); ?></label>
            <input type="date" id="spgp-event-date-end" name="event_date_end" />
        </div>
        
        <div class="spgp-upload-field">
            <label for="spgp-location"><?php _e('Location', 'smart-photo-gallery-pro'); ?></label>
            <input type="text" id="spgp-location" name="location" placeholder="<?php esc_attr_e('e.g., New York, USA', 'smart-photo-gallery-pro'); ?>" />
        </div>
        
        <div class="spgp-upload-field">
            <button type="submit" class="spgp-upload-submit"><?php _e('Upload Photo', 'smart-photo-gallery-pro'); ?></button>
        </div>
        
        <p class="spgp-upload-notice">
            <?php 
            if (spgp_get_setting('moderate_uploads', 1)) {
                _e('Your photo will be reviewed by an administrator before being published.', 'smart-photo-gallery-pro');
            } else {
                _e('Your photo will be published immediately after upload.', 'smart-photo-gallery-pro');
            }
            ?>
        </p>
    </form>
</div>
