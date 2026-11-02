<?php
class TextImageSplitTest extends WP_UnitTestCase {
    private function render_text_image_split( array $attributes = [] ): string {
        if ( [] === $attributes ) {
            return do_blocks( '<!-- wp:celina/text-image-split /-->' );
        }

        return do_blocks( '<!-- wp:celina/text-image-split ' . wp_json_encode( $attributes ) . ' /-->' );
    }

    private function create_image_attachment(): int {
        return self::factory()->attachment->create_upload_object(
            DIR_TESTDATA . '/images/test-image-1-100x100.jpg'
        );
    }

    public function test_prefers_a_large_wordpress_attachment_and_escapes_meaningful_alt_text(): void {
        $attachment_id = $this->create_image_attachment();

        $html = $this->render_text_image_split(
            [
                'heading'       => 'Field shelters',
                'imageId'       => $attachment_id,
                'imageUrl'      => 'https://example.test/fallback-must-not-render.jpg',
                'imageAlt'      => 'Shelter "ready" <now>',
                'imagePosition' => 'left',
                'background'    => 'warm',
            ]
        );

        $this->assertStringContainsString( 'attachment-large size-large', $html );
        $this->assertStringContainsString( 'alt="Shelter &quot;ready&quot; &lt;now&gt;"', $html );
        $this->assertStringContainsString( 'celina-text-image-split--image-left', $html );
        $this->assertStringContainsString( 'celina-text-image-split--warm', $html );
        $this->assertStringNotContainsString( 'fallback-must-not-render.jpg', $html );
    }

    public function test_marks_a_decorative_attachment_with_empty_alt_and_aria_hidden(): void {
        $attachment_id = $this->create_image_attachment();

        $html = $this->render_text_image_split(
            [
                'heading'         => 'Deployable systems',
                'imageId'         => $attachment_id,
                'imageAlt'        => 'This must be ignored',
                'imageDecorative' => true,
            ]
        );

        $this->assertMatchesRegularExpression( '/<img[^>]+alt=""[^>]+aria-hidden="true"/', $html );
        $this->assertStringNotContainsString( 'This must be ignored', $html );
    }

    public function test_uses_only_a_sanitized_stored_url_when_the_attachment_is_missing(): void {
        $safe_html = $this->render_text_image_split(
            [
                'heading'  => 'Fallback image',
                'imageId'  => 999999,
                'imageUrl' => 'https://example.test/shelter.jpg?size=large&mode=field',
                'imageAlt' => 'Fallback shelter',
            ]
        );
        $unsafe_html = $this->render_text_image_split(
            [
                'heading'  => 'Unsafe image',
                'imageId'  => -4,
                'imageUrl' => 'javascript:alert(1)',
                'imageAlt' => 'Unsafe',
            ]
        );

        $this->assertStringContainsString(
            'src="https://example.test/shelter.jpg?size=large&#038;mode=field"',
            $safe_html
        );
        $this->assertStringContainsString( 'alt="Fallback shelter"', $safe_html );
        $this->assertStringNotContainsString( '<img', $unsafe_html );
        $this->assertStringNotContainsString( 'javascript:', $unsafe_html );
    }

    public function test_allowlists_layout_classes_and_sanitizes_rich_content(): void {
        $html = $this->render_text_image_split(
            [
                'eyebrow'      => '<script>bad</script> Mission ready',
                'heading'      => '<img src=x onerror=alert(1)>Capabilities',
                'content'      => '<p>Safe <strong>rich text</strong>.</p><script>alert(2)</script>',
                'imagePosition' => 'center<script>',
                'background'    => 'black<script>',
            ]
        );

        $this->assertStringContainsString( 'celina-text-image-split--image-right', $html );
        $this->assertStringContainsString( 'celina-text-image-split--neutral', $html );
        $this->assertStringContainsString( '<strong>rich text</strong>', $html );
        $this->assertStringNotContainsString( '<script>', $html );
        $this->assertStringNotContainsString( '<img src=x', $html );
        $this->assertStringNotContainsString( 'center&lt;script&gt;', $html );
    }

    public function test_renders_only_complete_buttons_with_safe_urls_and_secure_new_tab_rel(): void {
        $valid_html = $this->render_text_image_split(
            [
                'buttonLabel'  => 'Explore capabilities',
                'buttonUrl'    => 'https://example.test/capabilities',
                'buttonNewTab' => true,
            ]
        );
        $unsafe_html = $this->render_text_image_split(
            [
                'heading'     => 'Unsafe button',
                'buttonLabel' => 'Do not render',
                'buttonUrl'   => 'javascript:alert(1)',
            ]
        );
        $incomplete_html = $this->render_text_image_split(
            [
                'heading'     => 'Incomplete button',
                'buttonLabel' => 'Missing URL',
            ]
        );

        $this->assertStringContainsString( 'href="https://example.test/capabilities"', $valid_html );
        $this->assertStringContainsString( 'target="_blank"', $valid_html );
        $this->assertStringContainsString( 'rel="noopener noreferrer"', $valid_html );
        $this->assertStringNotContainsString( '<a', $unsafe_html );
        $this->assertStringNotContainsString( '<a', $incomplete_html );
    }

    public function test_omits_the_section_when_only_an_eyebrow_or_invalid_media_is_present(): void {
        $this->assertSame( '', $this->render_text_image_split() );
        $this->assertSame(
            '',
            $this->render_text_image_split(
                [
                    'eyebrow'  => 'Orphan eyebrow',
                    'imageId'  => -2,
                    'imageUrl' => 'javascript:alert(1)',
                ]
            )
        );
    }
}
