<?php
defined( 'ABSPATH' ) || exit;

celina_template_open( '404' );
?>
<section class="celina-template-inner celina-not-found">
	<p class="celina-not-found__code" aria-hidden="true">404</p>
	<h1><?php esc_html_e( 'Page not found', 'celina' ); ?></h1>
	<p><?php esc_html_e( 'The requested page does not exist or has moved.', 'celina' ); ?></p>
	<p><a class="celina-button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Return to the homepage', 'celina' ); ?></a></p>
	<?php get_search_form(); ?>
</section>
<?php
celina_template_close();
