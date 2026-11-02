<?php
defined( 'ABSPATH' ) || exit;

celina_template_open( 'page' );

while ( have_posts() ) {
	the_post();
	get_template_part( 'template-parts/content', 'page' );
}

celina_template_close();
