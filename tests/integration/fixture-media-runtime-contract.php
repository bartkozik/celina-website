<?php
/**
 * Proves fixture media does not grow when the attachment row is reset.
 *
 * @package Celina_Workbench
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

/**
 * Counts fixture-owned source images without changing the upload directory.
 *
 * @param string $directory Upload directory.
 * @return int
 */
function celina_fixture_media_runtime_file_count( string $directory ): int {
	if ( ! is_dir( $directory ) ) {
		WP_CLI::error( 'Fixture media runtime upload directory is missing.' );
	}

	$count = 0;
	foreach ( new DirectoryIterator( $directory ) as $entry ) {
		if (
			$entry->isFile() &&
			1 === preg_match( '/^fixture-news-featured(?:-[0-9]+)?\.png$/', $entry->getFilename() )
		) {
			$count++;
		}
	}

	return $count;
}

/**
 * Counts generated temporary fixture files without changing them.
 *
 * @param string $directory Upload directory.
 * @return int
 */
function celina_fixture_media_runtime_temp_file_count( string $directory ): int {
	$count = 0;
	foreach ( new DirectoryIterator( $directory ) as $entry ) {
		if (
			$entry->isFile() &&
			0 === strpos( $entry->getFilename(), '.celina-fixture-news-featured-' )
		) {
			$count++;
		}
	}

	return $count;
}

$fixture_media_bootstrap = ABSPATH . 'fixtures/bootstrap.php';
require $fixture_media_bootstrap;

$fixture_media_id = celina_fixture_post_id( 'attachment', 'fixture-news-featured' );
if ( $fixture_media_id <= 0 ) {
	WP_CLI::error( 'Fixture media runtime attachment is missing before the reset.' );
}

$fixture_media_file_before = get_attached_file( $fixture_media_id );
$fixture_media_url_before  = wp_get_attachment_url( $fixture_media_id );
if (
	! is_string( $fixture_media_file_before ) ||
	! is_file( $fixture_media_file_before ) ||
	is_link( $fixture_media_file_before ) ||
	! is_string( $fixture_media_url_before ) ||
	'' === $fixture_media_url_before ||
	'fixture-news-featured.png' !== basename( $fixture_media_file_before ) ||
	'fixture-news-featured.png' !== basename( (string) wp_parse_url( $fixture_media_url_before, PHP_URL_PATH ) )
) {
	WP_CLI::error( 'Fixture media runtime attachment does not resolve to the canonical local file and URL.' );
}

$fixture_media_directory    = dirname( $fixture_media_file_before );
$fixture_media_count_before = celina_fixture_media_runtime_file_count( $fixture_media_directory );
if ( 0 !== celina_fixture_media_runtime_temp_file_count( $fixture_media_directory ) ) {
	WP_CLI::error( 'Fixture media runtime left a temporary file before the DB reset.' );
}

global $wpdb;
$fixture_media_deleted_meta = $wpdb->delete(
	$wpdb->postmeta,
	[ 'post_id' => $fixture_media_id ],
	[ '%d' ]
);
$fixture_media_deleted_post = $wpdb->delete(
	$wpdb->posts,
	[ 'ID' => $fixture_media_id ],
	[ '%d' ]
);
clean_post_cache( $fixture_media_id );

if ( false === $fixture_media_deleted_meta || 1 !== $fixture_media_deleted_post ) {
	WP_CLI::error( 'Fixture media runtime could not reset the exact attachment DB record.' );
}

require $fixture_media_bootstrap;

$fixture_media_id_after = celina_fixture_post_id( 'attachment', 'fixture-news-featured' );
$fixture_media_file_after = $fixture_media_id_after > 0
	? get_attached_file( $fixture_media_id_after )
	: false;
$fixture_media_url_after = $fixture_media_id_after > 0
	? wp_get_attachment_url( $fixture_media_id_after )
	: false;

if (
	! is_string( $fixture_media_file_after ) ||
	! is_string( $fixture_media_url_after ) ||
	is_link( $fixture_media_file_after ) ||
	basename( $fixture_media_file_before ) !== basename( $fixture_media_file_after ) ||
	$fixture_media_file_before !== $fixture_media_file_after ||
	$fixture_media_url_before !== $fixture_media_url_after
) {
	WP_CLI::error( 'Fixture media runtime changed basename, path, or URL after the DB reset.' );
}

$fixture_media_count_after = celina_fixture_media_runtime_file_count( $fixture_media_directory );
if ( $fixture_media_count_before !== $fixture_media_count_after ) {
	WP_CLI::error(
		sprintf(
			'Fixture media runtime file count grew from %1$d to %2$d after the DB reset.',
			$fixture_media_count_before,
			$fixture_media_count_after
		)
	);
}
if ( 0 !== celina_fixture_media_runtime_temp_file_count( $fixture_media_directory ) ) {
	WP_CLI::error( 'Fixture media runtime left a temporary file after the DB reset.' );
}

WP_CLI::success( 'Fixture media runtime contract passed.' );
