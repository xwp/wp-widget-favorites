<?php


namespace WidgetFavorites;

/**
 * Remove any present filters before some action, and then restore afterward.
 */
class Filter_Suspension {

	/**
	 * @var array[array]
	 */
	protected $filters = array();

	/**
	 * @var array[array(
	 *     @var string $hook
	 *     @var string $callback
	 *     @var string $priority
	 * )]
	 */
	protected $suspended_filters = array();

	/**
	 * @param array[array] $filter_hook_callback_pairs
	 * @throws Exception
	 */
	function __construct( $filter_hook_callback_pairs ) {
		$this->filters = $filter_hook_callback_pairs;

		foreach ( $this->filters as $pair ) {
			if ( ! is_array( $pair ) || 2 !== count( $pair ) ) {
				throw new Exception( 'Expected array of 2-element arrays to be passed into Filter_Suspension constructor' );
			}
			if ( ! is_callable( $pair[1], true ) ) {
				throw new Exception( 'Illegal callback for filter' );
			}
		}
	}

	/**
	 * Start suspending filters
	 *
	 * @return int Number of filters removed.
	 */
	function start() {
		$filters_removed = 0;

		foreach ( $this->filters as $pair ) {
			list( $hook, $callback ) = $pair;
			$priority = has_filter( $hook, $callback );
			if ( false !== $priority ) {
				remove_filter( $hook, $callback, $priority );
				$this->suspended_filters[] = compact( 'hook', 'callback', 'priority' );
				$filters_removed += 1;
			}
		}

		return $filters_removed;
	}

	/**
	 * Stop suspending filters
	 *
	 * @return int Number of filters added.
	 */
	function stop() {
		$filters_restored = 0;
		while ( count( $this->suspended_filters ) > 0 ) {
			$filter = array_pop( $this->suspended_filters );
			$filters_restored += 1;
			add_filter( $filter['hook'], $filter['callback'], $filter['priority'], PHP_INT_MAX );
		}
		return $filters_restored;
	}

	/**
	 * Run the supplied function with the filters disabled.
	 *
	 * @param callable $callback
	 *
	 * @return mixed return value of the callback
	 */
	function run( $callback ) {
		$this->start();
		$retval = call_user_func( $callback );
		$this->stop();
		return $retval;
	}

}
