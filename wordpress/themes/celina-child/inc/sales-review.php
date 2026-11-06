<?php
defined( 'ABSPATH' ) || exit;

function celina_sales_review_enabled(): bool {
	return (bool) get_theme_mod( 'celina_sales_review', false );
}

/** Explicit editor compositions own their copy; the review overlay must not rewrite them. */
function celina_sales_review_native_page(): bool {
	$content = get_post_field( 'post_content', get_queried_object_id() );
	return is_string( $content ) && false !== strpos( $content, 'celina-sales-page' );
}

function celina_sales_review_customize( WP_Customize_Manager $customizer ): void {
	$customizer->add_section( 'celina_sales_review', [
		'panel' => 'celina_site_settings',
		'title' => __( 'UX review variant', 'celina' ),
		'description' => __( 'Preview the sales-focused layout. Disable to restore the original pages and navigation. Saved page content and HubSpot settings are not changed. Clear page caches after publishing either choice.', 'celina' ),
	] );
	$customizer->add_setting( 'celina_sales_review', [
		'default' => false,
		'sanitize_callback' => 'rest_sanitize_boolean',
	] );
	$customizer->add_control( 'celina_sales_review', [
		'section' => 'celina_sales_review',
		'label' => __( 'Enable UX review variant', 'celina' ),
		'type' => 'checkbox',
	] );
}
add_action( 'customize_register', 'celina_sales_review_customize', 30 );

function celina_sales_review_intro( string $html, array $block ): string {
	if ( ! celina_sales_review_enabled() || is_admin() || is_feed() || celina_sales_review_native_page() || ! is_page() ) {
		return $html;
	}
	$headings = [
		'page.markets' => 'Specialized Expertise. Shared Standards.',
		'page.capabilities' => 'Expertise That Works Together.',
		'page.why-celina' => 'Confidence in Every Partnership.',
		'page.community' => 'A Lasting Commitment to Community.',
		'page.corporate-resources' => 'Company Information, Within Reach.',
	];
	$key = get_post_meta( get_queried_object_id(), '_celina_blueprint_key', true );
	$copy = $block['attrs']['content'] ?? '';
	if ( ! isset( $headings[ $key ] ) || ! empty( $block['attrs']['heading'] ) || ! preg_match( '/^<p>([^<]+)<\/p>$/s', trim( $copy ), $match ) ) {
		return $html;
	}
	$sentences = explode( '. ', $match[1], 2 );
	if ( 2 !== count( $sentences ) ) {
		return $html;
	}
	$intro = '<h2 class="celina-review-intro-title">' . esc_html( $headings[ $key ] ) . '</h2><p class="celina-review-intro-lead">' . $sentences[0] . '.</p><p>' . $sentences[1] . '</p>';
	return str_replace( $copy, $intro, $html );
}
add_filter( 'render_block_celina/intro-band', 'celina_sales_review_intro', 10, 2 );

function celina_sales_review_hero_emphasis( string $html ): string {
	if ( ! celina_sales_review_enabled() || is_admin() || is_feed() || celina_sales_review_native_page() || ( ! is_page() && ! is_home() ) ) {
		return $html;
	}
	$phrases = [
		'page.homepage' => [ 'fabric and shelter solutions', 'military, industrial, event and promotional' ],
		'page.markets' => [ 'military operations', 'industrial products', 'world-class events', 'your brand' ],
		'page.capabilities' => [ 'engineering expertise', 'manufacturing excellence', 'quality systems' ],
		'page.why-celina' => [ 'quality, integrity, innovation', 'long-term partnerships' ],
		'page.about' => [ 'fabric, tent, and shelter solutions', 'quality, integrity, and service' ],
		'page.careers' => [ 'rewarding careers', 'every role contributes' ],
		'page.community' => [ 'strong communities', 'next generation' ],
		'page.corporate-resources' => [ 'corporate documents', 'certifications', 'compliance information' ],
		'page.corporate-news' => [ 'company announcements', 'manufacturing investments', 'organizational milestones' ],
	];
	$key = get_post_meta( get_queried_object_id(), '_celina_blueprint_key', true );
	if ( ! isset( $phrases[ $key ] ) ) {
		return $html;
	}
	return preg_replace_callback( '/(<p class="celina-hero__description">)([^<]*)(<\/p>)/s', static function ( array $match ) use ( $phrases, $key ): string {
		$text = $match[2];
		foreach ( $phrases[ $key ] as $phrase ) {
			$text = str_replace( $phrase, '<strong>' . $phrase . '</strong>', $text );
		}
		return $match[1] . $text . $match[3];
	}, $html ) ?? $html;
}
add_filter( 'render_block_celina/hero', 'celina_sales_review_hero_emphasis' );

