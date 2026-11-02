<?php
defined( 'ABSPATH' ) || exit;

$posts_page = (int) get_option( 'page_for_posts' );
$title      = $posts_page ? get_the_title( $posts_page ) : '';
$title      = $title ?: __( 'News', 'celina' );

celina_template_open( 'home' );
if ( ! celina_render_managed_posts_page() ) {
	celina_render_post_archive( (string) $title );
}
celina_template_close();
