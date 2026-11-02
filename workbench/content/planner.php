<?php
/**
 * Pure planning rules and WordPress adapters for managed CELINA content.
 *
 * @package Celina_Workbench
 */

declare( strict_types=1 );

require_once __DIR__ . '/redesigned.php';

const CELINA_CONTENT_GROUPS = [
	'brand-pages',
	'offer-pages',
	'people-contact-pages',
	'news-resources',
	'legal-pages',
];

const CELINA_CONTENT_PARTIAL_EXIT_CODE = 20;

const CELINA_CONTENT_EXPECTED_CONFLICT_REASONS = [
	'duplicate_blueprint_key',
	'identity_conflict',
	'identity_migration_collision',
	'invalid_applied_hash',
	'invalid_blueprint_version',
	'manual_content_conflict',
	'reading_options_conflict',
	'refused_version_downgrade',
	'unmanaged_slug_collision',
	'category_identity_conflict',
	'redesign_rollback_hold',
];

function celina_content_result(
	string $action,
	array $entry,
	string $reason,
	array $details = []
): array {
	return [
		'action'  => $action,
		'key'     => (string) ( $entry['key'] ?? '' ),
		'reason'  => $reason,
		'details' => $details,
	];
}

function celina_content_filter_manifest( array $manifest, ?string $group ): array {
	if ( null === $group ) {
		return array_values( $manifest );
	}

	if ( ! in_array( $group, CELINA_CONTENT_GROUPS, true ) ) {
		throw new InvalidArgumentException( 'Unknown managed-content group.' );
	}

	return array_values(
		array_filter(
			$manifest,
			static fn( array $entry ): bool => $group === ( $entry['group'] ?? null )
		)
	);
}

function celina_content_is_valid_adoption( array $entry ): bool {
	$adopt_existing = $entry['adopt_existing'] ?? null;
	$type           = $entry['type'] ?? null;

	return 'page.privacy-policy' === ( $entry['key'] ?? null ) &&
		1 === ( $entry['version'] ?? null ) &&
		'page' === $type &&
		is_array( $adopt_existing ) &&
		3 === count( $adopt_existing ) &&
		isset(
			$adopt_existing['id'],
			$adopt_existing['type'],
			$adopt_existing['slug']
		) &&
		is_int( $adopt_existing['id'] ) &&
		$adopt_existing['id'] > 0 &&
		$adopt_existing['type'] === $type &&
		$adopt_existing['slug'] === ( $entry['slug'] ?? null );
}

function celina_content_validate_manifest( array $manifest ): array {
	$errors   = [];
	$keys     = [];
	$patterns = [];

	foreach ( $manifest as $index => $entry ) {
		$key     = (string) ( $entry['key'] ?? '' );
		$pattern = (string) ( $entry['pattern'] ?? '' );
		$version = $entry['version'] ?? null;
		$type    = $entry['type'] ?? null;
		$migrate_from = $entry['migrate_from'] ?? null;
		$adopt_existing = $entry['adopt_existing'] ?? null;
		$valid_migration = null === $migrate_from || (
			'page' === $type &&
			is_array( $migrate_from ) &&
			3 === count( $migrate_from ) &&
			isset( $migrate_from['version'], $migrate_from['slug'], $migrate_from['title'] ) &&
			is_int( $migrate_from['version'] ) &&
			$migrate_from['version'] >= 1 &&
			is_int( $version ) &&
			$migrate_from['version'] < $version &&
			'' !== (string) $migrate_from['slug'] &&
			'' !== (string) $migrate_from['title']
		);
		$valid_adoption = null === $adopt_existing || celina_content_is_valid_adoption( $entry );
		$valid_redesign = ! isset( $entry['redesign_from'] ) || celina_content_is_valid_redesign( $entry );

		if ( '' === $key || isset( $keys[ $key ] ) ) {
			$errors[] = celina_content_result(
				'conflict',
				$entry,
				'duplicate_manifest_key',
				[ 'index' => $index ]
			);
		}
		$keys[ $key ] = true;

		if ( '' === $pattern || isset( $patterns[ $pattern ] ) ) {
			$errors[] = celina_content_result(
				'conflict',
				$entry,
				'duplicate_manifest_pattern',
				[ 'index' => $index ]
			);
		}
		$patterns[ $pattern ] = true;

		if (
			! is_int( $version ) ||
			$version < 1 ||
			! in_array( $type, [ 'page', 'post' ], true ) ||
			! in_array( $entry['group'] ?? null, CELINA_CONTENT_GROUPS, true ) ||
			! $valid_migration ||
			! $valid_adoption ||
			! $valid_redesign ||
			'' === (string) ( $entry['slug'] ?? '' ) ||
			'' === (string) ( $entry['title'] ?? '' ) ||
			'publish' !== ( $entry['status'] ?? null ) ||
			( 'page' === $type && 0 !== ( $entry['parent'] ?? null ) ) ||
			( 'post' === $type && ! isset( $entry['category']['name'], $entry['category']['slug'] ) )
		) {
			$errors[] = celina_content_result(
				'conflict',
				$entry,
				'invalid_manifest_entry',
				[ 'index' => $index ]
			);
		}
	}

	return $errors;
}

