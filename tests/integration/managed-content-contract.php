<?php
/**
 * Live WordPress contract for managed CELINA content.
 *
 * @package Celina_Workbench
 */

require_once '/var/www/html/celina-workbench/content/manifest.php';
require_once '/var/www/html/celina-workbench/content/planner.php';

$managed_content_failures            = [];
$managed_content_registered_patterns = [];
$managed_content_created_post_ids    = [];
$managed_content_option_missing      = '__celina_contract_missing__';
$managed_content_original_options    = [];
$managed_content_manifest            = celina_content_manifest();
$managed_content_keys                = array_column( $managed_content_manifest, 'key' );
$managed_content_original_categories = [];
$managed_content_original_post_ids   = get_posts(
	[
		'fields'         => 'ids',
		'numberposts'    => -1,
		'post_status'    => 'any',
		'post_type'      => [ 'page', 'post' ],
		'suppress_filters' => true,
	]
);
$managed_content_original_category = get_term_by(
	'slug',
	'company-milestones',
	'category'
);
foreach ( $managed_content_manifest as $entry ) {
	if ( ! isset( $entry['category']['slug'] ) ) {
		continue;
	}

	$category_slug = $entry['category']['slug'];
	if ( isset( $managed_content_original_categories[ $category_slug ] ) ) {
		continue;
	}

	$managed_content_original_categories[ $category_slug ] = get_term_by(
		'slug',
		$category_slug,
		'category'
	);
}

foreach ( [ 'show_on_front', 'page_on_front', 'page_for_posts' ] as $option ) {
	$managed_content_original_options[ $option ] = get_option(
		$option,
		$managed_content_option_missing
	);
}

function managed_content_live_expect( bool $condition, string $message ): void {
	global $managed_content_failures;

	if ( ! $condition ) {
		$managed_content_failures[] = $message;
	}
}

function managed_content_live_results( string $mode, ?string $group = null ): array {
	$lines = [];
	$code  = celina_content_execute(
		$mode,
		$group,
		static function ( string $line ) use ( &$lines ): void {
			$lines[] = $line;
		}
	);

	return [ 'code' => $code, 'lines' => $lines ];
}

function managed_content_live_find_record( string $key ): ?array {
	foreach ( celina_content_load_records() as $record ) {
		if ( $key === $record['blueprint_key'] ) {
			return $record;
		}
	}

	return null;
}

function managed_content_live_find_manifest_entry( string $key ): array {
	foreach ( celina_content_manifest() as $entry ) {
		if ( $key === $entry['key'] ) {
			return $entry;
		}
	}

	throw new RuntimeException( "Managed content manifest entry {$key} is missing." );
}

