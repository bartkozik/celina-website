<?php
defined( 'ABSPATH' ) || exit;

if ( celina_render_latest_posts_front_page() ) {
	return;
}

celina_template_open( 'front-page' );

while ( have_posts() ) {
	the_post();
	get_template_part( 'template-parts/content', 'page' );
}

celina_template_close();
