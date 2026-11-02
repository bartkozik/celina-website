<?php
defined( 'ABSPATH' ) || exit;

/**
 * Check whether a location has a usable assigned navigation menu.
 *
 * @param string $location Registered menu location.
 * @return bool
 */
function celina_has_menu( string $location ): bool {
	$locations = get_nav_menu_locations();
	$menu_id   = $locations[ $location ] ?? 0;

	if ( ! $menu_id ) {
		return false;
	}

	$items = wp_get_nav_menu_items( (int) $menu_id );

	return is_array( $items ) && [] !== $items;
}

/**
 * Return the CELINA navigation used until an editor assigns a WordPress menu.
 *
 * @param string $location Registered menu location.
 * @return array<int, array{label: string, url: string, children?: array<int, array{label: string, url: string}>}>
 */
function celina_default_menu_items( string $location ): array {
	if ( 'celina-utility' === $location ) {
		return [
			[ 'label' => 'example.invalid', 'url' => 'https://example.invalid/reference' ],
			[ 'label' => 'Industries', 'url' => 'https://example.invalid/reference' ],
			[ 'label' => 'Military', 'url' => 'https://example.invalid/reference' ],
			[ 'label' => 'Event & Hospitality', 'url' => 'https://example.invalid/reference' ],
		];
	}

	if ( 'celina-primary' === $location ) {
		return [
			[ 'label' => 'CELINA Industries', 'url' => home_url( '/markets/' ) ],
			[ 'label' => 'CELINA Capabilities', 'url' => home_url( '/capabilities/' ) ],
			[
				'label'    => 'Why CELINA',
				'url'      => home_url( '/why-celina/' ),
				'children' => [
					[ 'label' => 'CELINA Community', 'url' => home_url( '/community/' ) ],
					[ 'label' => 'CELINA Corporate News', 'url' => home_url( '/news/' ) ],
					[ 'label' => 'Corporate Resources', 'url' => home_url( '/resources/' ) ],
					[ 'label' => 'About CELINA', 'url' => home_url( '/about/' ) ],
				],
			],
			[ 'label' => 'Careers', 'url' => home_url( '/careers/' ) ],
			[ 'label' => 'Contact', 'url' => home_url( '/contact/' ) ],
		];
	}

	if ( 'celina-footer' === $location ) {
		return [
			[ 'label' => 'CELINA Industries', 'url' => home_url( '/markets/' ) ],
			[ 'label' => 'CELINA Capabilities', 'url' => home_url( '/capabilities/' ) ],
			[ 'label' => 'CELINA Community', 'url' => home_url( '/community/' ) ],
			[ 'label' => 'CELINA Corporate News', 'url' => home_url( '/news/' ) ],
			[ 'label' => 'Corporate Resources', 'url' => home_url( '/resources/' ) ],
			[ 'label' => 'Why CELINA', 'url' => home_url( '/why-celina/' ) ],
			[ 'label' => 'Careers', 'url' => home_url( '/careers/' ) ],
			[ 'label' => 'About CELINA', 'url' => home_url( '/about/' ) ],
		];
	}

	return [];
}

/**
 * Render a safe fallback navigation list from the approved CELINA routes.
 *
 * @param array<int, array{label: string, url: string, children?: array<int, array{label: string, url: string}>}> $items Navigation items.
 * @param string                                                                                                      $class Menu list class.
 * @param int                                                                                                         $depth Maximum menu depth.
 */
function celina_render_default_menu_items( array $items, string $class, int $depth ): void {
	if ( [] === $items ) {
		return;
	}
	?>
	<ul class="<?php echo esc_attr( $class ); ?>">
		<?php foreach ( $items as $item ) : ?>
			<?php $children = $item['children'] ?? []; $nav_state = celina_sales_review_nav_state( $item['url'] ); ?>
			<li class="menu-item<?php echo [] !== $children ? ' menu-item-has-children' : ''; ?>">
				<a href="<?php echo esc_url( $item['url'] ); ?>"<?php if ( $nav_state ) : ?> aria-current="<?php echo esc_attr( $nav_state ); ?>"<?php endif; ?>><?php echo esc_html( $item['label'] ); ?></a>
				<?php if ( 1 < $depth && [] !== $children ) : ?>
					<?php celina_render_default_menu_items( $children, 'sub-menu', 1 ); ?>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
}

