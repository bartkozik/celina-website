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

$eyebrow = sanitize_text_field( $get_string( 'eyebrow' ) );
$heading = wp_kses( $get_string( 'heading' ), [ 'br' => [] ] );
$content = wp_kses_post( $get_string( 'content' ) );
$image_alt = trim( $get_string( 'imageAlt' ) );
$image_decorative = true === ( $attributes['imageDecorative'] ?? false );
$image_position = 'left' === ( $attributes['imagePosition'] ?? 'right' ) ? 'left' : 'right';
$background = 'warm' === ( $attributes['background'] ?? 'neutral' ) ? 'warm' : 'neutral';
$button_label = sanitize_text_field( $get_string( 'buttonLabel' ) );
$button_url = trim( $get_string( 'buttonUrl' ) );
$button_new_tab = true === ( $attributes['buttonNewTab'] ?? false );
$button_is_renderable = '' !== $button_label && $is_safe_link_url( $button_url );

$image_id = 0;
if ( isset( $attributes['imageId'] ) && is_numeric( $attributes['imageId'] ) ) {
    $image_id = max( 0, (int) $attributes['imageId'] );
}

$image_attributes = [
    'alt'     => $image_decorative ? '' : $image_alt,
    'loading' => 'lazy',
];
if ( $image_decorative ) {
    $image_attributes['aria-hidden'] = 'true';
}

$image_html = '';
if ( $image_id > 0 ) {
    $resolved_image = wp_get_attachment_image( $image_id, 'large', false, $image_attributes );
    $image_html = is_string( $resolved_image ) ? $resolved_image : '';
}

if ( '' === $image_html ) {
    $stored_image_url = esc_url_raw( $get_string( 'imageUrl' ), [ 'http', 'https' ] );

    if ( '' !== $stored_image_url ) {
        $decorative_attribute = $image_decorative ? ' aria-hidden="true"' : '';
        $image_html = sprintf(
            '<img src="%1$s" alt="%2$s" loading="lazy"%3$s>',
            esc_url( $stored_image_url, [ 'http', 'https' ] ),
            esc_attr( $image_decorative ? '' : $image_alt ),
            $decorative_attribute
        );
    }
}

$has_visible_content = '' !== trim( wp_strip_all_tags( $content ) );
if ( '' === $heading && ! $has_visible_content && '' === $image_html && ! $button_is_renderable ) {
    return '';
}

$classes = sprintf(
    'celina-text-image-split celina-text-image-split--image-%1$s celina-text-image-split--%2$s',
    $image_position,
    $background
);
?>
<section <?php echo get_block_wrapper_attributes( [ 'class' => $classes ] ); ?>>
    <div class="celina-text-image-split__inner">
        <?php if ( '' !== $eyebrow || '' !== $heading || $has_visible_content || $button_is_renderable ) : ?>
            <div class="celina-text-image-split__content">
                <?php if ( '' !== $eyebrow ) : ?>
                    <p class="celina-text-image-split__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
                <?php endif; ?>
                <?php if ( '' !== $heading ) : ?>
                    <h2 class="celina-text-image-split__heading"><?php echo $heading; ?></h2>
                <?php endif; ?>
                <?php if ( $has_visible_content ) : ?>
                    <div class="celina-text-image-split__prose"><?php echo $content; ?></div>
                <?php endif; ?>
                <?php if ( $button_is_renderable ) : ?>
                    <a
                        class="celina-button celina-button--primary"
                        href="<?php echo esc_url( $button_url, [ 'http', 'https', 'mailto', 'tel' ] ); ?>"
                        <?php if ( $button_new_tab ) : ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>
                    ><?php echo esc_html( $button_label ); ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ( '' !== $image_html ) : ?>
            <div class="celina-text-image-split__media"><?php echo $image_html; ?></div>
        <?php endif; ?>
    </div>
</section>
