<?php
defined( 'ABSPATH' ) || exit;

function celina_news_grid_category_is_in_scope( int $term_id, int $base_category ): bool {
    if ( $term_id <= 0 ) {
        return false;
    }

    $term = get_term( $term_id, 'category' );
    if ( ! $term instanceof WP_Term || is_wp_error( $term ) ) {
        return false;
    }

    if ( $base_category <= 0 ) {
        return true;
    }

    $base_term = get_term( $base_category, 'category' );
    if ( ! $base_term instanceof WP_Term || is_wp_error( $base_term ) ) {
        return false;
    }

    if ( $term_id === $base_category ) {
        return true;
    }

    $ancestors = get_ancestors( $term_id, 'category', 'taxonomy' );

    return is_array( $ancestors ) && in_array( $base_category, array_map( 'intval', $ancestors ), true );
}

function celina_news_grid_category_has_published_posts( WP_Term $term ): bool {
    if ( 'category' !== $term->taxonomy ) {
        return false;
    }

    if ( (int) $term->count > 0 ) {
        return true;
    }

    static $descendant_content = [];
    $cache_key = get_current_blog_id() . ':' . $term->term_id;

    if ( array_key_exists( $cache_key, $descendant_content ) ) {
        return $descendant_content[ $cache_key ];
    }

    $descendant_ids = get_term_children( $term->term_id, 'category' );
    if ( is_wp_error( $descendant_ids ) || [] === $descendant_ids ) {
        $descendant_content[ $cache_key ] = false;
        return false;
    }

    $non_empty_descendants = get_terms(
        [
            'taxonomy'   => 'category',
            'hide_empty' => true,
            'include'    => array_map( 'intval', $descendant_ids ),
            'fields'     => 'ids',
            'number'     => 1,
        ]
    );
    $has_content          = ! is_wp_error( $non_empty_descendants ) &&
        is_array( $non_empty_descendants ) &&
        [] !== $non_empty_descendants;
    $descendant_content[ $cache_key ] = $has_content;

    return $has_content;
}

function celina_news_grid_navigation_category_ids( array $values, int $base_category ): array {
    $default_category = (int) get_option( 'default_category' );
    $category_ids     = [];

    foreach ( $values as $value ) {
        if ( ! is_numeric( $value ) ) {
            continue;
        }

        $category_id = (int) $value;
        if (
            $category_id <= 0 ||
            $category_id === $default_category ||
            in_array( $category_id, $category_ids, true )
        ) {
            continue;
        }

        $term = get_term( $category_id, 'category' );
        if (
            ! $term instanceof WP_Term ||
            is_wp_error( $term ) ||
            ! celina_news_grid_category_is_in_scope( $category_id, $base_category ) ||
            ! celina_news_grid_category_has_published_posts( $term )
        ) {
            continue;
        }

        $category_ids[] = $category_id;
    }

    return $category_ids;
}

function celina_news_grid_attributes( array $attributes ): array {
    $heading = 'Latest News';
    if ( isset( $attributes['heading'] ) && is_string( $attributes['heading'] ) ) {
        $heading = sanitize_text_field( $attributes['heading'] );
    }

    $count = 3;
    if ( isset( $attributes['count'] ) && is_numeric( $attributes['count'] ) ) {
        $count = (int) $attributes['count'];
    }

    $category = 0;
    if ( isset( $attributes['category'] ) && is_numeric( $attributes['category'] ) ) {
        $category = max( 0, (int) $attributes['category'] );
    }

    $post_id = 0;
    if ( isset( $attributes['postId'] ) && is_numeric( $attributes['postId'] ) ) {
        $post_id = max( 0, (int) $attributes['postId'] );
    }

    $layout = isset( $attributes['layout'] ) && is_string( $attributes['layout'] )
        ? $attributes['layout']
        : 'grid';
    $layout = in_array( $layout, [ 'grid', 'featured', 'news-index' ], true )
        ? $layout
        : 'grid';
    $page = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );

    return [
        'heading'    => $heading,
        'category'   => $category,
        'count'      => max( 1, min( 12, $count ) ),
        'layout'     => $layout,
        'postId'     => $post_id,
        'pagination' => 'news-index' === $layout ||
            ( 'grid' === $layout && true === ( $attributes['pagination'] ?? false ) ),
        'page'       => $page,
        'navigationCategories' => celina_news_grid_navigation_category_ids(
            isset( $attributes['navigationCategories'] ) && is_array( $attributes['navigationCategories'] )
                ? $attributes['navigationCategories']
                : [],
            $category
        ),
    ];
}

