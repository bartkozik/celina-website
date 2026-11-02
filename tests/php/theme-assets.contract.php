<?php
/** Verify cache invalidation even when release archives preserve file timestamps. */
declare( strict_types=1 );

define( 'ABSPATH', __DIR__ );
$asset_root = sys_get_temp_dir() . '/celina-assets-' . bin2hex( random_bytes( 6 ) );
$versions = [];
$registered_scripts = [];
$enqueued_scripts = [];
$block_shell_enabled = false;

function add_action( ...$args ): void {}
function add_filter( ...$args ): void {}
function get_stylesheet_directory(): string {
	return $GLOBALS['asset_root'];
}
function get_stylesheet_directory_uri(): string {
	return 'https://example.test/theme';
}
function wp_get_theme(): object {
	return new class {
		public function get( string $key ): string {
			return '0.1.5';
		}
	};
}
function wp_enqueue_style( string $handle, string $url, array $deps, $version ): void {
	$GLOBALS['versions'][ $handle ] = $version;
}
function celina_block_shell_enabled(): bool {
	return $GLOBALS['block_shell_enabled'];
}
function wp_register_script( string $handle, string $url, array $deps, $version, bool $footer ): void {
	$GLOBALS['registered_scripts'][ $handle ] = compact( 'url', 'deps', 'version', 'footer' );
	$GLOBALS['versions'][ $handle ] = $version;
}
function wp_enqueue_script( string $handle, string $url = '', array $deps = [], $version = false, bool $footer = false ): void {
	$script = '' === $url ? $GLOBALS['registered_scripts'][ $handle ] : compact( 'url', 'deps', 'version', 'footer' );
	$GLOBALS['enqueued_scripts'][ $handle ] = $script;
	$GLOBALS['versions'][ $handle ] = $script['version'];
}

require dirname( __DIR__, 2 ) . '/wordpress/themes/celina-child/inc/setup.php';

$assets = [
	'celina-sales-patterns' => 'assets/css/sales-patterns.css',
	'celina-tokens' => 'assets/css/tokens.css',
	'celina-site-shell' => 'assets/css/site-shell.css',
	'celina-block-shell' => 'assets/css/block-shell.css',
	'celina-content-templates' => 'assets/css/content-templates.css',
	'celina-navigation' => 'assets/js/navigation.js',
	'celina-block-navigation' => 'assets/js/block-shell-navigation.js',
];
$failures = [];
try {
	mkdir( $asset_root . '/assets/css', 0700, true );
	mkdir( $asset_root . '/assets/js', 0700, true );
	foreach ( $assets as $path ) {
		file_put_contents( $asset_root . '/' . $path, 'before' );
		touch( $asset_root . '/' . $path, 315532800 );
	}
	celina_enqueue_assets();
	if ( ! isset( $registered_scripts['celina-block-navigation'] ) || isset( $enqueued_scripts['celina-block-navigation'] ) ) {
		$failures[] = 'Disabled block shell registers its script without loading it';
	}
	$block_shell_enabled = true;
	celina_enqueue_assets();
	$expected = [ 'url' => 'https://example.test/theme/assets/js/block-shell-navigation.js', 'deps' => [], 'version' => hash( 'sha256', 'before' ), 'footer' => true ];
	if ( ( $enqueued_scripts['celina-block-navigation'] ?? null ) !== $expected ) {
		$failures[] = 'Enabled block shell enqueues its registered, content-versioned footer script';
	}
	if ( ! isset( $enqueued_scripts['celina-navigation'] ) ) {
		$failures[] = 'Existing navigation script remains available';
	}
	$block_shell_enabled = false;
	$enqueued_scripts = [];
	celina_enqueue_assets();
	if ( isset( $enqueued_scripts['celina-block-navigation'] ) ) {
		$failures[] = 'A subsequent disabled request must not enqueue block navigation';
	}
	$before = $versions;
	celina_enqueue_assets();
	if ( $before !== $versions ) {
		$failures[] = 'Unchanged assets must keep stable cache keys';
	}
	foreach ( $assets as $handle => $path ) {
		file_put_contents( $asset_root . '/' . $path, 'after!' );
		touch( $asset_root . '/' . $path, 315532800 );
		clearstatcache();
		celina_enqueue_assets();
		if ( $versions[ $handle ] === $before[ $handle ] ) {
			$failures[] = "{$handle}: changed content with identical size/mtime must invalidate cache";
		}
	}
} finally {
	foreach ( $assets as $path ) {
		unlink( $asset_root . '/' . $path );
	}
	rmdir( $asset_root . '/assets/css' );
	rmdir( $asset_root . '/assets/js' );
	rmdir( $asset_root . '/assets' );
	rmdir( $asset_root );
}
if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}
echo "Theme asset cache contract passed\n";
