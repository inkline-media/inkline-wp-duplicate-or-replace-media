<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles the replace media admin page — form display and upload processing.
 */
class Inkline_Replace_Page {

    const PAGE_SLUG = 'inkline-replace-media';

    /**
     * Register the hidden submenu page under Media.
     */
    public static function register_page() {
        $hook = add_submenu_page(
            'upload.php',
            __( 'Replace Media' ),
            __( 'Replace Media' ),
            'upload_files',
            self::PAGE_SLUG,
            array( __CLASS__, 'route' )
        );

        // Hide from the menu — users reach it via direct links only.
        add_action( 'admin_head', function () {
            remove_submenu_page( 'upload.php', Inkline_Replace_Page::PAGE_SLUG );
        } );

        // Enqueue assets only on our page.
        add_action( 'admin_enqueue_scripts', function ( $hook_suffix ) use ( $hook ) {
            if ( $hook_suffix !== $hook ) {
                return;
            }
            wp_enqueue_style(
                'inkline-replace-form',
                plugins_url( 'assets/css/replace-form.css', dirname( __FILE__ ) ),
                array(),
                '4.0.1'
            );
            wp_enqueue_script(
                'inkline-replace-form',
                plugins_url( 'assets/js/replace-form.js', dirname( __FILE__ ) ),
                array(),
                '4.0.1',
                true
            );
        } );
    }

    /**
     * Build the URL to the replace page for a given attachment.
     *
     * @param int $attachment_id
     * @return string
     */
    public static function get_page_url( $attachment_id ) {
        return wp_nonce_url(
            admin_url( 'upload.php?page=' . self::PAGE_SLUG . '&attachment_id=' . (int) $attachment_id ),
            'inkline_replace_' . (int) $attachment_id
        );
    }

