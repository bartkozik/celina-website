<?php

require_once '/var/www/html/celina-workbench/content/manifest.php';

$failures = [];
$patterns = WP_Block_Patterns_Registry::get_instance();

$expected_sequences = [
	'celina/page-homepage' => [
		'celina/hero',
		'celina/intro-band',
		'celina/industry-cards',
		'celina/text-image-split',
		'celina/capability-cards',
		'celina/achievements-timeline',
		'celina/news-grid',
		'celina/text-image-split',
	],
	'celina/page-about' => [
		'celina/hero',
		'celina/intro-band',
		'celina/text-image-split',
		'celina/achievements-timeline',
		'core/group',
		'celina/capability-cards',
		'core/gallery',
		'celina/faq',
		'celina/cta-band',
	],
	'celina/page-why-celina' => [
		'celina/hero',
		'celina/intro-band',
		'celina/text-image-split',
		'celina/capability-cards',
		'celina/achievements-timeline',
		'celina/text-image-split',
		'celina/text-image-split',
		'celina/faq',
		'celina/cta-band',
	],
	'celina/page-capabilities' => [
		'celina/hero',
		'celina/intro-band',
		'celina/capability-cards',
		'celina/text-image-split',
		'celina/faq',
		'celina/cta-band',
	],
	'celina/page-industries' => [
		'celina/hero',
		'celina/intro-band',
		'celina/industry-cards',
		'celina/text-image-split',
		'celina/faq',
		'celina/cta-band',
	],
	'celina/page-community' => [
		'celina/hero',
		'celina/intro-band',
		'celina/text-image-split',
		'celina/industry-cards',
		'celina/faq',
		'celina/cta-band',
	],
	'celina/page-careers' => [
		'celina/hero',
		'celina/text-image-split',
		'celina/capability-cards',
		'celina/text-image-split',
		'celina/capability-cards',
		'core/heading',
		'core/list',
		'celina/paycor-jobs',
		'celina/faq',
		'celina/cta-band',
	],
	'celina/page-contact' => [
		'celina/hero',
		'celina/intro-band',
		'core/columns',
		'celina/facility-cards',
		'celina/faq',
		'celina/industry-cards',
	],
	'celina/page-corporate-news' => [
		'celina/hero',
		'celina/news-grid',
		'celina/media-contact',
	],
	'celina/page-corporate-resources' => [
		'celina/hero',
		'celina/intro-band',
		'celina/resource-list',
		'celina/resource-list',
		'celina/cta-band',
	],
	'celina/post-thirty-years' => [
		'core/paragraph', 'core/paragraph', 'core/paragraph', 'core/heading',
		'core/paragraph', 'core/paragraph', 'core/image', 'core/paragraph',
		'core/paragraph', 'core/heading', 'core/paragraph', 'core/paragraph',
		'core/paragraph', 'core/image', 'core/paragraph', 'core/paragraph',
		'core/pullquote', 'core/image', 'core/paragraph', 'core/heading',
		'core/paragraph', 'core/paragraph', 'core/paragraph', 'core/image',
		'core/paragraph', 'core/paragraph', 'core/paragraph', 'core/paragraph',
		'core/heading', 'core/paragraph', 'core/paragraph', 'core/paragraph',
		'core/image', 'core/paragraph', 'core/paragraph', 'core/paragraph',
		'core/paragraph', 'core/heading', 'core/paragraph', 'core/paragraph',
		'core/paragraph', 'core/paragraph', 'core/paragraph', 'core/heading',
		'core/paragraph', 'core/paragraph', 'core/paragraph', 'core/image',
		'core/paragraph', 'core/paragraph', 'core/pullquote', 'core/paragraph',
		'core/heading', 'core/paragraph', 'core/paragraph', 'core/paragraph',
		'core/paragraph', 'celina/media-contact',
	],
	'celina/page-privacy-policy' => [
		'celina/hero', 'core/group', 'celina/cta-band',
	],
	'celina/page-terms-and-conditions' => [
		'celina/hero', 'core/group', 'celina/cta-band',
	],
];