function celina_news_grid_wordpress_sample_post_id(): int {
    $sample = get_page_by_path( 'hello-world', OBJECT, 'post' );

    if (
        ! $sample instanceof WP_Post ||
        'publish' !== $sample->post_status ||
        'Hello world!' !== $sample->post_title ||
        '' !== trim( $sample->post_excerpt )
    ) {
        return 0;
    }

    $content = preg_replace(
        '/\s+/u',
        ' ',
        wp_strip_all_tags( $sample->post_content )
    );

    return 'Welcome to WordPress. This is your first post. Edit or delete it, then start writing!' === trim( (string) $content )
        ? $sample->ID
        : 0;
}

function celina_news_grid_excluded_post_ids( int $exclude_id = 0 ): array {
    $excluded_ids = [];
    $sample_id    = celina_news_grid_wordpress_sample_post_id();

    if ( $sample_id > 0 ) {
        $excluded_ids[] = $sample_id;
    }

    if ( $exclude_id > 0 && ! in_array( $exclude_id, $excluded_ids, true ) ) {
        $excluded_ids[] = $exclude_id;
    }

    return $excluded_ids;
}

function celina_news_grid_featured_post( array $config ): ?WP_Post {
    $selected_id = (int) ( $config['postId'] ?? 0 );
    $category    = (int) ( $config['category'] ?? 0 );
    $sample_id   = celina_news_grid_wordpress_sample_post_id();

    if ( $selected_id > 0 && $selected_id !== $sample_id ) {
        $selected = get_post( $selected_id );
        $selected_in_scope = 0 === $category;

        if ( ! $selected_in_scope && $selected instanceof WP_Post ) {
            $selected_category_ids = wp_get_post_categories( $selected->ID, [ 'fields' => 'ids' ] );
            if ( is_array( $selected_category_ids ) && ! is_wp_error( $selected_category_ids ) ) {
                foreach ( $selected_category_ids as $selected_category_id ) {
                    if ( celina_news_grid_category_is_in_scope( (int) $selected_category_id, $category ) ) {
                        $selected_in_scope = true;
                        break;
                    }
                }
            }
        }

        if (
            $selected instanceof WP_Post &&
            'post' === $selected->post_type &&
            'publish' === $selected->post_status &&
            $selected_in_scope
        ) {
            return $selected;
        }
    }

    $args = [
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'posts_per_page'      => 1,
        'no_found_rows'       => true,
        'ignore_sticky_posts' => true,
        'orderby'             => [ 'date' => 'DESC', 'ID' => 'DESC' ],
    ];

    if ( $category > 0 ) {
        $args['cat'] = $category;
    }

    if ( $sample_id > 0 ) {
        $args['post__not_in'] = [ $sample_id ];
    }

    $query = new WP_Query( $args );

    return isset( $query->posts[0] ) && $query->posts[0] instanceof WP_Post
        ? $query->posts[0]
        : null;
}

function celina_news_grid_query( array $config, int $exclude_id = 0 ): WP_Query {
    $layout     = (string) ( $config['layout'] ?? 'grid' );
    $pagination = true === ( $config['pagination'] ?? false );
    $args       = [
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'posts_per_page'      => 'news-index' === $layout
            ? 6
            : (int) ( $config['count'] ?? 3 ),
        'paged'                => $pagination ? (int) ( $config['page'] ?? 1 ) : 1,
        'no_found_rows'        => ! $pagination,
        'ignore_sticky_posts' => true,
        'orderby'              => [ 'date' => 'DESC', 'ID' => 'DESC' ],
    ];

    if ( (int) ( $config['category'] ?? 0 ) > 0 ) {
        $args['cat'] = (int) $config['category'];
    }

    $excluded_ids = celina_news_grid_excluded_post_ids( $exclude_id );
    if ( [] !== $excluded_ids ) {
        $args['post__not_in'] = $excluded_ids;
    }

    return new WP_Query( $args );
}

