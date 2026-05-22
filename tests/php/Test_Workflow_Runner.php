<?php
/**
 * @package Baton
 */

declare( strict_types=1 );

/**
 * Tests for Baton_Workflow_Runner.
 */
class Test_Workflow_Runner extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		if ( ! function_exists( 'wp_get_abilities' ) ) {
			$this->markTestSkipped( 'Abilities API is not available in this WordPress version.' );
		}
	}

	public function test_cycle_detection(): void {
		$definition = array(
			'steps' => array(
				array(
					'ability' => 'baton-test/echo',
					'input'   => array(),
				),
			),
		);

		$report = Baton_Workflow_Runner::run( $definition, 42, array( 42 ) );

		$this->assertFalse( $report['success'] );
		$this->assertStringContainsString( 'cycle', strtolower( (string) ( $report['error'] ?? '' ) ) );
	}

	public function test_run_echo_ability_step(): void {
		$definition = array(
			'initial_input' => array(),
			'steps'         => array(
				array(
					'ability' => 'baton-test/echo',
					'input'   => array(
						'hello' => 'world',
					),
				),
			),
		);

		$report = Baton_Workflow_Runner::run( $definition );

		$this->assertTrue( $report['success'] );
		$this->assertCount( 1, $report['steps'] );
		$this->assertTrue( $report['steps'][0]['success'] );
		$this->assertSame( 'world', $report['steps'][0]['output']['hello'] ?? null );
	}
}