$copy_markers = [
	'celina/page-homepage'     => 'Manufacturing Solutions Built on American Ingenuity.',
	'celina/page-about'        => 'Milestones &amp; Growth',
	'celina/page-why-celina'   => 'Experience the CELINA Difference.',
	'celina/page-capabilities' => 'The Capabilities Behind Every CELINA Solution.',
	'celina/page-industries'   => 'One Company. Four Specialized Industries.',
	'celina/page-community'    => 'Investing in the Communities That Support Us.',
	'celina/page-careers'      => 'Build More Than Products. Build Your Future.',
	'celina/page-contact'      => 'Request a Quote or Ask a Question',
	'celina/page-corporate-news' => 'News, Announcements',
	'celina/page-corporate-resources' => 'Featured Corporate Resources',
	'celina/post-thirty-years' => 'Thank You for Being Part of Our Story',
	'celina/post-celina-turns-30' => 'Thank You for Being Part of Our Story',
	'celina/post-ata-tent-training' => 'Knowledge Creates Better Outcomes',
	'celina/post-one-celina-brand' => 'Moving Forward as One CELINA Brand',
	'celina/post-recycled-rubble-foundation' => 'A Foundation With Local History',
	'celina/page-privacy-policy' => '16. Contact Information',
	'celina/page-terms-and-conditions' => '18. Contact Information',
];
$contact_hubspot_block = '<!-- wp:celina/hubspot-form {"heading":"Request a Quote or Ask a Question","region":"na1","portalId":"portfolio-unconfigured","formId":"portfolio-unconfigured","fallbackText":"Complete the form below and a member of our team will respond as quickly as possible during normal business hours."} /-->';
$manifest = array_values(
	array_filter(
		celina_content_manifest(),
		static fn( array $entry ): bool => in_array(
			$entry['group'],
			[ 'brand-pages', 'offer-pages', 'people-contact-pages', 'news-resources', 'legal-pages' ],
			true
		)
	)
);

foreach ( $manifest as $entry ) {
	$pattern_slug = str_replace( 'celina/redesigned-', 'celina/', $entry['pattern'] );
	$registered   = $patterns->get_registered( $pattern_slug );

	if ( ! is_array( $registered ) ) {
		$failures[] = 'missing canonical pattern ' . $pattern_slug;
		continue;
	}

	if ( [ $entry['type'] ] !== ( $registered['postTypes'] ?? [] ) ) {
		$failures[] = 'canonical pattern post type mismatch ' . $pattern_slug;
	}

	$content  = (string) ( $registered['content'] ?? '' );
	$blocks   = array_values(
		array_filter(
			parse_blocks( $content ),
			static fn( array $block ): bool => null !== ( $block['blockName'] ?? null )
		)
	);
	$sequence = array_map(
		static fn( array $block ): string => (string) $block['blockName'],
		$blocks
	);

	if (
		isset( $expected_sequences[ $pattern_slug ] ) &&
		$expected_sequences[ $pattern_slug ] !== $sequence
	) {
		$failures[] = 'canonical pattern sequence mismatch ' . $pattern_slug;
	}
	if ( false === strpos( $content, $copy_markers[ $pattern_slug ] ) ) {
		$failures[] = 'canonical pattern approved copy mismatch ' . $pattern_slug;
	}
	if (
		'celina/page-contact' === $pattern_slug &&
		false === strpos( $content, $contact_hubspot_block )
	) {
		$failures[] = 'canonical Contact pattern verified HubSpot configuration mismatch';
	}
	if (
		1 === preg_match(
			'/placedog\.net|excluded-media\.example\.invalid|<\/?(?:script|style)\b|href\s*=\s*(["\'])#\1/i',
			$content
		)
	) {
		$failures[] = 'canonical pattern forbidden content ' . $pattern_slug;
	}
	$tags = new WP_HTML_Tag_Processor( $content );
	while ( $tags->next_tag() ) {
		$style = $tags->get_attribute( 'style' );
		if ( null === $style ) { continue; }
		$hero_photo = $tags->has_class( 'celina-hero' ) && in_array( $style, array_map( static fn( array $media ): string => 'background-image:url(' . $media['url'] . ')', celina_media_attachment_map() ), true );
		$cover_overlay = $tags->has_class( 'wp-block-cover__background' ) && 'background:linear-gradient(270deg, rgb(61 69 67 / 94%) 0%, rgb(61 69 67 / 72%) 55%, rgb(61 69 67 / 40%) 100%)' === $style;
		if ( ! $hero_photo && ! $cover_overlay ) { $failures[] = 'canonical pattern unexpected inline style ' . $pattern_slug; }
	}
	if ( 'celina/page-careers' === $pattern_slug ) {
		$paycor_block = '<!-- wp:celina/paycor-jobs {"heading":"Join Our Team","clientId":"portfolio-unconfigured","fallbackText":"Open positions are also available directly on Paycor.","jobsLinkText":"View open positions on Paycor","anchor":"open-positions"} /-->';

		if ( false === strpos( $content, $paycor_block ) ) {
			$failures[] = 'canonical Careers pattern missing configured Paycor jobs block';
		}
		if ( false !== strpos( $content, '<!-- wp:celina/bamboohr-jobs' ) ) {
			$failures[] = 'canonical Careers pattern contains active BambooHR jobs block';
		}
	}
}

