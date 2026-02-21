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

        // Kinsta.
        if ( class_exists( 'Developer_Kinsta\Cache' ) || function_exists( 'kinsta_cache_purge' ) ) {
            wp_cache_flush();
        }

        /**
         * Allow other plugins to hook their own cache flush logic.
         *
         * @param int $post_id The attachment post ID.
         */
        do_action( 'inkline/replace/cache_flush', $post_id );
    }
}
