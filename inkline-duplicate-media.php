<?php
/**
 * Plugin Name: Inkline Duplicate Media
 * Description: Adds a "Duplicate" action in the Media Library list view, Edit Media page, and media modal, creating a copy of the media file as a new attachment.
 * Version: 3.1.0
 * Author: Inkline Media
 * License: GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Check whether the current user has permission to duplicate a given attachment.
 *
 * Requires both the general 'upload_files' capability (to create new media)
 * and 'edit_post' on the specific attachment (to read/access the original).
 * Mirrors the per-post permission check used by Enable Media Replace.
 *
 * @param WP_Post|mixed $post The attachment post object.
 * @return bool
 */
function inkline_dm_can_duplicate( $post ) {
    if ( ! is_object( $post ) || ! isset( $post->ID ) ) {
        return false;
    }
    if ( $post->post_type !== 'attachment' ) {
        return false;
    }
    if ( ! current_user_can( 'upload_files' ) ) {
        return false;
    }
    if ( ! current_user_can( 'edit_post', $post->ID ) ) {
        return false;
    }
    return true;
}

/**
 * Build a nonced URL for the duplicate action.
 *
 * @param int $attachment_id
 * @return string
 */
function inkline_dm_get_duplicate_url( $attachment_id ) {
    return wp_nonce_url(
        admin_url( 'admin-post.php?action=inkline_duplicate_media&attachment_id=' . (int) $attachment_id ),
        'inkline_duplicate_media_' . (int) $attachment_id
    );
}

// ---------------------------------------------------------------------------
// 1. Media Library list view — row action link
//    Hook: media_row_actions (same as EMR's add_media_action)
// ---------------------------------------------------------------------------

function inkline_dm_row_action( $actions, $post ) {
    if ( inkline_dm_can_duplicate( $post ) ) {
        $actions['duplicate'] = '<a href="' . esc_url( inkline_dm_get_duplicate_url( $post->ID ) ) . '" aria-label="' . esc_attr__( 'Duplicate this media' ) . '">' . esc_html__( 'Duplicate' ) . '</a>';
    }
    return $actions;
}
add_filter( 'media_row_actions', 'inkline_dm_row_action', 10, 2 );

// ---------------------------------------------------------------------------
// 2. Edit Media page — sidebar meta box
//    Hook: add_meta_boxes_attachment (same as EMR's add_meta_boxes)
// ---------------------------------------------------------------------------

function inkline_dm_register_meta_box( $post ) {
    if ( ! is_object( $post ) || ! inkline_dm_can_duplicate( $post ) ) {
        return;
    }
    add_meta_box(
        'inkline-duplicate-media',
        __( 'Duplicate Media' ),
        'inkline_dm_meta_box_render',
        'attachment',
        'side',
        'low'
    );
}
add_action( 'add_meta_boxes_attachment', 'inkline_dm_register_meta_box' );

function inkline_dm_meta_box_render( $post ) {
    $url = inkline_dm_get_duplicate_url( $post->ID );
    ?>
    <p><?php esc_html_e( 'Create an independent copy of this media file. The original will not be affected.' ); ?></p>
    <a href="<?php echo esc_url( $url ); ?>" class="button button-secondary"><?php esc_html_e( 'Duplicate this file' ); ?></a>
    <?php
}

// ---------------------------------------------------------------------------
// 3. Media modal & grid-view sidebar — attachment field
//    Hook: attachment_fields_to_edit (same as EMR's attachment_editor)
// ---------------------------------------------------------------------------

function inkline_dm_attachment_fields( $form_fields, $post ) {
    if ( ! inkline_dm_can_duplicate( $post ) ) {
        return $form_fields;
    }

    // Skip on the Edit Media screen — the meta box handles that context.
    if ( function_exists( 'get_current_screen' ) ) {
        $screen = get_current_screen();
        if ( $screen && $screen->id === 'attachment' ) {
            return $form_fields;
        }
    }

    $url = inkline_dm_get_duplicate_url( $post->ID );

    $form_fields['inkline-duplicate-media'] = array(
        'label' => esc_html__( 'Duplicate media' ),
        'input' => 'html',
        'html'  => '<a class="button-secondary" href="' . esc_url( $url ) . '">' . esc_html__( 'Duplicate this file' ) . '</a>',
        'helps' => esc_html__( 'Create an independent copy of this file. The original will not be affected.' ),
    );

    return $form_fields;
}
add_filter( 'attachment_fields_to_edit', 'inkline_dm_attachment_fields', 10, 2 );

