<?php
defined( 'ABSPATH' ) || exit;

function celina_block_shell_option_name(): string {
	return 'celina_block_shell_' . md5( get_stylesheet() );
}

/** Include drafts and trash: unpublished editorial work is still a conflict. */
function celina_block_shell_parts( string $slug ): array {
	return get_posts( [ 'post_type' => 'wp_template_part', 'post_status' => array_keys( get_post_stati() ), 'post_name__in' => [ $slug, $slug . '__trashed' ], 'posts_per_page' => -1, 'tax_query' => [ [ 'taxonomy' => 'wp_theme', 'field' => 'name', 'terms' => get_stylesheet() ] ] ] );
}

function celina_block_shell_enabled(): bool {
	$state = get_option( celina_block_shell_option_name(), [] );
	return ! empty( $state['enabled'] );
}

function celina_block_shell_plan(): array {
	$state = get_option( celina_block_shell_option_name(), [] );
	$plan = [ 'action' => empty( $state['parts'] ) ? 'initialize' : ( empty( $state['enabled'] ) ? 'enable' : 'noop' ), 'parts' => [], 'conflicts' => [] ];
	foreach ( [ 'header', 'footer' ] as $slug ) {
		$parts = celina_block_shell_parts( $slug );
		if ( ! empty( $state['parts'] ) ) {
			if ( 1 !== count( $parts ) || (int) $parts[0]->ID !== (int) ( $state['parts'][ $slug ] ?? 0 ) || 'publish' !== $parts[0]->post_status ) {
				$plan['conflicts'][] = 'Saved ' . $slug . ' is missing, unpublished, or replaced. Restore it before enabling.';
			}
		} elseif ( $parts ) {
			$plan['conflicts'][] = 'Existing ' . $slug . ' template part is editor-owned and will not be overwritten.';
		} else {
			$plan['parts'][ $slug ] = celina_block_shell_content( $slug );
		}
	}
	if ( $plan['conflicts'] ) { $plan['action'] = 'conflict'; }
	return $plan;
}

function celina_block_shell_validate( string $content ): bool {
	$walk = static function ( array $blocks ) use ( &$walk ): bool {
		foreach ( $blocks as $block ) {
			if ( empty( $block['blockName'] ) || ! WP_Block_Type_Registry::get_instance()->is_registered( $block['blockName'] ) || ! $walk( $block['innerBlocks'] ) ) { return false; }
		}
		return true;
	};
	$blocks = parse_blocks( $content );
	return [] !== $blocks && $walk( $blocks ) && serialize_blocks( $blocks ) === $content;
}

/** Atomic insert, unlike add_option's upsert whose changed value can report success twice. */
function celina_block_shell_acquire_lock(): bool {
	global $wpdb;
	$key = celina_block_shell_option_name() . '_lock';
	$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)", $key, 'locked', 'off' ) );
	wp_cache_delete( $key, 'options' );
	wp_cache_delete( 'notoptions', 'options' );
	return 1 === $inserted;
}

/** Retain partial records, including any concurrent editor saves, for deliberate recovery. */
function celina_block_shell_initialization_error( WP_Error $error, array $created ): WP_Error {
	if ( ! $created ) { return $error; }
	return new WP_Error( $error->get_error_code(), $error->get_error_message() . ' Block shell remains disabled. Created template parts were retained for review (IDs: ' . implode( ', ', array_keys( $created ) ) . '). Compare them in Appearance > Patterns before deliberately removing incomplete parts and retrying.', [ 'retained_part_ids' => array_keys( $created ) ] );
}

