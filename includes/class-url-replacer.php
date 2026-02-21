<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Searches and replaces old media URLs with new ones across the WordPress database.
 *
 * Handles wp_posts.post_content, wp_postmeta, wp_termmeta, and wp_commentmeta.
 * Correctly processes serialized and JSON-encoded data.
 */
class Inkline_URL_Replacer {

    private $source_url;
    private $source_meta;
    private $target_url;
    private $target_meta;

    /**
     * Set the source (old) attachment URL and metadata.
     *
     * @param string $url  Original attachment URL.
     * @param array  $meta Original attachment metadata.
     */
    public function set_source( $url, $meta ) {
        $this->source_url  = $url;
        $this->source_meta = $meta;
    }

    /**
     * Set the target (new) attachment URL and metadata.
     *
     * @param string $url  New attachment URL.
     * @param array  $meta New attachment metadata.
     */
    public function set_target( $url, $meta ) {
        $this->target_url  = $url;
        $this->target_meta = $meta;
    }

    /**
     * Run the replacement across the database.
     *
     * @param bool $thumbnails_only If true, only replace thumbnail URLs (not the main file URL).
     * @return int Number of rows updated.
     */
    public function replace( $thumbnails_only = false ) {
        $urls = $this->build_url_maps();

        if ( $thumbnails_only ) {
            // Remove the main file URL — it hasn't changed in MODE_REPLACE.
            unset( $urls['search']['base'], $urls['search']['file'] );
            unset( $urls['replace']['base'], $urls['replace']['file'] );
        }

        // Balance arrays: drop target sizes that don't exist in source.
        $search_keys  = array_keys( $urls['search'] );
        $replace_keys = array_keys( $urls['replace'] );

        foreach ( $replace_keys as $key ) {
            if ( ! in_array( $key, $search_keys, true ) ) {
                unset( $urls['replace'][ $key ] );
            }
        }

        // For source sizes missing in target, find nearest match by width.
        foreach ( $search_keys as $key ) {
            if ( ! isset( $urls['replace'][ $key ] ) ) {
                $nearest = $this->find_nearest_size( $key );
                if ( $nearest ) {
                    $urls['replace'][ $key ] = $nearest;
                } else {
                    unset( $urls['search'][ $key ] );
                }
            }
        }

        // Remove identity pairs.
        foreach ( $urls['search'] as $key => $val ) {
            if ( isset( $urls['replace'][ $key ] ) && $val === $urls['replace'][ $key ] ) {
                unset( $urls['search'][ $key ], $urls['replace'][ $key ] );
            }
        }

        $search_urls  = array_values( $urls['search'] );
        $replace_urls = array_values( $urls['replace'] );

        if ( empty( $search_urls ) || count( $search_urls ) !== count( $replace_urls ) ) {
            return 0;
        }

        // Get the base URL for the LIKE query (filename without extension).
        $base_url = $this->get_base_url( $this->source_url );
        if ( ! $base_url ) {
            return 0;
        }

        $updated = $this->do_replace_query( $base_url, $search_urls, $replace_urls );

        // Allow integrations (e.g. Elementor) to add custom replace queries.
        $custom_queries = apply_filters( 'inkline/replacer/custom_replace_query', array(), $base_url, $search_urls, $replace_urls );
        foreach ( $custom_queries as $query ) {
            if ( ! empty( $query['base_url'] ) && ! empty( $query['search'] ) && ! empty( $query['replace'] ) ) {
                $updated += $this->do_replace_query( $query['base_url'], $query['search'], $query['replace'] );
            }
        }

        return $updated;
    }

