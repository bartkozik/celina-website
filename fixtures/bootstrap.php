<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

if ( ! function_exists( 'celina_fixture_post_statuses' ) ) {
	function celina_fixture_post_statuses() {
		return [
			'publish',
			'future',
			'draft',
			'pending',
			'private',
			'trash',
			'auto-draft',
			'inherit',
		];
	}
}

if ( ! function_exists( 'celina_fixture_post_id' ) ) {
	function celina_fixture_post_id( $post_type, $post_name ) {
		$post_ids = get_posts(
			[
				'fields'           => 'ids',
				'name'             => $post_name,
				'numberposts'      => 1,
				'order'            => 'ASC',
				'orderby'          => 'ID',
				'post_status'      => celina_fixture_post_statuses(),
				'post_type'        => $post_type,
				'suppress_filters' => true,
			]
		);

		return empty( $post_ids ) ? 0 : (int) $post_ids[0];
	}
}

if ( ! function_exists( 'celina_fixture_error' ) ) {
	function celina_fixture_error( $post, $result ) {
		$label = sprintf( '%s %s', $post['post_type'], $post['post_name'] );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error(
				sprintf(
					'Could not seed fixture %1$s: %2$s',
					$label,
					$result->get_error_message()
				)
			);
		}

		if ( 0 === $result ) {
			WP_CLI::error( sprintf( 'Could not seed fixture %s.', $label ) );
		}
	}
}

if ( ! function_exists( 'celina_upsert_fixture_post' ) ) {
	function celina_upsert_fixture_post( $post ) {
		$post_id = celina_fixture_post_id( $post['post_type'], $post['post_name'] );
		$result  = $post_id
			? wp_update_post( array_merge( $post, [ 'ID' => $post_id ] ), true )
			: wp_insert_post( $post, true );

		celina_fixture_error( $post, $result );

		if (
			$post['post_name'] !== get_post_field( 'post_name', $result ) ||
			$post['post_type'] !== get_post_field( 'post_type', $result )
		) {
			WP_CLI::error(
				sprintf(
					'Could not seed fixture %1$s %2$s with its exact type and slug.',
					$post['post_type'],
					$post['post_name']
				)
			);
		}

		return (int) $result;
	}
}

if ( ! function_exists( 'celina_fixture_pattern_content' ) ) {
	function celina_fixture_pattern_content( $filename ) {
		$relative_path = 'patterns/' . $filename . '.php';
		$path          = function_exists( 'get_theme_file_path' )
			? get_theme_file_path( $relative_path )
			: dirname( __DIR__ ) . '/wordpress/themes/celina-child/' . $relative_path;
		$source = is_readable( $path ) ? file_get_contents( $path ) : false;

		if ( false === $source ) {
			WP_CLI::error( sprintf( 'Could not load fixture pattern %s.', $filename ) );
		}

		$header_end = strpos( $source, '?>' );
		if ( false === $header_end ) {
			WP_CLI::error( sprintf( 'Fixture pattern %s has no metadata boundary.', $filename ) );
		}

		return trim( substr( $source, $header_end + 2 ) );
	}
}

if ( ! function_exists( 'celina_fixture_category_id_by_definition' ) ) {
	function celina_fixture_category_id_by_definition( $name, $slug, $parent = 0 ) {
		$category = get_term_by( 'slug', $slug, 'category' );

		if ( $category ) {
			if ( $name !== $category->name || (int) $parent !== (int) $category->parent ) {
				WP_CLI::error(
					sprintf(
						'Synthetic category slug %s is already used by another category.',
						$slug
					)
				);
			}

			return (int) $category->term_id;
		}

		$result = wp_insert_term(
			$name,
			'category',
			[
				'parent' => (int) $parent,
				'slug'   => $slug,
			]
		);

		if ( is_wp_error( $result ) ) {
			WP_CLI::error(
				sprintf(
					'Could not seed the synthetic %1$s category: %2$s',
					$name,
					$result->get_error_message()
				)
			);
		}

		if ( empty( $result['term_id'] ) ) {
			WP_CLI::error( sprintf( 'Could not seed the synthetic %s category.', $name ) );
		}

		return (int) $result['term_id'];
	}
}

