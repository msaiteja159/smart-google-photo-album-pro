<?php
/**
 * Single Photo Overlay Template - Full Screen with Overlay
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
    $views = spgp_get_view_count($post_id);
    $likes = spgp_get_like_count($post_id);
    
    // Get adjacent posts for navigation
    $prev_post = get_previous_post();
    $next_post = get_next_post();
?>

<div class="spgp-single-photo-overlay">
    <div class="spgp-overlay-background" style="background-image: url('<?php echo esc_url($image_url); ?>');">
        <div class="spgp-overlay-content">
            <!-- Close Button -->
            <button class="spgp-overlay-close" onclick="history.back()">&times;</button>
            
            <!-- Navigation Arrows -->
            <?php if ($prev_post) : ?>
                <a href="<?php echo esc_url(get_permalink($prev_post->ID)); ?>" class="spgp-nav-arrow spgp-nav-prev">&#8249;</a>
            <?php endif; ?>
            
            <?php if ($next_post) : ?>
                <a href="<?php echo esc_url(get_permalink($next_post->ID)); ?>" class="spgp-nav-arrow spgp-nav-next">&#8250;</a>
            <?php endif; ?>
            
            <!-- Image -->
            <div class="spgp-overlay-image">
                <img src="<?php echo esc_url($image_url); ?>" alt="<?php the_title(); ?>" />
            </div>
            
            <!-- Overlay Info Box -->
            <div class="spgp-overlay-info">
                <h1 class="spgp-overlay-title"><?php the_title(); ?></h1>
                
                <?php if (get_the_content()) : ?>
                    <p class="spgp-overlay-description"><?php the_content(); ?></p>
                <?php endif; ?>
                
                <div class="spgp-overlay-meta">
                    <?php if ($event_date) : ?>
                        <div class="spgp-overlay-meta-item">
                            <strong><?php _e('Date:', 'smart-photo-gallery-pro'); ?></strong>
                            <span><?php echo esc_html($event_date); ?>
                            <?php if ($event_date_end && $event_date_end != $event_date) : ?>
                                - <?php echo esc_html($event_date_end); ?>
                            <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($location) : ?>
                        <div class="spgp-overlay-meta-item">
                            <strong><?php _e('Location:', 'smart-photo-gallery-pro'); ?></strong>
                            <span><?php echo esc_html($location); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($categories)) : ?>
                        <div class="spgp-overlay-meta-item">
                            <strong><?php _e('Category:', 'smart-photo-gallery-pro'); ?></strong>
                            <span><?php echo esc_html(implode(', ', wp_list_pluck($categories, 'name'))); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Action Buttons -->
                <div class="spgp-overlay-actions">
                    <?php if ($image_url) : ?>
                        <a href="<?php echo esc_url($image_url); ?>" download class="spgp-overlay-btn spgp-btn-download">
                            <span class="spgp-btn-icon">⬇</span>
                            <?php _e('Download', 'smart-photo-gallery-pro'); ?>
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $share_url = get_permalink();
                    $share_title = get_the_title();
                    ?>
                    <a href="<?php echo esc_url(spgp_get_share_url('whatsapp', $share_url, $share_title)); ?>" target="_blank" class="spgp-overlay-btn spgp-btn-share spgp-share-whatsapp">
                        <span class="spgp-btn-icon">📱</span>
                        <?php _e('Share', 'smart-photo-gallery-pro'); ?>
                    </a>
                    
                    <a href="<?php echo esc_url(spgp_get_share_url('facebook', $share_url, $share_title)); ?>" target="_blank" class="spgp-overlay-btn spgp-btn-share spgp-share-facebook">
                        <span class="spgp-btn-icon">📘</span>
                        Facebook
                    </a>
                    
                    <a href="<?php echo esc_url(spgp_get_share_url('twitter', $share_url, $share_title)); ?>" target="_blank" class="spgp-overlay-btn spgp-btn-share spgp-share-twitter">
                        <span class="spgp-btn-icon">🐦</span>
                        Twitter
                    </a>
                    
                    <button class="spgp-overlay-btn spgp-btn-copy" onclick="copyToClipboard('<?php echo esc_js($share_url); ?>')">
                        <span class="spgp-btn-icon">🔗</span>
                        <?php _e('Copy Link', 'smart-photo-gallery-pro'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Related Photos Grid Below -->
    <?php 
    $related = spgp_get_related_photos($post_id, 12);
    if (!empty($related)) : 
    ?>
        <div class="spgp-related-section">
            <h3><?php _e('Related Photos', 'smart-photo-gallery-pro'); ?></h3>
            <div class="spgp-related-grid masonry">
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

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        alert('<?php _e('Link copied to clipboard!', 'smart-photo-gallery-pro'); ?>');
    });
}

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    <?php if ($prev_post) : ?>
    if (e.key === 'ArrowLeft') {
        window.location.href = '<?php echo esc_url(get_permalink($prev_post->ID)); ?>';
    }
    <?php endif; ?>
    
    <?php if ($next_post) : ?>
    if (e.key === 'ArrowRight') {
        window.location.href = '<?php echo esc_url(get_permalink($next_post->ID)); ?>';
    }
    <?php endif; ?>
    
    if (e.key === 'Escape') {
        history.back();
    }
});
</script>

<?php
endwhile;
get_footer();
