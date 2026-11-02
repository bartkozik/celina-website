<?php
defined( 'ABSPATH' ) || exit;

function celina_theme_setup(): void {
	remove_theme_support( 'custom-logo' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'block-template-parts' );
	add_editor_style( [ 'assets/css/tokens.css', 'assets/css/content-templates.css', 'assets/css/sales-patterns.css', 'assets/css/block-shell.css' ] );

	add_theme_support(
		'custom-logo',
		[
			'height'      => 80,
			'width'       => 300,
			'flex-height' => true,
			'flex-width'  => true,
		]
	);

	register_nav_menus(
		[
			'celina-utility' => __( 'Utility Bar', 'celina' ),
			'celina-primary' => __( 'Primary Navigation', 'celina' ),
			'celina-footer'  => __( 'Footer Sites', 'celina' ),
			'celina-legal'   => __( 'Footer Legal', 'celina' ),
			'celina-social'  => __( 'Footer Social', 'celina' ),
		]
	);
}

add_action( 'after_setup_theme', 'celina_theme_setup', 20 );

/**
 * Release archives normalize mtimes, so cache keys must follow file contents.
 */
function celina_asset_version( string $relative_path ): string {
	return hash_file( 'sha256', get_stylesheet_directory() . '/' . $relative_path );
}

function celina_enqueue_assets(): void {
	wp_enqueue_style( 'celina-sales-patterns', get_stylesheet_directory_uri() . '/assets/css/sales-patterns.css', [ 'celina-content-templates' ], celina_asset_version( 'assets/css/sales-patterns.css' ) );
	wp_enqueue_style(
		'celina-tokens',
		get_stylesheet_directory_uri() . '/assets/css/tokens.css',
		[ 'generate-child' ],
		celina_asset_version( 'assets/css/tokens.css' )
	);
	wp_enqueue_style(
		'celina-site-shell',
		get_stylesheet_directory_uri() . '/assets/css/site-shell.css',
		[ 'celina-tokens' ],
		celina_asset_version( 'assets/css/site-shell.css' )
	);
	wp_enqueue_style(
		'celina-content-templates',
		get_stylesheet_directory_uri() . '/assets/css/content-templates.css',
		[ 'celina-site-shell' ],
		celina_asset_version( 'assets/css/content-templates.css' )
	);
	wp_enqueue_style( 'celina-block-shell', get_stylesheet_directory_uri() . '/assets/css/block-shell.css', [ 'celina-site-shell' ], celina_asset_version( 'assets/css/block-shell.css' ) );
	wp_register_script( 'celina-block-navigation', get_stylesheet_directory_uri() . '/assets/js/block-shell-navigation.js', [], celina_asset_version( 'assets/js/block-shell-navigation.js' ), true );
	if ( celina_block_shell_enabled() ) { wp_enqueue_script( 'celina-block-navigation' ); }
	wp_enqueue_script(
		'celina-navigation',
		get_stylesheet_directory_uri() . '/assets/js/navigation.js',
		[],
		celina_asset_version( 'assets/js/navigation.js' ),
		true
	);
}

add_action( 'wp_enqueue_scripts', 'celina_enqueue_assets', 20 );

/** Load the existing heading font before first paint, avoiding fallback-font reflow. */
function celina_preload_heading_font( array $resources ): array {
	$resources[] = [
		'href' => get_stylesheet_directory_uri() . '/assets/fonts/inter-latin.woff2',
		'as' => 'font',
		'type' => 'font/woff2',
		'crossorigin' => 'anonymous',
	];
	return $resources;
}
add_filter( 'wp_preload_resources', 'celina_preload_heading_font' );

/**
 * Keep the first core image eager and defer later core images in page content.
 *
 * The canonical article uses theme-owned placeholders rather than Media Library
 * attachments, so WordPress cannot infer their dimensions and does not add its
 * normal loading hint. Applying the attribute while rendering keeps serialized
 * Gutenberg markup valid and preserves an eager leading image.
 */
function celina_defer_non_leading_core_images( string $block_content ): string {
	static $rendered_core_images = 0;

	$processor = new WP_HTML_Tag_Processor( $block_content );
	if ( ! $processor->next_tag( 'img' ) ) {
		return $block_content;
	}

	++$rendered_core_images;
	if (
		1 === $rendered_core_images ||
		null !== $processor->get_attribute( 'loading' )
	) {
		return $block_content;
	}

	$processor->set_attribute( 'loading', 'lazy' );

	return $processor->get_updated_html();
}

add_filter( 'render_block_core/image', 'celina_defer_non_leading_core_images' );

/**
 * Redirect the retired Markets path to the canonical Industries page.
 */
function celina_redirect_legacy_markets_route(): void {
	if ( is_admin() || wp_doing_ajax() ) {
		return;
	}

	$request_path = wp_parse_url(
		(string) ( $_SERVER['REQUEST_URI'] ?? '' ),
		PHP_URL_PATH
	);
	$home_path    = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	$request_path = is_string( $request_path ) ? trim( $request_path, '/' ) : '';
	$home_path    = is_string( $home_path ) ? trim( $home_path, '/' ) : '';

	if ( '' !== $home_path && str_starts_with( $request_path, $home_path . '/' ) ) {
		$request_path = substr( $request_path, strlen( $home_path ) + 1 );
	}

	if ( 'markets' !== $request_path ) {
		return;
	}

	wp_safe_redirect( home_url( '/industries/' ), 301, 'CELINA' );
	exit;
}

add_action( 'template_redirect', 'celina_redirect_legacy_markets_route', 1 );
