<?php
/**
 * Workflow custom post type.
 *
 * @package Baton
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the baton_workflow post type.
 */
final class Baton_Workflow_CPT {

	public const POST_TYPE = 'baton_workflow';

	public const META_DEFINITION = '_baton_workflow_definition';

	/**
	 * Register post type.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_post_type' ) );
	}

	/**
	 * Register baton_workflow CPT.
	 */
	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Baton', 'baton' ),
					'singular_name' => __( 'Ability Workflow', 'baton' ),
					'add_new'       => __( 'Add Workflow', 'baton' ),
					'add_new_item'  => __( 'Add New Workflow', 'baton' ),
					'edit_item'     => __( 'Edit Workflow', 'baton' ),
					'view_item'     => __( 'View Workflow', 'baton' ),
					'search_items'  => __( 'Search Workflows', 'baton' ),
					'not_found'     => __( 'No workflows found.', 'baton' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'supports'            => array( 'title', 'excerpt' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'delete_with_user'    => false,
			)
		);
	}

	/**
	 * Default workflow definition.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_definition(): array {
		return array(
			'initial_input' => array(),
			'steps'         => array(),
		);
	}

	/**
	 * Get workflow definition for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public static function get_definition( int $post_id ): array {
		$stored = get_post_meta( $post_id, self::META_DEFINITION, true );

		if ( ! is_array( $stored ) ) {
			return self::default_definition();
		}

		return wp_parse_args(
			$stored,
			self::default_definition()
		);
	}

	/**
	 * Save workflow definition.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $definition Definition array.
	 */
	public static function save_definition( int $post_id, array $definition ): void {
		update_post_meta( $post_id, self::META_DEFINITION, $definition );
	}

	/**
	 * Sanitize definition from request/JSON.
	 *
	 * @param array<string, mixed> $raw Raw definition.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function sanitize_definition( array $raw ) {
		$definition = self::default_definition();

		if ( isset( $raw['initial_input'] ) ) {
			$definition['initial_input'] = Baton_Input_Sanitizer::sanitize_input_array( $raw['initial_input'] );
		}

		if ( ! isset( $raw['steps'] ) || ! is_array( $raw['steps'] ) ) {
			return $definition;
		}

		foreach ( $raw['steps'] as $step ) {
			if ( ! is_array( $step ) ) {
				continue;
			}

			$ability = isset( $step['ability'] ) ? sanitize_text_field( (string) $step['ability'] ) : '';
			if ( '' === $ability ) {
				continue;
			}

			$input = Baton_Input_Sanitizer::sanitize_input_array( $step['input'] ?? array() );

			$definition['steps'][] = array(
				'ability'             => $ability,
				'input'               => $input,
				'use_previous_output' => ! empty( $step['use_previous_output'] ),
				'input_mappings'      => Baton_Input_Mapper::sanitize_mappings( $step['input_mappings'] ?? array() ),
			);
		}

		return $definition;
	}
}
