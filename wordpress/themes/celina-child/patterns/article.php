<?php
/**
 * Title: CELINA article
 * Slug: celina/article
 * Categories: celina
 * Description: Flexible CELINA news article body with editorial starter blocks.
 * Post Types: post
 */
?>
<!-- wp:celina/article-layout -->
<div class="wp-block-celina-article-layout celina-article-layout">
<!-- wp:paragraph {"className":"celina-article-layout__lead"} -->
<p class="celina-article-layout__lead">Article introduction. Summarize the announcement and why it matters.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Article section heading</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Develop the story with the essential details.</p>
<!-- /wp:paragraph -->

<!-- wp:image {"sizeSlug":"large"} -->
<figure class="wp-block-image size-large"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/article-placeholder.svg' ) ); ?>" alt="Article image placeholder"/><figcaption class="wp-element-caption">Image caption.</figcaption></figure>
<!-- /wp:image -->

<!-- wp:pullquote -->
<figure class="wp-block-pullquote"><blockquote><p>Pull quote.</p><cite>Quote source</cite></blockquote></figure>
<!-- /wp:pullquote -->

<!-- wp:paragraph -->
<p>Article conclusion. Add the next step or key takeaway.</p>
<!-- /wp:paragraph -->

<!-- wp:celina/media-contact -->
<aside class="wp-block-celina-media-contact celina-media-contact"><h2 class="celina-media-contact__heading">Media Contact</h2><address class="celina-media-contact__details"></address></aside>
<!-- /wp:celina/media-contact -->
</div>
<!-- /wp:celina/article-layout -->