function celina_block_shell_apply() {
	if ( ! current_user_can( 'edit_theme_options' ) ) { return new WP_Error( 'forbidden', 'Header and footer changes require edit_theme_options.' ); }
	$key = celina_block_shell_option_name();
	// No timed lock stealing: an interrupted run requires inspection.
	if ( ! celina_block_shell_acquire_lock() ) { return new WP_Error( 'locked', 'A shell operation is already in progress. Inspect an interrupted operation before removing its lock.' ); }
	$created = [];
	try {
		$plan = celina_block_shell_plan();
		if ( 'conflict' === $plan['action'] ) { return new WP_Error( 'conflict', implode( ' ', $plan['conflicts'] ) ); }
		if ( 'noop' === $plan['action'] ) { return $plan; }
		$state = get_option( $key, [] );
		if ( 'initialize' === $plan['action'] ) {
			foreach ( $plan['parts'] as $content ) {
				if ( ! celina_block_shell_validate( $content ) ) { return new WP_Error( 'invalid_content', 'Shell composition is invalid. Nothing was changed.' ); }
			}
			$state = [ 'enabled' => false, 'parts' => [] ];
			foreach ( $plan['parts'] as $slug => $content ) {
				// Recheck immediately before inserting; never adopt or overwrite a part created elsewhere.
				if ( celina_block_shell_parts( $slug ) ) { return celina_block_shell_initialization_error( new WP_Error( 'conflict', 'A template part was created during initialization.' ), $created ); }
				$id = wp_insert_post( wp_slash( [ 'post_type' => 'wp_template_part', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => 'CELINA ' . ucfirst( $slug ), 'post_content' => $content ] ), true );
				if ( is_wp_error( $id ) ) { return celina_block_shell_initialization_error( $id, $created ); }
				$created[ $id ] = $content;
				foreach ( [ 'wp_theme' => get_stylesheet(), 'wp_template_part_area' => $slug ] as $taxonomy => $term ) {
					$result = wp_set_object_terms( $id, $term, $taxonomy );
					if ( is_wp_error( $result ) ) { return celina_block_shell_initialization_error( $result, $created ); }
				}
				$state['parts'][ $slug ] = $id;
			}
		}
		$state['enabled'] = true;
		if ( ! update_option( $key, $state, false ) ) { return celina_block_shell_initialization_error( new WP_Error( 'state_write', 'Could not enable the shell.' ), $created ); }
		return $plan;
	} catch ( Throwable $error ) {
		return celina_block_shell_initialization_error( new WP_Error( 'initialization_failed', $error->getMessage() ), $created );
	} finally {
		delete_option( $key . '_lock' );
	}
}

function celina_block_shell_disable() {
	if ( ! current_user_can( 'edit_theme_options' ) ) { return new WP_Error( 'forbidden', 'Header and footer changes require edit_theme_options.' ); }
	$key = celina_block_shell_option_name();
	if ( ! celina_block_shell_acquire_lock() ) { return new WP_Error( 'locked', 'A shell operation is already in progress.' ); }
	try {
		$state = get_option( $key, [] );
		if ( empty( $state['enabled'] ) ) { return true; }
		$state['enabled'] = false;
		return update_option( $key, $state, false ) ? true : new WP_Error( 'state_write', 'Could not disable the shell.' );
	} finally { delete_option( $key . '_lock' ); }
}

/** Returning true means the saved part was handled, including intentional empty content. */
function celina_render_block_shell_part( string $slug ): bool {
	if ( ! celina_block_shell_enabled() ) { return false; }
	$state = get_option( celina_block_shell_option_name(), [] );
	$post = get_post( (int) ( $state['parts'][ $slug ] ?? 0 ) );
	if ( ! $post || 'wp_template_part' !== $post->post_type || 'publish' !== $post->post_status || $slug !== $post->post_name || ! has_term( get_stylesheet(), 'wp_theme', $post ) ) { return false; }
	$template = get_block_template( get_stylesheet() . '//' . $slug, 'wp_template_part' );
	if ( ! $template || (int) ( $template->wp_id ?? 0 ) !== $post->ID ) { return false; }
	if ( '' !== $post->post_content ) {
		echo do_blocks( serialize_block( celina_shell_block( 'template-part', [ 'slug' => $slug, 'theme' => get_stylesheet(), 'tagName' => 'div', 'className' => 'celina-block-' . $slug . '-part' ] ) ) );
	}
	return true;
}
