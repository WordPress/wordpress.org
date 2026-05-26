<?php
/**
 * Test fake for the database driver Trac_Notifications_DB expects.
 *
 * @package WordPressdotorg\Trac
 */

/**
 * Records calls and returns scripted responses. Each `get_*` method has a
 * paired `$_returns` queue (drained FIFO) and a `$_calls` log for assertions.
 */
class Fake_DB {

	/**
	 * Log of calls to prepare(): each entry has query + args.
	 *
	 * @var array<int, array{query: string, args: array}>
	 */
	public $prepare_calls = array();

	/**
	 * Log of queries passed to get_row().
	 *
	 * @var array<int, string>
	 */
	public $get_row_calls = array();

	/**
	 * Log of queries passed to get_results().
	 *
	 * @var array<int, string>
	 */
	public $get_results_calls = array();

	/**
	 * Log of queries passed to get_col().
	 *
	 * @var array<int, string>
	 */
	public $get_col_calls = array();

	/**
	 * Log of queries passed to get_var().
	 *
	 * @var array<int, string>
	 */
	public $get_var_calls = array();

	/**
	 * Queued return values for get_row().
	 *
	 * @var array
	 */
	public $get_row_returns = array();

	/**
	 * Queued return values for get_results().
	 *
	 * @var array
	 */
	public $get_results_returns = array();

	/**
	 * Queued return values for get_col().
	 *
	 * @var array
	 */
	public $get_col_returns = array();

	/**
	 * Queued return values for get_var().
	 *
	 * @var array
	 */
	public $get_var_returns = array();

	/**
	 * Mimic wpdb::prepare(). Returns the raw query for assertion convenience;
	 * arg substitution is not performed since the production DAO casts numeric
	 * args before calling prepare().
	 *
	 * @param string $query SQL with placeholders.
	 * @param mixed  ...$args Placeholder values.
	 * @return string
	 */
	public function prepare( $query, ...$args ) {
		$this->prepare_calls[] = array(
			'query' => $query,
			'args'  => $args,
		);
		return $query;
	}

	/**
	 * Mimic wpdb::get_row().
	 *
	 * @param string $query  Prepared query.
	 * @param string $output Output type (ignored).
	 * @return mixed
	 */
	public function get_row( $query, $output = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- mirrors wpdb signature.
		$this->get_row_calls[] = $query;
		return array_shift( $this->get_row_returns );
	}

	/**
	 * Mimic wpdb::get_results().
	 *
	 * @param string $query  Prepared query.
	 * @param string $output Output type (ignored).
	 * @return mixed
	 */
	public function get_results( $query, $output = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- mirrors wpdb signature.
		$this->get_results_calls[] = $query;
		return array_shift( $this->get_results_returns );
	}

	/**
	 * Mimic wpdb::get_col().
	 *
	 * @param string $query Prepared query.
	 * @return mixed
	 */
	public function get_col( $query ) {
		$this->get_col_calls[] = $query;
		return array_shift( $this->get_col_returns );
	}

	/**
	 * Mimic wpdb::get_var().
	 *
	 * @param string $query Prepared query.
	 * @return mixed
	 */
	public function get_var( $query ) {
		$this->get_var_calls[] = $query;
		return array_shift( $this->get_var_returns );
	}
}
