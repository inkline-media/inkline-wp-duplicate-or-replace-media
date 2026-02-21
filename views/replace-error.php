<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap inkline-replace-form">
    <h1><?php esc_html_e( 'Replace Media' ); ?></h1>
    <div class="inkline-editor">
        <section class="inkline-section inkline-error-message">
            <div class="inkline-section-header"><?php esc_html_e( 'Error' ); ?></div>
            <p><?php echo esc_html( $error_message ); ?></p>
            <p><a href="#" class="button" onclick="history.back(); return false;"><?php esc_html_e( 'Go Back' ); ?></a></p>
        </section>
    </div>
</div>
