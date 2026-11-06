<?php
$failures = [];
$expect   = static function ( bool $condition, string $message ) use ( &$failures ): void {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$suffixes = [
	'facts', 'cta', 'careers', 'contact-heading', 'industry-handoff', 'intro', 'history',
	'page-homepage', 'page-industries', 'page-capabilities', 'page-why-celina',
	'page-about', 'page-contact', 'page-community', 'page-careers',
	'page-corporate-resources', 'page-corporate-news',
];
$expected_titles = [
	'achievement' => 'Achievement',
	'achievements-timeline' => 'Achievements Timeline',
	'article-layout' => 'CELINA Article Layout',
	'capability-card' => 'Capability Card',
	'capability-cards' => 'Capability Cards',
	'cta-band' => 'CTA Band',
	'facility-card' => 'Facility Card',
	'facility-cards' => 'Facility Cards',
	'faq' => 'FAQ',
	'faq-item' => 'FAQ Item',
	'hero' => 'Hero',
	'hubspot-form' => 'HubSpot Form',
	'industry-card' => 'Industry Card',
	'industry-cards' => 'Industry Cards',
	'intro-band' => 'Intro / Description Band',
	'media-contact' => 'Media Contact',
	'news-grid' => 'News Grid',
	'paycor-jobs' => 'Paycor Jobs',
	'resource-item' => 'Resource Item',
	'resource-list' => 'Resource List',
	'text-image-split' => 'Text + Image Split',
];

$pattern_categories = WP_Block_Pattern_Categories_Registry::get_instance();
$expect( 'CELINA REDESIGNED' === ( $pattern_categories->get_registered( 'celina-redesigned' )['label'] ?? null ), 'REDESIGNED pattern category missing or mislabeled' );
$expect( 'CELINA Sales' === ( $pattern_categories->get_registered( 'celina-sales' )['label'] ?? null ), 'original Sales pattern category changed' );
$block_categories = apply_filters( 'block_categories_all', [] );
$block_category_labels = array_column( $block_categories, 'title', 'slug' );
$expect( 'CELINA REDESIGNED' === ( $block_category_labels['celina-redesigned'] ?? null ), 'REDESIGNED block category missing or mislabeled' );
$expect( 'CELINA' === ( $block_category_labels['celina'] ?? null ), 'legacy block category changed' );

$block_registry = WP_Block_Type_Registry::get_instance();
$registered_celina_names = [];
foreach ( $block_registry->get_all_registered() as $name => $block ) {
	if ( str_starts_with( $name, 'celina/' ) ) {
		$registered_celina_names[] = $name;
	}
}
$expected_names = array_map( static fn ( string $slug ): string => 'celina/' . $slug, array_keys( $expected_titles ) );
$expected_names[] = 'celina/bamboohr-jobs';
sort( $registered_celina_names );
sort( $expected_names );
$expect( $expected_names === $registered_celina_names, 'CELINA block names changed' );
foreach ( $expected_titles as $slug => $title ) {
	$block = $block_registry->get_registered( 'celina/' . $slug );
	$expect( $block instanceof WP_Block_Type, "celina/$slug missing" );
	if ( $block instanceof WP_Block_Type ) {
		$expect( $title . ' — REDESIGNED' === $block->title, "celina/$slug title mismatch" );
		$expect( 'celina-redesigned' === $block->category, "celina/$slug category mismatch" );
	}
}
$legacy = $block_registry->get_registered( 'celina/bamboohr-jobs' );
$expect( $legacy instanceof WP_Block_Type, 'legacy BambooHR block missing' );
if ( $legacy instanceof WP_Block_Type ) {
	$expect( 'Legacy BambooHR Jobs' === $legacy->title, 'legacy BambooHR title changed' );
	$expect( 'celina' === $legacy->category, 'legacy BambooHR category changed' );
	$expect( false === ( $legacy->supports['inserter'] ?? null ), 'legacy BambooHR must remain hidden from inserter' );
}

$pattern_registry = WP_Block_Patterns_Registry::get_instance();
$scan_blocks = static function ( array $blocks, string $name ) use ( &$scan_blocks, &$failures, $block_registry ): void {
	foreach ( $blocks as $block ) {
		if ( ( ! $block['blockName'] && trim( $block['innerHTML'] ) ) || 'core/html' === $block['blockName'] ) {
			$failures[] = "$name contains noneditable markup";
		}
		if ( $block['blockName'] && ! $block_registry->is_registered( $block['blockName'] ) ) {
			$failures[] = "$name uses unregistered block: {$block['blockName']}";
		}
		if ( $block['blockName'] && ! str_starts_with( $block['blockName'], 'core/' ) && ! str_starts_with( $block['blockName'], 'celina/' ) ) {
			$failures[] = "$name uses a nonnative block: {$block['blockName']}";
		}
		$scan_blocks( $block['innerBlocks'], $name );
	}
};
$registered_aliases = [];
foreach ( $pattern_registry->get_all_registered() as $pattern ) {
	if ( str_starts_with( $pattern['name'], 'celina/redesigned-' ) ) {
		$registered_aliases[] = $pattern['name'];
	}
}
$expected_aliases = array_map( static fn ( string $suffix ): string => 'celina/redesigned-' . $suffix, $suffixes );
sort( $registered_aliases );
sort( $expected_aliases );
$expect( $expected_aliases === $registered_aliases, 'REDESIGNED pattern IDs changed' );
foreach ( $suffixes as $suffix ) {
	$source_name = 'celina/sales-' . $suffix;
	$alias_name  = 'celina/redesigned-' . $suffix;
	$source      = $pattern_registry->get_registered( $source_name );
	$alias       = $pattern_registry->get_registered( $alias_name );
	$expect( is_array( $source ), "$source_name missing" );
	$expect( is_array( $alias ), "$alias_name missing" );
	if ( ! is_array( $source ) || ! is_array( $alias ) ) {
		continue;
	}
	$expect( $source['content'] === $alias['content'], "$alias_name content differs from Sales source" );
	$expect( [ 'celina-sales' ] === ( $source['categories'] ?? null ), "$source_name categories changed" );
	$expect( [ 'celina-redesigned' ] === ( $alias['categories'] ?? null ), "$alias_name category mismatch" );
	$expect( str_replace( 'CELINA Sales', 'CELINA REDESIGNED', $source['title'] ) === $alias['title'], "$alias_name title mismatch" );
	$expect( ( $source['description'] ?? null ) === ( $alias['description'] ?? null ), "$alias_name description changed" );
	$expect( ( $source['postTypes'] ?? null ) === ( $alias['postTypes'] ?? null ), "$alias_name post types changed" );
	$scan_blocks( parse_blocks( $alias['content'] ), $alias_name );
}

foreach ( $failures as $failure ) {
	echo "FAIL: $failure\n";
}
if ( $failures ) {
	exit( 1 );
}
echo 'PASS: 21 REDESIGNED blocks, 17 exact-content pattern aliases, legacy BambooHR, and Sales originals.' . "\n";
