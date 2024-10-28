<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/*
Plugin Name: Add Image File Sizes to Table List View
Description: A plugin to get the file sizes of media files, add the sizes to the media table/list view as a column, and make it sortable.
Version: 0.1
Author: The 215 Guys
Author URI: https://www.the215guys.com
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

function aifstt_save_media_file_size_by_id($media_id) {
    global $wpdb;

    // Get the file path of the media file
    $file_path = get_attached_file($media_id);
    
    // Check if the file exists
    if (file_exists($file_path)) {
        // Get the file size
        $file_size = filesize($file_path);

        // Save the file size to the database, in a custom table or as a post meta
        // For example, saving as post meta
        update_post_meta($media_id, 'aifstt_media_file_size', $file_size);

        return $file_size;
    } else {
        return 'File not found';
    }
}

function aifstt_handle_new_media_upload($attachment_id) {
    // Call the function to save the media file size
    save_media_file_size_by_id($attachment_id);
}

// Hook into the media upload process
add_action('add_attachment', 'aifstt_handle_new_media_upload');

function aifstt_update_all_media_file_sizes() {
    $args = array(
        'post_type'      => 'attachment',
        'posts_per_page' => -1,
        'post_status'    => 'inherit',
    );
    
    $media_items = new WP_Query($args);

    if ($media_items->have_posts()) {
        while ($media_items->have_posts()) {
            $media_items->the_post();
            $media_id = get_the_ID();
            save_media_file_size_by_id($media_id);
        }
    }
}

// Schedule the cron job
if (!wp_next_scheduled('aifstt_daily_media_file_size_update')) {
    wp_schedule_event(time(), 'daily', 'aifstt_daily_media_file_size_update');
}

add_action('aifstt_daily_media_file_size_update', 'aifstt_update_all_media_file_sizes');

//get all files sizes on plugin activation
function aifstt_image_filesizes_activate() {
    update_all_media_file_sizes();
}

register_activation_hook(__FILE__, 'aifstt_image_filesizes_activate');


// Add the new column to the Media Library table
function aifstt_add_media_file_size_column($columns) {
    $columns['aifstt_media_file_size'] = 'File Size';
    return $columns;
}
add_filter('manage_upload_columns', 'aifstt_add_media_file_size_column');

// Display the file size in the new column for each media item
function aifstt_display_media_file_size_column($column_name, $post_id) {
    if ('media_file_size' == $column_name) {
        $file_size = get_post_meta($post_id, 'aifstt_media_file_size', true);
        if ($file_size) {
            echo esc_html(size_format($file_size, 2));
        } else {
            echo 'Not available';
        }
    }
}
add_action('manage_media_custom_column', 'aifstt_display_media_file_size_column', 10, 2);

// Make the file size column sortable
function aifstt_media_file_size_column_sortable($columns) {
    $columns['aifstt_media_file_size'] = 'aifstt_media_file_size';
    return $columns;
}
add_filter('manage_upload_sortable_columns', 'aifstt_media_file_size_column_sortable');

// Add custom order logic for the file size column
function aifstt_media_file_size_column_orderby($query) {
    if (!is_admin()) {
        return;
    }

    $orderby = $query->get('orderby');
    if ('aifstt_media_file_size' == $orderby) {
        $query->set('meta_key', 'aifstt_media_file_size');
        $query->set('orderby', 'meta_value_num');
    }
}
add_action('pre_get_posts', 'aifstt_media_file_size_column_orderby');
