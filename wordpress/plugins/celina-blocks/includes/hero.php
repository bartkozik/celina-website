<?php
/** Runtime attachment URLs for the existing static Hero block. */
defined( 'ABSPATH' ) || exit;

/** Replace only background-image declarations, retaining the other CSS bytes. */
function celina_hero_background_style( string $style, string $url ): string {
    $start = 0;
    $quote = '';
    $depth = 0;
    $comment = false;
    $result = '';
    $length = strlen( $style );

    // A semicolon inside a string, comment or CSS function is not a separator.
    for ( $index = 0; $index <= $length; $index++ ) {
        $char = $index < $length ? $style[ $index ] : '';
        $next = $index + 1 < $length ? $style[ $index + 1 ] : '';
        if ( $comment ) {
            if ( '*' === $char && '/' === $next ) {
                $comment = false;
                $index++;
            }
            continue;
        }
        if ( '\\' === $char ) {
            if ( '' === $next ) {
                return $style;
            }
            $index++;
            continue;
        }
        if ( '' !== $quote ) {
            if ( $quote === $char ) {
                $quote = '';
            }
            continue;
        }
        if ( '/' === $char && '*' === $next ) {
            $comment = true;
            $index++;
            continue;
        }
        if ( '"' === $char || "'" === $char ) {
            $quote = $char;
            continue;
        }
        if ( false !== strpos( '([{', $char ) && '' !== $char ) {
            $depth++;
        } elseif ( false !== strpos( ')]}', $char ) && '' !== $char ) {
            $depth--;
            if ( $depth < 0 ) {
                return $style;
            }
        }
        if ( ( ';' === $char && 0 === $depth ) || $index === $length ) {
            $declaration = substr( $style, $start, $index - $start );
            $result .= preg_replace_callback(
                '/^(\s*background-image\s*:\s*).*?(\s*!important\s*|\s*)$/is',
                static function ( array $match ) use ( $url ): string {
                    return $match[1] . 'url("' . $url . '")' . $match[2];
                },
                $declaration
            ) ?? $declaration;
            $result .= $char;
            $start = $index + 1;
        }
    }

    // Unexpected CSS should retain its original fallback, not be partially edited.
    return $comment || '' !== $quote || 0 !== $depth ? $style : $result;
}

function celina_hero_attachment_background( string $html, array $block ): string {
    $attributes = $block['attrs'] ?? [];
    $id = $attributes['backgroundId'] ?? 0;
    $saved_url = $attributes['backgroundUrl'] ?? '';
    if ( ! is_numeric( $id ) || (int) $id <= 0 || ! is_string( $saved_url ) || '' === trim( $saved_url ) || ! wp_attachment_is_image( (int) $id ) ) {
        return $html;
    }

    // Only the block's owning root section may change; never search descendants.
    $processor = new WP_HTML_Tag_Processor( $html );
    if ( ! $processor->next_tag() || 'SECTION' !== $processor->get_tag() || ! $processor->has_class( 'celina-hero' ) ) {
        return $html;
    }
    $style = $processor->get_attribute( 'style' );
    if ( ! is_string( $style ) ) {
        return $html;
    }

    $url = wp_get_attachment_image_url( (int) $id, 'full' );
    if ( ! is_string( $url ) || '' === $url ) {
        return $html;
    }
    // Encode CSS delimiters before URL sanitization; retain query-string ampersands.
    $url = preg_replace_callback( '/[\x00-\x20\x7f"\'()\\\\<>]/', static function ( array $match ): string {
        return rawurlencode( $match[0] );
    }, $url );
    $url = esc_url_raw( (string) $url, [ 'http', 'https' ] );
    $parts = wp_parse_url( $url );
    if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! in_array( strtolower( $parts['scheme'] ?? '' ), [ 'http', 'https' ], true ) ) {
        return $html;
    }

    $updated = celina_hero_background_style( $style, $url );
    if ( $updated === $style ) {
        return $html;
    }
    // The processor escapes the complete style attribute once, including quotes/&.
    $processor->set_attribute( 'style', $updated );
    return $processor->get_updated_html();
}
// Run after the theme's priority-10 heading/emphasis filters, preserving their output.
add_filter( 'render_block_celina/hero', 'celina_hero_attachment_background', 20, 2 );