function celina_sales_review_statement_emphasis( string $text ): string {
	$core = [
		'fabric, tent and shelter solutions', 'innovative fabric solutions',
		'rapidly deployable shelter systems', 'commercial-grade tents',
		'custom tents, flags, banners', 'durable fabric products', 'tent and shelter products',
		'custom shelter manufacturing', 'custom manufacturing', 'tarpaulins, covers',
		'engineering, manufacturing and customer support', 'engineering expertise',
		'engineering, manufacturing', 'in-house engineering', 'engineering challenges',
		'ISO 9001:2015 certified quality management system', 'consistent quality',
		'American manufacturing', 'advanced manufacturing', 'manufacturing careers',
		'quality, engineering excellence', 'practical, manufacturable solutions',
		'program management', 'product development', 'welding, sewing, cutting, printing',
		'responsive service', 'long-term partnerships', 'long-term partnership',
		'flexibility to adapt', 'continuous improvement', 'customer relationships',
		'military, industrial, and commercial markets', 'government and military programs',
		'military operations and humanitarian missions',
	];
	$fallback = [ 'workforce development', 'hands-on experience', 'professional development',
		'charitable organizations', 'community engagement', 'educational collaboration',
		'corporate resources', 'long-term value' ];
	$selected = [];
	foreach ( [ $core, $fallback ] as $tier ) {
		foreach ( $tier as $phrase ) {
			if ( false !== strpos( $text, $phrase ) ) {
				if ( ! array_filter( $selected, static fn( string $existing ): bool => false !== strpos( $existing, $phrase ) ) ) {
					$selected[] = $phrase;
				}
			}
			if ( 2 === count( $selected ) ) { break; }
		}
		if ( $selected ) { break; }
	}
	if ( ! $selected ) { return $text; }
	$pattern = '/\\b(' . implode( '|', array_map( static fn( string $phrase ): string => preg_quote( $phrase, '/' ), $selected ) ) . ')\\b/';
	return preg_replace( $pattern, '<strong class="celina-review-keyphrase">$1</strong>', $text, 2 ) ?? $text;
}

function celina_sales_review_statements( string $html, array $block ): string {
	if ( ! celina_sales_review_enabled() || is_admin() || is_feed() || celina_sales_review_native_page() || ! is_page() ) {
		return $html;
	}
	$types = [ 'celina/text-image-split', 'celina/industry-card', 'celina/capability-card', 'celina/achievement' ];
	if ( ! in_array( $block['blockName'] ?? '', $types, true ) ) {
		return $html;
	}
	return preg_replace_callback( '/(<p(?:\s[^>]*)?>)([^<]{120,})(<\/p>)/s', static function ( array $match ): string {
		return $match[1] . celina_sales_review_statement_emphasis( $match[2] ) . $match[3];
	}, $html ) ?? $html;
}
add_filter( 'render_block', 'celina_sales_review_statements', 20, 2 );

function celina_sales_review_cta( string $heading = 'Tell Us About Your Project' ): string {
	return '<section class="celina-review-cta"><div class="celina-review-inner"><h2>' . esc_html( $heading ) . '</h2><p class="celina-review-pre-cta">Looking for tents, shelters or custom fabric manufacturing?</p><p>Tell us what you need and start a conversation with our team.</p><a class="celina-button celina-button--primary" href="' . esc_url( home_url( '/contact/' ) ) . '">Discuss Your Project</a></div></section>';
}

function celina_sales_review_facts(): string {
	$facts = [
		[ 'Since 1996', 'Manufacturing experience', '<rect x="3" y="5" width="18" height="16" rx="1"/><path d="M7 3v4m10-4v4M3 11h18m-13 5 3 3 5-5"/>' ],
		[ 'Custom Manufacturing', 'Fabric, tent & shelter solutions', '<path d="M3 21V10l6 3V8l6 4V3h4l2 18H3Zm4-4h1m4 0h1m4 0h1"/>' ],
		[ 'ISO 9001:2015', 'Certified quality management system', '<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/>' ],
	];
	$html = '<section class="celina-review-facts celina-review-inner" aria-label="CELINA at a glance">';
	foreach ( $facts as [ $title, $description, $icon ] ) {
		$html .= '<div class="celina-review-fact"><span class="celina-review-fact-icon"><svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $icon . '</svg></span><p><strong>' . esc_html( $title ) . '</strong>' . esc_html( $description ) . '</p></div>';
	}
	return $html . '</section>';
}