if ( ! function_exists( 'celina_fixture_category_id' ) ) {
	function celina_fixture_category_id() {
		return celina_fixture_category_id_by_definition(
			'Fixture News',
			'fixture-news'
		);
	}
}

if ( ! function_exists( 'celina_fixture_editorial_category_ids' ) ) {
	/**
	 * Seed Manufacturing first to test navigation order independently of term IDs.
	 * @return array<string, int>
	 */
	function celina_fixture_editorial_category_ids( $parent ) {
		$manufacturing = celina_fixture_category_id_by_definition(
			'Manufacturing',
			'manufacturing',
			$parent
		);
		$company_updates = celina_fixture_category_id_by_definition(
			'Company Updates',
			'company-updates',
			$parent
		);
		$community = celina_fixture_category_id_by_definition(
			'Community',
			'community',
			$parent
		);

		return [
			'company-updates' => $company_updates,
			'manufacturing'   => $manufacturing,
			'community'       => $community,
		];
	}
}

if ( ! function_exists( 'celina_fixture_featured_image_upload' ) ) {
	/** @return array{file: string, url: string} */
	function celina_fixture_featured_image_upload() {
		$image_bytes = base64_decode(
			'iVBORw0KGgoAAAANSUhEUgAAABAAAAAJCAIAAAC0SDtlAAAAFElEQVR42mP4lKFEEmIY1TAoNAAAnWHVwcfuGpgAAAAASUVORK5CYII=',
			true
		);
		if ( false === $image_bytes ) {
			WP_CLI::error( 'Could not decode the synthetic fixture image.' );
		}

		$upload = wp_upload_dir( '2026-08-01 12:00:00', false, true );
		if ( ! empty( $upload['error'] ) ) {
			WP_CLI::error(
				sprintf(
					'Could not resolve the synthetic fixture image upload directory: %s',
					$upload['error']
				)
			);
		}
		if (
			empty( $upload['path'] ) ||
			! is_string( $upload['path'] ) ||
			empty( $upload['url'] ) ||
			! is_string( $upload['url'] ) ||
			empty( $upload['basedir'] ) ||
			! is_string( $upload['basedir'] ) ||
			empty( $upload['baseurl'] ) ||
			! is_string( $upload['baseurl'] )
		) {
			WP_CLI::error( 'Could not resolve the synthetic fixture image upload path and URL.' );
		}

		$upload_path    = rtrim( wp_normalize_path( $upload['path'] ), '/' );
		$upload_basedir = rtrim( wp_normalize_path( $upload['basedir'] ), '/' );
		$upload_url     = rtrim( $upload['url'], '/' );
		$upload_baseurl = rtrim( $upload['baseurl'], '/' );
		if (
			'' === $upload_path ||
			'.' === $upload_path ||
			'/' === $upload_path ||
			'' === $upload_basedir ||
			'.' === $upload_basedir ||
			'/' === $upload_basedir ||
			1 === preg_match( '#(?:^|/)\.\.(?:/|$)#', $upload_path ) ||
			(
				$upload_basedir !== $upload_path &&
				0 !== strpos( $upload_path, trailingslashit( $upload_basedir ) )
			) ||
			(
				$upload_baseurl !== $upload_url &&
				0 !== strpos( $upload_url, trailingslashit( $upload_baseurl ) )
			)
		) {
			WP_CLI::error( 'Could not validate the synthetic fixture image upload path.' );
		}
		if ( ! wp_mkdir_p( $upload_path ) || ! is_dir( $upload_path ) ) {
			WP_CLI::error( 'Could not create the synthetic fixture image upload directory.' );
		}

		$real_upload_path    = realpath( $upload_path );
		$real_upload_basedir = realpath( $upload_basedir );
		if ( false === $real_upload_path || false === $real_upload_basedir ) {
			WP_CLI::error( 'Could not resolve the synthetic fixture image upload directory.' );
		}

		$real_upload_path    = rtrim( wp_normalize_path( $real_upload_path ), '/' );
		$real_upload_basedir = rtrim( wp_normalize_path( $real_upload_basedir ), '/' );
		if (
			$real_upload_basedir !== $real_upload_path &&
			0 !== strpos( $real_upload_path, trailingslashit( $real_upload_basedir ) )
		) {
			WP_CLI::error( 'Could not validate the resolved synthetic fixture image upload directory.' );
		}

		$filename    = 'fixture-news-featured.png';
		$file        = trailingslashit( $real_upload_path ) . $filename;
		$url         = trailingslashit( $upload_url ) . $filename;
		$temp_prefix = '.celina-fixture-news-featured-';
		if (
			$real_upload_path !== wp_normalize_path( dirname( $file ) ) ||
			$filename !== basename( $file ) ||
			$filename !== basename( (string) wp_parse_url( $url, PHP_URL_PATH ) )
		) {
			WP_CLI::error( 'Could not validate the synthetic fixture image path and URL.' );
		}
		if ( is_link( $file ) ) {
			WP_CLI::error( 'Synthetic fixture image target must not be a symbolic link.' );
		}
		if ( file_exists( $file ) && ! is_file( $file ) ) {
			WP_CLI::error( 'Synthetic fixture image target must be a regular file.' );
		}

		$temp_file = @tempnam( $real_upload_path, $temp_prefix );
		$temp_path = is_string( $temp_file ) ? wp_normalize_path( $temp_file ) : '';
		$temp_owned =
			'' !== $temp_path &&
			$real_upload_path === wp_normalize_path( dirname( $temp_path ) ) &&
			0 === strpos( basename( $temp_path ), $temp_prefix );
		$cleanup_temp = static function () use ( &$temp_file, $temp_owned ) {
			if (
				$temp_owned &&
				is_string( $temp_file ) &&
				( is_file( $temp_file ) || is_link( $temp_file ) )
			) {
				return @unlink( $temp_file );
			}

			return true;
		};
		$fail_with_cleanup = static function ( $message ) use ( $cleanup_temp ) {
			if ( ! $cleanup_temp() ) {
				$message .= ' Could not remove the exact temporary fixture image file.';
			}

			WP_CLI::error( $message );
		};

		if (
			! $temp_owned ||
			! is_string( $temp_file ) ||
			is_link( $temp_file ) ||
			! is_file( $temp_file )
		) {
			$fail_with_cleanup( 'Could not create a safe temporary synthetic fixture image.' );
		}

		$bytes_written = @file_put_contents( $temp_file, $image_bytes, LOCK_EX );
		if ( false === $bytes_written || strlen( $image_bytes ) !== $bytes_written ) {
			$fail_with_cleanup( 'Could not write the temporary synthetic fixture image.' );
		}

		$directory_stat = @stat( $real_upload_path );
		if ( is_array( $directory_stat ) && isset( $directory_stat['mode'] ) ) {
			@chmod( $temp_file, $directory_stat['mode'] & 0000666 );
		}

		clearstatcache( true, $file );
		if ( is_link( $file ) ) {
			$fail_with_cleanup( 'Synthetic fixture image target became a symbolic link before replacement.' );
		}
		if ( file_exists( $file ) && ! is_file( $file ) ) {
			$fail_with_cleanup( 'Synthetic fixture image target became a non-regular file before replacement.' );
		}
		if ( ! @rename( $temp_file, $file ) ) {
			$fail_with_cleanup( 'Could not atomically replace the synthetic fixture image.' );
		}

		$temp_file = '';
		clearstatcache( true, $file );
		if ( is_link( $file ) || ! is_file( $file ) || strlen( $image_bytes ) !== filesize( $file ) ) {
			WP_CLI::error( 'Could not verify the atomically replaced synthetic fixture image.' );
		}

		return [
			'file' => $file,
			'url'  => $url,
		];
	}
}

