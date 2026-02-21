<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap inkline-replace-form">

    <div class="inkline-drop-overlay" id="inkline-drop-overlay"><h3><?php esc_html_e( 'Drop file here' ); ?></h3></div>

    <h1><?php esc_html_e( 'Replace Media' ); ?></h1>

    <form enctype="multipart/form-data" method="POST" action="<?php echo esc_url( $form_url ); ?>">
        <?php wp_nonce_field( 'inkline_replace_upload', 'inkline_replace_nonce' ); ?>
        <input type="hidden" name="attachment_id" value="<?php echo esc_attr( $attachment_id ); ?>" />

        <div class="inkline-editor">

            <!-- File chooser section -->
            <section class="inkline-section inkline-file-chooser">
                <div class="inkline-section-header"><?php esc_html_e( 'Select Replacement File' ); ?></div>

                <p class="inkline-explainer">
                    <?php
                    printf(
                        esc_html__( 'You are about to replace %1$s. This action is %2$spermanent%3$s. Select a new file from your computer or drag and drop one onto this page.' ),
                        '<strong>' . esc_html( $source_name ) . '</strong>',
                        '<strong>',
                        '</strong>'
                    );
                    ?>
                </p>

                <p><?php printf( esc_html__( 'Maximum upload size: %s' ), '<strong>' . esc_html( size_format( wp_max_upload_size() ) ) . '</strong>' ); ?></p>

                <div class="inkline-form-error inkline-error-filesize" style="display:none;">
                    <p><?php esc_html_e( 'The selected file exceeds the maximum upload size.' ); ?></p>
                </div>

                <div class="inkline-form-warning inkline-warning-filetype" style="display:none;">
                    <p><?php esc_html_e( 'The replacement file has a different type than the original. This may cause unexpected issues.' ); ?>
                    (<span class="inkline-source-type"></span> &rarr; <span class="inkline-target-type"></span>)</p>
                </div>

                <div class="inkline-previews">
                    <!-- Current file preview -->
                    <div class="inkline-preview-box inkline-preview-current">
                        <div class="inkline-preview-label"><?php esc_html_e( 'Current File' ); ?></div>
                        <?php if ( $is_image ) : ?>
                            <?php echo wp_get_attachment_image( $attachment_id, array( 300, 300 ), false, array( 'class' => 'inkline-preview-img' ) ); ?>
                        <?php else : ?>
                            <span class="dashicons dashicons-media-default inkline-preview-icon"></span>
                        <?php endif; ?>
                        <div class="inkline-preview-meta">
                            <span class="inkline-preview-name"><?php echo esc_html( $source_name ); ?></span>
                            <?php if ( $source_dimensions ) : ?>
                                <span class="inkline-preview-dimensions"><?php echo $source_dimensions; ?></span>
                            <?php endif; ?>
                            <?php if ( $source_size ) : ?>
                                <span class="inkline-preview-size"><?php echo esc_html( $source_size ); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- New file preview (populated by JS) -->
                    <div class="inkline-preview-box inkline-preview-new" id="inkline-preview-new">
                        <div class="inkline-preview-label"><?php esc_html_e( 'New File' ); ?></div>
                        <label class="inkline-upload-area" id="inkline-upload-area">
                            <span class="dashicons dashicons-upload inkline-upload-icon"></span>
                            <span class="inkline-upload-text"><?php esc_html_e( 'Click to select or drag a file here' ); ?></span>
                            <input type="file" name="userfile" id="inkline-file-input" style="display:none;" />
                        </label>
                        <div class="inkline-preview-meta">
                            <span class="inkline-preview-name" id="inkline-new-name"></span>
                            <span class="inkline-preview-dimensions" id="inkline-new-dimensions"></span>
                            <span class="inkline-preview-size" id="inkline-new-size"></span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Options columns -->
            <div class="inkline-options-row">

                <!-- Replace type -->
                <section class="inkline-section inkline-replace-options">
                    <div class="inkline-section-header"><?php esc_html_e( 'Replacement Options' ); ?></div>

                    <div class="inkline-option">
                        <label>
                            <input type="radio" name="replace_type" value="replace" <?php checked( 'replace', $settings['replace_type'] ); ?> />
                            <?php esc_html_e( 'Just replace the file' ); ?>
                        </label>
                        <p class="description"><?php printf( esc_html__( 'The file name will remain %s. Only the file content will change.' ), '<code>' . esc_html( $source_name ) . '</code>' ); ?></p>
                    </div>

                    <div class="inkline-option">
                        <label>
                            <input type="radio" name="replace_type" value="replace_and_search" <?php checked( 'replace_and_search', $settings['replace_type'] ); ?> />
                            <?php esc_html_e( 'Replace the file, use new file name, and update all links' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'The new file name will be used and all references across the site will be updated. External links to the old URL will break.' ); ?></p>
                    </div>
                </section>

                <!-- Timestamp options -->
                <section class="inkline-section inkline-timestamp-options">
                    <div class="inkline-section-header"><?php esc_html_e( 'Date Options' ); ?></div>

                    <p><?php esc_html_e( 'When replacing the file, set the media date to:' ); ?></p>

                    <ul class="inkline-timestamp-list">
                        <li>
                            <label>
                                <input type="radio" name="timestamp_replace" value="1" <?php checked( '1', $settings['timestamp_replace'] ); ?> />
                                <?php printf( esc_html__( 'Current date %s' ), '<span class="inkline-date-hint">(' . esc_html( date_i18n( 'd/M/Y H:i' ) ) . ')</span>' ); ?>
                            </label>
                        </li>
                        <li>
                            <label>
                                <input type="radio" name="timestamp_replace" value="2" <?php checked( '2', $settings['timestamp_replace'] ); ?> />
                                <?php printf( esc_html__( 'Keep original date %s' ), '<span class="inkline-date-hint">(' . esc_html( date_i18n( 'd/M/Y H:i', strtotime( $attachment->post_date ) ) ) . ')</span>' ); ?>
                            </label>
                        </li>
                        <li>
                            <label>
                                <input type="radio" name="timestamp_replace" value="3" <?php checked( '3', $settings['timestamp_replace'] ); ?> />
                                <?php esc_html_e( 'Set a custom date' ); ?>
                            </label>
                        </li>
                    </ul>

                    <div class="inkline-custom-date" id="inkline-custom-date" style="display:none;">
                        <input type="date" name="custom_date" id="inkline-date" value="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>" />
                        @ <input type="number" name="custom_hour" id="inkline-hour" value="<?php echo esc_attr( date( 'H' ) ); ?>" min="0" max="23" step="1" class="small-text" />
                        : <input type="number" name="custom_minute" id="inkline-minute" value="<?php echo esc_attr( date( 'i' ) ); ?>" min="0" max="59" step="1" class="small-text" />
                    </div>
                </section>

            </div>

            <!-- Submit / Cancel -->
            <section class="inkline-form-controls">
                <input type="submit" id="inkline-submit" class="button button-primary" value="<?php esc_attr_e( 'Upload' ); ?>" disabled="disabled" />
                <a href="#" class="button" onclick="history.back(); return false;"><?php esc_html_e( 'Cancel' ); ?></a>
            </section>

        </div>
    </form>
</div>
