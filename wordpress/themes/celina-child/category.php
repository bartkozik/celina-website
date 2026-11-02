<?php
defined( 'ABSPATH' ) || exit;

celina_template_open( 'category' );
celina_render_post_archive( single_cat_title( '', false ), category_description() );
celina_template_close();
