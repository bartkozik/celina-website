<?php
/**
 * Offline contract for the client-approved global footer.
 *
 * @package Celina_Workbench
 */

declare( strict_types=1 );

$root        = dirname( __DIR__, 2 );
$asset_path  = $root . '/wordpress/themes/celina-child/assets/images/celina-logo-white.png';
$shell_path  = $root . '/wordpress/themes/celina-child/inc/site-shell.php';
$settings_path = $root . '/wordpress/themes/celina-child/inc/customizer.php';
$styles_path = $root . '/wordpress/themes/celina-child/assets/css/site-shell.css';
$failures    = [];

$read = static function ( string $path, string $label ) use ( &$failures ): string {
	$source = is_readable( $path ) ? file_get_contents( $path ) : false;
	if ( false === $source ) {
		$failures[] = "missing readable {$label}";

		return '';
	}

	return $source;
};

$shell    = $read( $shell_path, 'site shell source' );
$settings = $read( $settings_path, 'Customizer source' );
$styles   = $read( $styles_path, 'site shell styles' );

if ( ! is_readable( $asset_path ) ) {
	$failures[] = 'missing local CELINA footer logo';
} elseif ( 'a4cf3090f74e0dea09f9906c0b04594422eb60be36b4a0b4855be2131d5651c3' !== hash_file( 'sha256', $asset_path ) ) {
	$failures[] = 'CELINA footer logo does not match the documented neutral portfolio placeholder';
}

$required_shell_markers = [
	'/assets/images/celina-logo-white.png' => 'local footer logo reference',
	"esc_attr__( 'CELINA', 'celina' )" => 'accessible CELINA logo name',
	'CELINA manufactures and distributes innovative fabric solutions for military, industrial, and commercial markets worldwide.' => 'approved footer claim',
	"123 Example Street\\nCelina, OH 00000 USA" => 'approved footer address',
	'sales@example.invalid' => 'approved footer email',
	"esc_html_e( 'Our Sites', 'celina' )" => 'Our Sites heading',
	"esc_html_e( 'Contact', 'celina' )" => 'Contact heading',
	"wp_date( 'Y' )" => 'current-year copyright',
	'All rights reserved.' => 'copyright copy',
];

foreach ( $required_shell_markers as $marker => $label ) {
	if ( false === strpos( $shell, $marker ) ) {
		$failures[] = "missing {$label}";
	}
}

foreach ( [ 'contact@example.invalid', 'Fixture address', 'Synthetic fixture tagline.' ] as $placeholder ) {
	if ( false !== strpos( $shell . $settings, $placeholder ) ) {
		$failures[] = "synthetic footer placeholder remains: {$placeholder}";
	}
}

foreach ( [ '.celina-footer-brand', '.celina-footer-logo', '.celina-footer-meta' ] as $selector ) {
	if ( false === strpos( $styles, $selector ) ) {
		$failures[] = "missing footer style selector {$selector}";
	}
}

if ( false !== strpos( $shell, 'excluded-media.example.invalid' ) ) {
	$failures[] = 'footer must not hotlink the client WordPress';
}

if ( count( $failures ) > 0 ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "Footer source contract passed\n";
