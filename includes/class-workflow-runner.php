<?php
/**
 * Workflow execution engine.
 *
 * @package Baton
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs workflow steps sequentially via the Abilities API.
 */
final class Baton_Workflow_Runner {

	/**
	 * Run a workflow definition.
	 *
	 * @param array<string, mixed> $definition Workflow definition.
	 * @param int                  $workflow_id Optional workflow post ID for hooks.
	 * @return array<string, mixed>
	 */
	public static function run( array $definition, int $workflow_id = 0, array $workflow_stack = array() ): array {
		$definition = wp_parse_args( $definition, Baton_Workflow_CPT::default_definition() );

		if ( $workflow_id > 0 && in_array( $workflow_id, $workflow_stack, true ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Workflow cycle detected: a workflow cannot call itself directly or indirectly.', 'baton' ),
				'steps'   => array(),
			);
		}

		if ( $workflow_id > 0 ) {
			$workflow_stack[] = $workflow_id;
		}

		$steps         = $definition['steps'] ?? array();
		$initial_input = is_array( $definition['initial_input'] ?? null ) ? $definition['initial_input'] : array();

		$report = array(
			'success' => true,
			'steps'   => array(),
		);

		if ( empty( $steps ) ) {
			$report['success'] = false;
			$report['error']   = __( 'Workflow has no steps.', 'baton' );
			return $report;
		}

		$previous_output = null;

		foreach ( $steps as $index => $step ) {
			if ( ! is_array( $step ) ) {
				continue;
			}

			$ability_slug = isset( $step['ability'] ) ? sanitize_text_field( (string) $step['ability'] ) : '';
			$static_input = $step['input'] ?? array();
			$use_previous = ! empty( $step['use_previous_output'] );
			$mappings     = Baton_Input_Mapper::sanitize_mappings( $step['input_mappings'] ?? array() );

			$step_report = array(
				'index'   => (int) $index,
				'ability' => $ability_slug,
				'success' => false,
			);

			if ( '' === $ability_slug ) {
				$step_report['error'] = __( 'Step is missing an ability.', 'baton' );
				$report['steps'][]    = $step_report;
				$report['success']    = false;
				$report['error']      = $step_report['error'];
				break;
			}

			$ability = wp_get_ability( $ability_slug );
			if ( ! $ability ) {
				$step_report['error'] = sprintf(
					/* translators: %s: ability slug */
					__( 'Ability "%s" not found.', 'baton' ),
					$ability_slug
				);
				$report['steps'][] = $step_report;
				$report['success'] = false;
				$report['error']   = $step_report['error'];
				break;
			}

			$input_schema = $ability->get_input_schema();

			$resolved = self::resolve_step_input(
				$static_input,
				$use_previous,
				$previous_output,
				0 === (int) $index ? $initial_input : array(),
				$mappings,
				$input_schema
			);

			$step_report['input']    = $resolved['input'];
			$step_report['warnings'] = $resolved['warnings'];

			/**
			 * Fires before a workflow step executes.
			 *
			 * @param int                  $workflow_id Workflow post ID.
			 * @param int                  $step_index  Step index.
			 * @param mixed                $input       Resolved input.
			 * @param array<string, mixed> $step        Step definition.
			 */
			do_action( 'baton_before_step', $workflow_id, (int) $index, $resolved['input'], $step );

			$result = self::execute_step( $ability_slug, $ability, $resolved['input'], $workflow_id, $workflow_stack );

			if ( is_wp_error( $result ) ) {
				$step_report['error'] = $result->get_error_message();
				$report['steps'][]    = $step_report;
				$report['success']    = false;
				$report['error']      = $step_report['error'];
				break;
			}

			$step_report['success'] = true;
			$step_report['output']  = $result;
			$report['steps'][]      = $step_report;
			$previous_output        = $result;

			/**
			 * Fires after a workflow step executes successfully.
			 *
			 * @param int                  $workflow_id Workflow post ID.
			 * @param int                  $step_index  Step index.
			 * @param mixed                $input       Input passed to the ability.
			 * @param mixed                $output      Ability output.
			 * @param array<string, mixed> $step        Step definition.
			 */
			do_action( 'baton_after_step', $workflow_id, (int) $index, $resolved['input'], $result, $step );
		}

		return $report;
	}

