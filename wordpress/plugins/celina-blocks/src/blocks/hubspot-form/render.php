<?php
defined( 'ABSPATH' ) || exit;

$heading = isset( $attributes['heading'] ) && is_string( $attributes['heading'] )
    ? sanitize_text_field( $attributes['heading'] )
    : 'Contact our team';
$fallback_text = isset( $attributes['fallbackText'] ) && is_string( $attributes['fallbackText'] )
    ? sanitize_text_field( $attributes['fallbackText'] )
    : 'Contact us directly if the form is unavailable.';
$region = isset( $attributes['region'] ) && is_string( $attributes['region'] )
    ? $attributes['region']
    : 'na1';
$portal_id = isset( $attributes['portalId'] ) && is_string( $attributes['portalId'] )
    ? $attributes['portalId']
    : '';
$form_id = isset( $attributes['formId'] ) && is_string( $attributes['formId'] )
    ? $attributes['formId']
    : '';

$valid_region = in_array( $region, [ 'na1', 'eu1' ], true );
$valid_portal_id = 1 === preg_match( '/\A\d+\z/', $portal_id );
$valid_form_id = 1 === preg_match(
    '/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i',
    $form_id
);
$configured = $valid_region && $valid_portal_id && $valid_form_id;

$target_id = '';
if ( $configured ) {
    $script_handle = 'celina-hubspot-forms';
    $script_url = 'https://js.hsforms.net/forms/embed/v2.js';
    $target_id = wp_unique_id( 'celina-hubspot-form-' );

    $registered_script = wp_scripts()->registered[ $script_handle ] ?? null;
    if ( ! $registered_script || $script_url !== $registered_script->src ) {
        wp_dequeue_script( $script_handle );
        wp_deregister_script( $script_handle );
        wp_register_script( $script_handle, $script_url, [], null, true );
    }

    wp_enqueue_script( $script_handle );
    $configuration = wp_json_encode(
        [
            'region'   => $region,
            'portalId' => $portal_id,
            'formId'   => $form_id,
            'target'   => '#' . $target_id,

            // Empty css/cssRequired makes HubSpot render the fields inline in
            // this page instead of its own iframe, so CELINA styling applies.
            'css'      => '',
            'cssClass' => 'celina-hubspot-form__form',
        ],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );

    if ( is_string( $configuration ) ) {
        wp_add_inline_script(
            $script_handle,
            '(function(){if(window.hbspt&&window.hbspt.forms){window.hbspt.forms.create(' . $configuration . ');}})();',
            'after'
        );
    }
}
?>
<section <?php echo get_block_wrapper_attributes( [ 'class' => 'celina-hubspot-form' ] ); ?>>
    <?php if ( '' !== $heading ) : ?>
        <h2><?php echo esc_html( $heading ); ?></h2>
    <?php endif; ?>
    <?php if ( $configured ) : ?>
        <div
            id="<?php echo esc_attr( $target_id ); ?>"
            class="celina-hubspot-form__target"
            data-hubspot-target="<?php echo esc_attr( $target_id ); ?>"
        ></div>
        <?php if ( '' !== $fallback_text ) : ?>
            <p class="celina-hubspot-form__fallback"><?php echo esc_html( $fallback_text ); ?></p>
        <?php endif; ?>
    <?php else : ?>
        <div class="celina-hubspot-form__status" role="status">
            <p><?php esc_html_e( 'HubSpot form is not configured in this workbench.', 'celina-blocks' ); ?></p>
            <?php if ( '' !== $fallback_text ) : ?>
                <p><?php echo esc_html( $fallback_text ); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
