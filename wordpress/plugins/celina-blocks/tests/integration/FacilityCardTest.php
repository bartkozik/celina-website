<?php
class FacilityCardTest extends WP_UnitTestCase {
    private function render_facility_card( array $attributes = [] ): string {
        if ( [] === $attributes ) {
            return do_blocks( '<!-- wp:celina/facility-card /-->' );
        }

        return do_blocks( '<!-- wp:celina/facility-card ' . wp_json_encode( $attributes ) . ' /-->' );
    }

    private function create_image_attachment(): int {
        return self::factory()->attachment->create_upload_object(
            DIR_TESTDATA . '/images/test-image-1-100x100.jpg'
        );
    }

    public function test_prefers_a_responsive_wordpress_attachment_and_escapes_alt_text(): void {
        $attachment_id = $this->create_image_attachment();
        $html = $this->render_facility_card(
            [
                'imageId'  => $attachment_id,
                'imageUrl' => 'https://example.test/fallback-must-not-render.jpg',
                'imageAlt' => 'Factory "one" <main>',
                'name'     => 'CELINA Ohio',
            ]
        );

        $this->assertStringContainsString( 'attachment-large size-large', $html );
        $this->assertStringContainsString( 'alt="Factory &quot;one&quot; &lt;main&gt;"', $html );
        $this->assertStringNotContainsString( 'fallback-must-not-render.jpg', $html );
    }

    public function test_renders_semantic_sanitized_contact_details_and_normalized_schemes(): void {
        $html = $this->render_facility_card(
            [
                'name'    => '<img src=x onerror=alert(1)>CELINA Ohio',
                'address' => '<p>123 <strong>Main</strong> Street</p><script>alert(2)</script>',
                'phone'   => '+1 (555) 010-0000',
                'email'   => 'contact@example.invalid',
            ]
        );

        $this->assertStringContainsString( '<h3 class="celina-facility-card__name">CELINA Ohio</h3>', $html );
        $this->assertStringContainsString( '<address class="celina-facility-card__contact">', $html );
        $this->assertStringContainsString( '<strong>Main</strong>', $html );
        $this->assertStringContainsString( 'href="tel:+15550100000"', $html );
        $this->assertStringContainsString( '>+1 (555) 010-0000</a>', $html );
        $this->assertStringContainsString( 'href="mailto:contact@example.invalid"', $html );
        $this->assertStringNotContainsString( '<script>', $html );
        $this->assertStringNotContainsString( '<img src=x', $html );
    }

    public function test_omits_invalid_contact_and_optional_link_values(): void {
        $html = $this->render_facility_card(
            [
                'name'  => 'CELINA Ohio',
                'phone' => '<script>alert(1)</script>',
                'email' => 'not-an-email',
                'label' => 'Unsafe directions',
                'url'   => 'javascript:alert(2)',
            ]
        );

        $this->assertStringNotContainsString( 'href="tel:', $html );
        $this->assertStringNotContainsString( 'href="mailto:', $html );
        $this->assertStringNotContainsString( 'celina-facility-card__link', $html );
        $this->assertStringNotContainsString( 'javascript:', $html );
    }

    public function test_renders_only_a_complete_safe_optional_link(): void {
        $valid_html = $this->render_facility_card(
            [
                'name'  => 'CELINA Ohio',
                'label' => 'Get directions',
                'url'   => '/contact#ohio',
            ]
        );
        $incomplete_html = $this->render_facility_card(
            [
                'name'  => 'CELINA Ohio',
                'label' => 'Missing destination',
            ]
        );

        $this->assertStringContainsString( 'href="/contact#ohio"', $valid_html );
        $this->assertStringNotContainsString( 'celina-facility-card__link', $incomplete_html );
    }

    public function test_uses_only_a_sanitized_image_url_fallback_and_omits_empty_cards(): void {
        $safe_html = $this->render_facility_card(
            [
                'imageId'  => 999999,
                'imageUrl' => 'https://example.test/facility.jpg?size=large&view=front',
                'imageAlt' => 'Front view',
            ]
        );
        $unsafe_html = $this->render_facility_card(
            [
                'imageUrl' => 'data:image/svg+xml,<svg onload=alert(1)>',
            ]
        );

        $this->assertStringContainsString(
            'src="https://example.test/facility.jpg?size=large&#038;view=front"',
            $safe_html
        );
        $this->assertStringNotContainsString( '<img', $unsafe_html );
        $this->assertSame( '', $unsafe_html );
        $this->assertSame( '', $this->render_facility_card() );
    }
}
