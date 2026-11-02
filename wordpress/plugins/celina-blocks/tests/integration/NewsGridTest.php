<?php

class NewsGridTest extends WP_UnitTestCase {
    private const POST_TEMPLATE_GLOBALS = [
        'post',
        'id',
        'authordata',
        'currentday',
        'currentmonth',
        'page',
        'pages',
        'multipage',
        'more',
        'numpages',
    ];

    private function render_news_grid( array $attributes = [] ): string {
        if ( [] === $attributes ) {
            return do_blocks( '<!-- wp:celina/news-grid /-->' );
        }

        return do_blocks( '<!-- wp:celina/news-grid ' . wp_json_encode( $attributes ) . ' /-->' );
    }

    private function snapshot_post_template_globals(): array {
        $snapshot = [];

        foreach ( self::POST_TEMPLATE_GLOBALS as $key ) {
            $snapshot[ $key ] = [
                'exists' => array_key_exists( $key, $GLOBALS ),
                'value'  => $GLOBALS[ $key ] ?? null,
            ];
        }

        return $snapshot;
    }

    private function assert_post_template_globals_same( array $snapshot ): void {
        foreach ( $snapshot as $key => $state ) {
            $this->assertSame( $state['exists'], array_key_exists( $key, $GLOBALS ) );

            if ( $state['exists'] ) {
                $this->assertSame( $state['value'], $GLOBALS[ $key ] );
            }
        }
    }

    private function restore_post_template_globals( array $snapshot ): void {
        foreach ( $snapshot as $key => $state ) {
            if ( $state['exists'] ) {
                $GLOBALS[ $key ] = $state['value'];
            } else {
                unset( $GLOBALS[ $key ] );
            }
        }
    }

    public function test_renders_exactly_nothing_when_no_published_posts_match(): void {
        $category = self::factory()->category->create();

        $html = $this->render_news_grid( [ 'category' => $category ] );

        $this->assertSame( '', $html );
    }

    public function test_only_omits_the_untouched_wordpress_sample_post(): void {
        $sample_id = self::factory()->post->create(
            [
                'post_status'  => 'publish',
                'post_name'    => 'hello-world',
                'post_title'   => 'Hello world!',
                'post_content' => "<!-- wp:paragraph -->\n<p>Welcome to WordPress. This is your first post. Edit or delete it, then start writing!</p>\n<!-- /wp:paragraph -->",
                'post_excerpt' => '',
                'post_date'    => '2026-09-10 12:00:00',
            ]
        );
        self::factory()->post->create(
            [
                'post_status'  => 'publish',
                'post_title'   => 'CELINA story',
                'post_content' => 'Approved editorial content.',
                'post_date'    => '2026-09-09 12:00:00',
            ]
        );

        foreach ( [ 'grid', 'featured', 'news-index' ] as $layout ) {
            $html = $this->render_news_grid( [ 'layout' => $layout ] );

            $this->assertStringNotContainsString( 'Hello world!', $html );
            $this->assertStringContainsString( 'CELINA story', $html );
        }

        wp_update_post(
            [
                'ID'           => $sample_id,
                'post_excerpt' => 'A real editorial summary.',
            ]
        );

        $edited_excerpt_html = $this->render_news_grid();

        $this->assertStringContainsString( 'Hello world!', $edited_excerpt_html );

        wp_update_post(
            [
                'ID'           => $sample_id,
                'post_excerpt' => '',
                'post_content' => 'A real article that intentionally keeps the original title.',
            ]
        );

        $edited_html = $this->render_news_grid();

        $this->assertStringContainsString( 'Hello world!', $edited_html );
    }

    public function test_preserves_null_post_template_state_when_rendering_posts(): void {
        global $wp_query;

        self::factory()->post->create( [ 'post_status' => 'publish' ] );
        $previous_main_post = $wp_query->post ?? null;
        $initial_state = $this->snapshot_post_template_globals();

        try {
            $wp_query->post = null;
            $GLOBALS['post'] = null;
            $expected_state = $this->snapshot_post_template_globals();

            $html = $this->render_news_grid( [ 'count' => 1 ] );

            $this->assertSame( 1, substr_count( $html, '<article class="celina-news-card' ) );
            $this->assert_post_template_globals_same( $expected_state );
        } finally {
            $wp_query->post = $previous_main_post;
            $this->restore_post_template_globals( $initial_state );
        }
    }

    public function test_preserves_null_post_template_state_when_permalink_rendering_throws(): void {
        global $wp_query;

        self::factory()->post->create( [ 'post_status' => 'publish' ] );
        $previous_main_post = $wp_query->post ?? null;
        $initial_state = $this->snapshot_post_template_globals();
        $output_buffer_level = ob_get_level();
        $throwing_permalink_filter = static function (): string {
            throw new RuntimeException( 'Fixture permalink failure' );
        };

        try {
            $wp_query->post = null;
            $GLOBALS['post'] = null;
            $expected_state = $this->snapshot_post_template_globals();
            add_filter( 'post_link', $throwing_permalink_filter, 10, 2 );

            try {
                $this->render_news_grid( [ 'count' => 1 ] );
                $this->fail( 'Expected the fixture permalink filter to throw.' );
            } catch ( RuntimeException $error ) {
                $this->assertSame( 'Fixture permalink failure', $error->getMessage() );
            } finally {
                remove_filter( 'post_link', $throwing_permalink_filter, 10 );
            }

            $this->assert_post_template_globals_same( $expected_state );
        } finally {
            while ( ob_get_level() > $output_buffer_level ) {
                ob_end_clean();
            }
            $wp_query->post = $previous_main_post;
            $this->restore_post_template_globals( $initial_state );
        }
    }

