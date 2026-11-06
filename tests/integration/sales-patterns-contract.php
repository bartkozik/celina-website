<?php
$registry = WP_Block_Patterns_Registry::get_instance();
$expected = [
	'facts', 'cta', 'careers', 'contact-heading', 'industry-handoff', 'intro', 'history',
	'page-homepage', 'page-industries', 'page-capabilities', 'page-why-celina',
	'page-about', 'page-contact', 'page-community', 'page-careers',
	'page-corporate-resources', 'page-corporate-news',
];
$failures = [];
$scan = static function ( array $blocks, string $name ) use ( &$scan, &$failures ): void {
	foreach ( $blocks as $block ) {
		if ( ( ! $block['blockName'] && trim( $block['innerHTML'] ) ) || 'core/html' === $block['blockName'] ) {
			$failures[] = "$name contains noneditable markup";
		}
		if ( $block['blockName'] && ! WP_Block_Type_Registry::get_instance()->is_registered( $block['blockName'] ) ) {
			$failures[] = "$name uses unregistered block: {$block['blockName']}";
		}
		$scan( $block['innerBlocks'], $name );
	}
};
foreach ( $expected as $slug ) {
	$name = 'celina/sales-' . $slug;
	$pattern = $registry->get_registered( $name );
	if ( ! $pattern ) {
		$failures[] = "$name missing";
		continue;
	}
	$blocks = parse_blocks( $pattern['content'] );
	$scan( $blocks, $name );
	if ( str_starts_with( $slug, 'page-' ) ) {
		if ( celina_page_should_show_title( $pattern['content'] ) ) {
			$failures[] = "$name would render a duplicate page title";
		}
		if ( celina_sales_review_content( $pattern['content'], 'page.homepage' ) !== $pattern['content'] ) {
			$failures[] = "$name copy is overwritten by review overlay";
		}
		if ( ! str_contains( $blocks[0]['attrs']['className'] ?? '', 'celina-sales-page' ) ) {
			$failures[] = "$name missing native composition marker";
		}
		if ( ! $registry->is_registered( 'celina/' . $slug ) ) {
			$failures[] = "$name original pattern missing";
		}
	}
	if ( str_contains( $pattern['content'], '<strong>58-acre' ) || str_contains( $pattern['content'], '<strong>300,000' ) ) {
		$failures[] = "$name emphasizes acreage";
	}
}
$native_contact = $registry->get_registered( 'celina/sales-page-contact' );
$legacy_contact = $registry->get_registered( 'celina/page-contact' );
if ( celina_find_content_block( parse_blocks( $native_contact['content'] ), 'celina/hubspot-form' ) !== celina_find_content_block( parse_blocks( $legacy_contact['content'] ), 'celina/hubspot-form' ) ) {
	$failures[] = 'Contact form configuration changed';
}
foreach ( $failures as $failure ) {
	echo "FAIL: $failure\n";
}
if ( $failures ) {
	exit( 1 );
}
echo "PASS: all 17 sales patterns are registered, editable block trees; original page patterns preserved.\n";