if ( ! function_exists( 'celina_fixture_menu_id' ) ) {
	function celina_fixture_menu_id() {
		$menu = wp_get_nav_menu_object( 'CELINA Fixture Navigation' );

		if ( $menu ) {
			return (int) $menu->term_id;
		}

		$menu_id = wp_create_nav_menu( 'CELINA Fixture Navigation' );

		if ( is_wp_error( $menu_id ) || ! $menu_id ) {
			WP_CLI::error( 'Could not seed the synthetic fixture navigation menu.' );
		}

		return (int) $menu_id;
	}
}

if ( ! function_exists( 'celina_upsert_fixture_menu_item' ) ) {
	function celina_upsert_fixture_menu_item( $menu_id, $title, $url, $legacy_urls = [] ) {
		$matching_items = [];
		$items          = wp_get_nav_menu_items( $menu_id, [ 'post_status' => 'any' ] );
		$owned_urls     = array_merge( [ $url ], $legacy_urls );
		$owned_paths    = array_map(
			static function ( $owned_url ) {
				return wp_parse_url( $owned_url, PHP_URL_PATH );
			},
			$owned_urls
		);

		foreach ( is_array( $items ) ? $items : [] as $item ) {
			$item_path = wp_parse_url( $item->url, PHP_URL_PATH );
			if (
				in_array( $item->url, $owned_urls, true ) ||
				( $title === $item->title && in_array( $item_path, $owned_paths, true ) )
			) {
				$matching_items[] = $item;
			}
		}

		$item_id = 0;
		foreach ( $matching_items as $item ) {
			if ( $url === $item->url ) {
				$item_id = (int) $item->ID;
				break;
			}
		}
		if ( ! $item_id && ! empty( $matching_items ) ) {
			$item_id = (int) $matching_items[0]->ID;
		}

		$result  = wp_update_nav_menu_item(
			$menu_id,
			$item_id,
			[
				'menu-item-status' => 'publish',
				'menu-item-title'  => $title,
				'menu-item-type'   => 'custom',
				'menu-item-url'    => $url,
			]
		);

		if ( is_wp_error( $result ) || ! $result ) {
			WP_CLI::error( 'Could not seed a synthetic fixture navigation item.' );
		}

		foreach ( $matching_items as $duplicate ) {
			if ( (int) $duplicate->ID !== $item_id ) {
				wp_delete_post( $duplicate->ID, true );
			}
		}
	}
}