    public function test_preserves_non_post_global_post_value_when_rendering_posts(): void {
        global $wp_query;

        self::factory()->post->create( [ 'post_status' => 'publish' ] );
        $previous_main_post = $wp_query->post ?? null;
        $initial_state = $this->snapshot_post_template_globals();

        try {
            $wp_query->post = null;
            $GLOBALS['post'] = 'news-grid-sentinel';
            $expected_state = $this->snapshot_post_template_globals();

            $html = $this->render_news_grid( [ 'count' => 1 ] );

            $this->assertSame( 1, substr_count( $html, '<article class="celina-news-card' ) );
            $this->assert_post_template_globals_same( $expected_state );
        } finally {
            $wp_query->post = $previous_main_post;
            $this->restore_post_template_globals( $initial_state );
        }
    }

    public function test_preserves_non_post_global_post_value_when_permalink_rendering_throws(): void {
        global $wp_query;

        self::factory()->post->create( [ 'post_status' => 'publish' ] );
        $previous_main_post = $wp_query->post ?? null;
        $initial_state = $this->snapshot_post_template_globals();
        $output_buffer_level = ob_get_level();
        $throwing_permalink_filter = static function (): string {
            throw new RuntimeException( 'Fixture permalink failure' );
        };

        try {
            $wp_query->post = null;
            $GLOBALS['post'] = 'news-grid-sentinel';
            $expected_state = $this->snapshot_post_template_globals();
            add_filter( 'post_link', $throwing_permalink_filter, 10, 2 );

            try {
                $this->render_news_grid( [ 'count' => 1 ] );
                $this->fail( 'Expected the fixture permalink filter to throw.' );
            } catch ( RuntimeException $error ) {
                $this->assertSame( 'Fixture permalink failure', $error->getMessage() );
            } finally {
                remove_filter( 'post_link', $throwing_permalink_filter, 10 );
            }

            $this->assert_post_template_globals_same( $expected_state );
        } finally {
            while ( ob_get_level() > $output_buffer_level ) {
                ob_end_clean();
            }
            $wp_query->post = $previous_main_post;
            $this->restore_post_template_globals( $initial_state );
        }
    }

    public function test_renders_only_twelve_published_posts_from_the_selected_category(): void {
        $category = self::factory()->category->create( [ 'name' => 'Fixture Category' ] );
        self::factory()->post->create_many(
            13,
            [
                'post_status'   => 'publish',
                'post_category' => [ $category ],
                'post_title'    => 'Matching published post',
            ]
        );
        self::factory()->post->create(
            [
                'post_status'   => 'draft',
                'post_category' => [ $category ],
                'post_title'    => 'Draft post must stay hidden',
            ]
        );
        self::factory()->post->create(
            [
                'post_status' => 'publish',
                'post_title'  => 'Other category must stay hidden',
            ]
        );

        $html = $this->render_news_grid(
            [
                'heading'  => 'Latest fixture news',
                'category' => $category,
                'count'    => 99,
            ]
        );

        $this->assertStringContainsString( '<h2>Latest fixture news</h2>', $html );
        $this->assertSame( 12, substr_count( $html, '<article class="celina-news-card' ) );
        $this->assertStringNotContainsString( 'Draft post must stay hidden', $html );
        $this->assertStringNotContainsString( 'Other category must stay hidden', $html );
    }

    public function test_escapes_heading_and_post_content_in_rendered_markup(): void {
        self::factory()->post->create(
            [
                'post_status'  => 'publish',
                'post_title'   => '<script>alert(2)</script> Safe title',
                'post_excerpt' => '<img src=x onerror=alert(3)> Safe excerpt',
            ]
        );

        $html = $this->render_news_grid(
            [ 'heading' => '<script>alert(1)</script> Latest news' ]
        );

        $this->assertStringNotContainsString( '<script>', $html );
        $this->assertStringNotContainsString( '<img src=x onerror=', $html );
        $this->assertStringContainsString( '<h2>Latest news</h2>', $html );
        $this->assertStringContainsString( 'alert(2) Safe title', $html );
        $this->assertStringContainsString( 'Safe excerpt', $html );
    }

    public function test_defaults_missing_or_malformed_attributes_and_bounds_the_count(): void {
        self::factory()->post->create_many( 4, [ 'post_status' => 'publish' ] );

        $default_html = $this->render_news_grid();
        $malformed_html = $this->render_news_grid( [ 'count' => 'not-a-number', 'category' => -4 ] );
        $minimum_html = $this->render_news_grid( [ 'count' => 0 ] );

        $this->assertStringContainsString( '<h2>Latest News</h2>', $default_html );
        $this->assertSame( 3, substr_count( $default_html, '<article class="celina-news-card' ) );
        $this->assertSame( 3, substr_count( $malformed_html, '<article class="celina-news-card' ) );
        $this->assertSame( 1, substr_count( $minimum_html, '<article class="celina-news-card' ) );
    }

    public function test_ignores_sticky_post_promotion_when_limiting_all_categories(): void {
        $first_post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
        $second_post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
        $previous_sticky_posts = get_option( 'sticky_posts' );

        try {
            update_option( 'sticky_posts', [ $first_post_id, $second_post_id ] );

            $html = $this->render_news_grid( [ 'category' => 0, 'count' => 1 ] );

            $this->assertSame( 1, substr_count( $html, '<article class="celina-news-card' ) );
        } finally {
            update_option( 'sticky_posts', $previous_sticky_posts );
        }
    }

