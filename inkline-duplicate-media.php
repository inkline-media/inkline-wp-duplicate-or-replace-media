<?php
/**
 * Plugin Name: Inkline Duplicate or Replace Media
 * Description: Adds Duplicate and Replace actions to the WordPress Media Library. Duplicate creates an independent copy; Replace swaps the underlying file and optionally updates all URLs site-wide.
 * Version: 4.0.0
 * Requires at least: 6.9
 * Requires PHP: 8.2
 * Author: Inkline Media
 * License: GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ---------------------------------------------------------------------------
// Auto-update from GitHub via plugin-update-checker
// ---------------------------------------------------------------------------

require_once __DIR__ . '/vendor/plugin-update-checker-5.6/load-v5p6.php';

$inkline_dm_update_checker = YahnisElsts\PluginUpdateChecker\v5p6\PucFactory::buildUpdateChecker(
    'https://github.com/inkline-media/inkline-wp-duplicate-or-replace-media/',
    __FILE__,
    'inkline-duplicate-or-replace-media'
);
$inkline_dm_update_checker->setBranch( 'main' );

// ---------------------------------------------------------------------------
// Includes
// ---------------------------------------------------------------------------

require_once __DIR__ . '/includes/class-duplicate-handler.php';
require_once __DIR__ . '/includes/class-replace-page.php';
require_once __DIR__ . '/includes/class-replace-controller.php';
require_once __DIR__ . '/includes/class-url-replacer.php';
require_once __DIR__ . '/includes/class-cache-flusher.php';
require_once __DIR__ . '/includes/class-elementor-compat.php';

// ---------------------------------------------------------------------------
// Shared permission check
// ---------------------------------------------------------------------------

/**
 * Check whether the current user can act on (duplicate or replace) a given attachment.
 *
 * @param WP_Post|mixed $post The attachment post object.
 * @return bool
 */
function inkline_can_act_on( $post ) {
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

// ---------------------------------------------------------------------------
// 1. Media Library list view — row actions
// ---------------------------------------------------------------------------

add_filter( 'media_row_actions', function ( $actions, $post ) {
    if ( ! inkline_can_act_on( $post ) ) {
        return $actions;
    }

    $actions['inkline_duplicate'] = '<a href="' . esc_url( Inkline_Duplicate_Handler::get_action_url( $post->ID ) ) . '" aria-label="' . esc_attr__( 'Duplicate this media' ) . '">' . esc_html__( 'Duplicate' ) . '</a>';

    $actions['inkline_replace'] = '<a href="' . esc_url( Inkline_Replace_Page::get_page_url( $post->ID ) ) . '" aria-label="' . esc_attr__( 'Replace this media' ) . '">' . esc_html__( 'Replace media' ) . '</a>';

    return $actions;
}, 10, 2 );

// ---------------------------------------------------------------------------
// 2. Edit Media page — sidebar meta boxes
// ---------------------------------------------------------------------------

add_action( 'add_meta_boxes_attachment', function ( $post ) {
    if ( ! is_object( $post ) || ! inkline_can_act_on( $post ) ) {
        return;
    }

    // Duplicate meta box
    add_meta_box(
        'inkline-duplicate-media',
        __( 'Duplicate Media' ),
        function ( $post ) {
            $url = Inkline_Duplicate_Handler::get_action_url( $post->ID );
            ?>
            <p><?php esc_html_e( 'Create an independent copy of this media file. The original will not be affected.' ); ?></p>
            <a href="<?php echo esc_url( $url ); ?>" class="button button-secondary"><?php esc_html_e( 'Duplicate this file' ); ?></a>
            <?php
        },
        'attachment',
        'side',
        'low'
    );

    // Replace meta box
    add_meta_box(
        'inkline-replace-media',
        __( 'Replace Media' ),
        function ( $post ) {
            $url = Inkline_Replace_Page::get_page_url( $post->ID );
            ?>
            <p><?php esc_html_e( 'Upload a new file to replace the current one. All existing links can be updated automatically.' ); ?></p>
            <a href="<?php echo esc_url( $url ); ?>" class="button button-secondary"><?php esc_html_e( 'Upload a new file' ); ?></a>
            <?php
        },
        'attachment',
        'side',
        'low'
    );
} );

// ---------------------------------------------------------------------------
// 3. Media modal & grid-view sidebar — attachment fields
// ---------------------------------------------------------------------------

add_filter( 'attachment_fields_to_edit', function ( $form_fields, $post ) {
    if ( ! inkline_can_act_on( $post ) ) {
        return $form_fields;
    }

    // Skip on the Edit Media screen — the meta boxes handle that context.
    if ( function_exists( 'get_current_screen' ) ) {
        $screen = get_current_screen();
        if ( $screen && $screen->id === 'attachment' ) {
            return $form_fields;
        }
    }

    $dup_url = Inkline_Duplicate_Handler::get_action_url( $post->ID );
    $rep_url = Inkline_Replace_Page::get_page_url( $post->ID );

    $form_fields['inkline-duplicate-media'] = array(
        'label' => esc_html__( 'Duplicate media' ),
        'input' => 'html',
        'html'  => '<a class="button-secondary" href="' . esc_url( $dup_url ) . '">' . esc_html__( 'Duplicate this file' ) . '</a>',
        'helps' => esc_html__( 'Create an independent copy of this file.' ),
    );

    $form_fields['inkline-replace-media'] = array(
        'label' => esc_html__( 'Replace media' ),
        'input' => 'html',
        'html'  => '<a class="button-secondary" href="' . esc_url( $rep_url ) . '">' . esc_html__( 'Replace this file' ) . '</a>',
        'helps' => esc_html__( 'Upload a new file to replace this one.' ),
    );

    return $form_fields;
}, 10, 2 );

// ---------------------------------------------------------------------------
// Hook handlers
// ---------------------------------------------------------------------------

add_action( 'admin_post_inkline_duplicate_media', array( 'Inkline_Duplicate_Handler', 'handle' ) );
add_action( 'admin_notices', array( 'Inkline_Duplicate_Handler', 'admin_notice' ) );
add_action( 'admin_menu', array( 'Inkline_Replace_Page', 'register_page' ) );

// Initialize integrations.
new Inkline_Elementor_Compat();
