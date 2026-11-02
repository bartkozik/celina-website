<?php
/** Read-only attachment lookup and native pattern hydration. */
defined( 'ABSPATH' ) || exit;

function celina_media_attachment_map(): array {
	$map = [];
	$ids = get_posts( [ 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_celina_media_source', 'orderby' => 'ID', 'order' => 'ASC' ] );
	foreach ( $ids as $id ) {
		$file = (string) get_post_meta( $id, '_celina_media_source', true );
		$path = get_attached_file( $id );
		$url = wp_get_attachment_url( $id );
		if ( $file && is_string( $path ) && is_file( $path ) && $url && wp_attachment_is_image( $id ) ) {
			$map[ $file ] = [ 'id' => (int) $id, 'url' => $url ];
		}
	}
	return $map;
}

/** Hydrate editor and managed-content patterns, never render-time post content. */
function celina_media_refresh_patterns(): void {
	$map = celina_media_attachment_map();
	if ( ! $map ) { return; }
	$registry = WP_Block_Patterns_Registry::get_instance();
	foreach ( $registry->get_all_registered() as $pattern ) {
		if ( ! str_starts_with( $pattern['name'], 'celina/' ) || empty( $pattern['content'] ) ) { continue; }
		$content = celina_media_transform_content( $pattern['content'], $map );
		if ( $content === $pattern['content'] ) { continue; }
		$name = $pattern['name'];
		unset( $pattern['name'], $pattern['filePath'] );
		$pattern['content'] = $content;
		unregister_block_pattern( $name );
		register_block_pattern( $name, $pattern );
	}
}
add_action( 'init', static function (): void {
	if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) { celina_media_refresh_patterns(); }
}, 30 );
add_action( 'rest_api_init', 'celina_media_refresh_patterns', 30 );
