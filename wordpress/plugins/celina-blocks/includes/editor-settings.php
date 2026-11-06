<?php
defined( 'ABSPATH' ) || exit;

function celina_news_editor_can_preview(): bool {
    return current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' );
}

function celina_blocks_editor_settings( array $settings, ?WP_Block_Editor_Context $context = null ): array {
    // Disable WordPress 7.1 implicit content locks for unsynced patterns; retain explicit and synced locks.
    if ( $context && $context->post instanceof WP_Post && in_array( $context->post->post_type, [ 'post', 'page' ], true ) ) {
        $settings['disableContentOnlyForUnsyncedPatterns'] = true;
    }
    if ( ! celina_news_editor_can_preview() ) {
        return $settings;
    }

    $post_type = get_post_type_object( 'post' );
    $settings['celinaNews'] = [
        'endpoint'  => rest_url( 'celina/v1/news-editor' ),
        'nonce'     => wp_create_nonce( 'wp_rest' ),
        'addUrl'    => $post_type && current_user_can( $post_type->cap->create_posts )
            ? admin_url( 'post-new.php' ) : null,
        'manageUrl' => $post_type && current_user_can( $post_type->cap->edit_posts )
            ? admin_url( 'edit.php' ) : null,
    ];
    return $settings;
}
add_filter( 'block_editor_settings_all', 'celina_blocks_editor_settings', 10, 2 );

function celina_news_editor_post( WP_Post $post ): array {
    $thumbnail_id  = get_post_thumbnail_id( $post );
    $thumbnail_url = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'large' ) : false;
    $categories    = get_the_category( $post->ID );
    $title         = wp_strip_all_tags( get_the_title( $post ) );

    return [
        'id'         => $post->ID,
        'title'      => '' !== trim( $title ) ? $title : __( 'Untitled article', 'celina-blocks' ),
        'excerpt'    => wp_strip_all_tags( wp_trim_words( get_the_excerpt( $post ), 24 ) ),
        'date'       => get_the_date( '', $post ),
        'dateTime'   => get_the_date( DATE_W3C, $post ),
        'categories' => array_map( static function ( WP_Term $category ): string {
            return $category->name;
        }, $categories ),
        'image'      => $thumbnail_url ? [
            'url' => $thumbnail_url,
            'alt' => (string) get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ),
        ] : null,
        'editUrl'    => current_user_can( 'edit_post', $post->ID )
            ? get_edit_post_link( $post->ID, 'raw' ) : null,
    ];
}

function celina_news_editor_response( WP_REST_Request $request ): WP_REST_Response {
    $config = celina_news_grid_attributes( $request->get_params() );
    $config['page'] = 1;
    $config['pagination'] = false;

    if ( 'search' === $request['mode'] ) {
        $args = [
            'post_type'           => 'post',
            'post_status'         => 'publish',
            'posts_per_page'      => 20,
            'paged'               => (int) $request['page'],
            's'                   => (string) $request['search'],
            'ignore_sticky_posts' => true,
            'orderby'             => [ 'date' => 'DESC', 'ID' => 'DESC' ],
            'post__not_in'        => celina_news_grid_excluded_post_ids(),
        ];
        if ( $config['category'] > 0 ) {
            $args['cat'] = $config['category'];
        }
        $query = new WP_Query( $args );
        $data = [
            'posts'   => array_map( 'celina_news_editor_post', $query->posts ),
            'hasMore' => (int) $request['page'] < (int) $query->max_num_pages,
        ];
    } else {
        $featured = 'grid' === $config['layout']
            ? null : celina_news_grid_featured_post( $config );
        $posts = 'featured' === $config['layout']
            ? [] : celina_news_grid_query( $config, $featured ? $featured->ID : 0 )->posts;
        $data = [
            'featured' => $featured ? celina_news_editor_post( $featured ) : null,
            'posts'    => array_map( 'celina_news_editor_post', $posts ),
            'navigationCategories' => $config['navigationCategories'],
            'selectedUnavailable' => 'grid' !== $config['layout'] && $config['postId'] > 0 &&
                ( ! $featured || $featured->ID !== $config['postId'] ),
        ];
    }

    $response = new WP_REST_Response( $data );
    // Permissions and article edits must be checked again on every refresh.
    $response->header( 'Cache-Control', 'no-store, private' );
    return $response;
}

function celina_news_editor_register_route(): void {
    register_rest_route( 'celina/v1', '/news-editor', [
        'methods'             => WP_REST_Server::READABLE,
        'permission_callback' => 'celina_news_editor_can_preview',
        'callback'            => 'celina_news_editor_response',
        'args'                => [
            'mode'     => [ 'type' => 'string', 'enum' => [ 'preview', 'search' ], 'default' => 'preview' ],
            'layout'   => [ 'type' => 'string', 'enum' => [ 'grid', 'featured', 'news-index' ], 'default' => 'grid' ],
            'category' => [ 'type' => 'integer', 'minimum' => 0, 'default' => 0 ],
            'postId'   => [ 'type' => 'integer', 'minimum' => 0, 'default' => 0 ],
            'count'    => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 12, 'default' => 3 ],
            'page'     => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 10000, 'default' => 1 ],
            'search'   => [ 'type' => 'string', 'maxLength' => 200, 'default' => '', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg' ],
            'navigationCategories' => [
                'type' => 'array', 'maxItems' => 100, 'default' => [],
                'items' => [ 'type' => 'integer', 'minimum' => 1 ],
            ],
        ],
    ] );
}
add_action( 'rest_api_init', 'celina_news_editor_register_route' );
