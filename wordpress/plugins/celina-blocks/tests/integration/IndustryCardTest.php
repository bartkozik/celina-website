<?php
class IndustryCardTest extends WP_UnitTestCase {
    private function render_industry_card( array $attributes = [] ): string {
        if ( [] === $attributes ) {
            return do_blocks( '<!-- wp:celina/industry-card /-->' );
        }

        return do_blocks( '<!-- wp:celina/industry-card ' . wp_json_encode( $attributes ) . ' /-->' );
    }

    private function create_image_attachment(): int {
        return self::factory()->attachment->create_upload_object(
            DIR_TESTDATA . '/images/test-image-1-100x100.jpg'
        );
    }

    public function test_prefers_a_large_wordpress_attachment_and_escapes_alt_text(): void {
        $attachment_id = $this->create_image_attachment();

        $html = $this->render_industry_card(
            [
                'imageId'  => $attachment_id,
                'imageUrl' => 'https://example.test/fallback-must-not-render.jpg',
                'imageAlt' => 'Shelter "ready" <now>',
                'title'    => 'Defense',
            ]
        );

        $this->assertStringContainsString( 'attachment-large size-large', $html );
        $this->assertStringContainsString( 'alt="Shelter &quot;ready&quot; &lt;now&gt;"', $html );
        $this->assertStringNotContainsString( 'fallback-must-not-render.jpg', $html );
    }

    public function test_uses_only_a_sanitized_stored_image_url_as_fallback(): void {
        $safe_html = $this->render_industry_card(
            [
                'imageId'  => 999999,
                'imageUrl' => 'https://example.test/industry.jpg?size=large&mode=field',
                'imageAlt' => 'Field systems',
                'title'    => 'Government',
            ]
        );
        $unsafe_html = $this->render_industry_card(
            [
                'imageUrl' => 'javascript:alert(1)',
                'title'    => 'Unsafe image',
            ]
        );

        $this->assertStringContainsString(
            'src="https://example.test/industry.jpg?size=large&#038;mode=field"',
            $safe_html
        );
        $this->assertStringContainsString( 'alt="Field systems"', $safe_html );
        $this->assertStringNotContainsString( '<img', $unsafe_html );
        $this->assertStringNotContainsString( 'javascript:', $unsafe_html );
    }

    public function test_renders_semantic_sanitized_card_content(): void {
        $html = $this->render_industry_card(
            [
                'title'       => '<img src=x onerror=alert(1)>Defense',
                'description' => '<p>Safe <strong>rich text</strong>.</p><script>alert(2)</script>',
            ]
        );

        $this->assertStringContainsString( '<article', $html );
        $this->assertStringContainsString( '<h3 class="celina-industry-card__title">Defense</h3>', $html );
        $this->assertStringContainsString( '<strong>rich text</strong>', $html );
        $this->assertStringNotContainsString( '<script>', $html );
        $this->assertStringNotContainsString( '<img src=x', $html );
    }

    public function test_renders_only_complete_links_with_safe_urls_and_secure_new_tab_rel(): void {
        $valid_html = $this->render_industry_card(
            [
                'title'     => 'Defense',
                'linkLabel' => 'Explore defense',
                'linkUrl'   => '/markets/defense',
                'newTab'    => true,
            ]
        );
        $unsafe_html = $this->render_industry_card(
            [
                'title'     => 'Unsafe link',
                'linkLabel' => 'Do not render',
                'linkUrl'   => 'javascript:alert(1)',
            ]
        );
        $incomplete_html = $this->render_industry_card(
            [
                'title'     => 'Incomplete link',
                'linkLabel' => 'Missing URL',
            ]
        );

        $this->assertStringContainsString( 'href="/markets/defense"', $valid_html );
        $this->assertStringContainsString( 'target="_blank"', $valid_html );
        $this->assertStringContainsString( 'rel="noopener noreferrer"', $valid_html );
        $this->assertStringNotContainsString( 'celina-industry-card__link', $unsafe_html );
        $this->assertStringNotContainsString( 'celina-industry-card__link', $incomplete_html );
    }

    public function test_omits_an_empty_or_non_renderable_card(): void {
        $this->assertSame( '', $this->render_industry_card() );
        $this->assertSame(
            '',
            $this->render_industry_card(
                [
                    'imageUrl' => 'javascript:alert(1)',
                    'linkLabel' => 'Orphan link label',
                ]
            )
        );
    }
}
