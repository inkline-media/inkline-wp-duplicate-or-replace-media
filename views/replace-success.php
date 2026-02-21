<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap inkline-replace-form">
    <div class="inkline-editor">
        <section class="inkline-section inkline-success-message">
            <div class="inkline-section-header"><?php esc_html_e( 'File Replaced Successfully' ); ?></div>
            <p><?php esc_html_e( 'The media file has been replaced. You will be redirected to the edit page shortly.' ); ?></p>
            <p><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Click here if you are not redirected automatically.' ); ?></a></p>
        </section>
    </div>
</div>
<script>setTimeout(function(){ window.location.href = <?php echo wp_json_encode( $edit_url ); ?>; }, 2000);</script>
