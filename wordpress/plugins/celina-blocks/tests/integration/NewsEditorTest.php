<?php

class NewsEditorTest extends WP_UnitTestCase {
    public function set_up(): void {
        parent::set_up();
        wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
        do_action( 'rest_api_init' );
    }

    private function request( array $params = [] ): WP_REST_Response {
        $request = new WP_REST_Request( 'GET', '/celina/v1/news-editor' );
        $request->set_query_params( $params );
        return rest_get_server()->dispatch( $request );
    }

    private function story( array $args = [] ): int {
        return self::factory()->post->create( array_merge( [
            'post_status' => 'publish',
            'post_title' => 'Editorial update',
            'post_excerpt' => 'A useful summary.',
        ], $args ) );
    }

    public function test_requires_editor_permissions(): void {
        wp_set_current_user( 0 );
        $this->assertSame( 401, $this->request()->get_status() );
        wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
        $this->assertSame( 403, $this->request()->get_status() );
    }

    public function test_page_and_post_patterns_stay_editable_without_changing_explicit_locks(): void {
        foreach ( [ 'page', 'post' ] as $type ) {
            $post = self::factory()->post->create_and_get( [ 'post_type' => $type ] );
            $settings = apply_filters( 'block_editor_settings_all', [ 'templateLock' => 'contentOnly' ], new WP_Block_Editor_Context( [ 'post' => $post ] ) );
            $this->assertTrue( $settings['disableContentOnlyForUnsyncedPatterns'] );
            $this->assertSame( 'contentOnly', $settings['templateLock'] );
        }
        $settings = apply_filters( 'block_editor_settings_all', [], new WP_Block_Editor_Context() );
        $this->assertArrayNotHasKey( 'disableContentOnlyForUnsyncedPatterns', $settings );
    }

    public function test_uses_server_admin_urls_and_separate_creation_permission(): void {
        $post_type = get_post_type_object( 'post' );
        $original = $post_type->cap->create_posts;
        $post_type->cap->create_posts = 'create_news_posts';
        $user = wp_get_current_user();
        $user->add_cap( 'create_news_posts' );
        try {
            $settings = apply_filters( 'block_editor_settings_all', [], new WP_Block_Editor_Context() );
            $this->assertSame( admin_url( 'post-new.php' ), $settings['celinaNews']['addUrl'] );
            $this->assertSame( admin_url( 'edit.php' ), $settings['celinaNews']['manageUrl'] );
            $user->remove_cap( 'create_news_posts' );
            $settings = apply_filters( 'block_editor_settings_all', [], new WP_Block_Editor_Context() );
            $this->assertNull( $settings['celinaNews']['addUrl'] );
            $this->assertSame( admin_url( 'edit.php' ), $settings['celinaNews']['manageUrl'] );
        } finally {
            $post_type->cap->create_posts = $original;
        }
    }

    public function test_grid_uses_public_query_with_descendants_sample_exclusion_and_tie_order(): void {
        $base = self::factory()->category->create();
        $child = self::factory()->category->create( [ 'parent' => $base ] );
        $ids = [];
        for ( $i = 0; $i < 5; $i++ ) {
            $ids[] = $this->story( [ 'post_category' => [ $child ], 'post_date' => '2026-01-01 12:00:00' ] );
        }
        stick_post( $ids[0] );
        $this->story( [
            'post_category' => [ $child ], 'post_date' => '2026-02-01 12:00:00',
            'post_name' => 'hello-world', 'post_title' => 'Hello world!', 'post_excerpt' => '',
            'post_content' => 'Welcome to WordPress. This is your first post. Edit or delete it, then start writing!',
        ] );
        $data = $this->request( [ 'category' => $base, 'count' => 3 ] )->get_data();
        $this->assertNull( $data['featured'] );
        $this->assertSame( array_reverse( array_slice( $ids, -3 ) ), array_column( $data['posts'], 'id' ) );
        $this->assertFalse( $data['selectedUnavailable'] );
    }

    public function test_featured_resolves_older_selected_and_index_excludes_it(): void {
        $old = $this->story( [ 'post_date' => '2020-01-01 12:00:00', 'post_title' => 'Archived launch' ] );
        for ( $i = 0; $i < 101; $i++ ) {
            $this->story( [ 'post_date' => '2026-01-01 12:00:00' ] );
        }
        $data = $this->request( [ 'layout' => 'featured', 'postId' => $old ] )->get_data();
        $this->assertSame( $old, $data['featured']['id'] );
        $this->assertSame( [], $data['posts'] );
        $this->assertFalse( $data['selectedUnavailable'] );
        $data = $this->request( [ 'layout' => 'news-index', 'postId' => $old ] )->get_data();
        $this->assertCount( 6, $data['posts'] );
        $this->assertNotContains( $old, array_column( $data['posts'], 'id' ) );
        $data = $this->request( [ 'mode' => 'search', 'search' => 'Archived launch' ] )->get_data();
        $this->assertSame( [ $old ], array_column( $data['posts'], 'id' ) );
    }

