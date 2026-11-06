<?php
defined( 'ABSPATH' ) || exit;
require_once get_stylesheet_directory() . '/inc/media-content.php';
require_once get_stylesheet_directory() . '/inc/media-library.php';

function celina_media_catalog(): array {
	$directory = realpath( get_stylesheet_directory() . '/assets/images/content' );
	if ( ! $directory ) { throw new RuntimeException( 'media_source_directory_missing' ); }
	$catalog = [];
	foreach ( glob( $directory . '/*' ) as $path ) {
		if ( ! preg_match( '/\.(webp|png|jpe?g)$/i', $path ) ) { continue; }
		if ( is_link( $path ) || ! is_readable( $path ) || dirname( realpath( $path ) ) !== $directory || ! wp_get_image_mime( $path ) ) {
			throw new RuntimeException( 'invalid_media_source:' . basename( $path ) );
		}
		$catalog[ basename( $path ) ] = [ 'path' => $path, 'hash' => hash_file( 'sha256', $path ) ];
	}
	ksort( $catalog );
	return $catalog;
}

function celina_media_records(): array {
	$posts = get_posts( [ 'post_type' => [ 'page', 'post', 'wp_block' ], 'post_status' => [ 'publish', 'future', 'draft', 'pending', 'private' ], 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ] );
	return array_values( array_filter( $posts, static function ( WP_Post $post ): bool {
		return ! get_post_meta( $post->ID, '_celina_redesign_snapshot_for', true ) &&
			! preg_match( '/\(before (?:redesign|UI\/UX edit\b).*\)$/u', $post->post_title );
	} ) );
}

function celina_media_record_fingerprint( WP_Post $post ): string {
	return hash( 'sha256', wp_json_encode( [ $post->to_array(), get_post_meta( $post->ID, '_celina_applied_content_hash', true ), get_post_meta( $post->ID, '_celina_redesign_hold', true ), get_post_meta( $post->ID, '_thumbnail_id', true ) ] ) );
}

