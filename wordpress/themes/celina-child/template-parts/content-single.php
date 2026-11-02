<?php
defined( 'ABSPATH' ) || exit;

$article = get_post();

if ( ! $article instanceof WP_Post ) {
	return;
}

$primary_category     = celina_article_primary_category( $article );
$primary_category_url = $primary_category instanceof WP_Term
	? get_term_link( $primary_category )
	: null;
$reading_minutes      = celina_article_reading_minutes( $article->post_content );
$article_body         = get_the_content( null, false, $article );
$queried_article_id   = (int) get_queried_object_id();
$normalize_headings   = ! is_admin() &&
	! is_feed() &&
	is_singular( 'post' ) &&
	is_main_query() &&
	in_the_loop() &&
	$queried_article_id > 0 &&
	get_the_ID() === $queried_article_id &&
	$article->ID === $queried_article_id;

if ( $normalize_headings ) {
	$article_heading_marker = wp_generate_uuid4();
	$article_body           = serialize_blocks(
		celina_article_mark_heading_blocks(
			parse_blocks( $article_body ),
			$article_heading_marker
		)
	);
	$article_heading_render = static function ( string $block_content, array $block ) use ( $article_heading_marker ): string {
		if (
			$article_heading_marker !==
			( $block['attrs']['celinaArticleHeadingScope'] ?? null )
		) {
			return $block_content;
		}

		return celina_article_render_heading_block( $block_content, $block );
	};

	add_filter( 'render_block_core/heading', $article_heading_render, 10, 2 );
}

try {
	$article_body = apply_filters( 'the_content', $article_body );
} finally {
	if ( $normalize_headings ) {
		remove_filter( 'render_block_core/heading', $article_heading_render, 10 );
	}
}

$article_body = str_replace( ']]>', ']]&gt;', $article_body );
$article_tags         = get_the_tags( $article->ID );
$article_tag_links    = [];

if ( is_array( $article_tags ) ) {
	foreach ( $article_tags as $article_tag ) {
		if ( ! $article_tag instanceof WP_Term ) {
			continue;
		}

		$article_tag_url = get_tag_link( $article_tag );

		if (
			is_wp_error( $article_tag_url ) ||
			! is_string( $article_tag_url ) ||
			'' === $article_tag_url
		) {
			continue;
		}

		$article_tag_links[] = [
			'name' => $article_tag->name,
			'url'  => $article_tag_url,
		];
	}
}

$article_author = get_userdata( (int) $article->post_author );
$previous_post  = celina_article_adjacent_post( $article, 'previous' );
$next_post      = celina_article_adjacent_post( $article, 'next' );

if ( ! $previous_post instanceof WP_Post || 'publish' !== $previous_post->post_status ) {
	$previous_post = null;
}
if ( ! $next_post instanceof WP_Post || 'publish' !== $next_post->post_status ) {
	$next_post = null;
}

$previous_url   = $previous_post instanceof WP_Post ? get_permalink( $previous_post ) : false;
$next_url       = $next_post instanceof WP_Post ? get_permalink( $next_post ) : false;
$previous_url   = is_string( $previous_url ) && '' !== $previous_url ? $previous_url : null;
$next_url       = is_string( $next_url ) && '' !== $next_url ? $next_url : null;
$previous_title = $previous_post instanceof WP_Post ? trim( get_the_title( $previous_post ) ) : '';
$next_title     = $next_post instanceof WP_Post ? trim( get_the_title( $next_post ) ) : '';

