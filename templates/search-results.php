<?php
/**
 * Search Results Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$categories = get_terms(array(
    'taxonomy' => 'album_category',
    'hide_empty' => false,
));
?>

<div class="spgp-search-container">
    <form class="spgp-search-form">
        <div class="spgp-search-field">
            <label for="spgp-search-input"><?php _e('Search', 'smart-photo-gallery-pro'); ?></label>
            <input type="text" id="spgp-search-input" class="spgp-search-input" name="q" placeholder="<?php esc_attr_e('Search photos, tags, events...', 'smart-photo-gallery-pro'); ?>" value="<?php echo isset($_GET['q']) ? esc_attr($_GET['q']) : ''; ?>" />
        </div>
        
        <div class="spgp-search-field">
            <label for="spgp-category-select"><?php _e('Category', 'smart-photo-gallery-pro'); ?></label>
            <select id="spgp-category-select" class="spgp-category-select" name="category">
                <option value=""><?php _e('All Categories', 'smart-photo-gallery-pro'); ?></option>
                <?php foreach ($categories as $cat) : ?>
                    <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected(isset($_GET['category']) ? intval($_GET['category']) : 0, $cat->term_id); ?>>
                        <?php echo esc_html($cat->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="spgp-search-field">
            <label for="spgp-date-from"><?php _e('Date From', 'smart-photo-gallery-pro'); ?></label>
            <input type="date" id="spgp-date-from" class="spgp-date-from" name="date_from" value="<?php echo isset($_GET['date_from']) ? esc_attr($_GET['date_from']) : ''; ?>" />
        </div>
        
        <div class="spgp-search-field">
            <label for="spgp-date-to"><?php _e('Date To', 'smart-photo-gallery-pro'); ?></label>
            <input type="date" id="spgp-date-to" class="spgp-date-to" name="date_to" value="<?php echo isset($_GET['date_to']) ? esc_attr($_GET['date_to']) : ''; ?>" />
        </div>
        
        <div class="spgp-search-field">
            <label for="spgp-tags-input"><?php _e('Tags', 'smart-photo-gallery-pro'); ?></label>
            <input type="text" id="spgp-tags-input" class="spgp-tags-input" name="tags" placeholder="<?php esc_attr_e('Separate tags with commas', 'smart-photo-gallery-pro'); ?>" value="<?php echo isset($_GET['tags']) ? esc_attr($_GET['tags']) : ''; ?>" />
        </div>
        
        <div class="spgp-search-field">
            <button type="submit" class="spgp-search-button"><?php _e('Search', 'smart-photo-gallery-pro'); ?></button>
        </div>
    </form>
</div>

<div class="spgp-gallery-wrapper">
    <div class="spgp-gallery-masonry" data-columns="3"></div>
</div>
