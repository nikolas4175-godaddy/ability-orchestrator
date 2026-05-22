<?php
/**
 * Test abilities for wp-env integration tests.
 *
 * @package Baton
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register echo ability used by runner tests.
 */
function baton_tests_register_abilities(): void {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}

	if ( function_exists( 'wp_register_ability_category' ) ) {
		wp_register_ability_category(
			'baton-test',
			array(
				'label' => 'Baton Test',
			)
		);
	}

	wp_register_ability(
		'baton-test/echo',
		array(
			'label'               => 'Echo',
			'description'         => 'Returns input for Baton tests.',
			'category'            => 'baton-test',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => true,
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'additionalProperties' => true,
			),
			'execute_callback'    => static function ( $input = null ) {
				return is_array( $input ) ? $input : array();
			},
			'permission_callback' => static function (): bool {
				return true;
			},
			'meta'                => array(
				'show_in_rest' => false,
			),
		)
	);
}

add_action( 'wp_abilities_api_init', 'baton_tests_register_abilities' );
