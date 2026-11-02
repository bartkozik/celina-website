<?php
defined( 'ABSPATH' ) || exit;

$heading = isset( $attributes['heading'] ) && is_string( $attributes['heading'] )
    ? sanitize_text_field( $attributes['heading'] )
    : 'Current openings';
$client_id = isset( $attributes['clientId'] ) && is_string( $attributes['clientId'] )
    ? $attributes['clientId']
    : '';
$fallback_text = isset( $attributes['fallbackText'] ) && is_string( $attributes['fallbackText'] )
    ? sanitize_text_field( $attributes['fallbackText'] )
    : 'Open positions are also available directly on Paycor.';
$jobs_link_text = isset( $attributes['jobsLinkText'] ) && is_string( $attributes['jobsLinkText'] )
    ? sanitize_text_field( $attributes['jobsLinkText'] )
    : 'View open positions on Paycor';

if ( '' === $jobs_link_text ) {
    $jobs_link_text = 'View open positions on Paycor';
}

$configured = 1 === preg_match( '/\A[a-f0-9]{32}\z/i', $client_id );
$load_embed = $configured && celina_paycor_claim_embed();
$script_url = $configured
    ? add_query_arg(
        'clientId',
        $client_id,
        'https://recruitingbypaycor.com/career/iframe.action'
    )
    : '';
$careers_url = $configured
    ? add_query_arg(
        'clientId',
        $client_id,
        'https://recruitingbypaycor.com/career/AggregateCareerHome.action'
    )
    : '';
?>
<section <?php echo get_block_wrapper_attributes( [ 'class' => 'celina-paycor-jobs' ] ); ?>>
    <?php if ( '' !== $heading ) : ?>
        <h2><?php echo esc_html( $heading ); ?></h2>
    <?php endif; ?>
    <?php if ( $configured ) : ?>
        <?php if ( $load_embed ) : ?>
            <div class="celina-paycor-jobs__embed">
                <script id="gnewtonjs" src="<?php echo esc_url( $script_url ); ?>"></script>
            </div>
        <?php endif; ?>
        <div class="celina-paycor-jobs__fallback">
            <?php if ( '' !== $fallback_text ) : ?>
                <p><?php echo esc_html( $fallback_text ); ?></p>
            <?php endif; ?>
            <a href="<?php echo esc_url( $careers_url ); ?>" target="_blank" rel="noopener noreferrer">
                <?php echo esc_html( $jobs_link_text ); ?>
            </a>
        </div>
    <?php else : ?>
        <div class="celina-paycor-jobs__status" role="status">
            <p><?php esc_html_e( 'Paycor jobs are not configured in this workbench.', 'celina-blocks' ); ?></p>
            <?php if ( '' !== $fallback_text ) : ?>
                <p><?php echo esc_html( $fallback_text ); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
