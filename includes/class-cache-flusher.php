<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Flushes various caches after a media replacement.
 *
 * Supports WordPress core cache plus popular caching plugins and hosting platforms.
 */
class Inkline_Cache_Flusher {

    /**
     * Flush all detected caches.
     *
     * @param int $post_id The attachment post ID.
     */
    public static function flush( $post_id ) {
        // WordPress core.
        clean_post_cache( $post_id );

        // W3 Total Cache.
        if ( function_exists( 'w3tc_pgcache_flush' ) ) {
            w3tc_pgcache_flush();
        }

        // WP Super Cache.
        if ( function_exists( 'wp_cache_clean_cache' ) ) {
            global $file_prefix;
            wp_cache_clean_cache( $file_prefix );
        }

        // WP Engine.
        if ( class_exists( 'WpeCommon' ) ) {
            if ( method_exists( 'WpeCommon', 'purge_memcached' ) ) {
                \WpeCommon::purge_memcached();
            }
            if ( method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) {
                \WpeCommon::purge_varnish_cache();
            }
        }

        // WP Fastest Cache.
        if ( isset( $GLOBALS['wp_fastest_cache'] ) && method_exists( $GLOBALS['wp_fastest_cache'], 'deleteCache' ) ) {
            $GLOBALS['wp_fastest_cache']->deleteCache();
        }

        // SiteGround SuperCacher.
        if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
            sg_cachepress_purge_cache();
        }

        // LiteSpeed Cache.
        if ( defined( 'LSCWP_DIR' ) ) {
            do_action( 'litespeed_media_reset', $post_id );
        }

        // Kinsta — purge object cache, page cache, and CDN cache for image URLs.
        if ( self::is_kinsta() ) {
            wp_cache_flush();
            self::flush_kinsta_urls( $post_id );
        }

        /**
         * Allow other plugins to hook their own cache flush logic.
         *
         * @param int $post_id The attachment post ID.
         */
        do_action( 'inkline/replace/cache_flush', $post_id );
    }

    /**
     * Check if the site is running on Kinsta.
     *
     * @return bool
     */
    private static function is_kinsta() {
        return defined( 'KINSTAMU_VERSION' )
            || class_exists( 'Kinsta\Cache' )
            || class_exists( 'Developer_Kinsta\Cache' )
            || isset( $_SERVER['KINSTA_CACHE_ZONE'] );
    }

    /**
     * Purge Kinsta's server/CDN cache for all URLs belonging to an attachment.
     *
     * Uses Kinsta's /kinsta-clear-cache/{path} endpoint to invalidate
     * individual image URLs (main file, original, and every thumbnail size).
     *
     * @param int $post_id The attachment post ID.
     */
    private static function flush_kinsta_urls( $post_id ) {
        $urls = self::get_attachment_urls( $post_id );

        foreach ( $urls as $url ) {
            $path = ltrim( wp_parse_url( $url, PHP_URL_PATH ), '/' );
            if ( ! $path ) {
                continue;
            }
            wp_remote_get(
                home_url( '/kinsta-clear-cache/' . $path ),
                array(
                    'sslverify' => false,
                    'timeout'   => 2,
                    'blocking'  => false,
                )
            );
        }
    }

    /**
     * Collect all URLs for an attachment (main file, original, and thumbnails).
     *
     * @param int $post_id The attachment post ID.
     * @return string[]
     */
    private static function get_attachment_urls( $post_id ) {
        $urls = array();

        // Main attachment URL (may be the -scaled version).
        $main_url = wp_get_attachment_url( $post_id );
        if ( $main_url ) {
            $urls[] = $main_url;
        }

        // Original image URL (pre-scaled), if different.
        if ( function_exists( 'wp_get_original_image_url' ) ) {
            $original_url = wp_get_original_image_url( $post_id );
            if ( $original_url && $original_url !== $main_url ) {
                $urls[] = $original_url;
            }
        }

        // Thumbnail URLs from metadata.
        $meta = wp_get_attachment_metadata( $post_id );
        if ( ! empty( $meta['sizes'] ) && ! empty( $meta['file'] ) ) {
            $upload_dir = wp_get_upload_dir();
            $base_dir   = dirname( $meta['file'] );
            foreach ( $meta['sizes'] as $size_data ) {
                $urls[] = $upload_dir['baseurl'] . '/' . $base_dir . '/' . $size_data['file'];
            }
        }

        return array_unique( $urls );
    }
}
