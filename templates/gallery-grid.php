<?php
/**
 * Gallery Grid Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$atts = isset($atts) ? $atts : array();
$category = isset($atts['category']) ? $atts['category'] : (isset($_GET['category']) ? intval($_GET['category']) : 0);
$tags = isset($atts['tags']) ? $atts['tags'] : '';
$columns = isset($atts['columns']) ? intval($atts['columns']) : 3;
$layout = isset($atts['layout']) ? $atts['layout'] : spgp_get_setting('gallery_layout', 'masonry');
$sort = isset($atts['sort']) ? $atts['sort'] : 'newest';
$limit = isset($atts['limit']) ? intval($atts['limit']) : 0;

// Query args
$args = array(
    'post_type' => 'photo_album',
    'post_status' => 'publish',
    'posts_per_page' => $limit > 0 ? $limit : spgp_get_setting('items_per_page', 20),
    'paged' => get_query_var('paged') ? get_query_var('paged') : 1,
);

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

// Tags filter
if (!empty($tags)) {
    $tag_array = explode(',', $tags);
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

// Sort
switch ($sort) {
    case 'most_viewed':
        $args['meta_key'] = '_spgp_views';
        $args['orderby'] = 'meta_value_num';
        $args['order'] = 'DESC';
        break;
    case 'most_liked':
        // Would need custom query for likes
        $args['orderby'] = 'date';
        $args['order'] = 'DESC';
        break;
    case 'oldest':
        $args['orderby'] = 'date';
        $args['order'] = 'ASC';
        break;
    default: // newest
        $args['orderby'] = 'date';
        $args['order'] = 'DESC';
}

$query = new WP_Query($args);
?>

<div class="spgp-gallery-wrapper">
    <?php if ($query->have_posts()) : ?>
        <div class="spgp-gallery-<?php echo esc_attr($layout); ?> <?php echo $layout === 'masonry' ? 'spgp-gallery-masonry' : 'spgp-gallery-grid'; ?>" data-columns="<?php echo esc_attr($columns); ?>">
            <?php while ($query->have_posts()) : $query->the_post(); 
                $post_id = get_the_ID();
                $thumb = spgp_get_photo_thumb($post_id, 'medium');
                $image_url = spgp_get_photo_url($post_id, 'large');
                $title = get_the_title();
                $description = get_the_excerpt();
                $event_date = get_post_meta($post_id, '_spgp_event_date', true);
                $views = spgp_get_view_count($post_id);
            ?>
                <div class="spgp-gallery-item" 
                     data-photo-id="<?php echo esc_attr($post_id); ?>"
                     data-photo-url="<?php echo esc_url(get_permalink()); ?>"
                     data-photo-title="<?php echo esc_attr($title); ?>"
                     data-photo-description="<?php echo esc_attr($description); ?>">
                    <?php if ($thumb) : ?>
                        <img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy" />
                    <?php else : ?>
                        <div class="spgp-placeholder"><?php echo esc_html($title); ?></div>
                    <?php endif; ?>
                    <div class="spgp-gallery-item-overlay">
                        <div class="spgp-gallery-item-title"><?php echo esc_html($title); ?></div>
                        <div class="spgp-gallery-item-meta">
                            <?php if ($event_date) : ?>
                                <span><?php echo esc_html($event_date); ?></span>
                            <?php endif; ?>
                            <?php if (spgp_get_setting('enable_views', 1)) : ?>
                                <span><?php printf(__('%d views', 'smart-photo-gallery-pro'), $views); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        
        <?php if (!$limit && $query->max_num_pages > 1 && !spgp_get_setting('enable_infinite_scroll', 1)) : ?>
            <div class="spgp-pagination">
                <?php
                echo paginate_links(array(
                    'total' => $query->max_num_pages,
                    'current' => max(1, get_query_var('paged')),
                    'prev_text' => __('&laquo; Previous', 'smart-photo-gallery-pro'),
                    'next_text' => __('Next &raquo;', 'smart-photo-gallery-pro'),
                ));
                ?>
            </div>
        <?php endif; ?>
        
    <?php else : ?>
        <div class="spgp-empty-state">
            <div class="spgp-empty-state-icon">📷</div>
            <div class="spgp-empty-state-message"><?php _e('No photos found.', 'smart-photo-gallery-pro'); ?></div>
            <div class="spgp-empty-state-description"><?php _e('Check back later for new photos.', 'smart-photo-gallery-pro'); ?></div>
        </div>
    <?php endif; ?>
</div>

<?php wp_reset_postdata(); ?>