$article_layout_block = WP_Block_Type_Registry::get_instance()->get_registered(
	'celina/article-layout'
);
$expected_article_allowed_blocks = [
	'core/paragraph',
	'core/heading',
	'core/image',
	'core/gallery',
	'core/list',
	'core/pullquote',
	'core/quote',
	'core/table',
	'core/buttons',
	'core/separator',
	'core/embed',
	'core/group',
	'core/columns',
	'core/media-text',
	'celina/media-contact',
];

if ( ! $article_layout_block instanceof WP_Block_Type ) {
	$failures[] = 'missing registered block celina/article-layout';
} else {
	if ( 'CELINA Article Layout — REDESIGNED' !== $article_layout_block->title ) {
		$failures[] = 'article layout title mismatch';
	}
	if ( [ 'article', 'news', 'story' ] !== $article_layout_block->keywords ) {
		$failures[] = 'article layout keywords mismatch';
	}
	if ( $expected_article_allowed_blocks !== $article_layout_block->allowed_blocks ) {
		$failures[] = 'article layout allowed blocks mismatch';
	}
	if ( null !== $article_layout_block->render_callback ) {
		$failures[] = 'article layout must remain static';
	}
}

$article_pattern = $patterns->get_registered( 'celina/article' );

if ( ! is_array( $article_pattern ) ) {
	$failures[] = 'missing canonical pattern celina/article';
} else {
	if ( [ 'post' ] !== ( $article_pattern['postTypes'] ?? [] ) ) {
		$failures[] = 'canonical pattern post type mismatch celina/article';
	}

	$article_blocks = array_values(
		array_filter(
			parse_blocks( (string) ( $article_pattern['content'] ?? '' ) ),
			static fn( array $block ): bool => null !== ( $block['blockName'] ?? null )
		)
	);

	if (
		1 !== count( $article_blocks ) ||
		'celina/article-layout' !== ( $article_blocks[0]['blockName'] ?? null )
	) {
		$failures[] = 'canonical article layout root mismatch';
	} else {
		$article_inner_sequence = array_map(
			static fn( array $block ): string => (string) ( $block['blockName'] ?? '' ),
			$article_blocks[0]['innerBlocks'] ?? []
		);
		$expected_article_inner_sequence = [
			'core/paragraph',
			'core/heading',
			'core/paragraph',
			'core/image',
			'core/pullquote',
			'core/paragraph',
			'celina/media-contact',
		];

		if ( $expected_article_inner_sequence !== $article_inner_sequence ) {
			$failures[] = 'canonical article starter sequence mismatch';
		}
		if (
			false === strpos(
				(string) ( $article_blocks[0]['innerHTML'] ?? '' ),
				'<div class="wp-block-celina-article-layout celina-article-layout">'
			)
		) {
			$failures[] = 'canonical article saved wrapper mismatch';
		}
	}
}

if ( count( $failures ) > 0 ) {
	WP_CLI::error_multi_line( $failures );
	WP_CLI::halt( 1 );
}

WP_CLI::success( 'Canonical patterns contract passed.' );

require __DIR__ . "/sales-patterns-contract.php";
