<?php

declare( strict_types=1 );

$theme_root   = dirname( __DIR__, 2 ) . '/wordpress/themes/celina-child';
$pattern_root = $theme_root . '/patterns';
$failures     = [];
$form_patterns = glob( $pattern_root . '/form-*.php' ) ?: [];

if ( [] !== $form_patterns ) {
	$failures[] = 'HubSpot form presets must not be registered as theme patterns';
}

$stylesheet = file_get_contents( $theme_root . '/style.css' );
if (
	false === $stylesheet ||
	1 !== preg_match( '/^Version:\s*(?<version>\S+)\s*$/mi', $stylesheet, $theme_version ) ||
	version_compare( $theme_version['version'], '0.1.3', '<' )
) {
	$failures[] = 'child theme version must invalidate the cached form patterns';
}

if ( count( $failures ) > 0 ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

fwrite( STDOUT, "HubSpot form preset source contract passed\n" );
