<?php

declare( strict_types=1 );

require dirname( __DIR__, 2 ) . '/workbench/content/manifest.php';
require dirname( __DIR__, 2 ) . '/workbench/content/planner.php';

$failures = [];

function managed_content_expect( bool $condition, string $message ): void {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

function managed_content_entry( array $changes = [] ): array {
	$entry            = celina_content_manifest()[2];
	$entry['version'] = 1;
	unset( $entry['redesign_from'] );

	return array_replace_recursive( $entry, $changes );
}

function managed_content_record(
	array $entry,
	string $content = '<!-- wp:paragraph --><p>Canonical</p><!-- /wp:paragraph -->',
	array $changes = []
): array {
	$record = [
		'id'                => 42,
		'type'              => $entry['type'],
		'slug'              => $entry['slug'],
		'title'             => $entry['title'],
		'status'            => $entry['status'],
		'parent'            => $entry['parent'] ?? null,
		'content'           => $content,
		'categories'        => isset( $entry['category'] ) ? [ $entry['category']['slug'] ] : [],
		'blueprint_key'     => $entry['key'],
		'blueprint_version' => $entry['version'],
		'applied_hash'      => celina_content_hash( $content ),
		'blueprint_meta_exists' => [
			'key'     => true,
			'version' => true,
			'hash'    => true,
		],
	];

	return array_replace( $record, $changes );
}

function managed_content_expect_plan(
	string $action,
	string $reason,
	array $entry,
	string $content,
	array $records,
	string $case
): void {
	$result = celina_content_plan_entry( $entry, $content, $records );
	managed_content_expect(
		$action === $result['action'] && $reason === $result['reason'],
		"{$case}: expected {$action}/{$reason}, got {$result['action']}/{$result['reason']}"
	);
	managed_content_expect(
		isset( $result['details'] ) && is_array( $result['details'] ),
		"{$case}: result details must always be an array"
	);
}

function managed_content_find_manifest_entry( array $manifest, string $key ): array {
	foreach ( $manifest as $entry ) {
		if ( $key === $entry['key'] ) {
			return $entry;
		}
	}

	throw new RuntimeException( "Managed content manifest entry {$key} is missing." );
}

$manifest = celina_content_manifest();
managed_content_expect( 17 === count( $manifest ), 'Manifest must contain seventeen identities' );
managed_content_expect(
	[
		'page.homepage',
		'page.about',
		'page.capabilities',
		'page.markets',
		'page.why-celina',
		'page.community',
		'page.careers',
		'page.contact',
		'page.corporate-news',
		'page.corporate-resources',
		'post.thirty-years',
		'post.celina-turns-30',
		'post.ata-tent-training',
		'post.one-celina-brand',
		'post.recycled-rubble-foundation',
		'page.privacy-policy',
		'page.terms-and-conditions',
	] === array_column( $manifest, 'key' ),
	'Manifest identities and order must match the approved migration scope'
);
managed_content_expect(
	[] === celina_content_validate_manifest( $manifest ),
	'The canonical manifest must validate'
);
foreach ( $manifest as $entry ) {
	managed_content_expect(
		! array_key_exists( 'content', $entry ),
		"{$entry['key']} must reference a pattern instead of duplicating content"
	);
	if ( 'page' === $entry['type'] && 'legal-pages' !== $entry['group'] ) {
		managed_content_expect( celina_content_is_valid_redesign( $entry ),
			"{$entry['key']} must use its exact redesigned source and a valid one-time transition" );
	} else {
		managed_content_expect( ! isset( $entry['redesign_from'] ), "{$entry['key']} is outside the redesign migration" );
	}
	$expected_version = match ( $entry['key'] ) {
		'page.capabilities' => 6,
		'page.homepage', 'page.markets', 'page.community', 'page.careers', 'page.contact', 'page.corporate-news' => 5,
		'post.thirty-years',
		'post.celina-turns-30',
		'post.ata-tent-training',
		'post.one-celina-brand',
		'post.recycled-rubble-foundation',
		'page.privacy-policy',
		'page.terms-and-conditions' => 1,
		default => 4,
	};
	managed_content_expect(
		$expected_version === $entry['version'],
		"{$entry['key']} must use blueprint version {$expected_version}"
	);
}
managed_content_expect(
	3 === count( celina_content_filter_manifest( $manifest, 'brand-pages' ) ),
	'Brand group must contain exactly three entries'
);
managed_content_expect(
	2 === count( celina_content_filter_manifest( $manifest, 'people-contact-pages' ) ),
	'People/contact group must contain exactly two entries'
);
managed_content_expect(
	7 === count( celina_content_filter_manifest( $manifest, 'news-resources' ) ),
	'News/resources group must contain the landing pages, existing anniversary article, and four approved migrations'
);
managed_content_expect(
	2 === count( celina_content_filter_manifest( $manifest, 'legal-pages' ) ),
	'Legal group must contain exactly two entries'
);

$migrated_articles = array_values(
	array_filter(
		$manifest,
		static fn( array $entry ): bool => 0 === strpos( $entry['key'], 'post.' ) &&
			'post.thirty-years' !== $entry['key']
	)
);
managed_content_expect(
	[
		[ 'celina-manufacturing-anniversary', 'company-milestones' ],
		[ 'ata-tent-training-celina', 'community-education' ],
		[ 'celina-brand-consilidation', 'company-announcements' ],
		[ 'celina-elementary-school-rubble-recycled', 'community-education' ],
	] === array_map(
		static fn( array $entry ): array => [ $entry['slug'], $entry['category']['slug'] ],
		$migrated_articles
	),
	'Migrated articles must preserve their canonical source slugs and approved categories'
);

$markets_entries = array_values(
	array_filter(
		$manifest,
		static fn( array $candidate ): bool => 'page.markets' === $candidate['key']
	)
);
$markets_entry   = $markets_entries[0];
managed_content_expect(
	'industries' === $markets_entry['slug'] &&
	'CELINA Industries' === $markets_entry['title'] &&
	'celina/redesigned-page-industries' === $markets_entry['pattern'],
	'The managed markets identity must publish the client-approved Industries page'
);
managed_content_expect(
	[
		'version' => 2,
		'slug'    => 'markets',
		'title'   => 'Markets',
	] === ( $markets_entry['migrate_from'] ?? null ),
	'The Industries page must declare its exact previous managed identity'
);

unset( $markets_entry['redesign_from'] );
$privacy_entry = managed_content_find_manifest_entry( $manifest, 'page.privacy-policy' );
managed_content_expect(
	[
		'id'     => 3,
		'type'   => 'page',
		'slug'   => 'privacy-policy',
	] === ( $privacy_entry['adopt_existing'] ?? null ),
	'The Privacy Policy must declare the approved demo record by its exact ID and slug'
);
$privacy_collision = managed_content_record(
	$privacy_entry,
	'<!-- wp:paragraph --><p>Legacy privacy policy</p><!-- /wp:paragraph -->',
	[
		'blueprint_key'     => '',
		'blueprint_version' => null,
		'applied_hash'      => '',
		'id'                => 3,
		'title'             => 'Legacy Privacy Policy',
		'status'            => 'draft',
		'parent'            => 7,
		'blueprint_meta_exists' => [
			'key'     => false,
			'version' => false,
			'hash'    => false,
		],
	]
);
$privacy_content = '<!-- wp:paragraph --><p>Canonical privacy policy</p><!-- /wp:paragraph -->';
managed_content_expect_plan(
	'update',
	'adoption',
	$privacy_entry,
	$privacy_content,
	[ $privacy_collision ],
	'approved exact Privacy Policy adoption'
);
managed_content_expect_plan(
	'conflict',
	'unmanaged_slug_collision',
	$privacy_entry,
	$privacy_content,
	[
		array_replace( $privacy_collision, [ 'id' => 4 ] ),
	],
	'privacy adoption must reject an unmanaged page with another ID'
);
$privacy_diagnostic = celina_content_plan_entry(
	$privacy_entry,
	$privacy_content,
	[ array_replace( $privacy_collision, [ 'id' => 4 ] ) ]
);
managed_content_expect(
	[
		'id'                   => 4,
		'blueprint_key'        => '',
		'blueprint_meta_exists' => [
			'key'     => false,
			'version' => false,
			'hash'    => false,
		],
		'identity_differences' => [
			'title' => [
				'actual'   => 'Legacy Privacy Policy',
				'expected' => 'Privacy Policy',
			],
			'status' => [
				'actual'   => 'draft',
				'expected' => 'publish',
			],
			'parent' => [
				'actual'   => 7,
				'expected' => 0,
			],
		],
	] === ( $privacy_diagnostic['details']['candidates'][0] ?? null ),
	'Collision diagnostics must identify the record and metadata/identity mismatch without exposing content'
);
managed_content_expect(
	false === strpos( json_encode( $privacy_diagnostic['details'] ), 'Legacy privacy policy' ),
	'Collision diagnostics must not expose record content'
);
managed_content_expect_plan(
	'conflict',
	'unmanaged_slug_collision',
	$privacy_entry,
	$privacy_content,
	[
		$privacy_collision,
		array_replace( $privacy_collision, [ 'id' => 4, 'title' => 'Legacy Privacy Policy' ] ),
	],
	'privacy adoption must reject ambiguous collisions'
);
managed_content_expect_plan(
	'conflict',
	'unmanaged_slug_collision',
	$privacy_entry,
	$privacy_content,
	[
		array_replace(
			$privacy_collision,
			[
				'blueprint_meta_exists' => [
					'key'     => true,
					'version' => false,
					'hash'    => false,
				],
			]
		),
	],
	'privacy adoption must not take over a page with even an empty CELINA marker'
);
$invalid_privacy_adoption = array_replace(
	$privacy_entry,
	[ 'adopt_existing' => [] ]
);
managed_content_expect(
	[] !== celina_content_validate_manifest( [ $invalid_privacy_adoption ] ),
	'An incomplete adoption declaration must invalidate the manifest'
);
managed_content_expect_plan(
	'conflict',
	'unmanaged_slug_collision',
	$invalid_privacy_adoption,
	$privacy_content,
	[ $privacy_collision ],
	'privacy adoption must fail closed when its declaration is invalid'
);

$content = '<!-- wp:paragraph --><p>Canonical</p><!-- /wp:paragraph -->';
$entry   = managed_content_entry();
$record  = managed_content_record( $entry, $content );

managed_content_expect_plan( 'create', 'missing', $entry, $content, [], 'create' );
managed_content_expect_plan( 'noop', 'current', $entry, $content, [ $record ], 'no-op' );
managed_content_expect_plan(
	'conflict',
	'manual_content_conflict',
	$entry,
	$content,
	[ array_replace( $record, [ 'content' => '<p>Manual edit</p>' ] ) ],
	'manual content'
);
managed_content_expect_plan(
	'conflict',
	'identity_conflict',
	$entry,
	$content,
	[ array_replace( $record, [ 'type' => 'post' ] ) ],
	'post type identity'
);
managed_content_expect_plan(
	'conflict',
	'identity_conflict',
	$entry,
	$content,
	[ array_replace( $record, [ 'parent' => 7 ] ) ],
	're-parented page'
);
managed_content_expect_plan(
	'conflict',
	'identity_conflict',
	$entry,
	$content,
	[ array_replace( $record, [ 'status' => 'trash' ] ) ],
	'trashed record'
);
managed_content_expect_plan(
	'conflict',
	'identity_conflict',
	$entry,
	$content,
	[ array_replace( $record, [ 'slug' => 'renamed-home' ] ) ],
	'renamed slug'
);
managed_content_expect_plan(
	'conflict',
	'unmanaged_slug_collision',
	$entry,
	$content,
	[
		array_replace(
			$record,
			[
				'blueprint_key' => '',
				'id'            => 91,
			]
		),
	],
	'unmanaged slug collision'
);
managed_content_expect_plan(
	'conflict',
	'duplicate_blueprint_key',
	$entry,
	$content,
	[ $record, array_replace( $record, [ 'id' => 43 ] ) ],
	'duplicate blueprint key'
);
managed_content_expect_plan(
	'conflict',
	'invalid_blueprint_version',
	$entry,
	$content,
	[ array_replace( $record, [ 'blueprint_version' => '1' ] ) ],
	'invalid version'
);

$upgrade_entry   = managed_content_entry( [ 'version' => 2 ] );
$previous_source = '<!-- wp:paragraph --><p>Previous</p><!-- /wp:paragraph -->';
$upgrade_record  = managed_content_record(
	$upgrade_entry,
	$previous_source,
	[ 'blueprint_version' => 1 ]
);
managed_content_expect_plan(
	'update',
	'version_upgrade',
	$upgrade_entry,
	$content,
	[ $upgrade_record ],
	'version upgrade'
);
managed_content_expect_plan(
	'conflict',
	'refused_version_downgrade',
	$entry,
	$content,
	[ array_replace( $record, [ 'blueprint_version' => 2 ] ) ],
	'refused downgrade'
);
managed_content_expect_plan(
	'conflict',
	'source_changed_without_version_bump',
	$entry,
	$content,
	[ managed_content_record( $entry, $previous_source ) ],
	'source changed without version bump'
);

$community_entry = managed_content_find_manifest_entry( $manifest, 'page.community' );
$community_entry['version'] = 3;
unset( $community_entry['redesign_from'] );
$previous_community_content = '<!-- wp:celina/industry-cards {"columns":3} /-->';
$redesigned_community_content = '<!-- wp:celina/industry-cards {"columns":1} /-->';
$previous_community_record = managed_content_record(
	$community_entry,
	$previous_community_content,
	[ 'blueprint_version' => 2 ]
);
managed_content_expect_plan(
	'update',
	'version_upgrade',
	$community_entry,
	$redesigned_community_content,
	[ $previous_community_record ],
	'Community version 2 upgrades to the redesigned card layout'
);
managed_content_expect_plan(
	'conflict',
	'manual_content_conflict',
	$community_entry,
	$redesigned_community_content,
	[
		array_replace(
			$previous_community_record,
			[ 'content' => $previous_community_content . '<!-- wp:paragraph --><p>Client copy</p><!-- /wp:paragraph -->' ]
		),
	],
	'Community layout upgrade preserves client edits'
);

$previous_markets_content = '<!-- wp:paragraph --><p>Previous markets page</p><!-- /wp:paragraph -->';
$previous_markets_record  = managed_content_record(
	$markets_entry,
	$previous_markets_content,
	[
		'blueprint_version' => 2,
		'slug'              => 'markets',
		'title'             => 'Markets',
	]
);
managed_content_expect_plan(
	'update',
	'identity_migration',
	$markets_entry,
	$content,
	[ $previous_markets_record ],
	'approved markets-to-industries identity migration'
);
managed_content_expect_plan(
	'conflict',
	'manual_content_conflict',
	$markets_entry,
	$content,
	[
		array_replace(
			$previous_markets_record,
			[ 'content' => '<!-- wp:paragraph --><p>Manual legacy markets edit</p><!-- /wp:paragraph -->' ]
		),
	],
	'identity migration with manual content'
);
managed_content_expect_plan(
	'conflict',
	'identity_migration_collision',
	$markets_entry,
	$content,
	[
		$previous_markets_record,
		managed_content_record(
			$markets_entry,
			$content,
			[
				'blueprint_key' => '',
				'id'            => 91,
			]
		),
	],
	'industries slug collision during identity migration'
);

$post_entry  = $manifest[10];
$post_record = managed_content_record( $post_entry, $content );
managed_content_expect_plan(
	'conflict',
	'identity_conflict',
	$post_entry,
	$content,
	[ array_replace( $post_record, [ 'categories' => [] ] ) ],
	'missing category'
);
managed_content_expect_plan(
	'conflict',
	'identity_conflict',
	$post_entry,
	$content,
	[
		array_replace(
			$post_record,
			[ 'categories' => [ 'company-milestones', 'other' ] ]
		),
	],
	'extra category'
);

$missing_source_plan = celina_content_plan( [ $entry ], [], [] );
managed_content_expect(
	'missing_pattern' === $missing_source_plan[0]['reason'],
	'Missing registered pattern must be a source conflict'
);

managed_content_expect(
	defined( 'CELINA_CONTENT_PARTIAL_EXIT_CODE' ) &&
	20 === CELINA_CONTENT_PARTIAL_EXIT_CODE,
	'Expected managed-content conflicts must have the stable partial exit code 20'
);
managed_content_expect(
	function_exists( 'celina_content_apply_exit_code' ),
	'The planner must expose a pure safe-apply exit classifier'
);
if ( function_exists( 'celina_content_apply_exit_code' ) ) {
	$planned_conflict = celina_content_result(
		'conflict',
		$entry,
		'manual_content_conflict'
	);
	$planned_update = celina_content_result( 'update', $upgrade_entry, 'version_upgrade' );
	$applied_update = celina_content_result( 'update', $upgrade_entry, 'updated' );
	$write_conflict  = celina_content_result( 'conflict', $upgrade_entry, 'post_write_failed' );
	$source_conflict = celina_content_result(
		'conflict',
		$upgrade_entry,
		'source_changed_without_version_bump'
	);
	$deferred_reading = [
		'action'  => 'noop',
		'key'     => 'site.reading',
		'reason'  => 'deferred_missing_pages',
		'details' => [],
	];
	$revealed_reading_conflict = [
		'action'  => 'conflict',
		'key'     => 'site.reading',
		'reason'  => 'reading_options_conflict',
		'details' => [],
	];

	managed_content_expect(
		0 === celina_content_apply_exit_code( [ $planned_update ], [ $applied_update ], true ),
		'A conflict-free safe apply must succeed'
	);
	managed_content_expect(
		1 === celina_content_apply_exit_code( [ $planned_conflict ], [ $planned_conflict ], false ),
		'Strict apply must retain failure semantics for planned conflicts'
	);
	managed_content_expect(
		20 === celina_content_apply_exit_code( [ $planned_conflict ], [ $planned_conflict ], true ),
		'Safe apply must classify only pre-existing conflicts as partial success'
	);
	managed_content_expect(
		1 === celina_content_apply_exit_code(
			[ $planned_conflict, $planned_update ],
			[ $planned_conflict, $write_conflict ],
			true
		),
		'Safe apply must fail hard when a planned safe write becomes a conflict'
	);
	managed_content_expect(
		1 === celina_content_apply_exit_code( [ $source_conflict ], [ $source_conflict ], true ),
		'Safe apply must fail hard on an unversioned source change'
	);
	managed_content_expect(
		20 === celina_content_apply_exit_code(
			[ $planned_update, $deferred_reading ],
			[ $applied_update, $revealed_reading_conflict ],
			true
		),
		'Safe apply must allow a Reading conflict revealed by creating its managed pages'
	);
}

$redesign_entry = array_replace( $community_entry, [
	'version' => 4,
	'pattern' => 'celina/redesigned-page-community',
	'redesign_from' => [ 'version' => 4, 'versions' => [ 2, 3 ] ],
] );
$redesign_record = managed_content_record( $redesign_entry, $previous_community_content, [
	'blueprint_version' => 2,
] );
$edited_redesign_record = array_replace( $redesign_record, [ 'content' => 'Existing approved demo copy' ] );
managed_content_expect_plan( 'update', 'redesign_snapshot', $redesign_entry, $redesigned_community_content,
	[ $edited_redesign_record ], 'declared redesign archives previous manual copy before replacement' );
managed_content_expect_plan( 'conflict', 'manual_content_conflict', $redesign_entry, $redesigned_community_content,
	[ managed_content_record( $redesign_entry, $redesigned_community_content, [ 'content' => 'Later client edit' ] ) ],
	'completed redesign protects later client edits' );
managed_content_expect_plan( 'conflict', 'redesign_rollback_hold', $redesign_entry, $redesigned_community_content,
	[ array_replace( $redesign_record, [ 'redesign_hold' => '99' ] ) ], 'rollback is not automatically reapplied' );
managed_content_expect_plan( 'conflict', 'manual_content_conflict', $redesign_entry, $redesigned_community_content,
	[ array_replace( $edited_redesign_record, [ 'blueprint_version' => 1 ] ) ], 'unapproved prior version stays protected' );
managed_content_expect_plan( 'conflict', 'identity_conflict', $redesign_entry, $redesigned_community_content,
	[ array_replace( $edited_redesign_record, [ 'title' => 'Different page' ] ) ], 'redesign never bypasses identity safety' );
foreach ( [
	[ 'version' => 5, 'versions' => [ 2 ] ],
	[ 'version' => 4, 'versions' => [ 4 ] ],
	[ 'version' => 4, 'versions' => [ '2' ] ],
] as $invalid_redesign ) {
	managed_content_expect( [] !== celina_content_validate_manifest( [ array_replace( $redesign_entry,
		[ 'redesign_from' => $invalid_redesign ] ) ] ), 'invalid one-time redesign declaration is rejected' );
}

if ( count( $failures ) > 0 ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, "- {$failure}\n" );
	}
	exit( 1 );
}

echo "Managed content contract passed\n";