    /**
     * Build source and target URL maps from attachment metadata.
     *
     * @return array { 'search' => [...], 'replace' => [...] }
     */
    private function build_url_maps() {
        $search  = array();
        $replace = array();

        // Main file URLs (relative path from parse_url).
        $source_path = parse_url( $this->source_url, PHP_URL_PATH );
        $target_path = parse_url( $this->target_url, PHP_URL_PATH );

        if ( $source_path && $target_path ) {
            $search['base']  = $source_path;
            $replace['base'] = $target_path;

            $search['file']  = basename( $source_path );
            $replace['file'] = basename( $target_path );
        }

        // Thumbnail URLs from metadata.
        $source_dir = $source_path ? dirname( $source_path ) : '';
        $target_dir = $target_path ? dirname( $target_path ) : '';

        if ( ! empty( $this->source_meta['sizes'] ) ) {
            foreach ( $this->source_meta['sizes'] as $size => $data ) {
                $search[ 'thumb-' . $size ] = $source_dir . '/' . $data['file'];
            }
        }

        if ( ! empty( $this->target_meta['sizes'] ) ) {
            foreach ( $this->target_meta['sizes'] as $size => $data ) {
                $replace[ 'thumb-' . $size ] = $target_dir . '/' . $data['file'];
            }
        }

        return array( 'search' => $search, 'replace' => $replace );
    }

    /**
     * Find the target thumbnail with the closest width to a given source size.
     *
     * @param string $size_key Size key (e.g. 'thumb-medium').
     * @return string|null Target URL path, or null if no match found.
     */
    private function find_nearest_size( $size_key ) {
        $size_name = str_replace( 'thumb-', '', $size_key );

        if ( empty( $this->source_meta['sizes'][ $size_name ]['width'] ) ) {
            return null;
        }

        $source_width = (int) $this->source_meta['sizes'][ $size_name ]['width'];

        if ( empty( $this->target_meta['sizes'] ) ) {
            return null;
        }

        $target_path = parse_url( $this->target_url, PHP_URL_PATH );
        $target_dir  = $target_path ? dirname( $target_path ) : '';

        $closest_file = null;
        $closest_diff = PHP_INT_MAX;

        foreach ( $this->target_meta['sizes'] as $data ) {
            $diff = abs( $source_width - (int) $data['width'] );
            if ( $diff < $closest_diff ) {
                $closest_diff = $diff;
                $closest_file = $target_dir . '/' . $data['file'];
            }
        }

        return $closest_file;
    }

    /**
     * Get the base URL (path without extension) for use in LIKE queries.
     *
     * @param string $url Full URL.
     * @return string|null
     */
    private function get_base_url( $url ) {
        $path = parse_url( $url, PHP_URL_PATH );
        if ( ! $path ) {
            return null;
        }

        $ext = pathinfo( $path, PATHINFO_EXTENSION );
        if ( $ext ) {
            $path = substr( $path, 0, - ( strlen( $ext ) + 1 ) );
        }

        return $path;
    }

    /**
     * Execute the search/replace queries across database tables.
     *
     * @param string $base_url    Base URL for LIKE matching.
     * @param array  $search_urls  URLs to search for.
     * @param array  $replace_urls URLs to replace with.
     * @return int Number of rows updated.
     */
    private function do_replace_query( $base_url, $search_urls, $replace_urls ) {
        global $wpdb;

        $updated  = 0;
        $like_val = '%' . $wpdb->esc_like( $base_url ) . '%';

        // 1. Search/replace in wp_posts.post_content.
        $posts = $wpdb->get_results( $wpdb->prepare(
            "SELECT ID, post_content FROM {$wpdb->posts}
             WHERE post_status IN ('publish','future','draft','pending','private')
             AND post_content LIKE %s",
            $like_val
        ) );

        if ( $posts ) {
            foreach ( $posts as $row ) {
                $new_content = $this->replace_content( $row->post_content, $search_urls, $replace_urls, false, true );
                if ( $new_content !== $row->post_content ) {
                    $wpdb->update(
                        $wpdb->posts,
                        array( 'post_content' => $new_content ),
                        array( 'ID' => $row->ID ),
                        array( '%s' ),
                        array( '%d' )
                    );
                    wp_cache_delete( $row->ID, 'posts' );
                    $updated++;
                }
            }
        }

        // 2. Search/replace in metadata tables.
        $meta_tables = array(
            $wpdb->postmeta    => 'meta_id',
            $wpdb->termmeta    => 'meta_id',
            $wpdb->commentmeta => 'meta_id',
        );

        /**
         * Filter the metadata tables to search.
         *
         * @param array $meta_tables Associative array of table name => primary key column.
         */
        $meta_tables = apply_filters( 'inkline/replacer/metadata_tables', $meta_tables );

        foreach ( $meta_tables as $table => $pk_col ) {
            $meta_query = "SELECT {$pk_col} AS id, meta_value FROM {$table} WHERE meta_value LIKE %s";

            // For postmeta, limit to posts with valid statuses.
            if ( $table === $wpdb->postmeta ) {
                $meta_query = "SELECT {$pk_col} AS id, meta_value FROM {$table}
                               WHERE post_id IN (
                                   SELECT ID FROM {$wpdb->posts}
                                   WHERE post_status IN ('publish','future','draft','pending','private')
                               )
                               AND meta_value LIKE %s";
            }

            $rows = $wpdb->get_results( $wpdb->prepare( $meta_query, $like_val ) );

            if ( $rows ) {
                foreach ( $rows as $row ) {
                    $new_value = $this->replace_content( $row->meta_value, $search_urls, $replace_urls, false, false );
                    if ( $new_value !== $row->meta_value ) {
                        $wpdb->update(
                            $table,
                            array( 'meta_value' => $new_value ),
                            array( $pk_col => $row->id ),
                            array( '%s' ),
                            array( '%d' )
                        );
                        $updated++;
                    }
                }
            }
        }

        return $updated;
    }

