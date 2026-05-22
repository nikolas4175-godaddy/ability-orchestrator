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

		wp_set_current_user( 1 );

		if ( ! function_exists( 'baton_tests_ensure_abilities_registered' ) ) {
			require_once dirname( __DIR__ ) . '/fixtures/test-abilities.php';
		}

		$registration_error = baton_tests_ensure_abilities_registered();
		if ( '' !== $registration_error ) {
			$this->fail( $registration_error );
		}

		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( 'baton-test/echo' ) ) {
			$this->fail( 'Test ability baton-test/echo is not registered after ensure.' );
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

		$this->assertTrue(
			$report['success'],
			isset( $report['error'] ) ? (string) $report['error'] : wp_json_encode( $report )
		);
		$this->assertCount( 1, $report['steps'] );
		$this->assertTrue(
			$report['steps'][0]['success'],
			isset( $report['steps'][0]['error'] ) ? (string) $report['steps'][0]['error'] : wp_json_encode( $report['steps'][0] )
		);
		$this->assertSame( 'world', $report['steps'][0]['output']['hello'] ?? null );
	}
}
