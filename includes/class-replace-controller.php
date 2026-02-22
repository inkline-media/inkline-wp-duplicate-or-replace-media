<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Core file replacement engine.
 *
 * Handles deleting the old file, copying the new file, regenerating thumbnails,
 * updating metadata, and optionally running URL search/replace across the database.
 */
class Inkline_Replace_Controller {

    const MODE_REPLACE       = 1;
    const MODE_SEARCHREPLACE = 2;

    const TIME_UPDATEALL     = 1;
    const TIME_UPDATEMODIFIED = 2;
    const TIME_CUSTOM        = 3;

    private $post_id;
    private $source_file;
    private $target_file;
    private $tmp_file;
    private $new_filename;
    private $mode;
    private $time_mode;
    private $custom_date;

    /**
     * @param int $post_id Attachment post ID.
     */
    public function __construct( $post_id ) {
        $this->post_id = absint( $post_id );
    }

    /**
     * Set up the replacement parameters.
     *
     * @param array $params {
     *     @type int    $mode         MODE_REPLACE or MODE_SEARCHREPLACE.
     *     @type string $tmp_file     Path to the uploaded temp file.
     *     @type string $new_filename Sanitized new filename.
     *     @type int    $time_mode    TIME_UPDATEALL, TIME_UPDATEMODIFIED, or TIME_CUSTOM.
     *     @type string $custom_date  MySQL-formatted custom date (if time_mode is TIME_CUSTOM).
     * }
     * @return true|WP_Error
     */
    public function setup_params( $params ) {
        $this->mode         = intval( $params['mode'] ?? self::MODE_REPLACE );
        $this->tmp_file     = $params['tmp_file'] ?? '';
        $this->new_filename = $params['new_filename'] ?? '';
        $this->time_mode    = intval( $params['time_mode'] ?? self::TIME_UPDATEMODIFIED );
        $this->custom_date  = $params['custom_date'] ?? null;

        // Resolve source file path, handling WP 5.3+ scaled images.
        $this->source_file = wp_get_original_image_path( $this->post_id );
        if ( ! $this->source_file ) {
            $this->source_file = get_attached_file( $this->post_id );
        }

        if ( ! $this->source_file || ! file_exists( $this->source_file ) ) {
            return new WP_Error( 'source_missing', __( 'Original file not found on disk.' ) );
        }

        // Path traversal check.
        $upload_dir  = wp_upload_dir();
        $real_upload = realpath( $upload_dir['basedir'] );
        $real_source = realpath( $this->source_file );

        if ( ! $real_source || ! $real_upload || strpos( $real_source, $real_upload . DIRECTORY_SEPARATOR ) !== 0 ) {
            return new WP_Error( 'path_traversal', __( 'File is outside the uploads directory.' ) );
        }

        // Determine target path.
        $source_dir = dirname( $this->source_file );

        if ( $this->mode === self::MODE_REPLACE ) {
            $this->target_file = $this->source_file;
        } else {
            // MODE_SEARCHREPLACE: use the new filename in the same directory.
            $unique_name       = wp_unique_filename( $source_dir, $this->new_filename );
            $this->target_file = $source_dir . '/' . $unique_name;
        }

        return true;
    }

