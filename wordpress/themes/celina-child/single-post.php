<?php
defined( 'ABSPATH' ) || exit;

celina_template_open( 'single-post' );

while ( have_posts() ) {
	the_post();
	get_template_part( 'template-parts/content', 'single' );
}

celina_template_close();
