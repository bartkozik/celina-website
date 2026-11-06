<?php
class BlockRegistrationTest extends WP_UnitTestCase {
    public function test_registers_celina_category_and_sorted_registry(): void {
        $this->assertSame( 10, has_filter( 'block_categories_all', 'celina_blocks_categories' ) );
        $this->assertSame( 10, has_action( 'init', 'celina_blocks_register' ) );
        $categories = apply_filters( 'block_categories_all', [] );
        $this->assertSame( 'celina-redesigned', $categories[0]['slug'] );
        $this->assertSame( 'CELINA REDESIGNED', $categories[0]['title'] );
        $this->assertSame( 'celina', $categories[1]['slug'] );
        $this->assertSame( 'CELINA', $categories[1]['title'] );
        $slugs = celina_block_slugs();
        $sorted = $slugs;
        sort( $sorted );
        $this->assertSame( $sorted, $slugs );
    }

    public function test_registers_only_blocks_with_readable_metadata(): void {
        $plugin_directory = dirname( __DIR__, 2 );
        $registry_path = $plugin_directory . '/blocks.json';
        $build_directory = $plugin_directory . '/build';
        $blocks_directory = $build_directory . '/blocks';
        $fixture_slug = 'integration-fixture';
        $fixture_directory = $blocks_directory . '/' . $fixture_slug;
        $fixture_metadata_path = $fixture_directory . '/block.json';
        $style_files = [ 'style.css', 'editor.css', 'view.css' ];
        $style_handles = [ 'celina-integration-fixture-style', 'celina-integration-fixture-editor-style', 'celina-integration-fixture-view-style' ];
        $fixture_name = 'celina/integration-fixture';
        $missing_name = 'celina/missing-fixture';
        $directories = [ $build_directory, $blocks_directory, $fixture_directory ];
        $existing_directories = [];
        $block_registry = WP_Block_Type_Registry::get_instance();
        $original_registry = file_get_contents( $registry_path );

        $this->assertIsString( $original_registry );
        foreach ( $directories as $directory ) {
            $existing_directories[ $directory ] = is_dir( $directory );
        }

        try {
            $this->assertFalse( $block_registry->is_registered( $fixture_name ) );
            $this->assertFalse( $block_registry->is_registered( $missing_name ) );
            $this->assertTrue( wp_mkdir_p( $fixture_directory ) );

            $metadata = [
                'apiVersion' => 3,
                'name'       => $fixture_name,
                'version'    => '0.1.0',
                'title'      => 'CELINA Integration Fixture',
                'category'   => 'celina',
                'style'      => 'file:./style.css',
                'editorStyle' => 'file:./editor.css',
                'viewStyle'  => 'file:./view.css',
            ];
            foreach ( $style_files as $file ) {
                file_put_contents( $fixture_directory . '/' . $file, 'before' );
                touch( $fixture_directory . '/' . $file, 315532800 );
            }
            $this->assertNotFalse(
                file_put_contents( $fixture_metadata_path, wp_json_encode( $metadata ) . "\n" )
            );

            $fixture_registry = [ $fixture_slug, 'missing-fixture' ];
            $this->assertNotFalse(
                file_put_contents( $registry_path, wp_json_encode( $fixture_registry ) . "\n" )
            );
            $this->assertSame( $fixture_registry, celina_block_slugs() );

            celina_blocks_register();

            $this->assertTrue( $block_registry->is_registered( $fixture_name ) );
            $this->assertFalse( $block_registry->is_registered( $missing_name ) );

            foreach ( [ 'before', 'before', 'after!' ] as $contents ) {
                unregister_block_type( $fixture_name );
                foreach ( $style_handles as $handle ) {
                    wp_deregister_style( $handle );
                }
                foreach ( $style_files as $file ) {
                    file_put_contents( $fixture_directory . '/' . $file, $contents );
                    touch( $fixture_directory . '/' . $file, 315532800 );
                }
                clearstatcache();
                celina_blocks_register();
                foreach ( $style_handles as $handle ) {
                    $this->assertSame(
                        hash( 'sha256', $contents ),
                        wp_styles()->registered[ $handle ]->ver,
                        $handle . ' must version the served CSS by content, including in SCRIPT_DEBUG mode'
                    );
                }
            }
        } finally {
            if ( $block_registry->is_registered( $fixture_name ) ) {
                unregister_block_type( $fixture_name );
            }
            if ( is_file( $fixture_metadata_path ) ) {
                unlink( $fixture_metadata_path );
            }
            foreach ( $style_handles as $handle ) {
                wp_deregister_style( $handle );
            }
            foreach ( $style_files as $file ) {
                if ( is_file( $fixture_directory . '/' . $file ) ) {
                    unlink( $fixture_directory . '/' . $file );
                }
            }
            foreach ( array_reverse( $directories ) as $directory ) {
                if ( ! $existing_directories[ $directory ] && is_dir( $directory ) ) {
                    rmdir( $directory );
                }
            }
            file_put_contents( $registry_path, $original_registry );
        }
    }
}