function celina_content_hash( string $content ): string {
	return hash( 'sha256', $content );
}

function celina_content_record_identity_differences(
	array $entry,
	array $record
): array {
	$differences = [];
	$expected    = [
		'type'   => $entry['type'],
		'slug'   => $entry['slug'],
		'title'  => $entry['title'],
		'status' => $entry['status'],
	];

	if ( 'page' === $entry['type'] ) {
		$expected['parent'] = $entry['parent'];
	}

	foreach ( $expected as $field => $value ) {
		if ( $value !== ( $record[ $field ] ?? null ) ) {
			$differences[ $field ] = [
				'actual'   => $record[ $field ] ?? null,
				'expected' => $value,
			];
		}
	}

	if ( 'post' === $entry['type'] ) {
		$actual_categories   = array_values( $record['categories'] ?? [] );
		$expected_categories = [ $entry['category']['slug'] ];
		sort( $actual_categories );
		sort( $expected_categories );
		if ( $actual_categories !== $expected_categories ) {
			$differences['categories'] = [
				'actual'   => $actual_categories,
				'expected' => $expected_categories,
			];
		}
	}

	return $differences;
}

function celina_content_record_has_no_blueprint_metadata( array $record ): bool {
	$metadata_exists = $record['blueprint_meta_exists'] ?? [];

	return false === ( $metadata_exists['key'] ?? true ) &&
		false === ( $metadata_exists['version'] ?? true ) &&
		false === ( $metadata_exists['hash'] ?? true );
}

