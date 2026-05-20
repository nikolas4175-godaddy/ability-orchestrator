<?php
/**
 * Resolves field-level mappings from workflow / previous-step data.
 *
 * @package Baton
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dot-path extraction and input mapping for workflow steps.
 */
final class Baton_Input_Mapper {

	/**
	 * JSON Schema types treated as scalar ability input (whole value, not an object).
	 *
	 * @var array<int, string>
	 */
	private static $scalar_types = array( 'integer', 'number', 'string', 'boolean' );

	/**
	 * Whether the ability input schema is a single scalar value.
	 *
	 * @param array<string, mixed> $schema Input schema.
	 * @return bool
	 */
	public static function is_scalar_input_schema( array $schema ): bool {
		if ( empty( $schema ) ) {
			return false;
		}

		if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) && ! empty( $schema['properties'] ) ) {
			return false;
		}

		$type = $schema['type'] ?? null;

		if ( is_array( $type ) ) {
			foreach ( $type as $t ) {
				if ( in_array( $t, self::$scalar_types, true ) ) {
					return true;
				}
			}
			return false;
		}

		return is_string( $type ) && in_array( $type, self::$scalar_types, true );
	}

	/**
	 * Primary scalar type from an input schema.
	 *
	 * @param array<string, mixed> $schema Input schema.
	 * @return string
	 */
	public static function get_scalar_type( array $schema ): string {
		$type = $schema['type'] ?? 'string';

		if ( is_array( $type ) ) {
			foreach ( self::$scalar_types as $scalar ) {
				if ( in_array( $scalar, $type, true ) ) {
					return $scalar;
				}
			}
			return 'string';
		}

		return in_array( $type, self::$scalar_types, true ) ? $type : 'string';
	}

	/**
	 * Resolve ability input from mappings and optional static override.
	 *
	 * @param array<string, mixed>              $input_schema    Ability input schema.
	 * @param mixed                             $static_input    Static JSON value (object array or scalar).
	 * @param array<int, array<string, string>> $mappings        Field mappings.
	 * @param mixed                             $previous_output Previous step output.
	 * @param array<string, mixed>              $initial_input   Workflow-level input.
	 * @return array{input: mixed, warnings: array<int, string>}
	 */
	public static function resolve_input(
		array $input_schema,
		$static_input,
		array $mappings,
		$previous_output,
		array $initial_input = array()
	): array {
		if ( self::is_scalar_input_schema( $input_schema ) ) {
			return self::resolve_scalar_input( $static_input, $mappings, $previous_output, $initial_input );
		}

		$static_array = is_array( $static_input ) ? $static_input : array();

		return self::apply_mappings( $static_array, $mappings, $previous_output, $initial_input );
	}

	/**
	 * Resolve input when the ability expects a single scalar.
	 *
	 * @param mixed                             $static_input    Static value or empty array.
	 * @param array<int, array<string, string>> $mappings        Path mappings (target ignored).
	 * @param mixed                             $previous_output Previous step output.
	 * @param array<string, mixed>              $initial_input   Workflow-level input.
	 * @return array{input: mixed, warnings: array<int, string>}
	 */
	public static function resolve_scalar_input(
		$static_input,
		array $mappings,
		$previous_output,
		array $initial_input = array()
	): array {
		$warnings = array();
		$input    = null;
		$has_path = false;

		foreach ( $mappings as $mapping ) {
			if ( ! is_array( $mapping ) ) {
				continue;
			}

			$path   = $mapping['path'] ?? '';
			$source = $mapping['source'] ?? 'previous';

			if ( '' === $path ) {
				continue;
			}

			$has_path     = true;
			$source_data  = 'initial' === $source ? $initial_input : $previous_output;
			$resolved_val = null;

			if ( null === $source_data ) {
				$warnings[] = sprintf(
					/* translators: 1: path, 2: source label */
					__( 'Scalar mapping for path "%1$s" skipped: no %2$s data available.', 'baton' ),
					$path,
					'initial' === $source ? __( 'workflow input', 'baton' ) : __( 'previous step', 'baton' )
				);
				continue;
			}

			$resolved_val = self::get_value_at_path( $source_data, $path );

			if ( null === $resolved_val ) {
				$warnings[] = sprintf(
					/* translators: %s: dot path */
					__( 'Scalar mapping skipped: path "%s" not found in source.', 'baton' ),
					$path
				);
				continue;
			}

			$input = self::coerce_value( $resolved_val );
			break;
		}

		if ( self::has_static_scalar_value( $static_input ) ) {
			$input = self::coerce_value( $static_input );
		} elseif ( $has_path && null === $input && empty( $warnings ) ) {
			$warnings[] = __( 'Scalar mapping could not resolve a value from the source path.', 'baton' );
		}

		return array(
			'input'    => $input,
			'warnings' => $warnings,
		);
	}

	/**
	 * Whether static input provides an explicit scalar override.
	 *
	 * @param mixed $static_input Static input from definition.
	 * @return bool
	 */
	public static function has_static_scalar_value( $static_input ): bool {
		if ( is_array( $static_input ) ) {
			return false;
		}

		return null !== $static_input && '' !== $static_input;
	}

	/**
	 * Get a value from nested data using a dot path (e.g. "id", "user.email", "items.0.id").
	 *
	 * @param mixed  $data Source data.
	 * @param string $path Dot-separated path.
	 * @return mixed|null Value or null when path cannot be resolved.
	 */
	public static function get_value_at_path( $data, string $path ) {
		$path = trim( $path );
		if ( '' === $path ) {
			return $data;
		}

		$segments = explode( '.', $path );
		$current  = $data;

		foreach ( $segments as $segment ) {
			if ( '' === $segment ) {
				continue;
			}

			if ( is_array( $current ) ) {
				if ( ! array_key_exists( $segment, $current ) ) {
					return null;
				}
				$current = $current[ $segment ];
				continue;
			}

			if ( is_object( $current ) ) {
				if ( ! isset( $current->$segment ) ) {
					return null;
				}
				$current = $current->$segment;
				continue;
			}

			return null;
		}

		return $current;
	}

	/**
	 * Apply field mappings onto a base input array.
	 *
	 * @param array<string, mixed> $input          Base input (typically static JSON).
	 * @param array<int, array<string, string>> $mappings Mapping definitions.
	 * @param mixed                $previous_output Previous step output.
	 * @param array<string, mixed> $initial_input   Workflow-level input.
	 * @return array{input: array<string, mixed>, warnings: array<int, string>}
	 */
	public static function apply_mappings(
		array $input,
		array $mappings,
		$previous_output,
		array $initial_input = array()
	): array {
		$warnings = array();

		foreach ( $mappings as $mapping ) {
			if ( ! is_array( $mapping ) ) {
				continue;
			}

			$target = isset( $mapping['target'] ) ? self::sanitize_field_name( (string) $mapping['target'] ) : '';
			$path   = isset( $mapping['path'] ) ? self::sanitize_path( (string) $mapping['path'] ) : '';
			$source = isset( $mapping['source'] ) ? (string) $mapping['source'] : 'previous';

			if ( '' === $path ) {
				continue;
			}

			if ( '' === $target ) {
				continue;
			}

			$source_data = 'initial' === $source ? $initial_input : $previous_output;

			if ( null === $source_data ) {
				$warnings[] = sprintf(
					/* translators: 1: target field, 2: source label */
					__( 'Mapping for "%1$s" skipped: no %2$s data available.', 'baton' ),
					$target,
					'initial' === $source ? __( 'workflow input', 'baton' ) : __( 'previous step', 'baton' )
				);
				continue;
			}

			$value = self::get_value_at_path( $source_data, $path );

			if ( null === $value ) {
				$warnings[] = sprintf(
					/* translators: 1: dot path, 2: target field */
					__( 'Mapping for "%2$s" skipped: path "%1$s" not found in source.', 'baton' ),
					$path,
					$target
				);
				continue;
			}

			$input[ $target ] = self::coerce_value( $value );
		}

		return array(
			'input'    => $input,
			'warnings' => $warnings,
		);
	}

	/**
	 * Sanitize a mapping list from stored/POST data.
	 *
	 * @param mixed $raw Raw mappings.
	 * @return array<int, array<string, string>>
	 */
	public static function sanitize_mappings( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $raw as $mapping ) {
			if ( ! is_array( $mapping ) ) {
				continue;
			}

			$target = isset( $mapping['target'] ) ? self::sanitize_field_name( (string) $mapping['target'] ) : '';
			$path   = isset( $mapping['path'] ) ? self::sanitize_path( (string) $mapping['path'] ) : '';
			$source = isset( $mapping['source'] ) ? sanitize_text_field( (string) $mapping['source'] ) : 'previous';

			if ( '' === $path ) {
				continue;
			}

			if ( ! in_array( $source, array( 'previous', 'initial' ), true ) ) {
				$source = 'previous';
			}

			$sanitized[] = array(
				'source' => $source,
				'path'   => $path,
				'target' => $target,
			);
		}

		return $sanitized;
	}

	/**
	 * Sanitize an input field / target name.
	 *
	 * @param string $name Raw field name.
	 * @return string
	 */
	public static function sanitize_field_name( string $name ): string {
		$name = trim( $name );
		return preg_replace( '/[^a-zA-Z0-9_-]/', '', $name ) ?? '';
	}

	/**
	 * Sanitize a dot path segment chain.
	 *
	 * @param string $path Raw path.
	 * @return string
	 */
	public static function sanitize_path( string $path ): string {
		$path = trim( $path );
		if ( '' === $path ) {
			return '';
		}

		$segments = explode( '.', $path );
		$clean    = array();

		foreach ( $segments as $segment ) {
			$segment = preg_replace( '/[^a-zA-Z0-9_-]/', '', $segment );
			if ( '' !== $segment ) {
				$clean[] = $segment;
			}
		}

		return implode( '.', $clean );
	}

	/**
	 * Coerce mapped values to sensible scalar types (e.g. numeric strings to int).
	 *
	 * @param mixed $value Raw mapped value.
	 * @return mixed
	 */
	public static function coerce_value( $value ) {
		if ( is_string( $value ) && is_numeric( $value ) ) {
			return false !== strpos( $value, '.' ) ? (float) $value : (int) $value;
		}

		return $value;
	}

	/**
	 * List top-level property keys from a JSON schema object.
	 *
	 * @param array<string, mixed> $schema JSON Schema fragment.
	 * @return array<int, string>
	 */
	public static function schema_property_keys( array $schema ): array {
		if ( ! isset( $schema['properties'] ) || ! is_array( $schema['properties'] ) ) {
			return array();
		}

		return array_keys( $schema['properties'] );
	}
}