update_option( 'blogname', 'CELINA Workbench' );
update_option( 'celina_fixture_version', '5' );

$fixture_home_content = '<!-- wp:celina/hero {"eyebrow":"Synthetic fixture","heading":"Fixture Home","description":"Editable CELINA homepage starter."} -->'
	. '<section class="wp-block-celina-hero celina-hero celina-hero--standard"><div class="celina-hero__inner"><p class="celina-hero__eyebrow">Synthetic fixture</p><h1 class="celina-hero__heading">Fixture Home</h1><p class="celina-hero__description">Editable CELINA homepage starter.</p></div></section>'
	. '<!-- /wp:celina/hero -->'
	. '<!-- wp:celina/faq --><section class="wp-block-celina-faq celina-faq">'
	. '<!-- wp:celina/faq-item {"question":"Fixture question?","answer":"<p>Fixture answer.</p>"} -->'
	. '<details class="wp-block-celina-faq-item celina-faq-item"><summary class="celina-faq-item__question">Fixture question?</summary><div class="celina-faq-item__answer"><p>Fixture answer.</p></div></details>'
	. '<!-- /wp:celina/faq-item --></section><!-- /wp:celina/faq -->'
	. '<!-- wp:celina/news-grid {"heading":"Fixture News","count":3} /-->'
	. '<!-- wp:celina/hubspot-form /-->'
	. '<!-- wp:celina/paycor-jobs {"clientId":""} /-->';

$fixture_about_content = '<!-- wp:celina/hero {"eyebrow":"Synthetic fixture","heading":"Fixture About","description":"Editable standard-page fixture."} -->'
	. '<section class="wp-block-celina-hero celina-hero celina-hero--standard"><div class="celina-hero__inner"><p class="celina-hero__eyebrow">Synthetic fixture</p><h1 class="celina-hero__heading">Fixture About</h1><p class="celina-hero__description">Editable standard-page fixture.</p></div></section>'
	. '<!-- /wp:celina/hero -->'
	. '<!-- wp:paragraph --><p>Synthetic CELINA page content.</p><!-- /wp:paragraph -->';

$fixture_form_presets_content = '';
foreach (
	[
		'Request a Callback',
		'Ask the CELINA Team',
		'Request a Quote',
		'Ask a Product or Technical Question',
	] as $heading
) {
	$fixture_form_presets_content .= '<!-- wp:celina/hubspot-form ' . json_encode(
		[
			'heading'      => $heading,
			'region'       => 'na1',
			'portalId'     => 'portfolio-unconfigured',
			'formId'       => '',
			'fallbackText' => 'If the form is unavailable, email sales@example.invalid or call (202) 555-0100.',
		]
	) . ' /-->';
}

