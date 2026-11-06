<?php
defined( 'ABSPATH' ) || exit;

function celina_article_job_title_field( WP_User $user ): void {
	if ( ! current_user_can( 'edit_user', $user->ID ) ) {
		return;
	}

	$job_title = (string) get_user_meta( $user->ID, 'celina_job_title', true );
	?>
	<h2><?php echo esc_html__( 'Article author details', 'celina' ); ?></h2>
	<?php wp_nonce_field( 'celina_article_job_title_' . $user->ID, 'celina_article_job_title_nonce' ); ?>
	<table class="form-table" role="presentation">
		<tr>
			<th>
				<label for="celina-job-title">
					<?php echo esc_html__( 'Job title', 'celina' ); ?>
				</label>
			</th>
			<td>
				<input
					type="text"
					class="regular-text"
					id="celina-job-title"
					name="celina_job_title"
					value="<?php echo esc_attr( $job_title ); ?>"
				>
				<p class="description">
					<?php echo esc_html__( 'Shown with this author’s name on published articles.', 'celina' ); ?>
				</p>
			</td>
		</tr>
	</table>
	<?php
}

add_action( 'show_user_profile', 'celina_article_job_title_field' );
add_action( 'edit_user_profile', 'celina_article_job_title_field' );

function celina_article_save_job_title( int $user_id ): void {
	if ( $user_id <= 0 || ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}

	$nonce = isset( $_POST['celina_article_job_title_nonce'] ) &&
		is_string( $_POST['celina_article_job_title_nonce'] )
		? sanitize_text_field( wp_unslash( $_POST['celina_article_job_title_nonce'] ) )
		: '';

	if (
		'' === $nonce ||
		! wp_verify_nonce( $nonce, 'celina_article_job_title_' . $user_id ) ||
		! isset( $_POST['celina_job_title'] ) ||
		! is_string( $_POST['celina_job_title'] )
	) {
		return;
	}

	$job_title = sanitize_text_field( wp_unslash( $_POST['celina_job_title'] ) );

	if ( '' === $job_title ) {
		delete_user_meta( $user_id, 'celina_job_title' );
		return;
	}

	update_user_meta( $user_id, 'celina_job_title', $job_title );
}

add_action( 'personal_options_update', 'celina_article_save_job_title' );
add_action( 'edit_user_profile_update', 'celina_article_save_job_title' );

function celina_article_is_html_space( string $character ): bool {
	return '' !== $character && false !== strpos( "\x09\x0A\x0C\x0D\x20", $character );
}

/** @return array{closing: bool, complete: bool, end: int, name: string, self_closing: bool, valid: bool}|null */
function celina_article_shortcode_token_at( string $content, int $start ): ?array {
	$length = strlen( $content );

	if ( $start < 0 || $start >= $length || '[' !== $content[ $start ] ) {
		return null;
	}
	if ( $start + 1 < $length && '[' === $content[ $start + 1 ] ) {
		return null;
	}

	$cursor  = $start + 1;
	$closing = false;
	if ( $cursor < $length && '/' === $content[ $cursor ] ) {
		$closing = true;
		++$cursor;
	}

	$name_start = $cursor;
	while ( $cursor < $length ) {
		$character = $content[ $cursor ];
		if (
			ord( $character ) <= 32 ||
			false !== strpos( '<>&/[]=', $character )
		) {
			break;
		}
		++$cursor;
	}

	if ( $cursor === $name_start ) {
		return null;
	}
	$name_end = $cursor;

	$quote              = '';
	$closing_tail_valid = true;
	while ( $cursor < $length ) {
		$character = $content[ $cursor ];
		if ( $closing && ']' !== $character && ! celina_article_is_html_space( $character ) ) {
			$closing_tail_valid = false;
		}
		if ( '' !== $quote ) {
			if ( $character === $quote ) {
				$quote = '';
			}
			++$cursor;
			continue;
		}

		if ( '"' === $character || "'" === $character ) {
			$quote = $character;
			++$cursor;
			continue;
		}

		if ( ']' === $character ) {
			$token_end = $cursor + 1;
			$self_closing_cursor = $cursor - 1;
			while (
				$self_closing_cursor >= $name_start &&
				celina_article_is_html_space( $content[ $self_closing_cursor ] )
			) {
				--$self_closing_cursor;
			}

			return [
				'closing'      => $closing,
				'complete'     => true,
				'end'          => $token_end,
				'name'         => substr( $content, $name_start, $name_end - $name_start ),
				'self_closing' => $self_closing_cursor >= $name_start && '/' === $content[ $self_closing_cursor ],
				'valid'        => ! $closing || $closing_tail_valid,
			];
		}

		++$cursor;
	}

	return [
		'closing'      => $closing,
		'complete'     => false,
		'end'          => $length,
		'name'         => substr( $content, $name_start, $name_end - $name_start ),
		'self_closing' => false,
		'valid'        => false,
	];
}

