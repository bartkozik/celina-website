<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function celina_media_source_filename( string $url ): ?string {
	if ( '' === $url || preg_match( '/[\x00-\x20\\\\%?#]/', $url ) ) {
		return null;
	}
	$parts = wp_parse_url( $url );
	if ( false === $parts || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
		return null;
	}
	if ( isset( $parts['host'] ) ) {
		if ( ! isset( $parts['scheme'] ) || ! in_array( strtolower( $parts['scheme'] ), [ 'http', 'https' ], true ) ) {
			return null;
		}
		$allowed = false;
		foreach ( [ home_url(), site_url(), get_stylesheet_directory_uri() ] as $base ) {
			$origin = wp_parse_url( $base );
			if ( strtolower( $parts['host'] ) === strtolower( $origin['host'] ?? '' ) && ( $parts['port'] ?? null ) === ( $origin['port'] ?? null ) ) {
				$allowed = true;
			}
		}
		if ( ! $allowed ) {
			return null;
		}
	} elseif ( isset( $parts['scheme'] ) || '/' !== substr( $url, 0, 1 ) || '//' === substr( $url, 0, 2 ) ) {
		return null;
	}
	$directory = rtrim( (string) wp_parse_url( get_stylesheet_directory_uri(), PHP_URL_PATH ), '/' ) . '/assets/images/content/';
	$path = $parts['path'] ?? '';
	if ( 0 !== strpos( $path, $directory ) ) {
		return null;
	}
	$file = substr( $path, strlen( $directory ) );
	return preg_match( '/^[a-zA-Z0-9][a-zA-Z0-9_-]*\.(?:webp|png|jpe?g|gif|avif)$/D', $file ) ? $file : null;
}

function celina_media_legacy_hero_photos(): array {
	return [
		'celina-hero--home-photo' => 'portfolio-image-29.png',
		'celina-hero--about-photo' => 'portfolio-image-14.png',
		'celina-hero--why-photo' => 'portfolio-image-37.png',
		'celina-hero--capabilities-photo' => 'portfolio-image-15.png',
		'celina-hero--markets-photo' => 'portfolio-image-13.png',
		'celina-hero--community-photo' => 'portfolio-image-20.png',
		'celina-hero--careers-photo' => 'portfolio-image-12.png',
		'celina-hero--news-photo' => 'portfolio-image-30.png',
		'celina-hero--resources-photo' => 'portfolio-image-21.png',
	];
}