    /**
     * Replace URLs within a content string, handling serialized and JSON data.
     *
     * @param mixed  $content      The content to process.
     * @param array  $search_urls  URLs to search for.
     * @param array  $replace_urls URLs to replace with.
     * @param bool   $in_deep      Whether we're recursing into unserialized data.
     * @param bool   $strict       Use strict unserialization (no class instantiation).
     * @return mixed The content with replacements applied.
     */
    private function replace_content( $content, $search_urls, $replace_urls, $in_deep = false, $strict = true ) {
        if ( is_string( $content ) ) {
            // Check for serialized data.
            if ( ! $in_deep && is_serialized( $content ) ) {
                $unserialized = @unserialize( $content, array( 'allowed_classes' => ! $strict ) );

                if ( $unserialized === false && $content !== serialize( false ) ) {
                    // Unserialization failed — fall back to string replacement.
                    return str_replace( $search_urls, $replace_urls, $content );
                }

                // Check for __PHP_Incomplete_Class — bail to avoid corruption.
                if ( is_object( $unserialized ) && get_class( $unserialized ) === '__PHP_Incomplete_Class' ) {
                    return str_replace( $search_urls, $replace_urls, $content );
                }

                $replaced = $this->replace_content( $unserialized, $search_urls, $replace_urls, true, $strict );
                return maybe_serialize( $replaced );
            }

            // Check for JSON data.
            if ( ! $in_deep && $this->is_json( $content ) ) {
                $decoded = json_decode( $content, true );
                if ( $decoded !== null ) {
                    $replaced = $this->replace_content( $decoded, $search_urls, $replace_urls, true, $strict );
                    return json_encode( $replaced, JSON_UNESCAPED_SLASHES );
                }
            }

            // Plain string replacement.
            return str_replace( $search_urls, $replace_urls, $content );
        }

        if ( is_array( $content ) ) {
            $result = array();
            foreach ( $content as $key => $value ) {
                $new_key = is_string( $key )
                    ? str_replace( $search_urls, $replace_urls, $key )
                    : $key;
                $result[ $new_key ] = $this->replace_content( $value, $search_urls, $replace_urls, true, $strict );
            }
            return $result;
        }

        if ( is_object( $content ) ) {
            if ( get_class( $content ) === '__PHP_Incomplete_Class' ) {
                return $content;
            }
            foreach ( get_object_vars( $content ) as $prop => $value ) {
                $content->$prop = $this->replace_content( $value, $search_urls, $replace_urls, true, $strict );
            }
            return $content;
        }

        return $content;
    }

    /**
     * Check if a string is valid JSON.
     *
     * @param string $string
     * @return bool
     */
    private function is_json( $string ) {
        if ( ! is_string( $string ) || strlen( $string ) < 2 ) {
            return false;
        }
        $first = $string[0];
        if ( $first !== '{' && $first !== '[' ) {
            return false;
        }
        json_decode( $string );
        return json_last_error() === JSON_ERROR_NONE;
    }
}
