<?php

declare( strict_types=1 );

$root         = dirname( __DIR__, 2 );
$helper_path  = $root . '/wordpress/themes/celina-child/inc/article-meta.php';
$template_path = $root . '/wordpress/themes/celina-child/template-parts/content-single.php';
$helper       = is_readable( $helper_path ) ? file_get_contents( $helper_path ) : false;
$template     = is_readable( $template_path ) ? file_get_contents( $template_path ) : false;
$failures     = [];

if ( false === $helper ) {
	$failures[] = 'missing article helper source';
} else {
	foreach (
		[
			'function celina_wordpress_sample_post_id(): int',
			'function celina_exclude_wordpress_sample_post( WP_Query $query ): void',
			"add_action( 'pre_get_posts', 'celina_exclude_wordpress_sample_post' );",
			'function celina_article_adjacent_post( WP_Post $post, string $direction ): ?WP_Post',
			'Welcome to WordPress. This is your first post. Edit or delete it, then start writing!',
		] as $marker
	) {
		if ( false === strpos( $helper, $marker ) ) {
			$failures[] = 'missing article sample exclusion marker ' . $marker;
		}
	}
}

if ( false === $template ) {
	$failures[] = 'missing single article template source';
} else {
	if ( 2 !== substr_count( $template, 'celina_article_adjacent_post( $article,' ) ) {
		$failures[] = 'single article navigation must resolve both links through the filtered adjacent-post helper';
	}
	if ( false !== strpos( $template, 'get_previous_post()' ) || false !== strpos( $template, 'get_next_post()' ) ) {
		$failures[] = 'single article navigation must not expose unfiltered WordPress adjacent posts';
	}
}

if ( [] !== $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "Article source contract passed\n";
