<?php
defined( 'ABSPATH' ) || exit;

$content    = get_the_content( null, false );
$show_title = celina_page_should_show_title( $content );
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'celina-page' ); ?>>
	<?php if ( $show_title ) : ?>
		<header class="celina-template-inner celina-page-header">
			<h1><?php the_title(); ?></h1>
		</header>
	<?php endif; ?>
	<div class="celina-entry-content">
		<?php the_content(); ?>
	</div>
</article>