function celina_content_plan_entry(
	array $entry,
	string $canonical_content,
	array $records
): array {
	$owned = array_values(
		array_filter(
			$records,
			static fn( array $record ): bool =>
				( $record['blueprint_key'] ?? null ) === $entry['key']
		)
	);

	if ( count( $owned ) > 1 ) {
		return celina_content_result(
			'conflict',
			$entry,
			'duplicate_blueprint_key',
			[ 'ids' => array_column( $owned, 'id' ) ]
		);
	}

	$source_hash = celina_content_hash( $canonical_content );

	if ( 0 === count( $owned ) ) {
		$collisions = array_values(
			array_filter(
				$records,
				static fn( array $record ): bool =>
					( $record['type'] ?? null ) === $entry['type'] &&
					( $record['slug'] ?? null ) === $entry['slug']
			)
		);

		if ( count( $collisions ) > 0 ) {
			$adoption_candidates = celina_content_is_valid_adoption( $entry )
				? array_values(
					array_filter(
						$collisions,
						static fn( array $record ): bool =>
							( $entry['adopt_existing']['id'] ?? null ) ===
							( $record['id'] ?? null ) &&
							( $entry['adopt_existing']['type'] ?? null ) ===
							( $record['type'] ?? null ) &&
							( $entry['adopt_existing']['slug'] ?? null ) ===
							( $record['slug'] ?? null ) &&
							celina_content_record_has_no_blueprint_metadata( $record )
					)
				)
				: [];
			if ( 1 === count( $collisions ) && 1 === count( $adoption_candidates ) ) {
				return celina_content_result(
					'update',
					$entry,
					'adoption',
					[ 'id' => $adoption_candidates[0]['id'] ?? null ]
				);
			}

			return celina_content_result(
				'conflict',
				$entry,
				'unmanaged_slug_collision',
				[
					'ids'        => array_column( $collisions, 'id' ),
					'candidates' => array_map(
						static fn( array $record ): array => [
							'id'                   => $record['id'] ?? null,
							'blueprint_key'        => (string) ( $record['blueprint_key'] ?? '' ),
							'blueprint_meta_exists' => $record['blueprint_meta_exists'] ?? [],
							'identity_differences' => celina_content_record_identity_differences(
								$entry,
								$record
							),
						],
						$collisions
					),
				]
			);
		}

		return celina_content_result(
			'create',
			$entry,
			'missing',
			[ 'source_hash' => $source_hash ]
		);
	}

	$record         = $owned[0];
	$stored_version = $record['blueprint_version'] ?? null;
	$applied_hash   = $record['applied_hash'] ?? null;

	if ( ! is_int( $stored_version ) || $stored_version < 1 ) {
		return celina_content_result(
			'conflict',
			$entry,
			'invalid_blueprint_version',
			[ 'id' => $record['id'] ?? null ]
		);
	}

	if ( $stored_version > $entry['version'] ) {
		return celina_content_result(
			'conflict',
			$entry,
			'refused_version_downgrade',
			[ 'id' => $record['id'] ?? null ]
		);
	}

	$identity_differences = celina_content_record_identity_differences(
		$entry,
		$record
	);
	$identity_migration = false;
	if ( count( $identity_differences ) > 0 ) {
		$migrate_from = $entry['migrate_from'] ?? null;
		$previous_entry = is_array( $migrate_from )
			? array_replace(
				$entry,
				[
					'slug'  => $migrate_from['slug'],
					'title' => $migrate_from['title'],
				]
			)
			: null;
		$previous_identity_differences = is_array( $previous_entry )
			? celina_content_record_identity_differences( $previous_entry, $record )
			: [ 'migration' => [ 'actual' => null, 'expected' => null ] ];

		if (
			is_array( $migrate_from ) &&
			$stored_version === $migrate_from['version'] &&
			0 === count( $previous_identity_differences )
		) {
			$collisions = array_values(
				array_filter(
					$records,
					static fn( array $candidate ): bool =>
						( $candidate['id'] ?? null ) !== ( $record['id'] ?? null ) &&
						( $candidate['type'] ?? null ) === $entry['type'] &&
						( $candidate['slug'] ?? null ) === $entry['slug']
				)
			);
			if ( count( $collisions ) > 0 ) {
				return celina_content_result(
					'conflict',
					$entry,
					'identity_migration_collision',
					[ 'ids' => array_column( $collisions, 'id' ) ]
				);
			}

			$identity_migration = true;
		} else {
			return celina_content_result(
				'conflict',
				$entry,
				'identity_conflict',
				[
					'differences' => $identity_differences,
					'id'          => $record['id'] ?? null,
				]
			);
		}
	}

	if ( ! is_string( $applied_hash ) || 64 !== strlen( $applied_hash ) ) {
		return celina_content_result(
			'conflict',
			$entry,
			'invalid_applied_hash',
			[ 'id' => $record['id'] ?? null ]
		);
	}

	if ( ! empty( $record['redesign_hold'] ) ) {
		return celina_content_result( 'conflict', $entry, 'redesign_rollback_hold',
			[ 'id' => $record['id'] ?? null ] );
	}

	if ( celina_content_is_valid_redesign( $entry ) &&
		in_array( $stored_version, $entry['redesign_from']['versions'], true ) ) {
		return celina_content_result( 'update', $entry, 'redesign_snapshot', [
			'id' => $record['id'],
			'source_hash' => $source_hash,
			'previous_fingerprint' => celina_content_record_fingerprint( $record ),
			'previous_identity' => $identity_migration ? $entry['migrate_from'] : null,
		] );
	}

	if ( $stored_version === $entry['version'] && $applied_hash !== $source_hash ) {
		return celina_content_result(
			'conflict',
			$entry,
			'source_changed_without_version_bump',
			[ 'id' => $record['id'] ?? null ]
		);
	}

	if ( celina_content_hash( (string) ( $record['content'] ?? '' ) ) !== $applied_hash ) {
		return celina_content_result(
			'conflict',
			$entry,
			'manual_content_conflict',
			[ 'id' => $record['id'] ?? null ]
		);
	}

	if ( $stored_version < $entry['version'] ) {
		return celina_content_result(
			'update',
			$entry,
			$identity_migration ? 'identity_migration' : 'version_upgrade',
			[
				'id'          => $record['id'] ?? null,
				'source_hash' => $source_hash,
				'previous_identity' => $identity_migration ? $entry['migrate_from'] : null,
			]
		);
	}

	return celina_content_result(
		'noop',
		$entry,
		'current',
		[ 'id' => $record['id'] ?? null ]
	);
}

