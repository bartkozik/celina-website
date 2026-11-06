<?php

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	fwrite( STDERR, "This command must run through WP-CLI.\n" );
	exit( 1 );
}

require_once __DIR__ . '/manifest.php';
require_once __DIR__ . '/planner.php';

$command_arguments = isset( $args ) && is_array( $args ) ? $args : [];
$mode              = $command_arguments[0] ?? '';
$group             = $command_arguments[1] ?? null;

if ( count( $command_arguments ) > 2 ) {
	WP_CLI::line( 'ERROR invalid_arguments' );
	WP_CLI::halt( 2 );
}

// The host takes a verified backup and excludes editors before apply-safe.
// Group-scoped content operations do not implicitly migrate unrelated pages.
if ( null === $group && in_array( $mode, [ 'plan', 'plan-safe', 'apply', 'apply-safe' ], true ) ) {
	require_once __DIR__ . '/media.php';
	try {
		$media_plan = celina_media_build_plan();
		WP_CLI::line( 'MEDIA_PLAN ' . wp_json_encode( $media_plan ) );
		if ( $media_plan['errors'] ) { throw new RuntimeException( 'media_plan_conflicts' ); }
		if ( in_array( $mode, [ 'apply', 'apply-safe' ], true ) ) {
			WP_CLI::line( 'MEDIA_APPLIED ' . wp_json_encode( celina_media_apply( $media_plan['fingerprint'] ) ) );
		}
	} catch ( Throwable $error ) {
		WP_CLI::error( $error->getMessage() );
	}
}

$exit_code = celina_content_execute(
	(string) $mode,
	$group,
	static function ( string $line ): void {
		WP_CLI::line( $line );
	}
);

WP_CLI::halt( $exit_code );
