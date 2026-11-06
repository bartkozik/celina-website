<?php
if ( ! function_exists( 'celina_sales_review_content' ) ) {
    WP_CLI::error( 'Sales review switch and content transformation are not implemented.' );
}
$registry = WP_Block_Patterns_Registry::get_instance();
$cases = [ 'homepage' => 'page.homepage', 'industries' => 'page.markets', 'about' => 'page.about', 'contact' => 'page.contact', 'capabilities' => 'page.capabilities', 'why-celina' => 'page.why-celina' ];
$before = get_theme_mod( 'celina_sales_review', null );
$stored_before = get_posts( [ 'post_type' => 'page', 'numberposts' => -1, 'post_status' => 'any' ] );
$menus_before = get_theme_mod( 'nav_menu_locations' );
$check = static function ( $condition, $message ) {
    if ( ! $condition ) { throw new RuntimeException( $message ); }
};
try {
    foreach ( $cases as $pattern => $key ) {
        $content = $registry->get_registered( 'celina/page-' . $pattern )['content'];
        set_theme_mod( 'celina_sales_review', false );
        $check( $content === celina_sales_review_content( $content, $key ), 'Disabled review changes ' . $key );
        set_theme_mod( 'celina_sales_review', true );
        $review = celina_sales_review_content( $content, $key );
        $check( $content !== $review, 'Enabled review does not change ' . $key );
        $check( false !== strpos( $review, 'Discuss Your Project' ), 'Contact CTA missing on ' . $key );
        if ( 'contact' === $pattern ) {
            $original_form = celina_find_content_block( parse_blocks( $content ), 'celina/hubspot-form' );
            $review_form = celina_find_content_block( parse_blocks( $review ), 'celina/hubspot-form' );
            $check( $original_form === $review_form, 'HubSpot configuration was changed' );
            $check( false === strpos( $review, 'Contact the Right Team Directly' ), 'Competing contact destinations remain' );
        }
        if ( 'about' === $pattern ) {
            $check( strpos( $review, 'celina-leadership' ) < strpos( $review, '<details' ), 'Leadership must precede expanded history' );
            $check( false !== strpos( $review, 'jeff-grieshop-photo' ), 'Leadership media lost' );
        }
        set_theme_mod( 'celina_sales_review', false );
        $check( $content === celina_sales_review_content( $content, $key ), 'Rollback is not exact for ' . $key );
    }
    set_theme_mod( 'celina_sales_review', true );
    $check( 'untouched' === celina_sales_review_content( 'untouched', 'page.unmanaged' ), 'Unmanaged page changed' );
    $check( '<p>Custom page</p>' === celina_sales_review_content( '<p>Custom page</p>', 'page.homepage' ), 'Unrecognized layout changed' );
    $check( 'Industries' === celina_sales_review_menu_label( 'CELINA Industries', 'celina-primary' ), 'Variant navigation label missing' );
    set_theme_mod( 'celina_sales_review', false );
    $check( 'CELINA Industries' === celina_sales_review_menu_label( 'CELINA Industries', 'celina-primary' ), 'Original menu label not restored' );
    $check( $stored_before == get_posts( [ 'post_type' => 'page', 'numberposts' => -1, 'post_status' => 'any' ] ), 'Stored pages changed' );
    $check( $menus_before === get_theme_mod( 'nav_menu_locations' ), 'Saved navigation changed' );
    WP_CLI::success( 'Sales review: original, variant, preserved form, history and exact rollback verified.' );
} finally {
    if ( null === $before ) { remove_theme_mod( 'celina_sales_review' ); } else { set_theme_mod( 'celina_sales_review', $before ); }
}
