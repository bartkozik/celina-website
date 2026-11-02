<?php
/** Read-only rendering contract: no saved content or settings are changed. */
$expect = static function ( bool $ok, string $label ): void {
	if ( ! $ok ) { throw new RuntimeException( $label ); }
};
$content = celina_block_shell_content( 'header' );
$expect( 1 === substr_count( $content, '<!-- wp:buttons ' ), 'header stores exactly one editable Buttons group' );
$expect( ! str_contains( $content, 'celina-block-mobile-cta' ), 'mobile presentation is not stored in editor content' );
$expect( celina_block_shell_validate( $content ), 'composition roundtrips as native registered blocks' );

$button = celina_shell_block( 'button', [ 'anchor' => 'cta-link', 'className' => 'is-style-outline' ], [], '<div id="cta-link" class="wp-block-button is-style-outline"><a id="cta-anchor" class="wp-block-button__link wp-element-button" href="https://example.org/revised/" target="_blank" rel="noopener"><strong>Revised project</strong></a></div>' );
$cta = celina_shell_block( 'buttons', [ 'anchor' => 'cta-group', 'className' => 'celina-block-header-cta custom-cta', 'style' => [ 'spacing' => [ 'blockGap' => '13px' ] ] ], [ $button ], '<div id="cta-group" class="wp-block-buttons celina-block-header-cta custom-cta">', '</div>' );
$panel = celina_shell_group( 'celina-block-primary-panel', [ celina_shell_text( 'Menu content' ) ] );
$header = celina_shell_group( 'celina-block-shell celina-block-header', [ $panel, $cta ], 'header' );
$saved = serialize_block( $header );
$html = do_blocks( $saved );
$expect( 2 === substr_count( $html, '<strong>Revised project</strong>' ) && 2 === substr_count( $html, 'https://example.org/revised/' ), 'label markup and URL render in both locations' );
$expect( 2 === substr_count( $html, 'target="_blank"' ) && 2 === substr_count( $html, 'rel="noopener"' ), 'link behavior preserved in both locations' );
foreach ( [ 'cta-link', 'cta-anchor', 'cta-group' ] as $id ) {
	$expect( 1 === substr_count( $html, 'id="' . $id . '"' ), 'mobile copy does not duplicate HTML ID ' . $id );
}
foreach ( [ 'custom-cta', 'is-style-outline' ] as $class ) {
	$tags = new WP_HTML_Tag_Processor( $html );
	$count = 0;
	while ( $tags->next_tag( [ 'class_name' => $class ] ) ) { ++$count; }
	$expect( 2 === $count, 'editor custom class preserved: ' . $class );
}
$derived = apply_filters( 'render_block_data', $header, $header, null );
$expect( $cta['attrs']['style'] === $derived['innerBlocks'][0]['innerBlocks'][1]['attrs']['style'], 'editor spacing preserved' );
$expect( $saved === serialize_block( $header ), 'rendering never mutates the saved block tree' );
$processor = new WP_HTML_Tag_Processor( $html );
$expect( $processor->next_tag( [ 'class_name' => 'celina-block-mobile-cta' ] ) && ! $processor->has_class( 'celina-block-header-cta' ), 'mobile presentation uses only the mobile layout class' );
$deleted = celina_shell_group( 'celina-block-shell celina-block-header', [ $panel ], 'header' );
$expect( ! str_contains( do_blocks( serialize_block( $deleted ) ), 'wp-block-buttons' ), 'deleting the source removes both presentations' );

$legacy = celina_shell_block( 'buttons', [ 'className' => 'celina-block-mobile-cta' ], [ celina_shell_block( 'button', [], [], '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://example.org/distinct/">Distinct mobile content</a></div>' ) ], '<div class="wp-block-buttons celina-block-mobile-cta">', '</div>' );
$old = celina_shell_group( 'celina-block-shell celina-block-header', [ celina_shell_group( 'celina-block-primary-panel', [ $legacy ] ), $cta ], 'header' );
$expect( $old === apply_filters( 'render_block_data', $old, $old, null ), 'legacy saved parts are left byte-for-byte unchanged' );
$old_html = do_blocks( serialize_block( $old ) );
$expect( 1 === substr_count( $old_html, 'Distinct mobile content' ) && 1 === substr_count( $old_html, 'Revised project' ), 'distinct legacy mobile content remains visible without extra copies' );
foreach ( [ [ $panel, $cta, $cta ], [ $panel, $panel, $cta ], [ $cta ] ] as $children ) {
	$ambiguous = celina_shell_group( 'celina-block-header', $children );
	$expect( $ambiguous === apply_filters( 'render_block_data', $ambiguous, $ambiguous, null ), 'ambiguous or missing layout markers are not guessed' );
}
$footer = parse_blocks( celina_block_shell_content( 'footer' ) )[0];
$expect( $footer === apply_filters( 'render_block_data', $footer, $footer, null ), 'footer is not transformed' );
$ordinary = celina_shell_group( 'ordinary-content', [ $panel, $cta ] );
$expect( $ordinary === apply_filters( 'render_block_data', $ordinary, $ordinary, null ), 'ordinary page groups are not transformed' );
echo "block shell single CTA contract passed\n";
