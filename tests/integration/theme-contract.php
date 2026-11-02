<?php

$failures = [];
$theme    = wp_get_theme();

if ( 'celina-child' !== $theme->get_stylesheet() ) {
	$failures[] = 'child theme inactive';
}

if ( 'generatepress' !== $theme->get_template() ) {
	$failures[] = 'GeneratePress parent missing';
}

$custom_logo = get_theme_support( 'custom-logo' );

if ( ! is_array( $custom_logo ) || ! isset( $custom_logo[0] ) || ! is_array( $custom_logo[0] ) ) {
	$failures[] = 'custom-logo support missing';
} else {
	$logo_settings = $custom_logo[0];

	if ( 300 !== ( $logo_settings['width'] ?? null ) ) {
		$failures[] = 'custom-logo width must be 300';
	}

	if ( 80 !== ( $logo_settings['height'] ?? null ) ) {
		$failures[] = 'custom-logo height must be 80';
	}

	if ( true !== ( $logo_settings['flex-width'] ?? null ) ) {
		$failures[] = 'custom-logo flex-width must be true';
	}

	if ( true !== ( $logo_settings['flex-height'] ?? null ) ) {
		$failures[] = 'custom-logo flex-height must be true';
	}
}

$menus          = get_registered_nav_menus();
$expected_menus = [
	'celina-utility' => 'Utility Bar',
	'celina-primary' => 'Primary Navigation',
	'celina-footer'  => 'Footer Sites',
	'celina-legal'   => 'Footer Legal',
	'celina-social'  => 'Footer Social',
];

foreach ( $expected_menus as $location => $label ) {
	if ( ! isset( $menus[ $location ] ) ) {
		$failures[] = 'missing menu ' . $location;
	} elseif ( $label !== $menus[ $location ] ) {
		$failures[] = 'incorrect menu label ' . $location;
	}
}

$tokens_file = get_stylesheet_directory() . '/assets/css/tokens.css';
$tokens      = is_readable( $tokens_file ) ? file_get_contents( $tokens_file ) : false;
$declarations = [
	'--celina-color-citrus: #ff6c0d;',
	'--celina-color-citrus-dark: #7e2d2b;',
	'--celina-color-stone: #3d4543;',
	'--celina-color-stone-dark: #2e3432;',
	'--celina-color-stone-darker: #242825;',
	'--celina-color-overlay: rgb(61 69 67 / 72%);',
	'--celina-color-slate: #d9d8d6;',
	'--celina-color-text: #1a1a1a;',
	'--celina-color-muted: #666;',
	'--celina-color-surface: #fff;',
	'--celina-color-surface-muted: #f5f4f2;',
	'--celina-color-border: #e5e5e5;',
	'--celina-font-body: inter, arial, sans-serif;',
	'--celina-font-heading: inter, arial, sans-serif;',
	'--celina-space-1: 0.25rem;',
	'--celina-space-2: 0.5rem;',
	'--celina-space-3: 0.75rem;',
	'--celina-space-4: 1rem;',
	'--celina-space-6: 1.5rem;',
	'--celina-space-8: 2rem;',
	'--celina-space-12: 3rem;',
	'--celina-space-16: 4rem;',
	'--celina-content-width: 72rem;',
	'--celina-wide-width: 80rem;',
	'--celina-radius-card: 0.75rem;',
	'--celina-breakpoint-tablet: 64rem;',
	'--celina-breakpoint-mobile: 48rem;',
];

if ( false === $tokens ) {
	$failures[] = 'tokens file missing';
} else {
	foreach ( $declarations as $declaration ) {
		if ( false === strpos( $tokens, $declaration ) ) {
			$failures[] = 'missing token ' . $declaration;
		}
	}
}

do_action( 'wp_enqueue_scripts' );

$styles                 = wp_styles();
$scripts                = wp_scripts();
$theme_version          = $theme->get( 'Version' );
$template_directory_uri = get_template_directory_uri();
$stylesheet_directory_uri = get_stylesheet_directory_uri();
$required_styles        = [
	'generate-style'           => $template_directory_uri . '/assets/css/main.css',
	'generate-child'           => $stylesheet_directory_uri . '/style.css',
	'celina-tokens'            => $stylesheet_directory_uri . '/assets/css/tokens.css',
	'celina-site-shell'        => $stylesheet_directory_uri . '/assets/css/site-shell.css',
	'celina-content-templates' => $stylesheet_directory_uri . '/assets/css/content-templates.css',
];

foreach ( $required_styles as $handle => $source ) {
	if ( ! isset( $styles->registered[ $handle ] ) ) {
		$failures[] = 'missing registered style ' . $handle;
		continue;
	}

	if ( $source !== $styles->registered[ $handle ]->src ) {
		$failures[] = 'incorrect style source ' . $handle;
	}

	if ( ! wp_style_is( $handle, 'enqueued' ) ) {
		$failures[] = 'style not enqueued ' . $handle;
	}
}

$parent_metadata_source = $template_directory_uri . '/style.css';
$parent_main_source     = $template_directory_uri . '/assets/css/main.css';
$parent_main_handles    = [];

