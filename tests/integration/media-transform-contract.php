<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$media_transform_file = get_stylesheet_directory() . '/inc/media-content.php';
if ( is_file( $media_transform_file ) ) {
	require_once $media_transform_file;
}

function celina_media_contract_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( 'Media transformer: ' . $message );
	}
}

celina_media_contract_assert( function_exists( 'celina_media_transform_content' ), 'transformer must exist' );

$source = '/wp-content/themes/celina-child/assets/images/content/portfolio-image-14.png';
$upload = home_url( '/wp-content/uploads/2026/09/portfolio-image-14.png' );
$map = [
	'portfolio-image-14.png' => [ 'id' => 901, 'url' => $upload ],
	'portfolio-image-11.png' => [ 'id' => 902, 'url' => home_url( '/wp-content/uploads/contact.webp' ) ],
];
$image = '<!-- wp:image {"sizeSlug":"large","linkDestination":"custom","width":"320px","aspectRatio":"4/3","scale":"cover","metadata":{"name":"Lake"}} -->' . "\n" . '<figure class="wp-block-image size-large is-resized"><a href="https://example.org/details"><img src="' . $source . '" alt="A &amp; B" style="aspect-ratio:4/3;object-fit:cover;width:320px;height:auto"/></a><figcaption class="wp-element-caption">Keep <em>every</em> caption &amp; link</figcaption></figure>' . "\n" . '<!-- /wp:image -->';
$existing = '<!-- wp:image { "id":77 } --><figure class="wp-block-image"><img class="wp-image-77" src="/wp-content/uploads/owned.webp" alt="Owned"/></figure><!-- /wp:image -->';
$unrelated = '<!-- wp:paragraph { "metadata" : {"name":"Keep formatting"} } --><p>' . $source . '</p><!-- /wp:paragraph -->';
$content = "Before\n<!-- wp:gallery {\"columns\":3,\"imageCrop\":true} --><figure class=\"wp-block-gallery has-nested-images columns-3 is-cropped\">" . $image . $existing . $image . '</figure><!-- /wp:gallery -->' . $unrelated . "\nAfter";
$changed = celina_media_transform_content( $content, $map );
celina_media_contract_assert( [ 'portfolio-image-14.png' ] === celina_media_content_sources( $content ), 'source inventory deduplicates native images and ignores prose' );
$blocks = parse_blocks( $changed );
$gallery = $blocks[1];
celina_media_contract_assert( 3 === count( $gallery['innerBlocks'] ), 'mixed gallery order/count preserved' );
celina_media_contract_assert( 901 === $gallery['innerBlocks'][0]['attrs']['id'] && 77 === $gallery['innerBlocks'][1]['attrs']['id'] && 901 === $gallery['innerBlocks'][2]['attrs']['id'], 'duplicates share attachment and existing upload remains' );
celina_media_contract_assert( 2 === substr_count( $changed, 'class="wp-image-901"' ), 'image classes use attachment IDs' );
celina_media_contract_assert( str_contains( $changed, $existing ) && str_contains( $changed, $unrelated ), 'unrelated block bytes preserved even when siblings change' );
celina_media_contract_assert( str_contains( $changed, 'alt="A &amp; B" style="aspect-ratio:4/3;object-fit:cover;width:320px;height:auto"' ) && str_contains( $changed, 'Keep <em>every</em> caption &amp; link' ) && str_contains( $changed, 'href="https://example.org/details"' ), 'alt, crop, captions and links preserved' );
celina_media_contract_assert( $changed === celina_media_transform_content( $changed, $map ), 'migration is byte-idempotent' );
celina_media_contract_assert( $content === celina_media_transform_content( $content, [] ), 'empty map preserves original bytes' );
celina_media_contract_assert( $image === celina_media_transform_content( $image, [ 'other.webp' => [ 'id' => 9, 'url' => $upload ] ] ), 'unavailable source remains unchanged' );