    public function test_latest_featured_is_not_duplicated_and_invalid_selection_falls_back(): void {
        $ids = [];
        for ( $i = 0; $i < 8; $i++ ) {
            $ids[] = $this->story( [ 'post_date' => '2026-01-01 12:00:00' ] );
        }
        $draft = $this->story( [ 'post_status' => 'draft' ] );
        $data = $this->request( [ 'layout' => 'news-index', 'postId' => $draft ] )->get_data();
        $this->assertTrue( $data['selectedUnavailable'] );
        $this->assertSame( $ids[7], $data['featured']['id'] );
        $this->assertSame( array_reverse( array_slice( $ids, 1, 6 ) ), array_column( $data['posts'], 'id' ) );
    }

    public function test_page_only_editor_can_preview_but_cannot_manage_or_edit_articles(): void {
        $post = $this->story();
        $user = self::factory()->user->create_and_get( [ 'role' => 'subscriber' ] );
        $user->add_cap( 'edit_pages' );
        wp_set_current_user( $user->ID );
        $response = $this->request();
        $this->assertSame( 200, $response->get_status() );
        $data = $response->get_data();
        $this->assertSame( $post, $data['posts'][0]['id'] );
        $this->assertNull( $data['posts'][0]['editUrl'] );
        $settings = apply_filters( 'block_editor_settings_all', [], new WP_Block_Editor_Context() );
        $this->assertNull( $settings['celinaNews']['addUrl'] );
        $this->assertNull( $settings['celinaNews']['manageUrl'] );
    }

    public function test_per_article_edit_permission_and_metadata(): void {
        $user = self::factory()->user->create_and_get( [ 'role' => 'author' ] );
        $own = $this->story( [ 'post_author' => $user->ID ] );
        $other = $this->story();
        wp_set_current_user( $user->ID );
        $data = $this->request()->get_data();
        $posts = array_column( $data['posts'], null, 'id' );
        $this->assertSame( get_edit_post_link( $own, 'raw' ), $posts[$own]['editUrl'] );
        $this->assertNull( $posts[$other]['editUrl'] );
        $this->assertSame( 'A useful summary.', $posts[$own]['excerpt'] );
        $this->assertNull( $posts[$own]['image'] );
    }

    public function test_search_is_bounded_paginated_and_excludes_drafts(): void {
        for ( $i = 0; $i < 22; $i++ ) {
            $this->story( [ 'post_title' => 'Searchable history' ] );
        }
        $this->story( [ 'post_title' => 'Searchable history', 'post_status' => 'draft' ] );
        $first = $this->request( [ 'mode' => 'search', 'search' => 'Searchable' ] )->get_data();
        $second = $this->request( [ 'mode' => 'search', 'search' => 'Searchable', 'page' => 2 ] )->get_data();
        $this->assertCount( 20, $first['posts'] );
        $this->assertTrue( $first['hasMore'] );
        $this->assertCount( 2, $second['posts'] );
        $this->assertFalse( $second['hasMore'] );
        $this->assertSame( [], array_intersect( array_column( $first['posts'], 'id' ), array_column( $second['posts'], 'id' ) ) );
    }

    public function test_reports_navigation_eligibility_with_published_descendants(): void {
        $parent = self::factory()->category->create();
        $child = self::factory()->category->create( [ 'parent' => $parent ] );
        $empty = self::factory()->category->create();
        $default = (int) get_option( 'default_category' );
        $this->story( [ 'post_category' => [ $child, $default ] ] );
        $data = $this->request( [
            'layout' => 'news-index',
            'navigationCategories' => [ $default, $empty, $parent, $child ],
        ] )->get_data();
        $this->assertArrayHasKey( 'navigationCategories', $data );
        $this->assertSame( [ $parent, $child ], $data['navigationCategories'] );
    }

    public function test_rejects_unbounded_or_invalid_query_inputs(): void {
        foreach ( [ [ 'count' => 9999 ], [ 'page' => -1 ], [ 'layout' => 'anything' ], [ 'mode' => 'delete' ], [ 'search' => str_repeat( 'x', 201 ) ] ] as $params ) {
            $this->assertSame( 400, $this->request( $params )->get_status(), wp_json_encode( $params ) );
        }
    }
}
