<?php
/**
 * @package Baton
 */

declare( strict_types=1 );

/**
 * Tests for Baton_Workflow_CPT definition sanitization.
 */
class Test_Workflow_Cpt extends WP_UnitTestCase {

	public function test_sanitize_definition_sanitizes_initial_input_and_step_input(): void {
		$raw = array(
			'initial_input' => array(
				'note' => '  trimmed  ',
			),
			'steps'         => array(
				array(
					'ability' => 'baton-test/echo',
					'input'   => array(
						'foo' => '<script>alert(1)</script>',
					),
				),
			),
		);

		$result = Baton_Workflow_CPT::sanitize_definition( $raw );

		$this->assertIsArray( $result );
		$this->assertSame( 'trimmed', $result['initial_input']['note'] );
		$this->assertCount( 1, $result['steps'] );
		$this->assertSame( 'baton-test/echo', $result['steps'][0]['ability'] );
		$this->assertStringNotContainsString( '<script>', $result['steps'][0]['input']['foo'] );
	}

	public function test_sanitize_definition_skips_steps_without_ability(): void {
		$raw = array(
			'steps' => array(
				array( 'input' => array( 'x' => 1 ) ),
			),
		);

		$result = Baton_Workflow_CPT::sanitize_definition( $raw );

		$this->assertSame( array(), $result['steps'] );
	}
}