    /**
     * Route to the correct handler based on request method.
     */
    public static function route() {
        if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
            self::process_upload();
        } else {
            self::show_form();
        }
    }

    /**
     * Display the replace form.
     */
    private static function show_form() {
        $attachment_id = absint( $_GET['attachment_id'] ?? 0 );

        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'inkline_replace_' . $attachment_id ) ) {
            wp_die( esc_html__( 'Security check failed.' ), esc_html__( 'Forbidden' ), array( 'response' => 403 ) );
        }

        if ( ! $attachment_id ) {
            wp_die( esc_html__( 'Invalid attachment.' ), '', array( 'response' => 400 ) );
        }

        $attachment = get_post( $attachment_id );
        if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
            wp_die( esc_html__( 'Attachment not found.' ), '', array( 'response' => 404 ) );
        }

        if ( ! inkline_can_act_on( $attachment ) ) {
            wp_die( esc_html__( 'You do not have permission to replace this file.' ), esc_html__( 'Forbidden' ), array( 'response' => 403 ) );
        }

        $source_file = get_attached_file( $attachment_id );
        $source_name = $source_file ? basename( $source_file ) : __( '(unknown)' );
        $source_size = $source_file && file_exists( $source_file ) ? size_format( filesize( $source_file ) ) : '';
        $source_mime = get_post_mime_type( $attachment_id );
        $is_image    = wp_attachment_is( 'image', $attachment_id );

        // Get image dimensions if applicable.
        $source_dimensions = '';
        if ( $is_image ) {
            $meta = wp_get_attachment_metadata( $attachment_id );
            if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
                $source_dimensions = $meta['width'] . ' &times; ' . $meta['height'];
            }
        }

        // Load saved settings or defaults.
        $settings = get_option( 'inkline_replace_settings', array() );
        $settings = wp_parse_args( $settings, array(
            'replace_type'      => 'replace',
            'timestamp_replace' => '2',
        ) );

        // Localize JS options.
        wp_localize_script( 'inkline-replace-form', 'inklineReplaceOptions', array(
            'maxFileSize'  => wp_max_upload_size(),
            'allowedMimes' => array_values( get_allowed_mime_types() ),
            'sourceType'   => $source_mime,
        ) );

        // Form URL for POST submission.
        $form_url = admin_url( 'upload.php?page=' . self::PAGE_SLUG );

        include __DIR__ . '/../views/replace-form.php';
    }

    /**
     * Process the uploaded replacement file.
     */
    private static function process_upload() {
        // Verify nonce.
        if ( ! wp_verify_nonce( $_POST['inkline_replace_nonce'] ?? '', 'inkline_replace_upload' ) ) {
            self::show_error( __( 'Security check failed.' ) );
            return;
        }

        // Verify capability.
        if ( ! current_user_can( 'upload_files' ) ) {
            self::show_error( __( 'You do not have permission to upload files.' ) );
            return;
        }

        $attachment_id = absint( $_POST['attachment_id'] ?? 0 );
        if ( ! $attachment_id ) {
            self::show_error( __( 'Invalid attachment.' ) );
            return;
        }

        $attachment = get_post( $attachment_id );
        if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
            self::show_error( __( 'Attachment not found.' ) );
            return;
        }

        if ( ! inkline_can_act_on( $attachment ) ) {
            self::show_error( __( 'You do not have permission to replace this file.' ) );
            return;
        }

        // Validate the uploaded file.
        if ( empty( $_FILES['userfile'] ) || $_FILES['userfile']['error'] !== UPLOAD_ERR_OK ) {
            $error_msg = __( 'No file was uploaded or the upload failed.' );
            if ( ! empty( $_FILES['userfile']['error'] ) && $_FILES['userfile']['error'] === UPLOAD_ERR_INI_SIZE ) {
                $error_msg = __( 'The uploaded file exceeds the maximum upload size.' );
            }
            self::show_error( $error_msg );
            return;
        }

        $tmp_file     = $_FILES['userfile']['tmp_name'];
        $new_filename = sanitize_file_name( $_FILES['userfile']['name'] );

        if ( ! is_uploaded_file( $tmp_file ) ) {
            self::show_error( __( 'Invalid upload.' ) );
            return;
        }

        // Validate file type.
        $filetype = wp_check_filetype_and_ext( $tmp_file, $new_filename );
        if ( ! $filetype['ext'] && ! current_user_can( 'unfiltered_upload' ) ) {
            self::show_error( __( 'This file type is not allowed.' ) );
            return;
        }

        // Parse replace options.
        $replace_type = sanitize_text_field( $_POST['replace_type'] ?? 'replace' );
        if ( ! in_array( $replace_type, array( 'replace', 'replace_and_search' ), true ) ) {
            $replace_type = 'replace';
        }

        $timestamp_replace = intval( $_POST['timestamp_replace'] ?? 2 );
        if ( ! in_array( $timestamp_replace, array( 1, 2, 3 ), true ) ) {
            $timestamp_replace = 2;
        }

        // Save user preferences for next time.
        update_option( 'inkline_replace_settings', array(
            'replace_type'      => $replace_type,
            'timestamp_replace' => $timestamp_replace,
        ), false );

        // Build custom date if needed.
        $custom_date = null;
        if ( $timestamp_replace === 3 ) {
            $date_val   = sanitize_text_field( $_POST['custom_date'] ?? '' );
            $hour_val   = str_pad( intval( $_POST['custom_hour'] ?? 0 ), 2, '0', STR_PAD_LEFT );
            $minute_val = str_pad( intval( $_POST['custom_minute'] ?? 0 ), 2, '0', STR_PAD_LEFT );

            if ( $date_val ) {
                $parsed = \DateTime::createFromFormat( 'Y-m-d', $date_val );
                if ( $parsed ) {
                    $custom_date = $parsed->format( 'Y-m-d' ) . ' ' . $hour_val . ':' . $minute_val . ':00';
                }
            }

            if ( ! $custom_date ) {
                $custom_date = current_time( 'mysql' );
            }
        }

        // Execute the replacement.
        $controller = new Inkline_Replace_Controller( $attachment_id );

        $mode = ( $replace_type === 'replace_and_search' )
            ? Inkline_Replace_Controller::MODE_SEARCHREPLACE
            : Inkline_Replace_Controller::MODE_REPLACE;

        $result = $controller->setup_params( array(
            'mode'         => $mode,
            'tmp_file'     => $tmp_file,
            'new_filename' => $new_filename,
            'time_mode'    => $timestamp_replace,
            'custom_date'  => $custom_date,
        ) );

        if ( is_wp_error( $result ) ) {
            self::show_error( $result->get_error_message() );
            return;
        }

        $run_result = $controller->run();

        if ( is_wp_error( $run_result ) ) {
            self::show_error( $run_result->get_error_message() );
            return;
        }

        // Show success.
        $post_id  = $attachment_id;
        $edit_url = admin_url( 'post.php?action=edit&post=' . $attachment_id );
        include __DIR__ . '/../views/replace-success.php';
    }

    /**
     * Show the error view.
     *
     * @param string $message
     */
    private static function show_error( $message ) {
        $error_message = $message;
        include __DIR__ . '/../views/replace-error.php';
    }
}
