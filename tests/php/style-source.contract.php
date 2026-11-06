<?php

declare( strict_types=1 );

$root     = dirname( __DIR__, 2 );
$failures = [];
$sources  = [
	'tokens'       => $root . '/wordpress/themes/celina-child/assets/css/tokens.css',
	'shell'        => $root . '/wordpress/themes/celina-child/assets/css/site-shell.css',
	'shell_markup' => $root . '/wordpress/themes/celina-child/inc/site-shell.php',
	'theme'        => $root . '/wordpress/themes/celina-child/style.css',
	'content' => $root . '/wordpress/themes/celina-child/assets/css/content-templates.css',
	'sales'   => $root . '/wordpress/themes/celina-child/assets/css/sales-patterns.css',
	'hero'    => $root . '/wordpress/plugins/celina-blocks/src/blocks/hero/style.scss',
	'capability' => $root . '/wordpress/plugins/celina-blocks/src/blocks/capability-cards/style.scss',
	'paycor'     => $root . '/wordpress/plugins/celina-blocks/src/blocks/paycor-jobs/style.scss',
];

$css = [];
foreach ( $sources as $name => $path ) {
	$source = is_readable( $path ) ? file_get_contents( $path ) : false;
	if ( false === $source ) {
		$failures[] = "missing visual source {$name}";
		continue;
	}
	$css[ $name ] = $source;
}

$required = [
	'tokens' => [
		'font-family: Inter;',
		'--celina-font-body: inter, arial, sans-serif;',
		'url(../fonts/inter-latin.woff2) format("woff2");',
		'url(../fonts/inter-latin-ext.woff2) format("woff2");',
		'--celina-color-surface-muted: #f4f4f2;',
		'--celina-radius-button: 2px;',
		'--celina-shadow-card: none;',
		'--celina-shadow-header: none;',
		'--celina-section-space: clamp(3rem, 5vw, 4.5rem);',
		'--celina-reading-width: 47.5rem;',
		'--celina-grid-width: 73.75rem;',
		'--celina-gutter: clamp(1rem, 4vw, 2rem);',
		'--celina-hero-height: 34rem;',
	],
	'shell' => [
		'background: var(--celina-color-stone);',
		'width: var(--celina-container);',
		'position: sticky;',
		'.celina-utility__contacts',
		'.celina-utility__links',
		'.celina-header-actions',
		'.celina-header-cta',
		'.celina-mobile-menu .sub-menu',
		'box-shadow: var(--celina-shadow-header);',
		'border-radius: var(--celina-radius-button);',
		'overflow-wrap: break-word;',
		'white-space: nowrap;',
		'@media (max-width: 64rem)',
		'flex: 0 0 3rem;',
		'.celina-nav-toggle__line',
		'inset: 100% 0 auto;',
	],
	'shell_markup' => [
		'celina-utility__contacts',
		'celina-utility__links',
		'celina-header-actions',
		'celina-header-cta',
		"esc_html__( 'Contact Us', 'celina' )",
		"'CELINA Industries'",
		"'Event & Hospitality'",
		"'Our Sites'",
	],
	'theme' => [
		'Version: 0.2.1',
	],
	'content' => [
		'.celina-entry-content > .wp-block-celina-capability-cards',
		'.celina-entry-content > .wp-block-gallery,',
		'.celina-entry-content > .wp-block-celina-media-contact,',
		'.editor-styles-wrapper .is-root-container > .wp-block-gallery,',
		'margin-block-start: var(--celina-section-space);',
		'.celina-form-pattern',
		'.celina-form-pattern__intro',
		'.celina-single-article',
		'.celina-contact-columns',
		'.celina-benefits-list',
		'@media (prefers-reduced-motion: reduce)',
	],
	'hero' => [
		'align-items: flex-end;',
		'min-height: var(--celina-hero-height, 34rem);',
		'background: var(--celina-hero-overlay,',
		'font-size: clamp(2rem, 4vw, 3.25rem);',
	],
	'capability' => [
		'.celina-capability-cards__related-links',
		'grid-template-columns: repeat(2, minmax(0, 1fr));',
		'.celina-capability-cards__related-link',
		'border-left: 0.25rem solid var(--celina-color-citrus, #ff6c0d);',
	],
	'paycor' => [
		'margin-block-end: var(--celina-section-space, 4rem);',
		'display: inline-flex;',
		'background: var(--celina-color-stone-dark, #2e3432);',
	],
];

foreach ( $required as $source_name => $markers ) {
	foreach ( $markers as $marker ) {
		if ( false === strpos( $css[ $source_name ] ?? '', $marker ) ) {
			$failures[] = "missing {$source_name} visual marker {$marker}";
		}
	}
}

foreach ( [ 'inter-latin.woff2', 'inter-latin-ext.woff2' ] as $font_file ) {
	if ( ! is_readable( $root . '/wordpress/themes/celina-child/assets/fonts/' . $font_file ) ) {
		$failures[] = "missing local Inter font {$font_file}";
	}
}

