<?php
defined( 'ABSPATH' ) || exit;

function celina_block_shell_editor_url( string $slug ): string {
	$state = get_option( celina_block_shell_option_name(), [] );
	return (string) get_edit_post_link( (int) ( $state['parts'][ $slug ] ?? 0 ), 'raw' );
}

function celina_block_shell_admin(): void {
	if ( ! current_user_can( 'edit_theme_options' ) ) { wp_die( esc_html__( 'You cannot edit theme options.', 'celina' ) ); }
	$result = null;
	if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		check_admin_referer( 'celina_block_shell' );
		$action = sanitize_key( wp_unslash( $_POST['shell_action'] ?? '' ) );
		$result = 'disable' === $action ? celina_block_shell_disable() : ( 'apply' === $action ? celina_block_shell_apply() : new WP_Error( 'invalid_action', 'Unknown shell action.' ) );
	}
	$plan = celina_block_shell_plan();
	$summaries = [
		'initialize' => 'Ready to create an editable header and footer from the current website settings.',
		'enable' => 'Ready to enable your saved header and footer without replacing any edits.',
		'noop' => 'Your saved block header and footer are active. No content changes are needed.',
		'conflict' => 'Existing or missing template parts need attention before the block shell can be enabled.',
	];
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Header & Footer', 'celina' ); ?></h1>
		<p><?php esc_html_e( 'Edit global header and footer content in Gutenberg. Saved changes apply across the website.', 'celina' ); ?></p>
		<p><?php esc_html_e( 'Enable once to copy the current logo, menus and contact settings. Future edits belong to the saved template parts. Returning to the legacy shell preserves those edits.', 'celina' ); ?></p>
		<?php if ( null !== $result ) : ?>
			<div class="notice <?php echo is_wp_error( $result ) ? 'notice-error' : 'notice-success'; ?>"><p><?php echo esc_html( is_wp_error( $result ) ? $result->get_error_message() : 'Header and footer setting saved.' ); ?></p></div>
		<?php endif; ?>
		<h2><?php esc_html_e( 'Current setup', 'celina' ); ?></h2>
		<p><?php echo esc_html( $summaries[ $plan['action'] ] ); ?></p>
		<?php foreach ( $plan['conflicts'] as $conflict ) : ?><p><?php echo esc_html( $conflict ); ?></p><?php endforeach; ?>
		<?php if ( ! empty( get_option( celina_block_shell_option_name(), [] )['parts'] ) ) : ?>
			<p><a class="button" href="<?php echo esc_url( celina_block_shell_editor_url( 'header' ) ); ?>">Edit Header</a> <a class="button" href="<?php echo esc_url( celina_block_shell_editor_url( 'footer' ) ); ?>">Edit Footer</a></p>
		<?php endif; ?>
		<form method="post">
			<?php wp_nonce_field( 'celina_block_shell' ); ?>
			<?php if ( celina_block_shell_enabled() ) : ?>
				<input type="hidden" name="shell_action" value="disable">
				<?php submit_button( 'Use legacy header and footer', 'secondary' ); ?>
			<?php elseif ( 'conflict' !== $plan['action'] ) : ?>
				<input type="hidden" name="shell_action" value="apply">
				<?php submit_button( 'Enable block header and footer' ); ?>
			<?php endif; ?>
		</form>
		<?php if ( 'initialize' === $plan['action'] ) : ?>
			<p><?php esc_html_e( 'The header will include your current logo, navigation, phone, email and contact button. The footer will include the white CELINA logo, company description, links, address and contact details.', 'celina' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

add_action( 'admin_menu', static function () { add_theme_page( 'Header & Footer', 'Header & Footer', 'edit_theme_options', 'celina-header-footer', 'celina_block_shell_admin' ); } );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'celina shell', static function ( array $args ) {
		$action = $args[0] ?? 'plan';
		if ( ! in_array( $action, [ 'plan', 'apply', 'disable' ], true ) ) { WP_CLI::error( 'Use wp celina shell plan|apply|disable. Mutations require --user with edit_theme_options.' ); }
		$result = 'plan' === $action ? celina_block_shell_plan() : ( 'apply' === $action ? celina_block_shell_apply() : celina_block_shell_disable() );
		if ( is_wp_error( $result ) ) { WP_CLI::error( $result->get_error_message() ); }
		WP_CLI::line( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	} );
}