foreach ( $styles->registered as $handle => $style ) {
	if ( $parent_metadata_source === $style->src ) {
		$failures[] = sprintf(
			'parent metadata stylesheet request %1$s %2$s',
			$handle,
			$style->src
		);
	}

	if ( $parent_main_source === $style->src ) {
		$parent_main_handles[] = $handle;
	}
}

if ( 1 !== count( $parent_main_handles ) ) {
	$failures[] = sprintf(
		'expected one parent main stylesheet source, found %1$s (%2$s)',
		count( $parent_main_handles ),
		implode( ', ', $parent_main_handles )
	);
} elseif ( 'generate-style' !== $parent_main_handles[0] ) {
	$failures[] = sprintf(
		'parent main stylesheet has unexpected handle %1$s (%2$s)',
		$parent_main_handles[0],
		$parent_main_source
	);
}

if (
	isset( $styles->registered['generate-child'] ) &&
	[ 'generate-style' ] !== $styles->registered['generate-child']->deps
) {
	$failures[] = 'generate-child dependency mismatch';
}

if (
	isset( $styles->registered['celina-tokens'] ) &&
	[ 'generate-child' ] !== $styles->registered['celina-tokens']->deps
) {
	$failures[] = 'celina-tokens dependency mismatch';
}

if (
	isset( $styles->registered['celina-site-shell'] ) &&
	[ 'celina-tokens' ] !== $styles->registered['celina-site-shell']->deps
) {
	$failures[] = 'celina-site-shell dependency mismatch';
}

if (
	isset( $styles->registered['celina-content-templates'] ) &&
	[ 'celina-site-shell' ] !== $styles->registered['celina-content-templates']->deps
) {
	$failures[] = 'celina-content-templates dependency mismatch';
}

if (
	isset( $styles->registered['celina-tokens'] ) &&
	$theme_version !== $styles->registered['celina-tokens']->ver
) {
	$failures[] = 'celina-tokens version mismatch';
}

if (
	isset( $styles->registered['celina-site-shell'] ) &&
	$theme_version !== $styles->registered['celina-site-shell']->ver
) {
	$failures[] = 'celina-site-shell version mismatch';
}

if (
	isset( $styles->registered['celina-content-templates'] ) &&
	$theme_version !== $styles->registered['celina-content-templates']->ver
) {
	$failures[] = 'celina-content-templates version mismatch';
}

$style_positions = [];

foreach ( array_keys( $required_styles ) as $handle ) {
	$style_positions[ $handle ] = array_search( $handle, $styles->queue, true );
}

if (
	false === $style_positions['generate-style'] ||
	false === $style_positions['generate-child'] ||
	false === $style_positions['celina-tokens'] ||
	false === $style_positions['celina-site-shell'] ||
	false === $style_positions['celina-content-templates'] ||
	! (
		$style_positions['generate-style'] < $style_positions['generate-child'] &&
		$style_positions['generate-child'] < $style_positions['celina-tokens'] &&
		$style_positions['celina-tokens'] < $style_positions['celina-site-shell'] &&
		$style_positions['celina-site-shell'] < $style_positions['celina-content-templates']
	)
) {
	$failures[] = 'style queue ordering mismatch';
}

$navigation = $scripts->registered['celina-navigation'] ?? null;

if ( null === $navigation ) {
	$failures[] = 'missing registered script celina-navigation';
} else {
	if ( $stylesheet_directory_uri . '/assets/js/navigation.js' !== $navigation->src ) {
		$failures[] = 'incorrect navigation source';
	}

	if ( [] !== $navigation->deps ) {
		$failures[] = 'navigation dependency mismatch';
	}

	if ( $theme_version !== $navigation->ver ) {
		$failures[] = 'navigation version mismatch';
	}

	if ( 1 !== ( $navigation->extra['group'] ?? 0 ) ) {
		$failures[] = 'navigation must be in footer';
	}

	if ( ! wp_script_is( 'celina-navigation', 'enqueued' ) ) {
		$failures[] = 'navigation not enqueued';
	}
}

$leading_image = apply_filters(
	'render_block_core/image',
	'<figure class="wp-block-image"><img src="leading.svg" alt="Leading"></figure>',
	[ 'blockName' => 'core/image' ]
);
$deferred_image = apply_filters(
	'render_block_core/image',
	'<figure class="wp-block-image"><img src="deferred.svg" alt="Deferred"></figure>',
	[ 'blockName' => 'core/image' ]
);
$explicit_image = apply_filters(
	'render_block_core/image',
	'<figure class="wp-block-image"><img src="explicit.svg" alt="Explicit" loading="eager"></figure>',
	[ 'blockName' => 'core/image' ]
);

if ( false !== strpos( $leading_image, 'loading="lazy"' ) ) {
	$failures[] = 'leading core image must remain eager';
}

if ( false === strpos( $deferred_image, 'loading="lazy"' ) ) {
	$failures[] = 'non-leading core image must be lazy';
}

if ( false === strpos( $explicit_image, 'loading="eager"' ) ) {
	$failures[] = 'explicit core image loading intent must be preserved';
}

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "theme contract passed\n";