function celina_sales_review_replace( array $block, string $old, string $new ): array {
	if ( '' === $old ) {
		return $block;
	}
	$walk = static function ( $value ) use ( &$walk, $old, $new ) {
		if ( is_array( $value ) ) {
			return array_map( $walk, $value );
		}
		return is_string( $value ) ? str_replace( $old, $new, $value ) : $value;
	};
	return $walk( $block );
}

function celina_sales_review_hero( array $block, bool $home, bool $show_capabilities = true ): string {
	if ( $home ) {
		$block = celina_sales_review_replace( $block, $block['attrs']['heading'] ?? '', 'Tents, Shelters & Custom Fabric Manufacturing' );
		$block = celina_sales_review_replace( $block, $block['attrs']['description'] ?? '', 'Engineered fabric and shelter solutions for military, industrial, event and promotional projects. Explore our capabilities or tell us what you need.' );
	}
	$markup = $block['innerHTML'];
	$actions = '<div class="celina-hero__actions"><a class="celina-button" href="' . esc_url( home_url( '/contact/' ) ) . '">Discuss Your Project</a>';
	if ( $home || $show_capabilities ) {
		$actions .= '<a class="celina-button" href="' . esc_url( home_url( $home ? '/industries/' : '/capabilities/' ) ) . '">' . ( $home ? 'Explore Industries' : 'Explore Capabilities' ) . '</a>';
	}
	$actions .= '</div>';
	$markup = preg_replace( '/<div class="celina-hero__actions">.*?<\/div>/s', $actions, $markup, 1 );
	if ( is_string( $markup ) ) {
		$block['innerHTML'] = $markup;
		$block['innerContent'] = [ $markup ];
	}
	return serialize_block( $block );
}

function celina_sales_review_content( string $content, string $key ): string {
	$allowed = [ 'page.homepage', 'page.markets', 'page.about', 'page.contact', 'page.capabilities', 'page.why-celina' ];
	if ( ! celina_sales_review_enabled() || false !== strpos( $content, 'celina-sales-page' ) || ! in_array( $key, $allowed, true ) ) {
		return $content;
	}
	$blocks = parse_blocks( $content );
	$hero = celina_first_meaningful_content_block( $content );
	if ( 'celina/hero' !== ( $hero['blockName'] ?? '' ) || ( 'page.contact' === $key && null === celina_find_content_block( $blocks, 'celina/hubspot-form' ) ) ) {
		return $content;
	}
	$result = '';
	$deferred = '';
	$history = '';
	$locations = '';
	foreach ( $blocks as $block ) {
		$name = $block['blockName'] ?? '';
		$attrs = $block['attrs'] ?? [];
		if ( 'celina/hero' === $name ) {
			if ( 'page.contact' === $key ) {
				$result .= '<header class="celina-review-contact-heading celina-review-inner"><p class="celina-review-eyebrow">Discuss Your Project</p><h1>Tell Us About Your Project</h1><p>Describe what you need. If you know your intended use, quantity or timing, include those details. You can also contact us directly by phone or email.</p></header>';
			} else {
				$result .= celina_sales_review_hero( $block, 'page.homepage' === $key, 'page.capabilities' !== $key );
			}
			continue;
		}
		if ( 'celina/intro-band' === $name && in_array( $key, [ 'page.homepage', 'page.about', 'page.contact' ], true ) ) {
			if ( 'page.contact' !== $key ) {
				$result .= celina_sales_review_facts();
			}
			continue;
		}
		if ( 'page.homepage' === $key && 'celina/text-image-split' === $name ) {
			if ( 'Careers' === ( $attrs['eyebrow'] ?? '' ) ) {
				$deferred .= '<aside class="celina-review-inner celina-review-careers"><div><h2>Build Your Career at CELINA</h2><p>Explore opportunities to join our team.</p></div><a href="' . esc_url( home_url( '/careers/' ) ) . '">Explore careers</a></aside>';
				continue;
			}
			if ( 'Why CELINA' === ( $attrs['eyebrow'] ?? '' ) ) {
				$block['attrs']['content'] = '<p>Family owned since 1996, CELINA brings engineering, manufacturing and customer support together to serve military, industrial, commercial and promotional projects.</p>';
				$deferred .= serialize_block( $block );
				continue;
			}
		}
		if ( 'page.homepage' === $key && 'celina/news-grid' === $name ) {
			$deferred .= serialize_block( $block );
			continue;
		}
		if ( 'page.about' === $key && 'celina/text-image-split' === $name && 'Who We Are' === ( $attrs['eyebrow'] ?? '' ) ) {
			$block['attrs']['heading'] = 'One Company. Four Specialized Industries.';
			$block['attrs']['content'] = '<p>CELINA designs and manufactures fabric, tent and shelter solutions for military, industrial, event and promotional customers. Our headquarters in Celina, Ohio brings together engineering, manufacturing, warehousing and customer support.</p><p>Family owned since 1996, we combine practical manufacturing experience with an ISO 9001:2015 certified quality management system.</p>';
		}
		if ( 'page.capabilities' === $key && 'celina/text-image-split' === $name && 'Our Footprint' === ( $attrs['eyebrow'] ?? '' ) && 'Contact Our Team' === ( $attrs['buttonLabel'] ?? '' ) && in_array( $attrs['buttonUrl'] ?? '', [ '/contact/', home_url( '/contact/' ) ], true ) ) {
			$block['attrs']['buttonLabel'] = 'Discuss Your Project';
		}
		if ( 'page.about' === $key && 'celina/achievements-timeline' === $name && 'timeline' === ( $attrs['variant'] ?? '' ) ) {
			$history = '<section class="celina-review-history celina-review-inner"><h2>Three Decades of Manufacturing</h2><p>From a family-owned rental business in 1996, through manufacturing and government programs, to four specialized industries today.</p><details><summary>Explore the full CELINA history</summary>' . serialize_block( $block ) . '</details></section>';
			continue;
		}
		if ( 'page.contact' === $key && 'celina/industry-cards' === $name ) {
			continue;
		}
		if ( 'page.contact' === $key && 'celina/facility-cards' === $name ) {
			$locations .= serialize_block( $block );
			continue;
		}
		if ( 'celina/cta-band' === $name ) {
			$result .= celina_sales_review_cta();
			continue;
		}
		$result .= serialize_block( $block );
		if ( 'page.about' === $key && false !== strpos( $attrs['className'] ?? '', 'celina-leadership' ) ) {
			$result .= $history;
			$history = '';
		}
		if ( 'celina/industry-cards' === $name && in_array( $key, [ 'page.homepage', 'page.markets' ], true ) ) {
			$result .= '<div class="celina-review-industry-action celina-review-inner"><p class="celina-review-pre-cta">Have a project in mind?</p><p>Start with our team. The industry links above open our specialist websites.</p><a class="celina-button celina-button--primary" href="' . esc_url( home_url( '/contact/' ) ) . '">Discuss Your Project</a></div>';
		}
	}
	$result .= $history . $locations . $deferred;
	if ( 'page.homepage' === $key ) {
		$result .= celina_sales_review_cta();
	}
	return '<div class="celina-entry-content celina-review-page celina-review-page--' . esc_attr( substr( $key, 5 ) ) . '">' . $result . '</div>';
}

