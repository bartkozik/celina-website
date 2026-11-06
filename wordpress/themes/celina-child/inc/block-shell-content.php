<?php
defined( 'ABSPATH' ) || exit;

function celina_shell_block( string $name, array $attrs = [], array $children = [], string $before = '', string $after = '' ): array {
	$names = [ 'celina-block-utility' => 'Utility bar', 'celina-block-header' => 'Header layout', 'celina-block-header-actions' => 'Navigation and buttons', 'celina-block-menu' => 'Mobile menu disclosure', 'celina-block-primary-panel' => 'Mobile menu panel', 'celina-block-header-cta' => 'Header button', 'celina-block-mobile-cta' => 'Mobile menu button', 'celina-block-footer' => 'Footer layout', 'celina-block-footer-brand' => 'Footer brand', 'celina-block-footer-links' => 'Footer links', 'celina-block-footer-contact' => 'Footer contact' ];
	foreach ( explode( ' ', $attrs['className'] ?? '' ) as $class ) {
		if ( isset( $names[ $class ] ) ) { $attrs['metadata']['name'] = $names[ $class ]; }
	}
	$content = $children ? array_merge( [ $before ], array_fill( 0, count( $children ), null ), [ $after ] ) : [ $before . $after ];
	return [ 'blockName' => 'core/' . $name, 'attrs' => $attrs, 'innerBlocks' => $children, 'innerHTML' => $before . $after, 'innerContent' => $content ];
}

function celina_shell_group( string $class, array $children, string $tag = 'div' ): array {
	if ( str_contains( $class, 'celina-block-shell ' ) && celina_sales_review_enabled() ) { $class .= ' celina-block-sales-review'; }
	$attrs = [ 'className' => $class, 'layout' => [ 'type' => 'default' ] ];
	if ( 'div' !== $tag ) { $attrs['tagName'] = $tag; }
	return celina_shell_block( 'group', $attrs, $children, '<' . $tag . ' class="wp-block-group ' . esc_attr( $class ) . '">', '</' . $tag . '>' );
}

function celina_shell_text( string $html, string $class = '', bool $heading = false ): array {
	$tag = $heading ? 'h2' : 'p';
	$classes = trim( ( $heading ? 'wp-block-heading ' : '' ) . $class );
	return celina_shell_block( $heading ? 'heading' : 'paragraph', $class ? [ 'className' => $class ] : [], [], '<' . $tag . ( $classes ? ' class="' . esc_attr( $classes ) . '"' : '' ) . '>' . $html . '</' . $tag . '>' );
}

function celina_shell_link( string $label, string $url ): string {
	return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
}

function celina_block_shell_menu_items( string $location ): array {
	$locations = get_nav_menu_locations();
	if ( empty( $locations[ $location ] ) ) {
		return celina_sales_review_fallback_items( celina_default_menu_items( $location ), $location );
	}
	$items = wp_get_nav_menu_items( (int) $locations[ $location ] );
	if ( ! $items ) { return celina_sales_review_fallback_items( celina_default_menu_items( $location ), $location ); }
	$args = (object) [ 'theme_location' => $location ];
	$items = apply_filters( 'wp_nav_menu_objects', $items, $args );
	$walk = static function ( int $parent, array $ancestors = [] ) use ( &$walk, $items, $args ): array {
		$result = [];
		foreach ( $items as $item ) {
			if ( (int) $item->menu_item_parent !== $parent || in_array( (int) $item->ID, $ancestors, true ) ) { continue; }
			$result[] = [
				'label' => wp_strip_all_tags( apply_filters( 'nav_menu_item_title', $item->title, $item, $args, count( $ancestors ) ) ),
				'url' => $item->url, 'target' => $item->target, 'rel' => $item->xfn,
				'kind' => 'post_type' === $item->type ? 'post-type' : ( 'taxonomy' === $item->type ? 'taxonomy' : 'custom' ),
				'type' => $item->object, 'id' => (int) $item->object_id,
				'children' => $walk( (int) $item->ID, array_merge( $ancestors, [ (int) $item->ID ] ) ),
			];
		}
		return $result;
	};
	$result = $walk( 0 );
	if ( celina_sales_review_enabled() && in_array( $location, [ 'celina-primary', 'celina-footer' ], true ) ) {
		$result[] = [ 'label' => 'News & Media', 'url' => home_url( '/news/' ) ];
	}
	return $result;
}

