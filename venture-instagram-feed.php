<?php
/**
 * Plugin Name:       Venture Instagram Feed
 * Plugin URI:        https://github.com/venture-media/venture-instagram-feed
 * Description:       A plugin that displays your Instagram feed with a shortcode. Custom Graph API. Only shows posts containing #YourHashtag.
 * Version:           0.9.0
 * Author:            Leon de Klerk
 * Author URI:        https://github.com/Leon2332
 * License:           MIT
 * License URI:       https://github.com/venture-media/venture-instagram-feed/blob/4e81c5a353d784309022d51a78d232c9d87af921/LICENSE
 */

// Only shows posts containing #YourHashtag in line:64
// Cache duration in line:81
// Limit amount of posts in line:90

// Define plugin version
define( 'VENTURE_INSTAGRAM_FEED_VERSION', '0.9.0' );
// Instagram Graph API
define('VENTURE_IG_USER_ID', 'REPLACE WITH NUMERIC ID');
define('VENTURE_IG_ACCESS_TOKEN', 'REPLACE WITH LONG-LIVED TOKEN');

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;


// Main function to get filtered posts
function get_venture_instagram_posts( $limit = 6 ) {
    $cache_key = 'venture_ig_posts_' . $limit;
    $posts = get_transient( $cache_key );

    if ( false === $posts ) {
        $user_id = VENTURE_IG_USER_ID;
        $token   = VENTURE_IG_ACCESS_TOKEN;

        if ( empty( $user_id ) || empty( $token ) ) {
            return [];
        }

        $url = add_query_arg( [
            'fields'       => 'id,caption,media_url,media_type,permalink,thumbnail_url,timestamp',
            'limit'        => 25,
            'access_token' => $token,
        ], "https://graph.instagram.com/{$user_id}/media" );

        $response = wp_remote_get( $url, [ 'timeout' => 15 ] );

        if ( is_wp_error( $response ) ) {
            return [];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['data'] ) ) {
            return [];
        }

        $filtered = [];

        foreach ( $body['data'] as $media ) {
            $caption = $media['caption'] ?? '';

            // Only keep posts that contain #YourHashtag
            if ( stripos( $caption, '#YourHashtag' ) !== false ) {
                $filtered[] = [
                    'id'         => $media['id'],
                    'caption'    => wp_trim_words( $caption, 18, '...' ),
                    'image'      => $media['media_url'] ?? $media['thumbnail_url'] ?? '',
                    'permalink'  => $media['permalink'],
                    'type'       => $media['media_type'],
                    'timestamp'  => $media['timestamp'],
                ];

                if ( count( $filtered ) >= $limit ) {
                    break;
                }
            }
        }

        $posts = $filtered;
        set_transient( $cache_key, $posts, HOUR_IN_SECONDS * 1 ); // Cache for 1 hour
    }

    return $posts;
}

// Shortcode: [venture_instagram limit="5"]
function venture_instagram_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'limit' => 5,
    ], $atts, 'venture_instagram' );

    $posts = get_venture_instagram_posts( (int) $atts['limit'] );

    if ( empty( $posts ) ) {
        return '<p>No matching Instagram posts found.</p>';
    }

    ob_start();
    ?>
    <div class="fly-namibia-ig-grid">
        <?php foreach ( $posts as $post ) : ?>
            <a href="<?php echo esc_url( $post['permalink'] ); ?>" target="_blank" rel="noopener" class="fly-namibia-ig-card">
                <div class="fly-namibia-ig-image">
                    <img src="<?php echo esc_url( $post['image'] ); ?>" 
                         alt="<?php echo esc_attr( wp_strip_all_tags( $post['caption'] ) ); ?>" 
                         loading="lazy">
                    <?php if ( $post['type'] === 'VIDEO' ) : ?>
                        <span class="ig-video-badge">▶</span>
                    <?php endif; ?>
                </div>
                <div class="fly-namibia-ig-content">
                    <p><?php echo esc_html( $post['caption'] ); ?></p>
                    <span class="ig-view-more">View on Instagram →</span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'venture_instagram', 'venture_instagram_shortcode' );