function celina_media_content_sources( string $content ): array {
	$files = [];
	$visit = function ( array $blocks ) use ( &$visit, &$files ): void {
		foreach ( $blocks as $block ) {
			$name = $block['blockName'];
			$attrs = $block['attrs'];
			$classes = preg_split( '/\s+/', trim( (string) ( $attrs['className'] ?? '' ) ) );
			foreach ( celina_media_legacy_hero_photos() as $class => $file ) {
				if ( in_array( $class, $classes, true ) && empty( $attrs['backgroundUrl'] ) ) {
					if ( 'celina/hero' !== $name ) {
						throw new RuntimeException( 'Unsupported legacy hero photograph placement.' );
					}
					$files[ $file ] = true;
				}
			}
			if ( array_intersect( [ 'celina-sales-contact-heading', 'celina-contact-card--hours' ], $classes ) && ! in_array( 'celina-media-cover', $classes, true ) ) {
				if ( 'core/group' !== $name ) {
					throw new RuntimeException( 'Unsupported legacy contact photograph placement.' );
				}
				$files['portfolio-image-11.png'] = true;
			}
			foreach ( [ 'imageUrl', 'backgroundUrl', 'url', 'mediaUrl' ] as $key ) {
				$file = celina_media_source_filename( (string) ( $attrs[ $key ] ?? '' ) );
				if ( null !== $file ) {
					$placements = [ 'imageUrl' => [ 'celina/text-image-split', 'celina/industry-card', 'celina/facility-card' ], 'backgroundUrl' => [ 'celina/hero' ], 'url' => [ 'core/image', 'core/cover' ], 'mediaUrl' => [ 'core/media-text' ] ];
					if ( ! in_array( $name, $placements[ $key ], true ) ) {
						throw new RuntimeException( 'Unsupported theme photograph attribute on ' . ( $name ?? 'freeform' ) );
					}
					$files[ $file ] = true;
				}
			}
			$processor = new WP_HTML_Tag_Processor( $block['innerHTML'] );
			while ( $processor->next_tag() ) {
				$file = celina_media_source_filename( (string) ( $processor->get_attribute( 'src' ) ?? '' ) );
				if ( null !== $file ) {
					if ( 'IMG' !== $processor->get_tag() || ! in_array( $name, [ 'core/image', 'core/cover', 'core/media-text' ], true ) ) {
						throw new RuntimeException( 'Unsupported saved theme photograph on ' . ( $name ?? 'freeform' ) );
					}
					$files[ $file ] = true;
				}
				$style = (string) ( $processor->get_attribute( 'style' ) ?? '' );
				preg_match_all( '/url\([\s\x22\x27]*([^\s\x22\x27)]+)[\s\x22\x27]*\)/i', $style, $urls );
				foreach ( $urls[1] as $url ) {
					$file = celina_media_source_filename( $url );
					if ( null !== $file ) {
						if ( 'celina/hero' !== $name && ! ( 'core/cover' === $name && $processor->has_class( 'wp-block-cover__image-background' ) ) ) {
							throw new RuntimeException( 'Unsupported saved theme photograph background on ' . ( $name ?? 'freeform' ) );
						}
						$files[ $file ] = true;
					}
				}
			}
			$visit( $block['innerBlocks'] );
		}
	};
	$visit( parse_blocks( $content ) );
	$result = array_keys( $files );
	sort( $result, SORT_STRING );
	return $result;
}

function celina_media_mapped_file( ?string $file, array $map ): ?array {
	if ( null === $file || ! isset( $map[ $file ] ) ) {
		return null;
	}
	$media = $map[ $file ];
	if ( ! is_array( $media ) || ! isset( $media['id'], $media['url'] ) || ! is_int( $media['id'] ) || $media['id'] < 1 || ! is_string( $media['url'] ) || ! preg_match( '#^https?://#', $media['url'] ) || esc_url_raw( $media['url'] ) !== $media['url'] ) {
		throw new RuntimeException( 'Invalid media mapping for ' . $file );
	}
	return $media;
}

/**
 * Preserve raw delimiter/HTML bytes for every unchanged block, including siblings.
 * The delimiter scanner uses WordPress's comment grammar, but rejects malformed
 * nesting rather than allowing the recovery parser to discard targeted content.
 */
