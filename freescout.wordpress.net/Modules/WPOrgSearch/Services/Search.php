<?php
/**
 * Conversation search.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSearch
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSearch\Services;

use App\Conversation;
use App\User;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

/**
 * Keeps conversation searches from reading every message, and runs them in one query instead of two.
 *
 * Core's search compares every message with `LIKE '%…%'`, which reads every thread, and then reads them all again to
 * count the results. A search with no other filters always gets an After date, so it only reads that period's threads.
 * A search that still takes too long is stopped, and the agent is asked to narrow it.
 */
final class Search {

	/**
	 * After date searches without other filters get, unless the agent picked one.
	 *
	 * @var string
	 */
	public const DEFAULT_AFTER = '-1 year';

	/**
	 * Seconds a search may run before MariaDB stops it, well within the web server's timeout.
	 *
	 * @var float
	 */
	public const TIME_LIMIT = 20.0;

	/**
	 * MariaDB's error for a statement stopped by `max_statement_time`.
	 *
	 * @var int
	 */
	private const STATEMENT_TIMEOUT = 1969;

	/**
	 * Column carrying the number of results on every row.
	 *
	 * @var string
	 */
	private const TOTAL = 'wporgsearch_total';

	/**
	 * Whether this request's search got the default date.
	 *
	 * @var bool
	 */
	private $defaulted = false;

	/**
	 * Whether this request's search took too long and was stopped.
	 *
	 * @var bool
	 */
	private $timed_out = false;

	/**
	 * Seconds a search may run.
	 *
	 * @var float
	 */
	private $time_limit;

	/**
	 * Sets how long a search may run.
	 *
	 * @param float $time_limit Seconds a search may run.
	 */
	public function __construct( float $time_limit = self::TIME_LIMIT ) {
		$this->time_limit = $time_limit;
	}

	/**
	 * Gives a search without other filters an After date, a year ago unless the agent picked one.
	 *
	 * Removing or clearing the date doesn't search everything: that's what times out.
	 *
	 * @param array  $filters Search filters from the request.
	 * @param string $q       Search query.
	 * @return array
	 */
	public function default_filters( array $filters, string $q ): array {
		if ( '' === $q || ! empty( $filters['after'] ) ) {
			return $filters;
		}

		unset( $filters['after'] );
		if ( array_filter( $filters ) ) {
			return $filters;
		}

		$filters['after'] = now()->modify( self::DEFAULT_AFTER )->format( 'Y-m-d' );
		$this->defaulted  = true;

		return $filters;
	}

	/**
	 * Whether this request's search got the default date.
	 *
	 * @return bool
	 */
	public function was_defaulted(): bool {
		return $this->defaulted;
	}

	/**
	 * Whether this request's search took too long and was stopped.
	 *
	 * @return bool
	 */
	public function timed_out(): bool {
		return $this->timed_out;
	}

	/**
	 * Searches conversations, a page at a time, like core's search.
	 *
	 * A search that takes too long comes back empty, rather than leaving it to core's search, which would be slower.
	 *
	 * @param string $q       Search query.
	 * @param array  $filters Search filters.
	 * @param User   $user    Agent searching.
	 * @return LengthAwarePaginator
	 * @throws QueryException When the search fails for another reason.
	 */
	public function conversations( string $q, array $filters, User $user ): LengthAwarePaginator {
		// Core applies this after the hook that lets modules search, on AJAX pages too.
		if ( $user->canSeeOnlyAssignedConversations() ) {
			$filters['assigned'] = $user->id;
		}

		try {
			return $this->paginate( Conversation::search( $q, $filters, $user ) );
		} catch ( QueryException $e ) {
			if ( self::STATEMENT_TIMEOUT !== (int) ( $e->errorInfo[1] ?? 0 ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Laravel's.
				throw $e;
			}

			$this->timed_out = true;

			return $this->page( collect(), 0 );
		}
	}

	/**
	 * Fetches a page of results with their total, in one query.
	 *
	 * Core's paginator counts with a second, grouped query that sends every match's row back, as slow as the search.
	 *
	 * @param EloquentBuilder $query Search.
	 * @return LengthAwarePaginator
	 */
	private function paginate( EloquentBuilder $query ): LengthAwarePaginator {
		$query->addSelect( \DB::raw( 'COUNT(*) OVER() AS ' . self::TOTAL ) )->forPage( Paginator::resolveCurrentPage(), Conversation::DEFAULT_LIST_SIZE );
		$rows = $this->with_time_limit(
			static function () use ( $query ): Collection {
				return $query->get();
			}
		);

		// A page past the last one has no rows to carry the total.
		$total = $rows->isEmpty() ? 0 : (int) $rows->first()->{ self::TOTAL };
		foreach ( $rows as $row ) {
			unset( $row->{ self::TOTAL } );
		}

		return $this->page( $rows, $total );
	}

	/**
	 * Runs a query under the time limit, on MariaDB, through its `max_statement_time`.
	 *
	 * @param callable $run Runs the query.
	 * @return mixed What the query returns.
	 */
	private function with_time_limit( callable $run ) {
		$connection = \DB::connection();
		$version    = (string) $connection->getPdo()->getAttribute( \PDO::ATTR_SERVER_VERSION );
		if ( 'mysql' !== $connection->getDriverName() || false === stripos( $version, 'MariaDB' ) ) {
			return $run();
		}

		$previous = (float) $connection->selectOne( 'SELECT @@max_statement_time AS `limit`' )->limit;
		$connection->statement( 'SET SESSION max_statement_time = ' . $this->time_limit );

		try {
			return $run();
		} finally {
			$connection->statement( 'SET SESSION max_statement_time = ' . $previous );
		}
	}

	/**
	 * A page of results, like core's.
	 *
	 * @param Collection $rows  Conversations on the page.
	 * @param int        $total Number of results on every page.
	 * @return LengthAwarePaginator
	 */
	private function page( Collection $rows, int $total ): LengthAwarePaginator {
		return new LengthAwarePaginator(
			$rows,
			$total,
			Conversation::DEFAULT_LIST_SIZE,
			Paginator::resolveCurrentPage(),
			array(
				'path'     => Paginator::resolveCurrentPath(),
				'pageName' => 'page',
			)
		);
	}
}
