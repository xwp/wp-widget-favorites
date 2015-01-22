<?php


namespace WidgetFavorites;

/**
 * Remove any present filters before some action, and then restore afterward.
 */
class Filter_Suspension {

	/**
	 * @var array[array(
	 *     @var string $hook
	 *     @var string $callback
	 *     @var string $priority
	 * )]
	 */
	public $suspended_filters = array();

	/**
	 * @param array[array] $filter_hook_callback_pairs
	 * @throws Exception
	 */
	function __construct( $filter_hook_callback_pairs ) {
		foreach ( $filter_hook_callback_pairs as $pair ) {
			if ( ! is_array( $pair ) || 2 !== count( $pair ) ) {
				throw new Exception( 'Expected array of 2-element arrays to be passed into Filter_Suspension constructor' );
			}
			list( $hook, $callback ) = $pair;
			if ( ! is_callable( $callback, true ) ) {
				throw new Exception( 'Illegal callback for filter' );
			}
			$priority = has_filter( $hook, $callback );
			if ( false !== $priority ) {
				$this->suspended_filters[] = compact( 'hook', 'callback', 'priority' );
			}
		}
	}

	/**
	 * Start suspending filters
	 */
	function start() {
		foreach ( $this->suspended_filters as $suspended_filter ) {
			remove_filter( $suspended_filter['hook'], $suspended_filter['callback'], $suspended_filter['priority'] );
		}
	}

	/**
	 * Stop suspending filters
	 */
	function stop() {
		foreach ( $this->suspended_filters as $suspended_filter ) {
			add_filter( $suspended_filter['hook'], $suspended_filter['callback'], $suspended_filter['priority'], PHP_INT_MAX );
		}
	}

}