foreach ( [ $source, home_url( $source ), set_url_scheme( home_url( $source ), 'http' ) ] as $local ) {
	celina_media_contract_assert( 'portfolio-image-14.png' === celina_media_source_filename( $local ), 'recognize authorized local URL' );
}
foreach ( [ 'https://evil.example' . $source, '//evil.example' . $source, $source . '?x=1', $source . '#x', str_replace( 'portfolio-image-14.png', '../secret.webp', $source ), str_replace( 'portfolio-image-14.png', '%2e%2e%2fsecret.webp', $source ), str_replace( '.webp', '.svg', $source ), 'javascript:' . $source, 'https://user:pass@' . wp_parse_url( home_url(), PHP_URL_HOST ) . $source ] as $unsafe ) {
	celina_media_contract_assert( null === celina_media_source_filename( $unsafe ), 'reject foreign or ambiguous source: ' . $unsafe );
}

foreach ( [ 'text-image-split', 'industry-card', 'facility-card' ] as $name ) {
	$input = '<!-- wp:celina/' . $name . ' ' . wp_json_encode( [ 'imageUrl' => $source, 'imageAlt' => 'Keep', 'imageId' => 0, 'heading' => 'Edited copy' ] ) . ' /-->';
	$result = parse_blocks( celina_media_transform_content( $input, $map ) )[0];
	celina_media_contract_assert( 901 === $result['attrs']['imageId'] && $upload === $result['attrs']['imageUrl'] && 'Edited copy' === $result['attrs']['heading'] && 'Keep' === $result['attrs']['imageAlt'], 'dynamic ' . $name . ' attributes preserve copy' );
}

$hero = '<!-- wp:celina/hero {"heading":"Edited heading","className":"extra celina-hero--about-photo","anchor":"intro"} --><section id="intro" class="wp-block-celina-hero celina-hero celina-hero--standard extra celina-hero--about-photo"><div class="celina-hero__inner"><h1 class="celina-hero__heading">Edited heading</h1></div></section><!-- /wp:celina/hero -->';
$hero_result = celina_media_transform_content( $hero, $map );
$hero_block = parse_blocks( $hero_result )[0];
celina_media_contract_assert( 901 === $hero_block['attrs']['backgroundId'] && $upload === $hero_block['attrs']['backgroundUrl'], 'legacy hero becomes editable media' );
celina_media_contract_assert( ! str_contains( $hero_result, 'celina-hero--about-photo' ) && str_contains( $hero_result, 'celina-hero__overlay' ) && str_contains( $hero_result, 'background-image:url(' . $upload . ')' ) && str_contains( $hero_result, 'id="intro"' ), 'hero save markup, overlay and anchor preserved' );
celina_media_contract_assert( $hero_result === celina_media_transform_content( $hero_result, $map ), 'hero idempotent' );
$uploaded_hero = str_replace(
	[ '"className":"extra"', 'celina-hero--standard extra"' ],
	[ '"className":"extra celina-hero--about-photo"', 'celina-hero--standard extra celina-hero--about-photo"' ],
	$hero_result
);
celina_media_contract_assert( $uploaded_hero !== $hero_result, 'uploaded hero regression fixture includes legacy photo class' );
$uploaded_hero_result = celina_media_transform_content( $uploaded_hero, $map );
celina_media_contract_assert( $hero_result === $uploaded_hero_result, 'existing uploaded hero loses only legacy photo class, preserving URL, ID and saved markup' );
celina_media_contract_assert( $uploaded_hero_result === celina_media_transform_content( $uploaded_hero_result, $map ), 'uploaded hero cleanup is byte-idempotent' );

foreach ( [ 'celina-sales-contact-heading', 'celina-contact-card--hours' ] as $class ) {
	$inner = '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Edited hours</h3><!-- /wp:heading -->';
	$group = '<!-- wp:group ' . wp_json_encode( [ 'className' => 'extra ' . $class, 'anchor' => 'hours', 'metadata' => [ 'name' => 'Hours' ], 'layout' => [ 'type' => 'constrained' ] ] ) . ' --><div id="hours" class="wp-block-group extra ' . $class . '">' . $inner . '</div><!-- /wp:group -->';
	$converted = celina_media_transform_content( $group, $map );
	$cover = parse_blocks( $converted )[0];
	celina_media_contract_assert( 'core/cover' === $cover['blockName'] && 902 === $cover['attrs']['id'] && str_contains( $cover['attrs']['className'], 'celina-media-cover' ), 'CSS photo group becomes native cover' );
	celina_media_contract_assert( 'hours' === $cover['attrs']['anchor'] && 'Hours' === $cover['attrs']['metadata']['name'] && 'constrained' === $cover['attrs']['layout']['type'] && str_contains( $converted, $inner ), 'cover preserves inner blocks, anchor, metadata and layout' );
	celina_media_contract_assert( $converted === celina_media_transform_content( $converted, $map ), 'cover conversion idempotent' );
	celina_media_contract_assert( strpos( $converted, '<img ' ) < strpos( $converted, '<span aria-hidden=' ), 'current native cover saves image before overlay' );
}

