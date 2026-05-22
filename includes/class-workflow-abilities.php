<?php
/**
 * Registers saved workflows as Abilities API abilities.
 *
 * @package Baton
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes each baton_workflow as baton/workflow-{post_id}.
 */
final class Baton_Workflow_Abilities {

	public const NAMESPACE = 'baton';

	public const ABILITY_PREFIX = 'baton/workflow-';

	public const CATEGORY = 'baton-workflows';

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'wp_abilities_api_categories_init', array( self::class, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( self::class, 'register_all_workflows' ) );
		add_action( 'save_post_' . Baton_Workflow_CPT::POST_TYPE, array( self::class, 'on_workflow_saved' ), 20, 2 );
		add_action( 'trashed_post', array( self::class, 'on_workflow_deleted' ) );
		add_action( 'deleted_post', array( self::class, 'on_workflow_deleted' ) );
	}

	/**
	 * Register the Baton workflows category.
	 */
	public static function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Baton Workflows', 'baton' ),
				'description' => __( 'Composed workflows built from other abilities.', 'baton' ),
			)
		);
	}

	/**
	 * Register abilities for all published workflows.
	 */
	public static function register_all_workflows(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$ids = get_posts(
			array(
				'post_type'      => Baton_Workflow_CPT::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $ids as $post_id ) {
			self::register_workflow_ability( (int) $post_id );
		}
	}

	/**
	 * Re-register when a workflow is saved.
	 *
	 * @param int      $post_id Post ID.
	 * @param WP_Post  $post    Post object.
	 */
	public static function on_workflow_saved( int $post_id, WP_Post $post ): void {
		if ( Baton_Workflow_CPT::POST_TYPE !== $post->post_type ) {
			return;
		}

		if ( 'publish' !== $post->post_status ) {
			self::unregister_workflow_ability( $post_id );
			return;
		}

		self::register_workflow_ability( $post_id );
	}

	/**
	 * Unregister when a workflow is deleted or trashed.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_workflow_deleted( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post || Baton_Workflow_CPT::POST_TYPE !== $post->post_type ) {
			return;
		}

		self::unregister_workflow_ability( $post_id );
	}

	/**
	 * Ability name for a workflow post.
	 *
	 * @param int $post_id Workflow post ID.
	 * @return string
	 */
	public static function get_ability_name( int $post_id ): string {
		return self::ABILITY_PREFIX . $post_id;
	}

	/**
	 * Parse workflow post ID from a baton/workflow-{id} ability name.
	 *
	 * @param string $ability_name Ability name.
	 * @return int|null Post ID or null.
	 */
	public static function parse_workflow_ability_id( string $ability_name ): ?int {
		if ( 0 !== strpos( $ability_name, self::ABILITY_PREFIX ) ) {
			return null;
		}

		$id = (int) substr( $ability_name, strlen( self::ABILITY_PREFIX ) );
		return $id > 0 ? $id : null;
	}

	/**
	 * Register one workflow as an ability.
	 *
	 * @param int $post_id Workflow post ID.
	 */
	public static function register_workflow_ability( int $post_id ): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || Baton_Workflow_CPT::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return;
		}

		$name = self::get_ability_name( $post_id );

		if ( function_exists( 'wp_unregister_ability' ) ) {
			wp_unregister_ability( $name );
		}

		$definition = Baton_Workflow_CPT::get_definition( $post_id );
		$output_schema = self::infer_output_schema( $definition );

		wp_register_ability(
			$name,
			array(
				'label'               => $post->post_title,
				'description'         => $post->post_excerpt ?: sprintf(
					/* translators: %d: workflow post ID */
					__( 'Baton workflow #%d', 'baton' ),
					$post_id
				),
				'category'            => self::CATEGORY,
				'input_schema'        => self::get_input_schema(),
				'output_schema'       => $output_schema,
				'execute_callback'    => static function ( $input = null ) use ( $post_id ) {
					return self::execute_workflow_ability( $post_id, $input );
				},
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => false,
				),
			)
		);
	}

	/**
	 * Unregister a workflow ability.
	 *
	 * @param int $post_id Workflow post ID.
	 */
	public static function unregister_workflow_ability( int $post_id ): void {
		if ( ! function_exists( 'wp_unregister_ability' ) ) {
			return;
		}

		wp_unregister_ability( self::get_ability_name( $post_id ) );
	}

	/**
	 * Default input schema for workflow abilities.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_input_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'description'          => __( 'Optional workflow-level input merged into the first step.', 'baton' ),
		);
	}

	/**
	 * Infer output schema from the last step ability when possible.
	 *
	 * @param array<string, mixed> $definition Workflow definition.
	 * @return array<string, mixed>
	 */
	private static function infer_output_schema( array $definition ): array {
		$steps = $definition['steps'] ?? array();
		if ( empty( $steps ) ) {
			return array(
				'type'                 => 'object',
				'additionalProperties' => true,
			);
		}

		$last = end( $steps );
		$slug = is_array( $last ) && isset( $last['ability'] ) ? (string) $last['ability'] : '';

		if ( '' !== $slug ) {
			$nested_id = self::parse_workflow_ability_id( $slug );
			if ( null !== $nested_id ) {
				$nested_def = Baton_Workflow_CPT::get_definition( $nested_id );
				$nested_out = self::infer_output_schema( $nested_def );
				if ( ! empty( $nested_out ) ) {
					return $nested_out;
				}
			}

			$ability = wp_get_ability( $slug );
			if ( $ability ) {
				$schema = $ability->get_output_schema();
				if ( ! empty( $schema ) ) {
					return $schema;
				}
			}
		}

		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
		);
	}

	/**
	 * Execute callback for a registered workflow ability.
	 *
	 * @param int   $post_id Workflow post ID.
	 * @param mixed $input   Optional workflow input.
	 * @return mixed|WP_Error
	 */
	public static function execute_workflow_ability( int $post_id, $input = null ) {
		$definition = Baton_Workflow_CPT::get_definition( $post_id );

		if ( is_array( $input ) && ! empty( $input ) ) {
			$definition['initial_input'] = Baton_Input_Sanitizer::sanitize_input_array( $input );
		}

		$report = Baton_Workflow_Runner::run( $definition, $post_id );

		if ( ! $report['success'] ) {
			return new WP_Error(
				'baton_workflow_failed',
				$report['error'] ?? __( 'Workflow failed.', 'baton' )
			);
		}

		$steps = $report['steps'] ?? array();
		if ( empty( $steps ) ) {
			return null;
		}

		$last = end( $steps );
		return $last['output'] ?? null;
	}
}
