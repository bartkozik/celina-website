<?php

class BambooHrJobsTest extends WP_UnitTestCase {
    private function render_bamboohr_jobs( array $attributes = [] ): string {
        if ( [] === $attributes ) {
            return do_blocks( '<!-- wp:celina/bamboohr-jobs /-->' );
        }

        return do_blocks( '<!-- wp:celina/bamboohr-jobs ' . wp_json_encode( $attributes ) . ' /-->' );
    }

    private function render_raw_bamboohr_jobs( array $raw_attributes ): string {
        return ( static function ( array $attributes ): string {
            ob_start();
            include dirname( __DIR__, 2 ) . '/build/blocks/bamboohr-jobs/render.php';
            return (string) ob_get_clean();
        } )( $raw_attributes );
    }

    private function saved_attributes( array $overrides = [] ): array {
        return array_merge(
            [
                'heading'          => 'Open positions',
                'companySubdomain' => 'celina-us',
                'fallbackText'     => 'Saved fallback copy.',
                'jobsLinkText'     => 'View jobs on BambooHR',
            ],
            $overrides
        );
    }

    public function test_saved_bamboohr_content_is_retired_without_requests_or_external_urls(): void {
        $html = $this->render_bamboohr_jobs( $this->saved_attributes() );

        $this->assertStringContainsString( '<h2>Open positions</h2>', $html );
        $this->assertStringContainsString( 'Saved fallback copy.', $html );
        $this->assertStringContainsString( 'Replace this legacy block with Paycor Jobs.', $html );
        $this->assertStringNotContainsString( 'bamboohr.com', strtolower( $html ) );
        $this->assertStringNotContainsString( 'recruitingbypaycor.com', strtolower( $html ) );
        $this->assertStringNotContainsString( '<script', strtolower( $html ) );
        $this->assertStringNotContainsString( '<iframe', strtolower( $html ) );
        $this->assertStringNotContainsString( 'http://', strtolower( $html ) );
        $this->assertStringNotContainsString( 'https://', strtolower( $html ) );
    }

    public function test_legacy_account_attribute_never_changes_retired_output(): void {
        $html = $this->render_bamboohr_jobs( [ 'companySubdomain' => 'celina-us' ] );

        $this->assertStringContainsString( 'Replace this legacy block with Paycor Jobs.', $html );
        $this->assertStringNotContainsString( 'bamboohr.com', strtolower( $html ) );
        $this->assertStringNotContainsString( 'recruitingbypaycor.com', strtolower( $html ) );
    }

    public function test_output_safely_sanitizes_saved_editorial_copy(): void {
        $html = $this->render_raw_bamboohr_jobs(
            $this->saved_attributes(
                [
                    'companySubdomain' => 'celina-copy',
                    'heading'          => '<script>alert(1)</script> Jobs',
                    'fallbackText'     => '<img src=x onerror=alert(2)> Direct access.',
                    'jobsLinkText'     => '<strong>View jobs</strong>',
                ]
            )
        );

        $this->assertStringContainsString( '<h2>Jobs</h2>', $html );
        $this->assertStringContainsString( 'Direct access.', $html );
        $this->assertStringNotContainsString( '<script>', $html );
        $this->assertStringNotContainsString( '<img', $html );
        $this->assertStringNotContainsString( '<strong>', $html );
    }
}