/**
 * Check whether a location has an editor-managed or approved fallback menu.
 *
 * @param string $location Registered menu location.
 * @return bool
 */
function celina_has_navigation( string $location ): bool {
	return celina_has_menu( $location ) || [] !== celina_default_menu_items( $location );
}

/**
 * Render an assigned menu or the approved CELINA fallback without a wrapper.
 *
 * @param string $location Registered menu location.
 * @param string $class CSS class for the menu list.
 * @param int    $depth Maximum menu depth.
 */
function celina_menu( string $location, string $class, int $depth = 1 ): void {
	if ( ! celina_has_menu( $location ) ) {
		$items = celina_sales_review_fallback_items( celina_default_menu_items( $location ), $location );
		celina_render_default_menu_items( $items, $class, $depth );
		return;
	}

	wp_nav_menu(
		[
			'container'      => false,
			'depth'          => $depth,
			'fallback_cb'    => false,
			'menu_class'     => $class,
			'theme_location' => $location,
		]
	);
}

/**
 * Render the header on the GeneratePress generate_header hook.
 */
function celina_render_header(): void {
	if ( celina_render_block_shell_part( 'header' ) ) { return; }
	$phone = sanitize_text_field( (string) get_theme_mod( 'celina_phone', '(202) 555-0100' ) );
	$email = sanitize_email( (string) get_theme_mod( 'celina_email', 'sales@example.invalid' ) );
	$tel   = preg_replace( '/[^0-9+]/', '', $phone );
	$logo  = get_custom_logo();
	$utility_menu = celina_has_navigation( 'celina-utility' );
	$primary_menu = celina_has_navigation( 'celina-primary' );
	?>
	<div class="celina-utility">
		<div class="celina-shell-inner">
			<div class="celina-utility__contacts">
				<a href="tel:<?php echo esc_attr( $tel ); ?>"><?php echo esc_html( $phone ); ?></a>
				<a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
			</div>
			<?php if ( $utility_menu ) : ?>
				<nav class="celina-utility__links" aria-label="<?php esc_attr_e( 'Utility navigation', 'celina' ); ?>">
					<?php celina_menu( 'celina-utility', 'celina-utility-menu' ); ?>
				</nav>
			<?php endif; ?>
		</div>
	</div>
	<header class="celina-header">
		<div class="celina-shell-inner">
			<?php if ( $logo ) : ?>
				<?php echo $logo; // WordPress generates custom-logo markup from the configured attachment. ?>
			<?php else : ?>
				<a class="celina-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></a>
			<?php endif; ?>
			<?php if ( $primary_menu ) : ?>
				<div class="celina-header-actions">
					<nav class="celina-primary" aria-label="<?php esc_attr_e( 'Primary navigation', 'celina' ); ?>">
						<?php celina_menu( 'celina-primary', 'celina-primary-menu', 2 ); ?>
					</nav>
					<a class="celina-header-cta" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php echo celina_sales_review_enabled() ? esc_html__( 'Discuss Project', 'celina' ) : esc_html__( 'Contact Us', 'celina' ); ?></a>
					<button
						class="celina-nav-toggle"
						type="button"
						aria-expanded="false"
						aria-controls="celina-mobile-nav"
						aria-label="<?php esc_attr_e( 'Open navigation', 'celina' ); ?>"
						data-open-label="<?php esc_attr_e( 'Open navigation', 'celina' ); ?>"
						data-close-label="<?php esc_attr_e( 'Close navigation', 'celina' ); ?>"
					>
						<span class="celina-nav-toggle__icon" aria-hidden="true">
							<span class="celina-nav-toggle__line"></span>
							<span class="celina-nav-toggle__line"></span>
							<span class="celina-nav-toggle__line"></span>
						</span>
					</button>
				</div>
			<?php endif; ?>
		</div>
		<?php if ( $primary_menu ) : ?>
			<nav id="celina-mobile-nav" class="celina-mobile-nav" aria-label="<?php esc_attr_e( 'Mobile navigation', 'celina' ); ?>" hidden>
				<?php celina_menu( 'celina-primary', 'celina-mobile-menu', 2 ); ?>
				<a class="celina-mobile-cta" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php echo celina_sales_review_enabled() ? esc_html__( 'Discuss Project', 'celina' ) : esc_html__( 'Contact Us', 'celina' ); ?></a>
			</nav>
		<?php endif; ?>
	</header>
	<?php
}

