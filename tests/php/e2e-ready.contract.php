<?php
/**
 * Contract coverage for the local E2E readiness endpoint.
 *
 * @package Celina_Workbench
 */

declare( strict_types=1 );

$marker = tempnam( sys_get_temp_dir(), 'celina-e2e-ready-' );

if ( false === $marker ) {
	throw new RuntimeException( 'Could not allocate readiness marker fixture.' );
}

unlink( $marker );
define( 'CELINA_E2E_READINESS_MARKER', $marker );

/**
 * Execute the endpoint and return its status and body.
 *
 * @return array{status: int, body: string}
 */
function celina_readiness_contract_run(): array {
	http_response_code( 200 );
	ob_start();
	require dirname( __DIR__, 2 ) . '/fixtures/e2e-ready.php';
	$body = (string) ob_get_clean();

	return [
		'status' => http_response_code(),
		'body'   => $body,
	];
}

$not_ready = celina_readiness_contract_run();

if ( 503 !== $not_ready['status'] || "not ready\n" !== $not_ready['body'] ) {
	throw new RuntimeException( 'Missing marker did not return not-ready HTTP 503.' );
}

file_put_contents( $marker, "ready\n" );
$ready = celina_readiness_contract_run();
unlink( $marker );

if ( 200 !== $ready['status'] || "ready\n" !== $ready['body'] ) {
	throw new RuntimeException( 'Present marker did not return ready HTTP 200.' );
}

echo "E2E readiness contract passed\n";
