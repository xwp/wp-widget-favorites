<?php

namespace WidgetFavorites;

class ClassFilterSuspensionTest extends \WP_UnitTestCase {

	function test_all() {
		add_filter( 'foo', 'strtoupper' );
		add_filter( 'bar', 'strrev', 20 );

		$foo_value = 'value1';
		$bar_value = 'value2';

		$this->assertEquals( strtoupper( $foo_value ), apply_filters( 'foo', $foo_value ) );
		$this->assertEquals( strrev( $bar_value ), apply_filters( 'bar', $bar_value ) );

		$instance = new Filter_Suspension( array(
			array( 'foo', 'strtoupper' ),
			array( 'bar', 'strrev' ),
		) );

		$this->assertEquals( strtoupper( $foo_value ), apply_filters( 'foo', $foo_value ) );
		$this->assertEquals( strrev( $bar_value ), apply_filters( 'bar', $bar_value ) );

		$instance->start();

		$this->assertEquals( $foo_value, apply_filters( 'foo', $foo_value ) );
		$this->assertEquals( $bar_value, apply_filters( 'bar', $bar_value ) );

		$instance->stop();

		$this->assertEquals( strtoupper( $foo_value ), apply_filters( 'foo', $foo_value ) );
		$this->assertEquals( strrev( $bar_value ), apply_filters( 'bar', $bar_value ) );
	}

}
