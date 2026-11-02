<?php
/**
 * Report whether the disposable E2E WordPress bootstrap has completed.
 *
 * This file is mapped into wp-env only. It is not part of the CELINA theme or
 * any production deployment artifact.
 *
 * @package Celina_Workbench
 */

declare( strict_types=1 );

$marker = defined( 'CELINA_E2E_READINESS_MARKER' )
	? (string) constant( 'CELINA_E2E_READINESS_MARKER' )
	: __DIR__ . '/.celina-e2e-ready';
$ready  = is_file( $marker );

http_response_code( $ready ? 200 : 503 );

if ( ! headers_sent() ) {
	header( 'Content-Type: text/plain; charset=utf-8' );
}

echo $ready ? "ready\n" : "not ready\n";