    public function test_preserves_the_main_post_when_permalink_rendering_throws(): void {
        global $wp_query;

        $category = self::factory()->category->create();
        self::factory()->post->create(
            [
                'post_status'   => 'publish',
                'post_category' => [ $category ],
            ]
        );
        $main_post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
        $main_post = get_post( $main_post_id );
        $previous_post = $GLOBALS['post'] ?? null;
        $previous_main_post = $wp_query->post ?? null;
        $output_buffer_level = ob_get_level();
        $throwing_permalink_filter = static function (): string {
            throw new RuntimeException( 'Fixture permalink failure' );
        };

        try {
            $this->assertInstanceOf( WP_Post::class, $main_post );
            $wp_query->post = $main_post;
            $GLOBALS['post'] = $main_post;
            setup_postdata( $main_post );
            add_filter( 'post_link', $throwing_permalink_filter, 10, 2 );

            try {
                $this->render_news_grid( [ 'category' => $category, 'count' => 1 ] );
                $this->fail( 'Expected the fixture permalink filter to throw.' );
            } catch ( RuntimeException $error ) {
                $this->assertSame( 'Fixture permalink failure', $error->getMessage() );
            } finally {
                remove_filter( 'post_link', $throwing_permalink_filter, 10 );
            }

            $this->assertSame( $main_post_id, get_the_ID() );
            $this->assertInstanceOf( WP_Post::class, $GLOBALS['post'] );
            $this->assertSame( $main_post_id, $GLOBALS['post']->ID );
        } finally {
            while ( ob_get_level() > $output_buffer_level ) {
                ob_end_clean();
            }
            $wp_query->post = $previous_main_post;
            $GLOBALS['post'] = $previous_post;

            if ( $previous_post instanceof WP_Post ) {
                setup_postdata( $previous_post );
            } else {
                wp_reset_postdata();
            }
        }
    }

    public function test_preserves_the_exact_outer_post_when_permalink_rendering_throws(): void {
        global $wp_query;

        $category = self::factory()->category->create();
        self::factory()->post->create(
            [
                'post_status'   => 'publish',
                'post_category' => [ $category ],
            ]
        );
        $main_post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
        $outer_post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
        $main_post = get_post( $main_post_id );
        $previous_main_post = $wp_query->post ?? null;
        $initial_state = $this->snapshot_post_template_globals();
        $output_buffer_level = ob_get_level();
        $throwing_permalink_filter = static function (): string {
            throw new RuntimeException( 'Fixture permalink failure' );
        };

        try {
            $this->assertInstanceOf( WP_Post::class, $main_post );
            $wp_query->post = $main_post;
            $GLOBALS['post'] = $main_post;
            setup_postdata( $main_post );

            $outer_query = new WP_Query( [ 'p' => $outer_post_id, 'post_type' => 'post' ] );
            $outer_query->the_post();
            $expected_state = $this->snapshot_post_template_globals();
            add_filter( 'post_link', $throwing_permalink_filter, 10, 2 );

            try {
                $this->render_news_grid( [ 'category' => $category, 'count' => 1 ] );
                $this->fail( 'Expected the fixture permalink filter to throw.' );
            } catch ( RuntimeException $error ) {
                $this->assertSame( 'Fixture permalink failure', $error->getMessage() );
            } finally {
                remove_filter( 'post_link', $throwing_permalink_filter, 10 );
            }

            $this->assertSame( $outer_post_id, get_the_ID() );
            $this->assertInstanceOf( WP_Post::class, $GLOBALS['post'] );
            $this->assertSame( $outer_post_id, $GLOBALS['post']->ID );
            $this->assertSame( $main_post_id, $wp_query->post->ID );
            $this->assert_post_template_globals_same( $expected_state );
        } finally {
            while ( ob_get_level() > $output_buffer_level ) {
                ob_end_clean();
            }
            $wp_query->post = $previous_main_post;

            $this->restore_post_template_globals( $initial_state );
        }
    }

    public function test_preserves_the_exact_outer_post_when_rendering_matching_posts(): void {
        global $wp_query;

        $category = self::factory()->category->create();
        self::factory()->post->create(
            [
                'post_status'   => 'publish',
                'post_category' => [ $category ],
            ]
        );
        $main_post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
        $outer_post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
        $main_post = get_post( $main_post_id );
        $previous_main_post = $wp_query->post ?? null;
        $initial_state = $this->snapshot_post_template_globals();

        try {
            $this->assertInstanceOf( WP_Post::class, $main_post );
            $wp_query->post = $main_post;
            $GLOBALS['post'] = $main_post;
            setup_postdata( $main_post );

            $outer_query = new WP_Query( [ 'p' => $outer_post_id, 'post_type' => 'post' ] );
            $outer_query->the_post();
            $expected_state = $this->snapshot_post_template_globals();

            $html = $this->render_news_grid( [ 'category' => $category, 'count' => 1 ] );

            $this->assertSame( 1, substr_count( $html, '<article class="celina-news-card' ) );
            $this->assertSame( $main_post_id, $wp_query->post->ID );
            $this->assert_post_template_globals_same( $expected_state );
        } finally {
            $wp_query->post = $previous_main_post;
            $this->restore_post_template_globals( $initial_state );
        }
    }

    public function test_returns_empty_without_changing_the_prior_post_when_no_posts_match(): void {
        global $wp_query;

        $category = self::factory()->category->create();
        $main_post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
        $outer_post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
        $main_post = get_post( $main_post_id );
        $previous_main_post = $wp_query->post ?? null;
        $initial_state = $this->snapshot_post_template_globals();

        try {
            $this->assertInstanceOf( WP_Post::class, $main_post );
            $wp_query->post = $main_post;
            $GLOBALS['post'] = $main_post;
            setup_postdata( $main_post );

            $outer_query = new WP_Query( [ 'p' => $outer_post_id, 'post_type' => 'post' ] );
            $outer_query->the_post();
            $expected_state = $this->snapshot_post_template_globals();

            $this->assertSame( '', $this->render_news_grid( [ 'category' => $category ] ) );
            $this->assertSame( $outer_post_id, get_the_ID() );
            $this->assertInstanceOf( WP_Post::class, $GLOBALS['post'] );
            $this->assertSame( $outer_post_id, $GLOBALS['post']->ID );
            $this->assertSame( $main_post_id, $wp_query->post->ID );
            $this->assert_post_template_globals_same( $expected_state );
        } finally {
            $wp_query->post = $previous_main_post;

            $this->restore_post_template_globals( $initial_state );
        }
    }

