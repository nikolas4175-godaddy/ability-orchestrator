<?php
/**
 * Ability catalog for admin UI.
 *
 * @package Baton
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formats registered abilities for JavaScript.
 */
final class Baton_Ability_Catalog {

	/**
	 * Get all abilities formatted for admin.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_all(): array {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return array();
		}

		$abilities = wp_get_abilities();
		$formatted = array();

		foreach ( $abilities as $ability ) {
			$formatted[] = self::format_ability( $ability );
		}

		usort(
			$formatted,
			static function ( array $a, array $b ): int {
				return strcmp( $a['label'], $b['label'] );
			}
		);

		return $formatted;
	}

	/**
	 * Format a single ability.
	 *
	 * @param WP_Ability $ability Ability instance.
	 * @return array<string, mixed>
	 */
	public static function format_ability( WP_Ability $ability ): array {
		$name = $ability->get_name();

		$input_schema  = $ability->get_input_schema();
		$output_schema = $ability->get_output_schema();

		$is_scalar = Baton_Input_Mapper::is_scalar_input_schema( $input_schema );

		return array(
			'slug'              => $name,
			'label'             => $ability->get_label(),
			'description'       => $ability->get_description(),
			'category'          => self::get_category_label( $ability ),
			'input_schema'      => $input_schema,
			'output_schema'     => $output_schema,
			'input_is_scalar'   => $is_scalar,
			'scalar_input_type' => $is_scalar ? Baton_Input_Mapper::get_scalar_type( $input_schema ) : '',
			'input_fields'      => Baton_Input_Mapper::schema_property_keys( $input_schema ),
			'output_fields'     => Baton_Input_Mapper::schema_property_keys( $output_schema ),
			'example_input'     => self::generate_example_input( $input_schema ),
		);
	}

	/**
	 * Get category label for an ability.
	 *
	 * @param WP_Ability $ability Ability instance.
	 * @return string
	 */
	private static function get_category_label( WP_Ability $ability ): string {
		$category_slug = $ability->get_category();

		if ( empty( $category_slug ) ) {
			return __( 'Other', 'baton' );
		}

		if ( function_exists( 'wp_get_ability_category' ) ) {
			$category = wp_get_ability_category( $category_slug );
			if ( $category ) {
				return $category->get_label();
			}
		}

		return $category_slug;
	}

	/**
	 * Generate example input from JSON schema.
	 *
	 * @param array<string, mixed> $schema Input schema.
	 * @return mixed
	 */
	public static function generate_example_input( array $schema ) {
		if ( Baton_Input_Mapper::is_scalar_input_schema( $schema ) ) {
			$type = Baton_Input_Mapper::get_scalar_type( $schema );

			if ( isset( $schema['default'] ) ) {
				return $schema['default'];
			}

			if ( isset( $schema['example'] ) ) {
				return $schema['example'];
			}

			if ( 'integer' === $type || 'number' === $type ) {
				$min = isset( $schema['minimum'] ) ? (int) $schema['minimum'] : 0;
				return max( 1, $min );
			}

			if ( 'boolean' === $type ) {
				return false;
			}

			return '';
		}

		if ( empty( $schema ) || ! isset( $schema['properties'] ) || ! is_array( $schema['properties'] ) ) {
			return array();
		}

		$input = array();

		foreach ( $schema['properties'] as $prop_name => $prop_schema ) {
			if ( ! is_array( $prop_schema ) ) {
				continue;
			}
			$input[ $prop_name ] = self::get_example_value( $prop_schema );
		}

		return $input;
	}

	/**
	 * Example value for a schema property.
	 *
	 * @param array<string, mixed> $prop_schema Property schema.
	 * @return mixed
	 */
	private static function get_example_value( array $prop_schema ) {
		if ( isset( $prop_schema['default'] ) ) {
			return $prop_schema['default'];
		}

		if ( isset( $prop_schema['example'] ) ) {
			return $prop_schema['example'];
		}

		$type = $prop_schema['type'] ?? 'string';

		switch ( $type ) {
			case 'string':
				return '';
			case 'number':
			case 'integer':
				return 0;
			case 'boolean':
				return false;
			case 'array':
				return array();
			case 'object':
				return new stdClass();
			default:
				return null;
		}
	}
}