try {
	$registry = WP_Block_Patterns_Registry::get_instance();
	foreach ( $managed_content_manifest as $entry ) {
		if ( $registry->is_registered( $entry['pattern'] ) ) {
			continue;
		}

		$registered = register_block_pattern(
			$entry['pattern'],
			[
				'content'    => sprintf(
					'<!-- wp:paragraph --><p>Managed contract: %s</p><!-- /wp:paragraph -->',
					esc_html( $entry['key'] )
				),
				'postTypes'  => [ $entry['type'] ],
				'title'      => 'Managed contract ' . $entry['key'],
			]
		);
		if ( $registered ) {
			$managed_content_registered_patterns[] = $entry['pattern'];
		}
	}

	$before_ids = get_posts(
		[
			'fields'           => 'ids',
			'numberposts'      => -1,
			'post_status'      => [ 'publish', 'future', 'draft', 'pending', 'private', 'trash' ],
			'post_type'        => [ 'page', 'post' ],
			'suppress_filters' => true,
		]
	);
	$plan       = managed_content_live_results( 'plan' );
	$after_ids  = get_posts(
		[
			'fields'           => 'ids',
			'numberposts'      => -1,
			'post_status'      => [ 'publish', 'future', 'draft', 'pending', 'private', 'trash' ],
			'post_type'        => [ 'page', 'post' ],
			'suppress_filters' => true,
		]
	);
	managed_content_live_expect( 0 === $plan['code'], 'Fresh plan must succeed' );
	managed_content_live_expect( $before_ids === $after_ids, 'Plan must not write posts' );
	$category_after_plan = get_term_by( 'slug', 'company-milestones', 'category' );
	managed_content_live_expect(
		( false === $managed_content_original_category && false === $category_after_plan ) ||
		( false !== $managed_content_original_category &&
			false !== $category_after_plan &&
			$managed_content_original_category->term_id === $category_after_plan->term_id &&
			$managed_content_original_category->name === $category_after_plan->name ),
		'Plan must not create or alter the managed article category'
	);
	foreach ( $managed_content_original_options as $option => $value ) {
		managed_content_live_expect(
			$value === get_option( $option, $managed_content_option_missing ),
			"Plan must not write the {$option} option"
		);
	}
	managed_content_live_expect(
		17 === count(
			array_filter(
				$plan['lines'],
				static fn( string $line ): bool => 0 === strpos( $line, 'CREATE ' )
			)
		),
		'Fresh plan must report seventeen creates'
	);

	$apply = managed_content_live_results( 'apply' );
	managed_content_live_expect( 0 === $apply['code'], 'Initial apply must succeed' );

	$managed_content_records = celina_content_load_records();
	foreach ( $managed_content_records as $record ) {
		if (
			in_array( $record['blueprint_key'], $managed_content_keys, true ) &&
			! in_array( $record['id'], $managed_content_original_post_ids, true )
		) {
			$managed_content_created_post_ids[] = $record['id'];
		}
	}
	managed_content_live_expect(
		17 === count( $managed_content_created_post_ids ),
		'Apply must create all seventeen managed records'
	);

	foreach ( $managed_content_manifest as $entry ) {
		$record     = managed_content_live_find_record( $entry['key'] );
		$registered = $registry->get_registered( $entry['pattern'] );
		managed_content_live_expect(
			null !== $record,
			"{$entry['key']} must resolve after apply"
		);
		if ( null !== $record && is_array( $registered ) ) {
			managed_content_live_expect(
				celina_content_hash( $registered['content'] ) === $record['applied_hash'],
				"{$entry['key']} must hash the exact registered pattern content"
			);
		}
	}

	$industries_entry          = $managed_content_manifest[3];
	$industries_record         = managed_content_live_find_record( 'page.markets' );
	$previous_markets_content  = '<!-- wp:paragraph --><p>Previous managed markets page.</p><!-- /wp:paragraph -->';
	wp_update_post(
		[
			'ID'           => $industries_record['id'],
			'post_content' => wp_slash( $previous_markets_content ),
			'post_name'    => 'markets',
			'post_title'   => 'Markets',
		]
	);
	update_post_meta( $industries_record['id'], '_celina_blueprint_version', 2 );
	update_post_meta(
		$industries_record['id'],
		'_celina_applied_content_hash',
		celina_content_hash( $previous_markets_content )
	);
	$industries_pattern = $registry->get_registered( $industries_entry['pattern'] );
	$industries_migration = celina_content_plan_entry(
		$industries_entry,
		$industries_pattern['content'],
		celina_content_load_records()
	);
	managed_content_live_expect(
		'update' === $industries_migration['action'] &&
		'redesign_snapshot' === $industries_migration['reason'],
		'The exact managed Markets identity must migrate to Industries'
	);
	$industries_write = celina_content_write_entry(
		$industries_entry,
		$industries_pattern['content'],
		$industries_migration
	);
	$industries_after = managed_content_live_find_record( 'page.markets' );
	managed_content_live_expect(
		'redesigned' === $industries_write['reason'] &&
		'industries' === $industries_after['slug'] &&
		'CELINA Industries' === $industries_after['title'],
		'The Industries migration must update content, slug, and title atomically'
	);

	$article = managed_content_live_find_record( 'post.thirty-years' );
	managed_content_live_expect(
		null !== $article && [ 'company-milestones' ] === $article['categories'],
		'The managed article must have exactly the canonical category'
	);
	foreach (
		[
			'post.celina-turns-30'            => 'company-milestones',
			'post.ata-tent-training'          => 'community-education',
			'post.one-celina-brand'           => 'company-announcements',
			'post.recycled-rubble-foundation' => 'community-education',
		] as $key => $category_slug
	) {
		$migrated_article = managed_content_live_find_record( $key );
		managed_content_live_expect(
			null !== $migrated_article && [ $category_slug ] === $migrated_article['categories'],
			"{$key} must have exactly its approved category"
		);
	}
	$community_category = get_term_by( 'slug', 'community-education', 'category' );
	$community_category_plan = celina_content_plan_category( $managed_content_manifest[12] );
	managed_content_live_expect(
		false !== $community_category &&
		'noop' === $community_category_plan['action'] &&
		$community_category->term_id === $community_category_plan['term_id'],
		'A category containing an ampersand must be reusable after WordPress normalizes its name'
	);
	$category_plan = celina_content_plan_category( $managed_content_manifest[10] );
	managed_content_live_expect(
		'noop' === $category_plan['action'],
		'The canonical category must be reused after creation'
	);
	$category_term = get_term_by( 'slug', 'company-milestones', 'category' );
	wp_update_term(
		$category_term->term_id,
		'category',
		[ 'name' => 'Wrong milestone category' ]
	);
	$category_conflict = celina_content_plan_category( $managed_content_manifest[10] );
	managed_content_live_expect(
		'conflict' === $category_conflict['action'] &&
		'category_identity_conflict' === $category_conflict['reason'],
		'A matching category slug with another name must be a conflict'
	);
	wp_update_term(
		$category_term->term_id,
		'category',
		[ 'name' => 'Company Milestones' ]
	);

	$resources_entry   = $managed_content_manifest[9];
	$resources_record  = managed_content_live_find_record( $resources_entry['key'] );
	$resources_pattern = $registry->get_registered( $resources_entry['pattern'] );
	$upgrade_entry     = array_replace( $resources_entry, [ 'version' => $resources_entry['version'] + 1 ] );
	unset( $upgrade_entry['redesign_from'] );
	$upgrade_content   = $resources_pattern['content'] . "\n<!-- managed version three -->";
	$upgrade_operation = celina_content_plan_entry(
		$upgrade_entry,
		$upgrade_content,
		celina_content_load_records()
	);
	managed_content_live_expect(
		'update' === $upgrade_operation['action'] &&
		'version_upgrade' === $upgrade_operation['reason'],
		'A reviewed version increase must plan a safe update'
	);
	$upgrade_write = celina_content_write_entry(
		$upgrade_entry,
		$upgrade_content,
		$upgrade_operation
	);
	managed_content_live_expect(
		'updated' === $upgrade_write['reason'],
		'A safe version increase must apply and verify'
	);
	wp_update_post(
		[
			'ID'           => $resources_record['id'],
			'post_content' => wp_slash( $resources_pattern['content'] ),
		]
	);
	update_post_meta( $resources_record['id'], '_celina_blueprint_version', $resources_entry['version'] );
	update_post_meta(
		$resources_record['id'],
		'_celina_applied_content_hash',
		celina_content_hash( $resources_pattern['content'] )
	);

	$home = managed_content_live_find_record( 'page.homepage' );
	$news = managed_content_live_find_record( 'page.corporate-news' );
	managed_content_live_expect(
		null !== $home && null !== $news &&
		'page' === get_option( 'show_on_front' ) &&
		$home['id'] === (int) get_option( 'page_on_front' ) &&
		$news['id'] === (int) get_option( 'page_for_posts' ),
		'Apply must atomically establish the canonical Reading tuple'
	);

	$privacy_entry  = managed_content_live_find_manifest_entry( 'page.privacy-policy' );
	$privacy_record = managed_content_live_find_record( 'page.privacy-policy' );
	wp_delete_post( $privacy_record['id'], true );
	$legacy_privacy_id = wp_insert_post(
		[
			'post_content' => '<!-- wp:paragraph --><p>Legacy privacy policy</p><!-- /wp:paragraph -->',
			'post_name'    => 'privacy-policy',
			'post_parent'  => 0,
			'post_status'  => 'draft',
			'post_title'   => 'Legacy Privacy Policy',
			'post_type'    => 'page',
		],
		true
	);
	managed_content_live_expect(
		! is_wp_error( $legacy_privacy_id ) && $legacy_privacy_id > 0,
		'The unmanaged Privacy Policy fixture must be created'
	);
	$managed_content_created_post_ids[] = (int) $legacy_privacy_id;
	$adoption_entry   = array_replace(
		$privacy_entry,
		[
			'adopt_existing' => array_replace(
				$privacy_entry['adopt_existing'],
				[ 'id' => (int) $legacy_privacy_id ]
			),
		]
	);
	$privacy_pattern = $registry->get_registered( $privacy_entry['pattern'] );
	$privacy_adoption = celina_content_plan_entry(
		$adoption_entry,
		$privacy_pattern['content'],
		celina_content_load_records()
	);
	managed_content_live_expect(
		'update' === $privacy_adoption['action'] &&
		'adoption' === $privacy_adoption['reason'] &&
		(int) $legacy_privacy_id === (int) ( $privacy_adoption['details']['id'] ?? 0 ),
		'The exact unmanaged Privacy Policy must plan an explicit adoption'
	);
	$privacy_write = celina_content_write_entry(
		$adoption_entry,
		$privacy_pattern['content'],
		$privacy_adoption
	);
	$privacy_after = managed_content_live_find_record( 'page.privacy-policy' );
	managed_content_live_expect(
		'updated' === $privacy_write['reason'] &&
		(int) $legacy_privacy_id === (int) ( $privacy_after['id'] ?? 0 ) &&
		'Privacy Policy' === ( $privacy_after['title'] ?? null ) &&
		'publish' === ( $privacy_after['status'] ?? null ) &&
		0 === ( $privacy_after['parent'] ?? null ) &&
		$privacy_after['blueprint_meta_exists']['key'] &&
		$privacy_after['blueprint_meta_exists']['version'] &&
		$privacy_after['blueprint_meta_exists']['hash'] &&
		celina_content_hash( $privacy_pattern['content'] ) === ( $privacy_after['applied_hash'] ?? null ),
		'Adoption must normalize the page identity and establish managed ownership'
	);
	delete_post_meta( $legacy_privacy_id, '_celina_blueprint_key' );
	delete_post_meta( $legacy_privacy_id, '_celina_blueprint_version' );
	delete_post_meta( $legacy_privacy_id, '_celina_applied_content_hash' );
	update_post_meta( $legacy_privacy_id, '_celina_blueprint_key', '' );
	$partial_metadata_plan = celina_content_plan_entry(
		$adoption_entry,
		$privacy_pattern['content'],
		celina_content_load_records()
	);
	managed_content_live_expect(
		'conflict' === $partial_metadata_plan['action'] &&
		'unmanaged_slug_collision' === $partial_metadata_plan['reason'],
		'Adoption must refuse a page with even an empty CELINA metadata marker'
	);
	delete_post_meta( $legacy_privacy_id, '_celina_blueprint_key' );
	$privacy_restore = celina_content_plan_entry(
		$adoption_entry,
		$privacy_pattern['content'],
		celina_content_load_records()
	);
	$privacy_restore_write = celina_content_write_entry(
		$adoption_entry,
		$privacy_pattern['content'],
		$privacy_restore
	);
	managed_content_live_expect(
		'update' === $privacy_restore['action'] &&
		'adoption' === $privacy_restore['reason'] &&
		'updated' === $privacy_restore_write['reason'],
		'An authorized Privacy Policy must remain adoptable after CELINA metadata cleanup'
	);
	$privacy_rerun = celina_content_plan_entry(
		$adoption_entry,
		$privacy_pattern['content'],
		celina_content_load_records()
	);
	managed_content_live_expect(
		'noop' === $privacy_rerun['action'] && 'current' === $privacy_rerun['reason'],
		'An adopted Privacy Policy must become current on the next plan'
	);

	$rerun = managed_content_live_results( 'plan' );
	managed_content_live_expect( 0 === $rerun['code'], 'No-op rerun must succeed' );
	managed_content_live_expect(
		18 === count(
			array_filter(
				$rerun['lines'],
				static fn( string $line ): bool => 0 === strpos( $line, 'NOOP ' )
			)
		),
		'No-op rerun must report seventeen records and Reading settings as current'
	);

	delete_option( 'show_on_front' );
	delete_option( 'page_on_front' );
	delete_option( 'page_for_posts' );
	$absent_options_plan = celina_content_plan_reading_options(
		$managed_content_manifest,
		celina_content_load_records()
	);
	managed_content_live_expect(
		'update' === $absent_options_plan['action'],
		'An entirely absent Reading tuple must be safely configurable'
	);
	$absent_options_apply = celina_content_apply_reading_options(
		$absent_options_plan
	);
	managed_content_live_expect(
		'updated' === $absent_options_apply['reason'],
		'An absent Reading tuple must be written and verified together'
	);

	wp_update_post(
		[
			'ID'           => $home['id'],
			'post_content' => 'Manual content that must be preserved',
		]
	);
	$manual_plan = managed_content_live_results( 'plan', 'brand-pages' );
	managed_content_live_expect( 1 === $manual_plan['code'], 'Manual edit must fail plan' );
	managed_content_live_expect(
		count(
			array_filter(
				$manual_plan['lines'],
				static fn( string $line ): bool => false !== strpos( $line, 'manual_content_conflict' )
			)
		) > 0,
		'Manual edit must report its stable conflict reason'
	);
	$manual_apply = managed_content_live_results( 'apply', 'brand-pages' );
	managed_content_live_expect( 1 === $manual_apply['code'], 'Manual conflict apply must fail' );
	managed_content_live_expect(
		'Manual content that must be preserved' === get_post( $home['id'] )->post_content,
		'Apply must not overwrite manually changed content'
	);

	$about = managed_content_live_find_record( 'page.about' );
	update_post_meta( $about['id'], '_celina_blueprint_version', 1 );
	$safe_plan = managed_content_live_results( 'plan-safe', 'brand-pages' );
	managed_content_live_expect(
		20 === $safe_plan['code'],
		'Safe plan must report an expected content conflict as partial'
	);
	$safe_apply = managed_content_live_results( 'apply-safe', 'brand-pages' );
	$about_after_safe_apply = managed_content_live_find_record( 'page.about' );
	managed_content_live_expect(
		20 === $safe_apply['code'],
		'Safe apply must report an expected content conflict as partial'
	);
	managed_content_live_expect(
		managed_content_live_find_manifest_entry( 'page.about' )['version'] === ( $about_after_safe_apply['blueprint_version'] ?? null ),
		'Safe apply must still apply an independent reviewed version update'
	);
	managed_content_live_expect(
		'Manual content that must be preserved' === get_post( $home['id'] )->post_content,
		'Safe apply must skip and preserve the conflicting record'
	);

	$home_pattern = $registry->get_registered( managed_content_live_find_manifest_entry( 'page.homepage' )['pattern'] );
	wp_update_post(
		[
			'ID'           => $home['id'],
			'post_content' => wp_slash( $home_pattern['content'] ),
		]
	);
	update_post_meta(
		$home['id'],
		'_celina_applied_content_hash',
		celina_content_hash( $home_pattern['content'] )
	);
	update_option( 'page_for_posts', 999999 );
	$option_plan = celina_content_plan_reading_options(
		$managed_content_manifest,
		celina_content_load_records()
	);
	managed_content_live_expect(
		'conflict' === $option_plan['action'] &&
		'reading_options_conflict' === $option_plan['reason'],
		'Mixed Reading settings must be a conflict'
	);
	managed_content_live_expect(
		999999 === (int) get_option( 'page_for_posts' ),
		'Reading plan must not mutate conflicting settings'
	);
} catch ( Throwable $error ) {
	$managed_content_failures[] = 'Unexpected exception: ' . $error->getMessage();
} finally {
	foreach ( array_unique( $managed_content_created_post_ids ) as $post_id ) {
		foreach ( get_posts( [ 'post_type' => 'page', 'post_status' => 'any', 'numberposts' => -1,
			'meta_key' => '_celina_redesign_snapshot_for', 'meta_value' => $post_id ] ) as $archive ) {
			wp_delete_post( $archive->ID, true );
		}
		wp_delete_post( $post_id, true );
	}

	foreach ( $managed_content_registered_patterns as $pattern ) {
		unregister_block_pattern( $pattern );
	}

	foreach ( $managed_content_original_categories as $category_slug => $original_category ) {
		if ( false !== $original_category ) {
			continue;
		}

		$created_category = get_term_by( 'slug', $category_slug, 'category' );
		if ( $created_category ) {
			wp_delete_term( $created_category->term_id, 'category' );
		}
	}

	foreach ( $managed_content_original_options as $option => $value ) {
		if ( $managed_content_option_missing === $value ) {
			delete_option( $option );
		} else {
			update_option( $option, $value );
		}
	}
}

if ( count( $managed_content_failures ) > 0 ) {
	foreach ( $managed_content_failures as $failure ) {
		WP_CLI::warning( $failure );
	}
	WP_CLI::error( 'Managed content live contract failed.' );
}

WP_CLI::success( 'Managed content live contract passed.' );