function celina_sales_review_filter( string $content ): string {
	if ( is_admin() || is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! is_singular( 'page' ) || ! in_the_loop() || ! is_main_query() || get_the_ID() !== get_queried_object_id() ) {
		return $content;
	}
	return celina_sales_review_content( $content, (string) get_post_meta( get_the_ID(), '_celina_blueprint_key', true ) );
}
add_filter( 'the_content', 'celina_sales_review_filter', 8 );

function celina_sales_review_body_class( array $classes ): array {
	if ( celina_sales_review_enabled() ) {
		$classes[] = 'celina-sales-review';
	}
	return $classes;
}
add_filter( 'body_class', 'celina_sales_review_body_class' );

function celina_sales_review_assets(): void {
	if ( celina_sales_review_enabled() ) {
		wp_enqueue_style( 'celina-sales-review', get_stylesheet_directory_uri() . '/assets/css/sales-review.css', [ 'celina-content-templates' ], celina_asset_version( 'assets/css/sales-review.css' ) );
		wp_enqueue_script( 'celina-sales-review', get_stylesheet_directory_uri() . '/assets/js/sales-review.js', [], celina_asset_version( 'assets/js/sales-review.js' ), true );
	}
}
add_action( 'wp_enqueue_scripts', 'celina_sales_review_assets', 30 );

function celina_sales_review_menu_label( string $label, string $location ): string {
	if ( ! celina_sales_review_enabled() ) {
		return $label;
	}
	$labels = 'celina-utility' === $location
		? [ 'Industries' => 'Industrial website', 'Military' => 'Military website', 'Event & Hospitality' => 'Event website', 'example.invalid' => 'CELINA website' ]
		: [ 'CELINA Industries' => 'Industries', 'CELINA Capabilities' => 'Capabilities', 'Contact' => 'Discuss Your Project', 'CELINA Corporate News' => 'News & Media' ];
	return $labels[ $label ] ?? $label;
}

