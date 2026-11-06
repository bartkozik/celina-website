<?php
require_once '/var/www/html/celina-workbench/content/manifest.php';
require_once '/var/www/html/celina-workbench/content/planner.php';

function redesign_expect( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}
function redesign_record( int $id ): array {
	foreach ( celina_content_load_records() as $record ) {
		if ( $id === $record['id'] ) {
			return $record;
		}
	}
	throw new RuntimeException( 'Test page missing.' );
}
function redesign_snapshots( int $id ): array {
	return get_posts( [ 'post_type' => 'page', 'post_status' => 'any', 'numberposts' => -1,
		'meta_key' => '_celina_redesign_snapshot_for', 'meta_value' => $id ] );
}

$registry = WP_Block_Patterns_Registry::get_instance();
$original_ids = get_posts( [ 'post_type' => [ 'page', 'post', 'revision' ], 'post_status' => 'any',
	'numberposts' => -1, 'fields' => 'ids' ] );
$original_reading = [ get_option( 'show_on_front' ), get_option( 'page_on_front' ), get_option( 'page_for_posts' ) ];
$failure = null;
$reject_update = null;
$entry = array_values( array_filter( celina_content_manifest(), static fn( array $entry ): bool =>
	'page.community' === $entry['key'] ) )[0];
foreach ( celina_content_load_records() as $existing ) {
	if ( in_array( $existing['blueprint_key'], [ 'page.community', 'page.capabilities', 'page.markets' ], true ) ||
		in_array( $existing['slug'], [ 'community', 'capabilities', 'industries' ], true ) ) {
		WP_CLI::error( 'Use the disposable content database: an offer page already exists.' );
	}
}
$source = $registry->get_registered( 'celina/sales-page-community' )['content'];
$old_copy = '<!-- wp:paragraph --><p>Original "client copy" with \\ and an apostrophe: CELINA\'s.</p><!-- /wp:paragraph -->';
$id = 0;
try {
	$id = wp_insert_post( wp_slash( [ 'post_type' => 'page', 'post_status' => 'publish',
		'post_title' => $entry['title'], 'post_name' => $entry['slug'], 'post_content' => $old_copy ] ), true );
	redesign_expect( ! is_wp_error( $id ), 'Create disposable owned page' );
	update_post_meta( $id, '_celina_blueprint_key', $entry['key'] );
	update_post_meta( $id, '_celina_blueprint_version', 2 );
	update_post_meta( $id, '_celina_applied_content_hash', celina_content_hash( 'Older source' ) );
	update_post_meta( $id, '_wp_page_template', 'default' );
	update_post_meta( $id, 'contract_nested', wp_slash( [ 'copy' => 'Keep "quotes" and \\ intact' ] ) );
	$original_meta = get_post_meta( $id );
	$plan = celina_content_plan_entry( $entry, $source, celina_content_load_records() );
	redesign_expect( 'redesign_snapshot' === $plan['reason'], 'Plan must explicitly declare snapshot replacement' );
	redesign_expect( [] === redesign_snapshots( $id ) && get_post( $id )->post_content === $old_copy, 'Planning is read-only' );
	redesign_expect( 0 === celina_content_execute( 'plan-safe', 'offer-pages', static function ( string $line ): void {} ), 'Normal pipeline plan accepts the reviewed transition' );

	wp_update_post( wp_slash( [ 'ID' => $id, 'post_content' => $old_copy . ' Changed while planning.' ] ) );
	$stale = celina_content_write_entry( $entry, $source, $plan );
	redesign_expect( 'conflict' === $stale['action'] && [] === redesign_snapshots( $id ), 'Stale plan must not write or archive' );
	redesign_expect( str_contains( get_post( $id )->post_content, 'Changed while planning.' ), 'Stale plan must preserve current copy' );
	wp_update_post( wp_slash( [ 'ID' => $id, 'post_content' => $old_copy ] ) );
	$plan = celina_content_plan_entry( $entry, $source, celina_content_load_records() );

	$reject_update = static fn( bool $empty, array $data ): bool => $id === (int) ( $data['ID'] ?? 0 ) ? true : $empty;
	add_filter( 'wp_insert_post_empty_content', $reject_update, 10, 2 );
	$failed = celina_content_write_entry( $entry, $source, $plan );
	remove_filter( 'wp_insert_post_empty_content', $reject_update, 10 );
	$reject_update = null;
	redesign_expect( 'conflict' === $failed['action'], 'Failed update must be a hard conflict' );
	redesign_expect( [] === redesign_snapshots( $id ), 'Failed update must roll back its draft too' );
	redesign_expect( $old_copy === get_post( $id )->post_content && $original_meta === get_post_meta( $id ), 'Failed update must restore all original state' );

	$applied = celina_content_write_entry( $entry, $source, $plan );
	redesign_expect( 'update' === $applied['action'], 'Snapshot transition must succeed' );
	$archives = redesign_snapshots( $id );
	redesign_expect( 1 === count( $archives ), 'Exactly one previous version must be available in Pages' );
	$archive = $archives[0];
	redesign_expect( 'draft' === $archive->post_status && $old_copy === $archive->post_content, 'Draft retains exact original copy' );
	redesign_expect( str_contains( $archive->post_title, '(before redesign)' ), 'Archive label must be clear' );
	redesign_expect( get_post_meta( $archive->ID, 'contract_nested', true ) === get_post_meta( $id, 'contract_nested', true ), 'Archive retains nested metadata' );
	redesign_expect( ! metadata_exists( 'post', $archive->ID, '_celina_blueprint_key' ) &&
		! metadata_exists( 'post', $archive->ID, '_celina_blueprint_version' ) &&
		! metadata_exists( 'post', $archive->ID, '_celina_applied_content_hash' ), 'Archive must not become a duplicate managed owner' );
	redesign_expect( $source === get_post( $id )->post_content && $entry['title'] === get_post( $id )->post_title &&
		$entry['slug'] === get_post( $id )->post_name, 'Active content changes while public identity remains stable' );
	redesign_expect( $entry['version'] === (int) get_post_meta( $id, '_celina_redesigned_version', true ), 'Admin marker is saved' );
	$states = apply_filters( 'display_post_states', [ 'existing' => 'Existing state' ], get_post( $id ) );
	redesign_expect( '(redesigned)' === ( $states['celina-redesigned'] ?? null ) &&
		'Existing state' === $states['existing'], 'Pages label appears without replacing existing post states' );
	redesign_expect( 'noop' === celina_content_plan_entry( $entry, $source, celina_content_load_records() )['action'], 'Repeat plan converges with archive present' );
	redesign_expect( 0 === celina_content_execute( 'apply-safe', 'offer-pages', static function ( string $line ): void {} ), 'Normal pipeline apply converges with draft snapshots present' );
	redesign_expect( 0 === celina_content_execute( 'apply-safe', 'offer-pages', static function ( string $line ): void {} ) &&
		1 === count( redesign_snapshots( $id ) ), 'Repeated pipeline sync does not duplicate archives' );

	redesign_expect( function_exists( 'celina_redesign_rollback' ), 'Explicit rollback must exist' );
	$rollback_plan = celina_redesign_plan_rollback( $archive->ID );
	redesign_expect( 'restore' === $rollback_plan['action'], 'Rollback plan recognizes unchanged target' );
	$rolled_back = celina_redesign_rollback( $archive->ID, $rollback_plan['fingerprint'] );
	redesign_expect( 'restored' === $rolled_back['action'], 'Rollback restores the original page' );
	redesign_expect( $old_copy === get_post( $id )->post_content &&
		'2' === get_post_meta( $id, '_celina_blueprint_version', true ), 'Rollback restores original copy and ownership version' );
	redesign_expect( 'redesign_rollback_hold' === celina_content_plan_entry( $entry, $source, celina_content_load_records() )['reason'], 'Normal sync must respect rollback hold' );
	foreach ( [ 'plan-safe', 'apply-safe' ] as $mode ) {
		redesign_expect( 20 === celina_content_execute( $mode, 'offer-pages', static function ( string $line ): void {} ) &&
			$old_copy === get_post( $id )->post_content, 'Pipeline must preserve the rollback hold' );
	}
	redesign_expect( 'noop' === celina_redesign_plan_rollback( $archive->ID )['action'], 'Rollback can be retried without overwriting edits' );

	delete_post_meta( $id, '_celina_redesign_hold' );
	$plan = celina_content_plan_entry( $entry, $source, celina_content_load_records() );
	redesign_expect( 'update' === celina_content_write_entry( $entry, $source, $plan )['action'], 'Explicit retry can activate again' );
	$latest = redesign_snapshots( $id )[0];
	wp_update_post( wp_slash( [ 'ID' => $id, 'post_content' => $source . '<!-- wp:paragraph --><p>Later client edit</p><!-- /wp:paragraph -->' ] ) );
	redesign_expect( 'manual_content_conflict' === celina_content_plan_entry( $entry, $source, celina_content_load_records() )['reason'], 'Later client copy stays protected' );
	redesign_expect( 'conflict' === celina_redesign_plan_rollback( $latest->ID )['action'], 'Rollback refuses to erase later edits' );
	redesign_expect( $original_reading === [ get_option( 'show_on_front' ), get_option( 'page_on_front' ), get_option( 'page_for_posts' ) ], 'Page transition preserves Reading assignments' );
} catch ( Throwable $error ) {
	$failure = $error->getMessage();
} finally {
	if ( $reject_update ) {
		remove_filter( 'wp_insert_post_empty_content', $reject_update, 10 );
	}
	global $wpdb;
	$wpdb->query( 'ROLLBACK' );
	if ( $id ) {
		clean_post_cache( $id );
		foreach ( redesign_snapshots( $id ) as $snapshot ) {
			wp_delete_post( $snapshot->ID, true );
		}
		wp_delete_post( $id, true );
	}
	$current_ids = get_posts( [ 'post_type' => [ 'page', 'post' ], 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ] );
	foreach ( array_diff( $current_ids, $original_ids ) as $created_id ) {
		wp_delete_post( $created_id, true );
	}
}
if ( $failure ) {
	WP_CLI::error( $failure );
}
WP_CLI::success( 'Redesigned transition, archives, rollback and client edits verified.' );
