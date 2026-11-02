<?php
/** One-time, snapshot-backed transition to Iza's native page compositions. */
declare( strict_types=1 );

function celina_content_is_valid_redesign( array $entry ): bool {
	$pages = [
		'page.homepage' => 'homepage', 'page.about' => 'about',
		'page.capabilities' => 'capabilities', 'page.markets' => 'industries',
		'page.why-celina' => 'why-celina', 'page.community' => 'community',
		'page.careers' => 'careers', 'page.contact' => 'contact',
		'page.corporate-news' => 'corporate-news', 'page.corporate-resources' => 'corporate-resources',
	];
	$transition = $entry['redesign_from'] ?? null;
	$key = $entry['key'] ?? '';
	if ( ! isset( $pages[ $key ] ) || 'page' !== ( $entry['type'] ?? null ) ||
		'celina/redesigned-page-' . $pages[ $key ] !== ( $entry['pattern'] ?? null ) ||
		! is_array( $transition ) || 2 !== count( $transition ) ||
		! is_int( $transition['version'] ?? null ) ||
		$transition['version'] !== ( $entry['version'] ?? null ) ||
		! is_array( $transition['versions'] ?? null ) || [] === $transition['versions'] ) {
		return false;
	}
	foreach ( $transition['versions'] as $version ) {
		if ( ! is_int( $version ) || $version < 1 || $version >= $entry['version'] ) {
			return false;
		}
	}
	return count( array_unique( $transition['versions'] ) ) === count( $transition['versions'] );
}