if ( '' === $previous_title ) {
	$previous_title = __( 'Untitled article', 'celina' );
}
if ( '' === $next_title ) {
	$next_title = __( 'Untitled article', 'celina' );
}
?>
<article id="post-<?php echo esc_attr( (string) $article->ID ); ?>" <?php post_class( 'celina-single-article', $article->ID ); ?>>
	<header class="celina-article-header">
		<?php if ( $primary_category instanceof WP_Term && is_string( $primary_category_url ) && '' !== $primary_category_url ) : ?>
			<p class="celina-article-category">
				<a href="<?php echo esc_url( $primary_category_url ); ?>">
					<?php echo esc_html( $primary_category->name ); ?>
				</a>
			</p>
		<?php endif; ?>

		<h1><?php echo esc_html( get_the_title( $article ) ); ?></h1>

		<div class="celina-article-meta">
			<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $article ) ); ?>">
				<?php echo esc_html( get_the_date( '', $article ) ); ?>
			</time>
			<span class="celina-article-meta__separator" aria-hidden="true">·</span>
			<span class="celina-article-reading-time">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: Estimated reading time in minutes. */
						__( '%d min read', 'celina' ),
						$reading_minutes
					)
				);
				?>
			</span>
		</div>

		<?php if ( get_post_modified_time( 'U', true, $article ) > get_post_time( 'U', true, $article ) ) : ?>
			<p class="celina-article-updated">
				<?php echo esc_html__( 'Updated:', 'celina' ); ?>
				<time datetime="<?php echo esc_attr( get_the_modified_date( DATE_W3C, $article ) ); ?>">
					<?php echo esc_html( get_the_modified_date( '', $article ) ); ?>
				</time>
			</p>
		<?php endif; ?>

		<?php if ( [] !== $article_tag_links ) : ?>
			<nav class="celina-article-tags" aria-label="<?php echo esc_attr__( 'Article tags', 'celina' ); ?>">
				<ul>
					<?php foreach ( $article_tag_links as $article_tag_link ) : ?>
						<li>
							<a href="<?php echo esc_url( $article_tag_link['url'] ); ?>">
								<?php echo esc_html( $article_tag_link['name'] ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endif; ?>
	</header>

	<?php echo celina_article_featured_image( $article ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns strict KSES-safe figure/image markup. ?>

	<?php if ( $article_author instanceof WP_User ) : ?>
		<?php
		$author_name      = $article_author->display_name;
		$author_job_title = (string) get_user_meta(
			$article_author->ID,
			'celina_job_title',
			true
		);
		$author_avatar    = get_avatar(
			$article_author->ID,
			80,
			'',
			$author_name,
			[ 'class' => 'celina-article-byline__avatar-image' ]
		);
		?>
		<address class="celina-article-byline">
			<?php if ( is_string( $author_avatar ) && '' !== $author_avatar ) : ?>
				<div class="celina-article-byline__avatar">
					<?php
					echo wp_kses(
						$author_avatar,
						[
							'img' => [
								'alt'           => true,
								'class'         => true,
								'decoding'      => true,
								'fetchpriority' => true,
								'height'        => true,
								'loading'       => true,
								'sizes'         => true,
								'src'           => true,
								'srcset'        => true,
								'width'         => true,
							],
						]
					);
					?>
				</div>
			<?php endif; ?>
			<div class="celina-article-byline__details">
				<p class="celina-article-byline__name"><?php echo esc_html( $author_name ); ?></p>
				<?php if ( '' !== trim( $author_job_title ) ) : ?>
					<p class="celina-article-byline__job-title">
						<?php echo esc_html( $author_job_title ); ?>
					</p>
				<?php endif; ?>
			</div>
		</address>
	<?php endif; ?>

	<div class="celina-entry-content">
		<?php echo $article_body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Standard the_content pipeline with scoped core Heading normalization. ?>
	</div>

	<?php if ( null !== $previous_url || null !== $next_url ) : ?>
		<nav class="celina-article-navigation" aria-label="<?php echo esc_attr__( 'More articles', 'celina' ); ?>">
			<?php if ( null !== $previous_url && $previous_post instanceof WP_Post ) : ?>
				<a class="celina-article-navigation__previous" href="<?php echo esc_url( $previous_url ); ?>" rel="prev">
					<span class="celina-article-navigation__label">
						<?php echo esc_html__( 'Previous article', 'celina' ); ?>
					</span>
					<span class="celina-article-navigation__title">
						<?php echo esc_html( $previous_title ); ?>
					</span>
				</a>
			<?php endif; ?>

			<?php if ( null !== $next_url && $next_post instanceof WP_Post ) : ?>
				<a class="celina-article-navigation__next" href="<?php echo esc_url( $next_url ); ?>" rel="next">
					<span class="celina-article-navigation__label">
						<?php echo esc_html__( 'Next article', 'celina' ); ?>
					</span>
					<span class="celina-article-navigation__title">
						<?php echo esc_html( $next_title ); ?>
					</span>
				</a>
			<?php endif; ?>
		</nav>
	<?php endif; ?>
</article>
