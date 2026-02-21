<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Elementor compatibility for media replacement.
 *
 * Elementor stores URLs in JSON with escaped forward slashes (\/path\/to\/image.jpg).
 * This class adds an additional search/replace pass with slash-escaped URLs, and clears
 * Elementor's file cache after replacement.
 */
class Inkline_Elementor_Compat {

    public function __construct() {
        // Only activate if Elementor is present.
        add_action( 'plugins_loaded', array( $this, 'maybe_init' ) );
    }

    /**
     * Initialize hooks if Elementor is active.
     */
    public function maybe_init() {
        if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
            return;
        }

        add_filter( 'inkline/replacer/custom_replace_query', array( $this, 'add_escaped_query' ), 10, 4 );
        add_action( 'inkline_replace_done', array( $this, 'clear_elementor_cache' ) );
    }

    /**
     * Add a slash-escaped URL replacement query for Elementor's JSON storage.
     *
     * @param array  $queries      Existing custom queries.
     * @param string $base_url     Original base URL.
     * @param array  $search_urls  Standard search URLs.
     * @param array  $replace_urls Standard replacement URLs.
     * @return array
     */
    public function add_escaped_query( $queries, $base_url, $search_urls, $replace_urls ) {
        global $wpdb;

        // Elementor stores paths with escaped slashes: \/wp-content\/uploads\/...
        $escaped_base = str_replace( '/', '\\/', ltrim( $base_url, '/' ) );

        // Also need to esc_like the escaped base for the SQL LIKE query.
        $escaped_base = $wpdb->esc_like( $escaped_base );

        $queries[] = array(
            'base_url' => $escaped_base,
            'search'   => $search_urls,
            'replace'  => $replace_urls,
        );

        return $queries;
    }

    /**
     * Clear Elementor's file cache after a replacement.
     */
    public function clear_elementor_cache() {
        if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }
    }
}
