<?php
/** Explicit operator recovery; normal sync never automatically rolls content back. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}
require_once __DIR__ . '/manifest.php';
require_once __DIR__ . '/planner.php';

$arguments = isset( $args ) && is_array( $args ) ? $args : [];
$mode = $arguments[0] ?? '';
$snapshot = $arguments[1] ?? '';
if ( ! in_array( $mode, [ 'plan-rollback', 'rollback' ], true ) ||
	! ctype_digit( $snapshot ) || (int) $snapshot < 1 ||
	count( $arguments ) !== ( 'rollback' === $mode ? 3 : 2 ) ||
	( 'rollback' === $mode && ! preg_match( '/^[a-f0-9]{64}$/', $arguments[2] ) ) ) {
	WP_CLI::error( 'Usage: redesigned-command.php plan-rollback SNAPSHOT_ID | rollback SNAPSHOT_ID REVIEWED_FINGERPRINT' );
}
$result = 'plan-rollback' === $mode
	? celina_redesign_plan_rollback( (int) $snapshot )
	: celina_redesign_rollback( (int) $snapshot, $arguments[2] );
WP_CLI::line( wp_json_encode( $result, JSON_UNESCAPED_SLASHES ) );
WP_CLI::halt( 'conflict' === $result['action'] ? 1 : 0 );
