<?php

declare( strict_types=1 );

require_once dirname( __DIR__, 2 ) . '/workbench/content/manifest.php';

$root     = dirname( __DIR__, 2 );
$failures = [];
$theme_styles = file_get_contents(
	$root . '/wordpress/themes/celina-child/assets/css/content-templates.css'
);
if ( false === $theme_styles ) {
	$failures[]   = 'missing readable content template styles';
	$theme_styles = '';
}
$patterns = [
	'page.homepage' => [
		'file'       => 'page-homepage.php',
		'hero_asset' => 'portfolio-image-29.png',
		'assets' => [
			'portfolio-image-01.png' => 1,
			'portfolio-image-03.png'               => 1,
			'portfolio-image-09.png'             => 1,
			'portfolio-image-17.png'               => 1,
			'portfolio-image-29.png'   => 2,
			'portfolio-image-36.png'     => 1,
		],
		'alts' => [
			'Government and Military',
			'Industrial and Agriculture',
			'Event and Hospitality',
			'Marketing and Promotion',
			'CELINA campus',
			'Careers at CELINA',
		],
		'copy' => [
			'dependable tent and shelter solutions',
			'Industries We Serve',
			'custom shelter manufacturing',
			'Creating branded, custom tents',
			'Core Capabilities',
		],
	],
	'page.about' => [
		'file'       => 'page-about.php',
		'hero_asset' => 'portfolio-image-14.png',
		'assets' => [
			'portfolio-image-07.png'             => 1,
			'portfolio-image-08.png'         => 1,
			'portfolio-image-11.png'           => 1,
			'portfolio-image-10.png'    => 1,
			'portfolio-image-14.png'                    => 1,
			'portfolio-image-15.png'          => 1,
			'portfolio-image-04.png'                    => 1,
			'portfolio-image-22.png'                    => 1,
			'portfolio-image-24.png'                    => 1,
			'portfolio-image-26.png'                  => 1,
			'portfolio-image-27.png'                       => 1,
			'portfolio-image-31.png'                   => 1,
			'portfolio-image-32.png'                      => 1,
			'portfolio-image-38.png'           => 1,
		],
		'alts' => [
			'About CELINA corporation',
			'Team Member 4, CEO of CELINA',
			'Team Member 6, CFO of CELINA',
			'Team Member 1, Marketing Director at CELINA',
			'Team Member 7, Sales Director at CELINA',
			'Team Member 2, General Manager, Operations at CELINA',
			'Team Member 3, General Manager, Program Management at CELINA',
			'Team Member 5, General Manager, Administration at CELINA',
			'CELINA manufacturing campus aerial view',
			'CELINA manufacturing floor',
			'CELINA tent and shelter teams at work',
			'CELINA warehouse and distribution center',
			'CELINA shipping and logistics',
		],
		'copy' => [
			'global supplier of engineered fabric, tent, and shelter solutions',
			'Milestones &amp; Growth',
			'The CELINA leadership team is focused on stewardship, stability, and long-term thinking',
			'Team Member 4',
			'General Manager, Administration',
			"Let's build something together.",
		],
	],
	'page.why-celina' => [
		'file'       => 'page-why-celina.php',
		'hero_asset' => 'portfolio-image-37.png',
		'assets' => [
			'portfolio-image-02.png' => 1,
			'portfolio-image-23.png'         => 1,
			'portfolio-image-37.png'   => 2,
		],
		'alts' => [
			'Why CELINA community open house',
			'CELINA innovation',
		],
		'copy' => [
			'Why CELINA? For 30 years',
			'What We Hold Ourselves To',
			'Evidence, Not Claims',
			'specialty industries worldwide',
			'industries we serve',
		],
	],
	'page.capabilities' => [
		'file'       => 'page-capabilities.php',
		'hero_asset' => 'portfolio-image-15.png',
		'assets' => [
			'portfolio-image-09.png'   => 1,
			'portfolio-image-15.png' => 1,
		],
		'alts' => [
			'CELINA capabilities supported by manufacturing facility',
		],
		'copy' => [
			'Core Capabilities',
			'specialty industries',
			'Our ISO 9001:2015 certified quality management system',
			'Behind every CELINA project',
			'Built to Support Every Industry We Serve.',
			'CELINA Capabilities FAQ',
		],
	],
	'page.markets' => [
		'file'       => 'page-industries.php',
		'hero_asset' => 'portfolio-image-13.png',
		'assets' => [
			'portfolio-image-01.png' => 1,
			'portfolio-image-03.png'               => 1,
			'portfolio-image-09.png'             => 1,

			'portfolio-image-13.png'        => 1,
			'portfolio-image-17.png'               => 1,
			'portfolio-image-29.png'   => 1,
		],
		'alts' => [
			'CELINA military shelters',
			'CELINA Industrial and Agriculture shelters',
			'CELINA Event and Hospitality tents',
			'CELINA custom tents for Marketing and Promotion',
			'CELINA shared capabilities',
		],
		'copy' => [
			'Industries We Serve',
			'One Company. Four Specialized Industries.',
			'Find Your Industry',
			'CELINA Industries FAQ',
		],
	],
	'page.community' => [
		'file'       => 'page-community.php',
		'hero_asset' => 'portfolio-image-20.png',
		'assets' => [
			'portfolio-image-05.png'                 => 1,
			'portfolio-image-19.png'                   => 1,
			'portfolio-image-20.png' => 1,
			'portfolio-image-34.png'                   => 1,
			'portfolio-image-35.png'                => 1,
		],
		'alts' => [
			'CELINA community investment',
			'Education and workforce development',
			'CELINA community and charitable engagement',
			'Industry leadership and tent training',
		],
		'copy' => [
			"CELINA community involvement isn't a campaign",
			'CELINA regularly works with educational institutions',
			'Organizations interested in partnering with CELINA',
			'CELINA community partnerships',
		],
	],
	'page.careers' => [
		'file'       => 'page-careers.php',
		'hero_asset' => 'portfolio-image-12.png',
		'assets' => [
			'portfolio-image-12.png'            => 1,
			'portfolio-image-15.png'           => 1,
			'portfolio-image-36.png'       => 1,
		],
		'alts' => [
			'CELINA employees building tent and shelter products',
			'CELINA employee development on the manufacturing floor',
		],
		'copy' => [
			'specialty industries',
			'tent and shelter products',
			"When you work at CELINA, you're not just building fabric structures.",
			'From production and engineering to program management and sales',
			'Why Choose CELINA Careers',
			'Compensation &amp; Perks',
			'CELINA Careers FAQ',
			'Browse our current openings and submit your application online.',
		],
	],
	'page.contact' => [
		'file'       => 'page-contact.php',
		'hero_asset' => null,
		'assets' => [
			'portfolio-image-03.png'             => 1,
			'portfolio-image-13.png'      => 1,
			'portfolio-image-14.png'                  => 1,
			'portfolio-image-17.png'             => 1,
			'portfolio-image-29.png' => 1,
			'portfolio-image-33.png'            => 1,
		],
		'alts' => [
			'Contact CELINA headquarters',
			'Celina, Ohio headquarters',
			'Worldwide distribution',
			'CELINA Military Shelters',
			'CELINA industrial shelters',
			'CELINA commercial event tents',
		],
		'copy' => [
			'At CELINA, we believe responsive communication',
			'All times Eastern. Holiday hours may vary.',
			'Contact CELINA FAQ',
			'Looking for a Specific Industry?',
			'Contact the Right Team Directly',
			'go straight to your industry website below.',
		],
	],
	'page.corporate-news' => [
		'file'       => 'page-corporate-news.php',
		'hero_asset' => 'portfolio-image-30.png',
		'assets' => [
			'portfolio-image-06.png'   => 0,
			'portfolio-image-30.png' => 1,
		],
		'alts' => [],
		'copy' => [
			'CELINA Corporate News',
			'Stay informed with CELINA Corporate news',
			'Latest CELINA Corporate News',
			'Members of the media seeking information about CELINA',
		],
	],
	'page.corporate-resources' => [
		'file'       => 'page-corporate-resources.php',
		'hero_asset' => 'portfolio-image-21.png',
		'assets' => [
			'portfolio-image-21.png' => 1,
		],
		'alts' => [],
		'copy' => [
			'frequently requested corporate resources here.',
			'Featured Corporate Resources',
			'Resource file not yet available',
			"Can't find what you're looking for?",
		],
	],
];