function celina_content_plan(
	array $manifest,
	array $sources,
	array $records
): array {
	$manifest_errors = celina_content_validate_manifest( $manifest );
	if ( count( $manifest_errors ) > 0 ) {
		return $manifest_errors;
	}

	$results = [];
	foreach ( $manifest as $entry ) {
		$pattern = $entry['pattern'];
		if ( ! isset( $sources[ $pattern ] ) || ! is_string( $sources[ $pattern ] ) ) {
			$results[] = celina_content_result(
				'conflict',
				$entry,
				'missing_pattern'
			);
			continue;
		}

		$results[] = celina_content_plan_entry(
			$entry,
			$sources[ $pattern ],
			$records
		);
	}

	return $results;
}

function celina_content_resolve_pattern_sources( array $manifest ): array {
	$sources = [];
	$errors  = [];

	if ( ! class_exists( 'WP_Block_Patterns_Registry' ) ) {
		foreach ( $manifest as $entry ) {
			$errors[] = celina_content_result(
				'conflict',
				$entry,
				'pattern_registry_unavailable'
			);
		}

		return [ 'errors' => $errors, 'sources' => $sources ];
	}

	$registry = WP_Block_Patterns_Registry::get_instance();
	foreach ( $manifest as $entry ) {
		$registered = $registry->get_registered( $entry['pattern'] );
		if ( ! is_array( $registered ) || ! isset( $registered['content'] ) ) {
			$errors[] = celina_content_result(
				'conflict',
				$entry,
				'missing_pattern'
			);
			continue;
		}

		if ( ! is_string( $registered['content'] ) || '' === trim( $registered['content'] ) ) {
			$errors[] = celina_content_result(
				'conflict',
				$entry,
				'invalid_pattern_content'
			);
			continue;
		}

		$sources[ $entry['pattern'] ] = $registered['content'];
	}

	return [ 'errors' => $errors, 'sources' => $sources ];
}

function celina_content_normalize_meta_version( $value ) {
	if ( is_int( $value ) ) {
		return $value;
	}

	if ( is_string( $value ) && preg_match( '/^[1-9][0-9]*$/', $value ) ) {
		return (int) $value;
	}

	return null;
}

function celina_content_load_records(): array {
	$posts   = get_posts(
		[
			'numberposts'      => -1,
			'orderby'          => 'ID',
			'order'            => 'ASC',
			'post_status'      => [
				'publish',
				'future',
				'draft',
				'pending',
				'private',
				'trash',
				'auto-draft',
				'inherit',
			],
			'post_type'        => [ 'page', 'post' ],
			'suppress_filters' => true,
		]
	);
	$records = [];

	foreach ( $posts as $post ) {
		$categories = [];
		if ( 'post' === $post->post_type ) {
			$category_slugs = wp_get_post_categories(
				$post->ID,
				[ 'fields' => 'slugs' ]
			);
			if ( ! is_wp_error( $category_slugs ) ) {
				$categories = array_values( $category_slugs );
			}
		}

		$records[] = [
			'id'                => (int) $post->ID,
			'type'              => (string) $post->post_type,
			'slug'              => (string) $post->post_name,
			'title'             => (string) $post->post_title,
			'status'            => (string) $post->post_status,
			'parent'            => 'page' === $post->post_type ? (int) $post->post_parent : null,
			'content'           => (string) $post->post_content,
			'categories'        => $categories,
			'blueprint_key'     => (string) get_post_meta(
				$post->ID,
				'_celina_blueprint_key',
				true
			),
			'blueprint_version' => celina_content_normalize_meta_version(
				get_post_meta( $post->ID, '_celina_blueprint_version', true )
			),
			'applied_hash'      => (string) get_post_meta(
				$post->ID,
				'_celina_applied_content_hash',
				true
			),
			'blueprint_meta_exists' => [
				'key'     => metadata_exists( 'post', $post->ID, '_celina_blueprint_key' ),
				'version' => metadata_exists( 'post', $post->ID, '_celina_blueprint_version' ),
				'hash'    => metadata_exists( 'post', $post->ID, '_celina_applied_content_hash' ),
			],
			'redesign_hold' => (string) get_post_meta( $post->ID, '_celina_redesign_hold', true ),
		];
	}

	return $records;
}

