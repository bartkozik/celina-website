<?php
defined( 'ABSPATH' ) || exit;

$config        = celina_news_grid_attributes( $attributes );
$featured_post = null;
$query         = null;

if ( 'featured' === $config['layout'] || 'news-index' === $config['layout'] ) {
    $featured_post = celina_news_grid_featured_post( $config );
}

if ( 'featured' !== $config['layout'] ) {
    $query = celina_news_grid_query(
        $config,
        $featured_post instanceof WP_Post && 'news-index' === $config['layout']
            ? $featured_post->ID
            : 0
    );
}

if (
    ! $featured_post instanceof WP_Post &&
    ( ! $query instanceof WP_Query || [] === $query->posts ) &&
    'news-index' !== $config['layout']
) {
    return '';
}

$current_category_id = is_category() ? (int) get_queried_object_id() : 0;
$has_visible_posts   =
    ( $featured_post instanceof WP_Post && 1 === $config['page'] ) ||
    ( $query instanceof WP_Query && [] !== $query->posts );
?>
<section <?php echo get_block_wrapper_attributes( [ 'class' => 'celina-news-grid celina-news-grid--' . $config['layout'] ] ); ?>>
    <?php if ( '' !== $config['heading'] ) : ?>
        <h2><?php echo esc_html( $config['heading'] ); ?></h2>
    <?php endif; ?>
    <?php if ( 'news-index' === $config['layout'] ) : ?>
        <?php echo celina_news_grid_render_navigation( $config, $current_category_id ); ?>
    <?php endif; ?>
    <?php if ( $featured_post instanceof WP_Post && 1 === $config['page'] ) : ?>
        <div class="celina-news-grid__featured">
            <?php echo celina_news_grid_render_card( $featured_post, 'featured', $config ); ?>
        </div>
    <?php endif; ?>
    <?php if ( $query instanceof WP_Query && [] !== $query->posts ) : ?>
        <div class="celina-news-grid__items">
            <?php foreach ( $query->posts as $post ) : ?>
                <?php echo celina_news_grid_render_card( $post, 'news-index' === $config['layout'] ? 'news-index' : 'grid', $config ); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ( 'news-index' === $config['layout'] && ! $has_visible_posts ) : ?>
        <p class="celina-news-grid__empty" role="status"><?php esc_html_e( 'No news articles are available at this time.', 'celina-blocks' ); ?></p>
    <?php endif; ?>
    <?php if ( $query instanceof WP_Query ) : ?>
        <?php echo celina_news_grid_render_pagination( $query, $config['page'] ); ?>
    <?php endif; ?>
</section>