    /**
     * Execute the replacement.
     *
     * @return true|WP_Error
     */
    public function run() {
        require_once ABSPATH . 'wp-admin/includes/image.php';

        global $wpdb;

        $post_id = $this->post_id;

        // 1. Get source metadata and URLs before we delete anything.
        // Use the attachment URL (possibly -scaled), not the original image URL,
        // because post content references the attachment URL.
        $source_url  = wp_get_attachment_url( $post_id );
        $source_meta = wp_get_attachment_metadata( $post_id );
        $source_mime = get_post_mime_type( $post_id );

        // 2. Preserve file permissions.
        $permissions = file_exists( $this->source_file ) ? fileperms( $this->source_file ) : 0644;

        // 3. Delete old file and all thumbnails.
        $backup_sizes = get_post_meta( $post_id, '_wp_attachment_backup_sizes', true );
        $attached     = get_attached_file( $post_id );
        wp_delete_attachment_files( $post_id, $source_meta, $backup_sizes, $this->source_file );

        // wp_delete_attachment_files() skips thumbnails shared with other attachments
        // (e.g. our Duplicate feature). Force-delete any remaining thumbnail files.
        if ( ! empty( $source_meta['sizes'] ) ) {
            $source_dir = dirname( $this->source_file );
            foreach ( $source_meta['sizes'] as $size_data ) {
                $thumb_path = $source_dir . '/' . $size_data['file'];
                if ( file_exists( $thumb_path ) ) {
                    @unlink( $thumb_path );
                }
            }
        }

        // Force-delete the attached file and original if they still exist (handles -scaled).
        if ( $attached && file_exists( $attached ) && $attached !== $this->source_file ) {
            @unlink( $attached );
        }
        if ( file_exists( $this->source_file ) ) {
            @unlink( $this->source_file );
        }

        // Clean up stale backup sizes and original_image reference.
        delete_post_meta( $post_id, '_wp_attachment_backup_sizes' );

        // 4. Copy new file to target location.
        if ( ! copy( $this->tmp_file, $this->target_file ) ) {
            return new WP_Error( 'copy_failed', __( 'Failed to copy the uploaded file.' ) );
        }
        @unlink( $this->tmp_file );

        // 5. Restore file permissions.
        @chmod( $this->target_file, $permissions );

        // 6. Update the attached file path in metadata.
        update_attached_file( $post_id, $this->target_file );

        // 7. Let other plugins intercept (e.g. Smush).
        $filetype = wp_check_filetype( basename( $this->target_file ) );
        apply_filters( 'wp_handle_upload', array(
            'file' => $this->target_file,
            'url'  => wp_get_attachment_url( $post_id ),
            'type' => $filetype['type'],
        ) );

        // 8. Update MIME type if changed.
        $new_mime = $filetype['type'];
        if ( $new_mime && $new_mime !== $source_mime ) {
            wp_update_post( array(
                'ID'             => $post_id,
                'post_mime_type' => $new_mime,
            ) );
        }

        // 9. Regenerate thumbnails.
        $target_meta = wp_generate_attachment_metadata( $post_id, $this->target_file );
        wp_update_attachment_metadata( $post_id, $target_meta );

        // 10. URL replacement.
        $target_url = wp_get_attachment_url( $post_id );

        $replacer = new Inkline_URL_Replacer();
        $replacer->set_source( $source_url, $source_meta );
        $replacer->set_target( $target_url, $target_meta );

        if ( $this->mode === self::MODE_SEARCHREPLACE ) {
            // Full replacement: update title, slug, GUID, and all URLs.
            $new_title = $this->get_new_title( $target_meta );
            $wpdb->update(
                $wpdb->posts,
                array(
                    'post_title' => $new_title,
                    'post_name'  => sanitize_title( $new_title ),
                    'guid'       => $target_url,
                ),
                array( 'ID' => $post_id ),
                array( '%s', '%s', '%s' ),
                array( '%d' )
            );
            wp_cache_delete( $post_id, 'posts' );

            $replacer->replace( false );
        } else {
            // Replace mode: update thumbnail URLs. Also update the main URL if
            // it changed (e.g. scaled image replaced with a non-scaled one).
            $thumbnails_only = ( $source_url === $target_url );
            $replacer->replace( $thumbnails_only );
        }

        // 11. Update timestamps.
        $this->update_date();

        // 12. Flush caches.
        Inkline_Cache_Flusher::flush( $post_id );

        // 13. Fire action for extensibility.
        do_action( 'inkline_replace_done', $target_url, $source_url, $post_id );

        return true;
    }

    /**
     * Extract a title from the new file metadata or filename.
     *
     * @param array $meta Attachment metadata.
     * @return string
     */
    private function get_new_title( $meta ) {
        // Try EXIF title.
        if ( ! empty( $meta['image_meta']['title'] ) ) {
            return sanitize_text_field( $meta['image_meta']['title'] );
        }

        // Fall back to filename without extension.
        $name = pathinfo( basename( $this->target_file ), PATHINFO_FILENAME );
        return sanitize_text_field( str_replace( array( '-', '_' ), ' ', $name ) );
    }

    /**
     * Update the attachment post dates based on the user's selection.
     */
    private function update_date() {
        global $wpdb;

        $post_id = $this->post_id;

        switch ( $this->time_mode ) {
            case self::TIME_UPDATEALL:
                $new_date = current_time( 'mysql' );
                break;
            case self::TIME_CUSTOM:
                $new_date = $this->custom_date ?: current_time( 'mysql' );
                break;
            case self::TIME_UPDATEMODIFIED:
            default:
                // Only update post_modified, keep post_date.
                $now     = current_time( 'mysql' );
                $now_gmt = get_gmt_from_date( $now );
                $wpdb->update(
                    $wpdb->posts,
                    array(
                        'post_modified'     => $now,
                        'post_modified_gmt' => $now_gmt,
                    ),
                    array( 'ID' => $post_id ),
                    array( '%s', '%s' ),
                    array( '%d' )
                );
                wp_cache_delete( $post_id, 'posts' );
                return;
        }

        $date_gmt = get_gmt_from_date( $new_date );
        $wpdb->update(
            $wpdb->posts,
            array(
                'post_date'         => $new_date,
                'post_date_gmt'     => $date_gmt,
                'post_modified'     => $new_date,
                'post_modified_gmt' => $date_gmt,
            ),
            array( 'ID' => $post_id ),
            array( '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );
        wp_cache_delete( $post_id, 'posts' );
    }
}
