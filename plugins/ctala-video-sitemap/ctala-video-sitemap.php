<?php
/*
Plugin Name: Youtube Video Sitemap Generator
Description: Generates a video sitemap for all videos in posts.
Version: 1.0
Author: Cristian Tala Sánchez
Author URI: https://cristiantala.cl
License: MIT
*/


// Hook into the 'init' action
add_action('init', 'vsg_init');

function vsg_init() {
    add_rewrite_rule('video_sitemap\.xml$', 'index.php?video_sitemap=1', 'top');
}

add_filter('query_vars', 'vsg_query_vars');
function vsg_query_vars($vars) {
    $vars[] = 'video_sitemap';
    return $vars;
}

add_action('template_redirect', 'vsg_template_redirect');
function vsg_template_redirect() {
    if (get_query_var('video_sitemap')) {
        header('Content-Type: application/xml; charset=' . get_option('blog_charset'), true);
        echo vsg_generate_sitemap();
        exit;
    }
}

function vsg_generate_sitemap() {
    $videos = vsg_get_videos();
    $sitemap = '<?xml version="1.0" encoding="UTF-8"?>';
    $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">';
    foreach ($videos as $video) {
        $sitemap .= '<url>';
        $sitemap .= '<loc>' . esc_url($video['loc']) . '</loc>';
        $sitemap .= '<video:video>';
        $sitemap .= '<video:thumbnail_loc>' . esc_url($video['thumbnail_loc']) . '</video:thumbnail_loc>';
        $sitemap .= '<video:title>' . esc_html($video['title']) . '</video:title>';
        $sitemap .= '<video:description>' . esc_html($video['description']) . '</video:description>';
        $sitemap .= '<video:content_loc>' . esc_url($video['content_loc']) . '</video:content_loc>';
        $sitemap .= '<video:player_loc>' . esc_url($video['player_loc']) . '</video:player_loc>';
        $sitemap .= '</video:video>';
        $sitemap .= '</url>';
    }
    $sitemap .= '</urlset>';
    return $sitemap;
}

function vsg_get_videos() {
    $videos = [];
    $args = array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => -1,
    );
    $query = new WP_Query($args);
    while ($query->have_posts()) : $query->the_post();
        $post_id = get_the_ID();
        $post_content = get_the_content();

        // Debugging: Imprime el contenido del post
        error_log('Post Content: ' . $post_content);

        // Check for YouTube iframes
        preg_match_all('/<iframe.*src=["\']https:\/\/www\.youtube\.com\/embed\/([a-zA-Z0-9_-]+)["\'].*><\/iframe>/is', $post_content, $matches);
        foreach ($matches[1] as $video_id) {
            $videos[] = array(
                'loc' => get_permalink($post_id),
                'thumbnail_loc' => 'https://img.youtube.com/vi/' . $video_id . '/hqdefault.jpg',
                'title' => get_the_title(),
                'description' => get_the_excerpt(),
                'content_loc' => 'https://www.youtube.com/watch?v=' . $video_id,
                'player_loc' => 'https://www.youtube.com/embed/' . $video_id,
            );
        }

        // Check for YouTube oEmbed blocks
        $blocks = parse_blocks($post_content);
        foreach ($blocks as $block) {
            if ($block['blockName'] === 'core/embed' && isset($block['attrs']['url']) && strpos($block['attrs']['url'], 'youtube.com') !== false) {
                $youtube_url = $block['attrs']['url'];
                preg_match('/https:\/\/www\.youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/', $youtube_url, $matches);
                if (isset($matches[1])) {
                    $video_id = $matches[1];
                    $videos[] = array(
                        'loc' => get_permalink($post_id),
                        'thumbnail_loc' => 'https://img.youtube.com/vi/' . $video_id . '/hqdefault.jpg',
                        'title' => get_the_title(),
                        'description' => get_the_excerpt(),
                        'content_loc' => $youtube_url,
                        'player_loc' => 'https://www.youtube.com/embed/' . $video_id,
                    );
                }
            } elseif ($block['blockName'] === 'core/embed' && isset($block['attrs']['url']) && strpos($block['attrs']['url'], 'youtu.be') !== false) {
                $youtube_url = $block['attrs']['url'];
                preg_match('/https:\/\/youtu\.be\/([a-zA-Z0-9_-]+)/', $youtube_url, $matches);
                if (isset($matches[1])) {
                    $video_id = $matches[1];
                    $videos[] = array(
                        'loc' => get_permalink($post_id),
                        'thumbnail_loc' => 'https://img.youtube.com/vi/' . $video_id . '/hqdefault.jpg',
                        'title' => get_the_title(),
                        'description' => get_the_excerpt(),
                        'content_loc' => $youtube_url,
                        'player_loc' => 'https://www.youtube.com/embed/' . $video_id,
                    );
                }
            }
        }
    endwhile;
    wp_reset_postdata();
    // Debugging: Imprime los videos encontrados
    error_log('Videos Found: ' . print_r($videos, true));
    return $videos;
}
?>