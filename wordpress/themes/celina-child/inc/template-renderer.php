<?php
defined( 'ABSPATH' ) || exit;

function celina_is_owned_template_route(): bool {
	return is_front_page() || is_home() || is_category() || is_page() || is_singular( 'post' ) || is_404();
}

function celina_template_sidebar_layout( string $layout ): string {
	if ( celina_is_owned_template_route() ) {
		return 'no-sidebar';
	}

	return $layout;
}

add_filter( 'generate_sidebar_layout', 'celina_template_sidebar_layout', 20 );

function celina_template_body_class( array $classes ): array {
	if (
		celina_is_owned_template_route() &&
		! in_array( 'celina-template-full-width', $classes, true )
	) {
		$classes[] = 'celina-template-full-width';
	}

	return $classes;
}

add_filter( 'body_class', 'celina_template_body_class', 20 );

function celina_is_latest_posts_front_page(): bool {
	return 'posts' === get_option( 'show_on_front' ) && is_front_page() && is_home();
}

function celina_resolve_latest_posts_front_page_template(): string {
	if ( ! celina_is_latest_posts_front_page() ) {
		return '';
	}

	$template = locate_template( [ 'home.php', 'index.php' ], false, false );

	return is_string( $template ) ? $template : '';
}

function celina_render_latest_posts_front_page( ?callable $loader = null ): bool {
	if ( ! celina_is_latest_posts_front_page() ) {
		return false;
	}

	$template = celina_resolve_latest_posts_front_page_template();

	if ( '' !== $template ) {
		if ( null === $loader ) {
			load_template( $template );
		} else {
			$loader( $template );
		}
	}

	return true;
}

function celina_first_meaningful_content_block( string $content ): ?array {
	foreach ( parse_blocks( $content ) as $block ) {
		if ( null !== ( $block['blockName'] ?? null ) ) {
			return $block;
		}

		$freeform_without_comments = preg_replace(
			'/<!--.*?-->/s',
			'',
			(string) ( $block['innerHTML'] ?? '' )
		);

		if ( null === $freeform_without_comments || '' !== trim( $freeform_without_comments ) ) {
			return $block;
		}
	}

	return null;
}

