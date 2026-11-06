<?php
defined( 'ABSPATH' ) || exit;

/** @param WP_Customize_Manager $customizer */
function celina_customize_register( WP_Customize_Manager $customizer ): void {
	$customizer->add_panel(
		'celina_site_settings',
		[
			'title' => __( 'CELINA Site Settings', 'celina' ),
		]
	);

	$customizer->add_section(
		'celina_contact',
		[
			'panel' => 'celina_site_settings',
			'title' => __( 'Contact', 'celina' ),
		]
	);

	$customizer->add_setting(
		'celina_phone',
		[
			'default'           => '(202) 555-0100',
			'sanitize_callback' => 'sanitize_text_field',
		]
	);
	$customizer->add_control(
		'celina_phone',
		[
			'label'   => __( 'Phone', 'celina' ),
			'section' => 'celina_contact',
			'type'    => 'text',
		]
	);

	$customizer->add_setting(
		'celina_email',
		[
			'default'           => 'sales@example.invalid',
			'sanitize_callback' => 'sanitize_email',
		]
	);
	$customizer->add_control(
		'celina_email',
		[
			'label'   => __( 'Email', 'celina' ),
			'section' => 'celina_contact',
			'type'    => 'email',
		]
	);

	$customizer->add_setting(
		'celina_address',
		[
			'default'           => "123 Example Street\nCelina, OH 00000 USA",
			'sanitize_callback' => 'sanitize_textarea_field',
		]
	);
	$customizer->add_control(
		'celina_address',
		[
			'label'   => __( 'Address', 'celina' ),
			'section' => 'celina_contact',
			'type'    => 'textarea',
		]
	);

	$customizer->add_setting(
		'celina_footer_tagline',
		[
			'default'           => 'CELINA manufactures and distributes innovative fabric solutions for military, industrial, and commercial markets worldwide.',
			'sanitize_callback' => 'sanitize_textarea_field',
		]
	);
	$customizer->add_control(
		'celina_footer_tagline',
		[
			'label'   => __( 'Footer tagline', 'celina' ),
			'section' => 'celina_contact',
			'type'    => 'textarea',
		]
	);
}

add_action( 'customize_register', 'celina_customize_register' );