/**
 * Replace GeneratePress shell callbacks after the parent theme has registered them.
 */
function celina_replace_generatepress_shell(): void {
	remove_action( 'generate_header', 'generate_construct_header', 10 );
	remove_action( 'generate_after_header', 'generate_add_navigation_after_header', 5 );
	remove_action( 'generate_before_header', 'generate_add_navigation_before_header', 5 );
	remove_action( 'generate_footer', 'generate_construct_footer_widgets', 5 );
	remove_action( 'generate_footer', 'generate_construct_footer', 10 );
	add_action( 'generate_header', 'celina_render_header', 10 );
	add_action( 'generate_footer', 'celina_render_footer', 10 );
}

add_action( 'after_setup_theme', 'celina_replace_generatepress_shell', 100 );

/**
 * Render the footer inserted before the end of the page.
 */
function celina_render_footer(): void {
	if ( celina_render_block_shell_part( 'footer' ) ) { return; }
	$phone   = sanitize_text_field( (string) get_theme_mod( 'celina_phone', '(202) 555-0100' ) );
	$email   = sanitize_email( (string) get_theme_mod( 'celina_email', 'sales@example.invalid' ) );
	$address = sanitize_textarea_field( (string) get_theme_mod( 'celina_address', "123 Example Street\nCelina, OH 00000 USA" ) );
	$tagline = sanitize_textarea_field( (string) get_theme_mod( 'celina_footer_tagline', 'CELINA manufactures and distributes innovative fabric solutions for military, industrial, and commercial markets worldwide.' ) );
	$tel     = preg_replace( '/[^0-9+]/', '', $phone );
	$logo    = get_stylesheet_directory_uri() . '/assets/images/celina-logo-white.png';
	$footer_menu = celina_has_navigation( 'celina-footer' );
	$legal_menu  = celina_has_menu( 'celina-legal' );
	?>
	<footer class="celina-footer">
		<div class="celina-shell-inner">
			<div class="celina-footer-grid">
				<div class="celina-footer-brand">
					<a class="celina-footer-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr__( 'CELINA', 'celina' ); ?>">
						<img class="celina-footer-logo__image" src="<?php echo esc_url( $logo ); ?>" width="1440" height="379" alt="<?php echo esc_attr__( 'CELINA', 'celina' ); ?>" loading="lazy" decoding="async">
					</a>
					<p class="celina-footer-tagline"><?php echo celina_sales_review_enabled() ? wp_kses_post( celina_sales_review_statement_emphasis( esc_html( $tagline ) ) ) : esc_html( $tagline ); ?></p>
				</div>
				<?php if ( $footer_menu ) : ?>
					<div class="celina-footer-column">
						<h2 class="celina-footer-heading"><?php if ( celina_sales_review_enabled() ) { esc_html_e( 'Explore CELINA', 'celina' ); } else { esc_html_e( 'Our Sites', 'celina' ); } ?></h2>
						<nav aria-label="<?php esc_attr_e( 'Footer sites', 'celina' ); ?>">
							<?php celina_menu( 'celina-footer', 'celina-footer-menu' ); ?>
						</nav>
					</div>
				<?php endif; ?>
				<div class="celina-footer-column">
					<h2 class="celina-footer-heading"><?php esc_html_e( 'Contact', 'celina' ); ?></h2>
					<address class="celina-footer-contact">
						<?php echo nl2br( esc_html( $address ) ); ?>
						<br>
						<a href="tel:<?php echo esc_attr( $tel ); ?>"><?php echo esc_html( $phone ); ?></a>
						<a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
						<a class="celina-footer-contact-link" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact form', 'celina' ); ?></a>
					</address>
				</div>
			</div>
			<div class="celina-footer-meta">
				<p>© <?php echo esc_html( wp_date( 'Y' ) ); ?> CELINA. <?php esc_html_e( 'All rights reserved.', 'celina' ); ?></p>
				<?php if ( $legal_menu ) : ?>
					<nav aria-label="<?php esc_attr_e( 'Legal', 'celina' ); ?>">
						<?php celina_menu( 'celina-legal', 'celina-legal-menu' ); ?>
					</nav>
				<?php endif; ?>
			</div>
		</div>
	</footer>
	<?php
}