/** @return array{end: int, visible: string}|null */
function celina_article_escaped_shortcode_at( string $content, int $start ): ?array {
	$length = strlen( $content );
	if (
		$start < 0 ||
		$start + 1 >= $length ||
		'[' !== $content[ $start ] ||
		'[' !== $content[ $start + 1 ]
	) {
		return null;
	}

	$opening = celina_article_shortcode_token_at( $content, $start + 1 );
	if ( ! is_array( $opening ) ) {
		return [
			'end'     => min( $length, $start + 2 ),
			'visible' => substr( $content, $start, 2 ),
		];
	}
	if ( false === $opening['complete'] ) {
		return [
			'end'     => $opening['end'],
			'visible' => substr( $content, $start, $opening['end'] - $start ),
		];
	}
	if ( false === $opening['valid'] ) {
		return [
			'end'     => $opening['end'],
			'visible' => substr( $content, $start, $opening['end'] - $start ),
		];
	}

	if ( $opening['end'] < $length && ']' === $content[ $opening['end'] ] ) {
		return [
			'end'     => $opening['end'] + 1,
			'visible' => substr( $content, $start + 1, $opening['end'] - $start - 1 ),
		];
	}

	if ( true === $opening['closing'] || true === $opening['self_closing'] ) {
		return [
			'end'     => $opening['end'],
			'visible' => substr( $content, $start, $opening['end'] - $start ),
		];
	}

	$cursor = $opening['end'];
	$depth  = 1;
	while ( $cursor < $length ) {
		$next_token = strpos( $content, '[', $cursor );
		if ( false === $next_token ) {
			return [
				'end'     => $length,
				'visible' => substr( $content, $start ),
			];
		}
		if ( $next_token + 1 < $length && '[' === $content[ $next_token + 1 ] ) {
			$cursor = $next_token + 2;
			continue;
		}

		$token = celina_article_shortcode_token_at( $content, $next_token );
		if ( ! is_array( $token ) ) {
			$cursor = $next_token + 1;
			continue;
		}
		if ( false === $token['complete'] ) {
			return [
				'end'     => $length,
				'visible' => substr( $content, $start ),
			];
		}
		if ( false === $token['valid'] ) {
			$cursor = $token['end'];
			continue;
		}

		if ( $opening['name'] === $token['name'] ) {
			if ( true === $token['closing'] ) {
				if ( 1 === $depth ) {
					if ( $token['end'] < $length && ']' === $content[ $token['end'] ] ) {
						return [
							'end'     => $token['end'] + 1,
							'visible' => substr( $content, $start + 1, $token['end'] - $start - 1 ),
						];
					}
				} else {
					--$depth;
				}
			} elseif ( false === $token['self_closing'] ) {
				++$depth;
			}
		}

		$cursor = $token['end'];
	}

	return [
		'end'     => $length,
		'visible' => substr( $content, $start ),
	];
}

function celina_article_tokenize_readable_content( string $content ): string {
	$length = strlen( $content );
	$cursor = 0;
	$text   = '';

	while ( $cursor < $length ) {
		$character = $content[ $cursor ];

		if ( '[' === $character ) {
			if ( $cursor + 1 < $length && '[' === $content[ $cursor + 1 ] ) {
				$escaped_shortcode = celina_article_escaped_shortcode_at( $content, $cursor );
				$text              .= $escaped_shortcode['visible'];
				$cursor             = $escaped_shortcode['end'];
				continue;
			}

			$token = celina_article_shortcode_token_at( $content, $cursor );
			if ( is_array( $token ) ) {
				$text  .= true === $token['complete'] && true === $token['valid']
					? ' '
					: substr( $content, $cursor, $token['end'] - $cursor );
				$cursor = $token['end'];
				continue;
			}
		}

		$text .= $character;
		++$cursor;
	}

	return $text;
}

/**
 * Calculate reading time from serialized post content without rendering it.
 */
function celina_article_reading_minutes( string $content ): int {
	$text       = html_entity_decode(
		wp_strip_all_tags( celina_article_tokenize_readable_content( $content ) ),
		ENT_QUOTES | ENT_HTML5,
		'UTF-8'
	);
	$word_count = preg_match_all(
		'/[\p{L}\p{N}]+(?:[\x{2019}\'\-][\p{L}\p{N}]+)*/u',
		$text
	);
	$word_count = false === $word_count ? 0 : $word_count;

	return max( 1, (int) ceil( $word_count / 200 ) );
}