    public function test_renders_safe_semantic_post_details_and_preserves_the_main_post(): void {
        global $wp_query;

        $category = self::factory()->category->create();
        $post_id = self::factory()->post->create(
            [
                'post_status'  => 'publish',
                'post_title'   => 'Fixture post',
                'post_date'    => '2024-03-04 12:34:56',
                'post_date_gmt' => '2024-03-04 12:34:56',
                'post_category' => [ $category ],
                'post_excerpt' => implode( ' ', array_map( static function ( int $number ): string {
                    return 'word' . $number;
                }, range( 1, 26 ) ) ),
            ]
        );
        $attachment_id = self::factory()->attachment->create_upload_object(
            DIR_TESTDATA . '/images/test-image-1-100x100.jpg',
            $post_id
        );
        set_post_thumbnail( $post_id, $attachment_id );

        $main_post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
        $main_post = get_post( $main_post_id );
        $previous_post = $GLOBALS['post'] ?? null;
        $previous_main_post = $wp_query->post ?? null;
        $permalink_filter = static function ( string $permalink, WP_Post $filtered_post ) use ( $post_id ): string {
            if ( $post_id === $filtered_post->ID ) {
                return $permalink . '&fixture=1';
            }

            return $permalink;
        };
        $title_filter = static function ( string $title, $filtered_post_id ) use ( $post_id ): string {
            if ( $post_id === (int) $filtered_post_id ) {
                return 'Fixture " onmouseover="alert(1)';
            }

            return $title;
        };

        add_filter( 'post_link', $permalink_filter, 10, 2 );
        add_filter( 'the_title', $title_filter, 10, 2 );

        try {
            $this->assertInstanceOf( WP_Post::class, $main_post );
            $wp_query->post = $main_post;
            $GLOBALS['post'] = $main_post;
            setup_postdata( $main_post );

            $html = $this->render_news_grid( [ 'category' => $category, 'count' => 1 ] );
            $permalink = get_permalink( $post_id );
            $date_w3c = get_the_date( DATE_W3C, $post_id );
            $display_date = get_the_date( '', $post_id );

            $this->assertStringContainsString(
                '<time datetime="' . esc_attr( $date_w3c ) . '">' . esc_html( $display_date ) . '</time>',
                $html
            );
            $this->assertSame( 2, substr_count( $html, 'href="' . esc_url( $permalink ) . '"' ) );
            $this->assertStringContainsString(
                '<a href="' . esc_url( $permalink ) . '" aria-label="Fixture &quot; onmouseover=&quot;alert(1)">',
                $html
            );
            $this->assertStringContainsString( 'loading="lazy"', $html );
            $this->assertStringContainsString( 'word24', $html );
            $this->assertStringNotContainsString( 'word25', $html );
            $this->assertStringNotContainsString( 'word26', $html );
            $this->assertSame( $main_post_id, get_the_ID() );
            $this->assertInstanceOf( WP_Post::class, $GLOBALS['post'] );
            $this->assertSame( $main_post_id, $GLOBALS['post']->ID );

            preg_match( '/<p>(.*?)<\/p>/s', $html, $excerpt_match );
            $this->assertArrayHasKey( 1, $excerpt_match );
            $excerpt_words = preg_split(
                '/\s+/',
                trim( wp_strip_all_tags( html_entity_decode( $excerpt_match[1] ) ) )
            );
            $this->assertLessThanOrEqual( 24, count( $excerpt_words ) );
        } finally {
            remove_filter( 'the_title', $title_filter, 10 );
            remove_filter( 'post_link', $permalink_filter, 10 );
            $wp_query->post = $previous_main_post;
            $GLOBALS['post'] = $previous_post;

            if ( $previous_post instanceof WP_Post ) {
                setup_postdata( $previous_post );
            } else {
                wp_reset_postdata();
            }
        }
    }

    public function test_exposes_focused_renderer_functions_and_normalizes_final_attributes(): void {
        foreach ( [
            'celina_news_grid_attributes',
            'celina_news_grid_featured_post',
            'celina_news_grid_query',
            'celina_news_grid_navigation_category_ids',
            'celina_news_grid_category_is_in_scope',
            'celina_news_grid_primary_category',
            'celina_news_grid_render_navigation',
            'celina_news_grid_render_card',
            'celina_news_grid_render_pagination',
        ] as $function ) {
            $this->assertTrue( function_exists( $function ), 'Missing ' . $function );
        }

        $this->assertSame(
            [
                'heading'    => 'Latest News',
                'category'   => 0,
                'count'      => 3,
                'layout'     => 'grid',
                'postId'     => 0,
                'pagination' => false,
                'page'       => 1,
                'navigationCategories' => [],
            ],
            celina_news_grid_attributes(
                [
                    'category'   => -5,
                    'count'      => 'invalid',
                    'layout'     => 'unsafe-layout',
                    'postId'     => -9,
                    'pagination' => 'yes',
                ]
            )
        );
    }

    public function test_normalizes_ordered_navigation_categories_with_default_scope_and_term_safety(): void {
        $default_category = self::factory()->category->create( [ 'name' => 'Editable default name' ] );
        $base_category    = self::factory()->category->create( [ 'name' => 'Corporate News' ] );
        $child_category   = self::factory()->category->create(
            [
                'name'   => 'Manufacturing',
                'parent' => $base_category,
            ]
        );
        $outside_category = self::factory()->category->create( [ 'name' => 'Outside scope' ] );
        $empty_category   = self::factory()->category->create( [ 'name' => 'Empty category' ] );
        $deleted_category = self::factory()->category->create( [ 'name' => 'Deleted category' ] );
        $previous_default = get_option( 'default_category' );

        self::factory()->post->create(
            [
                'post_status'   => 'publish',
                'post_category' => [
                    $default_category,
                    $base_category,
                    $child_category,
                    $outside_category,
                ],
            ]
        );
        wp_delete_term( $deleted_category, 'category' );

        try {
            update_option( 'default_category', $default_category );

            $config = celina_news_grid_attributes(
                [
                    'category'             => $base_category,
                    'navigationCategories' => [
                        $child_category,
                        $default_category,
                        $outside_category,
                        $child_category,
                        $empty_category,
                        $deleted_category,
                        $base_category,
                        0,
                        'invalid',
                    ],
                ]
            );

            $this->assertSame(
                [ $child_category, $base_category ],
                $config['navigationCategories']
            );
            $this->assertTrue(
                celina_news_grid_category_is_in_scope( $base_category, $base_category )
            );
            $this->assertTrue(
                celina_news_grid_category_is_in_scope( $child_category, $base_category )
            );
            $this->assertFalse(
                celina_news_grid_category_is_in_scope( $outside_category, $base_category )
            );
            $this->assertFalse(
                celina_news_grid_category_is_in_scope( $deleted_category, $base_category )
            );
        } finally {
            update_option( 'default_category', $previous_default );
        }
    }

