<?php

class HubSpotFormTest extends WP_UnitTestCase {
    private const SCRIPT_HANDLE = 'celina-hubspot-forms';
    private const SCRIPT_URL = 'https://js.hsforms.net/forms/embed/v2.js';

    private function render_form( array $attributes = [] ): string {
        if ( [] === $attributes ) {
            return do_blocks( '<!-- wp:celina/hubspot-form /-->' );
        }

        return do_blocks( '<!-- wp:celina/hubspot-form ' . wp_json_encode( $attributes ) . ' /-->' );
    }

    private function render_raw_form( array $raw_attributes ): string {
        return ( static function ( array $attributes ): string {
            ob_start();
            include dirname( __DIR__, 2 ) . '/build/blocks/hubspot-form/render.php';
            return (string) ob_get_clean();
        } )( $raw_attributes );
    }

    private function valid_attributes( array $overrides = [] ): array {
        return array_merge(
            [
                'heading'      => 'Contact CELINA',
                'region'       => 'eu1',
                'portalId'     => '123456',
                'formId'       => '123e4567-e89b-12d3-a456-426614174000',
                'fallbackText' => 'Email our team if the form is unavailable.',
            ],
            $overrides
        );
    }

    public function tear_down(): void {
        wp_dequeue_script( self::SCRIPT_HANDLE );
        wp_deregister_script( self::SCRIPT_HANDLE );
        parent::tear_down();
    }

    public function test_invalid_or_incomplete_configuration_is_an_explicit_non_submittable_placeholder(): void {
        $invalid_configurations = [
            [],
            $this->valid_attributes( [ 'region' => 'ap1' ] ),
            $this->valid_attributes( [ 'portalId' => '12x' ] ),
            $this->valid_attributes( [ 'portalId' => "123456\n" ] ),
            $this->valid_attributes( [ 'formId' => 'not-a-uuid' ] ),
            $this->valid_attributes( [ 'formId' => '123e4567-e89b-12d3-a456-426614174000 ' ] ),
        ];

        foreach ( $invalid_configurations as $attributes ) {
            $html = $this->render_raw_form( $attributes );

            $this->assertStringContainsString( 'role="status"', $html );
            $this->assertStringContainsString( 'HubSpot form is not configured in this workbench.', $html );
            $this->assertStringNotContainsString( '<form', $html );
            $this->assertStringNotContainsString( 'data-hubspot-target', $html );
        }

        $this->assertFalse( wp_script_is( self::SCRIPT_HANDLE, 'enqueued' ) );
        $this->assertFalse( wp_script_is( self::SCRIPT_HANDLE, 'registered' ) );
    }

    public function test_placeholder_escapes_heading_and_displays_fallback_contact_text(): void {
        $html = $this->render_raw_form(
            [
                'heading'      => '<script>alert(1)</script> Contact',
                'fallbackText' => '<img src=x onerror=alert(2)> Email us.',
            ]
        );

        $this->assertStringContainsString( '<h2>Contact</h2>', $html );
        $this->assertStringContainsString( 'Email us.', $html );
        $this->assertStringNotContainsString( '<script>', $html );
        $this->assertStringNotContainsString( '<img', $html );
    }

    public function test_valid_configuration_enqueues_only_the_official_script_and_escaped_inline_configuration(): void {
        $html = $this->render_form( $this->valid_attributes() );

        $this->assertTrue( wp_script_is( self::SCRIPT_HANDLE, 'registered' ) );
        $this->assertTrue( wp_script_is( self::SCRIPT_HANDLE, 'enqueued' ) );
        $this->assertSame( self::SCRIPT_URL, wp_scripts()->registered[ self::SCRIPT_HANDLE ]->src );
        $this->assertStringContainsString( 'data-hubspot-target', $html );

        $after = wp_scripts()->get_data( self::SCRIPT_HANDLE, 'after' );
        $this->assertIsArray( $after );
        $inline = implode( "\n", $after );
        $this->assertStringContainsString( 'hbspt.forms.create', $inline );
        $this->assertStringContainsString( '"region":"eu1"', $inline );
        $this->assertStringContainsString( '"portalId":"123456"', $inline );
        $this->assertStringContainsString( '"formId":"123e4567-e89b-12d3-a456-426614174000"', $inline );
        $this->assertStringNotContainsString( '<script>', $inline );
    }

    public function test_owned_handle_replaces_a_pre_registered_untrusted_script(): void {
        wp_register_script(
            self::SCRIPT_HANDLE,
            'https://untrusted.example.test/embed.js',
            [],
            null,
            true
        );
        wp_enqueue_script( self::SCRIPT_HANDLE );

        $this->render_form( $this->valid_attributes() );

        $this->assertSame( self::SCRIPT_URL, wp_scripts()->registered[ self::SCRIPT_HANDLE ]->src );
        $this->assertTrue( wp_script_is( self::SCRIPT_HANDLE, 'enqueued' ) );
    }

    public function test_two_valid_blocks_use_unique_targets_and_share_one_script_handle(): void {
        $before = wp_scripts()->get_data( self::SCRIPT_HANDLE, 'after' );
        $before_count = is_array( $before )
            ? count( array_filter( $before, 'is_string' ) )
            : 0;
        $first = $this->render_form( $this->valid_attributes() );
        $second = $this->render_form( $this->valid_attributes() );

        preg_match( '/data-hubspot-target="([^"]+)"/', $first, $first_match );
        preg_match( '/data-hubspot-target="([^"]+)"/', $second, $second_match );

        $this->assertNotEmpty( $first_match[1] ?? '' );
        $this->assertNotEmpty( $second_match[1] ?? '' );
        $this->assertNotSame( $first_match[1], $second_match[1] );
        $this->assertSame( 1, count( array_keys( wp_scripts()->queue, self::SCRIPT_HANDLE, true ) ) );

        $after = wp_scripts()->get_data( self::SCRIPT_HANDLE, 'after' );
        $this->assertIsArray( $after );
        $inline_scripts = array_values( array_filter( $after, 'is_string' ) );
        $this->assertSame( 2, count( $inline_scripts ) - $before_count );
    }
}