function celina_news_grid_primary_category( WP_Post $post, array $config ): ?WP_Term {
    $assigned_ids = wp_get_post_categories( $post->ID, [ 'fields' => 'ids' ] );
    if ( is_wp_error( $assigned_ids ) || ! is_array( $assigned_ids ) ) {
        return null;
    }

    $assigned_ids = array_values( array_unique( array_map( 'intval', $assigned_ids ) ) );
    $base_category = isset( $config['category'] ) && is_numeric( $config['category'] )
        ? max( 0, (int) $config['category'] )
        : 0;
    $navigation_ids = celina_news_grid_navigation_category_ids(
        isset( $config['navigationCategories'] ) && is_array( $config['navigationCategories'] )
            ? $config['navigationCategories']
            : [],
        $base_category
    );

    foreach ( $navigation_ids as $category_id ) {
        if ( in_array( $category_id, $assigned_ids, true ) ) {
            $term = get_term( $category_id, 'category' );

            return $term instanceof WP_Term && ! is_wp_error( $term ) ? $term : null;
        }
    }

    if ( $base_category > 0 && in_array( $base_category, $assigned_ids, true ) ) {
        $term = get_term( $base_category, 'category' );
        if ( $term instanceof WP_Term && ! is_wp_error( $term ) ) {
            return $term;
        }
    }

    $default_category = (int) get_option( 'default_category' );
    $fallback_ids     = array_values(
        array_filter(
            $assigned_ids,
            static function ( int $category_id ) use ( $default_category ): bool {
                return $category_id > 0 && $category_id !== $default_category;
            }
        )
    );
    sort( $fallback_ids, SORT_NUMERIC );

    foreach ( $fallback_ids as $category_id ) {
        $term = get_term( $category_id, 'category' );
        if ( $term instanceof WP_Term && ! is_wp_error( $term ) ) {
            return $term;
        }
    }

    return null;
}

function celina_news_grid_render_navigation( array $config, int $current_category_id = 0 ): string {
    $base_category = isset( $config['category'] ) && is_numeric( $config['category'] )
        ? max( 0, (int) $config['category'] )
        : 0;
    $category_ids = celina_news_grid_navigation_category_ids(
        isset( $config['navigationCategories'] ) && is_array( $config['navigationCategories'] )
            ? $config['navigationCategories']
            : [],
        $base_category
    );

    if ( [] === $category_ids ) {
        return '';
    }

    $posts_page_id = (int) get_option( 'page_for_posts' );
    $posts_page     = $posts_page_id > 0 ? get_post( $posts_page_id ) : null;
    $posts_page_url = $posts_page instanceof WP_Post ? get_permalink( $posts_page ) : false;
    if ( ! is_string( $posts_page_url ) || '' === $posts_page_url ) {
        return '';
    }

    $category_items = [];
    foreach ( $category_ids as $category_id ) {
        $term = get_term( $category_id, 'category' );
        if ( ! $term instanceof WP_Term || is_wp_error( $term ) ) {
            continue;
        }

        $term_url = get_term_link( $term );
        if ( is_wp_error( $term_url ) || ! is_string( $term_url ) || '' === $term_url ) {
            continue;
        }

        $current = $category_id === $current_category_id
            ? ' aria-current="page"'
            : '';
        $category_items[] = sprintf(
            '<li class="celina-news-grid__navigation-item"><a href="%1$s"%2$s>%3$s</a></li>',
            esc_url( $term_url ),
            $current,
            esc_html( $term->name )
        );
    }

    if ( [] === $category_items ) {
        return '';
    }

    $all_news_current = $current_category_id <= 0
        ? ' aria-current="page"'
        : '';
    $all_news_item    = sprintf(
        '<li class="celina-news-grid__navigation-item"><a href="%1$s"%2$s>%3$s</a></li>',
        esc_url( $posts_page_url ),
        $all_news_current,
        esc_html__( 'All News', 'celina-blocks' )
    );

    return '<nav class="celina-news-grid__navigation" aria-label="' .
        esc_attr__( 'News categories', 'celina-blocks' ) . '"><ul>' .
        $all_news_item . implode( '', $category_items ) . '</ul></nav>';
}

