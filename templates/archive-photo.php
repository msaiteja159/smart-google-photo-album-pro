<?php
/**
 * Archive Template for Photo Album
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<div class="spgp-archive-wrapper">
    <?php if (is_tax('album_category')) : 
        $term = get_queried_object();
    ?>
        <header class="spgp-archive-header">
            <h1><?php echo esc_html($term->name); ?></h1>
            <?php if ($term->description) : ?>
                <div class="spgp-archive-description">
                    <?php echo wp_kses_post($term->description); ?>
                </div>
            <?php endif; ?>
        </header>
    <?php else : ?>
        <header class="spgp-archive-header">
            <h1><?php _e('Photo Gallery', 'smart-photo-gallery-pro'); ?></h1>
        </header>
    <?php endif; ?>
    
    <?php echo do_shortcode('[smart_gallery]'); ?>
</div>

<?php get_footer(); ?>
