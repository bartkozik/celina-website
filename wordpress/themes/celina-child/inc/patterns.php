<?php
defined( 'ABSPATH' ) || exit;

function celina_register_pattern_category(): void {
	register_block_pattern_category( 'celina-sales', [ 'label' => __( 'CELINA Sales', 'celina' ) ] );
	register_block_pattern_category( 'celina-redesigned', [ 'label' => __( 'CELINA REDESIGNED', 'celina' ) ] );
	register_block_pattern_category(
		'celina',
		[ 'label' => __( 'CELINA', 'celina' ) ]
	);
}

add_action( 'init', 'celina_register_pattern_category' );

function celina_register_redesigned_pattern_aliases(): void {
	$registry = WP_Block_Patterns_Registry::get_instance();
	$theme    = wp_get_theme();
	$patterns = $theme->get_block_patterns();
	$domain   = $theme->get( 'TextDomain' );
	$suffixes = [
		'facts', 'cta', 'careers', 'contact-heading', 'industry-handoff', 'intro', 'history',
		'page-homepage', 'page-industries', 'page-capabilities', 'page-why-celina',
		'page-about', 'page-contact', 'page-community', 'page-careers',
		'page-corporate-resources', 'page-corporate-news',
	];

	foreach ( $suffixes as $suffix ) {
		$source_name = 'celina/sales-' . $suffix;
		$alias_name  = 'celina/redesigned-' . $suffix;
		$source      = $patterns[ 'sales-' . $suffix . '.php' ] ?? null;
		$file_path   = get_stylesheet_directory() . '/patterns/sales-' . $suffix . '.php';
		if ( ! $source || ! $registry->is_registered( $source_name ) || ! is_readable( $file_path ) || $registry->is_registered( $alias_name ) ) {
			continue;
		}

		// Match core's translated metadata, then let filePath hydrate content lazily.
		$alias = $source;
		unset( $alias['slug'] );
		$source_title = translate_with_gettext_context( $source['title'], 'Pattern title', $domain );
		$alias['title']      = str_replace( 'CELINA Sales', 'CELINA REDESIGNED', $source_title );
		if ( ! empty( $source['description'] ) ) {
			$alias['description'] = translate_with_gettext_context( $source['description'], 'Pattern description', $domain );
		}
		$alias['categories'] = [ 'celina-redesigned' ];
		$alias['filePath']   = $file_path;
		register_block_pattern( $alias_name, $alias );
	}
}

// Core registers theme pattern files on init at priority 10.
add_action( 'init', 'celina_register_redesigned_pattern_aliases', 20 );