/** Imported articles may lack featured media; use the first core Image with an http(s) URL. */
function celina_news_grid_first_content_image_url( WP_Post $post ): string {
    if ( ! has_blocks( $post->post_content ) ) {
        return '';
    }

    $find = static function ( array $blocks ) use ( &$find ): string {
        foreach ( $blocks as $block ) {
            if ( 'core/image' === ( $block['blockName'] ?? '' ) ) {
                $attachment_id = (int) ( $block['attrs']['id'] ?? 0 );
                if ( $attachment_id > 0 ) {
                    $url = wp_get_attachment_image_url( $attachment_id, 'large' );
                    if ( is_string( $url ) && '' !== $url ) {
                        return $url;
                    }
                }

                $processor = new WP_HTML_Tag_Processor( (string) ( $block['innerHTML'] ?? '' ) );
                if ( $processor->next_tag( 'img' ) ) {
                    $src    = trim( (string) $processor->get_attribute( 'src' ) );
                    $parts  = wp_parse_url( $src );
                    $scheme = is_array( $parts ) ? strtolower( (string) ( $parts['scheme'] ?? '' ) ) : '';
                    $is_absolute_http = in_array( $scheme, [ 'http', 'https' ], true ) && ! empty( $parts['host'] );
                    $is_root_relative = 1 === preg_match( '#^/(?!/)#', $src );

                    if ( $is_absolute_http || $is_root_relative ) {
                        return esc_url_raw( $src, [ 'http', 'https' ] );
                    }
                }
            }

            if ( ! empty( $block['innerBlocks'] ) ) {
                $nested = $find( $block['innerBlocks'] );
                if ( '' !== $nested ) {
                    return $nested;
                }
            }
        }

        return '';
    };

    return $find( parse_blocks( $post->post_content ) );
}