	/**
	 * Resolve input for a step.
	 *
	 * @param array<string, mixed> $static_input     Static step input.
	 * @param bool                 $use_previous     Whether to merge previous output.
	 * @param mixed                $previous_output  Previous step output.
	 * @param array<string, mixed>              $initial_input    Workflow initial input (first step only).
	 * @param array<int, array<string, string>> $mappings         Field-level input mappings.
	 * @return array{input: mixed, warnings: array<int, string>}
	 */
	public static function resolve_step_input(
		$static_input,
		bool $use_previous,
		$previous_output,
		array $initial_input = array(),
		array $mappings = array(),
		array $input_schema = array()
	): array {
		$warnings     = array();
		$has_mappings = ! empty( $mappings );

		if ( Baton_Input_Mapper::is_scalar_input_schema( $input_schema ) ) {
			$resolved = Baton_Input_Mapper::resolve_scalar_input(
				$static_input,
				$mappings,
				$previous_output,
				$initial_input
			);

			return array(
				'input'    => $resolved['input'],
				'warnings' => $resolved['warnings'],
			);
		}

		$static_array = is_array( $static_input ) ? $static_input : array();
		$input        = array();

		if ( $has_mappings ) {
			$mapped = Baton_Input_Mapper::apply_mappings(
				array(),
				$mappings,
				$previous_output,
				$initial_input
			);

			$input    = $mapped['input'];
			$warnings = array_merge( $warnings, $mapped['warnings'] );

			if ( ! empty( $static_array ) ) {
				$input = array_merge( $input, $static_array );
			}
		} else {
			$input = $static_array;
		}

		if ( ! $has_mappings && $use_previous && null !== $previous_output ) {
			if ( is_array( $previous_output ) ) {
				$input = array_merge( $previous_output, $static_array );
			} elseif ( empty( $static_array ) ) {
				$input = $previous_output;
			} else {
				$warnings[] = __(
					'Previous step output is not an array; static input was used instead.',
					'baton'
				);
			}
		}

		if ( ! $has_mappings && ! empty( $initial_input ) && is_array( $input ) ) {
			$input = array_merge( $initial_input, $input );
		} elseif ( ! $has_mappings && ! empty( $initial_input ) && empty( $input ) ) {
			$input = $initial_input;
		}

		return array(
			'input'    => $input,
			'warnings' => $warnings,
		);
	}

	/**
	 * Execute a step ability, including nested Baton workflows.
	 *
	 * @param string          $ability_slug   Ability name.
	 * @param WP_Ability|null $ability        Ability instance when already resolved.
	 * @param mixed           $input          Resolved input.
	 * @param int             $parent_id      Parent workflow post ID.
	 * @param array<int, int> $workflow_stack Active workflow stack for cycle detection.
	 * @return mixed|WP_Error
	 */
	private static function execute_step( string $ability_slug, ?WP_Ability $ability, $input, int $parent_id, array $workflow_stack ) {
		$nested_id = Baton_Workflow_Abilities::parse_workflow_ability_id( $ability_slug );
		if ( null !== $nested_id ) {
			return self::execute_nested_workflow( $nested_id, $input, $workflow_stack );
		}

		if ( ! $ability ) {
			return new WP_Error(
				'baton_ability_not_found',
				sprintf(
					/* translators: %s: ability slug */
					__( 'Ability "%s" not found.', 'baton' ),
					$ability_slug
				)
			);
		}

		return self::execute_ability( $ability, $input );
	}

	/**
	 * Run a nested workflow registered as baton/workflow-{id}.
	 *
	 * @param int             $nested_id      Nested workflow post ID.
	 * @param mixed           $input          Caller input (workflow initial_input).
	 * @param array<int, int> $workflow_stack Parent workflow stack.
	 * @return mixed|WP_Error
	 */
	private static function execute_nested_workflow( int $nested_id, $input, array $workflow_stack ) {
		$post = get_post( $nested_id );
		if ( ! $post || Baton_Workflow_CPT::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'baton_workflow_not_found',
				__( 'Nested workflow not found.', 'baton' )
			);
		}

		$definition = Baton_Workflow_CPT::get_definition( $nested_id );
		if ( is_array( $input ) && ! empty( $input ) ) {
			$definition['initial_input'] = $input;
		}

		$report = self::run( $definition, $nested_id, $workflow_stack );
		if ( ! $report['success'] ) {
			return new WP_Error(
				'baton_nested_workflow_failed',
				$report['error'] ?? __( 'Nested workflow failed.', 'baton' )
			);
		}

		$steps = $report['steps'] ?? array();
		if ( empty( $steps ) ) {
			return null;
		}

		$last = end( $steps );
		return $last['output'] ?? null;
	}

	/**
	 * Execute an ability with appropriate input.
	 *
	 * @param WP_Ability $ability Ability instance.
	 * @param mixed      $input   Resolved input.
	 * @return mixed|WP_Error
	 */
	private static function execute_ability( WP_Ability $ability, $input ) {
		$input_schema = $ability->get_input_schema();

		if ( empty( $input_schema ) ) {
			return $ability->execute();
		}

		if ( null === $input ) {
			return $ability->execute( null );
		}

		if ( is_array( $input ) && array() === $input ) {
			return $ability->execute( null );
		}

		return $ability->execute( $input );
	}
}