/**
 * @param array<int, array<string, mixed>> $blocks
 * @return array<int, array<string, mixed>>
 */
function celina_article_mark_heading_blocks( array $blocks, string $marker ): array {
	static $managed_containers = [
		'celina/article-layout',
		'core/group',
		'core/columns',
		'core/column',
		'core/cover',
		'core/media-text',
		'core/quote',
		'core/list',
		'core/list-item',
		'core/details',
	];

	foreach ( $blocks as &$block ) {
		if ( ! is_array( $block ) ) {
			continue;
		}

		if (
			'core/heading' === ( $block['blockName'] ?? null ) &&
			1 === (int) ( $block['attrs']['level'] ?? 2 )
		) {
			if ( ! isset( $block['attrs'] ) || ! is_array( $block['attrs'] ) ) {
				$block['attrs'] = [];
			}

			$block['attrs']['celinaArticleHeadingScope'] = $marker;
		}

		if (
			in_array( $block['blockName'] ?? null, $managed_containers, true ) &&
			isset( $block['innerBlocks'] ) &&
			is_array( $block['innerBlocks'] )
		) {
			$block['innerBlocks'] = celina_article_mark_heading_blocks(
				$block['innerBlocks'],
				$marker
			);
		}
	}
	unset( $block );

	return $blocks;
}

/** @param array<string, mixed> $block */
function celina_article_render_heading_block( string $block_content, array $block ): string {
	if (
		'core/heading' !== ( $block['blockName'] ?? null ) ||
		1 !== (int) ( $block['attrs']['level'] ?? 2 )
	) {
		return $block_content;
	}

	$html_space      = '\x09\x0A\x0C\x0D\x20';
	$attribute_name  = "[^\\x00-\\x20\"'\\/=<>`]+";
	$unquoted_value  = "[^\\x00-\\x20\"'=<>`]+";
	$attribute_value = '(?:"[^"]*"|\'[^\']*\'|' . $unquoted_value . ')';
	$attributes      = '(?:[' . $html_space . ']+' . $attribute_name
		. '(?:[' . $html_space . ']*=[' . $html_space . ']*' . $attribute_value . ')?)*'
		. '[' . $html_space . ']*';
	$wrapper_pattern = '~\A(?P<leading>[' . $html_space . ']*)'
		. '<(?P<opening>h1)(?P<attributes>' . $attributes . ')>'
		. '(?P<inner>.*?)'
		. '</(?P<closing>h1)[' . $html_space . ']*>'
		. '(?P<trailing>[' . $html_space . ']*)\z~is';

	if ( 1 !== preg_match( $wrapper_pattern, $block_content, $matches, PREG_OFFSET_CAPTURE ) ) {
		return $block_content;
	}

	$nested_heading_pattern = '~</?h1(?=[' . $html_space . '>/])~i';
	if ( 1 === preg_match( $nested_heading_pattern, $matches['inner'][0] ) ) {
		return $block_content;
	}

	$opening_offset = $matches['opening'][1];
	$closing_offset = $matches['closing'][1];

	return substr( $block_content, 0, $opening_offset )
		. 'h2'
		. substr( $block_content, $opening_offset + 2, $closing_offset - $opening_offset - 2 )
		. 'h2'
		. substr( $block_content, $closing_offset + 2 );
}

function celina_wordpress_sample_post_id(): int {
	static $sample_id = null;

	if ( null !== $sample_id ) {
		return $sample_id;
	}

	$sample = get_page_by_path( 'hello-world', OBJECT, 'post' );

	if (
		! $sample instanceof WP_Post ||
		'publish' !== $sample->post_status ||
		'Hello world!' !== $sample->post_title ||
		'' !== trim( $sample->post_excerpt )
	) {
		$sample_id = 0;
		return $sample_id;
	}

	$content = preg_replace(
		'/\s+/u',
		' ',
		wp_strip_all_tags( $sample->post_content )
	);

	$sample_id = 'Welcome to WordPress. This is your first post. Edit or delete it, then start writing!' === trim( (string) $content )
		? $sample->ID
		: 0;

	return $sample_id;
}

function celina_exclude_wordpress_sample_post( WP_Query $query ): void {
	if ( is_admin() ) {
		return;
	}

	$sample_id = celina_wordpress_sample_post_id();

	if ( $sample_id <= 0 ) {
		return;
	}

	$included_ids = array_values(
		array_filter(
			array_map( 'absint', (array) $query->get( 'post__in' ) )
		)
	);

	if ( [] !== $included_ids ) {
		$included_ids = array_values( array_diff( $included_ids, [ $sample_id ] ) );
		$query->set( 'post__in', [] !== $included_ids ? $included_ids : [ 0 ] );
		return;
	}

	$excluded_ids   = array_map( 'absint', (array) $query->get( 'post__not_in' ) );
	$excluded_ids[] = $sample_id;
	$query->set( 'post__not_in', array_values( array_unique( $excluded_ids ) ) );
}

