<?php
/**
 * Live contract for the CELINA site shell in the disposable wp-env site.
 *
 * @package Celina_Workbench
 */

$expect = static function ( bool $passed, string $label ): void {
	if ( ! $passed ) {
		throw new RuntimeException( $label . ' failed' );
	}
};

$parse = static function ( string $markup ): DOMXPath {
	if ( ! class_exists( 'DOMDocument' ) || ! class_exists( 'DOMXPath' ) ) {
		throw new RuntimeException( 'DOMDocument and DOMXPath are required' );
	}

	$dom      = new DOMDocument();
	$previous = libxml_use_internal_errors( true );
	$loaded   = $dom->loadHTML( '<!doctype html><html><body>' . $markup . '</body></html>' );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	if ( ! $loaded ) {
		throw new RuntimeException( 'could not parse contract HTML' );
	}

	return new DOMXPath( $dom );
};

$class_query = static function ( string $class ): string {
	return sprintf(
		'//*[contains(concat(" ", normalize-space(@class), " "), " %s ")]',
		$class
	);
};

$normalize_text = static function ( string $text ): string {
	$text = str_replace( "\xc2\xa0", ' ', $text );
	$text = preg_replace( '/[\t ]+/', ' ', $text );
	$text = preg_replace( '/(?: *\R *)+/', "\n", $text );

	return trim( (string) $text );
};

$saved_mods   = [];
$theme_mods   = get_theme_mods();
$tracked_mods = [
	'celina_phone',
	'celina_email',
	'celina_address',
	'celina_footer_tagline',
	'custom_logo',
	'nav_menu_locations',
];

foreach ( $tracked_mods as $mod ) {
	$saved_mods[ $mod ] = [
		'exists' => array_key_exists( $mod, $theme_mods ),
		'value'  => $theme_mods[ $mod ] ?? null,
	];
}

$saved_locations = get_nav_menu_locations();
$menu_id         = 0;
$attachment_id   = 0;
$uploaded_file   = '';
$cleanup_done    = false;

$cleanup = static function () use ( &$attachment_id, &$cleanup_done, &$menu_id, &$uploaded_file, $saved_mods ): void {
	if ( $cleanup_done ) {
		return;
	}

	$cleanup_done = true;

	foreach ( $saved_mods as $mod => $state ) {
		if ( $state['exists'] ) {
			set_theme_mod( $mod, $state['value'] );
		} else {
			remove_theme_mod( $mod );
		}
	}

	if ( $menu_id && ! is_wp_error( $menu_id ) ) {
		wp_delete_nav_menu( $menu_id );
	}

	if ( $attachment_id ) {
		wp_delete_attachment( $attachment_id, true );
	}

	if ( $uploaded_file && file_exists( $uploaded_file ) ) {
		unlink( $uploaded_file );
	}
};

register_shutdown_function( $cleanup );

$caught = null;

