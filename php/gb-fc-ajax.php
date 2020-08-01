<?php

class GbFcAjax {
    /**
     * AJAX endpoint for tooltip content for a calendar item.
     * Returns / echos a JSON object
     */
    public static function ajax_tooltip_content()
    {
        $content = new stdClass();
        if (!empty($_REQUEST['post_id'])) {
            $post = get_post($_REQUEST['post_id']);
            if ($post->post_type == 'attachment') {
                $content->imageUrl = wp_get_attachment_image_url($post->ID, 'thumbnail');
            } else {
                $content->excerpt = (!empty($post)) ? $post->post_content : '';
                if (get_option('gbfc_tooltip_image', true)) {
                    $post_image_url = get_the_post_thumbnail_url($post->ID);
                    if (!empty($post_image_url)) {
                        $content->imageUrl = $post_image_url;
                        $content->imageDimensions = [
                            intval(get_option('gbfc_tooltip_image_w', 75)),
                            intval(get_option('gbfc_tooltip_image_h', 75))
                        ];
                    }
                }
            }
        }
        // Apply content filter of Events Manager
        $content->excerpt = apply_filters('wpfc_qtip_content', $content->excerpt);
        $content = apply_filters('gbfc_tooltip_content', $content);
        echo json_encode($content);
        die();
    }
}