<?php
class HeroBackgroundTest extends WP_UnitTestCase {
    private const SAVED_URL = 'https://example.test/saved-hero.jpg';

    private function image_id(): int {
        return self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image-1-100x100.jpg' );
    }

    private function markup( string $style = '' ): string {
        $style = $style ?: 'background-image:url(' . self::SAVED_URL . ')';
        return '<section class="wp-block-celina-hero celina-hero celina-hero--standard" id="editable-anchor" style="' . esc_attr( $style ) . '"><div class="celina-hero__overlay" aria-hidden="true"></div><div class="celina-hero__inner"><h1 class="celina-hero__heading">Saved <em>heading</em></h1><p style="background-image:url(https://example.test/child.jpg)">Saved copy</p></div></section>';
    }

    private function content( array $attributes, string $html ): string {
        return '<!-- wp:celina/hero ' . serialize_block_attributes( $attributes ) . ' -->' . $html . '<!-- /wp:celina/hero -->';
    }

    private function render( array $attributes, string $html ): string {
        return do_blocks( $this->content( $attributes, $html ) );
    }

    private function style( string $html ): string {
        $processor = new WP_HTML_Tag_Processor( $html );
        $this->assertTrue( $processor->next_tag( 'SECTION' ) );
        return (string) $processor->get_attribute( 'style' );
    }

    public function test_resolves_the_full_attachment_through_wordpress_filters_without_changing_saved_content(): void {
        $id = $this->image_id();
        $attributes = [ 'backgroundId' => $id, 'backgroundUrl' => self::SAVED_URL, 'heading' => 'Saved heading' ];
        $saved = $this->markup();
        $cdn = 'https://imagedelivery.net/account/image/public?width=1920&quality=85';
        $sizes = [];
        $filter = static function ( $image, $attachment_id, $size ) use ( $id, $cdn, &$sizes ) {
            if ( $id === $attachment_id ) {
                $sizes[] = $size;
                return [ $cdn, 1920, 1080, false ];
            }
            return $image;
        };
        add_filter( 'wp_get_attachment_image_src', $filter, 10, 3 );
        try {
            $html = $this->render( $attributes, $saved );
        } finally {
            remove_filter( 'wp_get_attachment_image_src', $filter, 10 );
        }
        $this->assertSame( [ 'full' ], $sizes );
        $this->assertSame( 'background-image:url("' . $cdn . '")', $this->style( $html ) );
        $this->assertStringNotContainsString( self::SAVED_URL, $html );
        $this->assertSame( substr( $saved, strpos( $saved, '><div' ) ), substr( $html, strpos( $html, '><div' ) ) );
        $this->assertStringContainsString( 'id="editable-anchor"', $html );
        $content = $this->content( $attributes, $saved );
        $this->assertSame( $content, serialize_blocks( parse_blocks( $content ) ) );
    }

    public function test_resolves_the_original_attachment_without_a_cdn_filter(): void {
        $id = $this->image_id();
        $html = $this->render( [ 'backgroundId' => $id, 'backgroundUrl' => self::SAVED_URL ], $this->markup() );
        $this->assertSame( 'background-image:url("' . wp_get_attachment_image_url( $id, 'full' ) . '")', $this->style( $html ) );
    }

    public function test_preserves_unrelated_css_declarations_and_descendant_backgrounds(): void {
        $id = $this->image_id();
        $style = '--label:"keep;this";color:red; background-image: url("https://example.test/old;(one).jpg") !important;--asset:url("data:image/svg+xml;a;b"); padding: 2px';
        $html = $this->render( [ 'backgroundId' => $id, 'backgroundUrl' => self::SAVED_URL ], $this->markup( $style ) );
        $expected = str_replace( 'url("https://example.test/old;(one).jpg")', 'url("' . wp_get_attachment_image_url( $id, 'full' ) . '")', $style );
        $this->assertSame( $expected, $this->style( $html ) );
        $this->assertStringContainsString( '<p style="background-image:url(https://example.test/child.jpg)">Saved copy</p>', $html );
    }

    public function test_encodes_css_delimiters_and_html_without_truncating_cdn_query_parameters(): void {
        $id = $this->image_id();
        $url = 'https://cdn.example.test/photo(1)\'".jpg?token=a&width=1200';
        $filter = static function () use ( $url ) { return [ $url, 1200, 800, false ]; };
        add_filter( 'wp_get_attachment_image_src', $filter );
        try {
            $html = $this->render( [ 'backgroundId' => $id, 'backgroundUrl' => self::SAVED_URL ], $this->markup() );
        } finally {
            remove_filter( 'wp_get_attachment_image_src', $filter );
        }
        $this->assertSame( 'background-image:url("https://cdn.example.test/photo%281%29%27%22.jpg?token=a&width=1200")', $this->style( $html ) );
        $this->assertStringContainsString( '&quot;', $html );
        $this->assertStringContainsString( '&amp;width=1200', $html );
    }

    public function test_retains_saved_markup_for_missing_invalid_or_non_image_attachments(): void {
        $saved = $this->markup();
        $pdf = self::factory()->attachment->create( [ 'post_mime_type' => 'application/pdf' ] );
        foreach ( [ null, 0, -1, 999999, $pdf ] as $id ) {
            $attributes = [ 'backgroundUrl' => self::SAVED_URL ];
            if ( null !== $id ) {
                $attributes['backgroundId'] = $id;
            }
            $this->assertSame( $saved, $this->render( $attributes, $saved ) );
        }
    }

    public function test_retains_saved_markup_if_the_attachment_url_is_unresolved_or_unsafe(): void {
        $id = $this->image_id();
        $saved = $this->markup();
        foreach ( [ false, [ 'javascript:alert(1)', 1, 1, false ], [ 'data:image/svg+xml,unsafe', 1, 1, false ] ] as $result ) {
            $filter = static function () use ( $result ) { return $result; };
            add_filter( 'wp_get_attachment_image_src', $filter );
            try {
                $this->assertSame( $saved, $this->render( [ 'backgroundId' => $id, 'backgroundUrl' => self::SAVED_URL ], $saved ) );
            } finally {
                remove_filter( 'wp_get_attachment_image_src', $filter );
            }
        }
    }

    public function test_retains_the_original_markup_when_css_is_incomplete(): void {
        $id = $this->image_id();
        foreach ( [ 'background-image:url(old.jpg);color:red' . chr( 92 ), 'background-image:url(old.jpg);--label:"unfinished', 'background-image:url(old.jpg);/* unfinished' ] as $style ) {
            $saved = $this->markup( $style );
            $this->assertSame( $saved, $this->render( [ 'backgroundId' => $id, 'backgroundUrl' => self::SAVED_URL ], $saved ) );
        }
    }

    public function test_does_not_restore_a_removed_background_or_rewrite_a_descendant_hero(): void {
        $id = $this->image_id();
        $saved = $this->markup();
        foreach ( [ [ 'backgroundId' => $id ], [ 'backgroundId' => $id, 'backgroundUrl' => '' ] ] as $attributes ) {
            $this->assertSame( $saved, $this->render( $attributes, $saved ) );
        }
        $attributes = [ 'backgroundId' => $id, 'backgroundUrl' => self::SAVED_URL ];
        foreach ( [ '<div>' . $saved . '</div>', '<section class="unrelated">' . $saved . '</section>', $this->markup( 'color:red' ) ] as $html ) {
            $this->assertSame( $html, $this->render( $attributes, $html ) );
        }
    }
}