    public function test_navigation_keeps_a_parent_with_published_content_only_in_a_descendant(): void {
        $parent_category = self::factory()->category->create( [ 'name' => 'Corporate News' ] );
        $child_category  = self::factory()->category->create(
            [
                'name'   => 'Manufacturing',
                'parent' => $parent_category,
            ]
        );
        self::factory()->post->create(
            [
                'post_status'   => 'publish',
                'post_category' => [ $child_category ],
            ]
        );
        $parent_term = get_term( $parent_category, 'category' );

        $this->assertInstanceOf( WP_Term::class, $parent_term );
        $this->assertSame( 0, $parent_term->count );
        $this->assertSame(
            [ $parent_category ],
            celina_news_grid_navigation_category_ids( [ $parent_category ], 0 )
        );
    }

    public function test_primary_category_follows_navigation_base_and_lowest_id_precedence(): void {
        $default_category = self::factory()->category->create();
        $lowest_category  = self::factory()->category->create( [ 'name' => 'Lowest' ] );
        $base_category    = self::factory()->category->create( [ 'name' => 'Base' ] );
        $first_category   = self::factory()->category->create( [ 'name' => 'First selected' ] );
        $second_category  = self::factory()->category->create( [ 'name' => 'Second selected' ] );
        $post_id          = self::factory()->post->create(
            [
                'post_status'   => 'publish',
                'post_category' => [
                    $default_category,
                    $lowest_category,
                    $base_category,
                    $first_category,
                    $second_category,
                ],
            ]
        );
        $post             = get_post( $post_id );
        $previous_default = get_option( 'default_category' );

        try {
            update_option( 'default_category', $default_category );
            $this->assertInstanceOf( WP_Post::class, $post );

            $primary = celina_news_grid_primary_category(
                $post,
                [
                    'category'             => 0,
                    'navigationCategories' => [ $second_category, $first_category ],
                ]
            );
            $this->assertInstanceOf( WP_Term::class, $primary );
            $this->assertSame( $second_category, $primary->term_id );

            $primary = celina_news_grid_primary_category(
                $post,
                [
                    'category'             => $base_category,
                    'navigationCategories' => [],
                ]
            );
            $this->assertInstanceOf( WP_Term::class, $primary );
            $this->assertSame( $base_category, $primary->term_id );

            $primary = celina_news_grid_primary_category(
                $post,
                [
                    'category'             => 0,
                    'navigationCategories' => [],
                ]
            );
            $this->assertInstanceOf( WP_Term::class, $primary );
            $this->assertSame( $lowest_category, $primary->term_id );
        } finally {
            update_option( 'default_category', $previous_default );
        }
    }

    public function test_navigation_uses_canonical_links_order_and_current_route(): void {
        $posts_page_id = self::factory()->post->create(
            [
                'post_type'   => 'page',
                'post_status' => 'publish',
                'post_title'  => 'News',
            ]
        );
        $first_category  = self::factory()->category->create( [ 'name' => 'Company Updates' ] );
        $second_category = self::factory()->category->create( [ 'name' => 'Community' ] );
        $previous_page   = get_option( 'page_for_posts' );

        self::factory()->post->create(
            [
                'post_status'   => 'publish',
                'post_category' => [ $first_category, $second_category ],
            ]
        );

        try {
            update_option( 'page_for_posts', $posts_page_id );
            $config = celina_news_grid_attributes(
                [
                    'layout'               => 'news-index',
                    'navigationCategories' => [ $second_category, $first_category ],
                ]
            );
            $html = celina_news_grid_render_navigation( $config, $first_category );

            $this->assertStringContainsString( 'aria-label="News categories"', $html );
            $this->assertStringContainsString(
                'href="' . esc_url( get_permalink( $posts_page_id ) ) . '">All News</a>',
                $html
            );
            $this->assertStringContainsString(
                'href="' . esc_url( get_term_link( $first_category, 'category' ) ) . '" aria-current="page"',
                $html
            );
            $all_news_position = strpos( $html, 'All News' );
            $second_position   = strpos( $html, 'Community' );
            $first_position    = strpos( $html, 'Company Updates' );
            $this->assertIsInt( $all_news_position );
            $this->assertIsInt( $second_position );
            $this->assertIsInt( $first_position );
            $this->assertLessThan( $second_position, $all_news_position );
            $this->assertLessThan(
                $first_position,
                $second_position
            );
            $this->assertSame( 1, substr_count( $html, 'aria-current="page"' ) );
            $this->assertSame( '', celina_news_grid_render_navigation( [ 'navigationCategories' => [] ] ) );
        } finally {
            update_option( 'page_for_posts', $previous_page );
        }
    }