function celina_shell_navigation( string $location, string $class, string $label ): array {
	$walk = static function ( array $items, int $depth ) use ( &$walk ): array {
		return array_map( static function ( array $item ) use ( $walk, $depth ): array {
			$children = $depth > 1 ? $walk( $item['children'] ?? [], $depth - 1 ) : [];
			$attrs = [ 'label' => esc_html( $item['label'] ), 'url' => esc_url_raw( $item['url'] ), 'kind' => $item['kind'] ?? 'custom' ];
			if ( 'custom' !== $attrs['kind'] ) { $attrs['type'] = $item['type']; $attrs['id'] = $item['id']; }
			if ( '_blank' === ( $item['target'] ?? '' ) ) { $attrs['opensInNewTab'] = true; }
			if ( ! empty( $item['rel'] ) ) { $attrs['rel'] = sanitize_text_field( $item['rel'] ); }
			return celina_shell_block( $children ? 'navigation-submenu' : 'navigation-link', $attrs, $children );
		}, $items );
	};
	$children = $walk( celina_block_shell_menu_items( $location ), 'celina-primary' === $location ? 2 : 1 );
	// Core Navigation falls back to page-list for zero children. An empty group must not do that.
	if ( ! $children ) { return celina_shell_group( $class, [] ); }
	return celina_shell_block( 'navigation', [ 'className' => $class, 'ariaLabel' => $label, 'overlayMenu' => 'never', 'showSubmenuIcon' => false, 'layout' => [ 'type' => 'flex', 'orientation' => 'celina-footer' === $location ? 'vertical' : 'horizontal' ] ], $children );
}

