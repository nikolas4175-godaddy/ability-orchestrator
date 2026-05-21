<?php
/**
 * JSON Schema path helpers for the workflow editor UI.
 *
 * @package Baton
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds path catalogs and I/O summaries from ability schemas.
 */
final class Baton_Schema_Paths {

	/**
	 * Human-readable type label from a JSON schema type.
	 *
	 * @param array<string, mixed> $schema Schema fragment.
	 * @return string
	 */
	public static function type_label( array $schema ): string {
		$type = $schema['type'] ?? '';

		if ( is_array( $type ) ) {
			$type = $type[0] ?? 'string';
		}

		switch ( $type ) {
			case 'integer':
				return __( 'Integer', 'baton' );
			case 'number':
				return __( 'Number', 'baton' );
			case 'boolean':
				return __( 'Boolean', 'baton' );
			case 'string':
				return __( 'String', 'baton' );
			case 'array':
				return __( 'Array', 'baton' );
			case 'object':
				return __( 'Object', 'baton' );
			default:
				return __( 'Value', 'baton' );
		}
	}

	/**
	 * I/O kind for UI behavior.
	 *
	 * @param array<string, mixed> $schema Schema.
	 * @return string single_value|object|array|unknown
	 */
	public static function io_kind( array $schema ): string {
		if ( Baton_Input_Mapper::is_scalar_input_schema( $schema ) ) {
			return 'single_value';
		}

		$type = $schema['type'] ?? null;

		if ( 'array' === $type ) {
			return 'array';
		}

		if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) && ! empty( $schema['properties'] ) ) {
			return 'object';
		}

		if ( 'object' === $type ) {
			return 'object';
		}

		return 'unknown';
	}

	/**
	 * Short summary for step node chips.
	 *
	 * @param array<string, mixed> $schema Input or output schema.
	 * @return array{kind: string, type_label: string, summary: string}
	 */
	public static function io_summary( array $schema ): array {
		$kind       = self::io_kind( $schema );
		$type_label = self::type_label( $schema );

		if ( 'single_value' === $kind ) {
			return array(
				'kind'       => $kind,
				'type_label' => $type_label,
				'summary'    => $type_label,
			);
		}

		if ( 'array' === $kind ) {
			$paths   = self::get_output_paths( $schema );
			$preview = self::preview_path_labels( $paths, 3 );

			return array(
				'kind'       => $kind,
				'type_label' => $type_label,
				'summary'    => $preview ? $type_label . ' · ' . $preview : $type_label,
			);
		}

		if ( 'object' === $kind ) {
			$keys    = Baton_Input_Mapper::schema_property_keys( $schema );
			$preview = self::preview_keys( $keys, 4 );

			return array(
				'kind'       => $kind,
				'type_label' => $type_label,
				'summary'    => $preview ? $type_label . ' · ' . $preview : $type_label,
			);
		}

		return array(
			'kind'       => 'unknown',
			'type_label' => $type_label,
			'summary'    => $type_label,
		);
	}

	/**
	 * Output path options for source dropdowns.
	 *
	 * @param array<string, mixed> $schema Output schema.
	 * @return array{paths: array<int, array{value: string, label: string}>, selectable: bool, display: array{label: string}|null}
	 */
	public static function output_path_catalog( array $schema ): array {
		$kind = self::io_kind( $schema );

		if ( 'single_value' === $kind ) {
			return array(
				'paths'      => array(),
				'selectable' => false,
				'display'    => array(
					'label' => sprintf(
						/* translators: %s: type name e.g. Integer */
						__( 'Previous step output (%s)', 'baton' ),
						self::type_label( $schema )
					),
				),
			);
		}

		$paths = self::get_output_paths( $schema );

		return array(
			'paths'      => $paths,
			'selectable' => ! empty( $paths ),
			'display'    => null,
		);
	}

	/**
	 * Input target options for target dropdowns.
	 *
	 * @param array<string, mixed> $schema Input schema.
	 * @return array{targets: array<int, array{value: string, label: string}>, selectable: bool, display: array{label: string}|null}
	 */
	public static function input_target_catalog( array $schema ): array {
		if ( Baton_Input_Mapper::is_scalar_input_schema( $schema ) ) {
			$type = Baton_Input_Mapper::get_scalar_type( $schema );

			return array(
				'targets'    => array(),
				'selectable' => false,
				'display'    => array(
					'label' => sprintf(
						/* translators: %s: type name e.g. Integer */
						__( '%s — entire input', 'baton' ),
						ucfirst( $type )
					),
				),
			);
		}

		$keys    = Baton_Input_Mapper::schema_property_keys( $schema );
		$targets = array();

		foreach ( $keys as $key ) {
			$targets[] = array(
				'value' => $key,
				'label' => $key,
			);
		}

		return array(
			'targets'    => $targets,
			'selectable' => ! empty( $targets ),
			'display'    => null,
		);
	}

	/**
	 * Enumerate dot paths for an output schema.
	 *
	 * @param array<string, mixed> $schema Output schema.
	 * @return array<int, array{value: string, label: string}>
	 */
	public static function get_output_paths( array $schema ): array {
		$paths = array();
		$type  = $schema['type'] ?? null;

		if ( 'array' === $type && isset( $schema['items'] ) && is_array( $schema['items'] ) ) {
			$item_props = Baton_Input_Mapper::schema_property_keys( $schema['items'] );
			foreach ( $item_props as $prop ) {
				$path    = '0.' . $prop;
				$paths[] = array(
					'value' => $path,
					'label' => $path,
				);
			}
			return $paths;
		}

		foreach ( Baton_Input_Mapper::schema_property_keys( $schema ) as $key ) {
			$paths[] = array(
				'value' => $key,
				'label' => $key,
			);
		}

		return $paths;
	}

	/**
	 * Preview string from path option labels.
	 *
	 * @param array<int, array{value: string, label: string}> $paths Path options.
	 * @param int                                             $max   Max items.
	 * @return string
	 */
	private static function preview_path_labels( array $paths, int $max ): string {
		$labels = array();
		foreach ( array_slice( $paths, 0, $max ) as $path ) {
			$labels[] = $path['label'];
		}

		if ( count( $paths ) > $max ) {
			$labels[] = '…';
		}

		return implode( ', ', $labels );
	}

	/**
	 * Preview string from property keys.
	 *
	 * @param array<int, string> $keys Keys.
	 * @param int                $max  Max keys.
	 * @return string
	 */
	private static function preview_keys( array $keys, int $max ): string {
		$slice = array_slice( $keys, 0, $max );
		if ( count( $keys ) > $max ) {
			$slice[] = '…';
		}
		return implode( ', ', $slice );
	}
}