    public function test_navigation_omits_a_category_when_wordpress_returns_an_error_link(): void {
        $posts_page_id  = self::factory()->post->create(
            [
                'post_type'   => 'page',
                'post_status' => 'publish',
            ]
        );
        $good_category  = self::factory()->category->create( [ 'name' => 'Good category' ] );
        $error_category = self::factory()->category->create( [ 'name' => 'Error category' ] );
        $previous_page  = get_option( 'page_for_posts' );
        $link_filter    = static function ( $url, WP_Term $term ) use ( $error_category ) {
            return $error_category === $term->term_id
                ? new WP_Error( 'fixture_link_error' )
                : $url;
        };

        self::factory()->post->create(
            [
                'post_status'   => 'publish',
                'post_category' => [ $good_category, $error_category ],
            ]
        );

        try {
            update_option( 'page_for_posts', $posts_page_id );
            add_filter( 'term_link', $link_filter, 10, 2 );

            $html = celina_news_grid_render_navigation(
                [
                    'category'             => 0,
                    'navigationCategories' => [ $error_category, $good_category ],
                ]
            );

            $this->assertStringNotContainsString( 'Error category', $html );
            $this->assertStringContainsString( 'Good category', $html );
        } finally {
            remove_filter( 'term_link', $link_filter, 10 );
            update_option( 'page_for_posts', $previous_page );
        }
    }

    public function test_news_index_card_renders_placeholder_category_and_author_metadata_without_excerpt(): void {
        $category_id = self::factory()->category->create( [ 'name' => 'Manufacturing' ] );
        $author_id   = self::factory()->user->create(
            [
                'display_name' => 'Natalia Example',
                'user_email'   => 'natalia@example.com',
            ]
        );
        $post_id     = self::factory()->post->create(
            [
                'post_status'   => 'publish',
                'post_author'   => $author_id,
                'post_category' => [ $category_id ],
                'post_title'    => 'CELINA expands manufacturing capacity',
                'post_excerpt'  => 'This compact excerpt must not be rendered.',
            ]
        );
        $post        = get_post( $post_id );

        update_user_meta( $author_id, 'celina_job_title', 'Director of Operations' );
        $this->assertInstanceOf( WP_Post::class, $post );

        $html = celina_news_grid_render_card(
            $post,
            'news-index',
            [
                'category'             => 0,
                'navigationCategories' => [ $category_id ],
            ]
        );

        $this->assertStringContainsString( 'celina-news-card--news-index', $html );
        $this->assertStringContainsString( 'celina-news-card__media', $html );
        $this->assertStringContainsString(
            'src="' . esc_url( get_theme_file_uri( 'assets/images/article-placeholder.svg' ) ) . '" alt=""',
            $html
        );
        $this->assertStringContainsString(
            'href="' . esc_url( get_term_link( $category_id, 'category' ) ) . '"',
            $html
        );
        $this->assertStringContainsString( '>Manufacturing</a>', $html );
        $this->assertStringContainsString( 'celina-news-card__avatar', $html );
        $this->assertStringContainsString( 'Natalia Example', $html );
        $this->assertStringContainsString( 'Director of Operations', $html );
        $this->assertStringContainsString( '<time datetime="', $html );
        $this->assertStringNotContainsString( 'This compact excerpt must not be rendered.', $html );
    }

    public function test_grid_card_always_renders_media_from_featured_image_article_image_or_placeholder(): void {
        $config = [ 'category' => 0 ];

        $body_image_post = get_post(
            self::factory()->post->create(
                [
                    'post_status'  => 'publish',
                    'post_title'   => 'Article with a body photograph',
                    'post_content' => "<!-- wp:paragraph --><p>Intro</p><!-- /wp:paragraph -->\n" .
                        '<!-- wp:group --><div class="wp-block-group"><!-- wp:image {"sizeSlug":"large"} --><figure class="wp-block-image size-large"><img src="https://example.com/uploads/body-photo.webp" alt="Crew"/></figure><!-- /wp:image --></div><!-- /wp:group -->',
                ]
            )
        );
        $unsafe_image_post = get_post(
            self::factory()->post->create(
                [
                    'post_status'  => 'publish',
                    'post_content' => '<!-- wp:image --><figure class="wp-block-image"><img src="javascript:alert(1)" alt=""/></figure><!-- /wp:image -->',
                ]
            )
        );
        $featured_post_id = self::factory()->post->create(
            [
                'post_status'  => 'publish',
                'post_content' => '<!-- wp:image --><figure class="wp-block-image"><img src="https://example.com/uploads/ignored.webp" alt=""/></figure><!-- /wp:image -->',
            ]
        );
        set_post_thumbnail(
            $featured_post_id,
            self::factory()->attachment->create_upload_object(
                DIR_TESTDATA . '/images/test-image-1-100x100.jpg',
                $featured_post_id
            )
        );

        $this->assertInstanceOf( WP_Post::class, $body_image_post );
        $this->assertInstanceOf( WP_Post::class, $unsafe_image_post );

        $body_html     = celina_news_grid_render_card( $body_image_post, 'grid', $config );
        $unsafe_html   = celina_news_grid_render_card( $unsafe_image_post, 'grid', $config );
        $featured_html = celina_news_grid_render_card( get_post( $featured_post_id ), 'grid', $config );
        $placeholder   = esc_url( get_theme_file_uri( 'assets/images/article-placeholder.svg' ) );

        foreach ( [ $body_html, $unsafe_html, $featured_html ] as $html ) {
            $this->assertStringStartsWith(
                '<article class="celina-news-card celina-news-card--grid"><div class="celina-news-card__media"><a href="',
                $html
            );
            $this->assertStringContainsString( '<div class="celina-news-card__content"><time datetime="', $html );
        }

        $this->assertStringContainsString( 'src="https://example.com/uploads/body-photo.webp" alt=""', $body_html );
        $this->assertStringContainsString( 'src="' . $placeholder . '" alt=""', $unsafe_html );
        $this->assertStringNotContainsString( 'javascript:', $unsafe_html );
        $this->assertStringContainsString( 'wp-post-image', $featured_html );
        $this->assertStringNotContainsString( 'ignored.webp', $featured_html );
    }