function celina_media_build_plan(): array {
	$catalog = celina_media_catalog();
	$map = celina_media_attachment_map();
	$imports = [];
	$errors = [];
	$updates = [];
	$state = [];
	$ledger = get_option( '_celina_media_imports', [] );
	if ( ! is_array( $ledger ) ) { throw new RuntimeException( 'invalid_media_import_ledger' ); }
	foreach ( $catalog as $file => $asset ) {
		if ( isset( $ledger[ $file ] ) && ( ! isset( $map[ $file ] ) || $map[ $file ] !== $ledger[ $file ] ) ) {
			$errors[] = [ 'file' => $file, 'reason' => 'missing_or_changed_import_restore_from_backup' ];
		}
		$owned = get_posts( [ 'post_type' => 'attachment', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_celina_media_source', 'meta_value' => $file ] );
		if ( count( $owned ) > 1 || ( $owned && ! isset( $map[ $file ] ) ) ) {
			$errors[] = [ 'file' => $file, 'reason' => 'invalid_or_duplicate_import' ];
		} elseif ( $owned ) {
			$original = wp_get_original_image_path( $owned[0] );
			if ( get_post_meta( $owned[0], '_celina_media_source_hash', true ) !== $asset['hash'] ||
				! is_string( $original ) || ! is_file( $original ) || is_link( $original ) || hash_file( 'sha256', $original ) !== $asset['hash'] ) {
				$errors[] = [ 'file' => $file, 'reason' => 'changed_import_source' ];
			}
		}
		if ( ! isset( $map[ $file ] ) ) {
			$imports[] = $file;
			$map[ $file ] = [ 'id' => 2000000000 + count( $imports ), 'url' => get_stylesheet_directory_uri() . '/assets/images/content/' . $file ];
		}
		$state['assets'][ $file ] = [ $asset['hash'], $map[ $file ] ];
	}
	foreach ( celina_media_records() as $post ) {
		$fingerprint = celina_media_record_fingerprint( $post );
		$state['posts'][ $post->ID ] = $fingerprint;
		try {
			foreach ( celina_media_content_sources( $post->post_content ) as $source ) {
				if ( ! isset( $catalog[ $source ] ) ) { throw new RuntimeException( 'missing_media_source:' . $source ); }
			}
			$next = celina_media_transform_content( $post->post_content, $map );
			if ( $next === $post->post_content ) { continue; }
			if ( get_post_meta( $post->ID, '_celina_redesign_hold', true ) ) { throw new RuntimeException( 'media_redesign_hold' ); }
			$updates[] = [ 'id' => $post->ID, 'title' => $post->post_title, 'fingerprint' => $fingerprint ];
		} catch ( Throwable $error ) {
			$errors[] = [ 'id' => $post->ID, 'reason' => $error->getMessage() ];
		}
	}
	return [ 'fingerprint' => hash( 'sha256', wp_json_encode( [ $state, $ledger, $errors ] ) ), 'imports' => $imports, 'updates' => $updates, 'errors' => $errors ];
}

/** Import only trusted files already shipped with this theme; never fetch URLs. */
function celina_media_import( string $file, array $asset ): int {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$existing = get_posts( [ 'post_type' => 'attachment', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_celina_media_source', 'meta_value' => $file ] );
	if ( $existing ) { throw new RuntimeException( 'invalid_existing_media:' . $file ); }
	$temp = wp_tempnam( $file );
	if ( ! $temp || ! copy( $asset['path'], $temp ) ) { throw new RuntimeException( 'media_copy_failed:' . $file ); }
	try {
		$id = media_handle_sideload( [ 'name' => $file, 'tmp_name' => $temp ], 0, ucwords( str_replace( [ '-', '_' ], ' ', pathinfo( $file, PATHINFO_FILENAME ) ) ) );
		if ( is_wp_error( $id ) ) { throw new RuntimeException( 'media_import_failed:' . $file . ':' . $id->get_error_code() ); }
		if ( ! add_post_meta( $id, '_celina_media_source', $file, true ) || ! add_post_meta( $id, '_celina_media_source_hash', $asset['hash'], true ) ) {
			wp_delete_attachment( $id, true );
			throw new RuntimeException( 'media_import_metadata_failed:' . $file );
		}
		$ledger = get_option( '_celina_media_imports', [] );
		$ledger[ $file ] = [ 'id' => (int) $id, 'url' => wp_get_attachment_url( $id ) ];
		if ( ! update_option( '_celina_media_imports', $ledger, false ) ) {
			wp_delete_attachment( $id, true );
			throw new RuntimeException( 'media_ledger_write_failed:' . $file );
		}
		return $id;
	} finally {
		if ( is_file( $temp ) ) { unlink( $temp ); }
	}
}

/** Apply a reviewed plan. Host workflow additionally excludes editor writes. */
function celina_media_apply( string $expected ): array {
	global $wpdb;
	$lock = 'celina_media_' . substr( hash( 'sha256', $wpdb->prefix ), 0, 32 );
	if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) { throw new RuntimeException( 'media_migration_busy' ); }
	try {
		$plan = celina_media_build_plan();
		if ( ! hash_equals( $plan['fingerprint'], $expected ) ) { throw new RuntimeException( 'media_stale_plan' ); }
		if ( $plan['errors'] ) { throw new RuntimeException( 'media_plan_conflicts:' . wp_json_encode( $plan['errors'] ) ); }
		foreach ( [ $wpdb->posts, $wpdb->postmeta ] as $table ) {
			$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
			if ( 'INNODB' !== strtoupper( (string) $engine ) ) { throw new RuntimeException( 'media_transactional_tables_required' ); }
		}
		require_once ABSPATH . 'wp-admin/includes/post.php';
		foreach ( $plan['updates'] as $item ) {
			if ( wp_check_post_lock( $item['id'] ) ) { throw new RuntimeException( 'media_post_locked:' . $item['id'] ); }
		}
		$catalog = celina_media_catalog();
		foreach ( $plan['imports'] as $file ) { celina_media_import( $file, $catalog[ $file ] ); }
		$map = celina_media_attachment_map();
		if ( get_option( '_celina_media_imports', [] ) !== $map && ! update_option( '_celina_media_imports', $map, false ) ) { throw new RuntimeException( 'media_ledger_write_failed' ); }
		$changed = [];
		foreach ( $plan['updates'] as $item ) {
			$id = $item['id'];
			$revision_id = 0;
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) { throw new RuntimeException( 'media_transaction_failed' ); }
			try {
				if ( (string) $id !== (string) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID = %d FOR UPDATE", $id ) ) || false === $wpdb->query( $wpdb->prepare( "SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d FOR UPDATE", $id ) ) ) { throw new RuntimeException( 'media_row_lock_failed:' . $id ); }
				clean_post_cache( $id );
				$post = get_post( $id );
				if ( ! $post || ! hash_equals( $item['fingerprint'], celina_media_record_fingerprint( $post ) ) || wp_check_post_lock( $id ) ) { throw new RuntimeException( 'media_post_changed:' . $id ); }
				$before = $post->post_content;
				$after = celina_media_transform_content( $before, $map );
				if ( $after === $before ) { $wpdb->query( 'COMMIT' ); continue; }
				$revision_id = _wp_put_post_revision( $post );
				if ( ! $revision_id || is_wp_error( $revision_id ) ) { throw new RuntimeException( 'media_revision_failed:' . $id ); }
				$old_hash = (string) get_post_meta( $id, '_celina_applied_content_hash', true );
				$result = wp_update_post( [ 'ID' => $id, 'post_content' => wp_slash( $after ) ], true );
				if ( is_wp_error( $result ) || get_post( $id )->post_content !== $after ) { throw new RuntimeException( 'media_write_verification_failed:' . $id ); }
				if ( hash_equals( hash( 'sha256', $before ), $old_hash ) ) { update_post_meta( $id, '_celina_applied_content_hash', hash( 'sha256', $after ) ); }
				$receipt = [ 'revision_id' => $revision_id, 'before_hash' => hash( 'sha256', $before ), 'after_hash' => hash( 'sha256', $after ), 'previous_applied_hash' => $old_hash ];
				if ( ! update_post_meta( $id, '_celina_media_receipt', $receipt ) ) { throw new RuntimeException( 'media_receipt_failed:' . $id ); }
				if ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'media_commit_failed:' . $id ); }
				$changed[] = $id;
			} catch ( Throwable $error ) {
				$wpdb->query( 'ROLLBACK' );
				clean_post_cache( $id );
				if ( is_int( $revision_id ) && $revision_id > 0 ) { clean_post_cache( $revision_id ); }
				throw $error;
			}
		}
		celina_media_refresh_patterns();
		return [ 'imported' => count( $plan['imports'] ), 'updated' => $changed ];
	} finally {
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
	}
}
