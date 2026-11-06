<?php
defined( 'ABSPATH' ) || exit;

function celina_redesigned_post_states( array $states, WP_Post $post ): array {
	if ( 'page' === $post->post_type && (int) get_post_meta( $post->ID, '_celina_redesigned_version', true ) > 0 ) {
		$states['celina-redesigned'] = __( '(redesigned)', 'celina' );
	}
	return $states;
}
add_filter( 'display_post_states', 'celina_redesigned_post_states', 10, 2 );
