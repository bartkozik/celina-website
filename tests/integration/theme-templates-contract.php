<?php

$failures = [];
$theme    = wp_get_theme();
$root     = get_stylesheet_directory();

require_once '/var/www/html/celina-workbench/content/manifest.php';

if ( 'celina-child' !== $theme->get_stylesheet() ) {
	$failures[] = 'child theme inactive';
}

foreach ( [
	'inc/article-meta.php',
	'inc/template-renderer.php',
	'assets/css/content-templates.css',
	'front-page.php',
	'page.php',
	'home.php',
	'category.php',
	'single-post.php',
	'404.php',
	'template-parts/content-page.php',
	'template-parts/content-card.php',
	'template-parts/content-single.php',
] as $relative_path ) {
	if ( ! is_readable( $root . '/' . $relative_path ) ) {
		$failures[] = 'missing theme file ' . $relative_path;
	}
}

$route_markers = [
	'front-page.php'  => "celina_template_open( 'front-page' )",
	'page.php'        => "celina_template_open( 'page' )",
	'home.php'        => "celina_template_open( 'home' )",
	'category.php'    => "celina_template_open( 'category' )",
	'single-post.php' => "celina_template_open( 'single-post' )",
	'404.php'         => "celina_template_open( '404' )",
];

foreach ( $route_markers as $relative_path => $marker ) {
	$source = is_readable( $root . '/' . $relative_path )
		? file_get_contents( $root . '/' . $relative_path )
		: false;
	if ( false === $source || false === strpos( $source, $marker ) ) {
		$failures[] = 'incorrect route marker ' . $relative_path;
	}
}

$not_found_source = is_readable( $root . '/404.php' )
	? file_get_contents( $root . '/404.php' )
	: false;

if (
	false === $not_found_source ||
	false === strpos( $not_found_source, 'get_search_form()' )
) {
	$failures[] = '404 search form call missing';
}

if (
	false === $not_found_source ||
	false === strpos( $not_found_source, "esc_url( home_url( '/' ) )" )
) {
	$failures[] = '404 escaped home URL missing';
}

$front_page_source = file_get_contents( $root . '/front-page.php' );

if (
	false === $front_page_source ||
	false === strpos( $front_page_source, 'celina_render_latest_posts_front_page()' )
) {
	$failures[] = 'front page latest-posts delegation missing';
}

$patterns          = WP_Block_Patterns_Registry::get_instance();
$pattern_contracts = [
	'celina/front-page' => [
		'post_types'      => [ 'page' ],
		'content_markers' => [
			'<!-- wp:celina/hero ',
			'<!-- wp:celina/news-grid {"heading":"Latest news","count":3} /-->',
		],
	],
	'celina/content-page' => [
		'post_types'      => [ 'page' ],
		'content_markers' => [
			'<!-- wp:celina/hero ',
			'<!-- wp:paragraph -->',
			'<!-- wp:celina/faq -->',
			'<!-- wp:celina/faq-item ',
		],
	],
	'celina/news-landing' => [
		'post_types'      => [ 'page' ],
		'content_markers' => [
			'<!-- wp:celina/hero ',
			'<!-- wp:celina/news-grid {"heading":"Latest news","count":6} /-->',
		],
	],
	'celina/article' => [
		'post_types'      => [ 'post' ],
		'content_markers' => [
			'<!-- wp:celina/article-layout -->',
			'<!-- wp:heading -->',
			'<!-- wp:pullquote -->',
			'<!-- wp:image {"sizeSlug":"large"} -->',
			'<figcaption class="wp-element-caption">Image caption.</figcaption>',
			'<p>Article conclusion.',
			'<!-- wp:celina/media-contact -->',
		],
	],
];

foreach ( $pattern_contracts as $pattern_slug => $contract ) {
	if ( ! $patterns->is_registered( $pattern_slug ) ) {
		$failures[] = 'missing pattern ' . $pattern_slug;
		continue;
	}

	$pattern = $patterns->get_registered( $pattern_slug );

	if ( $contract['post_types'] !== ( $pattern['postTypes'] ?? [] ) ) {
		$failures[] = 'pattern post types mismatch ' . $pattern_slug;
	}

	if ( ! in_array( 'celina', $pattern['categories'] ?? [], true ) ) {
		$failures[] = 'pattern category mismatch ' . $pattern_slug;
	}

	$content = (string) ( $pattern['content'] ?? '' );

	foreach ( $contract['content_markers'] as $marker ) {
		if ( false === strpos( $content, $marker ) ) {
			$failures[] = 'pattern content mismatch ' . $pattern_slug . ': ' . $marker;
		}
	}
}

