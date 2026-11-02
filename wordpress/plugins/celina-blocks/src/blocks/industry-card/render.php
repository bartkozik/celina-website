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

    if ( preg_match( '/^(mailto|tel):.+$/i', $url ) ) {
        return true;
    }

    $parts = wp_parse_url( $url );
    if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
        return false;
    }

    return in_array( strtolower( $parts['scheme'] ), [ 'http', 'https' ], true );
};

$title = sanitize_text_field( $get_string( 'title' ) );
$description = wp_kses_post( $get_string( 'description' ) );
$image_alt = trim( $get_string( 'imageAlt' ) );
$link_label = sanitize_text_field( $get_string( 'linkLabel' ) );
$link_url = trim( $get_string( 'linkUrl' ) );
$new_tab = true === ( $attributes['newTab'] ?? false );
$link_is_renderable = '' !== $link_label && $is_safe_link_url( $link_url );

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

$has_description = '' !== trim( wp_strip_all_tags( $description ) );
if ( '' === $title && ! $has_description && '' === $image_html && ! $link_is_renderable ) {
    return '';
}
?>
<article <?php echo get_block_wrapper_attributes( [ 'class' => 'celina-industry-card' ] ); ?>>
    <?php if ( '' !== $image_html ) : ?>
        <div class="celina-industry-card__media"><?php echo $image_html; ?></div>
    <?php endif; ?>
    <?php if ( '' !== $title || $has_description || $link_is_renderable ) : ?>
        <div class="celina-industry-card__body">
            <?php if ( '' !== $title ) : ?>
                <h3 class="celina-industry-card__title"><?php echo esc_html( $title ); ?></h3>
            <?php endif; ?>
            <?php if ( $has_description ) : ?>
                <div class="celina-industry-card__description"><?php echo $description; ?></div>
            <?php endif; ?>
            <?php if ( $link_is_renderable ) : ?>
                <a
                    class="celina-industry-card__link"
                    href="<?php echo esc_url( $link_url, [ 'http', 'https', 'mailto', 'tel' ] ); ?>"
                    <?php if ( $new_tab ) : ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>
                ><?php echo esc_html( $link_label ); ?></a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</article>
