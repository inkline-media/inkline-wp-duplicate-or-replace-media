<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles the server-side duplicate operation for all three entry points.
 */
class Inkline_Duplicate_Handler {

    /**
     * Build a nonced URL for the duplicate action.
     *
     * @param int $attachment_id
     * @return string
     */
    public static function get_action_url( $attachment_id ) {
        return wp_nonce_url(
            admin_url( 'admin-post.php?action=inkline_duplicate_media&attachment_id=' . (int) $attachment_id ),
            'inkline_duplicate_media_' . (int) $attachment_id
        );
    }

    /**
     * Process the duplicate request (admin_post callback).
     */
    public static function handle() {
        $attachment_id = absint( $_GET['attachment_id'] ?? 0 );

        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'inkline_duplicate_media_' . $attachment_id ) ) {
            wp_die(
                esc_html__( 'Security check failed.' ),
                esc_html__( 'Forbidden' ),
                array( 'response' => 403 )
            );
        }

        if ( ! $attachment_id ) {
            wp_die( esc_html__( 'Invalid attachment.' ), '', array( 'response' => 400 ) );
        }

        $original = get_post( $attachment_id );
        if ( ! $original || $original->post_type !== 'attachment' ) {
            wp_die( esc_html__( 'Attachment not found.' ), '', array( 'response' => 404 ) );
        }

        if ( ! inkline_can_act_on( $original ) ) {
            wp_die(
                esc_html__( 'You do not have permission to duplicate this file.' ),
                esc_html__( 'Forbidden' ),
                array( 'response' => 403 )
            );
        }

        $original_file = get_attached_file( $attachment_id );
        if ( ! $original_file || ! file_exists( $original_file ) ) {
            wp_die( esc_html__( 'Original file not found on disk.' ), '', array( 'response' => 404 ) );
        }

        $upload_dir  = wp_upload_dir();
        $upload_base = $upload_dir['basedir'];
        $real_upload = realpath( $upload_base );
        $real_file   = realpath( $original_file );

        if ( ! $real_file || ! $real_upload || strpos( $real_file, $real_upload . DIRECTORY_SEPARATOR ) !== 0 ) {
            wp_die( esc_html__( 'File is outside the uploads directory.' ), '', array( 'response' => 403 ) );
        }

        $path_info     = pathinfo( $original_file );
        $directory     = $path_info['dirname'];
        $extension     = isset( $path_info['extension'] ) ? '.' . $path_info['extension'] : '';
        $copy_basename = sanitize_file_name( $path_info['filename'] . '-copy' . $extension );
        $new_filename  = wp_unique_filename( $directory, $copy_basename );
        $new_filepath  = $directory . '/' . $new_filename;

        if ( ! copy( $original_file, $new_filepath ) ) {
            wp_die( esc_html__( 'Failed to copy file.' ), '', array( 'response' => 500 ) );
        }

        $filetype = wp_check_filetype( $new_filename );

        $new_attachment = array(
            'post_mime_type' => $filetype['type'],
            'post_title'     => sanitize_text_field( $original->post_title . ' (Copy)' ),
            'post_content'   => $original->post_content,
            'post_excerpt'   => $original->post_excerpt,
            'post_status'    => 'inherit',
        );

        $new_id = wp_insert_attachment( $new_attachment, $new_filepath );

        if ( is_wp_error( $new_id ) ) {
            @unlink( $new_filepath );
            wp_die( esc_html__( 'Failed to create attachment record.' ), '', array( 'response' => 500 ) );
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata( $new_id, $new_filepath );
        wp_update_attachment_metadata( $new_id, $metadata );

        $alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
        if ( $alt_text ) {
            update_post_meta( $new_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );
        }

        wp_safe_redirect( add_query_arg(
            array( 'inkline_dm_duplicated' => $new_id ),
            admin_url( 'upload.php' )
        ) );
        exit;
    }

    /**
     * Show success notice after duplication.
     */
    public static function admin_notice() {
        if ( ! isset( $_GET['inkline_dm_duplicated'] ) ) {
            return;
        }

        $new_id    = absint( $_GET['inkline_dm_duplicated'] );
        $edit_link = get_edit_post_link( $new_id );
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php esc_html_e( 'Media duplicated successfully.' ); ?>
                <?php if ( $edit_link ) : ?>
                    <a href="<?php echo esc_url( $edit_link ); ?>"><?php esc_html_e( 'View duplicate' ); ?></a>
                <?php endif; ?>
            </p>
        </div>
        <?php
    }
}
