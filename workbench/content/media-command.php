<?php
/** Standalone media preview/apply; apply requires the exact reviewed fingerprint. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( 1 ); }
require_once __DIR__ . '/media.php';
try {
	$mode = $args[0] ?? '';
	if ( 'plan' === $mode && count( $args ) === 1 ) {
		WP_CLI::line( wp_json_encode( celina_media_build_plan(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	} elseif ( 'apply' === $mode && count( $args ) === 2 && preg_match( '/^[a-f0-9]{64}$/', $args[1] ) ) {
		WP_CLI::line( wp_json_encode( celina_media_apply( $args[1] ) ) );
	} else { WP_CLI::error( 'Usage: media-command.php plan | apply REVIEWED_FINGERPRINT' ); }
} catch ( Throwable $error ) { WP_CLI::error( $error->getMessage() ); }
