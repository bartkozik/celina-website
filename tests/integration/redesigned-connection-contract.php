<?php
/** Disposable subprocess contract: a lost connection must never escape the transaction. */
require_once '/var/www/html/celina-workbench/content/manifest.php';
require_once '/var/www/html/celina-workbench/content/planner.php';

function redesign_connection_expect( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function redesign_connection_entry( int $id ): array {
	$entry = array_values( array_filter( celina_content_manifest(), static fn( array $item ): bool =>
		'page.community' === $item['key'] ) )[0];
	$post = get_post( $id );
	$entry['slug'] = $post->post_name;
	$entry['title'] = $post->post_title;
	return $entry;
}

function redesign_connection_state( wpdb $observer, int $id ): array {
	global $wpdb;
	return [
		'post' => $observer->get_row( $observer->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID = %d", $id ), ARRAY_A ),
		'meta' => $observer->get_results( $observer->prepare(
			"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d ORDER BY meta_key, meta_id", $id
		), ARRAY_A ),
	];
}

function redesign_connection_snapshots( wpdb $observer, int $id ): array {
	global $wpdb;
	return $observer->get_col( $observer->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_celina_redesign_snapshot_for' AND meta_value = %s ORDER BY post_id", (string) $id
	) );
}

function redesign_connection_child( string $mode, int $id, int $snapshot_id = 0 ): string {
	$process = proc_open( [ 'wp', '--path=' . ABSPATH, 'eval-file', __FILE__, $mode,
		(string) $id, (string) $snapshot_id ], [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
	redesign_connection_expect( is_resource( $process ), 'Cannot launch connection-fault subprocess.' );
	fclose( $pipes[0] );
	$output = stream_get_contents( $pipes[1] );
	$error = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	$exit = proc_close( $process );
	redesign_connection_expect( str_contains( $output, 'FAULT_INJECTED:' . $mode ),
		$mode . ' connection loss was not reached: ' . $exit . ' ' . $error );
	return $output;
}

$source = '<!-- wp:paragraph --><p>Approved redesigned connection-contract copy.</p><!-- /wp:paragraph -->';
$arguments = isset( $args ) && is_array( $args ) ? $args : [];
if ( in_array( $arguments[0] ?? '', [ 'activation-loss', 'rollback-loss' ], true ) ) {
	global $wpdb;
	$mode = $arguments[0];
	$id = (int) $arguments[1];
	$snapshot_id = (int) $arguments[2];
	$entry = redesign_connection_entry( $id );
	$observer = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
	$connection_id = (int) $wpdb->get_var( 'SELECT CONNECTION_ID()' );
	$injected = false;
	add_filter( 'query', static function ( string $query ) use ( $wpdb, $observer, $connection_id, $mode, $id, &$injected ): string {
		$target = 'activation-loss' === $mode
			? str_starts_with( $query, "UPDATE `{$wpdb->posts}` SET " ) && str_ends_with( $query, "WHERE `ID` = $id" )
			: str_starts_with( $query, "DELETE FROM {$wpdb->postmeta} WHERE meta_id IN" );
		if ( ! $injected && $target ) {
			$injected = true;
			if ( 'activation-loss' === $mode ) {
				redesign_connection_expect( 1 === count( redesign_connection_snapshots( $wpdb, $id ) ),
					'Fault must occur after the uncommitted snapshot, before the active UPDATE.' );
			}
			redesign_connection_expect( false !== $observer->query( 'KILL CONNECTION ' . $connection_id ), 'Cannot terminate the transaction connection.' );
			echo 'FAULT_INJECTED:' . $mode . "\n";
			flush();
		}
		return $query;
	} );
	$result = 'activation-loss' === $mode
		? celina_content_write_entry( $entry, $source, celina_content_plan_entry( $entry, $source, celina_content_load_records() ) )
		: celina_redesign_rollback( $snapshot_id, celina_redesign_plan_rollback( $snapshot_id )['fingerprint'] );
	WP_CLI::line( wp_json_encode( $result ) );
	return;
}

global $wpdb;
foreach ( celina_content_load_records() as $existing ) {
	if ( 'page.community' === $existing['blueprint_key'] ) {
		WP_CLI::error( 'Use a disposable database without a managed Community page for the connection contract.' );
	}
}
$observer = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$original_retries = $wpdb->reconnect_retries;
$wpdb->reconnect_retries = 3;
$id = 0;
$failure = null;
$reject_update = null;
$cleanup_failure = null;
$observe_boundary = null;
try {
	$id = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish',
		'post_title' => 'Connection contract ' . wp_generate_uuid4(),
		'post_name' => 'connection-contract-' . wp_generate_uuid4(),
		'post_content' => '<!-- wp:paragraph --><p>Original client copy.</p><!-- /wp:paragraph -->' ], true );
	redesign_connection_expect( ! is_wp_error( $id ) && $id > 0, 'Cannot create disposable page.' );
	$entry = redesign_connection_entry( $id );
	update_post_meta( $id, '_celina_blueprint_key', $entry['key'] );
	update_post_meta( $id, '_celina_blueprint_version', $entry['redesign_from']['versions'][0] );
	update_post_meta( $id, '_celina_applied_content_hash', celina_content_hash( 'Previous source' ) );
	update_post_meta( $id, 'connection_contract', wp_slash( [ 'text' => 'Keep "quotes" and \\.' ] ) );
	$original = redesign_connection_state( $observer, $id );
	redesign_connection_child( 'activation-loss', $id );
	redesign_connection_expect( $original === redesign_connection_state( $observer, $id ),
		'Lost activation connection changed the active page or metadata outside its transaction.' );
	redesign_connection_expect( [] === redesign_connection_snapshots( $observer, $id ),
		'Lost activation connection left a committed snapshot.' );

	$boundaries = [];
	$observe_boundary = static function ( string $query ) use ( $wpdb, &$boundaries ): string {
		if ( in_array( $query, [ 'START TRANSACTION', 'COMMIT', 'ROLLBACK' ], true ) ) {
			$boundaries[] = [ $query, $wpdb->reconnect_retries ];
		}
		return $query;
	};
	add_filter( 'query', $observe_boundary );
	$plan = celina_content_plan_entry( $entry, $source, celina_content_load_records() );
	$reject_update = static fn( bool $empty, array $data ): bool => $id === (int) ( $data['ID'] ?? 0 ) ? true : $empty;
	add_filter( 'wp_insert_post_empty_content', $reject_update, 10, 2 );
	$failed = celina_content_write_entry( $entry, $source, $plan );
	remove_filter( 'wp_insert_post_empty_content', $reject_update, 10 );
	$reject_update = null;
	redesign_connection_expect( 'conflict' === $failed['action'] && 3 === $wpdb->reconnect_retries,
		'Ordinary activation failure did not restore the previous reconnect setting.' );
	$applied = celina_content_write_entry( $entry, $source, $plan );
	redesign_connection_expect( 'update' === $applied['action'] && 3 === $wpdb->reconnect_retries,
		'Successful activation did not restore the previous reconnect setting.' );
	$snapshot_id = $applied['details']['snapshot_id'];
	$active = redesign_connection_state( $observer, $id );
	$snapshot = redesign_connection_state( $observer, $snapshot_id );
	redesign_connection_child( 'rollback-loss', $id, $snapshot_id );
	redesign_connection_expect( $active === redesign_connection_state( $observer, $id ) &&
		$snapshot === redesign_connection_state( $observer, $snapshot_id ),
		'Lost rollback connection partially changed the active page or recovery snapshot.' );
	$failed_restore = celina_redesign_rollback( $snapshot_id, str_repeat( '0', 64 ) );
	redesign_connection_expect( 'conflict' === $failed_restore['action'] && 3 === $wpdb->reconnect_retries,
		'Ordinary rollback failure did not restore the previous reconnect setting.' );

	// A hook can throw while invalidating caches; restoring the connection policy is still mandatory.
	foreach ( [ 'activation', 'rollback' ] as $mode ) {
		$clean_count = 0;
		$cleanup_failure = static function ( int $clean_id ) use ( $id, &$clean_count ): void {
			if ( $clean_id === $id && 2 === ++$clean_count ) {
				throw new RuntimeException( 'connection_contract_cleanup_failure' );
			}
		};
		add_action( 'clean_post_cache', $cleanup_failure );
		$thrown = false;
		try {
			if ( 'activation' === $mode ) {
				celina_content_write_redesign( $entry, $source, $plan );
			} else {
				celina_redesign_rollback( $snapshot_id, str_repeat( '0', 64 ) );
			}
		} catch ( RuntimeException $error ) {
			$thrown = 'connection_contract_cleanup_failure' === $error->getMessage();
		} finally {
			remove_action( 'clean_post_cache', $cleanup_failure );
			$cleanup_failure = null;
		}
		redesign_connection_expect( $thrown && 3 === $wpdb->reconnect_retries,
			$mode . ' cleanup exception did not restore the previous reconnect setting.' );
	}
	$restored = celina_redesign_rollback( $snapshot_id, celina_redesign_plan_rollback( $snapshot_id )['fingerprint'] );
	redesign_connection_expect( 'restored' === $restored['action'] && 3 === $wpdb->reconnect_retries,
		'Successful rollback did not restore the previous reconnect setting.' );
	foreach ( $boundaries as [ $query, $retries ] ) {
		redesign_connection_expect( 0 === $retries, $query . ' ran while automatic reconnection was enabled.' );
	}
} catch ( Throwable $error ) {
	$failure = $error->getMessage();
} finally {
	if ( $reject_update ) {
		remove_filter( 'wp_insert_post_empty_content', $reject_update, 10 );
	}
	if ( $cleanup_failure ) {
		remove_action( 'clean_post_cache', $cleanup_failure );
	}
	if ( $observe_boundary ) {
		remove_filter( 'query', $observe_boundary );
	}
	$wpdb->query( 'ROLLBACK' );
	$wpdb->reconnect_retries = $original_retries;
	if ( $id ) {
		foreach ( redesign_connection_snapshots( $observer, $id ) as $snapshot_id ) {
			wp_delete_post( (int) $snapshot_id, true );
		}
		clean_post_cache( $id );
		wp_delete_post( $id, true );
	}
	$observer->close();
}
if ( $failure ) {
	WP_CLI::error( $failure );
}
WP_CLI::success( 'Connection-loss activation/rollback and reconnect-policy restoration verified.' );