$native_cover = '<!-- wp:cover ' . wp_json_encode( [ 'url' => $source, 'focalPoint' => [ 'x' => 0.2, 'y' => 0.7 ] ] ) . ' --><div class="wp-block-cover"><img class="wp-block-cover__image-background" src="' . $source . '" alt="" style="object-position:20% 70%"/><div class="wp-block-cover__inner-container">' . $existing . '</div></div><!-- /wp:cover -->';
$native_result = parse_blocks( celina_media_transform_content( $native_cover, $map ) )[0];
celina_media_contract_assert( 901 === $native_result['attrs']['id'] && $upload === $native_result['attrs']['url'] && 0.2 === $native_result['attrs']['focalPoint']['x'] && 77 === $native_result['innerBlocks'][0]['attrs']['id'], 'native cover preserves nested images and focal crop' );
$media_text = '<!-- wp:media-text ' . wp_json_encode( [ 'mediaUrl' => $source, 'mediaType' => 'image', 'mediaWidth' => 35, 'mediaPosition' => 'right' ] ) . ' --><div class="wp-block-media-text has-media-on-the-right"><figure class="wp-block-media-text__media"><img src="' . $source . '" alt="Preserved"/></figure><div class="wp-block-media-text__content">' . $existing . '</div></div><!-- /wp:media-text -->';
$media_text_result = parse_blocks( celina_media_transform_content( $media_text, $map ) )[0];
celina_media_contract_assert( 901 === $media_text_result['attrs']['mediaId'] && $upload === $media_text_result['attrs']['mediaUrl'] && 35 === $media_text_result['attrs']['mediaWidth'], 'native media-text remains native with sizing preserved' );
celina_media_contract_assert( str_contains( $media_text_result['innerHTML'], 'wp-image-901 size-full' ), 'native media-text attachment save includes its default size class' );
$parallax = '<!-- wp:cover ' . wp_json_encode( [ 'url' => $source, 'hasParallax' => true ] ) . ' --><div class="wp-block-cover has-parallax"><div class="wp-block-cover__image-background has-parallax" style="background-position:20% 70%;background-image:url(' . $source . ')"></div><span aria-hidden="true" class="wp-block-cover__background has-background-dim-100 has-background-dim"></span><div class="wp-block-cover__inner-container">' . $existing . '</div></div><!-- /wp:cover -->';
$parallax_result = celina_media_transform_content( $parallax, $map );
celina_media_contract_assert( str_contains( $parallax_result, 'background-image:url(' . $upload . ')' ) && str_contains( $parallax_result, 'background-position:20% 70%' ) && 901 === parse_blocks( $parallax_result )[0]['attrs']['id'], 'native parallax cover preserves positioning while attaching its background' );

foreach ( [ '<!-- wp:image {broken} --><img src="' . $source . '"/><!-- /wp:image -->', '<!-- wp:image --><img src="' . $source . '"/>', '<!-- wp:image --><figure><img src="' . $source . '"/><img src="' . $source . '"/></figure><!-- /wp:image -->', '<!-- wp:paragraph {"className":"celina-contact-card--hours"} --><p>Unsupported photo placement</p><!-- /wp:paragraph -->', '<!-- wp:celina/industry-card ' . wp_json_encode( [ 'imageUrl' => $source ] ) . ' --><img src="' . $source . '"/><!-- /wp:celina/industry-card -->' ] as $malformed ) {
	$threw = false;
	try {
		celina_media_transform_content( $malformed, $map );
	} catch ( RuntimeException $exception ) {
		$threw = true;
	}
	celina_media_contract_assert( $threw, 'malformed target or unsupported photo placement blocks migration' );
}

WP_CLI::success( 'Media transformer contracts passed.' );
