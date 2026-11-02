<?php
defined( 'ABSPATH' ) || exit;

$get_string = static function ( string $key ) use ( $attributes ): string {
    return isset( $attributes[ $key ] ) && is_string( $attributes[ $key ] )
        ? $attributes[ $key ]
        : '';
};

$is_safe_link_url = static function ( string $candidate ): bool {
    $url = trim( $candidate );

    if ( '' === $url || '#' === $url || preg_match( '/\s/', $url ) ) {
        return false;
    }

    if ( preg_match( '#^/(?!/)#', $url ) || preg_match( '/^#[a-z][a-z0-9_.:-]*$/i', $url ) ) {
        return true;
    }

    $parts = wp_parse_url( $url );
    if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
        return false;
    }

    return in_array( strtolower( $parts['scheme'] ), [ 'http', 'https' ], true );
};

$name = sanitize_text_field( $get_string( 'name' ) );
$address = wp_kses_post( $get_string( 'address' ) );
$phone = sanitize_text_field( $get_string( 'phone' ) );
$email = sanitize_email( $get_string( 'email' ) );
$label = sanitize_text_field( $get_string( 'label' ) );
$url = trim( $get_string( 'url' ) );
$image_alt = trim( $get_string( 'imageAlt' ) );

$phone_href = preg_replace( '/[\s().-]+/', '', $phone );
$phone_is_renderable = is_string( $phone_href )
    && 1 === preg_match( '/^\+?[0-9]+$/', $phone_href );
$email_is_renderable = '' !== $email && is_email( $email );
$link_is_renderable = '' !== $label && $is_safe_link_url( $url );

$image_id = 0;
if ( isset( $attributes['imageId'] ) && is_numeric( $attributes['imageId'] ) ) {
    $image_id = max( 0, (int) $attributes['imageId'] );
}

$image_html = '';
if ( $image_id > 0 ) {
    $resolved_image = wp_get_attachment_image(
        $image_id,
        'large',
        false,
        [
            'alt'     => $image_alt,
            'loading' => 'lazy',
        ]
    );
    $image_html = is_string( $resolved_image ) ? $resolved_image : '';
}

if ( '' === $image_html ) {
    $stored_image_url = esc_url_raw( $get_string( 'imageUrl' ), [ 'http', 'https' ] );

    if ( '' !== $stored_image_url ) {
        $image_html = sprintf(
            '<img src="%1$s" alt="%2$s" loading="lazy">',
            esc_url( $stored_image_url, [ 'http', 'https' ] ),
            esc_attr( $image_alt )
        );
    }
}

$has_address = '' !== trim( wp_strip_all_tags( $address ) );
$has_contact = $has_address || $phone_is_renderable || $email_is_renderable;
if ( '' === $name && ! $has_contact && '' === $image_html && ! $link_is_renderable ) {
    return '';
}
?>
<article <?php echo get_block_wrapper_attributes( [ 'class' => 'celina-facility-card' ] ); ?>>
    <?php if ( '' !== $image_html ) : ?>
        <div class="celina-facility-card__media"><?php echo $image_html; ?></div>
    <?php endif; ?>
    <div class="celina-facility-card__body">
        <?php if ( '' !== $name ) : ?>
            <h3 class="celina-facility-card__name"><?php echo esc_html( $name ); ?></h3>
        <?php endif; ?>
        <?php if ( $has_contact ) : ?>
            <address class="celina-facility-card__contact">
                <?php if ( $has_address ) : ?>
                    <div class="celina-facility-card__address"><?php echo $address; ?></div>
                <?php endif; ?>
                <?php if ( $phone_is_renderable ) : ?>
                    <a href="tel:<?php echo esc_attr( $phone_href ); ?>"><?php echo esc_html( $phone ); ?></a>
                <?php endif; ?>
                <?php if ( $email_is_renderable ) : ?>
                    <a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
                <?php endif; ?>
            </address>
        <?php endif; ?>
        <?php if ( $link_is_renderable ) : ?>
            <a class="celina-facility-card__link" href="<?php echo esc_url( $url, [ 'http', 'https' ] ); ?>"><?php echo esc_html( $label ); ?></a>
        <?php endif; ?>
    </div>
</article>