// ---------------------------------------------------------------------------
// Server-side handler — processes the duplicate for all three entry points
// ---------------------------------------------------------------------------

function inkline_dm_handle_duplicate() {
    $attachment_id = absint( $_GET['attachment_id'] ?? 0 );

    // 1. Verify nonce (CSRF protection)
    if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'inkline_duplicate_media_' . $attachment_id ) ) {
        wp_die(
            esc_html__( 'Security check failed.' ),
            esc_html__( 'Forbidden' ),
            array( 'response' => 403 )
        );
    }

    // 2. Validate attachment ID
    if ( ! $attachment_id ) {
        wp_die( esc_html__( 'Invalid attachment.' ), '', array( 'response' => 400 ) );
    }

    // 3. Load the original attachment
    $original = get_post( $attachment_id );
    if ( ! $original || $original->post_type !== 'attachment' ) {
        wp_die( esc_html__( 'Attachment not found.' ), '', array( 'response' => 404 ) );
    }

    // 4. Check per-post permissions (upload_files + edit_post)
    if ( ! inkline_dm_can_duplicate( $original ) ) {
        wp_die(
            esc_html__( 'You do not have permission to duplicate this file.' ),
            esc_html__( 'Forbidden' ),
            array( 'response' => 403 )
        );
    }

    // 5. Get and validate the original file path
    $original_file = get_attached_file( $attachment_id );
    if ( ! $original_file || ! file_exists( $original_file ) ) {
        wp_die( esc_html__( 'Original file not found on disk.' ), '', array( 'response' => 404 ) );
    }

    // 6. Path traversal protection — verify file is inside the uploads directory
    $upload_dir  = wp_upload_dir();
    $upload_base = $upload_dir['basedir'];
    $real_upload = realpath( $upload_base );
    $real_file   = realpath( $original_file );

    if ( ! $real_file || ! $real_upload || strpos( $real_file, $real_upload . DIRECTORY_SEPARATOR ) !== 0 ) {
        wp_die( esc_html__( 'File is outside the uploads directory.' ), '', array( 'response' => 403 ) );
    }

    // 7. Build a unique copy filename using WordPress core functions
    $path_info     = pathinfo( $original_file );
    $directory     = $path_info['dirname'];
    $extension     = isset( $path_info['extension'] ) ? '.' . $path_info['extension'] : '';
    $copy_basename = sanitize_file_name( $path_info['filename'] . '-copy' . $extension );
    $new_filename  = wp_unique_filename( $directory, $copy_basename );
    $new_filepath  = $directory . '/' . $new_filename;

    // 8. Copy the physical file
    if ( ! copy( $original_file, $new_filepath ) ) {
        wp_die( esc_html__( 'Failed to copy file.' ), '', array( 'response' => 500 ) );
    }

    // 9. Create the new attachment post
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

    // 10. Generate thumbnails and attachment metadata
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $metadata = wp_generate_attachment_metadata( $new_id, $new_filepath );
    wp_update_attachment_metadata( $new_id, $metadata );

    // 11. Copy alt text if present
    $alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
    if ( $alt_text ) {
        update_post_meta( $new_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );
    }

    // 12. Redirect back to media library — use wp_safe_redirect to prevent open redirects
    wp_safe_redirect( add_query_arg(
        array( 'inkline_dm_duplicated' => $new_id ),
        admin_url( 'upload.php' )
    ) );
    exit;
}
add_action( 'admin_post_inkline_duplicate_media', 'inkline_dm_handle_duplicate' );

// ---------------------------------------------------------------------------
// Admin notice after successful duplication
// ---------------------------------------------------------------------------

function inkline_dm_admin_notice() {
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
add_action( 'admin_notices', 'inkline_dm_admin_notice' );
