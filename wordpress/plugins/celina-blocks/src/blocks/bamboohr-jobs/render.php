<?php
defined( 'ABSPATH' ) || exit;

$heading = isset( $attributes['heading'] ) && is_string( $attributes['heading'] )
    ? sanitize_text_field( $attributes['heading'] )
    : 'Current openings';
$fallback_text = isset( $attributes['fallbackText'] ) && is_string( $attributes['fallbackText'] )
    ? sanitize_text_field( $attributes['fallbackText'] )
    : 'Job listings will appear here once BambooHR is connected.';
?>
<section <?php echo get_block_wrapper_attributes( [ 'class' => 'celina-bamboohr-jobs' ] ); ?>>
    <?php if ( '' !== $heading ) : ?>
        <h2><?php echo esc_html( $heading ); ?></h2>
    <?php endif; ?>
    <div class="celina-bamboohr-jobs__status" role="status">
        <p><?php esc_html_e( 'Replace this legacy block with Paycor Jobs.', 'celina-blocks' ); ?></p>
        <?php if ( '' !== $fallback_text ) : ?>
            <p><?php echo esc_html( $fallback_text ); ?></p>
        <?php endif; ?>
    </div>
</section>
