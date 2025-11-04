<?php
/**
 * Uninstall script for Smart Photo Gallery Pro
 */

// Exit if uninstall not called from WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check user permissions
if (!current_user_can('activate_plugins')) {
    return;
}

// Remove plugin options
delete_option('spgp_settings');

// Remove database tables
global $wpdb;

$tables = array(
    $wpdb->prefix . 'spgp_faces',
    $wpdb->prefix . 'spgp_ai_tags',
    $wpdb->prefix . 'spgp_favorites',
    $wpdb->prefix . 'spgp_likes',
    $wpdb->prefix . 'spgp_views',
);

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

// Optional: Remove all photo posts and attachments
// Uncomment the following lines if you want to delete all photos on uninstall

/*
$photo_posts = get_posts(array(
    'post_type' => 'photo_album',
    'posts_per_page' => -1,
    'post_status' => 'any',
));

foreach ($photo_posts as $post) {
    $attachment_id = get_post_meta($post->ID, '_spgp_photo_attachment_id', true);
    if ($attachment_id) {
        wp_delete_attachment($attachment_id, true);
    }
    wp_delete_post($post->ID, true);
}
*/

// Remove custom taxonomies
$terms = get_terms(array(
    'taxonomy' => 'album_category',
    'hide_empty' => false,
));

if (!is_wp_error($terms)) {
    foreach ($terms as $term) {
        wp_delete_term($term->term_id, 'album_category');
    }
}

// Flush rewrite rules
flush_rewrite_rules();