function celina_block_shell_content( string $part ): string {
	$phone = sanitize_text_field( (string) get_theme_mod( 'celina_phone', '(202) 555-0100' ) );
	$email = sanitize_email( (string) get_theme_mod( 'celina_email', 'sales@example.invalid' ) );
	$contacts = [ celina_shell_text( celina_shell_link( $phone, 'tel:' . preg_replace( '/[^0-9+]/', '', $phone ) ) ), celina_shell_text( celina_shell_link( $email, 'mailto:' . $email ) ) ];
	if ( 'header' === $part ) {
		$logo = has_custom_logo() ? celina_shell_block( 'site-logo', [ 'width' => 190, 'className' => 'celina-block-header-logo' ] ) : celina_shell_text( celina_shell_link( get_bloginfo( 'name' ), home_url( '/' ) ), 'celina-block-header-logo' );
		$button = celina_shell_block( 'button', [], [], '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url( home_url( '/contact/' ) ) . '">' . esc_html( celina_sales_review_enabled() ? 'Discuss Project' : 'Contact Us' ) . '</a></div>' );
		$cta = celina_shell_block( 'buttons', [ 'className' => 'celina-block-header-cta' ], [ $button ], '<div class="wp-block-buttons celina-block-header-cta">', '</div>' );
		$panel = celina_shell_group( 'celina-block-primary-panel', [ celina_shell_navigation( 'celina-primary', 'celina-block-primary', 'Primary navigation' ) ] );
		$menu = celina_shell_block( 'details', [ 'showContent' => true, 'className' => 'celina-block-menu' ], [ $panel ], '<details class="wp-block-details celina-block-menu" open><summary>Navigation</summary>', '</details>' );
		$utility = celina_shell_group( 'celina-block-shell celina-block-utility', [ celina_shell_group( 'celina-block-inner', [ celina_shell_group( 'celina-block-contacts', $contacts ), celina_shell_navigation( 'celina-utility', 'celina-block-utility-nav', 'Utility navigation' ) ] ) ] );
		$root = celina_shell_group( 'celina-block-shell celina-block-header', [
			celina_shell_group( 'celina-block-inner celina-block-header-main', [ $logo, celina_shell_group( 'celina-block-header-actions', [ $menu, $cta ] ) ] ),
		], 'header' );
		return serialize_blocks( [ $utility, $root ] );
	} else {
		$tagline = esc_html( sanitize_textarea_field( (string) get_theme_mod( 'celina_footer_tagline', 'CELINA manufactures and distributes innovative fabric solutions for military, industrial, and commercial markets worldwide.' ) ) );
		if ( celina_sales_review_enabled() ) { $tagline = wp_kses_post( celina_sales_review_statement_emphasis( $tagline ) ); }
		$logo = celina_shell_block( 'image', [ 'sizeSlug' => 'full', 'linkDestination' => 'custom', 'className' => 'celina-block-footer-logo' ], [], '<figure class="wp-block-image size-full celina-block-footer-logo"><a href="' . esc_url( home_url( '/' ) ) . '"><img src="' . esc_url( get_stylesheet_directory_uri() . '/assets/images/celina-logo-white.png' ) . '" alt="CELINA"/></a></figure>' );
		$year = celina_shell_text( '©', 'celina-block-copyright-year' );
		$year['attrs']['metadata'] = [ 'bindings' => [ 'content' => [ 'source' => 'celina/copyright-year' ] ] ];
		$meta = [ celina_shell_group( 'celina-block-copyright', [ $year, celina_shell_text( 'CELINA. All rights reserved.' ) ] ) ];
		if ( celina_block_shell_menu_items( 'celina-legal' ) ) { $meta[] = celina_shell_navigation( 'celina-legal', 'celina-block-legal', 'Legal' ); }
		$root = celina_shell_group( 'celina-block-shell celina-block-footer', [ celina_shell_group( 'celina-block-inner', [
			celina_shell_group( 'celina-block-footer-grid', [
				celina_shell_group( 'celina-block-footer-brand', [ $logo, celina_shell_text( $tagline, 'celina-block-footer-tagline' ) ] ),
				celina_shell_group( 'celina-block-footer-contact', [ celina_shell_text( 'Contact', '', true ), celina_shell_group( 'celina-block-footer-contact-details', array_merge( [ celina_shell_text( nl2br( esc_html( sanitize_textarea_field( (string) get_theme_mod( 'celina_address', "123 Example Street\nCelina, OH 00000 USA" ) ) ) ) ) ], $contacts, [ celina_shell_text( celina_shell_link( 'Contact form', home_url( '/contact/' ) ), 'celina-block-footer-contact-link' ) ] ), 'address' ) ] ),
				celina_shell_group( 'celina-block-footer-links', [ celina_shell_text( celina_sales_review_enabled() ? 'Explore CELINA' : 'Our Sites', '', true ), celina_shell_navigation( 'celina-footer', 'celina-block-footer-nav', 'Footer sites' ) ] ),
			] ),
			celina_shell_group( 'celina-block-footer-meta', $meta ),
		] ) ], 'footer' );
	}
	return serialize_block( $root );
}

function celina_block_shell_register_content(): void {
	register_block_bindings_source( 'celina/copyright-year', [ 'label' => __( 'Current copyright year', 'celina' ), 'get_value_callback' => static function () { return '© ' . wp_date( 'Y' ); } ] );
	foreach ( [ 'header', 'footer' ] as $part ) {
		register_block_pattern( 'celina/global-' . $part, [ 'title' => 'CELINA ' . ucfirst( $part ), 'content' => celina_block_shell_content( $part ), 'inserter' => false ] );
	}
}
add_action( 'init', 'celina_block_shell_register_content' );

function celina_block_shell_navigation_state( string $html, array $block ): string {
	$classes = explode( ' ', $block['attrs']['className'] ?? '' );
	if ( ! array_intersect( $classes, [ 'celina-block-primary', 'celina-block-footer-nav', 'celina-block-utility-nav', 'celina-block-legal', 'celina-block-social' ] ) ) { return $html; }
	$processor = new WP_HTML_Tag_Processor( $html );
	while ( $processor->next_tag( 'A' ) ) {
		$url = $processor->get_attribute( 'href' );
		if ( ! is_string( $url ) ) { continue; }
		$state = celina_sales_review_nav_state( $url );
		if ( ! $state && is_singular() && untrailingslashit( $url ) === untrailingslashit( (string) get_permalink( get_queried_object_id() ) ) ) { $state = 'page'; }
		if ( $state ) { $processor->set_attribute( 'aria-current', $state ); }
	}
	return $processor->get_updated_html();
}
add_filter( 'render_block_core/navigation', 'celina_block_shell_navigation_state', 10, 2 );

function celina_block_shell_mobile_cta( array $block ): array {
	if ( 'core/group' !== $block['blockName'] || ! in_array( 'celina-block-header', explode( ' ', $block['attrs']['className'] ?? '' ), true ) ) { return $block; }
	$matches = [ 'celina-block-header-cta' => [], 'celina-block-primary-panel' => [], 'celina-block-mobile-cta' => [] ];
	$find = static function ( array $node, array $path = [] ) use ( &$find, &$matches ): void {
		foreach ( explode( ' ', $node['attrs']['className'] ?? '' ) as $class ) {
			if ( isset( $matches[ $class ] ) ) { $matches[ $class ][] = $path; }
		}
		foreach ( $node['innerBlocks'] as $index => $child ) { $find( $child, array_merge( $path, [ $index ] ) ); }
	};
	$find( $block );
	// Old compositions may contain intentionally different mobile content. Never discard it.
	if ( $matches['celina-block-mobile-cta'] || 1 !== count( $matches['celina-block-header-cta'] ) || 1 !== count( $matches['celina-block-primary-panel'] ) ) { return $block; }
	$source_path = $matches['celina-block-header-cta'][0];
	$panel_path = $matches['celina-block-primary-panel'][0];
	if ( array_slice( $source_path, 0, count( $panel_path ) ) === $panel_path ) { return $block; }
	$source = $block;
	foreach ( $source_path as $index ) { $source = $source['innerBlocks'][ $index ]; }
	$panel = &$block;
	foreach ( $panel_path as $index ) { $panel = &$panel['innerBlocks'][ $index ]; }
	if ( 'core/buttons' !== $source['blockName'] || 'core/group' !== $panel['blockName'] || ! $panel['innerContent'] || ! is_string( end( $panel['innerContent'] ) ) ) { return $block; }
	$copy = static function ( array $node, bool $root = false ) use ( &$copy ): array {
		unset( $node['attrs']['anchor'] );
		if ( $root ) {
			$classes = explode( ' ', $node['attrs']['className'] );
			$node['attrs']['className'] = implode( ' ', array_map( static function ( $class ) { return 'celina-block-header-cta' === $class ? 'celina-block-mobile-cta' : $class; }, $classes ) );
		}
		$rewrite = static function ( string $html ) use ( $root ): string {
			$tags = new WP_HTML_Tag_Processor( $html );
			while ( $tags->next_tag() ) {
				$tags->remove_attribute( 'id' );
				if ( $root && $tags->has_class( 'celina-block-header-cta' ) ) {
					$tags->remove_class( 'celina-block-header-cta' );
					$tags->add_class( 'celina-block-mobile-cta' );
				}
			}
			return $tags->get_updated_html();
		};
		$node['innerHTML'] = $rewrite( $node['innerHTML'] );
		$node['innerContent'] = array_map( static function ( $chunk ) use ( $rewrite ) { return is_string( $chunk ) ? $rewrite( $chunk ) : $chunk; }, $node['innerContent'] );
		$node['innerBlocks'] = array_map( $copy, $node['innerBlocks'] );
		return $node;
	};
	$panel['innerBlocks'][] = $copy( $source, true );
	array_splice( $panel['innerContent'], count( $panel['innerContent'] ) - 1, 0, [ null ] );
	return $block;
}
add_filter( 'render_block_data', 'celina_block_shell_mobile_cta' );
