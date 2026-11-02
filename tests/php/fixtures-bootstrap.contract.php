<?php
/**
 * Contract coverage for the local WordPress fixture bootstrap.
 *
 * @package Celina_Workbench
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

final class WP_Error {
	private string $message;

	public function __construct( string $message ) {
		$this->message = $message;
	}

	public function get_error_message(): string {
		return $this->message;
	}
}

final class WP_User {
	public int $ID;
	public string $display_name;
	public string $user_login;

	public function __construct( int $id, string $login, string $display_name ) {
		$this->ID           = $id;
		$this->user_login   = $login;
		$this->display_name = $display_name;
	}
}

final class Fixture_Cli_Error extends RuntimeException {}

final class WP_CLI {
	public static function error( $message ): void {
		throw new Fixture_Cli_Error( (string) $message );
	}
}

final class Fixture_WP_Rewrite {
	public string $permalink_structure = '';

	public function set_permalink_structure( string $structure ): void {
		global $fixture_contract_options;

		$this->permalink_structure = $structure;
		$fixture_contract_options['permalink_structure'] = $structure;
	}
}

$fixture_contract_options      = [];
$fixture_contract_posts        = [];
$fixture_contract_post_meta    = [];
$fixture_contract_post_tags    = [];
$fixture_contract_thumbnails   = [];
$fixture_contract_user_meta    = [];
$fixture_contract_users        = [
	1 => new WP_User( 1, 'admin', 'admin' ),
];
$fixture_contract_attachment_files = [];
$fixture_contract_attachment_metadata = [];
$fixture_contract_upload_error = '';
$fixture_contract_upload_mkdir_error = false;
$fixture_contract_upload_path_override = null;
$fixture_contract_upload_root  = '';
$fixture_contract_upload_roots = [];
$fixture_contract_upload_bits_calls = 0;
$fixture_contract_categories   = [];
$fixture_contract_next_id      = 1;
$fixture_contract_next_category_id = 1;
$fixture_contract_term_insert_error = false;
$fixture_contract_insert_error = false;
$fixture_contract_menu_items   = [];
$fixture_contract_menus        = [];
$fixture_contract_next_menu_id = 1;
$fixture_contract_next_menu_item_id = 1;
$fixture_contract_theme_mods   = [];
$fixture_contract_update_error = false;
$fixture_contract_flushes      = 0;
$wp_rewrite                    = new Fixture_WP_Rewrite();

function fixture_contract_reset( array $posts = [], bool $preserve_uploads = false ): void {
	global $fixture_contract_categories;
	global $fixture_contract_attachment_files;
	global $fixture_contract_attachment_metadata;
	global $fixture_contract_insert_error;
	global $fixture_contract_next_category_id;
	global $fixture_contract_term_insert_error;
	global $fixture_contract_next_id;
	global $fixture_contract_next_menu_id;
	global $fixture_contract_next_menu_item_id;
	global $fixture_contract_menu_items;
	global $fixture_contract_menus;
	global $fixture_contract_options;
	global $fixture_contract_posts;
	global $fixture_contract_post_meta;
	global $fixture_contract_post_tags;
	global $fixture_contract_thumbnails;
	global $fixture_contract_theme_mods;
	global $fixture_contract_update_error;
	global $fixture_contract_upload_bits_calls;
	global $fixture_contract_upload_error;
	global $fixture_contract_upload_mkdir_error;
	global $fixture_contract_upload_path_override;
	global $fixture_contract_upload_root;
	global $fixture_contract_upload_roots;
	global $fixture_contract_user_meta;
	global $fixture_contract_users;
	global $fixture_contract_flushes;
	global $wp_rewrite;

	$fixture_contract_options      = [];
	$fixture_contract_posts        = [];
	$fixture_contract_post_meta    = [];
	$fixture_contract_post_tags    = [];
	$fixture_contract_thumbnails   = [];
	$fixture_contract_user_meta    = [];
	$fixture_contract_users        = [
		1 => new WP_User( 1, 'admin', 'admin' ),
	];
	$fixture_contract_attachment_files = [];
	$fixture_contract_attachment_metadata = [];
	$fixture_contract_categories   = [];
	$fixture_contract_insert_error = false;
	$fixture_contract_term_insert_error = false;
	$fixture_contract_update_error = false;
	$fixture_contract_upload_bits_calls = 0;
	$fixture_contract_upload_error = '';
	$fixture_contract_upload_mkdir_error = false;
	$fixture_contract_upload_path_override = null;
	if ( ! $preserve_uploads ) {
		$fixture_contract_upload_root = sys_get_temp_dir() . '/celina-fixture-contract-' . str_replace( '.', '-', uniqid( '', true ) );
		$fixture_contract_upload_roots[] = $fixture_contract_upload_root;
	}
	$fixture_contract_next_id      = 1;
	$fixture_contract_next_category_id = 1;
	$fixture_contract_next_menu_id = 1;
	$fixture_contract_next_menu_item_id = 1;
	$fixture_contract_menu_items   = [];
	$fixture_contract_menus        = [];
	$fixture_contract_theme_mods   = [];
	$fixture_contract_flushes      = 0;
	$wp_rewrite                    = new Fixture_WP_Rewrite();

	foreach ( $posts as $post ) {
		$post['ID'] = $fixture_contract_next_id;
		$fixture_contract_posts[ $fixture_contract_next_id ] = $post;
		$fixture_contract_next_id++;
	}
}

function fixture_contract_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function fixture_contract_cleanup_upload_roots(): void {
	global $fixture_contract_upload_roots;

	foreach ( $fixture_contract_upload_roots as $root ) {
		if (
			! is_string( $root ) ||
			0 !== strpos( $root, sys_get_temp_dir() . '/celina-fixture-contract-' ) ||
			! is_dir( $root )
		) {
			continue;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $entry ) {
			if ( $entry->isDir() ) {
				rmdir( $entry->getPathname() );
			} else {
				unlink( $entry->getPathname() );
			}
		}
		rmdir( $root );
	}
}

register_shutdown_function( 'fixture_contract_cleanup_upload_roots' );

function fixture_contract_expect_error( callable $callback, string $message, string $expected_detail = '' ): void {
	try {
		$callback();
	} catch ( Fixture_Cli_Error $error ) {
		if ( '' !== $expected_detail && false === strpos( $error->getMessage(), $expected_detail ) ) {
			throw new RuntimeException( $message . ': ' . $error->getMessage() );
		}

		return;
	}

	throw new RuntimeException( $message );
}

function fixture_contract_run_bootstrap(): void {
	require dirname( __DIR__, 2 ) . '/fixtures/bootstrap.php';
}

function fixture_contract_posts( string $post_type, string $post_name ): array {
	global $fixture_contract_posts;

	return array_values(
		array_filter(
			$fixture_contract_posts,
			static function ( array $post ) use ( $post_type, $post_name ): bool {
				return $post_type === $post['post_type'] && $post_name === $post['post_name'];
			}
		)
	);
}

function fixture_contract_post( string $post_type, string $post_name ): array {
	$posts = fixture_contract_posts( $post_type, $post_name );
	fixture_contract_assert( 1 === count( $posts ), "Expected one {$post_type} {$post_name}" );

	return $posts[0];
}

function fixture_contract_owned_upload_files(): array {
	global $fixture_contract_upload_root;

	$upload_path = $fixture_contract_upload_root . '/uploads/2026/08';
	if ( ! is_dir( $upload_path ) ) {
		return [];
	}

	$files = [];
	foreach ( new DirectoryIterator( $upload_path ) as $entry ) {
		if (
			$entry->isFile() &&
			1 === preg_match( '/^fixture-news-featured(?:-[0-9]+)?\.png$/', $entry->getFilename() )
		) {
			$files[] = $entry->getPathname();
		}
	}
	sort( $files );

	return $files;
}

function fixture_contract_temporary_upload_files(): array {
	global $fixture_contract_upload_root;

	$upload_path = $fixture_contract_upload_root . '/uploads/2026/08';
	if ( ! is_dir( $upload_path ) ) {
		return [];
	}

	$files = [];
	foreach ( new DirectoryIterator( $upload_path ) as $entry ) {
		if (
			$entry->isFile() &&
			0 === strpos( $entry->getFilename(), '.celina-fixture-news-featured-' )
		) {
			$files[] = $entry->getPathname();
		}
	}
	sort( $files );

	return $files;
}

function update_option( $option, $value ): bool {
	global $fixture_contract_options;

	$fixture_contract_options[ $option ] = $value;

	return true;
}

function get_option( $option, $default = false ) {
	global $fixture_contract_options;

	return $fixture_contract_options[ $option ] ?? $default;
}

function home_url( $path = '/' ): string {
	return 'https://example.test' . $path;
}

function wp_json_encode( $value ): string {
	$encoded = json_encode( $value );

	return is_string( $encoded ) ? $encoded : '';
}

function get_theme_file_uri( $path = '' ): string {
	return 'https://example.test/wp-content/themes/celina-child/' . ltrim( (string) $path, '/' );
}

function wp_upload_dir( $time = null, $create_dir = true, $refresh_cache = false ): array {
	global $fixture_contract_upload_error;
	global $fixture_contract_upload_path_override;
	global $fixture_contract_upload_root;

	$basedir = $fixture_contract_upload_root . '/uploads';
	$path    = is_string( $fixture_contract_upload_path_override )
		? $fixture_contract_upload_path_override
		: $basedir . '/2026/08';
	if ( $create_dir ) {
		wp_mkdir_p( $path );
	}

	return [
		'basedir' => $basedir,
		'baseurl' => 'https://example.test/wp-content/uploads',
		'error'   => $fixture_contract_upload_error,
		'path'    => $path,
		'url'     => 'https://example.test/wp-content/uploads/2026/08',
	];
}

function wp_mkdir_p( $target ): bool {
	global $fixture_contract_upload_mkdir_error;

	if ( $fixture_contract_upload_mkdir_error ) {
		return false;
	}

	return is_dir( $target ) || mkdir( $target, 0777, true );
}

function trailingslashit( $value ): string {
	return rtrim( (string) $value, '/\\' ) . '/';
}

function wp_normalize_path( $path ): string {
	return str_replace( '\\', '/', (string) $path );
}

function wp_upload_bits( $name, $deprecated, $bits, $time = null ): array {
	global $fixture_contract_upload_bits_calls;

	$fixture_contract_upload_bits_calls++;
	$upload = wp_upload_dir( $time, false, true );
	if ( ! empty( $upload['error'] ) || ! wp_mkdir_p( $upload['path'] ) ) {
		return [
			'error' => 'fixture upload failed',
			'file'  => '',
			'url'   => '',
		];
	}

	$extension = pathinfo( $name, PATHINFO_EXTENSION );
	$stem      = pathinfo( $name, PATHINFO_FILENAME );
	$filename  = $name;
	$suffix    = 1;
	while ( file_exists( trailingslashit( $upload['path'] ) . $filename ) ) {
		$filename = $stem . '-' . $suffix . ( '' === $extension ? '' : '.' . $extension );
		$suffix++;
	}
	$file = trailingslashit( $upload['path'] ) . $filename;
	file_put_contents( $file, $bits );

	return [
		'error' => false,
		'file'  => $file,
		'url'   => trailingslashit( $upload['url'] ) . $filename,
	];
}

function get_attached_file( int $attachment_id ) {
	global $fixture_contract_attachment_files;

	return $fixture_contract_attachment_files[ $attachment_id ] ?? false;
}

function wp_get_attachment_url( int $attachment_id ) {
	global $fixture_contract_posts;

	return $fixture_contract_posts[ $attachment_id ]['guid'] ?? false;
}

function update_attached_file( int $attachment_id, string $file ): bool {
	global $fixture_contract_attachment_files;

	$fixture_contract_attachment_files[ $attachment_id ] = $file;

	return true;
}

function _wp_relative_upload_path( string $path ): string {
	return '2026/08/' . basename( $path );
}

function wp_update_attachment_metadata( int $attachment_id, array $data ): bool {
	global $fixture_contract_attachment_metadata;

	$fixture_contract_attachment_metadata[ $attachment_id ] = $data;

	return true;
}

function get_user_by( $field, $value ) {
	global $fixture_contract_users;

	foreach ( $fixture_contract_users as $user ) {
		if ( 'login' === $field && $value === $user->user_login ) {
			return $user;
		}
	}

	return false;
}

function update_user_meta( int $user_id, string $key, $value ): bool {
	global $fixture_contract_user_meta;

	$fixture_contract_user_meta[ $user_id ][ $key ] = $value;

	return true;
}

function wp_update_user( array $userdata ) {
	global $fixture_contract_users;

	$user_id = (int) ( $userdata['ID'] ?? 0 );
	if ( ! isset( $fixture_contract_users[ $user_id ] ) ) {
		return new WP_Error( 'missing user' );
	}

	if ( isset( $userdata['display_name'] ) ) {
		$fixture_contract_users[ $user_id ]->display_name = (string) $userdata['display_name'];
	}

	return $user_id;
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

function get_nav_menu_locations(): array {
	global $fixture_contract_theme_mods;

	return $fixture_contract_theme_mods['nav_menu_locations'] ?? [];
}

function set_theme_mod( $name, $value ): bool {
	global $fixture_contract_theme_mods;

	$fixture_contract_theme_mods[ $name ] = $value;

	return true;
}

function wp_get_nav_menu_object( $name ) {
	global $fixture_contract_menus;

	foreach ( $fixture_contract_menus as $menu ) {
		if ( $name === $menu->name ) {
			return $menu;
		}
	}

	return false;
}

function wp_create_nav_menu( $name ) {
	global $fixture_contract_menus;
	global $fixture_contract_next_menu_id;

	$menu = (object) [
		'name'    => $name,
		'term_id' => $fixture_contract_next_menu_id,
	];
	$fixture_contract_menus[ $fixture_contract_next_menu_id ] = $menu;
	$fixture_contract_next_menu_id++;

	return $menu->term_id;
}

function get_term_by( $field, $value, $taxonomy ) {
	global $fixture_contract_categories;

	if ( 'category' !== $taxonomy || ! in_array( $field, [ 'name', 'slug' ], true ) ) {
		return false;
	}

	foreach ( $fixture_contract_categories as $category ) {
		if ( $value === $category->{$field} ) {
			return $category;
		}
	}

	return false;
}

function wp_insert_term( $term, $taxonomy, array $args = [] ) {
	global $fixture_contract_categories;
	global $fixture_contract_next_category_id;
	global $fixture_contract_term_insert_error;

	if ( $fixture_contract_term_insert_error ) {
		return new WP_Error( 'term insertion failed' );
	}

	$slug = $args['slug'] ?? '';
	foreach ( $fixture_contract_categories as $category ) {
		if ( $slug === $category->slug ) {
			return new WP_Error( 'term slug already exists' );
		}
	}

	$category = (object) [
		'name'    => $term,
		'parent'  => (int) ( $args['parent'] ?? 0 ),
		'slug'    => $slug,
		'term_id' => $fixture_contract_next_category_id,
	];
	$fixture_contract_categories[ $fixture_contract_next_category_id ] = $category;
	$fixture_contract_next_category_id++;

	return [
		'term_id' => $category->term_id,
	];
}

function wp_get_nav_menu_items( $menu_id, array $args = [] ): array {
	global $fixture_contract_menu_items;

	return array_values(
		array_filter(
			$fixture_contract_menu_items,
			static function ( object $item ) use ( $menu_id ): bool {
				return (int) $menu_id === $item->menu_id;
			}
		)
	);
}

function wp_update_nav_menu_item( $menu_id, $item_id, array $args ) {
	global $fixture_contract_menu_items;
	global $fixture_contract_next_menu_item_id;

	if ( ! $item_id ) {
		$item_id = $fixture_contract_next_menu_item_id;
		$fixture_contract_next_menu_item_id++;
	}

	$fixture_contract_menu_items[ $item_id ] = (object) [
		'ID'      => (int) $item_id,
		'menu_id' => (int) $menu_id,
		'title'   => $args['menu-item-title'],
		'url'     => $args['menu-item-url'],
	];

	return $item_id;
}

function wp_delete_post( $post_id, $force_delete = false ): bool {
	global $fixture_contract_menu_items;

	unset( $fixture_contract_menu_items[ $post_id ] );

	return true;
}

function flush_rewrite_rules(): void {
	global $fixture_contract_flushes;

	$fixture_contract_flushes++;
}

function get_posts( array $args ): array {
	global $fixture_contract_posts;

	$statuses = $args['post_status'] ?? [ 'publish' ];
	$statuses = is_array( $statuses ) ? $statuses : [ $statuses ];
	$posts    = array_filter(
		$fixture_contract_posts,
		static function ( array $post ) use ( $args, $statuses ): bool {
			return $args['post_type'] === $post['post_type'] &&
				$args['name'] === $post['post_name'] &&
				in_array( $post['post_status'], $statuses, true );
		}
	);
	$posts    = array_slice( $posts, 0, $args['numberposts'] ?? -1, true );

	if ( 'ids' === ( $args['fields'] ?? '' ) ) {
		return array_map( 'intval', array_keys( $posts ) );
	}

	return array_map( static fn( array $post ): object => (object) $post, $posts );
}

function get_post_field( string $field, int $post_id ) {
	global $fixture_contract_posts;

	return $fixture_contract_posts[ $post_id ][ $field ] ?? null;
}

function update_post_meta( int $post_id, string $key, $value ): bool {
	global $fixture_contract_post_meta;

	$fixture_contract_post_meta[ $post_id ][ $key ] = $value;

	return true;
}

function wp_set_post_tags( int $post_id, array $tags, bool $append = false ): array {
	global $fixture_contract_post_tags;

	$fixture_contract_post_tags[ $post_id ] = $tags;

	return range( 1, count( $tags ) );
}

function set_post_thumbnail( int $post_id, int $thumbnail_id ): bool {
	global $fixture_contract_thumbnails;

	$fixture_contract_thumbnails[ $post_id ] = $thumbnail_id;

	return true;
}

function delete_post_thumbnail( int $post_id ): bool {
	global $fixture_contract_thumbnails;

	unset( $fixture_contract_thumbnails[ $post_id ] );

	return true;
}

function wp_insert_post( array $postarr, bool $wp_error = false ) {
	global $fixture_contract_insert_error;
	global $fixture_contract_next_id;
	global $fixture_contract_posts;

	if ( $fixture_contract_insert_error ) {
		return $wp_error ? new WP_Error( 'insert failed' ) : 0;
	}

	$post_name = $postarr['post_name'];
	foreach ( $fixture_contract_posts as $post ) {
		if ( 'attachment' === $post['post_type'] && $post_name === $post['post_name'] ) {
			$post_name .= '-2';
		}
	}

	$postarr['ID']        = $fixture_contract_next_id;
	$postarr['post_name'] = $post_name;
	$fixture_contract_posts[ $fixture_contract_next_id ] = $postarr;
	$fixture_contract_next_id++;

	return $postarr['ID'];
}

function wp_update_post( array $postarr, bool $wp_error = false ) {
	global $fixture_contract_posts;
	global $fixture_contract_update_error;

	if ( $fixture_contract_update_error ) {
		return $wp_error ? new WP_Error( 'update failed' ) : 0;
	}
	if ( ! isset( $fixture_contract_posts[ $postarr['ID'] ] ) ) {
		return $wp_error ? new WP_Error( 'missing post' ) : 0;
	}

	$fixture_contract_posts[ $postarr['ID'] ] = array_merge(
		$fixture_contract_posts[ $postarr['ID'] ],
		$postarr
	);

	return $postarr['ID'];
}

function is_wp_error( $thing ): bool {
	return $thing instanceof WP_Error;
}

fixture_contract_reset();
fixture_contract_run_bootstrap();
fixture_contract_run_bootstrap();

fixture_contract_assert(
	'CELINA Workbench' === $fixture_contract_options['blogname'],
	'Blog name was not seeded'
);
fixture_contract_assert(
	'5' === $fixture_contract_options['celina_fixture_version'],
	'Fixture version was not seeded'
);
fixture_contract_assert(
	13 === count( $fixture_contract_posts ),
	'Fixture bootstrap was not idempotent'
);
$fixture_home = fixture_contract_post( 'page', 'fixture-home' );
foreach ( [
	'celina/hero',
	'celina/faq',
	'celina/news-grid',
	'celina/hubspot-form',
	'celina/paycor-jobs',
] as $block_name ) {
	fixture_contract_assert(
		false !== strpos( $fixture_home['post_content'], $block_name ),
		"Fixture Home is missing the {$block_name} block"
	);
}
fixture_contract_assert(
	false !== strpos(
		$fixture_home['post_content'],
		'<!-- wp:celina/paycor-jobs {"clientId":""} /-->'
	),
	'Fixture Home Paycor jobs block must have an explicit empty client ID'
);
fixture_contract_assert(
	false === strpos( $fixture_home['post_content'], '<!-- wp:celina/bamboohr-jobs' ),
	'Fixture Home must not include an active BambooHR jobs block'
);
fixture_contract_assert(
	2 === count( $fixture_contract_menu_items ),
	'Fixture navigation items were duplicated'
);
foreach ( [ 'fixture-home', 'fixture-news', 'fixture-about', 'fixture-form-patterns' ] as $slug ) {
	$page = fixture_contract_post( 'page', $slug );
	fixture_contract_assert( 'publish' === $page['post_status'], "Expected published page {$slug}" );
}
$fixture_form_presets = fixture_contract_post( 'page', 'fixture-form-patterns' );
foreach (
	[
		'Request a Callback',
		'Ask the CELINA Team',
		'Request a Quote',
		'Ask a Product or Technical Question',
	] as $heading
) {
	fixture_contract_assert(
		false !== strpos( $fixture_form_presets['post_content'], $heading ),
		"Fixture Form Presets is missing {$heading}"
	);
}
fixture_contract_assert(
	4 === substr_count(
		$fixture_form_presets['post_content'],
		'<!-- wp:celina/hubspot-form '
	),
	'Fixture Form Presets does not contain all four HubSpot blocks'
);
$fixture_news_page = fixture_contract_post( 'page', 'fixture-news' );
foreach ( [ 'celina/hero', 'celina/news-grid', 'celina/media-contact' ] as $block_name ) {
	fixture_contract_assert(
		false !== strpos( $fixture_news_page['post_content'], $block_name ),
		"Fixture News is missing the {$block_name} block"
	);
}
fixture_contract_assert(
	'page.corporate-news' === $fixture_contract_post_meta[ $fixture_news_page['ID'] ]['_celina_blueprint_key'],
	'Fixture News managed blueprint key mismatch'
);
fixture_contract_assert(
	'page' === $fixture_contract_options['show_on_front'],
	'Static front page not configured'
);
fixture_contract_assert(
	fixture_contract_post( 'page', 'fixture-home' )['ID'] === $fixture_contract_options['page_on_front'],
	'Front page ID mismatch'
);
fixture_contract_assert(
	fixture_contract_post( 'page', 'fixture-news' )['ID'] === $fixture_contract_options['page_for_posts'],
	'Posts page ID mismatch'
);
fixture_contract_assert(
	4 === count( $fixture_contract_categories ),
	'Fixture editorial categories were duplicated'
);
$fixture_base_category = get_term_by( 'slug', 'fixture-news', 'category' );
$fixture_company_updates = get_term_by( 'slug', 'company-updates', 'category' );
$fixture_manufacturing = get_term_by( 'slug', 'manufacturing', 'category' );
$fixture_community = get_term_by( 'slug', 'community', 'category' );
fixture_contract_assert(
	$fixture_base_category &&
	$fixture_company_updates &&
	$fixture_manufacturing &&
	$fixture_community,
	'Fixture editorial category set is incomplete'
);
fixture_contract_assert(
	'Company Updates' === $fixture_company_updates->name &&
	'Manufacturing' === $fixture_manufacturing->name &&
	'Community' === $fixture_community->name,
	'Fixture editorial category names changed'
);
foreach ( [ $fixture_company_updates, $fixture_manufacturing, $fixture_community ] as $category ) {
	fixture_contract_assert(
		(int) $fixture_base_category->term_id === (int) $category->parent,
		"Fixture category {$category->name} is outside the Fixture News scope"
	);
}
$fixture_news_block_match = [];
fixture_contract_assert(
	1 === preg_match(
		'/<!-- wp:celina\/news-grid (\{[^\n]+\}) \/-->/s',
		$fixture_news_page['post_content'],
		$fixture_news_block_match
	),
	'Fixture News Grid attributes were not serialized'
);
$fixture_news_attributes = json_decode( $fixture_news_block_match[1], true );
fixture_contract_assert(
	is_array( $fixture_news_attributes ) &&
	[
		(int) $fixture_company_updates->term_id,
		(int) $fixture_manufacturing->term_id,
		(int) $fixture_community->term_id,
	] === $fixture_news_attributes['navigationCategories'],
	'Fixture News Grid category navigation order mismatch'
);
foreach ( range( 1, 8 ) as $index ) {
	$post = fixture_contract_post( 'post', 'fixture-news-' . $index );
	fixture_contract_assert(
		in_array( (int) $fixture_base_category->term_id, $post['post_category'], true ),
		"Fixture News {$index} was not assigned to the base fixture category"
	);
	fixture_contract_assert(
		[ 'Fixture Editorial', 'CELINA News' ] === $fixture_contract_post_tags[ $post['ID'] ],
		"Fixture News {$index} tags mismatch"
	);
}
fixture_contract_assert(
	in_array( (int) $fixture_company_updates->term_id, fixture_contract_post( 'post', 'fixture-news-1' )['post_category'], true ) &&
	in_array( (int) $fixture_manufacturing->term_id, fixture_contract_post( 'post', 'fixture-news-1' )['post_category'], true ),
	'Fixture News 1 does not exercise ordered primary-category selection'
);
fixture_contract_assert(
	'Corporate Communications Manager' === $fixture_contract_user_meta[1]['celina_job_title'],
	'Fixture author job title mismatch'
);
fixture_contract_assert(
	'CELINA Editor' === get_user_by( 'login', 'admin' )->display_name,
	'Fixture author display name mismatch'
);
$fixture_featured_image = fixture_contract_post( 'attachment', 'fixture-news-featured' );
fixture_contract_assert(
	'image/png' === $fixture_featured_image['post_mime_type'] &&
	false !== strpos( $fixture_featured_image['guid'], 'fixture-news-featured.png' ),
	'Fixture featured image must use a local generated asset'
);
fixture_contract_assert(
	$fixture_contract_upload_root . '/uploads/2026/08/fixture-news-featured.png' ===
		$fixture_contract_attachment_files[ $fixture_featured_image['ID'] ] &&
	[
		'file'   => '2026/08/fixture-news-featured.png',
		'height' => 9,
		'sizes'  => [],
		'width'  => 16,
	] === $fixture_contract_attachment_metadata[ $fixture_featured_image['ID'] ],
	'Fixture featured image attachment metadata mismatch'
);
fixture_contract_assert(
	0 === $fixture_contract_upload_bits_calls,
	'Fixture bootstrap must not use WordPress unique-name upload behavior'
);
foreach ( range( 1, 8 ) as $index ) {
	$post = fixture_contract_post( 'post', 'fixture-news-' . $index );
	if ( 2 === $index ) {
		fixture_contract_assert(
		! isset( $fixture_contract_thumbnails[ $post['ID'] ] ),
		'Fixture News 2 must remain without a featured image'
		);
		continue;
	}

	fixture_contract_assert(
		$fixture_featured_image['ID'] === $fixture_contract_thumbnails[ $post['ID'] ],
		"Fixture News {$index} featured image mismatch"
	);
}
fixture_contract_assert(
	'https://example.test/fixture-news/' === $fixture_contract_menu_items[2]->url,
	'Fixture News navigation does not target the posts page'
);
fixture_contract_assert(
	'/%postname%/' === $fixture_contract_options['permalink_structure'],
	'Fixture permalink structure was not seeded'
);
fixture_contract_assert(
	1 === $fixture_contract_flushes,
	'Fixture rewrites were flushed more than once without a permalink change'
);
fixture_contract_assert(
	'/%postname%/' === $wp_rewrite->permalink_structure,
	'Fixture rewrite runtime did not receive the permalink structure'
);
foreach ( [ 'celina-utility', 'celina-primary', 'celina-footer', 'celina-legal', 'celina-social' ] as $location ) {
	fixture_contract_assert(
		isset( $fixture_contract_theme_mods['nav_menu_locations'][ $location ] ),
		"Fixture menu location {$location} was not assigned"
	);
}

$fixture_featured_file_before_reset = $fixture_contract_attachment_files[ $fixture_featured_image['ID'] ];
$fixture_featured_url_before_reset  = $fixture_featured_image['guid'];
fixture_contract_assert(
	1 === count( fixture_contract_owned_upload_files() ),
	'Fixture featured image setup must begin with exactly one owned upload'
);
fixture_contract_assert(
	[] === fixture_contract_temporary_upload_files(),
	'Fixture featured image overwrite left a temporary file behind'
);

fixture_contract_reset( [], true );
fixture_contract_run_bootstrap();
$fixture_featured_image_after_reset = fixture_contract_post( 'attachment', 'fixture-news-featured' );
$fixture_featured_file_after_reset  = $fixture_contract_attachment_files[ $fixture_featured_image_after_reset['ID'] ];
fixture_contract_assert(
	basename( $fixture_featured_file_before_reset ) === basename( $fixture_featured_file_after_reset ) &&
	$fixture_featured_file_before_reset === $fixture_featured_file_after_reset &&
	$fixture_featured_url_before_reset === $fixture_featured_image_after_reset['guid'],
	'Fixture featured media changed path or URL when its DB record was reset but its file was preserved'
);
fixture_contract_assert(
	1 === count( fixture_contract_owned_upload_files() ),
	'Fixture featured media file count grew when its DB record was reset but its file was preserved'
);
fixture_contract_assert(
	[] === fixture_contract_temporary_upload_files(),
	'Fixture featured media reset left a temporary file behind'
);
fixture_contract_assert(
	0 === $fixture_contract_upload_bits_calls,
	'Fixture featured media must not use WordPress unique-name upload behavior'
);

fixture_contract_reset(
	[
		[
			'post_content' => 'stale',
			'post_name'    => 'fixture-home',
			'post_status'  => 'draft',
			'post_title'   => 'stale',
			'post_type'    => 'page',
		],
		[
			'post_content' => 'stale',
			'post_excerpt' => 'stale',
			'post_name'    => 'fixture-news-1',
			'post_status'  => 'trash',
			'post_title'   => 'stale',
			'post_type'    => 'post',
		],
	]
);
fixture_contract_run_bootstrap();
$home  = fixture_contract_post( 'page', 'fixture-home' );
$news1 = fixture_contract_post( 'post', 'fixture-news-1' );
fixture_contract_assert( 'publish' === $home['post_status'], 'Page status was not repaired' );
fixture_contract_assert( 'Fixture Home' === $home['post_title'], 'Page title was not repaired' );
fixture_contract_assert( 'publish' === $news1['post_status'], 'Post status was not repaired' );
fixture_contract_assert( 'Fixture News 1' === $news1['post_title'], 'Post title was not repaired' );
fixture_contract_assert(
	[
		(int) get_term_by( 'slug', 'fixture-news', 'category' )->term_id,
		(int) get_term_by( 'slug', 'manufacturing', 'category' )->term_id,
		(int) get_term_by( 'slug', 'company-updates', 'category' )->term_id,
	] === $news1['post_category'],
	'Trashed fixture post category was not repaired'
);

fixture_contract_reset();
$fixture_contract_menus[1] = (object) [
	'name'    => 'CELINA Fixture Navigation',
	'term_id' => 1,
];
$fixture_contract_next_menu_id = 2;
$fixture_contract_menu_items[1] = (object) [
	'ID'      => 1,
	'menu_id' => 1,
	'title'   => 'Fixture News',
	'url'     => 'https://old.example.test/fixture-news-1/',
];
$fixture_contract_menu_items[2] = (object) [
	'ID'      => 2,
	'menu_id' => 1,
	'title'   => 'Keep this link',
	'url'     => 'https://old.example.test/keep-this-link/',
];
$fixture_contract_menu_items[3] = (object) [
	'ID'      => 3,
	'menu_id' => 1,
	'title'   => 'Fixture Home',
	'url'     => 'https://old.example.test/fixture-home/',
];
$fixture_contract_menu_items[4] = (object) [
	'ID'      => 4,
	'menu_id' => 1,
	'title'   => 'External fixture-looking link',
	'url'     => 'https://old.example.test/fixture-home/',
];
$fixture_contract_menu_items[5] = (object) [
	'ID'      => 5,
	'menu_id' => 1,
	'title'   => 'Fixture News',
	'url'     => 'https://old.example.test/unrelated-news/',
];
$fixture_contract_next_menu_item_id = 6;
fixture_contract_run_bootstrap();
$fixture_contract_menu_urls = array_map(
	static fn( object $item ): string => $item->url,
	array_values( $fixture_contract_menu_items )
);
fixture_contract_assert(
	1 === count( array_keys( $fixture_contract_menu_urls, 'https://example.test/fixture-news/' ) ),
	'Legacy Fixture News navigation was not consolidated'
);
fixture_contract_assert(
	0 === count( array_keys( $fixture_contract_menu_urls, 'https://old.example.test/fixture-news-1/' ) ),
	'Legacy Fixture News navigation item was not removed'
);
fixture_contract_assert(
	1 === count( array_keys( $fixture_contract_menu_urls, 'https://example.test/fixture-home/' ) ) &&
		'https://example.test/fixture-home/' === $fixture_contract_menu_items[3]->url,
	'Fixture Home navigation did not converge after a host change'
);
fixture_contract_assert(
	1 === count( array_keys( $fixture_contract_menu_urls, 'https://old.example.test/keep-this-link/' ) ),
	'Unrelated navigation item was changed'
);
fixture_contract_assert(
	1 === count( array_keys( $fixture_contract_menu_urls, 'https://old.example.test/fixture-home/' ) ),
	'A different-title item on an allowlisted path was changed'
);
fixture_contract_assert(
	1 === count( array_keys( $fixture_contract_menu_urls, 'https://old.example.test/unrelated-news/' ) ),
	'A matching-title item on an unrelated path was changed'
);

fixture_contract_reset();
$fixture_contract_categories[1] = (object) [
	'name'    => 'Fixture News',
	'parent'  => 0,
	'slug'    => 'fixture-news-legacy',
	'term_id' => 1,
];
$fixture_contract_next_category_id = 2;
fixture_contract_assert(
	1 === count( $fixture_contract_categories ),
	'Same-name category fixture setup is invalid'
);
fixture_contract_run_bootstrap();
fixture_contract_assert(
	5 === count( $fixture_contract_categories ),
	'Same-name category collision was not preserved'
);
fixture_contract_assert(
	'Fixture News' === get_term_by( 'slug', 'fixture-news', 'category' )->name,
	'Exact Fixture News category was not created for a slug collision'
);

fixture_contract_reset();
$fixture_contract_categories[1] = (object) [
	'name'    => 'Unrelated Category',
	'parent'  => 0,
	'slug'    => 'fixture-news',
	'term_id' => 1,
];
$fixture_contract_next_category_id = 2;
fixture_contract_expect_error(
	'fixture_contract_run_bootstrap',
	'Wrong-name exact-slug category collision did not fail loudly'
);
fixture_contract_assert(
	1 === count( $fixture_contract_categories ) &&
		'Unrelated Category' === $fixture_contract_categories[1]->name,
	'Wrong-name exact-slug category collision was overwritten'
);

fixture_contract_reset();
$fixture_contract_categories[1] = (object) [
	'name'    => 'Fixture News',
	'parent'  => 42,
	'slug'    => 'fixture-news',
	'term_id' => 1,
];
$fixture_contract_next_category_id = 2;
fixture_contract_expect_error(
	'fixture_contract_run_bootstrap',
	'Wrong-parent exact category collision did not fail loudly'
);
fixture_contract_assert(
	1 === count( $fixture_contract_categories ) &&
		42 === $fixture_contract_categories[1]->parent,
	'Wrong-parent exact category collision was overwritten'
);

fixture_contract_reset();
$fixture_contract_term_insert_error = true;
fixture_contract_expect_error(
	'fixture_contract_run_bootstrap',
	'Category insertion API failure did not fail loudly'
);

fixture_contract_reset();
$fixture_contract_upload_error = 'synthetic upload directory error';
fixture_contract_expect_error(
	'fixture_contract_run_bootstrap',
	'Upload directory API failure did not fail loudly',
	'Could not resolve the synthetic fixture image upload directory: synthetic upload directory error'
);

fixture_contract_reset();
$fixture_contract_upload_mkdir_error = true;
fixture_contract_expect_error(
	'fixture_contract_run_bootstrap',
	'Upload directory creation failure did not fail loudly',
	'Could not create the synthetic fixture image upload directory.'
);

fixture_contract_reset();
$fixture_contract_symlink_upload = wp_upload_dir( '2026-08-01 12:00:00', false, true );
fixture_contract_assert(
	wp_mkdir_p( $fixture_contract_symlink_upload['path'] ),
	'Could not prepare the synthetic fixture image symlink contract'
);
$fixture_contract_outside_sentinel = $fixture_contract_upload_root . '/outside-sentinel.txt';
fixture_contract_assert(
	8 === file_put_contents( $fixture_contract_outside_sentinel, 'sentinel' ),
	'Could not create the outside sentinel for the synthetic fixture image symlink contract'
);
$fixture_contract_symlink_target = $fixture_contract_symlink_upload['path'] . '/fixture-news-featured.png';
fixture_contract_assert(
	symlink( $fixture_contract_outside_sentinel, $fixture_contract_symlink_target ),
	'Could not create the synthetic fixture image target symlink'
);
fixture_contract_expect_error(
	'fixture_contract_run_bootstrap',
	'Fixture image target symlink was not rejected',
	'Synthetic fixture image target must not be a symbolic link.'
);
fixture_contract_assert(
	'sentinel' === file_get_contents( $fixture_contract_outside_sentinel ) &&
	is_link( $fixture_contract_symlink_target ),
	'Fixture image target symlink or its outside sentinel was changed'
);

fixture_contract_reset();
$fixture_contract_upload_path_override = $fixture_contract_upload_root . '/uploads/2026/08/../../../escaped';
fixture_contract_expect_error(
	'fixture_contract_run_bootstrap',
	'Fixture image upload path traversal was not rejected',
	'Could not validate the synthetic fixture image upload path.'
);

fixture_contract_reset();
$fixture_contract_write_failure_upload = wp_upload_dir( '2026-08-01 12:00:00', false, true );
fixture_contract_assert(
	wp_mkdir_p( $fixture_contract_write_failure_upload['path'] ),
	'Could not prepare the synthetic fixture image non-regular target contract'
);
fixture_contract_assert(
	mkdir( $fixture_contract_write_failure_upload['path'] . '/fixture-news-featured.png' ),
	'Could not reserve the synthetic fixture image target as a directory'
);
fixture_contract_expect_error(
	'fixture_contract_run_bootstrap',
	'Fixture image non-regular target was not rejected',
	'Synthetic fixture image target must be a regular file.'
);

fixture_contract_reset(
	[
		[
			'post_name'   => 'fixture-home',
			'post_status' => 'inherit',
			'post_title'  => 'Fixture asset',
			'post_type'   => 'attachment',
		],
	]
);
fixture_contract_expect_error(
	'fixture_contract_run_bootstrap',
	'An attachment slug collision did not fail loudly'
);

fixture_contract_reset();
$fixture_contract_insert_error = true;
fixture_contract_expect_error(
	'fixture_contract_run_bootstrap',
	'An insertion failure did not fail loudly'
);

fixture_contract_reset(
	[
		[
			'post_name'   => 'fixture-home',
			'post_status' => 'draft',
			'post_title'  => 'Fixture Home',
			'post_type'   => 'page',
		],
	]
);
$fixture_contract_update_error = true;
fixture_contract_expect_error(
	'fixture_contract_run_bootstrap',
	'An update failure did not fail loudly'
);

fwrite( STDOUT, "Fixture bootstrap contract passed\n" );