$canonical_pattern_sequences = [
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
$canonical_copy_markers = [
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
$canonical_manifest = array_values(
	array_filter(
		celina_content_manifest(),
		static fn( array $entry ): bool => in_array(
			$entry['group'],
			[ 'brand-pages', 'offer-pages', 'people-contact-pages', 'news-resources', 'legal-pages' ],
			true
		)
	)
);

foreach ( $canonical_manifest as $entry ) {
	$pattern_slug = str_replace( 'celina/redesigned-', 'celina/', $entry['pattern'] );
	$registered   = $patterns->get_registered( $pattern_slug );

	if ( ! is_array( $registered ) ) {
		$failures[] = 'missing canonical pattern ' . $pattern_slug;
		continue;
	}

	if ( [ $entry['type'] ] !== ( $registered['postTypes'] ?? [] ) ) {
		$failures[] = 'canonical pattern post type mismatch ' . $pattern_slug;
	}

	$content = (string) ( $registered['content'] ?? '' );
	$blocks  = array_values(
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
		isset( $canonical_pattern_sequences[ $pattern_slug ] ) &&
		$canonical_pattern_sequences[ $pattern_slug ] !== $sequence
	) {
		$failures[] = 'canonical pattern sequence mismatch ' . $pattern_slug;
	}
	if ( false === strpos( $content, $canonical_copy_markers[ $pattern_slug ] ) ) {
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
			'/placedog\.net|excluded-media\.example\.invalid|<\/?(?:script|style)\b|\sstyle\s*=|href\s*=\s*(["\'])#\1/i',
			$content
		)
	) {
		$failures[] = 'canonical pattern forbidden content ' . $pattern_slug;
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

$article_pattern = $patterns->get_registered( 'celina/article' );

if ( is_array( $article_pattern ) ) {
	$article_content = (string) ( $article_pattern['content'] ?? '' );
	$article_image_relative_path = 'assets/images/article-placeholder.svg';
	$article_image_path          = $root . '/' . $article_image_relative_path;
	$article_image_url           = esc_url( get_theme_file_uri( $article_image_relative_path ) );

	foreach ( [ '<!-- wp:celina/hero' ] as $excluded_marker ) {
		if ( false !== strpos( $article_content, $excluded_marker ) ) {
			$failures[] = 'article pattern owns single-post framing: ' . $excluded_marker;
		}
	}

	if ( false !== strpos( $article_content, 'data:image' ) ) {
		$failures[] = 'article pattern uses KSES-unsafe data image';
	}

	if ( ! is_readable( $article_image_path ) ) {
		$failures[] = 'article pattern image asset missing';
	}

	if ( false === strpos( $article_content, 'src="' . $article_image_url . '"' ) ) {
		$failures[] = 'article pattern theme image URL mismatch';
	}

	$sanitized_article_content = wp_kses_post( $article_content );
	$sanitized_article_blocks  = parse_blocks( $sanitized_article_content );
	$sanitized_layout_blocks   = array_values(
		array_filter(
			$sanitized_article_blocks,
			static function ( array $block ): bool {
				return 'celina/article-layout' === ( $block['blockName'] ?? null );
			}
		)
	);
	$sanitized_layout_inner_blocks = 1 === count( $sanitized_layout_blocks )
		? ( $sanitized_layout_blocks[0]['innerBlocks'] ?? [] )
		: [];
	$sanitized_image_blocks    = array_values(
		array_filter(
			$sanitized_layout_inner_blocks,
			static function ( array $block ): bool {
				return 'core/image' === ( $block['blockName'] ?? null );
			}
		)
	);

	if ( 1 !== count( $sanitized_layout_blocks ) ) {
		$failures[] = 'article pattern sanitized article-layout block mismatch';
	}

	if ( 1 !== count( $sanitized_image_blocks ) ) {
		$failures[] = 'article pattern sanitized core/image block mismatch';
	} else {
		$sanitized_image_markup = (string) ( $sanitized_image_blocks[0]['innerHTML'] ?? '' );

		if (
			false === strpos( $sanitized_image_markup, '<img' ) ||
			false === strpos( $sanitized_image_markup, 'src="' . $article_image_url . '"' )
		) {
			$failures[] = 'article pattern image does not survive KSES';
		}
	}
}

$categories       = WP_Block_Pattern_Categories_Registry::get_instance();
$celina_category  = $categories->get_registered( 'celina' );

if ( ! $categories->is_registered( 'celina' ) ) {
	$failures[] = 'missing CELINA pattern category';
} elseif ( 'CELINA' !== ( $celina_category['label'] ?? '' ) ) {
	$failures[] = 'CELINA pattern category label mismatch';
}

foreach ( [
	'celina_is_owned_template_route',
	'celina_is_latest_posts_front_page',
	'celina_resolve_latest_posts_front_page_template',
	'celina_render_latest_posts_front_page',
	'celina_first_meaningful_content_block',
	'celina_page_should_show_title',
	'celina_filter_empty_hero_heading',
	'celina_resolve_managed_posts_page',
	'celina_render_managed_posts_page',
	'celina_find_content_block',
	'celina_managed_news_index_attributes',
	'celina_prepare_managed_news_query',
	'celina_enqueue_managed_news_archive_styles',
	'celina_article_job_title_field',
	'celina_article_save_job_title',
	'celina_article_reading_minutes',
	'celina_article_tokenize_readable_content',
	'celina_article_mark_heading_blocks',
	'celina_article_render_heading_block',
	'celina_article_primary_category',
	'celina_article_featured_image',
	'celina_wordpress_sample_post_id',
	'celina_exclude_wordpress_sample_post',
	'celina_article_adjacent_post',
	'celina_render_post_archive',
	'celina_template_open',
	'celina_template_close',
	'celina_template_sidebar_layout',
	'celina_template_body_class',
] as $function ) {
	if ( ! function_exists( $function ) ) {
		$failures[] = 'missing function ' . $function;
	}
}

if ( false === has_action( 'wp_enqueue_scripts', 'celina_enqueue_managed_news_archive_styles' ) ) {
	$failures[] = 'managed category archive style loader hook missing';
}

$article_profile_hooks = [
	'show_user_profile'         => 'celina_article_job_title_field',
	'edit_user_profile'         => 'celina_article_job_title_field',
	'personal_options_update'   => 'celina_article_save_job_title',
	'edit_user_profile_update'  => 'celina_article_save_job_title',
];

foreach ( $article_profile_hooks as $hook => $callback ) {
	if ( false === has_action( $hook, $callback ) ) {
		$failures[] = 'article job title hook missing ' . $hook;
	}
}

if ( false !== has_filter( 'the_content', 'celina_article_normalize_body_headings' ) ) {
	$failures[] = 'article body heading normalization is globally hooked';
}

if ( false !== has_filter( 'render_block_core/heading', 'celina_article_render_heading_block' ) ) {
	$failures[] = 'article heading block normalization is persistently hooked';
}

foreach ( [
	'celina_article_html_tag_at',
	'celina_article_special_markup_at',
	'celina_article_protected_element_names',
	'celina_article_script_element_end',
	'celina_article_protected_element_end',
	'celina_article_transform_body_headings',
] as $removed_article_html_parser ) {
	if ( function_exists( $removed_article_html_parser ) ) {
		$failures[] = 'obsolete article HTML parser remains ' . $removed_article_html_parser;
	}
}

$single_article_template_path   = $root . '/template-parts/content-single.php';
$single_article_template_source = is_readable( $single_article_template_path )
	? file_get_contents( $single_article_template_path )
	: false;
if (
	! is_string( $single_article_template_source ) ||
	preg_match( '/\bthe_content\s*\(/', $single_article_template_source ) ||
	false === strpos( $single_article_template_source, 'get_the_content( null, false, $article )' ) ||
	1 !== substr_count( $single_article_template_source, "apply_filters( 'the_content'" ) ||
	false !== strpos( $single_article_template_source, 'celina_article_transform_body_headings' )
) {
	$failures[] = 'article canonical body render boundary mismatch';
}

$heading_filter_add_position = is_string( $single_article_template_source )
	? strpos( $single_article_template_source, "add_filter( 'render_block_core/heading'" )
	: false;
$content_pipeline_position = is_string( $single_article_template_source )
	? strpos( $single_article_template_source, "apply_filters( 'the_content'" )
	: false;
$heading_filter_finally_position = is_string( $single_article_template_source )
	? strpos( $single_article_template_source, 'finally', (int) $content_pipeline_position )
	: false;
$heading_filter_remove_position = is_string( $single_article_template_source )
	? strpos( $single_article_template_source, "remove_filter( 'render_block_core/heading'" )
	: false;
if (
	! is_string( $single_article_template_source ) ||
	false === $heading_filter_add_position ||
	false === $content_pipeline_position ||
	false === $heading_filter_finally_position ||
	false === $heading_filter_remove_position ||
	$heading_filter_add_position > $content_pipeline_position ||
	$content_pipeline_position > $heading_filter_finally_position ||
	$heading_filter_finally_position > $heading_filter_remove_position ||
	false === strpos( $single_article_template_source, 'celina_article_render_heading_block' ) ||
	false === strpos( $single_article_template_source, 'celina_article_mark_heading_blocks' ) ||
	false === strpos( $single_article_template_source, 'wp_generate_uuid4()' ) ||
	false === strpos( $single_article_template_source, 'parse_blocks( $article_body )' ) ||
	false === strpos( $single_article_template_source, 'serialize_blocks(' ) ||
	false === strpos( $single_article_template_source, 'celinaArticleHeadingScope' ) ||
	false !== strpos( $single_article_template_source, '$article_heading_scope_depth' ) ||
	false !== strpos( $single_article_template_source, "add_filter( 'the_content'" ) ||
	false !== strpos( $single_article_template_source, "remove_filter( 'the_content'" ) ||
	false === strpos( $single_article_template_source, '! is_admin()' ) ||
	false === strpos( $single_article_template_source, '! is_feed()' ) ||
	false === strpos( $single_article_template_source, "is_singular( 'post' )" ) ||
	false === strpos( $single_article_template_source, 'is_main_query()' ) ||
	false === strpos( $single_article_template_source, 'in_the_loop()' ) ||
	false === strpos( $single_article_template_source, 'get_queried_object_id()' ) ||
	false === strpos( $single_article_template_source, 'get_the_ID()' )
) {
	$failures[] = 'article scoped heading block filter boundary mismatch';
}

if ( function_exists( 'celina_article_render_heading_block' ) ) {
	$level_one_block = [
		'blockName' => 'core/heading',
		'attrs'     => [ 'level' => 1 ],
	];
	$level_one_content = "\n<h1 class=\"wp-block-heading\" data-label=\"A > B\">Inner <em data-byte=\"yes\">bytes</em></h1>\n";
	$level_one_expected = "\n<h2 class=\"wp-block-heading\" data-label=\"A > B\">Inner <em data-byte=\"yes\">bytes</em></h2>\n";
	if (
		$level_one_expected !==
		celina_article_render_heading_block( $level_one_content, $level_one_block )
	) {
		$failures[] = 'article core level-one heading block normalization mismatch';
	}

	$uppercase_level_one = '<H1 data-byte="uppercase">Uppercase tag bytes</H1>';
	if (
		'<h2 data-byte="uppercase">Uppercase tag bytes</h2>' !==
		celina_article_render_heading_block( $uppercase_level_one, $level_one_block )
	) {
		$failures[] = 'article uppercase core level-one heading normalization mismatch';
	}

	foreach ( [
		'<h1 data-byte="first">First root</h1><h1 data-byte="second">Second root</h1>',
		'<h1 data-label="unterminated>Malformed attribute</h1>',
		'<h1 data-byte="outer">Outer <h1 data-byte="nested">Nested</h1></h1>',
		'<h1 data-byte="outer">Outer <h1/> compact nested</h1>',
		'<h1 data=<bad>>Malformed unquoted attribute</h1>',
	] as $invalid_level_one_wrapper ) {
		if (
			$invalid_level_one_wrapper !==
			celina_article_render_heading_block( $invalid_level_one_wrapper, $level_one_block )
		) {
			$failures[] = 'article malformed or multi-root heading block changed';
		}
	}

	foreach ( [ 2, 3 ] as $unchanged_heading_level ) {
		$unchanged_heading = '<h' . $unchanged_heading_level . ' data-byte="unchanged">'
			. 'Level ' . $unchanged_heading_level
			. '</h' . $unchanged_heading_level . '>';
		if (
			$unchanged_heading !==
			celina_article_render_heading_block(
				$unchanged_heading,
				[
					'blockName' => 'core/heading',
					'attrs'     => [ 'level' => $unchanged_heading_level ],
				]
			)
		) {
			$failures[] = 'article non-H1 core heading block changed';
		}
	}

	$foreign_heading = '<h1 data-byte="foreign">Foreign block output</h1>';
	if (
		$foreign_heading !==
		celina_article_render_heading_block(
			$foreign_heading,
			[
				'blockName' => 'celina/foreign-heading',
				'attrs'     => [ 'level' => 1 ],
			]
		)
	) {
		$failures[] = 'article foreign block output changed';
	}
}

if ( function_exists( 'celina_article_mark_heading_blocks' ) ) {
	$contract_marker = 'contract-private-marker';
	$root_heading    = celina_article_mark_heading_blocks(
		parse_blocks(
			'<!-- wp:heading {"level":1} -->'
			. '<h1 data-contract="root-heading">Root heading</h1>'
			. '<!-- /wp:heading -->'
		),
		$contract_marker
	);
	if ( $contract_marker !== ( $root_heading[0]['attrs']['celinaArticleHeadingScope'] ?? null ) ) {
		$failures[] = 'article document-root heading marker mismatch';
	}

	$allowed_article_containers = [
		'celina/article-layout',
		'core/group',
		'core/columns',
		'core/column',
		'core/cover',
		'core/media-text',
		'core/quote',
		'core/list',
		'core/list-item',
		'core/details',
	];
	foreach ( $allowed_article_containers as $allowed_container ) {
		$delimiter_name = 0 === strpos( $allowed_container, 'core/' )
			? substr( $allowed_container, 5 )
			: $allowed_container;
		$container_fixture = '<!-- wp:' . $delimiter_name . ' --><div>'
			. '<!-- wp:heading {"level":1} -->'
			. '<h1 data-contract="allowed-container">Allowed nested heading</h1>'
			. '<!-- /wp:heading -->'
			. '</div><!-- /wp:' . $delimiter_name . ' -->';
		$marked_container = celina_article_mark_heading_blocks(
			parse_blocks( $container_fixture ),
			$contract_marker
		);
		$marked_heading = $marked_container[0]['innerBlocks'][0] ?? null;

		if (
			$allowed_container !== ( $marked_container[0]['blockName'] ?? null ) ||
			$contract_marker !== ( $marked_heading['attrs']['celinaArticleHeadingScope'] ?? null )
		) {
			$failures[] = 'article allowed container marker mismatch ' . $allowed_container;
		}
	}

	$opaque_article_containers = [
		'core/html' => [
			'open'  => '<script type="text/template"><template>',
			'close' => '</template></script>',
		],
		'core/shortcode' => [
			'open'  => '[opaque-contract]',
			'close' => '[/opaque-contract]',
		],
		'core/code' => [
			'open'  => '<pre class="wp-block-code"><code>',
			'close' => '</code></pre>',
		],
		'core/preformatted' => [
			'open'  => '<pre class="wp-block-preformatted">',
			'close' => '</pre>',
		],
		'celina/opaque-contract' => [
			'open'  => '<section data-contract="foreign-container">',
			'close' => '</section>',
		],
	];
	$opaque_render_filter = static function ( string $block_content, array $block ) use ( $contract_marker ): string {
		return $contract_marker === ( $block['attrs']['celinaArticleHeadingScope'] ?? null )
			? celina_article_render_heading_block( $block_content, $block )
			: $block_content;
	};
	add_filter( 'render_block_core/heading', $opaque_render_filter, 10, 2 );
	try {
		foreach ( $opaque_article_containers as $opaque_container => $opaque_wrapper ) {
			$delimiter_name = 0 === strpos( $opaque_container, 'core/' )
				? substr( $opaque_container, 5 )
				: $opaque_container;
			$opaque_fixture = '<!-- wp:' . $delimiter_name . ' -->'
				. $opaque_wrapper['open']
				. '<!-- wp:heading {"level":1} -->'
				. '<h1 data-contract="opaque-heading">Opaque nested heading</h1>'
				. '<!-- /wp:heading -->'
				. $opaque_wrapper['close']
				. '<!-- /wp:' . $delimiter_name . ' -->';
			$marked_opaque = celina_article_mark_heading_blocks(
				parse_blocks( $opaque_fixture ),
				$contract_marker
			);
			$serialized_opaque = serialize_blocks( $marked_opaque );
			$rendered_opaque   = do_blocks( $serialized_opaque );
			$opaque_heading    = $marked_opaque[0]['innerBlocks'][0] ?? null;

			if (
				$opaque_container !== ( $marked_opaque[0]['blockName'] ?? null ) ||
				isset( $opaque_heading['attrs']['celinaArticleHeadingScope'] ) ||
				false !== strpos( $serialized_opaque, $contract_marker ) ||
				1 !== preg_match( '/<h1\b[^>]*data-contract="opaque-heading"/i', $rendered_opaque ) ||
				1 === preg_match( '/<h2\b[^>]*data-contract="opaque-heading"/i', $rendered_opaque ) ||
				false !== strpos( $rendered_opaque, $contract_marker )
			) {
				$failures[] = 'article opaque container marker leak ' . $opaque_container;
			}
		}
	} finally {
		remove_filter( 'render_block_core/heading', $opaque_render_filter, 10 );
	}
}

if (
	function_exists( 'celina_article_job_title_field' ) &&
	function_exists( 'celina_article_save_job_title' )
) {
	$profile_original_user_id = get_current_user_id();
	$profile_original_post    = $_POST;
	$profile_target_id        = wp_insert_user(
		[
			'user_login'   => uniqid( 'celina_profile_target_', false ),
			'user_pass'    => wp_generate_password( 24 ),
			'display_name' => 'Profile Target',
			'role'         => 'subscriber',
		]
	);
	$profile_limited_id       = wp_insert_user(
		[
			'user_login'   => uniqid( 'celina_profile_limited_', false ),
			'user_pass'    => wp_generate_password( 24 ),
			'display_name' => 'Profile Limited',
			'role'         => 'subscriber',
		]
	);
	$administrator_ids       = get_users(
		[
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ids',
		]
	);

	if (
		is_wp_error( $profile_target_id ) ||
		is_wp_error( $profile_limited_id ) ||
		empty( $administrator_ids )
	) {
		$failures[] = 'article job title user fixtures unavailable';
	} else {
		$profile_target_id  = (int) $profile_target_id;
		$profile_limited_id = (int) $profile_limited_id;
		$profile_target     = get_userdata( $profile_target_id );

		try {
			wp_set_current_user( (int) $administrator_ids[0] );
			update_user_meta(
				$profile_target_id,
				'celina_job_title',
				'Press <script>alert(1)</script> & Relations'
			);

			ob_start();
			celina_article_job_title_field( $profile_target );
			$profile_html = (string) ob_get_clean();

			if (
				false === strpos( $profile_html, 'name="celina_job_title"' ) ||
				false === strpos( $profile_html, 'celina_article_job_title_nonce' ) ||
				false === strpos( $profile_html, 'Press &lt;script&gt;alert(1)&lt;/script&gt; &amp; Relations' ) ||
				false !== stripos( $profile_html, '<script' )
			) {
				$failures[] = 'article job title authorized profile output mismatch';
			}

			$_POST = [
				'celina_article_job_title_nonce' => wp_create_nonce(
					'celina_article_job_title_' . $profile_target_id
				),
				'celina_job_title' => '  Senior <b>Editor</b>  ',
			];
			celina_article_save_job_title( $profile_target_id );

			if ( 'Senior Editor' !== get_user_meta( $profile_target_id, 'celina_job_title', true ) ) {
				$failures[] = 'article job title sanitization mismatch';
			}

			$_POST = [
				'celina_article_job_title_nonce' => 'invalid',
				'celina_job_title'               => 'Invalid nonce value',
			];
			celina_article_save_job_title( $profile_target_id );

			if ( 'Senior Editor' !== get_user_meta( $profile_target_id, 'celina_job_title', true ) ) {
				$failures[] = 'article job title invalid nonce changed metadata';
			}

			$_POST = [
				'celina_article_job_title_nonce' => wp_create_nonce(
					'celina_article_job_title_' . $profile_target_id
				),
				'celina_job_title' => '   ',
			];
			celina_article_save_job_title( $profile_target_id );

			if ( metadata_exists( 'user', $profile_target_id, 'celina_job_title' ) ) {
				$failures[] = 'article blank job title was not removed';
			}

			update_user_meta( $profile_target_id, 'celina_job_title', 'Protected title' );
			wp_set_current_user( $profile_limited_id );

			ob_start();
			celina_article_job_title_field( $profile_target );
			$limited_profile_html = (string) ob_get_clean();

			$_POST = [
				'celina_article_job_title_nonce' => wp_create_nonce(
					'celina_article_job_title_' . $profile_target_id
				),
				'celina_job_title' => 'Unauthorized title',
			];
			celina_article_save_job_title( $profile_target_id );

			if (
				'' !== $limited_profile_html ||
				'Protected title' !== get_user_meta( $profile_target_id, 'celina_job_title', true )
			) {
				$failures[] = 'article job title capability boundary mismatch';
			}
		} finally {
			$_POST = $profile_original_post;
			wp_set_current_user( $profile_original_user_id );

			if ( ! function_exists( 'wp_delete_user' ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
			}
			wp_delete_user( $profile_target_id );
			wp_delete_user( $profile_limited_id );
		}
	}
}

if ( function_exists( 'celina_article_reading_minutes' ) ) {
	if ( 1 !== celina_article_reading_minutes( '' ) ) {
		$failures[] = 'article empty reading time mismatch';
	}

	$article_words = implode( ' ', array_fill( 0, 201, 'word' ) );
	if ( 2 !== celina_article_reading_minutes( $article_words ) ) {
		$failures[] = 'article 201-word reading time mismatch';
	}

	$article_markup = '<!-- wp:paragraph {"ignored":"metadata words"} -->'
		. '<p>' . implode( ' ', array_fill( 0, 198, 'Zażółć' ) )
		. ' <strong>entity&nbsp;word</strong></p><!-- /wp:paragraph -->';
	if ( 1 !== celina_article_reading_minutes( $article_markup ) ) {
		$failures[] = 'article serialized markup reading time mismatch';
	}

	$article_comment = '<!-- Contract metadata > '
		. implode( ' ', array_fill( 0, 201, 'comment-word' ) )
		. ' -->Visible';
	if ( 1 !== celina_article_reading_minutes( $article_comment ) ) {
		$failures[] = 'article generic HTML comment reading time mismatch';
	}

	$article_shortcode_calls = 0;
	add_shortcode(
		'celina_contract_throw',
		static function () use ( &$article_shortcode_calls ): string {
			++$article_shortcode_calls;
			throw new RuntimeException( 'Reading time executed a shortcode callback.' );
		}
	);

	try {
		$shortcode_minutes = celina_article_reading_minutes(
			'[celina_contract_throw]Visible enclosed text[/celina_contract_throw]'
		);

		if ( 1 !== $shortcode_minutes || 0 !== $article_shortcode_calls ) {
			$failures[] = 'article reading time shortcode side effect mismatch';
		}
	} catch ( Throwable $error ) {
		$failures[] = 'article reading time executed shortcode callback';
	} finally {
		remove_shortcode( 'celina_contract_throw' );
	}

	$article_dotted_shortcode_calls = 0;
	$article_shortcode_metadata     = implode(
		' ',
		array_fill( 0, 201, 'attribute-word' )
	);
	add_shortcode(
		'celina.contract',
		static function () use ( &$article_dotted_shortcode_calls ): string {
			++$article_dotted_shortcode_calls;
			throw new RuntimeException( 'Reading time executed a dotted shortcode callback.' );
		}
	);

	try {
		$dotted_shortcode_minutes = celina_article_reading_minutes(
			'[celina.contract metadata="' . $article_shortcode_metadata . '"]'
			. 'Visible inner text'
			. '[/celina.contract]'
		);

		if ( 1 !== $dotted_shortcode_minutes || 0 !== $article_dotted_shortcode_calls ) {
			$failures[] = 'article dotted shortcode reading time mismatch';
		}

		$unregistered_shortcode_minutes = celina_article_reading_minutes(
			'[unregistered.contract metadata="' . $article_shortcode_metadata . '"]'
			. 'Visible unregistered inner text'
			. '[/unregistered.contract]'
		);

		if ( 1 !== $unregistered_shortcode_minutes ) {
			$failures[] = 'article unregistered shortcode reading time mismatch';
		}

		$nested_shortcode_minutes = celina_article_reading_minutes(
			'[celina.contract][unregistered.contract]'
			. implode( ' ', array_fill( 0, 199, 'authored-word' ) )
			. '[/unregistered.contract][/celina.contract]'
		);

		if ( 1 !== $nested_shortcode_minutes || 0 !== $article_dotted_shortcode_calls ) {
			$failures[] = 'article nested shortcode reading time mismatch';
		}

		$deep_shortcode_content = implode( ' ', array_fill( 0, 199, 'authored-word' ) );
		$deep_shortcode_names   = [];
		for ( $depth = 0; $depth < 12; ++$depth ) {
			$deep_shortcode_name    = 'celina.contract.' . $depth;
			$deep_shortcode_names[] = $deep_shortcode_name;
			add_shortcode(
				$deep_shortcode_name,
				static function () use ( &$article_dotted_shortcode_calls ): string {
					++$article_dotted_shortcode_calls;
					throw new RuntimeException( 'Reading time executed a deep shortcode callback.' );
				}
			);
			$deep_shortcode_content = '[' . $deep_shortcode_name . ']'
				. $deep_shortcode_content
				. '[/' . $deep_shortcode_name . ']';
		}

		$deep_shortcode_minutes = celina_article_reading_minutes( $deep_shortcode_content );
		if ( 1 !== $deep_shortcode_minutes || 0 !== $article_dotted_shortcode_calls ) {
			$failures[] = 'article deeply nested shortcode reading time mismatch';
		}
		foreach ( $deep_shortcode_names as $deep_shortcode_name ) {
			remove_shortcode( $deep_shortcode_name );
		}

		$escaped_shortcode_minutes = celina_article_reading_minutes(
			'[[celina.contract metadata="' . $article_shortcode_metadata . '"]]'
		);

		if ( 2 !== $escaped_shortcode_minutes || 0 !== $article_dotted_shortcode_calls ) {
			$failures[] = 'article escaped shortcode preservation mismatch';
		}

		$shortcode_token_matrix = '[open.contract label="value ] remains quoted"]'
			. 'Visible inner text'
			. '[/open.contract]'
			. '[self.contract label=\'another ] value\' /]'
			. ' More authored text';
		$tokenized_matrix = celina_article_tokenize_readable_content( $shortcode_token_matrix );
		if (
			false === strpos( $tokenized_matrix, 'Visible inner text' ) ||
			false === strpos( $tokenized_matrix, 'More authored text' ) ||
			false !== strpos( $tokenized_matrix, 'open.contract' ) ||
			false !== strpos( $tokenized_matrix, 'self.contract' )
		) {
			$failures[] = 'article shortcode token matrix mismatch';
		}

		$escaped_token = 'Before [[literal.contract label="value ] remains"]] after';
		if (
			'Before [literal.contract label="value ] remains"] after' !==
			celina_article_tokenize_readable_content( $escaped_token )
		) {
			$failures[] = 'article escaped shortcode token visibility mismatch';
		}

		$escaped_enclosing_shortcode = 'Before [[literal.contract label="value ] remains"]inner[/literal.contract]] after';
		if (
			'Before [literal.contract label="value ] remains"]inner[/literal.contract] after' !==
			celina_article_tokenize_readable_content( $escaped_enclosing_shortcode )
		) {
			$failures[] = 'article escaped enclosing shortcode visibility mismatch';
		}

		$malformed_shortcode = 'Before [broken.contract label="unterminated ] after';
		if ( $malformed_shortcode !== celina_article_tokenize_readable_content( $malformed_shortcode ) ) {
			$failures[] = 'article malformed shortcode visibility mismatch';
		}

		$malformed_closing_metadata = implode( ' ', array_fill( 0, 201, 'closing-metadata-word' ) );
		$malformed_closing_shortcode = 'Before [/closing.contract metadata="'
			. $malformed_closing_metadata
			. '"] [valid.contract]authored[/valid.contract] after';
		$malformed_closing_tokenized = celina_article_tokenize_readable_content( $malformed_closing_shortcode );
		if (
			false === strpos( $malformed_closing_tokenized, '[/closing.contract metadata="' ) ||
			false !== strpos( $malformed_closing_tokenized, '[valid.contract]' ) ||
			2 !== celina_article_reading_minutes( $malformed_closing_shortcode )
		) {
			$failures[] = 'article malformed closing shortcode visibility mismatch';
		}

		$malformed_self_closing_end = 'Before [/closing.contract /] after';
		if ( $malformed_self_closing_end !== celina_article_tokenize_readable_content( $malformed_self_closing_end ) ) {
			$failures[] = 'article self-closing end shortcode visibility mismatch';
		}

		if ( 'Before   after' !== celina_article_tokenize_readable_content( 'Before [/closing.contract ] after' ) ) {
			$failures[] = 'article valid closing shortcode control mismatch';
		}
	} catch ( Throwable $error ) {
		$failures[] = 'article reading time executed dotted shortcode callback';
	} finally {
		remove_shortcode( 'celina.contract' );
	}

	$reading_reflection = new ReflectionFunction( 'celina_article_reading_minutes' );
	$reading_lines      = file( $reading_reflection->getFileName() );
	$reading_source     = is_array( $reading_lines )
		? implode(
			'',
			array_slice(
				$reading_lines,
				$reading_reflection->getStartLine() - 1,
				$reading_reflection->getEndLine() - $reading_reflection->getStartLine() + 1
			)
		)
		: '';

	if ( preg_match( '/\b(?:do_blocks|render_block|do_shortcode)\s*\(/', $reading_source ) ) {
		$failures[] = 'article reading time executes serialized content';
	}

	if ( function_exists( 'celina_article_tokenize_readable_content' ) ) {
		$tokenizer_reflection = new ReflectionFunction( 'celina_article_tokenize_readable_content' );
		$tokenizer_lines      = file( $tokenizer_reflection->getFileName() );
		$tokenizer_source     = is_array( $tokenizer_lines )
			? implode(
				'',
				array_slice(
					$tokenizer_lines,
					$tokenizer_reflection->getStartLine() - 1,
					$tokenizer_reflection->getEndLine() - $tokenizer_reflection->getStartLine() + 1
				)
			)
			: '';

		if (
			false === strpos( $tokenizer_source, '$cursor' ) ||
			false === strpos( $tokenizer_source, 'while' ) ||
			preg_match( '/get_shortcode_regex|preg_replace(?:_callback)?/', $tokenizer_source )
		) {
			$failures[] = 'article reading tokenizer is not monotonic single-pass';
		}

		$large_shortcode_content = str_repeat( '[outer.contract]', 300 )
			. implode( ' ', array_fill( 0, 199, 'authored-word' ) )
			. str_repeat( '[/outer.contract]', 300 );
		if ( 1 !== celina_article_reading_minutes( $large_shortcode_content ) ) {
			$failures[] = 'article large adversarial shortcode reading time mismatch';
		}
	}
}

if (
	function_exists( 'celina_article_primary_category' ) &&
	function_exists( 'celina_article_featured_image' )
) {
	$article_term_suffix = strtolower( wp_generate_password( 8, false, false ) );
	$article_term_one    = wp_insert_term(
		'Article Contract One ' . $article_term_suffix,
		'category',
		[ 'slug' => 'article-contract-one-' . $article_term_suffix ]
	);
	$article_term_two    = wp_insert_term(
		'Article Contract Two ' . $article_term_suffix,
		'category',
		[ 'slug' => 'article-contract-two-' . $article_term_suffix ]
	);
	$article_post_id     = 0;
	$article_attachment_id = 0;

	if ( is_wp_error( $article_term_one ) || is_wp_error( $article_term_two ) ) {
		$failures[] = 'article category term fixtures unavailable';
	} else {
		$article_term_ids = [
			(int) $article_term_one['term_id'],
			(int) $article_term_two['term_id'],
		];
		$article_post_id  = wp_insert_post(
			[
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_title'    => 'Article helper contract',
				'post_content'  => 'Article helper content.',
				'post_category' => array_merge(
					[ (int) get_option( 'default_category' ) ],
					$article_term_ids
				),
			]
		);

		if ( is_wp_error( $article_post_id ) || 0 === $article_post_id ) {
			$failures[] = 'article helper post fixture unavailable';
			$article_post_id = 0;
		} else {
			$article_post_id = (int) $article_post_id;
			$article_post    = get_post( $article_post_id );
			$posts_page_id   = (int) get_option( 'page_for_posts' );
			$posts_page      = get_post( $posts_page_id );

			if ( ! $article_post instanceof WP_Post || ! $posts_page instanceof WP_Post ) {
				$failures[] = 'article helper managed configuration fixtures unavailable';
			} else {
				$original_posts_content = $posts_page->post_content;
				$original_blueprint_key = get_post_meta(
					$posts_page_id,
					'_celina_blueprint_key',
					true
				);
				$preferred_term_id      = max( $article_term_ids );
				$fallback_term_id       = min( $article_term_ids );
				$managed_article_config = '<!-- wp:celina/news-grid '
					. wp_json_encode(
						[
							'layout'                 => 'news-index',
							'category'               => 0,
							'navigationCategories'   => [
								$preferred_term_id,
								$fallback_term_id,
							],
						],
						JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
					)
					. ' /-->';

				try {
					wp_update_post(
						[
							'ID'           => $posts_page_id,
							'post_content' => $managed_article_config,
						]
					);
					update_post_meta(
						$posts_page_id,
						'_celina_blueprint_key',
						'page.corporate-news'
					);

					$managed_primary = celina_article_primary_category( $article_post );
					if (
						! $managed_primary instanceof WP_Term ||
						$preferred_term_id !== (int) $managed_primary->term_id
					) {
						$failures[] = 'article managed primary category mismatch';
					}

					delete_post_meta( $posts_page_id, '_celina_blueprint_key' );
					$fallback_primary = celina_article_primary_category( $article_post );
					if (
						! $fallback_primary instanceof WP_Term ||
						$fallback_term_id !== (int) $fallback_primary->term_id
					) {
						$failures[] = 'article fallback primary category mismatch';
					}

					wp_set_post_categories(
						$article_post_id,
						[ (int) get_option( 'default_category' ) ]
					);
					if ( null !== celina_article_primary_category( $article_post ) ) {
						$failures[] = 'article default-only category fallback mismatch';
					}

					$placeholder_markup = celina_article_featured_image( $article_post );
					$placeholder_url    = esc_url(
						get_theme_file_uri( 'assets/images/article-placeholder.svg' )
					);

					if (
						false === strpos( $placeholder_markup, '<figure' ) ||
						false === strpos( $placeholder_markup, 'src="' . $placeholder_url . '"' ) ||
						false === strpos( $placeholder_markup, 'alt=""' ) ||
						false !== strpos( $placeholder_markup, 'placehold.co' )
					) {
						$failures[] = 'article featured image placeholder mismatch';
					}

					$article_attachment_id = wp_insert_attachment(
						[
							'post_title'     => 'Article contract image',
							'post_status'    => 'inherit',
							'post_mime_type' => 'image/jpeg',
							'guid'           => home_url( '/wp-content/uploads/article-contract.jpg' ),
						]
					);
					$article_attachment_id = is_wp_error( $article_attachment_id )
						? 0
						: (int) $article_attachment_id;

					if ( 0 === $article_attachment_id ) {
						$failures[] = 'article featured image attachment fixture unavailable';
					} else {
						update_post_meta( $article_post_id, '_thumbnail_id', $article_attachment_id );
						$thumbnail_filter = static function ( string $html, int $post_id ): string {
							if ( 0 === $post_id ) {
								return $html;
							}

							return '<img src="https://example.test/uploads/article-contract.jpg" '
								. 'alt="Contract image"><script>alert(1)</script>';
						};
						add_filter( 'post_thumbnail_html', $thumbnail_filter, 99, 5 );

						try {
							$featured_markup = celina_article_featured_image( $article_post );
						} finally {
							remove_filter( 'post_thumbnail_html', $thumbnail_filter, 99 );
						}

						if (
							false === strpos( $featured_markup, '<figure' ) ||
							false === strpos(
								$featured_markup,
								'src="https://example.test/uploads/article-contract.jpg"'
							) ||
							false !== stripos( $featured_markup, '<script' ) ||
							false !== strpos( $featured_markup, $placeholder_url )
						) {
							$failures[] = 'article real featured image mismatch';
						}
					}
				} finally {
					wp_update_post(
						[
							'ID'           => $posts_page_id,
							'post_content' => $original_posts_content,
						]
					);

					if ( '' === $original_blueprint_key ) {
						delete_post_meta( $posts_page_id, '_celina_blueprint_key' );
					} else {
						update_post_meta(
							$posts_page_id,
							'_celina_blueprint_key',
							$original_blueprint_key
						);
					}
				}

				$deleted_post = clone $article_post;
				wp_delete_post( $article_post_id, true );
				$article_post_id = 0;

				if ( null !== celina_article_primary_category( $deleted_post ) ) {
					$failures[] = 'article deleted post category fallback mismatch';
				}
			}
		}

		if ( $article_attachment_id > 0 ) {
			wp_delete_attachment( $article_attachment_id, true );
		}
		if ( $article_post_id > 0 ) {
			wp_delete_post( $article_post_id, true );
		}
		foreach ( $article_term_ids as $article_term_id ) {
			wp_delete_term( $article_term_id, 'category' );
		}
	}
}

$hero_markup = static function ( string $heading ): string {
	$attributes = wp_json_encode(
		[ 'heading' => $heading ],
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);

	return '<!-- wp:celina/hero ' . $attributes . ' -->'
		. '<section class="wp-block-celina-hero celina-hero celina-hero--standard">'
		. '<div class="celina-hero__inner">'
		. '<h1 class="celina-hero__heading">' . $heading . '</h1>'
		. '</div></section>'
		. '<!-- /wp:celina/hero -->';
};

if ( function_exists( 'celina_page_should_show_title' ) ) {
	$page_title_cases = [
		'empty content' => [
			'content'  => '',
			'expected' => true,
		],
		'whitespace and comment before Hero with heading' => [
			'content'  => " \n<!-- editorial note -->\n" . $hero_markup( 'Visible Hero' ),
			'expected' => false,
		],
		'Hero with empty visible heading' => [
			'content'  => $hero_markup( '<strong> </strong>' ),
			'expected' => true,
		],
		'Hero with named non-breaking space entity' => [
			'content'  => $hero_markup( '&nbsp;' ),
			'expected' => true,
		],
		'Hero with numeric non-breaking space entity' => [
			'content'  => $hero_markup( '&#160;' ),
			'expected' => true,
		],
		'Hero with zero-width and BOM entities' => [
			'content'  => $hero_markup( '&#8203;&#65279;' ),
			'expected' => true,
		],
		'Hero with visible entity and text' => [
			'content'  => $hero_markup( 'R&amp;D' ),
			'expected' => false,
		],
		'non-empty freeform before Hero' => [
			'content'  => 'Lead text' . $hero_markup( 'Visible Hero' ),
			'expected' => true,
		],
		'paragraph before Hero' => [
			'content'  => '<!-- wp:paragraph --><p>Lead text</p><!-- /wp:paragraph -->' . $hero_markup( 'Visible Hero' ),
			'expected' => true,
		],
	];

	foreach ( $page_title_cases as $label => $case ) {
		$actual = celina_page_should_show_title( $case['content'] );

		if ( $case['expected'] !== $actual ) {
			$failures[] = 'page title visibility mismatch for ' . $label;
		}
	}
}

if ( 20 !== has_filter( 'generate_sidebar_layout', 'celina_template_sidebar_layout' ) ) {
	$failures[] = 'template sidebar policy missing';
}

if ( 20 !== has_filter( 'body_class', 'celina_template_body_class' ) ) {
	$failures[] = 'template body class policy missing';
}

if ( 10 !== has_filter( 'render_block_celina/hero', 'celina_filter_empty_hero_heading' ) ) {
	$failures[] = 'empty Hero heading filter missing';
}

if ( 10 !== has_action( 'pre_get_posts', 'celina_prepare_managed_news_query' ) ) {
	$failures[] = 'managed News query alignment missing';
}

$fixture_page = get_page_by_path( 'fixture-home', OBJECT, 'page' );

if ( ! $fixture_page instanceof WP_Post ) {
	$failures[] = 'fixture page missing';
} else {
	$render_content_page = static function (
		string $content,
		bool $front_page
	) use ( $fixture_page, $root ): string {
		$global_names = [
			'wp_query',
			'wp_the_query',
			'post',
			'pages',
			'page',
			'more',
			'multipage',
			'preview',
		];
		$original_globals = [];

		foreach ( $global_names as $name ) {
			$original_globals[ $name ] = [
				'exists' => array_key_exists( $name, $GLOBALS ),
				'value'  => $GLOBALS[ $name ] ?? null,
			];
		}

		$original_show_on_front = get_option( 'show_on_front' );
		$original_page_on_front = get_option( 'page_on_front' );
		$controlled_post         = clone $fixture_page;
		$controlled_post->post_title   = 'Contract Page Title';
		$controlled_post->post_content = $content;
		$query                          = new WP_Query();
		$query->posts                   = [ $controlled_post ];
		$query->post                    = $controlled_post;
		$query->post_count              = 1;
		$query->queried_object          = $controlled_post;
		$query->queried_object_id       = $controlled_post->ID;
		$query->is_page                 = true;
		$query->is_singular             = true;
		$query->is_home                 = false;
		$buffer_level                   = ob_get_level();
		$html                           = '';

		try {
			update_option( 'page_on_front', $front_page ? $controlled_post->ID : 0 );
			update_option( 'show_on_front', $front_page ? 'page' : 'posts' );

			$GLOBALS['wp_query']     = $query;
			$GLOBALS['wp_the_query'] = $query;
			$GLOBALS['post']         = $controlled_post;
			$GLOBALS['pages']        = [ $content ];
			$GLOBALS['page']         = 1;
			$GLOBALS['more']         = 1;
			$GLOBALS['multipage']    = 0;
			$GLOBALS['preview']      = false;

			ob_start();
			require $root . '/template-parts/content-page.php';
			$html = (string) ob_get_clean();
		} finally {
			while ( ob_get_level() > $buffer_level ) {
				ob_end_clean();
			}

			update_option( 'show_on_front', $original_show_on_front );
			update_option( 'page_on_front', $original_page_on_front );

			foreach ( $original_globals as $name => $state ) {
				if ( $state['exists'] ) {
					$GLOBALS[ $name ] = $state['value'];
				} else {
					unset( $GLOBALS[ $name ] );
				}
			}
		}

		return $html;
	};
	$visible_heading_text = static function ( string $heading ): string {
		$text = html_entity_decode(
			wp_strip_all_tags( $heading ),
			ENT_QUOTES | ENT_HTML5,
			'UTF-8'
		);
		$text = preg_replace( '/\p{Z}+/u', ' ', $text );
		$text = preg_replace( '/[\x{200B}\x{FEFF}]+/u', '', (string) $text );

		return trim( is_string( $text ) ? $text : '' );
	};
	$render_cases = [
		'empty page content' => [
			'content'         => '',
			'front_page'      => false,
			'heading'         => 'Contract Page Title',
			'has_page_header' => true,
		],
		'entity-only leading Hero' => [
			'content'         => $hero_markup( '&nbsp;' ),
			'front_page'      => false,
			'heading'         => 'Contract Page Title',
			'has_page_header' => true,
		],
		'visible leading Hero' => [
			'content'         => $hero_markup( 'Visible Hero' ),
			'front_page'      => false,
			'heading'         => 'Visible Hero',
			'has_page_header' => false,
		],
		'empty static front page' => [
			'content'         => '',
			'front_page'      => true,
			'heading'         => 'Contract Page Title',
			'has_page_header' => true,
		],
		'static front page with entity-only Hero' => [
			'content'         => $hero_markup( '&nbsp;' ),
			'front_page'      => true,
			'heading'         => 'Contract Page Title',
			'has_page_header' => true,
		],
		'static front page with visible Hero' => [
			'content'         => $hero_markup( 'Static Front Hero' ),
			'front_page'      => true,
			'heading'         => 'Static Front Hero',
			'has_page_header' => false,
		],
	];

	foreach ( $render_cases as $label => $case ) {
		$html     = $render_content_page( $case['content'], $case['front_page'] );
		$h1_count = preg_match_all( '/<h1\b[^>]*>(.*?)<\/h1>/is', $html, $matches );

		if ( 1 !== $h1_count ) {
			$failures[] = 'rendered H1 count mismatch for ' . $label;
		} elseif ( $case['heading'] !== $visible_heading_text( $matches[1][0] ) ) {
			$failures[] = 'rendered H1 source mismatch for ' . $label;
		}

		$has_page_header = false !== strpos( $html, 'celina-page-header' );

		if ( $case['has_page_header'] !== $has_page_header ) {
			$failures[] = 'rendered page header mismatch for ' . $label;
		}
	}

	$reading_original_query         = $GLOBALS['wp_query'] ?? null;
	$reading_original_the_query     = $GLOBALS['wp_the_query'] ?? null;
	$reading_original_post          = $GLOBALS['post'] ?? null;
	$reading_original_show_on_front = get_option( 'show_on_front' );
	$reading_original_page_on_front = get_option( 'page_on_front' );

	try {
		update_option( 'show_on_front', 'posts' );
		update_option( 'page_on_front', 0 );

		$posts_query          = new WP_Query();
		$posts_query->is_home = true;

		$GLOBALS['wp_query']     = $posts_query;
		$GLOBALS['wp_the_query'] = $posts_query;
		$GLOBALS['post']         = null;

		if (
			function_exists( 'celina_is_latest_posts_front_page' ) &&
			! celina_is_latest_posts_front_page()
		) {
			$failures[] = 'latest-posts front page not recognized';
		}

		if (
			function_exists( 'celina_resolve_latest_posts_front_page_template' ) &&
			function_exists( 'celina_render_latest_posts_front_page' )
		) {
			$posts_template = celina_resolve_latest_posts_front_page_template();
			$loaded_template = '';
			$did_delegate = celina_render_latest_posts_front_page(
				static function ( string $template ) use ( &$loaded_template ): void {
					$loaded_template = $template;
				}
			);

			if ( ! $did_delegate || ! is_readable( $posts_template ) ) {
				$failures[] = 'latest-posts fallback template unresolved';
			}

			if ( $posts_template !== $loaded_template ) {
				$failures[] = 'latest-posts template not delegated';
			}

			$child_home = $root . '/home.php';

			if (
				is_readable( $child_home ) &&
				realpath( $child_home ) !== realpath( $posts_template )
			) {
				$failures[] = 'child home template not preferred';
			} elseif (
				! is_readable( $child_home ) &&
				! in_array( basename( $posts_template ), [ 'home.php', 'index.php' ], true )
			) {
				$failures[] = 'GeneratePress posts fallback mismatch';
			}
		}

		update_option( 'page_on_front', $fixture_page->ID );
		update_option( 'show_on_front', 'page' );

		$static_query = new WP_Query( [ 'page_id' => $fixture_page->ID ] );

		$GLOBALS['wp_query']     = $static_query;
		$GLOBALS['wp_the_query'] = $static_query;
		$GLOBALS['post']         = $static_query->post;

		if (
			function_exists( 'celina_is_latest_posts_front_page' ) &&
			celina_is_latest_posts_front_page()
		) {
			$failures[] = 'static front page recognized as latest posts';
		}

		if (
			function_exists( 'celina_resolve_latest_posts_front_page_template' ) &&
			'' !== celina_resolve_latest_posts_front_page_template()
		) {
			$failures[] = 'static front page resolved a posts template';
		}

		if ( function_exists( 'celina_render_latest_posts_front_page' ) ) {
			$static_loaded = false;
			$static_delegated = celina_render_latest_posts_front_page(
				static function () use ( &$static_loaded ): void {
					$static_loaded = true;
				}
			);

			if ( $static_delegated || $static_loaded ) {
				$failures[] = 'static front page delegated to posts template';
			}
		}

		if ( function_exists( 'celina_page_should_show_title' ) ) {
			if ( ! celina_page_should_show_title( '' ) ) {
				$failures[] = 'empty static front page title suppressed';
			}

			if (
				! celina_page_should_show_title(
					$hero_markup( '&nbsp;' )
				)
			) {
				$failures[] = 'entity-only static front page title suppressed';
			}

			if (
				celina_page_should_show_title(
					$hero_markup( 'Static Front Hero' )
				)
			) {
				$failures[] = 'visible-Hero static front page title duplicated';
			}
		}
	} finally {
		update_option( 'show_on_front', $reading_original_show_on_front );
		update_option( 'page_on_front', $reading_original_page_on_front );
		$GLOBALS['wp_query']     = $reading_original_query;
		$GLOBALS['wp_the_query'] = $reading_original_the_query;
		$GLOBALS['post']         = $reading_original_post;
	}

	$original_query     = $GLOBALS['wp_query'] ?? null;
	$original_the_query = $GLOBALS['wp_the_query'] ?? null;
	$original_post      = $GLOBALS['post'] ?? null;
	$owned_query        = new WP_Query( [ 'page_id' => $fixture_page->ID ] );

	$GLOBALS['wp_query']     = $owned_query;
	$GLOBALS['wp_the_query'] = $owned_query;
	$GLOBALS['post']         = $owned_query->post;

	if (
		function_exists( 'celina_is_owned_template_route' ) &&
		! celina_is_owned_template_route()
	) {
		$failures[] = 'fixture page not recognized as owned route';
	}

	$owned_classes = apply_filters( 'body_class', [ 'contract-baseline' ], [] );

	if ( ! in_array( 'celina-template-full-width', $owned_classes, true ) ) {
		$failures[] = 'owned route full-width body class missing';
	}

	if (
		function_exists( 'celina_template_sidebar_layout' ) &&
		'no-sidebar' !== celina_template_sidebar_layout( 'right-sidebar' )
	) {
		$failures[] = 'owned route sidebar policy mismatch';
	}

	$search_query = new WP_Query( [ 's' => 'celina-template-contract-no-match' ] );

	$GLOBALS['wp_query']     = $search_query;
	$GLOBALS['wp_the_query'] = $search_query;
	$GLOBALS['post']         = $search_query->post;

	if (
		function_exists( 'celina_is_owned_template_route' ) &&
		celina_is_owned_template_route()
	) {
		$failures[] = 'search route incorrectly recognized as owned';
	}

	$search_classes = apply_filters( 'body_class', [ 'contract-baseline' ], [] );

	if ( in_array( 'celina-template-full-width', $search_classes, true ) ) {
		$failures[] = 'search route received full-width body class';
	}

	if (
		function_exists( 'celina_template_sidebar_layout' ) &&
		'right-sidebar' !== celina_template_sidebar_layout( 'right-sidebar' )
	) {
		$failures[] = 'search route sidebar policy mismatch';
	}

	$GLOBALS['wp_query']     = $original_query;
	$GLOBALS['wp_the_query'] = $original_the_query;
	$GLOBALS['post']         = $original_post;
}

$with_controlled_query = static function ( WP_Query $query, callable $render ): string {
	$global_names = [
		'wp_query',
		'wp_the_query',
		'post',
		'id',
		'authordata',
		'currentday',
		'currentmonth',
		'pages',
		'page',
		'more',
		'multipage',
		'numpages',
		'preview',
	];
	$original_globals = [];

	foreach ( $global_names as $name ) {
		$original_globals[ $name ] = [
			'exists' => array_key_exists( $name, $GLOBALS ),
			'value'  => $GLOBALS[ $name ] ?? null,
		];
	}

	$query->rewind_posts();
	$controlled_post = $query->post instanceof WP_Post
		? $query->post
		: ( $query->posts[0] ?? null );
	$content         = $controlled_post instanceof WP_Post
		? (string) $controlled_post->post_content
		: '';
	$buffer_level    = ob_get_level();
	$html            = '';

	try {
		$GLOBALS['wp_query']     = $query;
		$GLOBALS['wp_the_query'] = $query;
		$GLOBALS['post']         = $controlled_post;
		$GLOBALS['pages']        = [ $content ];
		$GLOBALS['page']         = 1;
		$GLOBALS['more']         = 1;
		$GLOBALS['multipage']    = 0;
		$GLOBALS['preview']      = false;

		ob_start();
		$render();
		$html = (string) ob_get_clean();
	} finally {
		while ( ob_get_level() > $buffer_level ) {
			ob_end_clean();
		}

		foreach ( $original_globals as $name => $state ) {
			if ( $state['exists'] ) {
				$GLOBALS[ $name ] = $state['value'];
			} else {
				unset( $GLOBALS[ $name ] );
			}
		}
	}

	return $html;
};

if (
	function_exists( 'celina_resolve_managed_posts_page' ) &&
	function_exists( 'celina_render_managed_posts_page' )
) {
	$posts_page_id            = (int) get_option( 'page_for_posts' );
	$posts_page               = get_post( $posts_page_id );
	$original_blueprint_key   = get_post_meta( $posts_page_id, '_celina_blueprint_key', true );
	$original_content         = $posts_page instanceof WP_Post ? $posts_page->post_content : '';
	$managed_content          = '<!-- wp:paragraph --><p>Managed Corporate News content.</p><!-- /wp:paragraph -->';

	if ( ! $posts_page instanceof WP_Post ) {
		$failures[] = 'posts page fixture missing for managed content contract';
	} else {
		try {
			wp_update_post(
				[
					'ID'           => $posts_page_id,
					'post_content' => $managed_content,
				]
			);
			delete_post_meta( $posts_page_id, '_celina_blueprint_key' );

			if ( null !== celina_resolve_managed_posts_page() ) {
				$failures[] = 'unmanaged posts page was resolved';
			}

			ob_start();
			$unmanaged_rendered = celina_render_managed_posts_page();
			$unmanaged_html     = (string) ob_get_clean();

			if ( $unmanaged_rendered || '' !== $unmanaged_html ) {
				$failures[] = 'unmanaged posts page rendered managed content';
			}

			update_post_meta(
				$posts_page_id,
				'_celina_blueprint_key',
				'page.corporate-news'
			);

			$resolved_posts_page = celina_resolve_managed_posts_page();
			if (
				! $resolved_posts_page instanceof WP_Post ||
				$posts_page_id !== $resolved_posts_page->ID
			) {
				$failures[] = 'canonical managed posts page unresolved';
			}

			ob_start();
			$managed_rendered = celina_render_managed_posts_page();
			$managed_html     = (string) ob_get_clean();

			if (
				! $managed_rendered ||
				false === strpos( $managed_html, 'Managed Corporate News content.' ) ||
				false === strpos( $managed_html, 'celina-entry-content' ) ||
				false !== strpos( $managed_html, '<h1' )
			) {
				$failures[] = 'managed posts page content rendering mismatch';
			}
		} finally {
			wp_update_post(
				[
					'ID'           => $posts_page_id,
					'post_content' => $original_content,
				]
			);

			if ( '' === $original_blueprint_key ) {
				delete_post_meta( $posts_page_id, '_celina_blueprint_key' );
			} else {
				update_post_meta(
					$posts_page_id,
					'_celina_blueprint_key',
					$original_blueprint_key
				);
			}
		}
	}
}

if ( function_exists( 'celina_render_post_archive' ) ) {
	$archive_renderer_reflection = new ReflectionFunction( 'celina_render_post_archive' );
	$archive_renderer_lines      = file( $archive_renderer_reflection->getFileName() );
	$archive_renderer_source     = is_array( $archive_renderer_lines )
		? implode(
			'',
			array_slice(
				$archive_renderer_lines,
				$archive_renderer_reflection->getStartLine() - 1,
				$archive_renderer_reflection->getEndLine() - $archive_renderer_reflection->getStartLine() + 1
			)
		)
		: '';

	foreach ( [
		'celina_news_grid_attributes',
		'celina_news_grid_render_navigation',
		'celina_news_grid_render_card',
	] as $plugin_helper ) {
		if (
			! preg_match(
				'/function_exists\(\s*[\'\"]' . preg_quote( $plugin_helper, '/' ) . '[\'\"]\s*\)/',
				$archive_renderer_source
			)
		) {
			$failures[] = 'archive plugin helper guard missing ' . $plugin_helper;
		}
	}

	if ( ! preg_match( '/[\'\"]headingLevel[\'\"]\s*\]\s*=\s*2/', $archive_renderer_source ) ) {
		$failures[] = 'archive shared card heading level missing';
	}

	$template_renderer_source = file_get_contents( $root . '/inc/template-renderer.php' );
	if (
		false !== $template_renderer_source &&
		preg_match( '/\b(?:require|include)(?:_once)?\b[^;]*celina-blocks/i', $template_renderer_source )
	) {
		$failures[] = 'theme archive directly loads plugin files';
	}

	$home_query = new WP_Query(
		[
			'order'          => 'ASC',
			'orderby'        => 'ID',
			'post_status'    => 'publish',
			'post_type'      => 'post',
			'posts_per_page' => 2,
		]
	);
	$home_query->is_home       = true;
	$home_query->max_num_pages = 2;
	$home_html                  = $with_controlled_query(
		$home_query,
		static function (): void {
			celina_render_post_archive( 'News <script>alert(1)</script>' );
		}
	);

	if ( 2 !== substr_count( $home_html, 'celina-article-card' ) ) {
		$failures[] = 'home archive card count mismatch';
	}

	if ( false === strpos( $home_html, '<h1>News &lt;script&gt;alert(1)&lt;/script&gt;</h1>' ) ) {
		$failures[] = 'home archive title escaping mismatch';
	}

	if ( false === strpos( $home_html, 'celina-card-grid' ) ) {
		$failures[] = 'home archive grid missing';
	}

	if (
		false === strpos( $home_html, 'nav-links' ) ||
		false === strpos( $home_html, 'page-numbers' )
	) {
		$failures[] = 'home archive pagination missing';
	}

	$category = get_term_by( 'slug', 'uncategorized', 'category' );

	if ( ! $category instanceof WP_Term ) {
		$failures[] = 'fixture category missing';
	} else {
		$category_query = new WP_Query(
			[
				'cat'            => $category->term_id,
				'order'          => 'ASC',
				'orderby'        => 'ID',
				'post_status'    => 'publish',
				'posts_per_page' => 2,
			]
		);
		$category_description_filter = static function (): string {
			return '<p>Allowed <strong>description</strong><script>alert(1)</script></p>';
		};

		add_filter( 'category_description', $category_description_filter, 99 );

		try {
			$category_html = $with_controlled_query(
				$category_query,
				static function (): void {
					celina_render_post_archive(
						single_cat_title( '', false ),
						category_description()
					);
				}
			);
		} finally {
			remove_filter( 'category_description', $category_description_filter, 99 );
		}

		if ( false === strpos( $category_html, '<h1>' . esc_html( $category->name ) . '</h1>' ) ) {
			$failures[] = 'category bare title mismatch';
		}

		if (
			false === strpos( $category_html, '<strong>description</strong>' ) ||
			false !== stripos( $category_html, '<script' )
		) {
			$failures[] = 'category description sanitization mismatch';
		}
	}

	$empty_query = new WP_Query(
		[
			'post_status' => 'publish',
			'post_type'   => 'post',
			's'           => 'celina-contract-no-published-article-can-match-this',
		]
	);
	$empty_html  = $with_controlled_query(
		$empty_query,
		static function (): void {
			celina_render_post_archive( 'Empty News' );
		}
	);

	if (
		false === strpos( $empty_html, '<h1>Empty News</h1>' ) ||
		false === strpos( $empty_html, 'No published articles were found.' ) ||
		false !== strpos( $empty_html, 'celina-article-card' )
	) {
		$failures[] = 'archive empty state mismatch';
	}

	$archive_category = get_term_by( 'slug', 'fixture-news', 'category' );
	$posts_page_id    = (int) get_option( 'page_for_posts' );
	$posts_page       = get_post( $posts_page_id );

	if ( ! $archive_category instanceof WP_Term || ! $posts_page instanceof WP_Post ) {
		$failures[] = 'managed category archive fixtures missing';
	} else {
		$original_posts_page_option = get_option( 'page_for_posts' );
		$original_posts_content     = $posts_page->post_content;
		$original_blueprint_key     = get_post_meta( $posts_page_id, '_celina_blueprint_key', true );
		$managed_archive_content    = '<!-- wp:celina/news-grid ' . wp_json_encode(
			[
				'heading'                => 'Corporate News',
				'category'               => $archive_category->term_id,
				'count'                  => 6,
				'layout'                 => 'news-index',
				'navigationCategories' => [ $archive_category->term_id ],
			],
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		) . ' /-->';
		$archive_query              = new WP_Query(
			[
				'cat'            => $archive_category->term_id,
				'order'          => 'ASC',
				'orderby'        => 'ID',
				'post_status'    => 'publish',
				'posts_per_page' => 2,
			]
		);
		$archive_post_titles        = array_map(
			static function ( WP_Post $post ): string {
				return get_the_title( $post );
			},
			$archive_query->posts
		);
		$render_archive             = static function () use ( $archive_category ): void {
			celina_render_post_archive(
				$archive_category->name,
				'<p>Managed <strong>archive description</strong><script>alert(1)</script></p>'
			);
		};
		$assert_fallback            = static function ( string $html, string $label ) use ( &$failures ): void {
			if (
				2 !== substr_count( $html, 'celina-article-card' ) ||
				false !== strpos( $html, 'celina-news-grid__navigation' ) ||
				false !== strpos( $html, 'celina-news-card--news-index' )
			) {
				$failures[] = $label;
			}
		};

		try {
			wp_update_post(
				[
					'ID'           => $posts_page_id,
					'post_content' => $managed_archive_content,
				]
			);
			update_post_meta( $posts_page_id, '_celina_blueprint_key', 'page.corporate-news' );

			if ( null === celina_managed_news_index_attributes() ) {
				$failures[] = 'managed category archive configuration unresolved';
			}

			if ( ! function_exists( 'celina_enqueue_managed_news_archive_styles' ) ) {
				$failures[] = 'managed category archive style loader unavailable';
			} else {
				$news_grid_block = WP_Block_Type_Registry::get_instance()->get_registered( 'celina/news-grid' );
				$style_handles   = $news_grid_block instanceof WP_Block_Type
					? $news_grid_block->style_handles
					: [];

				if ( [] === $style_handles ) {
					$failures[] = 'managed category archive shared styles unregistered';
				} else {
					foreach ( $style_handles as $style_handle ) {
						wp_dequeue_style( $style_handle );
					}

					$with_controlled_query(
						$archive_query,
						static function (): void {
							celina_enqueue_managed_news_archive_styles();
						}
					);

					foreach ( $style_handles as $style_handle ) {
						if ( ! wp_style_is( $style_handle, 'enqueued' ) ) {
							$failures[] = 'managed category archive shared style missing ' . $style_handle;
						}
					}
				}
			}

			$managed_archive_html = $with_controlled_query( $archive_query, $render_archive );
			$category_url         = get_term_link( $archive_category );

			if (
				2 !== substr_count( $managed_archive_html, 'celina-news-card--news-index' ) ||
				false !== strpos( $managed_archive_html, 'celina-article-card' ) ||
				false === strpos( $managed_archive_html, 'celina-news-grid celina-news-grid--news-index' )
			) {
				$failures[] = 'managed category archive shared cards mismatch';
			}

			if (
				is_wp_error( $category_url ) ||
				false === strpos( $managed_archive_html, 'celina-news-grid__navigation' ) ||
				false === strpos(
					$managed_archive_html,
					'href="' . esc_url( $category_url ) . '" aria-current="page"'
				)
			) {
				$failures[] = 'managed category archive active navigation mismatch';
			}

			if (
				false === strpos( $managed_archive_html, '<h1>' . esc_html( $archive_category->name ) . '</h1>' ) ||
				false === strpos( $managed_archive_html, '<strong>archive description</strong>' ) ||
				false !== stripos( $managed_archive_html, '<script' ) ||
				false === strpos( $managed_archive_html, 'nav-links' ) ||
				false === strpos( $managed_archive_html, 'page-numbers' )
			) {
				$failures[] = 'managed category archive shell mismatch';
			}

			foreach ( $archive_post_titles as $archive_post_title ) {
				if (
					1 !== substr_count(
						$managed_archive_html,
						'>' . esc_html( $archive_post_title ) . '</a></h2>'
					)
				) {
					$failures[] = 'managed category archive h2 loop mismatch ' . $archive_post_title;
				}
			}

			if ( false !== strpos( $managed_archive_html, '<h3>' ) ) {
				$failures[] = 'managed category archive contains h3 card title';
			}

			wp_update_post(
				[
					'ID'           => $posts_page_id,
					'post_content' => '<!-- wp:paragraph --><p>No managed grid.</p><!-- /wp:paragraph -->',
				]
			);
			$assert_fallback(
				$with_controlled_query( $archive_query, $render_archive ),
				'category archive without news-index did not use fallback cards'
			);

			wp_update_post(
				[
					'ID'           => $posts_page_id,
					'post_content' => $managed_archive_content,
				]
			);
			delete_post_meta( $posts_page_id, '_celina_blueprint_key' );
			$assert_fallback(
				$with_controlled_query( $archive_query, $render_archive ),
				'unmanaged category archive did not use fallback cards'
			);

			update_option( 'page_for_posts', 0 );
			$assert_fallback(
				$with_controlled_query( $archive_query, $render_archive ),
				'category archive without posts page did not use fallback cards'
			);
		} finally {
			update_option( 'page_for_posts', $original_posts_page_option );
			wp_update_post(
				[
					'ID'           => $posts_page_id,
					'post_content' => $original_posts_content,
				]
			);

			if ( '' === $original_blueprint_key ) {
				delete_post_meta( $posts_page_id, '_celina_blueprint_key' );
			} else {
				update_post_meta( $posts_page_id, '_celina_blueprint_key', $original_blueprint_key );
			}
		}
	}
}

$fixture_post = get_page_by_path( 'fixture-news-1', OBJECT, 'post' );

if (
	! $fixture_post instanceof WP_Post ||
	! is_readable( $root . '/template-parts/content-single.php' )
) {
	$failures[] = 'single article fixture or template part missing';
} else {
	$single_query = new WP_Query(
		[
			'p'         => $fixture_post->ID,
			'post_type' => 'post',
		]
	);
	$single_post                 = clone $fixture_post;
	$single_post->post_title   = 'Contract <script>alert(1)</script> & Article';
	$single_post->post_content = $hero_markup( 'Visible Article Hero' )
		. '<!-- wp:paragraph --><p>Controlled article content.</p><!-- /wp:paragraph -->';
	$single_query->posts[0]          = $single_post;
	$single_query->post              = $single_post;
	$single_query->queried_object    = $single_post;
	$single_query->queried_object_id = $single_post->ID;
	$single_html                       = $with_controlled_query(
		$single_query,
		static function (): void {
			while ( have_posts() ) {
				the_post();
				get_template_part( 'template-parts/content', 'single' );
			}
		}
	);
	$single_h1_count = preg_match_all( '/<h1\b[^>]*>.*?<\/h1>/is', $single_html );

	if ( 1 !== $single_h1_count ) {
		$failures[] = 'single article H1 count mismatch with visible Hero';
	}

	if (
		false === strpos(
			$single_html,
			'<h1>' . esc_html( get_the_title( $single_post ) ) . '</h1>'
		)
	) {
		preg_match( '/<h1\b[^>]*>.*?<\/h1>/is', $single_html, $single_title_match );
		$failures[] = 'single article escaped title mismatch: '
			. ( $single_title_match[0] ?? 'missing H1' );
	}

	if ( false !== stripos( $single_html, '<script' ) ) {
		$failures[] = 'single article contains an unsafe script element';
	}

	if (
		false === strpos( $single_html, 'celina-single-article' ) ||
		false === strpos( $single_html, 'celina-entry-content' ) ||
		false === strpos( $single_html, '<time datetime=' )
	) {
		$failures[] = 'single article structure mismatch';
	}

	if (
		1 !== preg_match(
			"/<h2\\b[^>]*class=([\"'])[^\"']*celina-hero__heading[^\"']*\\1[^>]*>Visible Article Hero<\\/h2>/is",
			$single_html
		)
	) {
		$failures[] = 'single article Hero heading level mismatch';
	}

	$single_post->post_content = $hero_markup( '&nbsp;' );
	$single_query->posts[0]     = $single_post;
	$single_query->post         = $single_post;
	$empty_hero_single_html     = $with_controlled_query(
		$single_query,
		static function (): void {
			while ( have_posts() ) {
				the_post();
				get_template_part( 'template-parts/content', 'single' );
			}
		}
	);

	if (
		1 !== preg_match_all( '/<h1\b[^>]*>.*?<\/h1>/is', $empty_hero_single_html ) ||
		false !== strpos( $empty_hero_single_html, 'celina-hero__heading' )
	) {
		$failures[] = 'single article empty Hero heading cleanup mismatch';
	}
}

if (
	function_exists( 'celina_article_reading_minutes' ) &&
	function_exists( 'celina_article_primary_category' ) &&
	function_exists( 'celina_article_featured_image' )
) {
	$article_template_author_id = wp_insert_user(
		[
			'user_login'   => uniqid( 'celina_article_author_', false ),
			'user_pass'    => wp_generate_password( 24 ),
			'display_name' => 'Contract Author',
			'role'         => 'author',
		]
	);
	$article_template_post_ids  = [];
	$article_template_image_id  = 0;
	$article_template_tag       = 'Contract Tag ' . strtolower(
		wp_generate_password( 6, false, false )
	);
	$article_template_category  = get_term_by( 'slug', 'fixture-news', 'category' );

	if (
		is_wp_error( $article_template_author_id ) ||
		! $article_template_category instanceof WP_Term
	) {
		$failures[] = 'single article rich fixtures unavailable';
	} else {
		$article_template_author_id = (int) $article_template_author_id;
		$article_template_date      = current_time( 'timestamp' ) - 600;
		$article_template_rows      = [
			[
				'title' => 'Contract previous article',
				'date'  => $article_template_date,
			],
			[
				'title' => 'Contract current article',
				'date'  => $article_template_date + 120,
			],
			[
				'title' => 'Contract next article',
				'date'  => $article_template_date + 240,
			],
		];

		foreach ( $article_template_rows as $article_template_row ) {
			$article_template_id = wp_insert_post(
				[
					'post_type'     => 'post',
					'post_status'   => 'publish',
					'post_title'    => $article_template_row['title'],
					'post_content'  => $hero_markup( 'Visible Article Hero' )
						. '<!-- wp:heading {"level":1} -->'
						. '<h1 class="wp-block-heading" data-contract="core-heading">Core body heading</h1>'
						. '<!-- /wp:heading -->'
						. '<!-- wp:celina/article-layout --><div data-contract="article-layout-container">'
						. '<!-- wp:group --><div class="wp-block-group">'
						. '<!-- wp:heading {"level":1} -->'
						. '<h1 class="wp-block-heading" data-contract="allowed-nested-heading">Allowed nested heading</h1>'
						. '<!-- /wp:heading -->'
						. '</div><!-- /wp:group -->'
						. '</div><!-- /wp:celina/article-layout -->'
						. '<!-- wp:heading -->'
						. '<h2 class="wp-block-heading" data-contract="core-heading-two">Core H2 unchanged</h2>'
						. '<!-- /wp:heading -->'
						. '<!-- wp:heading {"level":3} -->'
						. '<h3 class="wp-block-heading" data-contract="core-heading-three">Core H3 unchanged</h3>'
						. '<!-- /wp:heading -->'
						. '<!-- wp:html -->'
						. '<h1 id="classic-heading" data-contract="classic-heading">Classic body heading</h1>'
						. '<!-- /wp:html -->'
						. '<h1 id="raw-heading" data-contract="raw-heading">Raw body heading</h1>'
						. '<!-- wp:paragraph --><p>Controlled article content.</p><!-- /wp:paragraph -->',
					'post_author'   => $article_template_author_id,
					'post_category' => [ (int) $article_template_category->term_id ],
					'post_date'     => wp_date( 'Y-m-d H:i:s', $article_template_row['date'] ),
					'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $article_template_row['date'] ),
				]
			);

			if ( is_wp_error( $article_template_id ) || 0 === $article_template_id ) {
				$failures[] = 'single article navigation post fixture unavailable';
				continue;
			}

			$article_template_post_ids[] = (int) $article_template_id;
		}

		$article_first_id = wp_insert_post(
			[
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_title'    => 'Contract first article',
				'post_content'  => '<!-- wp:paragraph --><p>First article.</p><!-- /wp:paragraph -->',
				'post_author'   => $article_template_author_id,
				'post_category' => [ (int) $article_template_category->term_id ],
				'post_date'     => '1971-01-02 00:00:00',
				'post_date_gmt' => '1971-01-02 00:00:00',
			]
		);

		if ( ! is_wp_error( $article_first_id ) && $article_first_id > 0 ) {
			$article_template_post_ids[] = (int) $article_first_id;
		} else {
			$article_first_id = 0;
			$failures[]       = 'single article first-post fixture unavailable';
		}

		if ( 4 === count( $article_template_post_ids ) ) {
			$article_previous_id = $article_template_post_ids[0];
			$article_current_id  = $article_template_post_ids[1];
			$article_next_id     = $article_template_post_ids[2];
			$article_newest_id   = $article_next_id;
			$article_current     = get_post( $article_current_id );

			update_user_meta(
				$article_template_author_id,
				'celina_job_title',
				'Communications <script>alert(1)</script>'
			);
			wp_set_post_tags( $article_current_id, [ $article_template_tag ], false );

			$article_template_image_id = wp_insert_attachment(
				[
					'post_title'     => 'Single article contract image',
					'post_status'    => 'inherit',
					'post_mime_type' => 'image/jpeg',
					'guid'           => home_url( '/wp-content/uploads/single-article-contract.jpg' ),
				]
			);
			$article_template_image_id = is_wp_error( $article_template_image_id )
				? 0
				: (int) $article_template_image_id;

			if ( 0 === $article_template_image_id || ! $article_current instanceof WP_Post ) {
				$failures[] = 'single article media fixture unavailable';
			} else {
				update_post_meta(
					$article_current_id,
					'_thumbnail_id',
					$article_template_image_id
				);

				$article_title_filter = static function ( string $title, int $post_id ) use ( $article_current_id ): string {
					return $article_current_id === $post_id
						? 'Contract <script>alert(1)</script> & Article'
						: $title;
				};
				$article_image_filter = static function ( string $html, int $post_id ) use ( $article_current_id ): string {
					return $article_current_id === $post_id
						? '<img src="https://example.test/uploads/single-article-contract.jpg" alt="Contract image">'
						: $html;
				};
				$article_foreign_output_filter = static function ( string $content ) use ( $article_current_id ): string {
					return $article_current_id === get_the_ID()
						? $content . '<h1 data-contract="foreign-output">Foreign output heading</h1>'
						: $content;
				};
				$render_single_article = static function ( int $post_id ) use ( $with_controlled_query ): string {
					$query = new WP_Query(
						[
							'p'           => $post_id,
							'post_type'   => 'post',
							'post_status' => 'publish',
						]
					);

					return $with_controlled_query(
						$query,
						static function (): void {
							while ( have_posts() ) {
								the_post();
								get_template_part( 'template-parts/content', 'single' );
							}
						}
					);
				};
				$count_hook_callbacks = static function ( string $hook_name, ?int $priority = null ): int {
					$hook = $GLOBALS['wp_filter'][ $hook_name ] ?? null;

					if ( ! $hook instanceof WP_Hook ) {
						return 0;
					}

					if ( null !== $priority ) {
						return count( $hook->callbacks[ $priority ] ?? [] );
					}

					return array_sum( array_map( 'count', $hook->callbacks ) );
				};

				add_filter( 'the_title', $article_title_filter, 99, 2 );
				add_filter( 'post_thumbnail_html', $article_image_filter, 99, 5 );
				add_filter( 'the_content', $article_foreign_output_filter, 12 );

				try {
					$article_content_filter_calls = 0;
					$article_heading_filter_seen  = false;
					$article_render_hook_count    = $count_hook_callbacks( 'render_block_core/heading' );
					$article_content_filter       = static function ( string $content ) use ( &$article_content_filter_calls, &$article_heading_filter_seen, $article_render_hook_count, $count_hook_callbacks ): string {
						++$article_content_filter_calls;
						$article_heading_filter_seen = $count_hook_callbacks( 'render_block_core/heading' ) > $article_render_hook_count;

						return $content;
					};
					add_filter( 'the_content', $article_content_filter, 8 );
					try {
						$article_html = $render_single_article( $article_current_id );
					} finally {
						remove_filter( 'the_content', $article_content_filter, 8 );
					}
					if ( 1 !== $article_content_filter_calls ) {
						$failures[] = 'article canonical body filter execution count mismatch';
					}
					if (
						! $article_heading_filter_seen ||
						$article_render_hook_count !== $count_hook_callbacks( 'render_block_core/heading' )
					) {
						$failures[] = 'article heading block filter success cleanup mismatch';
					}

					$nested_template_html   = '';
					$nested_template_active = false;
					$nested_template_filter = static function ( string $content ) use ( &$nested_template_html, &$nested_template_active, $render_single_article, $article_next_id ): string {
						if ( $nested_template_active ) {
							return $content;
						}

						$nested_template_active = true;
						try {
							$nested_template_html = $render_single_article( $article_next_id );
						} finally {
							$nested_template_active = false;
						}

						return $content;
					};
					$nested_heading_markers = [];
					$nested_marker_probe    = static function ( string $block_content, array $block ) use ( &$nested_heading_markers ): string {
						if (
							'core/heading' === ( $block['blockName'] ?? null ) &&
							1 === (int) ( $block['attrs']['level'] ?? 2 ) &&
							is_string( $block['attrs']['celinaArticleHeadingScope'] ?? null )
						) {
							$nested_heading_markers[] = $block['attrs']['celinaArticleHeadingScope'];
						}

						return $block_content;
					};
					add_filter( 'the_content', $nested_template_filter, 7 );
					add_filter( 'render_block_core/heading', $nested_marker_probe, 5, 2 );
					$nested_render_hook_count = $count_hook_callbacks( 'render_block_core/heading' );
					try {
						$outer_template_html = $render_single_article( $article_current_id );
					} finally {
						remove_filter( 'the_content', $nested_template_filter, 7 );
						remove_filter( 'render_block_core/heading', $nested_marker_probe, 5 );
					}
					if (
						1 !== preg_match( '/<h2\b[^>]*data-contract="core-heading"[^>]*>Core body heading<\/h2>/i', $outer_template_html ) ||
						1 !== preg_match( '/<h2\b[^>]*data-contract="core-heading"[^>]*>Core body heading<\/h2>/i', $nested_template_html ) ||
						2 !== count( array_unique( $nested_heading_markers ) ) ||
						false !== strpos( $outer_template_html, 'celinaArticleHeadingScope' ) ||
						false !== strpos( $nested_template_html, 'celinaArticleHeadingScope' )
					) {
						$failures[] = 'article nested template heading reentrancy mismatch';
					}
					if ( $nested_render_hook_count - 1 !== $count_hook_callbacks( 'render_block_core/heading' ) ) {
						$failures[] = 'article nested template hook cleanup mismatch';
					}

					foreach ( [ 1, 7, 8, 9, 12 ] as $unrelated_priority ) {
						$unrelated_nested_html   = '';
						$unrelated_nested_active = false;
						$unrelated_nested_filter = static function ( string $content ) use ( &$unrelated_nested_html, &$unrelated_nested_active ): string {
							if ( $unrelated_nested_active ) {
								return $content;
							}

							$unrelated_nested_active = true;
							try {
								$unrelated_nested_html = apply_filters(
									'the_content',
									'<!-- wp:heading {"level":1} -->'
									. '<h1 class="wp-block-heading" data-contract="unrelated-unmarked">Unrelated unmarked heading</h1>'
									. '<!-- /wp:heading -->'
									. '<!-- wp:heading {"level":1,"celinaArticleHeadingScope":"wrong-token"} -->'
									. '<h1 class="wp-block-heading" data-contract="unrelated-mismatch">Unrelated mismatched heading</h1>'
									. '<!-- /wp:heading -->'
								);
							} finally {
								$unrelated_nested_active = false;
							}

							return $content;
						};
						add_filter( 'the_content', $unrelated_nested_filter, $unrelated_priority );
						$unrelated_render_hook_count = $count_hook_callbacks( 'render_block_core/heading' );
						try {
							$render_single_article( $article_current_id );
						} finally {
							remove_filter( 'the_content', $unrelated_nested_filter, $unrelated_priority );
						}
						if (
							false === strpos( $unrelated_nested_html, '<h1 class="wp-block-heading" data-contract="unrelated-unmarked">' ) ||
							false === strpos( $unrelated_nested_html, '<h1 class="wp-block-heading" data-contract="unrelated-mismatch">' ) ||
							false !== strpos( $unrelated_nested_html, '<h2' ) ||
							false !== strpos( $unrelated_nested_html, 'celinaArticleHeadingScope' )
						) {
							$failures[] = 'article unrelated nested content pipeline was normalized at priority ' . $unrelated_priority;
						}
						if ( $unrelated_render_hook_count !== $count_hook_callbacks( 'render_block_core/heading' ) ) {
							$failures[] = 'article unrelated nested pipeline hook cleanup mismatch at priority ' . $unrelated_priority;
						}
					}
					$secondary_loop_html = $with_controlled_query(
						new WP_Query(
							[
								'p'           => $article_current_id,
								'post_type'   => 'post',
								'post_status' => 'publish',
							]
						),
						static function () use ( $article_current_id ): void {
							while ( have_posts() ) {
								the_post();
								echo apply_filters(
									'the_content',
									'<h1 data-contract="main-loop">Main loop heading</h1>'
									. '<!-- <h1>comment</h1> -->'
									. '<![CDATA[<h1>cdata</h1>]]>'
									. '<?contract ignored?>'
									. '<script><h1>script</h1></script>'
									. '<style><h1>style</h1></style>'
									. '<textarea><h1>textarea</h1></textarea>'
									. '<title><h1>title</h1></title>'
									. '<template><h1>template</h1><template><h1>nested</h1></template></template>'
									. '<xmp><h1>xmp</h1></xmp>'
									. '<iframe><h1>iframe</h1></iframe>'
									. '<noembed><h1>noembed</h1></noembed>'
									. '<noframes><h1>noframes</h1></noframes>'
									. '<plaintext><h1>plaintext</h1>'
								);

								$secondary_query = new WP_Query(
									[
										'p'           => $article_current_id,
										'post_type'   => 'post',
										'post_status' => 'publish',
									]
								);
								while ( $secondary_query->have_posts() ) {
									$secondary_query->the_post();
									echo apply_filters(
										'the_content',
										'<h1 data-contract="secondary-loop">Secondary loop heading</h1>'
									);
								}
								wp_reset_postdata();
							}
						}
					);

					if (
						false === strpos( $secondary_loop_html, '<h1 data-contract="main-loop">' ) ||
						false === strpos( $secondary_loop_html, '<h1 data-contract="secondary-loop">' )
					) {
						$failures[] = 'article secondary loop heading isolation mismatch';
					}

					$feed_heading_filter_seen = null;
					$feed_heading_probe       = static function ( string $content ) use ( &$feed_heading_filter_seen ): string {
						$feed_heading_filter_seen = false !== has_filter( 'render_block_core/heading' );

						return $content;
					};
					$feed_query = new WP_Query(
						[
							'p'           => $article_current_id,
							'post_type'   => 'post',
							'post_status' => 'publish',
						]
					);
					$feed_query->is_feed = true;
					add_filter( 'the_content', $feed_heading_probe, 8 );
					try {
						$with_controlled_query(
							$feed_query,
							static function (): void {
								while ( have_posts() ) {
									the_post();
									get_template_part( 'template-parts/content', 'single' );
								}
							}
						);
					} finally {
						remove_filter( 'the_content', $feed_heading_probe, 8 );
					}
					if ( false !== $feed_heading_filter_seen ) {
						$failures[] = 'article feed route installed heading block filter';
					}

					$secondary_query_filter_seen = null;
					$secondary_query_probe       = static function ( string $content ) use ( &$secondary_query_filter_seen ): string {
						$secondary_query_filter_seen = false !== has_filter( 'render_block_core/heading' );

						return $content;
					};
					add_filter( 'the_content', $secondary_query_probe, 8 );
					try {
						$with_controlled_query(
							new WP_Query(
								[
									'p'           => $article_current_id,
									'post_type'   => 'post',
									'post_status' => 'publish',
								]
							),
							static function (): void {
								while ( have_posts() ) {
									the_post();
									$GLOBALS['wp_the_query'] = new WP_Query();
									get_template_part( 'template-parts/content', 'single' );
								}
							}
						);
					} finally {
						remove_filter( 'the_content', $secondary_query_probe, 8 );
					}
					if ( false !== $secondary_query_filter_seen ) {
						$failures[] = 'article secondary query installed heading block filter';
					}

					$category_url = get_term_link( $article_template_category );
					$tag_terms    = get_the_tags( $article_current_id );
					$tag_url      = is_array( $tag_terms ) && isset( $tag_terms[0] )
						? get_tag_link( $tag_terms[0] )
						: new WP_Error( 'missing_tag', 'Missing tag.' );
					$order_markers = [
						'celina-article-category',
						'<h1>',
						'celina-article-meta',
						'celina-article-tags',
						'celina-article-media',
						'celina-article-byline',
						'celina-entry-content',
						'celina-article-navigation',
					];
					$order_positions = array_map(
						static function ( string $marker ) use ( $article_html ) {
							return strpos( $article_html, $marker );
						},
						$order_markers
					);

					if (
						in_array( false, $order_positions, true ) ||
						$order_positions !== array_values( array_unique( $order_positions ) ) ||
						$order_positions !== array_values( array_filter( $order_positions, 'is_int' ) ) ||
						$order_positions !== ( $sorted_order_positions = ( function ( array $positions ): array {
							sort( $positions, SORT_NUMERIC );

							return $positions;
						} )( $order_positions ) )
					) {
						$failures[] = 'single article component order mismatch';
					}

					if (
						is_wp_error( $category_url ) ||
						false === strpos( $article_html, 'href="' . esc_url( $category_url ) . '"' ) ||
						false === strpos( $article_html, esc_html( $article_template_category->name ) ) ||
						is_wp_error( $tag_url ) ||
						false === strpos( $article_html, 'href="' . esc_url( $tag_url ) . '"' )
					) {
						$failures[] = 'single article linked taxonomy metadata mismatch';
					}

					if (
						4 !== preg_match_all( '/<h1\b[^>]*>.*?<\/h1>/is', $article_html ) ||
						false === strpos(
							$article_html,
							'<h1>Contract &lt;script&gt;alert(1)&lt;/script&gt; &amp; Article</h1>'
						) ||
						false !== stripos( $article_html, '<script' ) ||
						1 !== preg_match(
							'/<h2\b[^>]*class=(["\'])[^"\']*celina-hero__heading[^"\']*\1[^>]*>Visible Article Hero<\/h2>/is',
							$article_html
						)
					) {
						$failures[] = 'single article heading or escaping mismatch';
					}

					if (
						1 !== preg_match(
							'/<h2\b(?=[^>]*data-contract="core-heading")[^>]*>Core body heading<\/h2>/is',
							$article_html
						) ||
						1 !== preg_match(
							'/<h2\b(?=[^>]*data-contract="allowed-nested-heading")[^>]*>Allowed nested heading<\/h2>/is',
							$article_html
						) ||
						false === strpos(
							$article_html,
							'<h2 class="wp-block-heading" data-contract="core-heading-two">Core H2 unchanged</h2>'
						) ||
						false === strpos(
							$article_html,
							'<h3 class="wp-block-heading" data-contract="core-heading-three">Core H3 unchanged</h3>'
						) ||
						false === strpos(
							$article_html,
							'<h1 id="classic-heading" data-contract="classic-heading">Classic body heading</h1>'
						) ||
						false === strpos(
							$article_html,
							'<h1 id="raw-heading" data-contract="raw-heading">Raw body heading</h1>'
						) ||
						false === strpos(
							$article_html,
							'<h1 data-contract="foreign-output">Foreign output heading</h1>'
						) ||
						false !== strpos( $article_html, 'celinaArticleHeadingScope' )
					) {
						$failures[] = 'single article scoped Gutenberg heading normalization mismatch';
					}

					if (
						false === strpos( $article_html, '1 min read' ) ||
						false === strpos( $article_html, 'Contract Author' ) ||
						false === strpos(
							$article_html,
							'Communications &lt;script&gt;alert(1)&lt;/script&gt;'
						) ||
						false === strpos( $article_html, 'celina-article-byline__avatar' ) ||
						false === strpos(
							$article_html,
							'src="https://example.test/uploads/single-article-contract.jpg"'
						) ||
						false === strpos( $article_html, '<address class="celina-article-byline">' ) ||
						false === strpos( $article_html, '</address>' )
					) {
						$failures[] = 'single article media or byline mismatch';
					}

					preg_match(
						'/<address\b[^>]*class=(["\'])[^"\']*celina-article-byline[^"\']*\1[^>]*>(.*?)<\/address>/is',
						$article_html,
						$byline_match
					);
					if ( false !== stripos( $byline_match[0] ?? '', '<a ' ) ) {
						$failures[] = 'single article byline links the author';
					}

					if (
						false === strpos( $article_html, esc_url( get_permalink( $article_previous_id ) ) ) ||
						false === strpos( $article_html, esc_html( get_the_title( $article_previous_id ) ) ) ||
						false === strpos( $article_html, esc_url( get_permalink( $article_next_id ) ) ) ||
						false === strpos( $article_html, esc_html( get_the_title( $article_next_id ) ) )
					) {
						$failures[] = 'single article previous/next links mismatch';
					}

					$untitled_navigation_filter = static function ( string $title, int $post_id ) use ( $article_previous_id, $article_next_id ): string {
						return in_array( $post_id, [ $article_previous_id, $article_next_id ], true )
							? ''
							: $title;
					};
					add_filter( 'the_title', $untitled_navigation_filter, 100, 2 );
					try {
						$untitled_navigation_html = $render_single_article( $article_current_id );
					} finally {
						remove_filter( 'the_title', $untitled_navigation_filter, 100 );
					}
					if ( 2 !== substr_count( $untitled_navigation_html, 'Untitled article' ) ) {
						$failures[] = 'single article untitled navigation fallback mismatch';
					}

					if (
						false !== strpos( $article_html, 'comments_template' ) ||
						false !== strpos( $article_html, 'celina-article-sharing' )
					) {
						$failures[] = 'single article contains comments or sharing UI';
					}

					wp_set_post_tags( $article_current_id, [], false );
					$without_tags_html = $render_single_article( $article_current_id );
					if ( false !== strpos( $without_tags_html, 'celina-article-tags' ) ) {
						$failures[] = 'single article rendered empty tag controls';
					}

					$newest_html = $render_single_article( $article_newest_id );
					if (
						false === strpos( $newest_html, 'celina-article-navigation__previous' ) ||
						false !== strpos( $newest_html, 'celina-article-navigation__next' )
					) {
						$failures[] = 'single article newest navigation omission mismatch';
					}

					$first_html = $render_single_article( (int) $article_first_id );
					if (
						false !== strpos( $first_html, 'celina-article-navigation__previous' ) ||
						false === strpos( $first_html, 'celina-article-navigation__next' )
					) {
						$failures[] = 'single article first navigation omission mismatch';
					}

					$password_secret = 'CELINA_PASSWORD_SECRET_' . wp_generate_password( 12, false, false );
					$password_post_id = wp_insert_post(
						[
							'post_type'     => 'post',
							'post_status'   => 'publish',
							'post_title'    => 'Contract password article',
							'post_content'  => '<h1>' . $password_secret . '</h1>',
							'post_password' => 'contract-password',
							'post_author'   => $article_template_author_id,
							'post_category' => [ (int) $article_template_category->term_id ],
						]
					);
					if ( is_wp_error( $password_post_id ) || 0 === $password_post_id ) {
						$failures[] = 'single article password fixture unavailable';
					} else {
						$password_post_id           = (int) $password_post_id;
						$article_template_post_ids[] = $password_post_id;
						$password_article_html       = $render_single_article( $password_post_id );
						if (
							false !== strpos( $password_article_html, $password_secret ) ||
							false === strpos( $password_article_html, 'post-password-form' )
						) {
							$failures[] = 'single article password privacy mismatch';
						}
					}

					$paged_post_id = wp_insert_post(
						[
							'post_type'     => 'post',
							'post_status'   => 'publish',
							'post_title'    => 'Contract paged article',
							'post_content'  => '<p>Visible first-page contract.</p><!--nextpage--><p>Hidden second-page contract.</p>',
							'post_author'   => $article_template_author_id,
							'post_category' => [ (int) $article_template_category->term_id ],
						]
					);
					if ( is_wp_error( $paged_post_id ) || 0 === $paged_post_id ) {
						$failures[] = 'single article paged fixture unavailable';
					} else {
						$paged_post_id              = (int) $paged_post_id;
						$article_template_post_ids[] = $paged_post_id;
						$paged_article_html          = $render_single_article( $paged_post_id );
						if (
							false === strpos( $paged_article_html, 'Visible first-page contract.' ) ||
							false !== strpos( $paged_article_html, 'Hidden second-page contract.' )
						) {
							$failures[] = 'single article per-page content mismatch';
						}
					}

					$throwing_render_hook_count        = $count_hook_callbacks( 'render_block_core/heading' );
					$throwing_filter_saw_heading_hook = false;
					$throwing_article_filter          = static function ( string $content ) use ( &$throwing_filter_saw_heading_hook, $throwing_render_hook_count, $count_hook_callbacks ): string {
						$throwing_filter_saw_heading_hook = $count_hook_callbacks( 'render_block_core/heading' ) > $throwing_render_hook_count;
						throw new RuntimeException( 'Article content filter contract exception.' );
					};
					$article_content_exception_caught = false;
					add_filter( 'the_content', $throwing_article_filter, 8 );
					try {
						$render_single_article( $article_current_id );
					} catch ( RuntimeException $error ) {
						$article_content_exception_caught = 'Article content filter contract exception.' === $error->getMessage();
					} finally {
						remove_filter( 'the_content', $throwing_article_filter, 8 );
					}
					if (
						! $article_content_exception_caught ||
						! $throwing_filter_saw_heading_hook ||
						$throwing_render_hook_count !== $count_hook_callbacks( 'render_block_core/heading' )
					) {
						$failures[] = 'article heading block filter exception cleanup mismatch';
					}
				} finally {
					remove_filter( 'the_title', $article_title_filter, 99 );
					remove_filter( 'post_thumbnail_html', $article_image_filter, 99 );
					remove_filter( 'the_content', $article_foreign_output_filter, 12 );
				}
			}
		}

		if ( $article_template_image_id > 0 ) {
			wp_delete_attachment( $article_template_image_id, true );
		}
		foreach ( $article_template_post_ids as $article_template_post_id ) {
			wp_delete_post( $article_template_post_id, true );
		}

		$article_template_tag_term = get_term_by( 'name', $article_template_tag, 'post_tag' );
		if ( $article_template_tag_term instanceof WP_Term ) {
			wp_delete_term( $article_template_tag_term->term_id, 'post_tag' );
		}

		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}
		wp_delete_user( $article_template_author_id );
	}
}

$not_found_template = $root . '/404.php';

if ( ! is_readable( $not_found_template ) ) {
	$failures[] = '404 live render unavailable';
} else {
	$not_found_global_names = [
		'wp_query',
		'wp_the_query',
		'post',
		'id',
		'authordata',
		'currentday',
		'currentmonth',
		'pages',
		'page',
		'more',
		'multipage',
		'numpages',
		'preview',
	];
	$not_found_original_globals = [];

	foreach ( $not_found_global_names as $name ) {
		$not_found_original_globals[ $name ] = [
			'exists' => array_key_exists( $name, $GLOBALS ),
			'value'  => $GLOBALS[ $name ] ?? null,
		];
	}

	$not_found_query          = new WP_Query();
	$not_found_query->is_404  = true;
	$not_found_query->is_page = false;
	$not_found_buffer_level   = ob_get_level();
	$not_found_html           = '';
	$server_name_exists       = array_key_exists( 'SERVER_NAME', $_SERVER );
	$original_server_name     = $_SERVER['SERVER_NAME'] ?? null;
	$unsafe_home_url          = 'https://example.test/celina-home/?next="><script>alert(1)</script>';
	$home_url_filter          = static function () use ( $unsafe_home_url ): string {
		return $unsafe_home_url;
	};

	try {
		$GLOBALS['wp_query']     = $not_found_query;
		$GLOBALS['wp_the_query'] = $not_found_query;
		$GLOBALS['post']         = null;
		$_SERVER['SERVER_NAME']  = 'localhost';
		add_filter( 'home_url', $home_url_filter, 99, 4 );

		$expected_home_url = esc_url( home_url( '/' ) );

		ob_start();
		require $not_found_template;
		$not_found_html = (string) ob_get_clean();
	} finally {
		while ( ob_get_level() > $not_found_buffer_level ) {
			ob_end_clean();
		}

		remove_filter( 'home_url', $home_url_filter, 99 );

		if ( $server_name_exists ) {
			$_SERVER['SERVER_NAME'] = $original_server_name;
		} else {
			unset( $_SERVER['SERVER_NAME'] );
		}

		foreach ( $not_found_original_globals as $name => $state ) {
			if ( $state['exists'] ) {
				$GLOBALS[ $name ] = $state['value'];
			} else {
				unset( $GLOBALS[ $name ] );
			}
		}
	}

	if ( false === strpos( $not_found_html, 'data-celina-template="404"' ) ) {
		$failures[] = '404 live route marker missing';
	}

	$not_found_h1_count = preg_match_all(
		'/<h1\b[^>]*>(.*?)<\/h1>/is',
		$not_found_html,
		$not_found_h1_matches
	);
	$not_found_meaningful_h1 = array_filter(
		$not_found_h1_matches[1] ?? [],
		static function ( string $heading ): bool {
			return celina_has_visible_text( $heading );
		}
	);

	if (
		1 !== count( $not_found_meaningful_h1 ) ||
		1 !== $not_found_h1_count ||
		'Page not found' !== celina_normalize_visible_text( reset( $not_found_meaningful_h1 ) )
	) {
		$failures[] = '404 live meaningful H1 mismatch';
	}

	if (
		false === strpos( $not_found_html, '>404</p>' ) ||
		false === strpos( $not_found_html, 'The requested page does not exist or has moved.' )
	) {
		$failures[] = '404 live copy mismatch';
	}

	if (
		false === strpos(
			$not_found_html,
			'class="celina-button" href="' . esc_attr( $expected_home_url ) . '"'
		) ||
		false !== stripos( $not_found_html, '<script>alert(1)</script>' )
	) {
		$failures[] = '404 live home link mismatch';
	}

	if (
		1 !== preg_match(
			'/<form\b[^>]*class=(["\'])[^"\']*\bsearch-form\b[^"\']*\1[^>]*>/i',
			$not_found_html
		)
	) {
		$failures[] = '404 live search form missing';
	}
}

if ( ! current_theme_supports( 'editor-styles' ) ) {
	$failures[] = 'editor styles theme support missing';
}

$stylesheet_directory_uri = get_stylesheet_directory_uri();
$editor_styles            = get_editor_stylesheets();
$expected_editor_styles   = [
	$stylesheet_directory_uri . '/assets/css/tokens.css',
	$stylesheet_directory_uri . '/assets/css/content-templates.css',
];

foreach ( $expected_editor_styles as $editor_style ) {
	if ( ! in_array( $editor_style, $editor_styles, true ) ) {
		$failures[] = 'missing editor stylesheet ' . $editor_style;
	}
}

$content_stylesheet = $root . '/assets/css/content-templates.css';
$content_css        = is_readable( $content_stylesheet ) ? file_get_contents( $content_stylesheet ) : false;
$css_contracts      = [
	'frontend full-width container policy' => '/\.celina-template-full-width\s+#page\.grid-container\s*\{[^}]*max-width:\s*none;/s',
	'frontend default content gutter'      => '/\.celina-entry-content\s*>\s*\*\s*\{[^}]*width:\s*var\(--celina-container\);[^}]*max-width:\s*var\(--celina-grid-width\);/s',
	'frontend wide content gutter'         => '/\.celina-entry-content\s*>\s*\.alignwide\s*\{[^}]*width:\s*var\(--celina-container\);[^}]*max-width:\s*var\(--celina-grid-width\);/s',
	'frontend alignfull true width'        => '/\.celina-entry-content\s*>\s*\.alignfull\s*,[^{]*\{[^}]*width:\s*100%;[^}]*max-width:\s*none;/s',
	'editor default content width'         => '/\.editor-styles-wrapper\s+\.is-root-container\s*>\s*\.wp-block\s*\{[^}]*max-width:\s*var\(--celina-grid-width\);/s',
	'editor wide content width'            => '/\.editor-styles-wrapper\s+\.is-root-container\s*>\s*\.wp-block(?:\.alignwide|\[data-align=["\']wide["\']\])[^,{]*,[^{]*\{[^}]*max-width:\s*var\(--celina-grid-width\);/s',
	'editor full content width'            => '/\.editor-styles-wrapper\s+\.is-root-container\s*>\s*\.wp-block(?:\.alignfull|\[data-align=["\']full["\']\])[^,{]*,[^{]*\{[^}]*max-width:\s*none;/s',
	'editor Hero full width'                => '/\.editor-styles-wrapper\s+\.is-root-container\s*>\s*\.wp-block-celina-hero[^,{]*,[^{]*\{[^}]*max-width:\s*none;/s',
	'editor News Grid full width'           => '/\.editor-styles-wrapper\s+\.is-root-container\s*>\s*\.wp-block-celina-news-grid\s*,[^{]*\{[^}]*max-width:\s*none;/s',
	'404 centered layout'                   => '/\.celina-not-found\s*\{[^}]*text-align:\s*center;/s',
	'404 code treatment'                    => '/\.celina-not-found__code\s*\{[^}]*font-size:\s*clamp\(4rem,\s*14vw,\s*9rem\);[^}]*font-weight:\s*800;/s',
	'404 home button treatment'             => '/\.celina-not-found\s+\.celina-button\s*\{[^}]*display:\s*inline-flex;[^}]*background:\s*var\(--celina-color-citrus\);[^}]*color:\s*var\(--celina-color-text\);[^}]*text-decoration:\s*none;/s',
	'article bounded reading column'         => '/\.celina-single-article\s*\{[^}]*width:\s*min\(100%\s*-\s*2\s*\*\s*var\(--celina-gutter\),\s*42rem\);[^}]*overflow-wrap:\s*anywhere;/s',
	'article metadata wrapping'              => '/\.celina-article-meta\s*\{[^}]*display:\s*flex;[^}]*flex-wrap:\s*wrap;/s',
	'article taxonomy pills'                 => '/\.celina-article-(?:category|tags)\s+a[^,{]*,[^{]*\{[^}]*border-radius:\s*var\(--celina-radius-pill\);/s',
	'article featured media'                 => '/\.celina-article-media\s*\{[^}]*margin:[^;}]+;[^}]*overflow:\s*hidden;/s',
	'article byline layout'                  => '/\.celina-article-byline\s*\{[^}]*display:\s*flex;[^}]*align-items:\s*center;/s',
	'article semantic byline reset'          => '/\.celina-article-byline\s*\{[^}]*font-style:\s*normal;/s',
	'article body headings'                  => '/\.celina-single-article\s+\.celina-entry-content\s*>\s*h2\s*\{[^}]*margin-block:[^;}]+;/s',
	'article body lists'                     => '/\.celina-single-article\s+\.celina-entry-content\s*>\s*:where\(ul,\s*ol\)\s*\{[^}]*padding-inline-start:[^;}]+;/s',
	'article lead treatment'                 => '/\.celina-article-intro\s*\{[^}]*border-inline-start:\s*0\.25rem\s+solid\s+var\(--celina-color-citrus\);/s',
	'article gallery treatment'              => '/\.celina-single-article\s+\.celina-entry-content\s+\.wp-block-gallery\s*\{[^}]*gap:[^;}]+;/s',
	'article caption treatment'              => '/\.celina-single-article\s+figcaption\s*\{[^}]*color:\s*var\(--celina-color-muted\);/s',
	'article pullquote treatment'            => '/\.celina-single-article\s+\.wp-block-pullquote\s*\{[^}]*background:\s*var\(--celina-color-stone-darker\);/s',
	'article table narrow overflow'          => '/\.celina-single-article\s+\.wp-block-table\s*\{[^}]*max-width:\s*100%;[^}]*overflow-x:\s*auto;/s',
	'article citrus focus'                   => '/\.celina-single-article\s+a:focus-visible\s*\{[^}]*outline:\s*3px\s+solid\s+var\(--celina-color-citrus\);/s',
	'article navigation wrapping'            => '/\.celina-article-navigation\s*\{[^}]*display:\s*flex;[^}]*flex-wrap:\s*wrap;/s',
];

if ( false === $content_css ) {
	$failures[] = 'content template stylesheet unreadable';
} else {
	foreach ( $css_contracts as $label => $pattern ) {
		if ( 1 !== preg_match( $pattern, $content_css ) ) {
			$failures[] = 'missing CSS contract ' . $label;
		}
	}
}

do_action( 'wp_enqueue_scripts' );
$styles        = wp_styles();
$content_style = $styles->registered['celina-content-templates'] ?? null;

if ( null === $content_style ) {
	$failures[] = 'content template style missing';
} elseif ( [ 'celina-site-shell' ] !== $content_style->deps ) {
	$failures[] = 'content template style dependency mismatch';
}

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "theme templates contract passed\n";
