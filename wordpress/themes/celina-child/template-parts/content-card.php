<?php
defined( 'ABSPATH' ) || exit;
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'celina-article-card' ); ?>>
	<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
	<h2><a href="<?php echo esc_url( get_permalink() ); ?>"><?php echo esc_html( get_the_title() ); ?></a></h2>
	<?php if ( has_excerpt() ) : ?>
		<p><?php echo esc_html( get_the_excerpt() ); ?></p>
	<?php endif; ?>
</article>