add_action( 'pre_get_posts', 'celina_exclude_wordpress_sample_post' );

function celina_article_adjacent_post( WP_Post $post, string $direction ): ?WP_Post {
	global $wpdb;

	if ( ! in_array( $direction, [ 'previous', 'next' ], true ) ) {
		return null;
	}

	$order    = 'previous' === $direction ? 'DESC' : 'ASC';
	$operator = 'previous' === $direction ? '<' : '>';
	$query    = new WP_Query();
	// Match the news list's date/ID ordering, including equal publication times.
	$where = static function ( string $where, WP_Query $candidate ) use ( $query, $post, $operator, $wpdb ): string {
		if ( $candidate !== $query ) {
			return $where;
		}
		return $where . $wpdb->prepare(
			" AND ({$wpdb->posts}.post_date {$operator} %s OR ({$wpdb->posts}.post_date = %s AND {$wpdb->posts}.ID {$operator} %d))",
			$post->post_date,
			$post->post_date,
			$post->ID
		);
	};

	add_filter( 'posts_where', $where, 10, 2 );
	try {
		$query->query(
			[
				'ignore_sticky_posts'  => true,
				'no_found_rows'        => true,
				'orderby'              => [ 'date' => $order, 'ID' => $order ],
				'post__not_in'         => array_values(
					array_filter( [ $post->ID, celina_wordpress_sample_post_id() ] )
				),
				'post_status'          => 'publish',
				'post_type'            => $post->post_type,
				'posts_per_page'       => 1,
				'suppress_filters'     => false,
			]
		);
	} finally {
		remove_filter( 'posts_where', $where, 10 );
	}

	$adjacent = $query->posts[0] ?? null;

	return $adjacent instanceof WP_Post ? $adjacent : null;
}

function celina_article_primary_category( WP_Post $post ): ?WP_Term {
	if (
		function_exists( 'celina_managed_news_index_attributes' ) &&
		function_exists( 'celina_news_grid_attributes' ) &&
		function_exists( 'celina_news_grid_primary_category' )
	) {
		try {
			$attributes = celina_managed_news_index_attributes();

			if ( is_array( $attributes ) ) {
				$config   = celina_news_grid_attributes( $attributes );
				$category = celina_news_grid_primary_category( $post, $config );

				if ( $category instanceof WP_Term ) {
					return $category;
				}
			}
		} catch ( Throwable $error ) {
		}
	}

	$assigned_ids = wp_get_post_categories( $post->ID, [ 'fields' => 'ids' ] );

	if ( is_wp_error( $assigned_ids ) || ! is_array( $assigned_ids ) ) {
		return null;
	}

	$default_category = (int) get_option( 'default_category' );
	$assigned_ids     = array_values(
		array_unique(
			array_filter(
				array_map( 'intval', $assigned_ids ),
				static function ( int $category_id ) use ( $default_category ): bool {
					return $category_id > 0 && $category_id !== $default_category;
				}
			)
		)
	);
	sort( $assigned_ids, SORT_NUMERIC );

	foreach ( $assigned_ids as $category_id ) {
		$category = get_term( $category_id, 'category' );

		if ( $category instanceof WP_Term && ! is_wp_error( $category ) ) {
			return $category;
		}
	}

	return null;
}

function celina_article_featured_image( WP_Post $post ): string {
	$image = '';

	if ( get_post_thumbnail_id( $post ) > 0 ) {
		$image = get_the_post_thumbnail(
			$post,
			'large',
			[
				'class'   => 'celina-article-media__image',
				'loading' => 'eager',
			]
		);
	}

	if ( '' === trim( $image ) ) {
		$image = sprintf(
			'<img class="celina-article-media__image" src="%s" alt="" loading="eager">',
			esc_url( get_theme_file_uri( 'assets/images/article-placeholder.svg' ) )
		);
		$figure_class = 'celina-article-media celina-article-media--placeholder';
	} else {
		$figure_class = 'celina-article-media';
	}

	return wp_kses(
		sprintf(
			'<figure class="%1$s">%2$s</figure>',
			esc_attr( $figure_class ),
			$image
		),
		[
			'figure' => [
				'class' => true,
			],
			'img'    => [
				'alt'           => true,
				'class'         => true,
				'decoding'      => true,
				'fetchpriority' => true,
				'height'        => true,
				'loading'       => true,
				'sizes'         => true,
				'src'           => true,
				'srcset'        => true,
				'width'         => true,
			],
		]
	);
}
