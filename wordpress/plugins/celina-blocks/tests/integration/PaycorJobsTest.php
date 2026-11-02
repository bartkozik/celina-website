<?php
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class PaycorJobsTest extends WP_UnitTestCase {
    private const CLIENT_ID = '00000000000000000000000000000000';

    private const SECOND_CLIENT_ID = 'abcdef0123456789abcdef0123456789';

    private function render_jobs( array $attributes = [] ): string {
        if ( [] === $attributes ) {
            return do_blocks( '<!-- wp:celina/paycor-jobs /-->' );
        }

        return do_blocks( '<!-- wp:celina/paycor-jobs ' . wp_json_encode( $attributes ) . ' /-->' );
    }

    private function valid_attributes( array $overrides = [] ): array {
        return array_merge(
            [
                'heading'      => 'Open positions',
                'clientId'     => self::CLIENT_ID,
                'fallbackText' => 'If the list does not load, use the direct careers page.',
                'jobsLinkText' => 'View jobs on Paycor',
            ],
            $overrides
        );
    }

    public function test_valid_client_renders_exact_embed_and_accessible_fallback_link(): void {
        $html = $this->render_jobs( $this->valid_attributes() );
        $script_url = 'https://recruitingbypaycor.com/career/iframe.action?clientId=' . self::CLIENT_ID;
        $careers_url = 'https://recruitingbypaycor.com/career/AggregateCareerHome.action?clientId=' . self::CLIENT_ID;

        $this->assertStringContainsString( '<div class="celina-paycor-jobs__embed">', $html );
        $this->assertStringContainsString( '<script id="gnewtonjs" src="' . $script_url . '"></script>', $html );
        $this->assertStringContainsString( 'If the list does not load, use the direct careers page.', $html );
        $this->assertStringContainsString( 'href="' . $careers_url . '"', $html );
        $this->assertStringContainsString( 'target="_blank"', $html );
        $this->assertStringContainsString( 'rel="noopener noreferrer"', $html );
        $this->assertMatchesRegularExpression( '/>\s*View jobs on Paycor\s*<\/a>/', $html );
    }

    public function test_blank_or_invalid_client_ids_render_status_without_vendor_requests(): void {
        $invalid_client_ids = [
            '',
            '8a7883c691c40f800191c43b305a003',
            '00000000000000000000000000000000a',
            '8a7883c691c40f800191c43b305a003z',
            ' 00000000000000000000000000000000',
            'https://evil.example/',
            '"><script src="https://evil.example/payload.js"></script>',
        ];

        foreach ( $invalid_client_ids as $client_id ) {
            $html = $this->render_jobs( $this->valid_attributes( [ 'clientId' => $client_id ] ) );

            $this->assertStringContainsString( 'role="status"', $html );
            $this->assertStringContainsString( 'Paycor jobs are not configured in this workbench.', $html );
            $this->assertStringNotContainsString( 'recruitingbypaycor.com', strtolower( $html ) );
            $this->assertStringNotContainsString( 'bamboohr.com', strtolower( $html ) );
            $this->assertStringNotContainsString( '<script', strtolower( $html ) );
        }
    }

    public function test_editorial_text_is_sanitized_and_escaped(): void {
        $html = $this->render_jobs(
            $this->valid_attributes(
                [
                    'heading'      => '<script>alert(1)</script> Jobs',
                    'fallbackText' => '<img src=x onerror=alert(2)> Direct access.',
                    'jobsLinkText' => '<strong>View jobs</strong>',
                ]
            )
        );

        $this->assertStringContainsString( '<h2>Jobs</h2>', $html );
        $this->assertStringContainsString( 'Direct access.', $html );
        $this->assertMatchesRegularExpression( '/>\s*View jobs\s*<\/a>/', $html );
        $this->assertStringNotContainsString( '<script>alert', $html );
        $this->assertStringNotContainsString( '<img', $html );
        $this->assertStringNotContainsString( '<strong>', $html );
    }

    public function test_invalid_block_does_not_consume_page_wide_embed_claim(): void {
        $html = $this->render_jobs( $this->valid_attributes( [ 'clientId' => '' ] ) );
        $html .= $this->render_jobs( $this->valid_attributes() );

        $this->assertSame( 1, substr_count( $html, 'id="gnewtonjs"' ) );
        $this->assertSame( 1, substr_count( $html, 'iframe.action?clientId=' . self::CLIENT_ID ) );
    }

    public function test_equal_valid_clients_render_one_embed_and_two_fallback_links(): void {
        $attributes = $this->valid_attributes();
        $html = $this->render_jobs( $attributes ) . $this->render_jobs( $attributes );
        $careers_url = 'https://recruitingbypaycor.com/career/AggregateCareerHome.action?clientId=' . self::CLIENT_ID;

        $this->assertSame( 1, substr_count( $html, 'id="gnewtonjs"' ) );
        $this->assertSame( 1, substr_count( $html, 'class="celina-paycor-jobs__embed"' ) );
        $this->assertSame( 1, substr_count( $html, 'iframe.action?clientId=' . self::CLIENT_ID ) );
        $this->assertSame( 2, substr_count( $html, 'href="' . $careers_url . '"' ) );
        $this->assertSame( 2, substr_count( $html, 'If the list does not load, use the direct careers page.' ) );
        $this->assertSame( 2, substr_count( $html, 'target="_blank"' ) );
        $this->assertSame( 2, substr_count( $html, 'rel="noopener noreferrer"' ) );
    }

    public function test_different_valid_clients_make_the_second_block_link_only(): void {
        $html = $this->render_jobs( $this->valid_attributes() );
        $html .= $this->render_jobs(
            $this->valid_attributes(
                [
                    'clientId'     => self::SECOND_CLIENT_ID,
                    'fallbackText' => 'Use the second direct careers page.',
                    'jobsLinkText' => 'View second Paycor jobs',
                ]
            )
        );

        $this->assertSame( 1, substr_count( $html, 'id="gnewtonjs"' ) );
        $this->assertSame( 1, substr_count( $html, 'class="celina-paycor-jobs__embed"' ) );
        $this->assertSame( 1, substr_count( $html, 'iframe.action?clientId=' . self::CLIENT_ID ) );
        $this->assertStringNotContainsString( 'iframe.action?clientId=' . self::SECOND_CLIENT_ID, $html );
        $this->assertStringContainsString(
            'href="https://recruitingbypaycor.com/career/AggregateCareerHome.action?clientId=' . self::SECOND_CLIENT_ID . '"',
            $html
        );
        $this->assertStringContainsString( 'Use the second direct careers page.', $html );
        $this->assertMatchesRegularExpression( '/>\s*View second Paycor jobs\s*<\/a>/', $html );
    }
}