function celina_normalize_visible_text( string $text ): string {
	$stripped = wp_strip_all_tags( $text );
	$charset  = get_bloginfo( 'charset' );
	$charset  = is_string( $charset ) && '' !== trim( $charset ) ? $charset : 'UTF-8';

	try {
		$decoded = html_entity_decode( $stripped, ENT_QUOTES | ENT_HTML5, $charset );
	} catch ( ValueError $error ) {
		$decoded = html_entity_decode( $stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	$normalized = preg_replace( '/\p{Z}+/u', ' ', $decoded );

	if ( null === $normalized ) {
		$decoded    = html_entity_decode( $stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$normalized = preg_replace( '/\p{Z}+/u', ' ', $decoded );
	}

	$normalized = is_string( $normalized ) ? $normalized : $decoded;
	$normalized = preg_replace( '/[\x{200B}\x{FEFF}]+/u', '', $normalized );

	return trim( is_string( $normalized ) ? $normalized : '' );
}

function celina_has_visible_text( string $text ): bool {
	return '' !== celina_normalize_visible_text( $text );
}

function celina_page_should_show_title( string $content ): bool {
	$first_block = celina_first_meaningful_content_block( $content );
	// Explicit page patterns own their visible H1, including the compact contact header.
	if ( 'core/group' === ( $first_block['blockName'] ?? '' ) && in_array( 'celina-sales-page', explode( ' ', $first_block['attrs']['className'] ?? '' ), true ) ) {
		$has_heading = static function ( array $blocks ) use ( &$has_heading ): bool {
			foreach ( $blocks as $block ) {
				if ( 'celina/hero' === $block['blockName'] && celina_has_visible_text( $block['attrs']['heading'] ?? '' ) ) {
					return true;
				}
				if ( 'core/heading' === $block['blockName'] && 1 === ( $block['attrs']['level'] ?? 2 ) && celina_has_visible_text( $block['innerHTML'] ) ) {
					return true;
				}
				if ( $has_heading( $block['innerBlocks'] ) ) {
					return true;
				}
			}
			return false;
		};
		return ! $has_heading( $first_block['innerBlocks'] );
	}

	if ( 'celina/hero' !== ( $first_block['blockName'] ?? '' ) ) {
		return true;
	}

	$heading = $first_block['attrs']['heading'] ?? '';

	return ! is_string( $heading ) || ! celina_has_visible_text( $heading );
}

function celina_filter_empty_hero_heading( string $block_content, array $block ): string {
	$heading = $block['attrs']['heading'] ?? '';

	if ( is_string( $heading ) && celina_has_visible_text( $heading ) ) {
		if ( ! is_singular( 'post' ) ) {
			return $block_content;
		}

		$article_hero_heading = preg_replace(
			'/<h1\b([^>]*class=(["\'])[^"\']*\bcelina-hero__heading\b[^"\']*\2[^>]*)>(.*?)<\/h1>/is',
			'<h2$1>$3</h2>',
			$block_content,
			1
		);

		return is_string( $article_hero_heading ) ? $article_hero_heading : $block_content;
	}

	$without_empty_heading = preg_replace(
		'/<h1\b[^>]*class=(["\'])[^"\']*\bcelina-hero__heading\b[^"\']*\1[^>]*>.*?<\/h1>/is',
		'',
		$block_content,
		1
	);

	return is_string( $without_empty_heading ) ? $without_empty_heading : $block_content;
}

add_filter( 'render_block_celina/hero', 'celina_filter_empty_hero_heading', 10, 2 );

function celina_resolve_managed_posts_page(): ?WP_Post {
	$posts_page_id = (int) get_option( 'page_for_posts' );

	if ( $posts_page_id <= 0 ) {
		return null;
	}

	$posts_page = get_post( $posts_page_id );

	if (
		! $posts_page instanceof WP_Post ||
		'page' !== $posts_page->post_type ||
		'publish' !== $posts_page->post_status ||
		'page.corporate-news' !== get_post_meta(
			$posts_page_id,
			'_celina_blueprint_key',
			true
		)
	) {
		return null;
	}

	return $posts_page;
}

function celina_render_managed_posts_page(): bool {
	$posts_page = celina_resolve_managed_posts_page();

	if ( ! $posts_page instanceof WP_Post ) {
		return false;
	}

	$content = apply_filters( 'the_content', $posts_page->post_content );
	?>
	<article class="celina-managed-posts-page">
		<div class="celina-entry-content">
			<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Standard the_content pipeline. ?>
		</div>
	</article>
	<?php

	return true;
}

function celina_find_content_block( array $blocks, string $block_name ): ?array {
	foreach ( $blocks as $block ) {
		if ( $block_name === ( $block['blockName'] ?? null ) ) {
			return $block;
		}

		$inner_blocks = $block['innerBlocks'] ?? [];
		if ( is_array( $inner_blocks ) && [] !== $inner_blocks ) {
			$match = celina_find_content_block( $inner_blocks, $block_name );

			if ( null !== $match ) {
				return $match;
			}
		}
	}

	return null;
}

function celina_managed_news_index_attributes(): ?array {
	$posts_page = celina_resolve_managed_posts_page();

	if ( ! $posts_page instanceof WP_Post ) {
		return null;
	}

	$block = celina_find_content_block(
		parse_blocks( $posts_page->post_content ),
		'celina/news-grid'
	);
	$attrs = $block['attrs'] ?? null;

	return is_array( $attrs ) && 'news-index' === ( $attrs['layout'] ?? '' )
		? $attrs
		: null;
}

function celina_prepare_managed_news_query( WP_Query $query ): void {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_home() ) {
		return;
	}

	$attributes = celina_managed_news_index_attributes();

	if ( null === $attributes ) {
		return;
	}

	if (
		! function_exists( 'celina_news_grid_attributes' ) ||
		! function_exists( 'celina_news_grid_featured_post' )
	) {
		return;
	}

	$config       = celina_news_grid_attributes( $attributes );
	$featured     = celina_news_grid_featured_post( $config );
	$feature_id   = $featured instanceof WP_Post ? $featured->ID : 0;

	$query->set( 'posts_per_page', 6 );
	$query->set( 'post_status', 'publish' );
	$query->set( 'ignore_sticky_posts', true );
	$query->set( 'orderby', [ 'date' => 'DESC', 'ID' => 'DESC' ] );
	$query->set( 'cat', $config['category'] );
	$query->set( 'post__not_in', $feature_id > 0 ? [ $feature_id ] : [] );
}

add_action( 'pre_get_posts', 'celina_prepare_managed_news_query' );

function celina_enqueue_managed_news_archive_styles(): void {
	if (
		! is_category() ||
		null === celina_managed_news_index_attributes() ||
		! function_exists( 'celina_news_grid_attributes' ) ||
		! function_exists( 'celina_news_grid_render_navigation' ) ||
		! function_exists( 'celina_news_grid_render_card' )
	) {
		return;
	}

	$block = WP_Block_Type_Registry::get_instance()->get_registered( 'celina/news-grid' );
	if ( ! $block instanceof WP_Block_Type ) {
		return;
	}

	foreach ( $block->style_handles as $style_handle ) {
		wp_enqueue_style( $style_handle );
	}
}

add_action( 'wp_enqueue_scripts', 'celina_enqueue_managed_news_archive_styles', 30 );

function celina_render_post_archive( string $title, string $description = '' ): void {
	$is_category_archive = is_category();
	$current_category_id = $is_category_archive ? (int) get_queried_object_id() : 0;
	$attributes          = $is_category_archive
		? celina_managed_news_index_attributes()
		: null;
	$config              = null;

	if (
		is_array( $attributes ) &&
		function_exists( 'celina_news_grid_attributes' ) &&
		function_exists( 'celina_news_grid_render_navigation' ) &&
		function_exists( 'celina_news_grid_render_card' )
	) {
		$config = celina_news_grid_attributes( $attributes );
	}

	$card_config = $config;
	if ( is_array( $card_config ) ) {
		$card_config['headingLevel'] = 2;
	}

	?>
	<header class="celina-template-inner celina-archive-header">
		<h1><?php echo esc_html( $title ); ?></h1>
		<?php if ( '' !== $description ) : ?>
			<div class="celina-archive-description"><?php echo wp_kses_post( $description ); ?></div>
		<?php endif; ?>
	</header>
	<?php if ( is_array( $config ) ) : ?>
		<div class="celina-news-grid celina-news-grid--news-index">
			<?php echo celina_news_grid_render_navigation( $config, $current_category_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plugin helper returns escaped markup. ?>
	<?php endif; ?>
	<?php if ( have_posts() ) : ?>
		<div class="<?php echo esc_attr( is_array( $config ) ? 'celina-news-grid__items' : 'celina-template-inner celina-card-grid' ); ?>">
			<?php
			while ( have_posts() ) {
				the_post();

				if ( is_array( $card_config ) ) {
					echo celina_news_grid_render_card( get_post(), 'news-index', $card_config ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plugin helper returns escaped markup.
				} else {
					get_template_part( 'template-parts/content', 'card' );
				}
			}
			?>
		</div>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<p class="celina-template-inner"><?php esc_html_e( 'No published articles were found.', 'celina' ); ?></p>
	<?php endif; ?>
	<?php if ( is_array( $config ) ) : ?>
		</div>
	<?php endif; ?>
	<?php
}

function celina_template_open( string $template ): void {
	$allowed  = [ 'front-page', 'page', 'home', 'category', 'single-post', '404' ];
	$template = in_array( $template, $allowed, true ) ? $template : 'page';

	get_header();
	?>
	<div <?php generate_do_attr( 'content' ); ?>>
		<main <?php generate_do_attr( 'main' ); ?> data-celina-template="<?php echo esc_attr( $template ); ?>">
			<?php do_action( 'generate_before_main_content' ); ?>
	<?php
}

function celina_template_close(): void {
	?>
			<?php do_action( 'generate_after_main_content' ); ?>
		</main>
	</div>
	<?php
	do_action( 'generate_after_primary_content_area' );
	generate_construct_sidebars();
	get_footer();
}