$manifest = [];
foreach ( celina_content_manifest() as $entry ) {
	$manifest[ $entry['key'] ] = $entry;
}

foreach ( $patterns as $key => $contract ) {
	$pattern_path = $root . '/wordpress/themes/celina-child/patterns/' . $contract['file'];
	$source       = is_readable( $pattern_path ) ? file_get_contents( $pattern_path ) : false;
	if ( false === $source ) {
		$failures[] = "{$key}: missing readable pattern";
		continue;
	}

	if ( 2 > ( $manifest[ $key ]['version'] ?? 0 ) ) {
		$failures[] = "{$key}: client media migration requires blueprint version 2 or later";
	}
	if ( false !== strpos( $source, 'article-placeholder.svg' ) ) {
		$failures[] = "{$key}: placeholder image remains";
	}
	if (
		false !== strpos( $source, 'excluded-media.example.invalid' ) ||
		false !== strpos( $source, '/wp-content/uploads/' )
	) {
		$failures[] = "{$key}: client media must be local, not hotlinked";
	}
	if ( false !== strpos( $source, 'photography to be supplied' ) ) {
		$failures[] = "{$key}: unresolved photography handoff copy remains";
	}

	foreach ( $contract['assets'] as $asset => $expected_references ) {
		$relative_path = 'assets/images/content/' . $asset;
		$asset_path    = $root . '/wordpress/themes/celina-child/' . $relative_path;
		$reference_count = substr_count( $source, $asset );
		if ( $asset === $contract['hero_asset'] ) {
			$reference_count += substr_count( $theme_styles, $asset );
		}
		if ( ! is_readable( $asset_path ) ) {
			$failures[] = "{$key}: missing local asset {$asset}";
		}
		if ( $expected_references !== $reference_count ) {
			$failures[] = "{$key}: expected {$expected_references} references to {$asset}";
		}
	}

	foreach ( $contract['alts'] as $alt ) {
		if ( false === strpos( $source, $alt ) ) {
			$failures[] = "{$key}: missing required contextual alt text {$alt}";
		}
	}

	foreach ( $contract['copy'] as $copy ) {
		if ( false === strpos( $source, $copy ) ) {
			$failures[] = "{$key}: missing client copy marker {$copy}";
		}
	}
}

if ( count( $failures ) > 0 ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "Client content asset contract passed\n";
