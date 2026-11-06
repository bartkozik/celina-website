<?php
/** Local wp-env-only endpoint; tests are never shipped in theme packages. */
if ( ! defined( 'ABSPATH' ) ) { require dirname( __DIR__, 2 ) . '/wp-load.php'; }
$theme = get_option( 'stylesheet' );
$mods = get_option( 'theme_mods_' . $theme, [] );
$empty_menu = isset( $_GET['empty_menu'] ) ? wp_create_nav_menu( 'Parity empty menu ' . wp_generate_uuid4() ) : 0;
if ( is_wp_error( $empty_menu ) ) { throw new RuntimeException( $empty_menu->get_error_message() ); }
$locations = $empty_menu ? [ 'celina-primary' => $empty_menu ] : [];
$original_mods = static function ( $value ) use ( $mods, $locations ) { return array_merge( $mods, [ 'celina_sales_review' => ! isset( $_GET['legacy_style'] ), 'nav_menu_locations' => $locations ] ); };
add_filter( 'pre_option_theme_mods_' . $theme, $original_mods );
$disabled = static function () { return [ 'enabled' => false ]; };
add_filter( 'pre_option_' . celina_block_shell_option_name(), $disabled );
$logo = static function () { return '<a class="custom-logo-link" href="' . esc_url( home_url( '/' ) ) . '"><img class="custom-logo" src="' . esc_url( get_stylesheet_directory_uri() . '/assets/images/celina-logo-white.png' ) . '" alt="CELINA"></a>'; };
if ( isset( $_GET['logo'] ) ) {
	add_filter( 'get_custom_logo', $logo );
}
try {
	ob_start(); celina_render_header(); celina_render_footer(); $legacy = ob_get_clean();
	$header = celina_block_shell_content( 'header' );
	if ( isset( $_GET['logo'] ) ) {
		$header_blocks = parse_blocks( $header );
		$header_blocks[1]['innerBlocks'][0]['innerBlocks'][0] = celina_shell_block( 'site-logo', [ 'width' => 190, 'className' => 'celina-block-header-logo' ] );
		$header = serialize_blocks( $header_blocks );
	}
	$blocks = do_blocks( $header ) . do_blocks( celina_block_shell_content( 'footer' ) );
	do_action( 'wp_enqueue_scripts' );
	wp_enqueue_script( 'celina-block-navigation' );
	ob_start(); wp_head(); $head = ob_get_clean();
	ob_start(); wp_footer(); $scripts = ob_get_clean();
	$document = static function ( $body ) use ( $head, $scripts ) { return '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1">' . $head . '</head><body class="' . ( celina_sales_review_enabled() ? 'celina-sales-review' : '' ) . '">' . $body . '<div style="height:1200px"></div>' . $scripts . '</body></html>'; };
	echo wp_json_encode( [ 'legacy' => $document( $legacy ), 'blocks' => $document( $blocks ), 'source' => [ 'Header' => $header, 'Footer' => celina_block_shell_content( 'footer' ) ] ] );
} finally {
	if ( $empty_menu ) { wp_delete_nav_menu( $empty_menu ); }
	remove_filter( 'get_custom_logo', $logo );
	remove_filter( 'pre_option_theme_mods_' . $theme, $original_mods );
	remove_filter( 'pre_option_' . celina_block_shell_option_name(), $disabled );
}