function celina_sales_review_menu_title( string $title, $item, $args ): string {
	return celina_sales_review_menu_label( $title, $args->theme_location ?? '' );
}
add_filter( 'nav_menu_item_title', 'celina_sales_review_menu_title', 10, 3 );

function celina_sales_review_keep_menu_item( string $url, string $location ): bool {
	if ( ! celina_sales_review_enabled() ) {
		return true;
	}
	if ( 'celina-utility' === $location ) {
		return ! in_array( strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ), [ 'example.invalid', 'example.invalid' ], true );
	}
	if ( 'celina-primary' === $location ) {
		return ! in_array( untrailingslashit( $url ), [ untrailingslashit( home_url( '/contact/' ) ), untrailingslashit( home_url( '/news/' ) ) ], true );
	}
	return true;
}

function celina_sales_review_menu_items( array $items, $args ): array {
	return array_values( array_filter( $items, static function ( $item ) use ( $args ) {
		return celina_sales_review_keep_menu_item( $item->url, $args->theme_location ?? '' );
	} ) );
}
add_filter( 'wp_nav_menu_objects', 'celina_sales_review_menu_items', 10, 2 );

function celina_sales_review_news_link( string $items, $args ): string {
	if ( celina_sales_review_enabled() && in_array( $args->theme_location ?? '', [ 'celina-primary', 'celina-footer' ], true ) ) {
		$state = celina_sales_review_nav_state( home_url( '/news/' ) );
		$current = $state ? ' aria-current="' . esc_attr( $state ) . '"' : '';
		$items .= '<li class="menu-item"><a href="' . esc_url( home_url( '/news/' ) ) . '"' . $current . '>News &amp; Media</a></li>';
	}
	return $items;
}
add_filter( 'wp_nav_menu_items', 'celina_sales_review_news_link', 10, 2 );

function celina_sales_review_fallback_items( array $items, string $location ): array {
	$result = [];
	foreach ( $items as $item ) {
		if ( ! celina_sales_review_keep_menu_item( $item['url'], $location ) ) {
			continue;
		}
		$item['label'] = celina_sales_review_menu_label( $item['label'], $location );
		if ( isset( $item['children'] ) ) {
			$item['children'] = array_values( array_filter( $item['children'], static function ( $child ) use ( $location ) {
				return celina_sales_review_keep_menu_item( $child['url'], $location );
			} ) );
		}
		$result[] = $item;
	}
	if ( celina_sales_review_enabled() && 'celina-primary' === $location ) {
		$result[] = [ 'label' => 'News & Media', 'url' => home_url( '/news/' ) ];
	}
	return $result;
}

function celina_sales_review_nav_state( string $url ): string {
	if ( ! celina_sales_review_enabled() ) {
		return '';
	}
	$target = untrailingslashit( $url );
	$current = is_singular() ? untrailingslashit( get_permalink( get_queried_object_id() ) ) : '';
	if ( $target === $current ) {
		return 'page';
	}
	if ( $target === untrailingslashit( home_url( '/news/' ) ) ) {
		if ( is_home() ) {
			return 'page';
		}
		if ( is_singular( 'post' ) || is_category() || is_tag() || is_date() || is_author() ) {
			return 'location';
		}
	}
	if ( $target === untrailingslashit( home_url( '/markets/' ) ) && is_page( 'industries' ) ) {
		return 'page';
	}
	if ( $target === untrailingslashit( home_url( '/why-celina/' ) ) && is_page( [ 'about', 'community', 'resources' ] ) ) {
		return 'location';
	}
	return '';
}

function celina_sales_review_nav_attributes( array $atts, $item, $args ): array {
	if ( celina_sales_review_enabled() && in_array( $args->theme_location ?? '', [ 'celina-primary', 'celina-footer' ], true ) ) {
		$state = celina_sales_review_nav_state( $item->url );
		if ( $state ) {
			$atts['aria-current'] = $state;
		}
	}
	return $atts;
}
add_filter( 'nav_menu_link_attributes', 'celina_sales_review_nav_attributes', 10, 3 );

function celina_sales_review_back_to_top(): void {
	if ( ! celina_sales_review_enabled() ) {
		return;
	}
	?>
	<button class="celina-back-to-top" type="button" aria-label="<?php esc_attr_e( 'Back to top', 'celina' ); ?>" hidden>
		<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="m5 12 7-7 7 7M12 5v15"/></svg>
	</button>
	<?php
}
add_action( 'wp_footer', 'celina_sales_review_back_to_top', 10 );