try {
	$raw_phone   = '+1 (202) 555-0100<script></script>';
	$raw_email   = 'contact@example.invalid"><';
	$raw_address = "Fixture address<script></script>\nCelina, OH";
	$raw_tagline = 'Synthetic fixture tagline.<script></script>';

	set_theme_mod( 'celina_phone', $raw_phone );
	set_theme_mod( 'celina_email', $raw_email );
	set_theme_mod( 'celina_address', $raw_address );
	set_theme_mod( 'celina_footer_tagline', $raw_tagline );
	set_theme_mod( 'custom_logo', 'bad"><script>evil-logo</script>' );
	set_theme_mod( 'nav_menu_locations', [] );

	ob_start();
	celina_render_header();
	$unassigned_header = (string) ob_get_clean();
	ob_start();
	celina_render_footer();
	$unassigned_footer = (string) ob_get_clean();

	$unassigned_header_xpath = $parse( $unassigned_header );
	$unassigned_footer_xpath = $parse( $unassigned_footer );
	$expect( 1 === $unassigned_header_xpath->query( $class_query( 'celina-primary' ) )->length, 'fallback primary navigation present' );
	$expect( 1 === $unassigned_header_xpath->query( $class_query( 'celina-mobile-nav' ) )->length, 'fallback mobile navigation present' );
	$expect( 1 === $unassigned_header_xpath->query( $class_query( 'celina-nav-toggle' ) )->length, 'fallback menu toggle present' );
	$expect( 3 === $unassigned_header_xpath->query( $class_query( 'celina-nav-toggle__line' ) )->length, 'hamburger toggle has three visual lines' );
	$expect( 1 === $unassigned_header_xpath->query( '//nav[@aria-label="Utility navigation"]' )->length, 'fallback utility navigation present' );
	$expect( 1 === $unassigned_header_xpath->query( $class_query( 'celina-header-cta' ) )->length, 'fallback header contact CTA present' );
	$expect( 1 === $unassigned_footer_xpath->query( '//nav[@aria-label="Footer sites"]' )->length, 'fallback footer-sites navigation present' );
	$expect( 0 === $unassigned_footer_xpath->query( '//nav[@aria-label="Legal"]' )->length, 'unassigned legal navigation absent' );
	$expect( 1 === $unassigned_footer_xpath->query( '//a[normalize-space(.)="CELINA Industries"]' )->length, 'fallback footer CELINA Industries link present' );
	$expect( false === stripos( $unassigned_header . $unassigned_footer, '<script' ), 'invalid custom logo cannot inject markup' );

	$menu_id = wp_create_nav_menu( 'CELINA Contract Menu ' . wp_generate_uuid4() );
	if ( is_wp_error( $menu_id ) ) {
		throw new RuntimeException( 'could not create contract menu: ' . $menu_id->get_error_message() );
	}

	$menu_item_id = wp_update_nav_menu_item(
		$menu_id,
		0,
		[
			'menu-item-status' => 'publish',
			'menu-item-title'  => 'Synthetic Contract Link',
			'menu-item-type'   => 'custom',
			'menu-item-url'    => home_url( '/' ),
		]
	);
	if ( is_wp_error( $menu_item_id ) ) {
		throw new RuntimeException( 'could not create contract menu item: ' . $menu_item_id->get_error_message() );
	}

	$locations = [];
	foreach ( [ 'celina-utility', 'celina-primary', 'celina-footer', 'celina-legal', 'celina-social' ] as $location ) {
		$locations[ $location ] = $menu_id;
	}
	set_theme_mod( 'nav_menu_locations', $locations );

	$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true );
	if ( false === $png ) {
		throw new RuntimeException( 'could not decode the contract logo' );
	}

	$upload = wp_upload_bits( 'celina-contract-logo-' . wp_generate_uuid4() . '.png', null, $png );
	if ( $upload['error'] ) {
		throw new RuntimeException( 'could not upload contract logo: ' . $upload['error'] );
	}
	$uploaded_file = $upload['file'];

	$attachment_id = wp_insert_attachment(
		[
			'post_excerpt'   => '<script>evil-logo-caption</script>',
			'post_mime_type' => 'image/png',
			'post_status'    => 'inherit',
			'post_title'     => 'CELINA contract logo<script>evil-logo-title</script>',
		],
		$uploaded_file
	);
	if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
		$message = is_wp_error( $attachment_id ) ? $attachment_id->get_error_message() : 'empty attachment ID';
		throw new RuntimeException( 'could not create the contract logo attachment: ' . $message );
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	$metadata = wp_generate_attachment_metadata( $attachment_id, $uploaded_file );
	if ( is_array( $metadata ) ) {
		wp_update_attachment_metadata( $attachment_id, $metadata );
	}
	update_post_meta( $attachment_id, '_wp_attachment_image_alt', 'CELINA contract logo' );
	set_theme_mod( 'custom_logo', $attachment_id );

	$logo_url = wp_get_attachment_url( $attachment_id );
	if ( ! $logo_url ) {
		throw new RuntimeException( 'could not resolve the contract logo URL' );
	}

	ob_start();
	celina_render_header();
	$header = (string) ob_get_clean();
	ob_start();
	celina_render_footer();
	$footer = (string) ob_get_clean();
	$shell  = $header . $footer;

	$header_xpath = $parse( $header );
	$footer_xpath = $parse( $footer );
	$shell_xpath  = $parse( $shell );

	$expected_phone   = '+1 (202) 555-0100';
	$expected_tel     = '+12025550100';
	$expected_email   = 'contact@example.invalid';
	$expected_address = "Fixture address\nCelina, OH";
	$expected_tagline = 'Synthetic fixture tagline.';

	$phone_links = $shell_xpath->query( '//a[@href="tel:' . $expected_tel . '"]' );
	$email_links = $shell_xpath->query( '//a[@href="mailto:' . $expected_email . '"]' );
	$logo_links  = $header_xpath->query( '//a[contains(concat(" ", normalize-space(@class), " "), " custom-logo-link ") and @href="' . home_url( '/' ) . '"]' );
	$logo_images = $header_xpath->query( '//img[contains(concat(" ", normalize-space(@class), " "), " custom-logo ") and @src="' . $logo_url . '" and @alt="CELINA contract logo"]' );
	$footer_logo_src = get_stylesheet_directory_uri() . '/assets/images/celina-logo-white.png';
	$footer_logo_links = $footer_xpath->query( '//a[contains(concat(" ", normalize-space(@class), " "), " celina-footer-logo ") and @href="' . home_url( '/' ) . '"]' );
	$footer_logo_images = $footer_xpath->query( '//img[contains(concat(" ", normalize-space(@class), " "), " celina-footer-logo__image ") and @src="' . $footer_logo_src . '" and @alt="CELINA"]' );
	$tagline     = $footer_xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " celina-footer-tagline ")]' )->item( 0 );
	$address     = $footer_xpath->query( '//address' )->item( 0 );

	$address_text = '';
	if ( $address ) {
		foreach ( $address->childNodes as $node ) {
			if ( $node instanceof DOMElement && 'a' === strtolower( $node->tagName ) ) {
				break;
			}
			$address_text .= $node instanceof DOMElement && 'br' === strtolower( $node->tagName ) ? "\n" : $node->textContent;
		}
	}

	$expect( 1 === $header_xpath->query( $class_query( 'celina-header' ) )->length, 'one CELINA header' );
	$expect( 1 === $footer_xpath->query( $class_query( 'celina-footer' ) )->length, 'one CELINA footer' );
	$expect( 2 === $phone_links->length, 'exact sanitized telephone hrefs' );
	$expect( $expected_phone === trim( $phone_links->item( 0 )->textContent ) && $expected_phone === trim( $phone_links->item( 1 )->textContent ), 'exact sanitized telephone values' );
	$expect( 2 === $email_links->length, 'exact sanitized email hrefs' );
	$expect( $expected_email === trim( $email_links->item( 0 )->textContent ) && $expected_email === trim( $email_links->item( 1 )->textContent ), 'exact sanitized email values' );
	$expect( $expected_address === $normalize_text( $address_text ), 'exact sanitized address' );
	$expect( $tagline && $expected_tagline === trim( $tagline->textContent ), 'exact sanitized tagline' );
	$expect( 1 === $logo_links->length, 'safe custom logo link' );
	$expect( 1 === $logo_images->length, 'safe custom logo image' );
	$expect( 1 === $footer_logo_links->length, 'local footer logo link' );
	$expect( 1 === $footer_logo_images->length, 'local footer logo image' );
	$expect( false === strpos( $footer_logo_src, 'example.invalid' ), 'footer logo is not hotlinked' );
	$expect( 1 === $footer_xpath->query( '//h2[normalize-space(.)="Our Sites"]' )->length, 'Our Sites footer heading' );
	$expect( 1 === $footer_xpath->query( '//h2[normalize-space(.)="Contact"]' )->length, 'Contact footer heading' );
	$expect( false !== strpos( $footer, '© ' . wp_date( 'Y' ) . ' CELINA. All rights reserved.' ), 'current-year copyright copy' );
	$expect( 0 === $shell_xpath->query( '//script' )->length, 'no script elements in rendered shell' );
	$expect( 0 === $shell_xpath->query( '//@*[starts-with(translate(name(), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "on")]' )->length, 'no event-handler attributes in rendered shell' );
	foreach ( [ '<script', $raw_phone, $raw_email, $raw_address, $raw_tagline, 'bad"><script>evil-logo</script>', 'evil-logo-title', 'evil-logo-caption' ] as $payload ) {
		$expect( false === stripos( $shell, $payload ), 'raw payload absent: ' . $payload );
	}

	$expect( 1 === $header_xpath->query( '//nav[@aria-label="' . esc_attr__( 'Utility navigation', 'celina' ) . '"]' )->length, 'translated utility navigation label' );
	$expect( 1 === $header_xpath->query( '//nav[@aria-label="' . esc_attr__( 'Primary navigation', 'celina' ) . '"]' )->length, 'translated primary navigation label' );
	$expect( 1 === $header_xpath->query( '//nav[@aria-label="' . esc_attr__( 'Mobile navigation', 'celina' ) . '"]' )->length, 'translated mobile navigation label' );
	$expect( 1 === $footer_xpath->query( '//nav[@aria-label="' . esc_attr__( 'Footer sites', 'celina' ) . '"]' )->length, 'translated footer-sites navigation label' );
	$expect( 1 === $footer_xpath->query( '//nav[@aria-label="' . esc_attr__( 'Legal', 'celina' ) . '"]' )->length, 'translated legal navigation label' );
	$expect( 1 === $header_xpath->query( '//button[@aria-controls="celina-mobile-nav" and @aria-expanded="false" and @aria-label="' . esc_attr__( 'Open navigation', 'celina' ) . '"]' )->length, 'labelled collapsed mobile navigation control' );
	$expect( 3 === $header_xpath->query( '//button[contains(concat(" ", normalize-space(@class), " "), " celina-nav-toggle ")]/*[contains(concat(" ", normalize-space(@class), " "), " celina-nav-toggle__icon ")]/*[contains(concat(" ", normalize-space(@class), " "), " celina-nav-toggle__line ")]' )->length, 'three-line hamburger icon' );

	if ( ! class_exists( 'WP_Customize_Manager' ) ) {
		require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
	}
	$customizer = new WP_Customize_Manager();
	celina_customize_register( $customizer );
	$customizer_contract = [
		'celina_phone'          => [ '(202) 555-0100', 'sanitize_text_field', 'text', 'Phone' ],
		'celina_email'          => [ 'sales@example.invalid', 'sanitize_email', 'email', 'Email' ],
		'celina_address'        => [ "123 Example Street\nCelina, OH 00000 USA", 'sanitize_textarea_field', 'textarea', 'Address' ],
		'celina_footer_tagline' => [ 'CELINA manufactures and distributes innovative fabric solutions for military, industrial, and commercial markets worldwide.', 'sanitize_textarea_field', 'textarea', 'Footer tagline' ],
	];
	foreach ( $customizer_contract as $id => $expected ) {
		$setting = $customizer->get_setting( $id );
		$control = $customizer->get_control( $id );
		$expect( $setting && 'theme_mod' === $setting->type && $expected[0] === $setting->default && $expected[1] === $setting->sanitize_callback, $id . ' Customizer setting contract' );
		$expect( $control && 'celina_contact' === $control->section && $expected[2] === $control->type && $expected[3] === $control->label, $id . ' Customizer control contract' );
	}

	$expect( 10 === has_action( 'generate_header', 'celina_render_header' ), 'GeneratePress header hook priority' );
	$expect( 10 === has_action( 'generate_footer', 'celina_render_footer' ), 'GeneratePress footer hook priority' );
	$expect( 100 === has_action( 'after_setup_theme', 'celina_replace_generatepress_shell' ), 'late GeneratePress replacement hook' );
	$expect( false === has_action( 'generate_header', 'generate_construct_header' ), 'parent header callback absent' );
	$expect( false === has_action( 'generate_after_header', 'generate_add_navigation_after_header' ), 'parent navigation callback absent' );
	$expect( false === has_action( 'generate_before_header', 'generate_add_navigation_before_header' ), 'parent before-header navigation callback absent' );
	$expect( false === has_action( 'generate_footer', 'generate_construct_footer' ), 'parent footer callback absent' );
	$expect( false === has_action( 'generate_footer', 'generate_construct_footer_widgets' ), 'parent footer-widgets callback absent' );
	$expect( 2 === has_action( 'generate_before_header', 'generate_do_skip_to_content_link' ), 'GeneratePress skip link retained' );
	$expect( false === has_action( 'wp_body_open', 'celina_render_header' ), 'obsolete body hook absent' );
	$expect( false === has_action( 'wp_footer', 'celina_render_footer' ), 'obsolete footer hook absent' );

	$fixture_url       = home_url( '/' );
	$fixture_url_parts = wp_parse_url( $fixture_url );
	if ( ! is_array( $fixture_url_parts ) || empty( $fixture_url_parts['host'] ) ) {
		throw new RuntimeException( 'could not parse the fixture home URL' );
	}

	$fixture_host = $fixture_url_parts['host'];
	if ( isset( $fixture_url_parts['port'] ) ) {
		$fixture_host .= ':' . $fixture_url_parts['port'];
	}

	$fixture_internal_url = 'http://wordpress' . ( $fixture_url_parts['path'] ?? '/' );
	if ( isset( $fixture_url_parts['query'] ) ) {
		$fixture_internal_url .= '?' . $fixture_url_parts['query'];
	}

	$response = wp_remote_get(
		$fixture_internal_url,
		[
			'headers' => [ 'Host' => $fixture_host ],
			'timeout' => 15,
		]
	);
	if ( is_wp_error( $response ) ) {
		throw new RuntimeException( 'fixture HTTP request failed: ' . $response->get_error_message() );
	}

	$body   = wp_remote_retrieve_body( $response );
	$status = wp_remote_retrieve_response_code( $response );
	$xpath  = $parse( $body );
	$expect( 200 === $status, 'fixture HTTP 200' );
	$expect( 1 === $xpath->query( $class_query( 'celina-header' ) )->length, 'fixture exact CELINA header count' );
	$expect( 1 === $xpath->query( $class_query( 'celina-footer' ) )->length, 'fixture exact CELINA footer count' );
	$expect( 0 === $xpath->query( $class_query( 'site-header' ) )->length, 'fixture parent site-header class token absent' );
	$expect( 0 === $xpath->query( $class_query( 'site-info' ) )->length, 'fixture parent site-info class token absent' );
	$expect( 0 === $xpath->query( '//*[@id="site-navigation"]' )->length, 'fixture parent navigation ID absent' );
	$expect( 0 === $xpath->query( '//*[@id="footer-widgets"]' )->length, 'fixture parent footer-widgets ID absent' );

	$skip_position   = strpos( $body, 'class="screen-reader-text skip-link"' );
	$header_position = strpos( $body, '<header class="celina-header"' );
	$expect( false !== $skip_position, 'fixture skip link present' );
	$expect( false !== $header_position, 'fixture CELINA header position present' );
	$expect( $skip_position < $header_position, 'fixture skip link precedes CELINA header' );

	$visible_h1 = $xpath->query( '//h1[not(@hidden) and not(@aria-hidden="true") and not(contains(translate(@style, "ABCDEFGHIJKLMNOPQRSTUVWXYZ ", "abcdefghijklmnopqrstuvwxyz"), "display:none"))]' );
	$expect( 1 <= $visible_h1->length && '' !== trim( $visible_h1->item( 0 )->textContent ), 'fixture visible H1' );
	$ids = [];
	foreach ( $xpath->query( '//*[@id]' ) as $node ) {
		$ids[] = $node->getAttribute( 'id' );
	}
	$expect( count( $ids ) === count( array_unique( $ids ) ), 'fixture unique IDs' );
	$expect( 0 === $xpath->query( '//script[contains(., "evil-")]' )->length, 'fixture raw payload absent' );
} catch ( Throwable $error ) {
	$caught = $error;
} finally {
	$cleanup();
}