function celina_media_transform_content( string $content, array $map ): string {
	if ( [] === $map || ! str_contains( $content, '<!--' ) ) {
		return $content;
	}
	preg_match_all( '/<!--\s*(\/?)wp:([a-z][a-z0-9_-]*(?:\/[a-z][a-z0-9_-]*)?)(\s.*?)?-->/s', $content, $tokens, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
	$index = 0;
	$position = 0;
	$walk = function ( ?string $parent = null, int $depth = 0 ) use ( &$walk, &$index, &$position, $tokens, $content, $map ): array {
		if ( $depth > 100 ) {
			throw new RuntimeException( 'Media migration block nesting exceeds 100.' );
		}
		$output = '';
		while ( isset( $tokens[ $index ] ) ) {
			$token = $tokens[ $index++ ];
			$raw = $token[0][0];
			$output .= substr( $content, $position, $token[0][1] - $position );
			$position = $token[0][1] + strlen( $raw );
			$name = $token[2][0];
			if ( '/' === $token[1][0] ) {
				if ( $parent !== $name ) {
					throw new RuntimeException( 'Unbalanced block delimiter during media migration: ' . $name );
				}
				return [ $output, $raw ];
			}
			$tail = trim( $token[3][0] ?? '' );
			$self = str_ends_with( $tail, '/' );
			$json = $self ? rtrim( substr( $tail, 0, -1 ) ) : $tail;
			$attrs = '' === $json ? [] : json_decode( $json, true );
			if ( ! is_array( $attrs ) || ( '' !== $json && '{' !== substr( $json, 0, 1 ) ) ) {
				throw new RuntimeException( 'Malformed attributes during media migration: ' . $name );
			}
			[ $body, $close ] = $self ? [ '', '' ] : $walk( $name, $depth + 1 );
			$original_attrs = $attrs;
			$original_name = $name;
			$body = celina_media_transform_block( $name, $attrs, $body, $map );
			if ( $attrs !== $original_attrs || $name !== $original_name ) {
				$raw = '<!-- wp:' . $name . ( $attrs ? ' ' . serialize_block_attributes( $attrs ) : '' ) . ( $self ? ' /-->' : ' -->' );
				$close = $self ? '' : '<!-- /wp:' . $name . ' -->';
			}
			$output .= $raw . $body . $close;
		}
		if ( null !== $parent ) {
			throw new RuntimeException( 'Unclosed block during media migration: ' . $parent );
		}
		$output .= substr( $content, $position );
		return [ $output, '' ];
	};
	return $walk()[0];
}

function celina_media_transform_image( string $html, array $map, ?array &$media, ?string $size = null ): string {
	$processor = new WP_HTML_Tag_Processor( $html );
	$images = 0;
	$found = null;
	$nested = 0;
	while ( $processor->next_token() ) {
		if ( '#comment' === $processor->get_token_type() ) {
			$comment = trim( $processor->get_modifiable_text() );
			if ( str_starts_with( $comment, '/wp:' ) ) {
				$nested--;
			} elseif ( str_starts_with( $comment, 'wp:' ) && ! str_ends_with( $comment, '/' ) ) {
				$nested++;
			}
		}
		if ( 0 !== $nested || '#tag' !== $processor->get_token_type() || 'IMG' !== $processor->get_tag() ) {
			continue;
		}
		$images++;
		$source = $processor->get_attribute( 'src' );
		$candidate = is_string( $source ) ? celina_media_mapped_file( celina_media_source_filename( $source ), $map ) : null;
		if ( null !== $candidate ) {
			$found = $candidate;
			$processor->set_bookmark( 'celina_media' );
		}
	}
	if ( null === $found ) {
		return $html;
	}
	if ( 1 !== $images ) {
		throw new RuntimeException( 'Target image block must contain exactly one image.' );
	}
	$processor->seek( 'celina_media' );
	$processor->set_attribute( 'src', $found['url'] );
	foreach ( $processor->class_list() as $class ) {
		if ( preg_match( '/^wp-image-\d+$/D', $class ) ) {
			$processor->remove_class( $class );
		}
	}
	$processor->add_class( 'wp-image-' . $found['id'] );
	if ( null !== $size ) {
		$processor->add_class( 'size-' . $size );
	}
	// Stale source candidates must not override the newly selected library image.
	$processor->remove_attribute( 'srcset' );
	$media = $found;
	return $processor->get_updated_html();
}

function celina_media_transform_block( string &$name, array &$attrs, string $html, array $map ): string {
	$name_short = str_starts_with( $name, 'core/' ) ? substr( $name, 5 ) : $name;
	$classes = preg_split( '/\s+/', trim( (string) ( $attrs['className'] ?? '' ) ) );
	$legacy_heroes = array_intersect( array_keys( celina_media_legacy_hero_photos() ), $classes );
	$legacy_groups = array_intersect( [ 'celina-sales-contact-heading', 'celina-contact-card--hours' ], $classes );
	if ( $legacy_heroes && 'celina/hero' !== $name ) {
		throw new RuntimeException( 'Legacy hero photo class is on an unsupported block: ' . $name );
	}
	if ( $legacy_groups && ! in_array( $name_short, [ 'group', 'cover' ], true ) ) {
		throw new RuntimeException( 'Legacy contact photo class is on an unsupported block: ' . $name );
	}
	if ( 'group' === $name_short && $legacy_groups ) {
		$media = celina_media_mapped_file( 'portfolio-image-11.png', $map );
		return $media ? celina_media_group_to_cover( $name, $attrs, $html, $media, in_array( 'celina-contact-card--hours', $classes, true ) ) : $html;
	}
	if ( in_array( $name, [ 'celina/text-image-split', 'celina/industry-card', 'celina/facility-card' ], true ) ) {
		$media = celina_media_mapped_file( celina_media_source_filename( (string) ( $attrs['imageUrl'] ?? '' ) ), $map );
		if ( $media ) {
			if ( '' !== trim( $html ) ) {
				throw new RuntimeException( 'Target dynamic CELINA media block contains unsupported saved markup: ' . $name );
			}
			$attrs['imageUrl'] = $media['url'];
			$attrs['imageId'] = $media['id'];
		}
		return $html;
	}
	if ( 'celina/hero' === $name ) {
		$background = (string) ( $attrs['backgroundUrl'] ?? '' );
		$media = celina_media_mapped_file( celina_media_source_filename( $background ), $map );
		if ( '' === $background && $legacy_heroes ) {
			if ( count( $legacy_heroes ) !== 1 ) {
				throw new RuntimeException( 'Multiple legacy hero photographs on one block.' );
			}
			$media = celina_media_mapped_file( celina_media_legacy_hero_photos()[ reset( $legacy_heroes ) ], $map );
		}
		// An explicit upload already owns the hero background. Retire its old CSS
		// fallback too, so the editor's Remove action cannot reveal that photograph.
		$cleanup_existing = $legacy_heroes && '' !== $background && null === celina_media_source_filename( $background );
		if ( ! $media && ! $cleanup_existing ) {
			return $html;
		}
		if ( $media ) {
			$attrs['backgroundUrl'] = $media['url'];
			$attrs['backgroundId'] = $media['id'];
		}
		if ( $legacy_heroes ) {
			$attrs['className'] = implode( ' ', array_diff( $classes, $legacy_heroes ) );
			if ( '' === $attrs['className'] ) {
				unset( $attrs['className'] );
			}
		}
		$processor = new WP_HTML_Tag_Processor( $html );
		if ( ! $processor->next_tag( 'SECTION' ) ) {
			throw new RuntimeException( 'Target hero has no saved section.' );
		}
		foreach ( $legacy_heroes as $class ) {
			$processor->remove_class( $class );
		}
		if ( ! $media ) {
			return $processor->get_updated_html();
		}
		$style = (string) $processor->get_attribute( 'style' );
		$style = preg_replace( '/(?:^|;)\s*background-image\s*:[^;]*(?:;|$)/i', ';', $style );
		$style = trim( $style, '; ' );
		$processor->set_attribute( 'style', ( $style ? $style . ';' : '' ) . 'background-image:url(' . $media['url'] . ')' );
		$html = $processor->get_updated_html();
		if ( ! str_contains( $html, 'class="celina-hero__overlay"' ) ) {
			$html = preg_replace( '/(<section\b[^>]*>)/i', '$1<div class="celina-hero__overlay" aria-hidden="true"></div>', $html, 1 );
		}
		return $html;
	}
	if ( in_array( $name_short, [ 'image', 'cover', 'media-text' ], true ) ) {
		$media = null;
		$html = celina_media_transform_image( $html, $map, $media, 'media-text' === $name_short ? (string) ( $attrs['mediaSizeSlug'] ?? 'full' ) : null );
		$url_key = 'media-text' === $name_short ? 'mediaUrl' : 'url';
		$id_key = 'media-text' === $name_short ? 'mediaId' : 'id';
		$attribute_media = celina_media_mapped_file( celina_media_source_filename( (string) ( $attrs[ $url_key ] ?? '' ) ), $map );
		if ( 'cover' === $name_short && $attribute_media && ! $media && ( ! empty( $attrs['hasParallax'] ) || ! empty( $attrs['isRepeated'] ) ) ) {
			$processor = new WP_HTML_Tag_Processor( $html );
			if ( $processor->next_tag( [ 'tag_name' => 'DIV', 'class_name' => 'wp-block-cover__image-background' ] ) ) {
				$style = (string) $processor->get_attribute( 'style' );
				$old = 'url(' . $attrs['url'] . ')';
				if ( 1 !== substr_count( $style, $old ) ) {
					throw new RuntimeException( 'Native cover background attributes and saved style disagree.' );
				}
				$processor->set_attribute( 'style', str_replace( $old, 'url(' . $attribute_media['url'] . ')', $style ) );
				foreach ( $processor->class_list() as $class ) {
					if ( preg_match( '/^wp-image-\d+$/D', $class ) ) {
						$processor->remove_class( $class );
					}
				}
				$processor->add_class( 'wp-image-' . $attribute_media['id'] );
				$html = $processor->get_updated_html();
				$media = $attribute_media;
			}
		}
		if ( $attribute_media && ! $media ) {
			throw new RuntimeException( 'Target native media has no matching saved image: ' . $name );
		}
		if ( $attribute_media && $media && $attribute_media !== $media ) {
			throw new RuntimeException( 'Native media attributes and saved image disagree: ' . $name );
		}
		if ( $media ) {
			$attrs[ $id_key ] = $media['id'];
			if ( 'image' !== $name_short || isset( $attrs['url'] ) ) {
				$attrs[ $url_key ] = $media['url'];
			}
		}
	}
	return $html;
}

function celina_media_group_to_cover( string &$name, array &$attrs, string $html, array $media, bool $hours ): string {
	if ( ! preg_match( '/^(\s*)<(div|section|article|aside|header|footer|main)\b([^>]*)>([\s\S]*)<\/\2>(\s*)$/i', $html, $wrapper ) ) {
		throw new RuntimeException( 'Unsupported saved wrapper for contact photograph.' );
	}
	// Block supports are shared by group/cover. Reject unsupported group-specific
	// semantics rather than silently dropping them during conversion.
	if ( isset( $attrs['backgroundColor'] ) || isset( $attrs['gradient'] ) || isset( $attrs['style']['color']['background'] ) || isset( $attrs['style']['background'] ) ) {
		throw new RuntimeException( 'Contact photo group has an additional background requiring review.' );
	}
	$name = 'cover';
	$attrs['className'] = trim( (string) ( $attrs['className'] ?? '' ) . ' celina-media-cover' );
	$attrs['url'] = $media['url'];
	$attrs['id'] = $media['id'];
	$attrs['dimRatio'] = 100;
	$attrs['customGradient'] = 'linear-gradient(' . ( $hours ? '270' : '90' ) . 'deg, rgb(61 69 67 / 94%) 0%, rgb(61 69 67 / 72%) 55%, rgb(61 69 67 / 40%) 100%)';
	$attrs['isUserOverlayColor'] = true;
	$attrs['isDark'] = true;
	$attrs['sizeSlug'] = 'full';
	$processor = new WP_HTML_Tag_Processor( '<' . $wrapper[2] . $wrapper[3] . '>' );
	$processor->next_tag();
	$processor->remove_class( 'wp-block-group' );
	$processor->add_class( 'wp-block-cover' );
	$processor->add_class( 'celina-media-cover' );
	$opening = $processor->get_updated_html();
	$overlay = '<span aria-hidden="true" class="wp-block-cover__background has-background-dim-100 has-background-dim wp-block-cover__gradient-background has-background-gradient" style="background:' . esc_attr( $attrs['customGradient'] ) . '"></span>';
	$image = '<img class="wp-block-cover__image-background wp-image-' . $media['id'] . ' size-full" alt="" src="' . esc_url( $media['url'] ) . '" data-object-fit="cover"/>';
	$inner = $wrapper[4];
	if ( preg_match( '/^\s*<div class="wp-block-group__inner-container">([\s\S]*)<\/div>\s*$/', $inner, $old_inner ) ) {
		$inner = $old_inner[1];
	}
	return $wrapper[1] . $opening . $image . $overlay . '<div class="wp-block-cover__inner-container">' . $inner . '</div></' . $wrapper[2] . '>' . $wrapper[5];
}
