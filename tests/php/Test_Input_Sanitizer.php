<?php
/**
 * @package Baton
 */

declare( strict_types=1 );

/**
 * Tests for Baton_Input_Sanitizer.
 */
class Test_Input_Sanitizer extends WP_UnitTestCase {

	public function test_sanitize_input_array_rejects_non_array(): void {
		$this->assertSame( array(), Baton_Input_Sanitizer::sanitize_input_array( 'nope' ) );
	}

	public function test_sanitize_string_leaf(): void {
		$raw = array(
			'message' => '  hello <b>world</b>  ',
		);

		$out = Baton_Input_Sanitizer::sanitize_input_array( $raw );

		$this->assertSame( 'hello world', $out['message'] );
	}

	public function test_sanitize_preserves_scalars_and_nested_structure(): void {
		$raw = array(
			'count'   => 3,
			'enabled' => true,
			'nested'  => array(
				'plan_id' => 'abc-123',
			),
		);

		$out = Baton_Input_Sanitizer::sanitize_input_array( $raw );

		$this->assertSame( 3, $out['count'] );
		$this->assertTrue( $out['enabled'] );
		$this->assertSame( 'abc-123', $out['nested']['plan_id'] );
	}

	public function test_sanitize_strips_invalid_object_keys(): void {
		$raw = array(
			'valid_key'   => 'ok',
			'bad key!'    => 'drop-me',
			'also-valid'  => 1,
		);

		$out = Baton_Input_Sanitizer::sanitize_input_array( $raw );

		$this->assertArrayHasKey( 'valid_key', $out );
		$this->assertArrayHasKey( 'also-valid', $out );
		$this->assertArrayNotHasKey( 'bad key!', $out );
	}

	public function test_max_depth_truncates_deep_trees(): void {
		$raw = array( 'leaf' => 'deep' );

		// Nest deeper than Baton_Input_Sanitizer::MAX_DEPTH (10).
		for ( $i = 0; $i < 12; $i++ ) {
			$raw = array( 'level_' . $i => $raw );
		}

		$out = Baton_Input_Sanitizer::sanitize_input_array( $raw );

		$this->assertIsArray( $out );
		$this->assertNotEmpty( $out );
	}
}