function celina_content_plan_category( array $entry ): array {
	if ( ! isset( $entry['category'] ) ) {
		return [ 'action' => 'noop', 'reason' => 'not_required', 'term_id' => null ];
	}

	$category = $entry['category'];
	$by_slug  = get_terms(
		[
			'hide_empty' => false,
			'slug'       => $category['slug'],
			'taxonomy'   => 'category',
		]
	);
	$by_name  = get_terms(
		[
			'hide_empty' => false,
			'name'       => $category['name'],
			'taxonomy'   => 'category',
		]
	);

	if ( is_wp_error( $by_slug ) || is_wp_error( $by_name ) ) {
		return [ 'action' => 'conflict', 'reason' => 'category_query_failed', 'term_id' => null ];
	}

	$candidates = [];
	foreach ( array_merge( $by_slug, $by_name ) as $term ) {
		$candidates[ (int) $term->term_id ] = $term;
	}

	if ( 0 === count( $candidates ) ) {
		return [ 'action' => 'create', 'reason' => 'category_missing', 'term_id' => null ];
	}

	if ( 1 === count( $candidates ) ) {
		$term      = reset( $candidates );
		$term_name = html_entity_decode( $term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( $category['slug'] === $term->slug && $category['name'] === $term_name ) {
			return [
				'action'  => 'noop',
				'reason'  => 'category_current',
				'term_id' => (int) $term->term_id,
			];
		}
	}

	return [
		'action'  => 'conflict',
		'reason'  => 'category_identity_conflict',
		'term_id' => null,
		'details' => [ 'ids' => array_keys( $candidates ) ],
	];
}

function celina_content_attach_category_plan(
	array $results,
	array $manifest
): array {
	$entries = [];
	foreach ( $manifest as $entry ) {
		$entries[ $entry['key'] ] = $entry;
	}

	foreach ( $results as $index => $result ) {
		$entry = $entries[ $result['key'] ];
		if ( ! isset( $entry['category'] ) ) {
			continue;
		}

		$category_plan = celina_content_plan_category( $entry );
		if ( 'conflict' === $category_plan['action'] ) {
			$results[ $index ] = celina_content_result(
				'conflict',
				$entry,
				$category_plan['reason'],
				$category_plan['details'] ?? []
			);
			continue;
		}

		$results[ $index ]['details']['category'] = $category_plan;
	}

	return $results;
}

function celina_content_build_wordpress_plan( array $manifest ): array {
	$manifest_errors = celina_content_validate_manifest( $manifest );
	if ( count( $manifest_errors ) > 0 ) {
		return $manifest_errors;
	}

	$resolved = celina_content_resolve_pattern_sources( $manifest );
	if ( count( $resolved['errors'] ) > 0 ) {
		return $resolved['errors'];
	}

	return celina_content_attach_category_plan(
		celina_content_plan(
			$manifest,
			$resolved['sources'],
			celina_content_load_records()
		),
		$manifest
	);
}

function celina_content_ensure_category( array $entry, array $category_plan ) {
	if ( ! isset( $entry['category'] ) ) {
		return null;
	}

	if ( isset( $category_plan['term_id'] ) && $category_plan['term_id'] > 0 ) {
		return (int) $category_plan['term_id'];
	}

	$inserted = wp_insert_term(
		$entry['category']['name'],
		'category',
		[ 'slug' => $entry['category']['slug'] ]
	);
	if ( is_wp_error( $inserted ) ) {
		$replanned = celina_content_plan_category( $entry );
		if ( 'noop' === $replanned['action'] && $replanned['term_id'] > 0 ) {
			return (int) $replanned['term_id'];
		}

		return new WP_Error( 'celina_category_write_failed', $inserted->get_error_message() );
	}

	$term_id   = (int) $inserted['term_id'];
	$replanned = celina_content_plan_category( $entry );
	if ( 'noop' !== $replanned['action'] || $term_id !== $replanned['term_id'] ) {
		return new WP_Error(
			'celina_category_verification_failed',
			'Category did not match after creation.'
		);
	}

	return $term_id;
}

function celina_content_write_entry(
	array $entry,
	string $canonical_content,
	array $operation
): array {
	if ( 'redesign_snapshot' === ( $operation['reason'] ?? null ) ) {
		return celina_content_write_redesign( $entry, $canonical_content, $operation );
	}
	$category_id = null;
	if ( isset( $entry['category'] ) ) {
		$category_id = celina_content_ensure_category(
			$entry,
			$operation['details']['category'] ?? []
		);
		if ( is_wp_error( $category_id ) ) {
			return celina_content_result(
				'conflict',
				$entry,
				'category_write_failed',
				[ 'message' => $category_id->get_error_message() ]
			);
		}
	}

	$post_data = [
		'post_content' => wp_slash( $canonical_content ),
	];
	if ( 'create' === $operation['action'] ) {
		$post_data = array_merge(
			$post_data,
			[
				'post_name'   => $entry['slug'],
				'post_status' => $entry['status'],
				'post_title'  => $entry['title'],
				'post_type'   => $entry['type'],
			]
		);
		if ( 'page' === $entry['type'] ) {
			$post_data['post_parent'] = $entry['parent'];
		}
		if ( null !== $category_id ) {
			$post_data['post_category'] = [ $category_id ];
		}
		$post_id = wp_insert_post( $post_data, true );
	} else {
		$post_data['ID'] = (int) $operation['details']['id'];
		if ( in_array( $operation['reason'] ?? '', [ 'identity_migration', 'adoption' ], true ) ) {
			$post_data['post_name']   = $entry['slug'];
			$post_data['post_status'] = $entry['status'];
			$post_data['post_title']  = $entry['title'];
			if ( 'page' === $entry['type'] ) {
				$post_data['post_parent'] = $entry['parent'];
			}
		}
		$post_id         = wp_update_post( $post_data, true );
		if ( ! is_wp_error( $post_id ) && null !== $category_id ) {
			$category_result = wp_set_post_categories(
				(int) $post_id,
				[ $category_id ],
				false
			);
			if ( is_wp_error( $category_result ) ) {
				$post_id = $category_result;
			}
		}
	}

	if ( is_wp_error( $post_id ) || ! $post_id ) {
		return celina_content_result(
			'conflict',
			$entry,
			'post_write_failed',
			[
				'message' => is_wp_error( $post_id )
					? $post_id->get_error_message()
					: 'WordPress returned no post ID.',
			]
		);
	}

	update_post_meta( $post_id, '_celina_blueprint_key', $entry['key'] );
	update_post_meta( $post_id, '_celina_blueprint_version', $entry['version'] );
	if ( celina_content_is_valid_redesign( $entry ) ) {
		update_post_meta( $post_id, '_celina_redesigned_version', $entry['version'] );
	}
	update_post_meta(
		$post_id,
		'_celina_applied_content_hash',
		celina_content_hash( $canonical_content )
	);
	clean_post_cache( $post_id );

	$records = celina_content_load_records();
	$verify  = celina_content_plan_entry( $entry, $canonical_content, $records );
	if ( 'noop' !== $verify['action'] ) {
		return celina_content_result(
			'conflict',
			$entry,
			'write_verification_failed',
			[ 'verification' => $verify ]
		);
	}

	return celina_content_result(
		$operation['action'],
		$entry,
		'create' === $operation['action'] ? 'created' : 'updated',
		[ 'id' => (int) $post_id ]
	);
}

function celina_content_find_canonical_record_id(
	string $key,
	array $manifest,
	array $records
): ?int {
	$entry = null;
	foreach ( $manifest as $candidate ) {
		if ( $key === $candidate['key'] ) {
			$entry = $candidate;
			break;
		}
	}

	if ( null === $entry ) {
		return null;
	}

	$owned = array_values(
		array_filter(
			$records,
			static fn( array $record ): bool => $key === $record['blueprint_key']
		)
	);
	if (
		1 !== count( $owned ) ||
		count( celina_content_record_identity_differences( $entry, $owned[0] ) ) > 0 ||
		$entry['version'] !== $owned[0]['blueprint_version'] ||
		! is_string( $owned[0]['applied_hash'] ) ||
		64 !== strlen( $owned[0]['applied_hash'] ) ||
		celina_content_hash( $owned[0]['content'] ) !== $owned[0]['applied_hash']
	) {
		return null;
	}

	return (int) $owned[0]['id'];
}

function celina_content_plan_reading_options(
	array $manifest,
	array $records
): array {
	$front_id = celina_content_find_canonical_record_id(
		'page.homepage',
		$manifest,
		$records
	);
	$posts_id = celina_content_find_canonical_record_id(
		'page.corporate-news',
		$manifest,
		$records
	);

	if ( null === $front_id || null === $posts_id ) {
		return [
			'action' => 'noop',
			'key'    => 'site.reading',
			'reason' => 'deferred_missing_pages',
			'details' => [],
		];
	}

	$missing = '__celina_missing_option__';
	$current = [
		get_option( 'show_on_front', $missing ),
		get_option( 'page_on_front', $missing ),
		get_option( 'page_for_posts', $missing ),
	];
	$canonical = [ 'page', $front_id, $posts_id ];
	$bootstrap = [ 'posts', 0, 0 ];
	$absent    = [ $missing, $missing, $missing ];
	$normalized_current = [
		$current[0],
		is_numeric( $current[1] ) ? (int) $current[1] : $current[1],
		is_numeric( $current[2] ) ? (int) $current[2] : $current[2],
	];

	if ( $canonical === $normalized_current ) {
		return [
			'action' => 'noop',
			'key'    => 'site.reading',
			'reason' => 'current',
			'details' => [ 'values' => $canonical ],
		];
	}

	if ( $bootstrap === $normalized_current || $absent === $normalized_current ) {
		return [
			'action' => 'update',
			'key'    => 'site.reading',
			'reason' => 'bootstrap',
			'details' => [ 'values' => $canonical ],
		];
	}

	return [
		'action' => 'conflict',
		'key'    => 'site.reading',
		'reason' => 'reading_options_conflict',
		'details' => [ 'actual' => $normalized_current, 'expected' => $canonical ],
	];
}

function celina_content_apply_reading_options( array $operation ): array {
	if ( 'update' !== $operation['action'] ) {
		return $operation;
	}

	$values  = $operation['details']['values'];
	$missing = '__celina_missing_option__';
	$before  = [
		'show_on_front' => get_option( 'show_on_front', $missing ),
		'page_on_front' => get_option( 'page_on_front', $missing ),
		'page_for_posts' => get_option( 'page_for_posts', $missing ),
	];
	update_option( 'show_on_front', $values[0] );
	update_option( 'page_on_front', $values[1] );
	update_option( 'page_for_posts', $values[2] );

	$actual = [
		get_option( 'show_on_front' ),
		(int) get_option( 'page_on_front' ),
		(int) get_option( 'page_for_posts' ),
	];
	if ( $values !== $actual ) {
		foreach ( $before as $option => $value ) {
			if ( $missing === $value ) {
				delete_option( $option );
			} else {
				update_option( $option, $value );
			}
		}

		return [
			'action' => 'conflict',
			'key'    => 'site.reading',
			'reason' => 'reading_options_write_failed',
			'details' => [ 'actual' => $actual, 'expected' => $values ],
		];
	}

	return [
		'action' => 'update',
		'key'    => 'site.reading',
		'reason' => 'updated',
		'details' => [ 'values' => $values ],
	];
}

function celina_content_print_results( array $results, callable $write ): void {
	$totals = [ 'conflict' => 0, 'create' => 0, 'noop' => 0, 'update' => 0 ];
	foreach ( $results as $result ) {
		$action = $result['action'];
		if ( isset( $totals[ $action ] ) ) {
			$totals[ $action ]++;
		}
		$write(
			sprintf(
				'%s %s %s %s',
				strtoupper( $action ),
				$result['key'],
				$result['reason'],
				wp_json_encode( $result['details'] )
			)
		);
	}

	$write(
		sprintf(
			'TOTAL create=%d update=%d noop=%d conflict=%d',
			$totals['create'],
			$totals['update'],
			$totals['noop'],
			$totals['conflict']
		)
	);
}

function celina_content_is_expected_conflict( array $result ): bool {
	return 'conflict' === ( $result['action'] ?? null ) &&
		in_array(
			$result['reason'] ?? null,
			CELINA_CONTENT_EXPECTED_CONFLICT_REASONS,
			true
		);
}

function celina_content_conflict_fingerprint( array $result ): string {
	return (string) ( $result['key'] ?? '' ) . "\0" .
		(string) ( $result['reason'] ?? '' );
}

function celina_content_apply_exit_code(
	array $planned,
	array $applied,
	bool $allow_expected_conflicts
): int {
	$applied_conflicts = array_values(
		array_filter(
			$applied,
			static fn( array $result ): bool => 'conflict' === ( $result['action'] ?? null )
		)
	);
	if ( 0 === count( $applied_conflicts ) ) {
		return 0;
	}
	if ( ! $allow_expected_conflicts ) {
		return 1;
	}

	$planned_expected_conflicts = [];
	$reading_conflict_may_be_revealed = false;
	foreach ( $planned as $result ) {
		if ( celina_content_is_expected_conflict( $result ) ) {
			$planned_expected_conflicts[ celina_content_conflict_fingerprint( $result ) ] = true;
		}
		if (
			'site.reading' === ( $result['key'] ?? null ) &&
			'deferred_missing_pages' === ( $result['reason'] ?? null )
		) {
			$reading_conflict_may_be_revealed = true;
		}
	}

	foreach ( $applied_conflicts as $result ) {
		$is_revealed_reading_conflict = $reading_conflict_may_be_revealed &&
			'site.reading' === ( $result['key'] ?? null ) &&
			'reading_options_conflict' === ( $result['reason'] ?? null );
		if (
			! celina_content_is_expected_conflict( $result ) ||
			(
				! $is_revealed_reading_conflict &&
				! isset(
				$planned_expected_conflicts[
					celina_content_conflict_fingerprint( $result )
				]
				)
			)
		) {
			return 1;
		}
	}

	return CELINA_CONTENT_PARTIAL_EXIT_CODE;
}

function celina_content_execute(
	string $mode,
	?string $group,
	callable $write
): int {
	if ( ! in_array( $mode, [ 'plan', 'plan-safe', 'apply', 'apply-safe' ], true ) ) {
		$write( 'ERROR invalid_mode' );

		return 2;
	}

	try {
		$complete_manifest = celina_content_manifest();
		$manifest          = celina_content_filter_manifest(
			$complete_manifest,
			$group
		);
	} catch ( InvalidArgumentException $error ) {
		$write( 'ERROR invalid_group' );

		return 2;
	}

	$resolved = celina_content_resolve_pattern_sources( $manifest );
	if ( count( $resolved['errors'] ) > 0 ) {
		celina_content_print_results( $resolved['errors'], $write );

		return 1;
	}

	$results = celina_content_attach_category_plan(
		celina_content_plan(
			$manifest,
			$resolved['sources'],
			celina_content_load_records()
		),
		$manifest
	);
	$source_state_errors = array_filter(
		$results,
		static fn( array $result ): bool =>
			'source_changed_without_version_bump' === $result['reason']
	);
	$allow_expected_conflicts = in_array( $mode, [ 'plan-safe', 'apply-safe' ], true );
	$hard_conflicts = array_filter(
		$results,
		static fn( array $result ): bool =>
			'conflict' === ( $result['action'] ?? null ) &&
			! celina_content_is_expected_conflict( $result )
	);

	if (
		in_array( $mode, [ 'apply', 'apply-safe' ], true ) &&
		(
			count( $source_state_errors ) > 0 ||
			( 'apply-safe' === $mode && count( $hard_conflicts ) > 0 )
		)
	) {
		celina_content_print_results( $results, $write );

		return 1;
	}

	if ( in_array( $mode, [ 'plan', 'plan-safe' ], true ) ) {
		$results[] = celina_content_plan_reading_options(
			$complete_manifest,
			celina_content_load_records()
		);
		celina_content_print_results( $results, $write );

		return celina_content_apply_exit_code(
			$results,
			$results,
			$allow_expected_conflicts
		);
	}

	$entries_by_key = [];
	foreach ( $manifest as $entry ) {
		$entries_by_key[ $entry['key'] ] = $entry;
	}

	$initial_reading_plan = celina_content_plan_reading_options(
		$complete_manifest,
		celina_content_load_records()
	);
	$planned_for_exit     = array_merge( $results, [ $initial_reading_plan ] );
	$applied              = [];
	foreach ( $results as $result ) {
		if ( ! in_array( $result['action'], [ 'create', 'update' ], true ) ) {
			$applied[] = $result;
			continue;
		}

		$entry     = $entries_by_key[ $result['key'] ];
		$applied[] = celina_content_write_entry(
			$entry,
			$resolved['sources'][ $entry['pattern'] ],
			$result
		);
	}

	$reading_plan = celina_content_plan_reading_options(
		$complete_manifest,
		celina_content_load_records()
	);
	$applied[]    = 'update' === $reading_plan['action']
		? celina_content_apply_reading_options( $reading_plan )
		: $reading_plan;

	celina_content_print_results( $applied, $write );

	return celina_content_apply_exit_code(
		$planned_for_exit,
		$applied,
		$allow_expected_conflicts
	);
}
