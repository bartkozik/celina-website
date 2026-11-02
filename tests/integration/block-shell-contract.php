<?php
/** Run with wp eval-file on disposable WordPress data. Restores everything it owns. */
$expect = static function ( bool $ok, string $label ): void {
	if ( ! $ok ) { throw new RuntimeException( $label ); }
};
$expect( function_exists( 'celina_block_shell_plan' ), 'block shell lifecycle API is available' );
$expect( current_theme_supports( 'block-template-parts' ), 'native template part support' );
$real_theme_mods_option = 'theme_mods_' . get_option( 'stylesheet' );
$read_real_theme_mods = static function () use ( $real_theme_mods_option ) {
	global $wpdb;
	return $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $real_theme_mods_option ) );
};
$original_real_theme_mods = $read_real_theme_mods();
$contract_stylesheet = 'celina-shell-contract-' . wp_generate_uuid4();
$stylesheet_filter = static function () use ( $contract_stylesheet ) { return $contract_stylesheet; };
add_filter( 'stylesheet', $stylesheet_filter );
// Theme mods read the stylesheet option directly, bypassing the stylesheet filter.
add_filter( 'pre_option_stylesheet', $stylesheet_filter );
$key = celina_block_shell_option_name();
$expect( false === get_option( $key, false ), 'contract requires uninitialized disposable shell' );
$expect( [] === celina_block_shell_parts( 'header' ) && [] === celina_block_shell_parts( 'footer' ), 'contract requires no saved shell parts' );
$created = [];
$menus = [];
$terms = [];
$saved_query = $GLOBALS['wp_query'];
$user = get_current_user_id();
$admin = get_users( [ 'role' => 'administrator', 'number' => 1 ] )[0];
wp_set_current_user( $admin->ID );
try {
	$expect( $contract_stylesheet === get_option( 'stylesheet' ), 'theme-mod option reads use the isolated stylesheet namespace' );
	// Independent PHP workers prime negative option caches before racing the same unique key.
	$gate = tempnam( sys_get_temp_dir(), 'celina-shell-lock-' );
	unlink( $gate );
	$worker_code = 'require ' . var_export( ABSPATH . 'wp-load.php', true ) . ';'
		. 'add_filter("stylesheet", static function () { return ' . var_export( $contract_stylesheet, true ) . '; });'
		. 'add_filter("pre_option_stylesheet", static function () { return ' . var_export( $contract_stylesheet, true ) . '; });'
		. 'get_option(celina_block_shell_option_name()."_lock"); echo "ready\n"; fflush(STDOUT);'
		. '$until=microtime(true)+10; while (!file_exists(' . var_export( $gate, true ) . ')) { if(microtime(true)>$until){exit(2);} usleep(1000); }'
		. 'echo celina_block_shell_acquire_lock() ? "acquired" : "busy";';
	$workers = [];
	try {
		for ( $i = 0; $i < 4; ++$i ) {
			$process = proc_open( [ PHP_BINARY, '-r', $worker_code ], [ 0 => [ 'file', '/dev/null', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
			$expect( is_resource( $process ), 'lock worker starts' );
			$workers[] = [ $process, $pipes ];
			$expect( "ready\n" === fgets( $pipes[1] ), 'lock worker primes its cache' );
		}
		touch( $gate );
		$acquired = 0;
		foreach ( $workers as [ $process, $pipes ] ) {
			$output = stream_get_contents( $pipes[1] );
			$error = stream_get_contents( $pipes[2] );
			fclose( $pipes[1] ); fclose( $pipes[2] );
			$expect( 0 === proc_close( $process ) && '' === $error, 'lock worker exits cleanly: ' . $error );
			$acquired += 'acquired' === $output ? 1 : 0;
		}
		$expect( 1 === $acquired, 'exactly one concurrent initializer acquires the lock' );
	} finally {
		foreach ( $workers as [ $process, $pipes ] ) {
			if ( is_resource( $process ) ) { proc_terminate( $process ); proc_close( $process ); }
		}
		if ( file_exists( $gate ) ) { unlink( $gate ); }
		wp_cache_delete( $key . '_lock', 'options' ); wp_cache_delete( 'notoptions', 'options' );
		delete_option( $key . '_lock' );
	}
	set_theme_mod( 'celina_sales_review', true );
	set_theme_mod( 'celina_phone', '+1 (555) 010-1234' );
	set_theme_mod( 'celina_footer_tagline', 'Editable contract tagline' );
	set_theme_mod( 'nav_menu_locations', [] );
	$expect( $original_real_theme_mods === $read_real_theme_mods(), 'fixture settings never mutate the real raw theme-mod option' );
	$plan = celina_block_shell_plan();
	$expect( 'initialize' === $plan['action'], 'plan initializes missing parts' );
	$expect( false === get_option( $key, false ), 'plan does not write options' );
	$expect( [] === celina_block_shell_parts( 'header' ), 'plan does not create parts' );
	$expect( str_contains( $plan['parts']['header'], 'Discuss Project' ), 'active CTA source preserved' );
	$expect( str_contains( $plan['parts']['header'], '"name":"Header button"' ) && ! str_contains( $plan['parts']['header'], '"name":"Mobile menu button"' ), 'List View contains one named Header button source' );
	$expect( str_contains( $plan['parts']['header'], 'celina-block-sales-review' ), 'editor variant marker follows initialization source' );
	$expect( str_contains( $plan['parts']['header'], 'Industrial website' ), 'sales utility labels preserved' );
	$expect( ! str_contains( $plan['parts']['header'], 'CELINA website' ), 'sales utility filtering preserved' );
	$expect( str_contains( $plan['parts']['footer'], 'celina-logo-white.png' ), 'white footer logo preserved' );
	$expect( str_contains( $plan['parts']['footer'], 'Editable contract tagline' ), 'target settings preserved' );
	$expect( str_contains( $plan['parts']['header'], '+1 (555) 010-1234' ) && str_contains( $plan['parts']['footer'], 'tel:+15550101234' ), 'target phone preserved in both parts' );
	$expect( str_contains( do_blocks( $plan['parts']['footer'] ), wp_date( 'Y' ) ), 'copyright year renders dynamically' );
	$footer_grid = parse_blocks( $plan['parts']['footer'] )[0]['innerBlocks'][0]['innerBlocks'][0];
	$expect( 'celina-block-footer-contact' === $footer_grid['innerBlocks'][1]['attrs']['className'] && 'celina-block-footer-links' === $footer_grid['innerBlocks'][2]['attrs']['className'], 'initial footer DOM follows visible order so editor moves remain effective' );
	$empty = wp_create_nav_menu( 'Empty shell ' . wp_generate_uuid4() ); $menus[] = $empty;
	set_theme_mod( 'nav_menu_locations', [ 'celina-primary' => $empty ] );
	$expect( celina_sales_review_fallback_items( celina_default_menu_items( 'celina-primary' ), 'celina-primary' ) === celina_block_shell_menu_items( 'celina-primary' ), 'assigned empty menu copies the visible legacy fallback' );
	$expect( ! str_contains( celina_block_shell_content( 'header' ), 'wp:page-list' ), 'fallback is an explicit editable snapshot, never automatic pages' );
	$menu = wp_create_nav_menu( 'Nested shell ' . wp_generate_uuid4() ); $menus[] = $menu;
	$top = wp_update_nav_menu_item( $menu, 0, [ 'menu-item-title' => 'Parent', 'menu-item-url' => home_url( '/parent/' ), 'menu-item-status' => 'publish' ] );
	$child = wp_update_nav_menu_item( $menu, 0, [ 'menu-item-title' => 'Child', 'menu-item-url' => home_url( '/child/' ), 'menu-item-parent-id' => $top, 'menu-item-status' => 'publish' ] );
	wp_update_nav_menu_item( $menu, 0, [ 'menu-item-title' => 'Dormant grandchild', 'menu-item-url' => home_url( '/grandchild/' ), 'menu-item-parent-id' => $child, 'menu-item-status' => 'publish' ] );
	$destination = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Shell navigation ' . wp_generate_uuid4() ] );
	$created[] = $destination;
	wp_update_nav_menu_item( $menu, 0, [ 'menu-item-title' => 'Native page', 'menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $destination, 'menu-item-status' => 'publish' ] );
	$category = wp_insert_term( 'Shell category ' . wp_generate_uuid4(), 'category' );
	$terms[] = $category['term_id'];
	wp_update_nav_menu_item( $menu, 0, [ 'menu-item-title' => 'Native category', 'menu-item-type' => 'taxonomy', 'menu-item-object' => 'category', 'menu-item-object-id' => $category['term_id'], 'menu-item-status' => 'publish' ] );
	set_theme_mod( 'nav_menu_locations', [ 'celina-primary' => $menu, 'celina-legal' => $menu, 'celina-social' => $menu ] );
	$native_nav = celina_shell_navigation( 'celina-primary', 'celina-block-primary', 'Primary navigation' );
	$native_page = array_values( array_filter( $native_nav['innerBlocks'], static function ( $block ) { return 'Native page' === $block['attrs']['label']; } ) )[0]['attrs'];
	$native_category = array_values( array_filter( $native_nav['innerBlocks'], static function ( $block ) { return 'Native category' === $block['attrs']['label']; } ) )[0]['attrs'];
	$expect( 'post-type' === ( $native_page['kind'] ?? '' ) && 'page' === ( $native_page['type'] ?? '' ) && $destination === ( $native_page['id'] ?? 0 ), 'native page identity preserved for current navigation state' );
	$expect( 'taxonomy' === ( $native_category['kind'] ?? '' ) && 'category' === ( $native_category['type'] ?? '' ) && $category['term_id'] === ( $native_category['id'] ?? 0 ), 'native taxonomy identity preserved' );
	$GLOBALS['wp_query'] = new WP_Query( [ 'page_id' => $destination ] );
	$expect( str_contains( do_blocks( serialize_block( $native_nav ) ), 'aria-current="page"' ), 'native page current state rendered' );
	$custom_nav = celina_shell_block( 'navigation', [ 'className' => 'celina-block-primary', 'overlayMenu' => 'never' ], [ celina_shell_block( 'navigation-link', [ 'label' => 'Custom current', 'kind' => 'custom', 'url' => get_permalink( $destination ) ] ) ] );
	$expect( str_contains( do_blocks( serialize_block( $custom_nav ) ), 'aria-current="page"' ), 'custom shell link preserves current URL state' );
	$custom_nav['attrs']['className'] = 'ordinary-page-navigation';
	$expect( ! str_contains( do_blocks( serialize_block( $custom_nav ) ), 'aria-current=' ), 'custom state filter leaves ordinary page navigation untouched' );
	$GLOBALS['wp_query'] = new WP_Query();
	$GLOBALS['wp_query']->is_category = true;
	$custom_nav['attrs']['className'] = 'celina-block-footer-nav';
	$custom_nav['innerBlocks'][0]['attrs']['url'] = home_url( '/news/' );
	$expect( str_contains( do_blocks( serialize_block( $custom_nav ) ), 'aria-current="location"' ), 'news section state retained on category archives' );
	$GLOBALS['wp_query'] = $saved_query;

	$plan = celina_block_shell_plan();
	$expect( ! str_contains( $plan['parts']['header'], 'Dormant grandchild' ), 'primary snapshot retains two visible levels' );
	$expect( str_contains( $plan['parts']['header'], 'wp:navigation-submenu' ) && str_contains( $plan['parts']['header'], 'Child' ), 'nested editable menus retained' );
	$expect( ! str_contains( $plan['parts']['footer'], 'celina-block-social' ) && str_contains( $plan['parts']['footer'], 'celina-block-legal' ), 'visible legal links retained without exposing dormant social menu' );
	$expect( str_contains( $plan['parts']['header'], 'wp:details' ) && ! str_contains( $plan['parts']['header'], 'celina-block-mobile-cta' ), 'editable disclosure contains navigation without a duplicate editable CTA' );
	$expect( ! str_contains( serialize_block( celina_shell_navigation( 'celina-legal', 'celina-block-legal', 'Legal' ) ), 'Child' ), 'non-primary menu retains one visible level' );
	add_option( $key . '_lock', 'other request', '', false );
	$expect( is_wp_error( celina_block_shell_apply() ), 'concurrent initialization blocked' );
	delete_option( $key . '_lock' );
	$foreign = wp_insert_post( [ 'post_type' => 'wp_template_part', 'post_status' => 'publish', 'post_name' => 'header', 'post_content' => 'Foreign content', 'tax_input' => [ 'wp_theme' => [ get_stylesheet() ], 'wp_template_part_area' => [ 'header' ] ] ] );
	$created[] = $foreign;
	$expect( 'conflict' === celina_block_shell_plan()['action'], 'existing part conflicts' );
	$expect( is_wp_error( celina_block_shell_apply() ) && 'Foreign content' === get_post( $foreign )->post_content, 'foreign part protected' );
	wp_trash_post( $foreign );
	$expect( 'conflict' === celina_block_shell_plan()['action'], 'trashed editorial part remains a conflict' );
	wp_delete_post( $foreign, true );
	$reject_footer = static function ( $maybe_empty, $postarr ) {
		if ( 'wp_template_part' !== ( $postarr['post_type'] ?? '' ) || 'footer' !== ( $postarr['post_name'] ?? '' ) ) { return $maybe_empty; }
		$header = celina_block_shell_parts( 'header' )[0];
		wp_update_post( [ 'ID' => $header->ID, 'post_content' => '<!-- wp:paragraph --><p>Concurrent editor save</p><!-- /wp:paragraph -->' ] );
		wp_save_post_revision( $header->ID );
		return true;
	};
	add_filter( 'wp_insert_post_empty_content', $reject_footer, 10, 2 );
	$failure = celina_block_shell_apply();
	$expect( is_wp_error( $failure ) && str_contains( $failure->get_error_message(), 'retained for review' ), 'partial insert failure reports preserved records' );
	remove_filter( 'wp_insert_post_empty_content', $reject_footer, 10 );
	$partial = celina_block_shell_parts( 'header' );
	$expect( ! celina_block_shell_enabled() && 1 === count( $partial ), 'partial records retained without activation' );
	$created[] = $partial[0]->ID;
	$expect( str_contains( $partial[0]->post_content, 'Concurrent editor save' ), 'concurrent editor save survives partial failure' );
	$revisions = wp_get_post_revisions( $partial[0]->ID );
	$expect( (bool) array_filter( $revisions, static function ( $revision ) { return str_contains( $revision->post_content, 'Concurrent editor save' ); } ), 'concurrent editor revision survives partial failure' );
	$expect( [ $partial[0]->ID ] === $failure->get_error_data()['retained_part_ids'], 'partial failure identifies retained records' );
	$expect( 'conflict' === celina_block_shell_plan()['action'], 'partial initialization requires deliberate review' );
	wp_delete_post( $partial[0]->ID, true );
	$result = celina_block_shell_apply();
	$expect( ! is_wp_error( $result ), 'explicit apply succeeds' );
	$state = get_option( $key ); $created = array_merge( $created, array_values( $state['parts'] ) );
	$expect( celina_block_shell_enabled(), 'explicit apply enables' );
	$header = $state['parts']['header'];
	$expect( get_edit_post_link( $header, 'raw' ) === celina_block_shell_editor_url( 'header' ) && str_contains( celina_block_shell_editor_url( 'header' ), 'canvas=edit' ), 'direct editor link uses the native editable route' );
	wp_update_post( [ 'ID' => $header, 'post_content' => '<!-- wp:paragraph --><p>Editor-owned header</p><!-- /wp:paragraph -->' ] );
	$expect( 'noop' === celina_block_shell_apply()['action'], 'second apply converges' );
	ob_start(); celina_render_header(); $rendered = ob_get_clean();
	$expect( str_contains( $rendered, 'Editor-owned header' ), 'saved edits rendered from existing hook' );
	wp_update_post( [ 'ID' => $header, 'post_content' => '' ] );
	ob_start(); celina_render_header(); $rendered = ob_get_clean();
	$expect( '' === $rendered, 'intentional empty header stays empty' );
	wp_update_post( [ 'ID' => $header, 'post_name' => 'renamed-contract-header' ] );
	$expect( false === celina_render_block_shell_part( 'header' ), 'renamed saved part cannot render unrelated file defaults' );
	wp_update_post( [ 'ID' => $header, 'post_name' => 'header' ] );
	celina_block_shell_disable();
	$expect( ! celina_block_shell_enabled() && null !== get_post( $header ), 'disable preserves parts' );
	$expect( 'enable' === celina_block_shell_apply()['action'] && '' === get_post( $header )->post_content, 'reenable preserves empty saved content' );
	wp_set_current_user( 0 );
	$expect( is_wp_error( celina_block_shell_apply() ) && is_wp_error( celina_block_shell_disable() ), 'mutation requires theme capability' );
} finally {
	wp_set_current_user( $admin->ID );
	foreach ( $created as $id ) { wp_delete_post( $id, true ); }
	foreach ( $menus as $id ) { wp_delete_nav_menu( $id ); }
	foreach ( $terms as $id ) { wp_delete_term( $id, 'category' ); }
	$GLOBALS['wp_query'] = $saved_query;
	delete_option( $key ); delete_option( $key . '_lock' );
	wp_set_current_user( $user );
	delete_option( 'theme_mods_' . $contract_stylesheet );
	$contract_theme_term = get_term_by( 'name', $contract_stylesheet, 'wp_theme' );
	if ( $contract_theme_term ) { wp_delete_term( $contract_theme_term->term_id, 'wp_theme' ); }
	remove_filter( 'stylesheet', $stylesheet_filter );
	remove_filter( 'pre_option_stylesheet', $stylesheet_filter );
	$expect( $original_real_theme_mods === $read_real_theme_mods(), 'real raw theme-mod option unchanged after cleanup' );
}
echo "block shell contract passed\n";