$fixture_article_content = '<!-- wp:celina/article-layout -->'
	. '<div class="wp-block-celina-article-layout celina-article-layout">'
	. '<!-- wp:paragraph {"className":"celina-article-layout__lead"} --><p class="celina-article-layout__lead">Synthetic article introduction.</p><!-- /wp:paragraph -->'
	. '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Fixture article details</h1><!-- /wp:heading -->'
	. '<!-- wp:paragraph --><p>CELINA teams share a concise manufacturing and community update for this deterministic local fixture.</p><!-- /wp:paragraph -->'
	. '<!-- wp:pullquote --><figure class="wp-block-pullquote"><blockquote><p>Synthetic pull quote.</p><cite>Fixture source</cite></blockquote></figure><!-- /wp:pullquote -->'
	. '<!-- wp:paragraph --><p>Synthetic article conclusion.</p><!-- /wp:paragraph -->'
	. '</div><!-- /wp:celina/article-layout -->';

$fixture_home_id = celina_upsert_fixture_post(
	[
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Fixture Home',
		'post_name'    => 'fixture-home',
		'post_content' => $fixture_home_content,
	]
);

$fixture_news_id = celina_upsert_fixture_post(
	[
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Fixture News',
		'post_name'    => 'fixture-news',
		'post_content' => '',
	]
);

update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $fixture_home_id );
update_option( 'page_for_posts', $fixture_news_id );

celina_upsert_fixture_post(
	[
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Fixture About',
		'post_name'    => 'fixture-about',
		'post_content' => $fixture_about_content,
	]
);

celina_upsert_fixture_post(
	[
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Fixture Form Presets',
		'post_name'    => 'fixture-form-patterns',
		'post_content' => $fixture_form_presets_content,
	]
);

$fixture_news_category_id = celina_fixture_category_id();
$fixture_editorial_category_ids = celina_fixture_editorial_category_ids(
	$fixture_news_category_id
);
$fixture_news_author = get_user_by( 'login', 'admin' );

if ( ! $fixture_news_author instanceof WP_User ) {
	WP_CLI::error( 'Could not resolve the synthetic fixture author.' );
}

$fixture_author_update = wp_update_user(
	[
		'ID'           => $fixture_news_author->ID,
		'display_name' => 'CELINA Editor',
	]
);
if ( is_wp_error( $fixture_author_update ) ) {
	WP_CLI::error(
		sprintf(
			'Could not update the synthetic fixture author: %s',
			$fixture_author_update->get_error_message()
		)
	);
}

update_user_meta(
	$fixture_news_author->ID,
	'celina_job_title',
	'Corporate Communications Manager'
);

$fixture_featured_image_upload = celina_fixture_featured_image_upload();
$fixture_featured_image_id     = celina_upsert_fixture_post(
	[
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'post_title'     => 'Synthetic CELINA manufacturing news',
		'post_name'      => 'fixture-news-featured',
		'post_mime_type' => 'image/png',
		'guid'           => $fixture_featured_image_upload['url'],
	]
);
update_attached_file(
	$fixture_featured_image_id,
	$fixture_featured_image_upload['file']
);
wp_update_attachment_metadata(
	$fixture_featured_image_id,
	[
		'file'   => _wp_relative_upload_path( $fixture_featured_image_upload['file'] ),
		'height' => 9,
		'sizes'  => [],
		'width'  => 16,
	]
);
update_post_meta(
	$fixture_featured_image_id,
	'_wp_attachment_image_alt',
	'Synthetic CELINA manufacturing news'
);

$fixture_post_categories = [
	1 => [
		$fixture_news_category_id,
		$fixture_editorial_category_ids['manufacturing'],
		$fixture_editorial_category_ids['company-updates'],
	],
	2 => [ $fixture_news_category_id, $fixture_editorial_category_ids['manufacturing'] ],
	3 => [ $fixture_news_category_id, $fixture_editorial_category_ids['community'] ],
	4 => [ $fixture_news_category_id, $fixture_editorial_category_ids['company-updates'] ],
	5 => [ $fixture_news_category_id, $fixture_editorial_category_ids['manufacturing'] ],
	6 => [ $fixture_news_category_id, $fixture_editorial_category_ids['community'] ],
	7 => [ $fixture_news_category_id, $fixture_editorial_category_ids['company-updates'] ],
	8 => [ $fixture_news_category_id, $fixture_editorial_category_ids['manufacturing'] ],
];

