<?php
/**
 * Plugin Name:       Venture Instagram Feed
 * Plugin URI:        https://github.com/venture-media/venture-instagram-feed
 * Description:       Displays Instagram posts from a specific account that contain a chosen hashtag. Built with Instagram Graph API.
 * Version:           0.9.1
 * Author:            Leon de Klerk
 * Author URI:        https://github.com/Leon2332
 * License:           MIT
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin version
define( 'VENTURE_INSTAGRAM_FEED_VERSION', '0.9.1' );

// ============================== CONFIGURATION ====================================
define( 'VENTURE_IG_USER_ID', 'REPLACE_WITH_NUMERIC_INSTAGRAM_USER_ID' );
define( 'VENTURE_IG_ACCESS_TOKEN', 'REPLACE_WITH_LONG_LIVED_TOKEN' );
define( 'VENTURE_IG_HASHTAG', '#YourHashtag' );
// =================================================================================


// Main function - Get filtered Instagram posts
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
        $hashtag  = VENTURE_IG_HASHTAG;

        foreach ( $body['data'] as $media ) {
            $caption = $media['caption'] ?? '';

            if ( stripos( $caption, $hashtag ) !== false ) {
                $filtered[] = [
                    'id'        => $media['id'],
                    'caption'   => wp_trim_words( $caption, 18, '...' ),
                    'image'     => $media['media_url'] ?? $media['thumbnail_url'] ?? '',
                    'permalink' => $media['permalink'],
                    'type'      => $media['media_type'],
                    'timestamp' => $media['timestamp'],
                ];

                if ( count( $filtered ) >= $limit ) {
                    break;
                }
            }
        }

        $posts = $filtered;
        set_transient( $cache_key, $posts, HOUR_IN_SECONDS * 1 ); // Cache 1 hour
    }

    return $posts;
}


// Shortcode: [venture_instagram limit="6"]
function venture_instagram_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'limit' => 6,
    ], $atts, 'venture_instagram' );

    $posts = get_venture_instagram_posts( (int) $atts['limit'] );

    if ( empty( $posts ) ) {
        return '<p>No Instagram posts found matching the criteria.</p>';
    }

    // Enqueue styles when shortcode is used
    wp_enqueue_style( 'venture-instagram-feed' );

    ob_start();
    ?>
    <div class="venture-ig-grid">
        <?php foreach ( $posts as $post ) : ?>
            <a href="<?php echo esc_url( $post['permalink'] ); ?>" 
               target="_blank" 
               rel="noopener noreferrer" 
               class="venture-ig-card">

                <div class="venture-ig-image">
                    <img src="<?php echo esc_url( $post['image'] ); ?>" 
                         alt="<?php echo esc_attr( wp_strip_all_tags( $post['caption'] ) ); ?>" 
                         loading="lazy">
                    
                    <?php if ( $post['type'] === 'VIDEO' ) : ?>
                        <span class="ig-video-badge">▶</span>
                    <?php endif; ?>
                </div>

                <div class="venture-ig-content">
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


// Register & Enqueue Styles
function venture_register_instagram_styles() {
    wp_register_style(
        'venture-instagram-feed',
        plugin_dir_url( __FILE__ ) . 'css/frontend.css',
        [],
        VENTURE_INSTAGRAM_FEED_VERSION
    );
}
add_action( 'wp_enqueue_scripts', 'venture_register_instagram_styles' );
