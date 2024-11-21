<?php
/*  
Plugin Name: Wordpress auto download remote image
Plugin URI: https://github.com/hljhljhlj/wordpress-auto-download-remote-image
Description: wordpress auto download remote image when publish
Author: He Lijun
Author URI: http://www.xiaolikt.cn
Version: 1.0.1
*/
add_action('publish_post', 'download_remote_images_on_publish');
function download_remote_images_on_publish($post_id) {
    // Check if this is an auto-save routine. If it is, our form has not been submitted, so we don't want to do anything.
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check the post type
    if (get_post_type($post_id) != 'post') {
        return;
    }
remove_action('publish_post', 'download_remote_images_on_publish');
    // Get the post content
    $content = get_post_field('post_content', $post_id);

    // Load the content
    $dom = new DOMDocument();
    @$dom->loadHTML($content);

    // Get all image tags
    $images = $dom->getElementsByTagName('img');

    foreach ($images as $img) {
        $url = $img->getAttribute('src');

        // Check if the image is remote and not already downloaded
        if (strpos($url, get_site_url()) === false && !is_image_already_downloaded($url)) {
            // Download the image
            $response = wp_remote_get($url);
            if (is_wp_error($response)||$response==null) {
                continue;
            }

            $image_data = wp_remote_retrieve_body($response);
            $filename = basename($url);

            // Save the image to the uploads directory
            $upload_dir = wp_upload_dir();
            $file = $upload_dir['path'] . '/' . $filename;
            file_put_contents($file, $image_data);

            // Get the file type
            $wp_filetype = wp_check_filetype($filename, null);

            // Prepare an array of post data for the attachment
            $attachment = array(
                'guid' => $upload_dir['url'] . '/' . $filename,
                'post_mime_type' => $wp_filetype['type'],
                'post_title' => sanitize_file_name($filename),
                'post_content' => '',
                'post_status' => 'inherit'
            );

            // Insert the attachment
            $attach_id = wp_insert_attachment($attachment, $file);

            // Include image.php
            require_once(ABSPATH . 'wp-admin/includes/image.php');

            // Generate the metadata for the attachment, and update the database record
            $attach_data = wp_generate_attachment_metadata($attach_id, $file);
            wp_update_attachment_metadata($attach_id, $attach_data);

            // Replace the old image URL with the new one
            $content = str_replace($url, $upload_dir['url'] . '/' . $filename, $content);
        }
    }

    // Update the post content
    wp_update_post(array(
        'ID' => $post_id,
        'post_content' => $content
    ));
    add_action('publish_post', 'download_remote_images_on_publish');
}

function is_image_already_downloaded($url) {
    $upload_dir = wp_upload_dir();
    $filename = basename($url);
    $file = $upload_dir['path'] . '/' . $filename;

    return file_exists($file);
}
?>