    public function test_featured_card_renders_thumbnail_and_excerpt_while_generic_card_stays_excerpt_oriented(): void {
        $category_id = self::factory()->category->create( [ 'name' => 'Company Updates' ] );
        $post_id     = self::factory()->post->create(
            [
                'post_status'   => 'publish',
                'post_category' => [ $category_id ],
                'post_excerpt'  => 'Editorial summary for the featured story.',
            ]
        );
        $attachment_id = self::factory()->attachment->create_upload_object(
            DIR_TESTDATA . '/images/test-image-1-100x100.jpg',
            $post_id
        );
        $post = get_post( $post_id );
        set_post_thumbnail( $post_id, $attachment_id );

        $this->assertInstanceOf( WP_Post::class, $post );
        $config = [
            'category'             => 0,
            'navigationCategories' => [ $category_id ],
        ];

        $featured_html = celina_news_grid_render_card( $post, 'featured', $config );
        $generic_html  = celina_news_grid_render_card( $post, 'grid', $config );

        $this->assertStringContainsString( 'celina-news-featured-card', $featured_html );
        $this->assertStringContainsString( 'Editorial summary for the featured story.', $featured_html );
        $this->assertStringContainsString( 'wp-post-image', $featured_html );
        $this->assertStringNotContainsString( 'article-placeholder.svg', $featured_html );
        $this->assertStringContainsString( 'Editorial summary for the featured story.', $generic_html );
        $this->assertStringNotContainsString( 'celina-news-card__byline', $generic_html );
        $this->assertStringNotContainsString( 'celina-news-card__category', $generic_html );
    }

    public function test_untitled_editorial_card_uses_a_visible_and_accessible_fallback_title(): void {
        $post_id = self::factory()->post->create(
            [
                'post_status' => 'publish',
                'post_title'  => '',
            ]
        );
        $post = get_post( $post_id );

        $this->assertInstanceOf( WP_Post::class, $post );
        $html = celina_news_grid_render_card( $post, 'news-index' );

        $this->assertStringContainsString( 'aria-label="Untitled article"', $html );
        $this->assertStringContainsString( '>Untitled article</a></h3>', $html );
        $this->assertSame( 2, substr_count( $html, 'Untitled article' ) );
    }

    public function test_untitled_generic_card_keeps_its_title_link_accessible(): void {
        $post_id = self::factory()->post->create(
            [
                'post_status' => 'publish',
                'post_title'  => '',
            ]
        );
        $post = get_post( $post_id );

        $this->assertInstanceOf( WP_Post::class, $post );
        $html = celina_news_grid_render_card( $post, 'grid' );

        $this->assertStringContainsString( '<h3><a href="', $html );
        $this->assertStringContainsString( '>Untitled article</a></h3>', $html );
    }

    public function test_card_heading_level_allows_h2_and_normalizes_invalid_values_to_h3(): void {
        $post_id = self::factory()->post->create(
            [
                'post_status' => 'publish',
                'post_title'  => 'Archive heading fixture',
            ]
        );
        $post = get_post( $post_id );

        $this->assertInstanceOf( WP_Post::class, $post );
        $archive_html = celina_news_grid_render_card(
            $post,
            'news-index',
            [ 'headingLevel' => 2 ]
        );
        $invalid_html = celina_news_grid_render_card(
            $post,
            'news-index',
            [ 'headingLevel' => 1 ]
        );
        $string_html = celina_news_grid_render_card(
            $post,
            'news-index',
            [ 'headingLevel' => '2' ]
        );

        $this->assertStringContainsString( '<h2><a href="', $archive_html );
        $this->assertStringContainsString( '>Archive heading fixture</a></h2>', $archive_html );
        $this->assertStringNotContainsString( '<h3>', $archive_html );
        $this->assertStringContainsString( '<h3><a href="', $invalid_html );
        $this->assertStringContainsString( '<h3><a href="', $string_html );
    }

    public function test_card_title_layout_styles_cover_both_supported_heading_levels(): void {
        $style = file_get_contents(
            dirname( __DIR__, 2 ) . '/src/blocks/news-grid/style.scss'
        );

        $this->assertIsString( $style );
        $this->assertStringContainsString(
            '.celina-news-card__content :is(h2, h3)',
            $style
        );
        $this->assertSame(
            2,
            substr_count( $style, '.celina-news-grid__items .celina-news-card :is(h2, h3)' )
        );
    }

    public function test_news_index_renders_navigation_before_feature_and_a_visible_empty_status(): void {
        $posts_page_id = self::factory()->post->create(
            [
                'post_type'   => 'page',
                'post_status' => 'publish',
            ]
        );
        $category_id   = self::factory()->category->create( [ 'name' => 'Community' ] );
        $post_id       = self::factory()->post->create(
            [
                'post_status'   => 'publish',
                'post_category' => [ $category_id ],
            ]
        );
        $previous_page = get_option( 'page_for_posts' );

        try {
            update_option( 'page_for_posts', $posts_page_id );
            $html = $this->render_news_grid(
                [
                    'layout'               => 'news-index',
                    'postId'               => $post_id,
                    'navigationCategories' => [ $category_id ],
                ]
            );

            $this->assertLessThan(
                strpos( $html, 'celina-news-grid__featured' ),
                strpos( $html, 'celina-news-grid__navigation' )
            );
            $this->assertStringContainsString( 'celina-news-card__category', $html );

            wp_delete_post( $post_id, true );
            $empty_html = $this->render_news_grid(
                [
                    'layout'   => 'news-index',
                    'category' => $category_id,
                ]
            );

            $this->assertStringContainsString( 'celina-news-grid__empty', $empty_html );
            $this->assertStringContainsString( 'role="status"', $empty_html );
            $this->assertStringContainsString( 'No news articles are available at this time.', $empty_html );
        } finally {
            update_option( 'page_for_posts', $previous_page );
        }
    }

