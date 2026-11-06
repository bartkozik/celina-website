<?php
/**
 * Plugin Name: CELINA Blocks
 * Description: Native Gutenberg blocks for CELINA sites.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Text Domain: celina-blocks
 */
defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/news-grid.php';
require_once __DIR__ . '/includes/editor-settings.php';
require_once __DIR__ . '/includes/paycor-jobs.php';
require_once __DIR__ . '/includes/hero.php';

function celina_block_slugs(): array {
    $decoded = json_decode( (string) file_get_contents( __DIR__ . '/blocks.json' ), true );
    return is_array( $decoded ) ? array_values( array_filter( $decoded, 'is_string' ) ) : [];
}

function celina_blocks_categories( array $categories ): array {
    array_unshift( $categories, [
        'slug'  => 'celina-redesigned',
        'title' => __( 'CELINA REDESIGNED', 'celina-blocks' ),
    ], [
        'slug'  => 'celina',
        'title' => __( 'CELINA', 'celina-blocks' ),
    ] );
    return $categories;
}
add_filter( 'block_categories_all', 'celina_blocks_categories' );

/** ZIP releases normalize mtimes; use stylesheet contents for cache invalidation. */
function celina_blocks_version_styles( WP_Block_Type $block ): void {
    $styles = wp_styles();
    $build_path = wp_normalize_path( __DIR__ . '/build/' );
    $handles = array_unique( array_merge( $block->style_handles, $block->editor_style_handles, $block->view_style_handles ) );
    foreach ( $handles as $handle ) {
        $style = $styles->registered[ $handle ] ?? null;
        $path = $style ? ( $style->extra['path'] ?? '' ) : '';
        // Do not change external/shared styles that this plugin does not own.
        if ( ! is_string( $path ) || 0 !== strpos( wp_normalize_path( $path ), $build_path ) || ! is_readable( $path ) ) {
            continue;
        }
        $version = hash_file( 'sha256', $path );
        if ( false !== $version ) {
            $style->ver = $version;
        }
    }
}

function celina_blocks_register(): void {
    foreach ( celina_block_slugs() as $slug ) {
        $directory = __DIR__ . '/build/blocks/' . $slug;
        if ( is_readable( $directory . '/block.json' ) ) {
            $block = register_block_type( $directory );
            if ( $block instanceof WP_Block_Type ) {
                celina_blocks_version_styles( $block );
            }
        }
    }
}
add_action( 'init', 'celina_blocks_register' );
