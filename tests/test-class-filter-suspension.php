<?php

namespace WidgetFavorites;

class ClassFilterSuspensionTest extends \WP_UnitTestCase {

	function test_all() {
		add_filter( 'foo', 'strtoupper' );
		add_filter( 'bar', 'strrev', 20 );
		add_filter( 'baz', 'strtolower', 30 );

		$foo_value = 'value1';
		$bar_value = 'value2';

		$this->assertEquals( strtoupper( $foo_value ), apply_filters( 'foo', $foo_value ) );
		$this->assertEquals( strrev( $bar_value ), apply_filters( 'bar', $bar_value ) );
		$this->assertEquals( 10, has_filter( 'foo', 'strtoupper' ) );
		$this->assertEquals( 20, has_filter( 'bar', 'strrev' ) );
		$this->assertEquals( 30, has_filter( 'baz', 'strtolower' ) );

		$instance = new Filter_Suspension( array(
			array( 'foo', 'strtoupper' ),
			array( 'bar', 'strrev' ),
			array( 'baz', 'strtolower' ),
			array( 'quux', 'strip_tags' ),
		) );

		$this->assertEquals( strtoupper( $foo_value ), apply_filters( 'foo', $foo_value ) );
		$this->assertEquals( strrev( $bar_value ), apply_filters( 'bar', $bar_value ) );

		$removed = $instance->start();
		$this->assertEquals( 3, $removed );

		$this->assertEquals( $foo_value, apply_filters( 'foo', $foo_value ) );
		$this->assertEquals( $bar_value, apply_filters( 'bar', $bar_value ) );
		$this->assertEquals( false, has_filter( 'foo', 'strtoupper' ) );
		$this->assertEquals( false, has_filter( 'bar', 'strrev' ) );
		$this->assertEquals( false, has_filter( 'baz', 'strtolower' ) );

		$added = $instance->stop();
		$this->assertEquals( 3, $added );

		$this->assertEquals( strtoupper( $foo_value ), apply_filters( 'foo', $foo_value ) );
		$this->assertEquals( strrev( $bar_value ), apply_filters( 'bar', $bar_value ) );
		$this->assertEquals( 10, has_filter( 'foo', 'strtoupper' ) );
		$this->assertEquals( 20, has_filter( 'bar', 'strrev' ) );
		$this->assertEquals( 30, has_filter( 'baz', 'strtolower' ) );
	}

	function test_run() {
		$test = $this; // for PHP 5.3 closures

		add_filter( 'foo', 'strtoupper' );
		$foo_value = 'value1';

		$instance = new Filter_Suspension( array(
			array( 'foo', 'strtoupper' ),
		) );
		$this->assertEquals( strtoupper( $foo_value ), apply_filters( 'foo', $foo_value ) );

		$retval = $instance->run( function () use ( $foo_value, $test ) {
			$test->assertEquals( $foo_value, apply_filters( 'foo', $foo_value ) );
			return 'bard';
		} );
		$this->assertEquals( 'bard', $retval );

	}

}