for ( $index = 1; $index <= 8; $index++ ) {
	$fixture_post_id = celina_upsert_fixture_post(
		[
			'post_type'     => 'post',
			'post_status'   => 'publish',
			'post_title'    => 'Fixture News ' . $index,
			'post_name'     => 'fixture-news-' . $index,
			'post_excerpt'  => 'Synthetic excerpt ' . $index . '.',
			'post_content'  => $fixture_article_content,
			'post_author'   => $fixture_news_author->ID,
			'post_category' => $fixture_post_categories[ $index ],
			'post_date'     => sprintf( '2026-08-%02d 12:00:00', $index ),
			'post_date_gmt' => sprintf( '2026-08-%02d 12:00:00', $index ),
		]
	);

	wp_set_post_tags(
		$fixture_post_id,
		[ 'Fixture Editorial', 'CELINA News' ],
		false
	);

	if ( 2 === $index ) {
		delete_post_thumbnail( $fixture_post_id );
	} else {
		set_post_thumbnail( $fixture_post_id, $fixture_featured_image_id );
	}
}

$fixture_navigation_category_ids = array_values(
	$fixture_editorial_category_ids
);

$fixture_news_content = '<!-- wp:celina/hero {"eyebrow":"Synthetic fixture","heading":"Fixture News","description":"A managed Corporate News page powered by WordPress posts."} -->'
	. '<section class="wp-block-celina-hero celina-hero celina-hero--standard"><div class="celina-hero__inner"><p class="celina-hero__eyebrow">Synthetic fixture</p><h1 class="celina-hero__heading">Fixture News</h1><p class="celina-hero__description">A managed Corporate News page powered by WordPress posts.</p></div></section>'
	. '<!-- /wp:celina/hero -->'
	. '<!-- wp:celina/news-grid {"heading":"Corporate News","category":' . $fixture_news_category_id . ',"count":6,"layout":"news-index","postId":0,"pagination":false,"navigationCategories":' . wp_json_encode( $fixture_navigation_category_ids ) . '} /-->'
	. '<!-- wp:celina/media-contact {"eyebrow":"Press enquiries","heading":"Media Contact","name":"Fixture Communications","role":"Synthetic contact","email":"contact@example.invalid","phone":"+1 (419) 555-0123"} -->'
	. '<aside class="wp-block-celina-media-contact celina-media-contact"><p class="celina-media-contact__eyebrow">Press enquiries</p><h2 class="celina-media-contact__heading">Media Contact</h2><address class="celina-media-contact__details"><strong class="celina-media-contact__name">Fixture Communications</strong><span class="celina-media-contact__role">Synthetic contact</span><a href="mailto:contact@example.invalid">contact@example.invalid</a><a href="tel:+14195550123">+1 (419) 555-0123</a></address></aside>'
	. '<!-- /wp:celina/media-contact -->';

wp_update_post(
	[
		'ID'           => $fixture_news_id,
		'post_content' => $fixture_news_content,
	]
);
update_post_meta(
	$fixture_news_id,
	'_celina_blueprint_key',
	'page.corporate-news'
);

$fixture_menu_id = celina_fixture_menu_id();

celina_upsert_fixture_menu_item( $fixture_menu_id, 'Fixture Home', home_url( '/fixture-home/' ) );
celina_upsert_fixture_menu_item(
	$fixture_menu_id,
	'Fixture News',
	home_url( '/fixture-news/' ),
	[ home_url( '/fixture-news-1/' ) ]
);

$menu_locations = get_nav_menu_locations();

foreach ( [ 'celina-utility', 'celina-primary', 'celina-footer', 'celina-legal', 'celina-social' ] as $location ) {
	$menu_locations[ $location ] = $fixture_menu_id;
}

set_theme_mod( 'nav_menu_locations', $menu_locations );

if ( '/%postname%/' !== get_option( 'permalink_structure' ) ) {
	global $wp_rewrite;
	$wp_rewrite->set_permalink_structure( '/%postname%/' );
	flush_rewrite_rules();
}