$benefits_rule = [];
if ( 1 !== preg_match( '/\.celina-benefits-list\s*\{(?<rule>[^}]*)\}/s', $css['content'] ?? '', $benefits_rule ) ) {
	$failures[] = 'missing benefits list layout rule';
} else {
	if ( false === strpos( $benefits_rule['rule'], 'margin-inline: auto;' ) ) {
		$failures[] = 'benefits list must stay centered in the content column';
	}
	if ( false === strpos( $benefits_rule['rule'], 'margin-block: var(--celina-space-8) var(--celina-space-6);' ) ) {
		$failures[] = 'benefits list must preserve spacing before the following section';
	}
}

$benefits_heading_rule = [];
if ( 1 !== preg_match( '/\.celina-benefits-heading\s*\{(?<rule>[^}]*)\}/s', $css['content'] ?? '', $benefits_heading_rule ) ) {
	$failures[] = 'missing benefits heading spacing rule';
} elseif ( false === strpos( $benefits_heading_rule['rule'], 'margin-block: 0;' ) ) {
	$failures[] = 'benefits heading must take its spacing from the perks band, not its own margin';
}

$benefits_band_rule = [];
if ( 1 !== preg_match( '/\.celina-sales-section\.celina-careers-benefits\s*\{(?<rule>[^}]*)\}/s', $css['sales'] ?? '', $benefits_band_rule ) ) {
	$failures[] = 'missing Compensation & Perks band rule';
} elseif ( false === strpos( $benefits_band_rule['rule'], 'background: var(--celina-color-surface-muted);' ) ) {
	$failures[] = 'Compensation & Perks band must keep the shared gray section background';
}

if ( false !== strpos( $css['paycor'] ?? '', 'min-height: 24rem;' ) ) {
	$failures[] = 'Paycor embed must not reserve a large blank area before fallback content';
}

$form_pattern_rule = [];
if ( 1 !== preg_match( '/\.celina-form-pattern\s*\{(?<rule>[^}]*)\}/s', $css['content'] ?? '', $form_pattern_rule ) ) {
	$failures[] = 'missing reusable form pattern layout rule';
} else {
	foreach (
		[
			'padding: clamp(1.5rem, 4vw, 3rem);',
			'border-top: 0.25rem solid var(--celina-color-citrus);',
			'border-radius: var(--celina-radius-card);',
			'background: var(--celina-color-surface);',
		] as $marker
	) {
		if ( false === strpos( $form_pattern_rule['rule'], $marker ) ) {
			$failures[] = "form pattern layout missing {$marker}";
		}
	}
}

$form_intro_rule = [];
if ( 1 !== preg_match( '/\.celina-form-pattern__intro\s*\{(?<rule>[^}]*)\}/s', $css['content'] ?? '', $form_intro_rule ) ) {
	$failures[] = 'missing reusable form pattern intro rule';
} elseif ( false === strpos( $form_intro_rule['rule'], 'max-width: var(--celina-reading-width);' ) ) {
	$failures[] = 'form pattern intro must use the reading width token';
}

$form_block_rule = [];
if (
	1 !== preg_match(
		'/\.celina-form-pattern\s+\.wp-block-celina-hubspot-form\s*\{(?<rule>[^}]*)\}/s',
		$css['content'] ?? '',
		$form_block_rule
	)
) {
	$failures[] = 'missing reusable form pattern block width rule';
} elseif ( false === strpos( $form_block_rule['rule'], 'max-width: var(--celina-reading-width);' ) ) {
	$failures[] = 'form pattern block must use the reading width token';
}

$style_files = array_merge(
	glob( $root . '/wordpress/themes/celina-child/assets/css/*.css' ) ?: [],
	glob( $root . '/wordpress/plugins/celina-blocks/src/blocks/*/*.scss' ) ?: []
);

foreach ( $style_files as $style_file ) {
	$source = file_get_contents( $style_file );
	if ( false === $source ) {
		$failures[] = "unreadable style source {$style_file}";
		continue;
	}
	if ( 1 === preg_match( '#https?://#i', $source ) ) {
		$failures[] = "remote asset in style source {$style_file}";
	}
	if ( false !== strpos( $source, '!important' ) ) {
		$failures[] = "important override in style source {$style_file}";
	}
	if ( false !== strpos( $source, 'box-shadow: 0' ) || false !== strpos( $source, '--celina-shadow-card, 0' ) || false !== strpos( $source, 'translateY(-0.25rem)' ) ) {
		$failures[] = "raised card treatment in style source {$style_file}";
	}
	if ( false !== strpos( $source, '#f5f4f2' ) || false !== strpos( $source, '--celina-radius-button, 0.375rem' ) ) {
		$failures[] = "outdated CELINA visual fallback in style source {$style_file}";
	}
}

if ( count( $failures ) > 0 ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "Visual system source contract passed\n";