$restoration_failures = [];
$restored_mods         = get_theme_mods();
foreach ( $saved_mods as $mod => $state ) {
	$exists = array_key_exists( $mod, $restored_mods );
	if ( $state['exists'] !== $exists ) {
		$restoration_failures[] = 'restored ' . $mod . ' existence failed';
	} elseif ( $state['exists'] && $state['value'] !== $restored_mods[ $mod ] ) {
		$restoration_failures[] = 'restored ' . $mod . ' value failed';
	}
}

if ( $saved_locations !== get_nav_menu_locations() ) {
	$restoration_failures[] = 'restored menu locations failed';
}
if ( $menu_id && false !== wp_get_nav_menu_object( $menu_id ) ) {
	$restoration_failures[] = 'temporary menu removal failed';
}
if ( $attachment_id && null !== get_post( $attachment_id ) ) {
	$restoration_failures[] = 'temporary attachment removal failed';
}
if ( $uploaded_file && file_exists( $uploaded_file ) ) {
	$restoration_failures[] = 'temporary logo file removal failed';
}

if ( $caught || $restoration_failures ) {
	$messages = $restoration_failures;
	if ( $caught ) {
		array_unshift( $messages, $caught->getMessage() );
	}
	fwrite( STDERR, implode( "\n", $messages ) . "\n" );
	exit( 1 );
}

echo "site shell contract passed\n";