function celina_news_grid_render_card( WP_Post $post, string $variant = 'grid', array $config = [] ): string {
    $is_editorial = in_array( $variant, [ 'featured', 'news-index' ], true );
    $heading_level = isset( $config['headingLevel'] ) &&
        in_array( $config['headingLevel'], [ 2, 3 ], true )
        ? $config['headingLevel']
        : 3;
    $heading_tag   = 2 === $heading_level ? 'h2' : 'h3';
    $permalink    = get_permalink( $post );
    $date_w3c     = get_the_date( DATE_W3C, $post );
    $display_date = get_the_date( '', $post );
    $excerpt      = get_the_excerpt( $post );
    $title        = get_the_title( $post );
    $thumbnail    = '';

    if ( '' === trim( wp_strip_all_tags( $title ) ) ) {
        $title = __( 'Untitled article', 'celina-blocks' );
    }

    $title_markup = sprintf(
        '<%1$s><a href="%2$s">%3$s</a></%1$s>',
        $heading_tag,
        esc_url( $permalink ),
        esc_html( $title )
    );

    if ( has_post_thumbnail( $post ) ) {
        $thumbnail = sprintf(
            '<a href="%1$s" aria-label="%2$s">%3$s</a>',
            esc_url( $permalink ),
            esc_attr( $title ),
            get_the_post_thumbnail( $post, 'large', [ 'loading' => 'lazy' ] )
        );
    }

    if ( '' === $thumbnail ) {
        $content_image = celina_news_grid_first_content_image_url( $post );
        $thumbnail = sprintf(
            '<a href="%1$s" aria-label="%2$s"><img src="%3$s" alt="" loading="lazy"></a>',
            esc_url( $permalink ),
            esc_attr( $title ),
            esc_url(
                '' !== $content_image
                    ? $content_image
                    : get_theme_file_uri( 'assets/images/article-placeholder.svg' )
            )
        );
    }

    if ( ! $is_editorial ) {
        $excerpt_markup = '' !== $excerpt
            ? '<p>' . esc_html( wp_trim_words( $excerpt, 24 ) ) . '</p>'
            : '';

        return sprintf(
            '<article class="celina-news-card celina-news-card--grid"><div class="celina-news-card__media">%1$s</div><div class="celina-news-card__content"><time datetime="%2$s">%3$s</time>%4$s%5$s</div></article>',
            $thumbnail,
            esc_attr( $date_w3c ),
            esc_html( $display_date ),
            $title_markup,
            $excerpt_markup
        );
    }

    $category_markup = '';
    $primary_category = celina_news_grid_primary_category( $post, $config );
    if ( $primary_category instanceof WP_Term ) {
        $category_url = get_term_link( $primary_category );
        if ( ! is_wp_error( $category_url ) && is_string( $category_url ) && '' !== $category_url ) {
            $category_markup = sprintf(
                '<a class="celina-news-card__category" href="%1$s">%2$s</a>',
                esc_url( $category_url ),
                esc_html( $primary_category->name )
            );
        }
    }

    $author_markup = '';
    $author        = get_userdata( (int) $post->post_author );
    if ( $author instanceof WP_User ) {
        $avatar = get_avatar(
            $author->ID,
            64,
            '',
            '',
            [
                'class'   => 'celina-news-card__avatar',
                'loading' => 'lazy',
            ]
        );
        $job_title = get_user_meta( $author->ID, 'celina_job_title', true );
        $job_title = is_scalar( $job_title ) ? trim( (string) $job_title ) : '';

        $author_markup = '<div class="celina-news-card__byline">';
        if ( is_string( $avatar ) && '' !== $avatar ) {
            $author_markup .= wp_kses_post( $avatar );
        }
        $author_markup .= '<div class="celina-news-card__author">' .
            '<span class="celina-news-card__author-name">' . esc_html( $author->display_name ) . '</span>';
        if ( '' !== $job_title ) {
            $author_markup .= '<span class="celina-news-card__author-title">' .
                esc_html( $job_title ) . '</span>';
        }
        $author_markup .= '<time datetime="' . esc_attr( $date_w3c ) . '">' .
            esc_html( $display_date ) . '</time></div></div>';
    } else {
        $author_markup = '<time datetime="' . esc_attr( $date_w3c ) . '">' .
            esc_html( $display_date ) . '</time>';
    }

    $excerpt_markup = '' !== $excerpt
        ? '<p>' . esc_html( wp_trim_words( $excerpt, 24 ) ) . '</p>'
        : '';
    $excerpt_markup = 'featured' === $variant ? $excerpt_markup : '';
    $card_classes   = 'celina-news-card celina-news-card--' . $variant;
    if ( 'featured' === $variant ) {
        $card_classes .= ' celina-news-featured-card';
    }

    return sprintf(
        '<article class="%1$s"><div class="celina-news-card__media">%2$s</div><div class="celina-news-card__content">%3$s%4$s%5$s%6$s</div></article>',
        esc_attr( $card_classes ),
        $thumbnail,
        $category_markup,
        $title_markup,
        $excerpt_markup,
        $author_markup
    );
}

function celina_news_grid_render_pagination( WP_Query $query, int $page ): string {
    if ( $query->max_num_pages < 2 ) {
        return '';
    }

    $placeholder = 999999999;
    $links       = paginate_links(
        [
            'base'      => str_replace(
                (string) $placeholder,
                '%#%',
                esc_url( get_pagenum_link( $placeholder ) )
            ),
            'current'   => max( 1, $page ),
            'total'     => (int) $query->max_num_pages,
            'type'      => 'array',
            'prev_text' => __( 'Previous', 'celina-blocks' ),
            'next_text' => __( 'Next', 'celina-blocks' ),
        ]
    );

    if ( ! is_array( $links ) || [] === $links ) {
        return '';
    }

    return '<nav class="celina-news-grid__pagination" aria-label="' .
        esc_attr__( 'News pages', 'celina-blocks' ) . '">' .
        wp_kses_post( implode( '', $links ) ) . '</nav>';
}
