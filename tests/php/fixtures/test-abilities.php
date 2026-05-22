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
 * Test ability category registration args.
 *
 * @return array<string, string>
 */
function baton_tests_get_category_args(): array {
	return array(
		'label'       => 'Baton Test',
		'description' => 'Test ability category for Baton PHPUnit fixtures.',
	);
}

/**
 * Echo ability registration args.
 *
 * @return array<string, mixed>
 */
function baton_tests_get_echo_ability_args(): array {
	return array(
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
	);
}

/**
 * Register test ability category on the required hook.
 */
function baton_tests_register_ability_category(): void {
	if ( ! function_exists( 'wp_register_ability_category' ) ) {
		return;
	}

	wp_register_ability_category( 'baton-test', baton_tests_get_category_args() );
}

/**
 * Register echo ability used by runner tests on the required hook.
 */
function baton_tests_register_abilities(): void {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}

	wp_register_ability( 'baton-test/echo', baton_tests_get_echo_ability_args() );
}

/**
 * Register test fixtures when PHPUnit loads the plugin after core ability hooks already ran.
 *
 * @return string Empty on success, otherwise an error message for assertions.
 */
function baton_tests_ensure_abilities_registered(): string {
	if ( ! did_action( 'init' ) ) {
		return 'WordPress init has not fired.';
	}

	if ( function_exists( 'wp_get_ability' ) && wp_get_ability( 'baton-test/echo' ) ) {
		return '';
	}

	if ( ! class_exists( 'WP_Ability_Categories_Registry' ) || ! class_exists( 'WP_Abilities_Registry' ) ) {
		return 'Abilities API registry classes are not available.';
	}

	$categories = WP_Ability_Categories_Registry::get_instance();
	$registry   = WP_Abilities_Registry::get_instance();

	if ( ! $categories || ! $registry ) {
		return 'Abilities API registries could not be initialized.';
	}

	if ( ! $categories->is_registered( 'baton-test' ) ) {
		$category = $categories->register( 'baton-test', baton_tests_get_category_args() );

		if ( ! $category ) {
			return 'Failed to register ability category baton-test.';
		}
	}

	if ( ! $registry->is_registered( 'baton-test/echo' ) ) {
		$ability = $registry->register( 'baton-test/echo', baton_tests_get_echo_ability_args() );

		if ( ! $ability ) {
			return 'Failed to register ability baton-test/echo.';
		}
	}

	return '';
}

add_action( 'wp_abilities_api_categories_init', 'baton_tests_register_ability_category', 0 );
add_action( 'wp_abilities_api_init', 'baton_tests_register_abilities', 0 );
