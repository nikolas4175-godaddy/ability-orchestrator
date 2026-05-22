<?php
/**
 * Sanitizes workflow JSON input (initial_input, per-step static input).
 *
 * @package Baton
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Recursively sanitizes admin-scoped JSON while preserving structure for abilities.
 */
final class Baton_Input_Sanitizer {

	public const MAX_DEPTH = 10;

	/**
	 * Sanitize a value intended as ability input (object/array root).
	 *
	 * @param mixed $value Raw value.
	 * @return array<string, mixed>
	 */
	public static function sanitize_input_array( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$sanitized = self::sanitize_json_value( $value );

		return is_array( $sanitized ) ? $sanitized : array();
	}

	/**
	 * Recursively sanitize JSON-compatible data.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $depth Current recursion depth.
	 * @return mixed Sanitized value (array, scalar, or null).
	 */
	public static function sanitize_json_value( $value, int $depth = 0 ) {
		if ( $depth >= self::MAX_DEPTH ) {
			return array();
		}

		if ( is_array( $value ) ) {
			$sanitized = array();

			foreach ( $value as $key => $item ) {
				if ( is_string( $key ) ) {
					$key = self::sanitize_object_key( $key );
					if ( '' === $key ) {
						continue;
					}
				}

				$sanitized[ $key ] = self::sanitize_json_value( $item, $depth + 1 );
			}

			return $sanitized;
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return sanitize_text_field( $value );
		}

		if ( null === $value ) {
			return null;
		}

		return array();
	}

	/**
	 * Sanitize an associative array key.
	 *
	 * @param string $key Raw key.
	 * @return string
	 */
	public static function sanitize_object_key( string $key ): string {
		$key = trim( $key );

		return preg_replace( '/[^a-zA-Z0-9_-]/', '', $key ) ?? '';
	}
}
