<?php
/**
 * Single Photo Template
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) : the_post();
    $post_id = get_the_ID();
    $image_url = spgp_get_photo_url($post_id, 'full');
    $thumb = spgp_get_photo_thumb($post_id, 'large');
    $categories = spgp_get_photo_categories($post_id);
    $tags = spgp_get_photo_tags($post_id);
    $event_date = get_post_meta($post_id, '_spgp_event_date', true);
    $event_date_end = get_post_meta($post_id, '_spgp_event_date_end', true);
    $location = get_post_meta($post_id, '_spgp_location', true);
    $photographer = get_post_meta($post_id, '_spgp_photographer', true);
    $views = spgp_get_view_count($post_id);
    $likes = spgp_get_like_count($post_id);
    $related = spgp_get_related_photos($post_id, 12);
?>

<div class="spgp-single-photo">
    <div class="spgp-single-photo-image">
        <?php if ($image_url) : ?>
            <img src="<?php echo esc_url($image_url); ?>" alt="<?php the_title(); ?>" />
        <?php endif; ?>
    </div>
    
    <div class="spgp-single-photo-meta">
        <div class="spgp-single-photo-content">
            <h1><?php the_title(); ?></h1>
            <div class="spgp-single-photo-description">
                <?php the_content(); ?>
            </div>
            
            <?php if (!empty($tags)) : ?>
                <div class="spgp-single-photo-tags">
                    <strong><?php _e('Tags:', 'smart-photo-gallery-pro'); ?></strong>
                    <?php foreach ($tags as $tag) : ?>
                        <span class="spgp-tag"><?php echo esc_html($tag); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($categories)) : ?>
                <div class="spgp-single-photo-categories">
                    <strong><?php _e('Categories:', 'smart-photo-gallery-pro'); ?></strong>
                    <?php foreach ($categories as $cat) : ?>
                        <a href="<?php echo esc_url(get_term_link($cat)); ?>" class="spgp-category-tag"><?php echo esc_html($cat->name); ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="spgp-single-photo-actions">
                <?php if ($image_url) : ?>
                    <a href="<?php echo esc_url($image_url); ?>" download class="spgp-lightbox-button"><?php _e('Download', 'smart-photo-gallery-pro'); ?></a>
                <?php endif; ?>
                
                <?php 
                $share_url = get_permalink();
                $share_title = get_the_title();
                ?>
                <a href="<?php echo esc_url(spgp_get_share_url('facebook', $share_url, $share_title)); ?>" target="_blank" class="spgp-lightbox-button">Facebook</a>
                <a href="<?php echo esc_url(spgp_get_share_url('twitter', $share_url, $share_title)); ?>" target="_blank" class="spgp-lightbox-button">Twitter</a>
                <a href="<?php echo esc_url(spgp_get_share_url('whatsapp', $share_url, $share_title)); ?>" target="_blank" class="spgp-lightbox-button">WhatsApp</a>
                <button class="spgp-lightbox-button spgp-copy-link-btn" data-url="<?php echo esc_url($share_url); ?>"><?php _e('Copy Link', 'smart-photo-gallery-pro'); ?></button>
                
                <?php if (spgp_get_setting('enable_likes', 1) && is_user_logged_in()) : ?>
                    <button class="spgp-lightbox-button spgp-like-btn" data-photo-id="<?php echo esc_attr($post_id); ?>">
                        <span class="spgp-like-icon"><?php echo spgp_is_liked($post_id) ? '❤️' : '🤍'; ?></span>
                        <span class="spgp-like-count"><?php echo $likes; ?></span>
                    </button>
                <?php endif; ?>
                
                <?php if (spgp_get_setting('enable_favorites', 1) && is_user_logged_in()) : ?>
                    <button class="spgp-lightbox-button spgp-favorite-btn" data-photo-id="<?php echo esc_attr($post_id); ?>">
                        <span class="spgp-favorite-icon"><?php echo spgp_is_favorited($post_id) ? '⭐' : '☆'; ?></span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="spgp-single-photo-info">
            <?php if ($event_date) : ?>
                <div class="spgp-single-photo-info-item">
                    <div class="spgp-single-photo-info-label"><?php _e('Event Date', 'smart-photo-gallery-pro'); ?></div>
                    <div class="spgp-single-photo-info-value">
                        <?php echo esc_html($event_date); ?>
                        <?php if ($event_date_end && $event_date_end != $event_date) : ?>
                            - <?php echo esc_html($event_date_end); ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($location) : ?>
                <div class="spgp-single-photo-info-item">
                    <div class="spgp-single-photo-info-label"><?php _e('Location', 'smart-photo-gallery-pro'); ?></div>
                    <div class="spgp-single-photo-info-value"><?php echo esc_html($location); ?></div>
                </div>
            <?php endif; ?>
            
            <?php if ($photographer) : ?>
                <div class="spgp-single-photo-info-item">
                    <div class="spgp-single-photo-info-label"><?php _e('Photographer', 'smart-photo-gallery-pro'); ?></div>
                    <div class="spgp-single-photo-info-value"><?php echo esc_html($photographer); ?></div>
                </div>
            <?php endif; ?>
            
            <?php if (spgp_get_setting('enable_views', 1)) : ?>
                <div class="spgp-single-photo-info-item">
                    <div class="spgp-single-photo-info-label"><?php _e('Views', 'smart-photo-gallery-pro'); ?></div>
                    <div class="spgp-single-photo-info-value"><?php echo $views; ?></div>
                </div>
            <?php endif; ?>
            
            <div class="spgp-single-photo-info-item">
                <div class="spgp-single-photo-info-label"><?php _e('Published', 'smart-photo-gallery-pro'); ?></div>
                <div class="spgp-single-photo-info-value"><?php echo get_the_date(); ?></div>
            </div>
        </div>
    </div>
    
    <?php if (!empty($related)) : ?>
        <div class="spgp-related-photos">
            <h2 class="spgp-related-photos-title"><?php _e('Related Photos', 'smart-photo-gallery-pro'); ?></h2>
            <div class="spgp-related-photos-grid">
                <?php foreach ($related as $related_post) : 
                    $related_thumb = spgp_get_photo_thumb($related_post->ID, 'medium');
                ?>
                    <a href="<?php echo esc_url(get_permalink($related_post->ID)); ?>" class="spgp-gallery-item">
                        <?php if ($related_thumb) : ?>
                            <img src="<?php echo esc_url($related_thumb); ?>" alt="<?php echo esc_attr(get_the_title($related_post->ID)); ?>" />
                        <?php endif; ?>
                        <div class="spgp-gallery-item-overlay">
                            <div class="spgp-gallery-item-title"><?php echo esc_html(get_the_title($related_post->ID)); ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
endwhile;
get_footer();