function celina_content_record_fingerprint( array $record ): string {
	return hash( 'sha256', (string) json_encode( $record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
}

function celina_redesign_post_fields( WP_Post $post ): array {
	return array_intersect_key( $post->to_array(), array_flip( [
		'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title',
		'post_excerpt', 'post_status', 'comment_status', 'ping_status', 'post_password',
		'post_name', 'post_parent', 'menu_order', 'post_type', 'post_mime_type',
	] ) );
}

function celina_redesign_state_fingerprint( int $id ): string {
	$post = get_post( $id );
	if ( ! $post ) {
		return '';
	}
	$metadata = get_post_meta( $id );
	unset( $metadata['_edit_lock'], $metadata['_edit_last'] );
	ksort( $metadata );
	return celina_content_record_fingerprint( [ celina_redesign_post_fields( $post ), $metadata ] );
}

/** Both WordPress tables must support rollback; do not silently degrade to partial writes. */
function celina_redesign_begin(): void {
	global $wpdb;
	foreach ( [ $wpdb->posts, $wpdb->postmeta ] as $table ) {
		$engine = $wpdb->get_var( $wpdb->prepare(
			'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table
		) );
		if ( 'INNODB' !== strtoupper( (string) $engine ) ) {
			throw new RuntimeException( 'redesign_transactional_tables_required' );
		}
	}
	if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
		throw new RuntimeException( 'redesign_transaction_failed' );
	}
}

function celina_redesign_lock_post( int $id ): void {
	global $wpdb;
	$locked = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID = %d FOR UPDATE", $id ) );
	if ( (string) $id !== (string) $locked || false === $wpdb->query(
		$wpdb->prepare( "SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d FOR UPDATE", $id )
	) ) {
		throw new RuntimeException( 'redesign_lock_failed' );
	}
	clean_post_cache( $id );
}

function celina_redesign_archive_meta( array $metadata ): array {
	foreach ( array_keys( $metadata ) as $key ) {
		if ( in_array( $key, [ '_celina_blueprint_key', '_celina_blueprint_version',
			'_celina_applied_content_hash', '_edit_lock', '_edit_last' ], true ) ||
			0 === strpos( $key, '_celina_redesign' ) ) {
			unset( $metadata[ $key ] );
		}
	}
	return $metadata;
}

function celina_redesign_copy_meta( int $id, array $metadata ): void {
	foreach ( $metadata as $key => $values ) {
		foreach ( $values as $value ) {
			if ( false === add_post_meta( $id, $key, wp_slash( maybe_unserialize( $value ) ) ) ) {
				throw new RuntimeException( 'redesign_metadata_write_failed' );
			}
		}
	}
}

function celina_redesign_snapshot_matches( int $id, array $receipt ): bool {
	$post = get_post( $id );
	if ( ! $post || 'page' !== $post->post_type || 'draft' !== $post->post_status ||
		$post->post_content !== $receipt['post']['post_content'] ||
		$post->post_title !== $receipt['post']['post_title'] . ' (before redesign)' ||
		get_post_meta( $id, '_celina_redesign_original', true ) !== $receipt ) {
		return false;
	}
	foreach ( [ '_celina_blueprint_key', '_celina_blueprint_version', '_celina_applied_content_hash' ] as $key ) {
		if ( metadata_exists( 'post', $id, $key ) ) {
			return false;
		}
	}
	$actual = get_post_meta( $id );
	foreach ( celina_redesign_archive_meta( $receipt['meta'] ) as $key => $values ) {
		if ( ( $actual[ $key ] ?? null ) !== $values ) {
			return false;
		}
	}
	return true;
}

function celina_content_write_redesign( array $entry, string $content, array $operation ): array {
	global $wpdb;
	$id = (int) ( $operation['details']['id'] ?? 0 );
	$archive_id = 0;
	$started = false;
	$previous_reconnect_retries = $wpdb->reconnect_retries;
	try {
		// wpdb otherwise retries a lost connection's query in a new, autocommitting session.
		$wpdb->reconnect_retries = 0;
		celina_redesign_begin();
		$started = true;
		celina_redesign_lock_post( $id );
		$fresh = celina_content_plan_entry( $entry, $content, celina_content_load_records() );
		if ( 'redesign_snapshot' !== $fresh['reason'] ||
			( $fresh['details']['previous_fingerprint'] ?? null ) !== ( $operation['details']['previous_fingerprint'] ?? null ) ||
			( $fresh['details']['source_hash'] ?? null ) !== ( $operation['details']['source_hash'] ?? null ) ||
			$id !== ( $fresh['details']['id'] ?? null ) ) {
			throw new RuntimeException( 'redesign_state_changed' );
		}
		$receipt = [
			'format' => 1,
			'post_id' => $id,
			'post' => celina_redesign_post_fields( get_post( $id ) ),
			'meta' => get_post_meta( $id ),
			'target_key' => $entry['key'],
			'target_version' => $entry['version'],
		];
		$archive = wp_insert_post( wp_slash( array_replace( $receipt['post'], [
			'post_status' => 'draft',
			'post_title' => $receipt['post']['post_title'] . ' (before redesign)',
			'post_name' => 'celina-before-redesign-' . $id . '-' . wp_generate_uuid4(),
			'post_parent' => 0,
		] ) ), true );
		if ( is_wp_error( $archive ) || ! $archive ) {
			throw new RuntimeException( 'redesign_snapshot_write_failed' );
		}
		$archive_id = (int) $archive;
		celina_redesign_copy_meta( $archive_id, celina_redesign_archive_meta( $receipt['meta'] ) );
		update_post_meta( $archive_id, '_celina_redesign_snapshot_for', $id );
		update_post_meta( $archive_id, '_celina_redesign_snapshot_version', $entry['version'] );
		update_post_meta( $archive_id, '_celina_redesign_original', wp_slash( $receipt ) );
		if ( ! celina_redesign_snapshot_matches( $archive_id, $receipt ) ||
			$id !== (int) get_post_meta( $archive_id, '_celina_redesign_snapshot_for', true ) ||
			$entry['version'] !== (int) get_post_meta( $archive_id, '_celina_redesign_snapshot_version', true ) ) {
			throw new RuntimeException( 'redesign_snapshot_verification_failed' );
		}
		// Reuse normal identity migration and verified ownership writes within this transaction.
		$write_operation = $fresh;
		$write_operation['reason'] = $fresh['details']['previous_identity'] ? 'identity_migration' : 'version_upgrade';
		$result = celina_content_write_entry( $entry, $content, $write_operation );
		if ( 'update' !== $result['action'] ||
			$entry['version'] !== (int) get_post_meta( $id, '_celina_redesigned_version', true ) ) {
			throw new RuntimeException( 'redesign_page_write_failed' );
		}
		$receipt['active_fingerprint'] = celina_redesign_state_fingerprint( $id );
		update_post_meta( $archive_id, '_celina_redesign_original', wp_slash( $receipt ) );
		if ( ! celina_redesign_snapshot_matches( $archive_id, $receipt ) ) {
			throw new RuntimeException( 'redesign_receipt_verification_failed' );
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			throw new RuntimeException( 'redesign_commit_failed' );
		}
		$started = false;
		return celina_content_result( 'update', $entry, 'redesigned', [ 'id' => $id, 'snapshot_id' => $archive_id ] );
	} catch ( Throwable $error ) {
		return celina_content_result( 'conflict', $entry, $error->getMessage(), [ 'id' => $id ] );
	} finally {
		try {
			if ( $started ) {
				$wpdb->query( 'ROLLBACK' );
			}
			foreach ( array_filter( [ $id, $archive_id ] ) as $cache_id ) {
				clean_post_cache( $cache_id );
			}
		} finally {
			$wpdb->reconnect_retries = $previous_reconnect_retries;
		}
	}
}

/** Read-only recovery plan. Never discards edits made after the original activation. */
function celina_redesign_plan_rollback( int $snapshot_id ): array {
	$receipt = get_post_meta( $snapshot_id, '_celina_redesign_original', true );
	if ( ! is_array( $receipt ) || 1 !== ( $receipt['format'] ?? null ) ||
		! is_int( $receipt['post_id'] ?? null ) || ! is_array( $receipt['post'] ?? null ) ||
		! is_array( $receipt['meta'] ?? null ) || ! is_string( $receipt['active_fingerprint'] ?? null ) ||
		! celina_redesign_snapshot_matches( $snapshot_id, $receipt ) ) {
		return [ 'action' => 'conflict', 'reason' => 'invalid_snapshot' ];
	}
	$id = $receipt['post_id'];
	if ( (string) $snapshot_id === get_post_meta( $id, '_celina_redesign_hold', true ) ) {
		return [ 'action' => 'noop', 'reason' => 'already_restored', 'id' => $id ];
	}
	$fingerprint = celina_redesign_state_fingerprint( $id );
	if ( $receipt['active_fingerprint'] !== $fingerprint ) {
		return [ 'action' => 'conflict', 'reason' => 'page_changed_after_redesign', 'id' => $id ];
	}
	return [ 'action' => 'restore', 'id' => $id, 'snapshot_id' => $snapshot_id, 'fingerprint' => $fingerprint ];
}

function celina_redesign_rollback( int $snapshot_id, string $expected_fingerprint ): array {
	global $wpdb;
	$started = false;
	$id = 0;
	$previous_reconnect_retries = $wpdb->reconnect_retries;
	try {
		$initial = celina_redesign_plan_rollback( $snapshot_id );
		if ( 'restore' !== $initial['action'] ) {
			return $initial;
		}
		$id = $initial['id'];
		$wpdb->reconnect_retries = 0;
		celina_redesign_begin();
		$started = true;
		celina_redesign_lock_post( $id );
		celina_redesign_lock_post( $snapshot_id );
		$plan = celina_redesign_plan_rollback( $snapshot_id );
		if ( 'restore' !== $plan['action'] || $expected_fingerprint !== $plan['fingerprint'] ) {
			throw new RuntimeException( 'rollback_state_changed' );
		}
		$receipt = get_post_meta( $snapshot_id, '_celina_redesign_original', true );
		$restored = wp_update_post( wp_slash( array_merge( $receipt['post'], [ 'ID' => $id ] ) ), true );
		if ( is_wp_error( $restored ) || ! $restored ) {
			throw new RuntimeException( 'rollback_page_write_failed' );
		}
		foreach ( array_keys( get_post_meta( $id ) ) as $key ) {
			if ( ! delete_post_meta( $id, $key ) ) {
				throw new RuntimeException( 'rollback_metadata_delete_failed' );
			}
		}
		celina_redesign_copy_meta( $id, $receipt['meta'] );
		update_post_meta( $id, '_celina_redesign_hold', $snapshot_id );
		clean_post_cache( $id );
		$metadata = get_post_meta( $id );
		$hold = $metadata['_celina_redesign_hold'] ?? null;
		unset( $metadata['_celina_redesign_hold'] );
		$expected_meta = $receipt['meta'];
		unset( $expected_meta['_celina_redesign_hold'] );
		ksort( $metadata );
		ksort( $expected_meta );
		if ( $receipt['post'] !== celina_redesign_post_fields( get_post( $id ) ) ||
			$metadata !== $expected_meta || [ (string) $snapshot_id ] !== $hold ) {
			throw new RuntimeException( 'rollback_verification_failed' );
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			throw new RuntimeException( 'rollback_commit_failed' );
		}
		$started = false;
		return [ 'action' => 'restored', 'id' => $id, 'snapshot_id' => $snapshot_id ];
	} catch ( Throwable $error ) {
		return [ 'action' => 'conflict', 'reason' => $error->getMessage(), 'id' => $id ];
	} finally {
		try {
			if ( $started ) {
				$wpdb->query( 'ROLLBACK' );
			}
			foreach ( array_filter( [ $id, $snapshot_id ] ) as $cache_id ) {
				clean_post_cache( $cache_id );
			}
		} finally {
			$wpdb->reconnect_retries = $previous_reconnect_retries;
		}
	}
}
