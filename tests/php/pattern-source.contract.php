<?php

declare( strict_types=1 );

require_once dirname( __DIR__, 2 ) . '/workbench/content/manifest.php';

$pattern_source_failures = [];
$pattern_source_root     = dirname( __DIR__, 2 );
$delivered_groups        = [ 'brand-pages', 'offer-pages', 'people-contact-pages', 'news-resources', 'legal-pages' ];
$expected_sequences      = [
	'page.homepage' => [
		'celina/hero',
		'celina/intro-band',
		'celina/industry-cards',
		'celina/text-image-split',
		'celina/capability-cards',
		'celina/achievements-timeline',
		'celina/news-grid',
		'celina/text-image-split',
	],
	'page.about' => [
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
	'page.why-celina' => [
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
	'page.capabilities' => [
		'celina/hero',
		'celina/intro-band',
		'celina/capability-cards',
		'celina/text-image-split',
		'celina/faq',
		'celina/cta-band',
	],
	'page.markets' => [
		'celina/hero',
		'celina/intro-band',
		'celina/industry-cards',
		'celina/text-image-split',
		'celina/faq',
		'celina/cta-band',
	],
	'page.community' => [
		'celina/hero',
		'celina/intro-band',
		'celina/text-image-split',
		'celina/industry-cards',
		'celina/faq',
		'celina/cta-band',
	],
	'page.careers' => [
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
	'page.contact' => [
		'celina/hero',
		'celina/intro-band',
		'core/columns',
		'celina/facility-cards',
		'celina/faq',
		'celina/industry-cards',
	],
	'page.corporate-news' => [
		'celina/hero',
		'celina/news-grid',
		'celina/media-contact',
	],
	'page.corporate-resources' => [
		'celina/hero',
		'celina/intro-band',
		'celina/resource-list',
		'celina/resource-list',
		'celina/cta-band',
	],
	'post.thirty-years' => [
		'core/paragraph',
		'core/paragraph',
		'core/paragraph',
		'core/heading',
		'core/paragraph',
		'core/paragraph',
		'core/image',
		'core/paragraph',
		'core/paragraph',
		'core/heading',
		'core/paragraph',
		'core/paragraph',
		'core/paragraph',
		'core/image',
		'core/paragraph',
		'core/paragraph',
		'core/pullquote',
		'core/image',
		'core/paragraph',
		'core/heading',
		'core/paragraph',
		'core/paragraph',
		'core/paragraph',
		'core/image',
		'core/paragraph',
		'core/paragraph',
		'core/paragraph',
		'core/paragraph',
		'core/heading',
		'core/paragraph',
		'core/paragraph',
		'core/paragraph',
		'core/image',
		'core/paragraph',
		'core/paragraph',
		'core/paragraph',
		'core/paragraph',
		'core/heading',
		'core/paragraph',
		'core/paragraph',
		'core/paragraph',
		'core/paragraph',
		'core/paragraph',
		'core/heading',
		'core/paragraph',
		'core/paragraph',
		'core/paragraph',
		'core/image',
		'core/paragraph',
		'core/paragraph',
		'core/pullquote',
		'core/paragraph',
		'core/heading',
		'core/paragraph',
		'core/paragraph',
		'core/paragraph',
		'core/paragraph',
		'celina/media-contact',
	],
	'page.privacy-policy' => [
		'celina/hero',
		'core/group',
		'celina/cta-band',
	],
	'page.terms-and-conditions' => [
		'celina/hero',
		'core/group',
		'celina/cta-band',
	],
];
$representative_copy = [
	'page.homepage'            => [
		'Manufacturing Solutions Built on American Ingenuity.',
		'Capabilities That Strengthen Every Partnership',
	],
	'page.about'               => [
		'Built in Celina, Ohio. Trusted around the world.',
		'Milestones & Growth',
	],
	'page.why-celina'          => [
		'Built on Trust. Proven Through Experience.',
		'Experience the CELINA Difference.',
	],
	'page.capabilities'        => [ 'Core Capabilities', 'CELINA Capabilities FAQ' ],
	'page.markets'             => [ 'Industries We Serve', 'CELINA Industries FAQ' ],
	'page.community'           => [
		'Investing in the Communities That Support Us.',
		'Building Strong Communities Together.',
	],
	'page.careers'             => [ 'Build More Than Products. Build Your Future.', 'Why Choose CELINA Careers' ],
	'page.contact'             => [ 'Contact CELINA', 'Contact the Right Team Directly' ],
	'page.corporate-news'      => [ 'CELINA Corporate News', 'Latest CELINA Corporate News' ],
	'page.corporate-resources' => [ 'Corporate Resources', 'Featured Corporate Resources' ],
	'post.thirty-years'        => [ 'Thirty Years Built by People', 'Media Contact' ],
	'post.celina-turns-30'     => [
		'Thirty years ago, we started with a simple vision',
		'Thank You for Being Part of Our Story',
	],
	'post.ata-tent-training'   => [
		'ATA Tent Training: Why Education Has Become Part of CELINA\'s Mission',
		'Knowledge Creates Better Outcomes',
	],
	'post.one-celina-brand'    => [
		'From Celina Tent to One CELINA Brand',
		'Moving Forward as One CELINA Brand',
	],
	'post.recycled-rubble-foundation' => [
		'Every community has places that leave a lasting mark.',
		'A Foundation With Local History',
	],
	'page.privacy-policy'      => [ 'Privacy Policy', '16. Contact Information' ],
	'page.terms-and-conditions' => [ 'Terms &amp; Conditions', '18. Contact Information' ],
];
$contact_hubspot_block = '<!-- wp:celina/hubspot-form {"heading":"Request a Quote or Ask a Question","region":"na1","portalId":"portfolio-unconfigured","formId":"portfolio-unconfigured","fallbackText":"Complete the form below and a member of our team will respond as quickly as possible during normal business hours."} /-->';

function pattern_source_top_level_blocks( string $content ): array {
	preg_match_all(
		'/<!--\s*(\/?)wp:([a-z0-9-]+(?:\/[a-z0-9-]+)?)(?:\s+\{.*?\})?\s*(\/?)-->/s',
		$content,
		$tokens,
		PREG_SET_ORDER
	);

	$depth  = 0;
	$blocks = [];
	foreach ( $tokens as $token ) {
		$is_closing     = '/' === $token[1];
		$is_self_closing = '/' === $token[3];
		$name           = false === strpos( $token[2], '/' )
			? 'core/' . $token[2]
			: $token[2];

		if ( $is_closing ) {
			$depth = max( 0, $depth - 1 );
			continue;
		}

		if ( 0 === $depth ) {
			$blocks[] = $name;
		}
		if ( ! $is_self_closing ) {
			$depth++;
		}
	}

	return $blocks;
}

function pattern_source_header( string $source, string $name ): ?string {
	if (
		1 === preg_match(
			'/^\s*\*\s*' . preg_quote( $name, '/' ) . ':\s*(.+?)\s*$/mi',
			$source,
			$match
		)
	) {
		return trim( $match[1] );
	}

	return null;
}

$manifest = array_values(
	array_filter(
		celina_content_manifest(),
		static fn( array $entry ): bool => in_array(
			$entry['group'],
			$delivered_groups,
			true
		)
	)
);

$expected_pattern_keys = array_keys( $representative_copy );
$manifest_pattern_keys = array_column( $manifest, 'key' );
sort( $expected_pattern_keys );
sort( $manifest_pattern_keys );
if ( $expected_pattern_keys !== $manifest_pattern_keys ) {
	$pattern_source_failures[] = 'managed pattern identities must match the approved client page set';
}

foreach ( $manifest as $entry ) {
	$entry['pattern'] = str_replace( 'celina/redesigned-', 'celina/', $entry['pattern'] );
	$file = $pattern_source_root . '/wordpress/themes/celina-child/patterns/' .
		str_replace( '/', '-', substr( $entry['pattern'], strlen( 'celina/' ) ) ) . '.php';

	if ( ! is_readable( $file ) ) {
		$pattern_source_failures[] = "{$entry['key']}: missing readable canonical pattern {$file}";
		continue;
	}

	$source = file_get_contents( $file );
	if ( false === $source ) {
		$pattern_source_failures[] = "{$entry['key']}: unable to read canonical pattern";
		continue;
	}

	if ( $entry['pattern'] !== pattern_source_header( $source, 'Slug' ) ) {
		$pattern_source_failures[] = "{$entry['key']}: pattern slug header mismatch";
	}
	if ( $entry['type'] !== pattern_source_header( $source, 'Post Types' ) ) {
		$pattern_source_failures[] = "{$entry['key']}: post type header mismatch";
	}
	if (
		isset( $expected_sequences[ $entry['key'] ] ) &&
		$expected_sequences[ $entry['key'] ] !== pattern_source_top_level_blocks( $source )
	) {
		$pattern_source_failures[] = "{$entry['key']}: top-level block sequence mismatch";
	}

	foreach ( $representative_copy[ $entry['key'] ] as $marker ) {
		if ( false === strpos( $source, $marker ) ) {
			$pattern_source_failures[] = "{$entry['key']}: missing approved copy marker {$marker}";
		}
	}

	if ( 'page.careers' === $entry['key'] ) {
		$paycor_block = '<!-- wp:celina/paycor-jobs {"heading":"Join Our Team","clientId":"portfolio-unconfigured","fallbackText":"Open positions are also available directly on Paycor.","jobsLinkText":"View open positions on Paycor","anchor":"open-positions"} /-->';

		if ( false === strpos( $source, $paycor_block ) ) {
			$pattern_source_failures[] = 'page.careers: missing configured Paycor jobs block';
		}
		if ( false !== strpos( $source, '<!-- wp:celina/bamboohr-jobs' ) ) {
			$pattern_source_failures[] = 'page.careers: contains active BambooHR jobs block';
		}
	}

	if ( 'page.capabilities' === $entry['key'] ) {
		if ( 2 !== substr_count( $source, 'class=\"celina-capability-cards__related-link\"' ) ) {
			$pattern_source_failures[] = 'page.capabilities: related destinations must render as two separate link cards';
		}
		if ( false !== strpos( $source, 'See our Industries page →</a> For certifications' ) ) {
			$pattern_source_failures[] = 'page.capabilities: related destinations must not remain embedded in one sentence';
		}
		if ( 6 !== ( $entry['version'] ?? 0 ) ) {
			$pattern_source_failures[] = 'page.capabilities: editable media requires blueprint version 6';
		}
	}

	if ( 'page.corporate-news' === $entry['key'] ) {
		if ( false === strpos( $source, '"navigationCategories":[]' ) ) {
			$pattern_source_failures[] = 'page.corporate-news: missing portable empty category-navigation configuration';
		}
		if ( 1 === preg_match( '/"navigationCategories"\s*:\s*\[(?!\s*\])/', $source ) ) {
			$pattern_source_failures[] = 'page.corporate-news: hard-codes environment-specific category IDs';
		}
	}

	if ( 0 === strpos( $entry['key'], 'post.' ) && 'post.thirty-years' !== $entry['key'] ) {
		if ( false === strpos( $source, 'article-placeholder.svg' ) ) {
			$pattern_source_failures[] = "{$entry['key']}: missing explicit local placeholder for provisional article media";
		}
		if ( false === strpos( $source, '<!-- wp:celina/media-contact' ) ) {
			$pattern_source_failures[] = "{$entry['key']}: missing editable media contact block";
		}
	}

	if ( 'page.contact' === $entry['key'] ) {
		if ( 5 !== ( $entry['version'] ?? 0 ) ) {
			$pattern_source_failures[] = 'page.contact: editable media requires blueprint version 5';
		}
		if ( false === strpos( $source, $contact_hubspot_block ) ) {
			$pattern_source_failures[] = 'page.contact: verified HubSpot configuration mismatch';
		}
	}

	$forbidden = [
		'/placedog\.net/i'                         => 'placedog host',
		'/excluded-media\.example\.invalid/i'    => 'test WP Engine host',
		'/<\/?(?:script|style)\b/i'                => 'script/style tag',
		'/\sstyle\s*=/i'                          => 'inline style',
		'/href\s*=\s*(["\'])#\1/i'               => 'dead fragment link',
		'/\b(?:Headline Goes Here|Placeholder Date|Real date:)\b/i' => 'unresolved placeholder copy',
		'/replace (?:this|all existing) content/i'  => 'document instruction',
	];
	foreach ( $forbidden as $pattern => $label ) {
		if ( 1 === preg_match( $pattern, $source ) ) {
			$pattern_source_failures[] = "{$entry['key']}: forbidden {$label}";
		}
	}
}

$article_pattern_file = $pattern_source_root . '/wordpress/themes/celina-child/patterns/article.php';
$article_pattern_source = file_get_contents( $article_pattern_file );

if ( false === $article_pattern_source ) {
	$pattern_source_failures[] = 'article: unable to read canonical pattern';
} else {
	if ( 'celina/article' !== pattern_source_header( $article_pattern_source, 'Slug' ) ) {
		$pattern_source_failures[] = 'article: pattern slug header mismatch';
	}
	if ( 'post' !== pattern_source_header( $article_pattern_source, 'Post Types' ) ) {
		$pattern_source_failures[] = 'article: post type header mismatch';
	}
	if ( [ 'celina/article-layout' ] !== pattern_source_top_level_blocks( $article_pattern_source ) ) {
		$pattern_source_failures[] = 'article: top-level block sequence mismatch';
	}

	preg_match_all(
		'/<!--\s*wp:([a-z0-9-]+(?:\/[a-z0-9-]+)?)(?:\s+\{.*?\})?\s*(?:\/)?-->/s',
		$article_pattern_source,
		$article_opening_tokens
	);
	$article_opening_blocks = array_map(
		static fn( string $name ): string => false === strpos( $name, '/' )
			? 'core/' . $name
			: $name,
		$article_opening_tokens[1]
	);
	$expected_article_blocks = [
		'celina/article-layout',
		'core/paragraph',
		'core/heading',
		'core/paragraph',
		'core/image',
		'core/pullquote',
		'core/paragraph',
		'celina/media-contact',
	];

	if ( $expected_article_blocks !== $article_opening_blocks ) {
		$pattern_source_failures[] = 'article: starter block sequence mismatch';
	}
	if (
		false === strpos(
			$article_pattern_source,
			'<div class="wp-block-celina-article-layout celina-article-layout">'
		) ||
		false === strpos( $article_pattern_source, '<!-- /wp:celina/article-layout -->' )
	) {
		$pattern_source_failures[] = 'article: saved layout wrapper mismatch';
	}
}

if ( count( $pattern_source_failures ) > 0 ) {
	fwrite( STDERR, implode( "\n", $pattern_source_failures ) . "\n" );
	exit( 1 );
}

echo "Pattern source contract passed\n";
