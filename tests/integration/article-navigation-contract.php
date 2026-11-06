<?php

( static function (): void {
	$ids      = [];
	$scope    = [];
	$failures = [];
	$original_post = $GLOBALS['post'] ?? null;
	$restrict = static function ( WP_Query $query ) use ( &$scope ): void {
		if ( 1 === $query->get( 'posts_per_page' ) && $query->get( 'no_found_rows' ) ) {
			$query->set( 'post__in', $scope );
		}
	};

	try {
		foreach ( [
			[ 'middle-a', '2001-02-02', 'publish' ],
			[ 'oldest', '2001-02-01', 'publish' ],
			[ 'newest', '2001-02-03', 'publish' ],
			[ 'middle-b', '2001-02-02', 'publish' ],
			[ 'middle-c', '2001-02-02', 'publish' ],
			[ 'draft', '2001-02-02', 'draft' ],
			[ 'private', '2001-02-02', 'private' ],
			[ 'scheduled', '2099-02-02', 'future' ],
		] as [ $name, $date, $status ] ) {
			$id = wp_insert_post( [
				'post_title'    => 'Navigation contract ' . $name,
				'post_status'   => $status,
				'post_date'     => $date . ' 12:00:00',
				'post_date_gmt' => $date . ' 12:00:00',
			], true );
			if ( is_wp_error( $id ) ) {
				throw new RuntimeException( $id->get_error_message() );
			}
			$ids[ $name ] = $id;
		}
		$scope = array_values( $ids );
		add_action( 'pre_get_posts', $restrict );
		$order = [ 'oldest', 'middle-a', 'middle-b', 'middle-c', 'newest' ];
		foreach ( $order as $index => $name ) {
			$article = get_post( $ids[ $name ] );
			$GLOBALS['post'] = $article;
			setup_postdata( $article );
			ob_start();
			get_template_part( 'template-parts/content', 'single' );
			$html = ob_get_clean();
			foreach ( [ 'previous' => $index - 1, 'next' => $index + 1 ] as $direction => $neighbor ) {
				$expected = isset( $order[ $neighbor ] ) ? $ids[ $order[ $neighbor ] ] : null;
				$actual = celina_article_adjacent_post( $article, $direction );
				if ( $expected !== ( $actual instanceof WP_Post ? $actual->ID : null ) ) {
					$failures[] = "$name: wrong $direction article";
				}
				$pattern = '/<a class="celina-article-navigation__' . $direction . '" href="([^"]+)"/';
				$found = preg_match( $pattern, $html, $matches );
				if ( null === $expected ? 0 !== $found : ( 1 !== $found || esc_url( get_permalink( $expected ) ) !== $matches[1] ) ) {
					$failures[] = "$name: wrong rendered $direction button";
				}
			}
		}
		$scope = [ $ids['middle-b'] ];
		$article = get_post( $scope[0] );
		foreach ( [ 'previous', 'next', 'invalid' ] as $direction ) {
			if ( null !== celina_article_adjacent_post( $article, $direction ) ) {
				$failures[] = "Single article must have no $direction neighbor";
			}
		}
	} finally {
		remove_action( 'pre_get_posts', $restrict );
		foreach ( $ids as $id ) {
			wp_delete_post( $id, true );
		}
		$GLOBALS['post'] = $original_post;
		if ( $original_post instanceof WP_Post ) {
			setup_postdata( $original_post );
		}
	}
	if ( [] !== $failures ) {
		throw new RuntimeException( implode( "\n", $failures ) );
	}
	echo "Article navigation contract passed\n";
} )();