    public function test_featured_mode_prefers_only_a_compatible_published_selection(): void {
        $category       = self::factory()->category->create();
        $other_category = self::factory()->category->create();
        $fallback_id    = self::factory()->post->create(
            [
                'post_status'   => 'publish',
                'post_category' => [ $category ],
                'post_title'    => 'Latest compatible story',
                'post_date'     => '2026-01-03 12:00:00',
            ]
        );
        $selected_id    = self::factory()->post->create(
            [
                'post_status'   => 'publish',
                'post_category' => [ $category ],
                'post_title'    => 'Selected compatible story',
                'post_date'     => '2026-01-01 12:00:00',
            ]
        );
        $wrong_id       = self::factory()->post->create(
            [
                'post_status'   => 'publish',
                'post_category' => [ $other_category ],
                'post_title'    => 'Wrong category story',
                'post_date'     => '2026-01-04 12:00:00',
            ]
        );
        $draft_id       = self::factory()->post->create(
            [
                'post_status'   => 'draft',
                'post_category' => [ $category ],
                'post_title'    => 'Draft story',
            ]
        );

        $selected_html = $this->render_news_grid(
            [
                'category'   => $category,
                'layout'     => 'featured',
                'postId'     => $selected_id,
                'pagination' => true,
            ]
        );
        $wrong_html = $this->render_news_grid(
            [
                'category' => $category,
                'layout'   => 'featured',
                'postId'   => $wrong_id,
            ]
        );
        $draft_html = $this->render_news_grid(
            [
                'category' => $category,
                'layout'   => 'featured',
                'postId'   => $draft_id,
            ]
        );

        $this->assertStringContainsString( 'Selected compatible story', $selected_html );
        $this->assertStringNotContainsString( 'Latest compatible story', $selected_html );
        $this->assertSame( 1, substr_count( $selected_html, '<article class="celina-news-card' ) );
        $this->assertStringNotContainsString( 'celina-news-grid__pagination', $selected_html );
        $this->assertStringContainsString( get_the_title( $fallback_id ), $wrong_html );
        $this->assertStringContainsString( get_the_title( $fallback_id ), $draft_html );
        $this->assertStringNotContainsString( 'Wrong category story', $wrong_html );
        $this->assertStringNotContainsString( 'Draft story', $draft_html );
    }

    public function test_featured_mode_accepts_an_explicit_selection_from_a_descendant_category(): void {
        $base_category  = self::factory()->category->create( [ 'name' => 'Corporate News' ] );
        $child_category = self::factory()->category->create(
            [
                'name'   => 'Manufacturing',
                'parent' => $base_category,
            ]
        );
        self::factory()->post->create(
            [
                'post_status'   => 'publish',
                'post_category' => [ $base_category ],
                'post_title'    => 'Newer fallback story',
                'post_date'     => '2026-03-02 12:00:00',
            ]
        );
        $selected_id = self::factory()->post->create(
            [
                'post_status'   => 'publish',
                'post_category' => [ $child_category ],
                'post_title'    => 'Selected descendant story',
                'post_date'     => '2026-03-01 12:00:00',
            ]
        );

        $html = $this->render_news_grid(
            [
                'category' => $base_category,
                'layout'   => 'featured',
                'postId'   => $selected_id,
            ]
        );

        $this->assertStringContainsString( 'Selected descendant story', $html );
        $this->assertStringNotContainsString( 'Newer fallback story', $html );
    }

    public function test_news_index_deduplicates_feature_and_keeps_stable_six_item_pages(): void {
        $category = self::factory()->category->create();
        $post_ids = [];

        for ( $index = 1; $index <= 8; $index++ ) {
            $post_ids[ $index ] = self::factory()->post->create(
                [
                    'post_status'   => 'publish',
                    'post_category' => [ $category ],
                    'post_title'    => 'Ordered story ' . $index,
                    'post_date'     => sprintf( '2026-02-%02d 12:00:00', $index ),
                ]
            );
        }

        $previous_paged = get_query_var( 'paged' );

        try {
            set_query_var( 'paged', 1 );
            $page_one = $this->render_news_grid(
                [
                    'category' => $category,
                    'layout'   => 'news-index',
                    'postId'   => $post_ids[ 3 ],
                ]
            );
            set_query_var( 'paged', 2 );
            $page_two = $this->render_news_grid(
                [
                    'category' => $category,
                    'layout'   => 'news-index',
                    'postId'   => $post_ids[ 3 ],
                ]
            );
        } finally {
            set_query_var( 'paged', $previous_paged );
        }

        $this->assertSame( 1, substr_count( $page_one, 'celina-news-grid__featured' ) );
        $this->assertSame( 1, substr_count( $page_one, '>Ordered story 3</a></h3>' ) );
        $this->assertSame( 7, substr_count( $page_one, '<article class="celina-news-card' ) );
        $this->assertStringContainsString( 'Ordered story 8', $page_one );
        $this->assertStringContainsString( 'Ordered story 2', $page_one );
        $this->assertStringNotContainsString( 'Ordered story 1', $page_one );
        $this->assertStringNotContainsString( 'celina-news-grid__featured', $page_two );
        $this->assertSame( 1, substr_count( $page_two, '<article class="celina-news-card' ) );
        $this->assertStringContainsString( 'Ordered story 1', $page_two );
        $this->assertStringNotContainsString( 'Ordered story 3', $page_two );
        $this->assertStringContainsString( 'celina-news-grid__pagination', $page_one );
        $this->assertStringContainsString( 'celina-news-grid__pagination', $page_two );
    }

    public function test_news_index_without_a_feature_candidate_uses_the_normal_latest_sequence(): void {
        $category = self::factory()->category->create();

        self::factory()->post->create_many(
            2,
            [
                'post_status'   => 'publish',
                'post_category' => [ $category ],
            ]
        );

        $html = $this->render_news_grid(
            [
                'category' => $category,
                'layout'   => 'news-index',
            ]
        );

        $this->assertSame( 1, substr_count( $html, 'celina-news-grid__featured' ) );
        $this->assertSame( 2, substr_count( $html, '<article class="celina-news-card' ) );
    }
}
